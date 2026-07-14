# WP Career Board 1.7.0 — Mobile API Contract

The plugin-side work that must land **before** any app code, so the app build never reopens the
plugin. Derived by mapping the complete app surface (every candidate + employer journey → screen →
endpoint) against the live REST API, then diffing.

Verified against the running site (`career-board.local`, free 1.7.0 + pro 1.7.0, 75 routes on
`wcb/v1`). Every claim below marked CONFIRMED was reproduced live with curl, not read from source.

Reference implementations: Jetonomy 1.6.0 (auth, push, enrichment, `/app/config`) and WPMediaVerse
2.1.0 (the App-Store compliance set). Do not reinvent — port the patterns.

---

## Part 0 — SECURITY: ship these regardless of the app

Three authorization holes found while auditing the API for mobile. They are **live in 1.6.0 today**
and are not mobile-specific — a browser session exploits them equally. The mobile app only makes them
reachable from a phone, unattended. **These are P0 and should ship on their own timeline, ahead of
everything else in this document.**

### P0-1 — Cross-tenant applicant disclosure (CONFIRMED, exploited live)

`GET /wcb/v1/jobs/{id}/applications` returns **HTTP 200** to *any* employer, for *any* job, including
jobs they do not own.

Reproduced: employer B (no relationship to employer A's job) received A's applicant list —
`applicant_name`, `applicant_email`, `cover_letter`, `resume_url`. A plain authenticated GET; no
exploit chain.

Root cause — `api/endpoints/class-jobs-endpoint.php`, `view_applications_permissions_check()`:
it checks only `check_ability( 'wcb/view-applications' )`. That ability is granted to the entire
`wcb_employer` role unconditionally (`core/class-roles.php`), and is **never scoped to the job**.

The fix already exists elsewhere in the codebase — the sibling routes
`GET /employers/{id}/applications` and `PATCH /applications/{id}/status` both correctly check
`post_author === get_current_user_id()`. Apply the same ownership check here.

```php
// api/endpoints/class-jobs-endpoint.php
public function view_applications_permissions_check( $request ) {
    if ( ! $this->check_ability( 'wcb/view-applications' ) ) {
        return $this->forbidden();
    }
    $job = get_post( (int) $request['id'] );
    if ( ! $job || 'wcb_job' !== $job->post_type ) {
        return $this->not_found();
    }
    // The ability says "this role may view applications AT ALL".
    // It does NOT say "…for this job". Scope it to the owner.
    if ( (int) $job->post_author !== get_current_user_id()
        && ! $this->check_ability( 'wcb/moderate-jobs' ) ) {
        return $this->forbidden();
    }
    return true;
}
```

Audit `GET /ai/ranked-applications/{job_id}` in the same pass — it gates on the identical
`wcb/view-applications`-only pattern and is very likely the same bug.

### P0-2 — Private resumes readable by any employer (CONFIRMED, exploited live)

`GET /wcb/v1/resumes/{id}` and `GET /wcb/v1/resumes/{id}/pdf` both return **HTTP 200** for a resume
the candidate explicitly marked private, to an employer who never received an application from them.

Root cause — `wp-career-board-pro/api/endpoints/class-resume-endpoint.php`: the
`wcb/view-resumes` ability is a blanket role-cap on every `wcb_employer`
(`core/class-pro-abilities.php`), and it is checked **before** `_wcb_resume_public`. So the ability
short-circuits the candidate's own privacy toggle — the toggle is decorative.

This is the more serious of the two: it is direct PII (phone, email, work history) and it silently
breaks a privacy promise the product's own UI makes to the candidate.

Fix: check visibility **first**, and treat the ability as necessary-but-not-sufficient.

```php
$is_owner  = (int) $resume->post_author === get_current_user_id();
$is_public = '1' === (string) get_post_meta( $resume->ID, '_wcb_resume_public', true );

if ( $is_owner || $this->check_ability( 'wcb/manage-settings' ) ) {
    return true;                       // owner + admin always
}
if ( ! $is_public ) {
    return $this->forbidden();         // private beats the role-cap. Always.
}
return $this->check_ability( 'wcb/view-resumes' );
```

Decide deliberately whether an employer should see a private resume **from a candidate who applied to
their job** — that is a legitimate product rule, but it must be an explicit relationship check
(an application exists linking this candidate to a job owned by this employer), never a blanket cap.

### P0-3 — Pipeline stage-id is not validated against the board (CONFIRMED by read; known as BUG-3)

`PATCH /applications/{id}/stage` writes `_wcb_stage_id` straight from the request. Job ownership is
checked; the *stage* is not — it is never verified to exist, nor to belong to the job's board. Today
this is data corruption rather than disclosure (boards are admin-only), but it becomes cross-tenant
the moment `wcbp_manage_boards` is granted to employers — which the code comments already anticipate.

Same class of bug in `/boards/*` and `/fields/*`: gated on the ability, with no per-board ownership
check. Add the ownership check **now**, while it is cheap and non-breaking.

### P0-4 — Job detail leaks unpublished jobs (CONFIRMED by read)

`GET /jobs/{id}` checks the post exists and is a `wcb_job`, but never checks `post_status`. A guessed
ID exposes a job still in moderation or a rejected draft, unauthenticated. Low severity, but it
contradicts the moderation model. Gate to `publish` unless owner/moderator.

---

## Part 1 — What is already done (do not rebuild)

Verified live. This plugin is far more mobile-ready than a typical first pass, and the contract
should build on these rather than duplicate them.

| Contract requirement | Status | Evidence |
|---|---|---|
| **Auth: Application Passwords** | **DONE — no work needed** | `wp_is_application_passwords_available()` → true. An employer authed via app password created a job (201). |
| **Ban gate survives App Passwords** | **DONE — and better than the reference** | Banned employer, same app password: job create → **403 `wcb_forbidden`**, employer job list → 403, public reads → 200. Enforced at the **abilities chokepoint** (`core/class-abilities.php`), so it covers every route at once. Jetonomy/MediaVerse needed a per-route `auth_mutation()` factory; Career Board does not. |
| **Ban covers candidates too** | **DONE (misnamed)** | `_wcb_employer_banned` is checked in *both* `gate()` and `candidate_gate()`. The flag works for candidates; only the *name* is employer-flavoured. |
| **Config endpoint** | **EXISTS — extend, don't build** | `GET /wcb/v1/settings/app-config`, public (200 unauth), already returns `feature_toggles` + `is_pro_active`. Already has the `wcb_rest_app_config` filter seam. |
| **Big-site readiness** | **Largely DONE** | Jobs / companies / applications / kanban / notifications all server-paginated with capped `per_page`, batched meta/user priming, and real indexes (incl. a FULLTEXT key on `post_title` and a composite `postmeta(meta_key,meta_value)` key). The kanban was explicitly re-engineered for the 500-applicant case. |
| **Reporting NOT dead-403** | **DONE** | Career Board does **not** have MediaVerse's default-off reporting bug. `POST /jobs/{id}/report` works on a stock install. |

Two big-site items still open: `GET /employers/{id}/applications` is hard-capped at 20 rows with no
paging, and `GET /ai/ranked-applications/{job_id}` is unverified at 500 applicants. Read
`AiModule::rank_applications()` before building the ranked-applicants screen.

---

## Part 2 — The mobile contract (the actual delta)

### 2.1 Extend `/settings/app-config` (do NOT add a second `/app/config`)

The endpoint exists and the app should keep using it. Add the missing keys through the existing
`wcb_rest_app_config` filter. Rules: **additive only** — never rename or retype an existing key
(a strict client parser throws on the unexpected); coerce every flag with `array_map('boolval', …)`.

```jsonc
{
  // --- existing, keep exactly as-is ---
  "site_name": "...", "plugin_version": "1.7.0", "is_pro_active": true,
  "feature_toggles": { "guest_apply": true, "bookmarks": true, "job_alerts": true,
                        "application_pipeline": true, "resume_archive": true,
                        "credits": true, "ai_matching": true },

  // --- ADD: white-label / branding (Pro settings → free settings → default) ---
  "accent_color": "#2563EB",
  "logo_url": "https://…", "login_bg_url": "https://…",
  "dark_mode_default": false,

  // --- ADD: compliance surfaces, so the app never renders a control that 403s ---
  "feature_toggles": { "reporting": true, "blocking": true, "account_deletion": true },

  // --- ADD: legal, per-site. Never a hardcoded Wbcom placeholder. ---
  "legal": {
    "privacy_policy_url": "…",       // default: get_privacy_policy_url()
    "terms_url": null,
    "eula_url": null,                 // null → app falls back to Apple's standard EULA
    "community_guidelines_url": null,
    "abuse_contact_email": "…"        // default: get_option('admin_email')
  },

  // --- ADD: version floor, so a bad build can be retired ---
  "min_app_version": "1.0.0",
  "contract_version": 1,

  // --- ADD: fail-closed app gate (port from Jetonomy verbatim) ---
  "app_enabled": false               // free hardcodes false; Pro flips true only on a valid license
}
```

`app_enabled` is the single line that stops the app running against an unlicensed install. The app's
config loader must default **every** flag to `false` and treat only an explicit `true` as enabled, so
a parse failure or an older site degrades to the minimal UI rather than showing a control that 403s.

### 2.2 Viewer-relative fields on jobs (the N+1 the app would otherwise force)

`GET /jobs` and `GET /jobs/{id}` today carry **no viewer state**. Without it the app must fetch
bookmarks and applications separately and diff client-side on every list page — the exact N+1 the
big-site checklist forbids.

Add, batch-fetched (one query per page, hydrate from a set — never per row):

| Field | Type | Meaning |
|---|---|---|
| `is_bookmarked` | bool | viewer saved this job |
| `has_applied` | bool | viewer already applied |
| `application_status` | string\|null | viewer's own application status |
| `viewer_can_apply` | bool | resolves guest-apply, employer-can't-apply, already-applied, closed |

Port Jetonomy's three-part pattern exactly (`Jetonomy\API\Posts_Controller::enrich_viewer_state`):
a batch model method returning an ID-set/map → a controller hydrate step over the page → `prepare_*`
reads the preset property if set, else falls back to a single lookup (so the list path and the
single-item path share one code path). The jobs endpoint already primes `update_meta_cache` per page,
so this slots into an existing step rather than adding a query shape.

### 2.3 Native push — `POST|DELETE /wcb/v1/push/register-device`

Nothing exists today (zero Expo/`register-device` code in either plugin). Pro-side.

```sql
CREATE TABLE {prefix}wcb_push_devices (
  id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id         bigint(20) unsigned NOT NULL,
  expo_push_token varchar(255) NOT NULL,
  platform        varchar(16)  NOT NULL,   -- ios | android
  device_name     varchar(190) NULL,
  created_at      datetime NOT NULL,
  updated_at      datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_token (expo_push_token),  -- lets a token migrate accounts on re-login
  KEY idx_user (user_id)
);
```

The `UNIQUE` on the token is load-bearing: registration must be an upsert that **re-owns** the row to
the new `user_id`, so a shared device re-logged-in as someone else does not keep pushing to the
previous account.

Fan-out to `https://exp.host/--/api/v2/push/send`, async (scheduled/Action-Scheduler), chunked at 100
tokens, and **delete any token Expo reports `DeviceNotRegistered`**.

Bind to Career Board's real notification events — `wcb_job_approved`, `wcb_job_rejected`,
`wcbp_application_stage_changed`, plus the Pro in-app notification insert.

> **The one bug to not repeat.** Jetonomy's push was silently dead in production because the hook
> fired 7 args and the handler was bound with `accepted_args = 1`. PHP truncates without error.
> Count the args of the actual `do_action()` and match `add_action( …, 10, N )` exactly. Grep every
> `add_action` bound to a plugin-fired hook for this class of mismatch.

Deep-link payload: `{ "type": "job"|"application"|"notification", "id": int }`.

### 2.4 In-app account deletion — Apple 5.1.1(v). **This is a hard App Store blocker.**

Today's `POST /candidates/me/privacy/erase` is **not** account deletion — it files a WP core
`remove_personal_data` request (email-confirm + admin-approve). It never deletes the account. These
are two different features and the app needs both.

```
DELETE /wcb/v1/me            { password, confirm: "DELETE" }  → 202 scheduled | 200 deleted
GET    /wcb/v1/me/deletion                                     → status
DELETE /wcb/v1/me/deletion                                     → cancel
```

- Require the **account password**, not just the Application Password. An app password is a
  long-lived bearer token on a phone; if a stolen one could destroy the account, it becomes a weapon.
- `confirm: "DELETE"` literal.
- Grace period (filterable, default 14 days, `0` = immediate). On schedule: suspend the user and
  **revoke all app passwords + sessions**.
- Refuse for administrators (never let the last admin lock the site out).
- End at `wp_delete_user()` so core's `deleted_user` cascade runs. Do **not** invent a parallel path.
- **The cancel route must be exempt from the suspension gate.** Scheduling deletion suspends the
  member; if suspension then blocks the cancel route, the grace period becomes a one-way door. This
  bug shipped in MediaVerse and was only caught by exercising the flow end-to-end. Add it to the QA
  plan, not just a unit test.
- Reuse the GDPR erase map (below) — do not hand-roll a second purge list.

### 2.5 Report + block — Apple 1.2

Career Board's UGC surface is thinner than a social app (no DMs, no member comments), but the app
exposes candidate resumes and employer-authored job posts, so member-level safety is required.

```
POST        /wcb/v1/users/{id}/report   { reason, details }
POST|DELETE /wcb/v1/users/{id}/block
GET         /wcb/v1/me/blocked
```
Job reporting (`POST /jobs/{id}/report`) already exists — keep it.

Reason enum should be domain-appropriate: `spam`, `fraud`, `fake_listing`, `discrimination`,
`harassment`, `other`.

Rules learned the hard way on MediaVerse:
- **Reports must reach a human.** A queue nobody reads is worse than no endpoint — it tells the
  member their report was sent. Career Board already has an admin moderation surface; extend it.
- **A block with no unblock UI is a trap.** `GET /me/blocked` exists so the app can offer one.
- **Reporting stays ON by default** with a settings opt-out, read from one helper by REST *and*
  templates *and* `app-config`. Never re-derive the state in two places.

### 2.6 Suspension trigger for candidates

Enforcement already covers candidates (§Part 1) — but the **only admin trigger writes
`_wcb_employer_banned` from the Employers screen** (`admin/class-admin-employers.php`). There is no
way for an admin to ban an abusive *candidate*. That is capability-without-a-surface: the gate exists
and nothing can pull it.

Add the trigger to the Candidates admin list (row action + Users-list column), funnelling through one
`set_suspended()` that fires an action. Consider renaming the meta to `_wcb_user_banned` with a
back-compat read of the old key — the current name misleads every future reader into thinking
candidates are unprotected.

### 2.7 GDPR — one map, and `done` must be earned

Before the deletion route ships, audit the erase/export map table-by-table. Rules:
- **Every** user-keyed table is on exactly one list: ERASE or RETAIN. RETAIN needs a stated reason +
  legal basis and anonymises (id → 0). Silence is not an answer.
- Cover the member as a **target**, not just as an actor (both directions of blocks; recipient *and*
  actor of notifications).
- Pro registers its tables into the free map via a filter — including **`wcb_push_devices`**. Leaving
  those behind means still pushing notifications to the phone of someone who deleted their account.
- **`done` must be earned**: count residual rows from the same map and report finished only at zero.
  Core emails the member "your data is gone" on the strength of that flag — it is the one lie a
  compliance path cannot tell.
- Add a build gate (`bin/check-erasure.php`) that fails CI if a user-keyed table is on neither list.
  A compliance rule with no gate is a suggestion.

---

## Part 3 — The credits / IAP decision (business, not technical — BLOCKS the employer app)

Employers spend credits to post jobs (`credit_cost` per board, hold/deduct/refund) and top up via
`POST /wbcom-credits/v1/wp-career-board/checkout/{gateway}`.

**If the iOS app lets an employer buy credits, Apple Guideline 3.1.1 requires StoreKit IAP — a 30%
cut on every purchase.** Even *pricing copy* with no purchase path is a documented rejection magnet.

Three ways out, and this must be decided **before** the employer app is scoped:

1. **Candidate-only app v1** (what Indeed/LinkedIn job-seeker apps do). Employers stay on the web. No
   IAP, no purchase UI, no rejection risk. Fastest, cleanest.
2. **Both personas, credits read-only in-app.** Balance and ledger visible; no purchase, no prices,
   no link-out to buy. Narrower than it sounds — Apple reads link-outs unkindly.
3. **Full StoreKit IAP** for credit packs. Largest build, 30% margin hit, and the credit ledger must
   reconcile IAP receipts against the gateway ledger.

Also note **Guideline 4.2.6**: Wbcom cannot publish per-customer branded apps from its own Apple
account. A white-label build is a **customer-delivered artifact**, submitted from the customer's own
account. One multi-tenant app where the user enters their site URL is the safe model.

---

## Part 4 — Sequencing

**P0 — security, ships on its own, independent of the app**
1. P0-1 job-applications ownership check (+ audit `ai/ranked-applications`)
2. P0-2 resume privacy before the role-cap
3. P0-3 stage/board ownership validation
4. P0-4 job-detail status gate

Each is a one-function fix in an existing file. None needs a new table.

**P1 — contract completeness (cheap, unblocks the app)**
5. Extend `app-config` (branding, legal, compliance flags, `min_app_version`, `app_enabled`)
6. Viewer-relative batch fields on jobs

**P2 — new surfaces (do once, together)**
7. Push (`register-device` + fan-out + GDPR erase-map entry)
8. Account deletion + GDPR map audit + build gate
9. Member report/block + candidate suspension trigger

Items 7–9 land together: account-deletion's "cancel must be suspension-exempt" rule only exists once
suspension exists, and push's device table must be in the erase map from day one.

**P3 — verify before building the corresponding screens**
10. Read `AiModule::rank_applications()` and `ResumeModule::list_public_resumes()` — both unverified
    at scale; both back a planned screen.

Once P0–P2 land, every screen in the candidate and employer journey maps is served, and the app build
should not need to reopen the plugin.

---

## Manifest

Add every new route/hook/table to `audit/manifest.json` (free + Pro) in the same change that adds it.
