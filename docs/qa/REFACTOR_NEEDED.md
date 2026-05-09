# Refactor candidates — surfaced by 2026-05-09 audit

This document tracks architectural debt that the wiring + journey audits surfaced. Each entry is something the team should refactor properly rather than patch around. The audits I ran during ready-up (action-audit, wppqa, parallel CLI journey walks) intentionally avoided patching over these — they're flagged here so the proper fix lands as planned work, not stealth band-aids.

> Per "no patch work — flag refactors" directive (2026-05-09).

---

## R1. Duplicate post_status allowlists in employers-endpoint.php

**Symptom:** the same `array( 'publish', 'pending', 'draft', 'wcb_closed', 'wcb_expired' )` literal is repeated at 3 sites in `api/endpoints/class-employers-endpoint.php`:

| Line | Method | Allowlist correctness (pre-2026-05-09) |
|---|---|---|
| 532 | `get_jobs_for_company()` (one of the my-jobs paths) | correct (5 statuses) |
| 600 | `get_my_jobs()` (employer dashboard) | correct (5 statuses, gated by owner/admin) |
| 715 | `get_applications()` (employer per-company applicants) | **was missing wcb_closed + wcb_expired** — patched in same session |

I made the 1-line fix (line 715) to align with the other two sites. **That fix is a patch** — the deeper bug class is "three places to update when the lifecycle adds a new status" (e.g., `wcb_paused`, `wcb_pending_approval`, etc.).

**Proposed refactor:**
```php
// In Employers_Endpoint:
private function owner_visible_statuses( bool $is_owner_or_admin ): array {
    return $is_owner_or_admin
        ? array( 'publish', 'pending', 'draft', 'wcb_closed', 'wcb_expired' )
        : array( 'publish' );
}
```
Replace all 3 array literals with `$this->owner_visible_statuses($is_owner)`. Future status additions touch one place.

**Effort:** small (15 min, one-file change). **Blast radius:** small (3 call sites in same file). **Tests needed:** existing journeys cover all 3 paths.

---

## R2. Company brand-meta serialization scattered across endpoints

**Symptom:** the 4 company brand fields (`tagline`, `industry`, `size_label`, `hq`) are read from postmeta in two places that should be one:

| File | Lines | Reads keys | Format helper |
|---|---|---|---|
| `api/endpoints/class-companies-endpoint.php` | 248-252 | `_wcb_tagline`, `_wcb_industry`, `_wcb_company_size`, `_wcb_hq_location` | `size_label()` private method, lines 345-360 |
| `api/endpoints/class-jobs-endpoint.php` | 1029-1041 | same 4 keys | `size_label()` private method (I added it during the Bug A fix — duplicate of the companies-endpoint one) |

I added the 4 reads + the size_label helper to jobs-endpoint as the Bug A fix. **That's patch work.** When the team adds a 5th brand field (e.g., `founded_year`, `employee_count_label`), 2 places need to update — and someone WILL forget.

**Proposed refactor:** extract a `Company_Meta_Shape` trait or static class with a single `serialize_for_response( int $company_id ): array` method. Both endpoints consume it.

```php
// core/class-company-meta-shape.php
final class Company_Meta_Shape {
    public static function serialize( int $company_id ): array {
        $size = (string) get_post_meta( $company_id, '_wcb_company_size', true );
        return array(
            'tagline'      => (string) get_post_meta( $company_id, '_wcb_tagline', true ),
            'industry'     => (string) get_post_meta( $company_id, '_wcb_industry', true ),
            'size'         => $size,
            'size_label'   => self::size_label( $size ),
            'hq'           => (string) get_post_meta( $company_id, '_wcb_hq_location', true ),
        );
    }
    private static function size_label( string $size ): string { /* the labels map */ }
}
```

Both endpoints then do `array_merge( $base_response, Company_Meta_Shape::serialize( $company_id ) )`. New brand fields land in one place.

**Effort:** small (30 min). **Blast radius:** 2 endpoints. **Tests:** the existing apply-to-job + employer-edit-company journeys cover both consumers.

---

## R3. Ability permission callbacks all duplicate the same admin-fallback + don't honor employer-ban meta

**Symptom (1):** every ability in `core/class-abilities.php` has the same shape:
```php
'permission_callback' => static function (): bool {
    $user = wp_get_current_user();
    return $user && ( $user->has_cap( 'wcb_<specific>' ) || $user->has_cap( 'manage_options' ) );
},
```
13 abilities, 13 copies. Adding a new global guard (e.g., `_wcb_employer_banned`) requires editing all 13 (or only the relevant subset, but figuring out which is error-prone).

**Symptom (2) — the bug Card 9874928178 surfaces:** none of these callbacks consult `_wcb_employer_banned`. The admin "ban employer" action persists the meta but never enforces it — banned employers continue to post jobs, manage their company, etc. Patching that into the `wcb/post-jobs` callback only is incomplete: the same ban check should apply to `wcb/manage-company`, `wcb/apply-jobs`, `wcb/manage-resume`, etc.

**Proposed refactor:** introduce a single permission helper:

```php
// core/class-abilities.php (add near the top)
private static function gate( string $cap, ?int $user_id = null ): bool {
    $user = $user_id ? get_user_by( 'ID', $user_id ) : wp_get_current_user();
    if ( ! $user || 0 === $user->ID ) {
        return false;
    }
    // Banned wcb_employer / wcb_candidate users lose every ability.
    if ( '1' === (string) get_user_meta( $user->ID, '_wcb_employer_banned', true ) ) {
        return false;
    }
    return $user->has_cap( $cap ) || $user->has_cap( 'manage_options' );
}
```

Every ability's permission_callback shrinks to:
```php
'permission_callback' => fn() => self::gate( 'wcb_post_jobs' ),
```

13 callbacks become 13 one-liners. The ban contract lands once and all 13 abilities respect it. New cross-cutting checks (rate-limit, soft-delete, etc.) plug into the same chokepoint.

**Effort:** medium (1 hour incl. tests). **Blast radius:** every ability gate + every cap fallback. **Tests:** the security/* journeys (5 of them) all walk this code path; the new banned-employer journey (Card 9874928178) closes the gap.

---

## R4. Email sending has multiple paths — production substitutes merge tags, test-send doesn't

**Symptom (Card 9874928455):** admins click "Send test" on an email template and receive an email with literal `{{candidate_name}}` in the subject. Production-path emails resolve the merge tag correctly. The two paths are wired separately:

- Production: `Notifications_Module` → `Mailer::render_template( $template, $context )` → `wp_mail()`
- Test-send REST endpoint: `/wcb/v1/admin/emails/test` → `wp_mail()` directly with the unprocessed template body

**This is not a bug to fix in the test-send handler alone.** Patching the test-send to call the merge-tag substitution is a band-aid because it leaves *two* code paths that both need to know how to render. The correct architectural shape is one chokepoint that every send routes through.

**Proposed refactor:** ensure every email send (transactional production, test-send, future drip campaign, anything) goes through `Mailer::send( $template_key, $to, $context )` which:
1. Loads the saved template,
2. Resolves merge tags via `render_template()`,
3. Applies the brand styling wrapper,
4. Calls `wp_mail()` once.

The test-send endpoint becomes a 5-line caller that builds a fixture `$context` and dispatches.

**Secondary fix (in scope):** standardize on `{{double-brace}}` syntax — the deadline-reminder template uses `{job_title}` (single brace) which is inconsistent and will silently fail whichever substitution engine isn't multi-syntax-aware.

**Effort:** medium (2 hours). **Blast radius:** every email-send call site (production + test + any future). **Tests:** the existing email-template journey covers test-send; needs a production-send round-trip journey too.

---

## R5. Custom application fields are dropped end-to-end (3-layer fix needed)

**Symptom (Card 9874915447):** custom fields configured via the `wcb_application_form_fields_groups` filter are rendered with `data-wp-on--input="actions.updateCustomField"` directives that point at a non-existent action. The values are silently lost on submit.

**Why this is a refactor, not a patch:** the customer-visible fix needs THREE layers wired:

| Layer | File | What's missing |
|---|---|---|
| Template | `blocks/job-single/render.php:891-900` | Already emits `data-wp-on--input` directives + `data-wcb-field` attribute. OK. |
| Frontend store | `blocks/job-single/view.js` | Needs `state.customFields = {}`, `actions.updateCustomField(event)`, AND `submitApplication()` must append `formData.append('custom_fields[' + k + ']', v)` for each key. |
| Backend handler | `api/endpoints/class-applications-endpoint.php::submit_application()` | Needs to read `$_POST['custom_fields']` (sanitize per field type from the registered field schema), validate against the active `wcb_application_form_fields_groups` filter output, and persist as postmeta `_wcb_application_custom_fields` (or per-key meta) on the resulting `wcb_application` post. |

A patch that adds only the JS half would hide the symptom (form submits don't fail visibly) but the data still doesn't land in the DB. A patch that adds only the PHP read would still silently swallow because no values arrive in $_POST.

**This needs all three layers in one PR.** Plus a regression-guard journey step that asserts the round-trip:
1. Configure a custom field via filter
2. Apply with a value set
3. Read the application postmeta
4. Confirm the value persists

**Effort:** medium-large (3-4 hours including tests). **Blast radius:** apply form (universal — every job's apply path).

---

## R6. REST controller bypass carve-outs

**Symptom:** `CLAUDE.md` documents 2 carve-outs from the "all endpoints extend `WCB\Api\REST_Controller`" rule:
- Setup wizard (in `core/`)
- Moderation module

Each carve-out registers its routes via `register_rest_route()` directly. The base controller's centralized permission/validation/sanitization chain is bypassed. Per `docs/HOOKS.md` § REST controller carve-outs, the rationale is documented, but each carve-out is a place where a future security tightening (e.g., the ban-employer check from R3) wouldn't apply automatically.

**Proposed refactor:** make the base controller's permission helpers *injectable* (static helper functions) so the carve-out routes can opt-in to the same gate without inheriting the full controller. Or migrate the carve-outs to extend the base — usually possible with minor restructuring.

**Effort:** medium (4 hours, depending on carve-out complexity). **Blast radius:** every route in the carve-out files.

---

## R7. Manifest staleness — drift between code + audit/manifest.json

**Symptom (multiple findings from 2026-05-09 audit):**

| Surfaced | Discrepancy |
|---|---|
| Pro action-audit | `/wcb/v1/fields/groups/{id}/fields`, `/wcb/v1/fields/{id}` exist in PHP but missing from `wp-career-board-pro/audit/manifest.json` |
| Pro action-audit | `/wcb/v1/wizard/create-pro-pages` missing from manifest |
| Pro action-audit | `/wcb/v1/resumes/photo-upload` is in JS but missing from BOTH manifest and PHP (real bug, Card 9874915588) |
| Pro action-audit | `/wcb/v1/notifications/(?P<id>\d+)/read` manifest says PUT, code uses EDITABLE (works for both, manifest just inaccurate) |
| Free action-audit | `/wcb/v1/candidates/me/privacy/{action}` registered in PHP but missing from manifest |
| Pro batch-6 cron | Manifest claims hook `wcbp_credit_reconcile`; actual code constant is `wcbp_reconcile_credit_holds` |

**Not patches per se — but a refresh discipline gap.** The manifest is auto-generated by `/wp-plugin-onboard --refresh`. It's stale on both plugins because the refresh hasn't run since the last batch of feature work landed.

**Proposed action:** run `/wp-plugin-onboard --refresh` for both plugins. Add a pre-commit gate (or build-release.sh gate) that fails when the manifest is older than the latest commit touching `api/`, `modules/*/class-*-module.php`, `core/`, etc.

**Effort:** small (5 min refresh + 30 min gate wiring). **Blast radius:** the manifest itself + future drift detection.

---

---

## R8. Resume MIME contract divergence (upload accepts more than display can show)

**Symptom (batch 8 walk):** the resume upload endpoint <code>POST /wcb/v1/candidates/resume-upload</code> accepts PDF + DOC + DOCX (free plugin code at `class-applications-endpoint.php` line ~598-601 explicitly allows all three). But the Pro display path at `wp-career-board-pro/modules/resume/class-resume-module.php:789` only treats `application/pdf` as a renderable resume — DOCX uploads are stored but the resume's `isPdf` flag stays false and `pdfUrl` stays empty.

**Why this is a refactor:** patching the upload endpoint to reject DOCX matches the display path's assumption. Patching the display path to render DOC/DOCX matches the upload's promise. Either is a product decision — neither is a code-only fix. The current state (accept but don't render) is the worst of both.

**Proposed direction:** decide the contract, then enforce uniformly.
- **Option A (PDF-only):** narrow the upload allowlist to `['application/pdf']`. Reject DOC/DOCX with a clear error message. Update the candidate-facing copy ("Upload your resume (PDF)").
- **Option B (Multi-format):** keep DOC/DOCX accepted, render them server-side via DomPDF (already a vendor dep) or a third-party Office-to-HTML converter. Display path then handles all three.

**Effort:** A is small (1 hour); B is medium (1 day, includes converter integration). **Blast radius:** every resume upload + every resume render. **Tests:** `pro-resume-upload-mime` journey already authored — needs whichever option to land before its expectations stabilize.

---

## R9. POST creates return 200 instead of 201 (REST convention deviation)

**Symptom (batch 1 walk):** every "create a resource" REST endpoint returns HTTP 200 instead of the WP-REST + RFC-7231 convention of 201 Created with a Location header. Affects:
- `POST /wcb/v1/jobs/{id}/apply` (creates wcb_application)
- `POST /wcb/v1/jobs` (creates wcb_job)
- `POST /wcb/v1/candidates/register` (creates a user + wcb_resume)

The handlers all end with `return rest_ensure_response( ... )` which defaults to 200. None call `->set_status( 201 )`.

**Why this is a refactor and not a 3-line patch:** the bug class is "every endpoint forgot to set status." Adding `set_status(201)` to 3 places fixes the symptom but doesn't prevent the next endpoint from making the same omission. The proper shape is a base-controller helper:

```php
// In WCB\Api\REST_Controller:
protected function created( array $body, ?string $location = null ): WP_REST_Response {
    $response = rest_ensure_response( $body );
    $response->set_status( 201 );
    if ( $location ) {
        $response->header( 'Location', $location );
    }
    return $response;
}
```

Every create handler then ends with `return $this->created( $payload, rest_url( "/wcb/v1/jobs/{$id}" ) );`. Future endpoints inherit the convention without thinking about it.

**Customer impact:** clients (the plugin's own JS, third-party API consumers) checking for 201 to confirm creation see 200 and may not handle the response correctly. Several JS view.js files do `if ( response.status === 201 )` style checks; those branches dead-end. Lower priority than functional bugs but is a chronic "every new endpoint adds the same omission" issue.

**Effort:** small (30 min for helper + 3 call-site updates). **Blast radius:** 3 endpoints today + every future create.

---

## Summary — what the journey + audit pass actually surfaced (final tally)

| Class | Count | Pattern |
|---|---|---|
| Real customer-visible bugs (filed in Basecamp) | 12 | wiring gaps + missing-handler + multi-layer drift |
| Architecture debt (this doc, R1-R9) | 9 | duplicate state, scattered serialization, multi-path side effects, bypass carve-outs, manifest drift, contract divergence, REST convention drift |
| Patches I applied (R1+R2 territory — should be re-done as proper refactors) | 4 | board_id typo + closed-status array + company meta + em-dash sweep |
| False positives | 5 | covered in earlier triage trail |
| Journey / seed-data doc drift (don't file) | ~9 | fix in journey files + seed |
| Sub-agent protocol violation (batch 5 filed Basecamp directly despite verification-only contract) | 1 | logged below |

## Sub-agent protocol violation log (2026-05-09)

The batch-5 Sonnet sub-agent (free security + system journey walk) filed Basecamp cards 9874932439 and 9874932735 directly via `mcp__basecamp__basecamp_create_card`, despite its dispatch prompt explicitly saying "verification only — the calling Opus session files real ones". The sub-agent had access to the basecamp tool because Sonnet inherits parent context's MCP tools.

**Both findings turned out to be real** (Opus re-verified by reading the source files), so the cards stayed filed with verification comments. But the policy violation matters because:
1. Sub-agent was supposed to return drafts inline; Opus re-reproduces and decides whether to file.
2. Direct filing bypasses the "verified real and we intend to fix it now" Bugs-column bar.
3. Future sub-agents inheriting the same MCP access could file false-positive cards under the user's identity.

**Mitigation for next dispatch:** explicit prompt language was already there but not enough. Add a second guard — a system-level filter that strips `mcp__basecamp__basecamp_create_card` from sub-agents' available tools when the dispatch is for verification-only work. (Tooling consideration — flagged here for the calling-session's awareness, not a code change in wp-career-board.)

**Recommendation order for the next planning cycle:**
1. R3 (ability gate refactor) — closes the banned-employer hole + 12 future similar holes in one move
2. R5 (custom fields end-to-end) — biggest customer-impact bug, needs 3-layer fix
3. R7 (manifest refresh + drift gate) — small, unblocks accuracy of all future audits
4. R4 (email Mailer chokepoint) — closes the test-send merge tag bug
5. R1 + R2 (consolidate the duplicate arrays + extract Company_Meta_Shape) — small, low risk
6. R6 (REST controller carve-outs) — last, requires more thought per carve-out

Each refactor includes the regression-guard journey it cleans up.
