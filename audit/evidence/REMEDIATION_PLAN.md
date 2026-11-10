# WP Career Board — Long-Run Remediation Plan

> Tracks every known gap from the 2026-05-06 audit session through to a
> stable solution. Each item has scope, files, behaviour, verification, and a
> ship target. Nothing in this list is a "TODO" — each is concrete work the
> next dev session can pick up and execute end-to-end.

---

## Status legend

- 🟢 Shipped
- 🟡 Scoped, ready to execute
- 🔴 Blocked
- ⏳ Continuous (recurring quality gate, not a one-off)

---

## P1 — Employer Credits UX (Basecamp 9758375157)

**Status:** 🟡 Scoped.

**Problem:** Wbcom Credits SDK backend works (verified 4 mappings present
across WC / PMPro / Woo Subscriptions). But employers have zero discoverable
UX to:

1. See their current credit balance from the dashboard
2. Buy more credits with a clear path
3. Be told what a job posting will cost when posting
4. Be alerted when they run low

**Files to change:**

| File | Change |
|---|---|
| `blocks/employer-dashboard/render.php` (Free) | Add credit balance card to Overview stats row when `wcb_pro_active` filter returns true; pass `creditBalance` + `creditPurchaseUrl` into Interactivity state |
| `blocks/employer-dashboard/style.css` (Free) | Style for `.wcb-stat-card--credits` with prominent "Buy More" link |
| `blocks/job-form/render.php` + `view.js` (Free) | Show inline "This costs N credits. You have M." line under the title field; gate the submit button + show CTA to purchase if `M < N` |
| `wp-career-board-pro/blocks/credit-balance/` (Pro) | Already exists; verify still works after this change |
| `wp-career-board-pro/blocks/credits-pricing/` **NEW** (Pro) | New block: pricing-card grid sourced from `\Wbcom\Credits\Adapters\AdapterRegistry::get_available()` + their `get_mappable_items()` enriched with the configured credit amounts. Customer drops it on a `/buy-credits/` page. |
| `wp-career-board-pro/integrations/woocommerce/class-wc-redirect.php` **NEW** | Hook `woocommerce_get_checkout_order_received_url` to redirect employer back to `/employer-dashboard/?wcb_credits_added=N` after a credit-package purchase completes |
| `wp-career-board-pro/modules/credits/class-low-balance-banner.php` **NEW** | When `Credits::get_balance()` ≤ `wcbp_low_credit_threshold`, surface a dismissible banner inside `.wcb-dashboard` via Interactivity API state, with a "Top up" link to the buy page |

**Behaviour spec:**

- Dashboard Overview shows 6 stat cards on Pro (was 5 — adds Credits)
- Job-form Step 1 shows "This costs 1 credit. You have 5." in normal black; turns red + locks Next button when balance < cost; "Buy more credits" link appears
- Buying credits via WC: success page redirects to `/employer-dashboard/?wcb_credits_added=10` which renders a green dismissible banner "10 credits added to your account"
- Low-balance banner appears at top of `.wcb-dashboard-shell` whenever balance ≤ threshold; persists until balance > threshold or user dismisses (sticky 24 h)
- All copy + paths are i18n

**Verification:**

1. Employer with 0 credits opens dashboard → sees Credits card showing 0 + "Buy More" link prominent
2. Buy 10-credit package via WC → redirected to dashboard with success banner
3. Open Post-a-Job → step 1 shows "1 credit / you have 9"
4. Set threshold to 5 → reduce balance to 4 → low-balance banner appears at top of dashboard
5. Dismiss banner → check transient stored, banner gone for 24 h
6. PHPStan level 5 + WPCS clean

**Ship target:** Next dev session, ~4-6 h focused work.

---

## P2 — Mobile QA matrix across 11 themes

**Status:** ⏳ Continuous quality gate.

**Problem:** Today's theme matrix (`docs/qa/THEME_MATRIX.md`) verified the
employer-dashboard at 1614px on 11 themes, but only spot-checked mobile.
Premium UX must hold at 390px (iPhone), 768px (iPad), and 1024px (laptop).

**Solution:** Add a Playwright spec that runs the matrix at each breakpoint
and fails CI when card columns drop below the agreed minimum or content
overflows the canonical container.

**Files to change:**

| File | Change |
|---|---|
| `tests/playwright/theme-matrix.spec.ts` **NEW** | Cycles through `wp theme activate <slug>` for each of the 10 in-scope themes, navigates to the 5 critical pages (`/employer-dashboard/`, `/find-jobs/`, `/find-candidates/`, `/companies/`, `/jobs/<slug>/`), takes screenshots at 390 / 768 / 1024 / 1440, asserts container width = `--wcb-container-max-width`, asserts no horizontal scroll, asserts theme-link defence holds (no underlines on `.wcb-nav-item`). |
| `tests/playwright/fixtures/themes.ts` **NEW** | Theme list (matches `docs/qa/THEME_MATRIX.md`) |
| `tests/playwright/fixtures/pages.ts` **NEW** | Page list with required role per page |
| `tests/playwright/playwright.config.ts` **NEW** | Standard config, screenshot path `tests/playwright/screenshots/<theme>/<page>-<width>.png` |
| `.github/workflows/theme-matrix.yml` **NEW** | Runs the spec on every PR, attaches the screenshots dir as an artefact |

**Behaviour spec:**

- Each (theme × page × width) cell takes < 5 s
- Total run time < 10 min for 10 themes × 5 pages × 4 widths = 200 cells
- Failure means concrete cell — e.g., "Storefront × /employer-dashboard/ × 768 — stat-card row collapsed to 4+1"
- Screenshots stored in artefact, diffable PR-to-PR

**Ship target:** Next dev session, ~3-4 h. Reduces every future theme audit
from "spend a day clicking through" to "open the run, see what failed."

---

## P3 — Accessibility audit

**Status:** ⏳ Continuous quality gate.

**Problem:** Premium UX includes accessibility, never verified.

**Solution:** axe-core spec across the same matrix as P2.

**Files:**

| File | Change |
|---|---|
| `tests/playwright/a11y.spec.ts` **NEW** | Runs `@axe-core/playwright` on each page in the matrix; asserts 0 critical / 0 serious violations |
| `docs/qa/A11Y_BASELINE.md` **NEW** | Records the violations we knowingly accept (theme-controlled colour contrast on inherited link colours, etc.) |

**Specific known issues to verify:**

- Editor.js focus indicators visible on keyboard tab through admin job edit
- All interactive elements reachable by keyboard (post-a-job multi-step,
  candidate dashboard tab cycling)
- Hidden `<textarea class="wcb-editor-source">` properly aria-hidden so SR
  announces only the contenteditable surface
- Profile / dashboard tabs have proper `aria-controls` linking to the
  panels they reveal

**Ship target:** Dedicated session. ~4 h to write spec + 4 h to fix
findings.

---

## P4 — RTL audit

**Status:** ⏳ Continuous quality gate.

**Problem:** Plugin claims RTL support but never verified end-to-end.
`wp_style_add_data($handle, 'rtl', 'replace')` is set on `wcb-frontend` and
`wcb-confirm-modal`, BUT not on `wcb-frontend-components` (which now hosts
the form primitives, listings primitives, and canonical container — half
the plugin's CSS).

**Files:**

| File | Change |
|---|---|
| `core/class-plugin.php` | Add `wp_style_add_data('wcb-frontend-components', 'rtl', 'replace')` next to the existing `wcb-frontend` line |
| `core/class-plugin.php` | Same for `wcb-editor` and `wcb-frontend-tokens` |
| `tests/playwright/rtl.spec.ts` **NEW** | Set `<html dir="rtl">` then run the page matrix; assert `.wcb-archive-shell` margin-inline auto, `.wcb-sidebar` border on the correct side, sort dropdown chevron mirrored |
| `tests/Cypress/wp-cli` script to set site to Hebrew/Arabic | Reproducible RTL setup |

**Specific known LTR-only patterns to fix:**

- A few `padding-left` / `padding-right` (literal) instead of `padding-inline-start` / `-inline-end` — search-and-replace pass needed
- `inset-inline-start` already correct in the global search-icon rule (verified)
- Background-position SVG chevrons in `.wcb-field-select` already have RTL
  override (verified)

**Ship target:** Dedicated session. ~3 h.

---

## P5 — Pro-deep-integration audit

**Status:** 🟡 Scoped.

**Problem:** Free was making calls to Pro-only endpoints unconditionally
(today's `Could not load your resumes` finding). The Pro license gate was
the proximate cause and is fixed (`0ef4cd8`), but the underlying issue is
that Free has no shared "is Pro present?" detection that frontend code can
key off of.

**Files:**

| File | Change |
|---|---|
| `core/class-pro-coordination.php` | Already has `wcb_pro_active` and `wcb_pro_licensed` filter contracts. Add a sibling filter `wcb_pro_features` that returns an array of feature flags (`['resumes' => true, 'alerts' => true, 'credits' => true]`) so Free's blocks can selectively enable UI |
| `blocks/candidate-dashboard/render.php` | Read `wcb_pro_features` and only render My Resumes / Job Alerts tabs when those features are flagged true |
| `blocks/candidate-dashboard/view.js` | When a feature is missing in state.proFeatures, don't fire the REST request — show "Resumes are a Pro feature — [Activate Pro]" message |
| `wp-career-board-pro/core/class-pro-coordination-bridge.php` | Hook `wcb_pro_features` and return the actual flag map |

**Verification:**

- Disable Pro plugin → candidate dashboard hides My Resumes / Job Alerts
  tabs (no more 404s, no more confusing errors)
- Enable Pro → tabs appear, REST works
- License invalid → tabs still appear (license is updates-only per
  Wbcom model), backend works

**Ship target:** Half a session. ~2-3 h.

---

## P6 — Token audit (CSS hardcoded fallbacks)

**Status:** 🟡 Scoped.

**Problem:** Documented in `docs/qa/CSS_DEDUP_AUDIT.md`. Some block
stylesheets still have hardcoded hex fallbacks (`#0f172a`, `#1e293b`,
`#e2e8f0`) inside `var(--wcb-*, #fallback)`. These were defensive when the
tokens stylesheet wasn't guaranteed to load on every page, but
`assets/css/frontend-tokens.css` is now enqueued with `frontend.css` on
every page that has a WCB block, so the fallbacks are dead code.

**Files:**

| File | Change |
|---|---|
| Every `blocks/*/style.css` | Search-and-replace `var(--wcb-X, #fallback)` → `var(--wcb-X)`. Then visually re-verify on a fresh install where tokens hadn't loaded yet (e.g. before plugin activation) |

**Verification:** Run grep, confirm `var(--wcb-` no longer has comma-fallback
inside block stylesheets. Visual diff on theme matrix unchanged.

**Ship target:** ~1 h focused work. Defer until P1+P2 done so it doesn't
churn screenshots used for verification.

---

## Recurring quality gates (every release)

- ⏳ Project phpcs clean (already enforced by `mcp__wpcs__wpcs_check_staged` MCP)
- ⏳ PHPStan level 5 clean
- ⏳ Theme matrix Playwright spec passes (P2)
- ⏳ Axe spec passes (P3)
- ⏳ RTL spec passes (P4)

## Ship targets summary

| Item | Effort | Priority |
|---|---|---|
| P1 — Credits UX | 4-6 h | High (real customer asks) |
| P5 — Pro feature flags | 2-3 h | High (defensive: avoids future "Could not load" surprises) |
| P2 — Mobile / theme matrix Playwright | 3-4 h | Medium (CI gate, prevents regressions) |
| P3 — A11y audit | 8 h | Medium |
| P4 — RTL audit | 3 h | Medium |
| P6 — Token cleanup | 1 h | Low (cosmetic, defer) |

**Total: ~22 h of focused work to clear every known gap.**

Recommended sequence:
1. **Session 1:** P1 (Credits UX) — biggest customer impact
2. **Session 2:** P5 (Pro feature flags) + P6 (Token cleanup) — defensive + cleanup
3. **Session 3:** P2 (Theme matrix CI) — turn manual audit into automated gate
4. **Session 4:** P3 (A11y) — premium UX completeness
5. **Session 5:** P4 (RTL) — multilingual market

After all 5 sessions: every known gap closed, regressions impossible
without CI catching them.
