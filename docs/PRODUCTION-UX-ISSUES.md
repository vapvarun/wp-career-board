# Production UX Issues — Code-Level Audit

> Date: 2026-03-26
> Phase: 0 (Code audit) — before browser verification

---

## UX-0.1: Hover Effect Consistency

### Issues Found (4 hardcoded, 2 transition:all)

| # | File | Line | Issue | Fix |
|---|------|------|-------|-----|
| H-1 | `candidate-dashboard/style.css` | 755 | Bell hover uses `#4f46e5` fallback | Replace with `var(--wp--preset--color--wcb-primary)` |
| H-2 | `candidate-dashboard/style.css` | 806 | Bell item hover uses `#f9fafb` | Replace with `var(--wp--preset--color--wcb-surface)` |
| H-3 | `employer-dashboard/style.css` | 1624 | Same bell hover `#4f46e5` | Same fix |
| H-4 | `employer-dashboard/style.css` | 1675 | Same bell item hover `#f9fafb` | Same fix |
| H-5 | `job-single/style.css` | 677 | `transition: all 0.15s` | Scope to `transition: border-color 0.15s, color 0.15s` |
| H-6 | `employer-registration/style.css` | 98 | `transition: all 0.15s` | Scope to specific properties |

### Passing
- Pro blocks: 0 hardcoded hex in hover rules
- Most blocks have `:focus-visible` alongside `:hover`

---

## UX-0.2: Action Flow (JS)

### Issues Found

| # | File | Line | Issue | Fix |
|---|------|------|-------|-----|
| J-1 | `job-form/view.js` | 291 | Hardcoded: `'Your session has expired...'` | Move to state.strings via render.php |
| J-2 | `job-form/view.js` | 303 | Hardcoded: `'Connection error...'` | Move to state.strings |
| J-3 | `employer-registration/view.js` | 117 | Hardcoded: `'Connection error...'` | Move to state.strings |

### Silent Catches (4 — intentional, review only)
- `job-listings/view.js:134` — alert save fail (button stays enabled)
- `candidate-dashboard/view.js:330` — unbookmark fail (row stays visible)
- `candidate-dashboard/view.js:354` — resume delete fail (optimistic update)
- `job-single/view.js:149` — bookmark toggle fail

### Passing
- `window.alert()`: 0 occurrences
- `window.confirm()`: 3 occurrences — all for destructive actions, all use i18n strings
- `console.log/error/warn`: 0 occurrences

---

## UX-0.3: Notice/Feedback Patterns

### Counts
| Pattern | Free | Expected |
|---------|------|----------|
| `role="alert"` | 1 (job-form) | Should be on all error containers |
| `role="status"` | 6 (4 candidate, 2 employer) | Good coverage |
| `aria-live="polite"` | 2 (job-listings, company-archive) | Should be on all dynamic lists |

### Missing `role="alert"` on error containers
- employer-registration error container
- employer-dashboard error messages
- candidate-dashboard error messages
- job-single apply error

### Missing `aria-live` on dynamic content
- employer-dashboard job list
- candidate-dashboard application/bookmark/resume lists
- resume archive

---

## UX-0.4: Empty States

### Blocks that silently return (no output when empty)

**Free (8 blocks):**
| Block | Condition | Acceptable? |
|-------|-----------|-------------|
| job-form | Not logged in / no permission | Yes — access control |
| company-profile | No company found | Yes — 404 context |
| job-stats | No stats | Borderline — could show "No stats yet" |
| employer-dashboard | No permission | Yes — access control |
| job-single | No job found | Yes — 404 context |
| featured-jobs | No featured posts | **No** — section disappears silently |
| recent-jobs | No jobs | **No** — widget disappears silently |

**Pro (12 blocks):**
| Block | Condition | Acceptable? |
|-------|-----------|-------------|
| my-applications | No permission | Yes — access control |
| resume-single | No resume / no user | Yes — 404 context |
| featured-candidates | No resumes | **No** — section disappears |
| job-alerts | Not logged in | Yes — access control |
| resume-builder | Not logged in | Yes — access control |
| credit-balance | Not logged in | Yes — access control |
| board-switcher | No boards | **No** — section disappears |
| featured-companies | No companies | **No** — section disappears |
| open-to-work | No users | **No** — section disappears |

**5 blocks that should show empty state instead of vanishing:**
- featured-jobs, recent-jobs, featured-candidates, featured-companies, open-to-work

---

## UX-0.5: Theme Override Resilience

### Known Issue
- Job listings search input requires inline `style` to override Reign theme's `input[type="search"]` padding (already fixed)
- Same pattern may affect other inputs — needs browser verification

---

## Fix Status

### Must Fix — DONE
1. ~~J-1, J-2, J-3: Move remaining 3 hardcoded JS strings to state.strings~~ — Fixed in `e9b86ac`
2. ~~H-1 to H-4: Replace 4 hardcoded hex in bell hover with tokens~~ — Fixed in `412105b`

### Should Fix — DONE
3. ~~H-5, H-6: Scope `transition: all` to specific properties~~ — Fixed in `412105b`
4. ~~Add `role="alert"` to all error containers (4 missing)~~ — Fixed in `e9b86ac`
5. ~~Add `aria-live="polite"` to all dynamic list containers (3+ missing)~~ — Fixed in `e9b86ac` (Free) + `349024c` (Pro)

### Nice to Have — IN PROGRESS
6. Add empty state messages to 5 vanishing blocks — fixing now
7. Review 4 silent catches — intentional, documented, no action needed
