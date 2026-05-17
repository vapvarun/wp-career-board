# wppqa Baseline — wp-career-board

**Date:** 2026-05-17
**Version:** 1.2.0
**Source:** mcp__wp-plugin-qa__wppqa_check_*

---

## Per-check results

| Check | Passed | Failed | Warnings | Duration |
|---|---|---|---|---|
| plugin_dev_rules | 9 | 0 | 9 | 71 ms |
| rest_js_contract | 37 | **1** | 0 | 16 ms |
| wiring_completeness | 0 | 0 | 0 (skipped) | 0 ms |

---

## High-severity findings (1)

### REST-JS-001 — `data.reason` envelope mismatch
- **File:** `assets/js/admin.js:183`
- **Route:** `/wcb/v1/employers/(?P<id>\d+)/jobs`
- **PHP returns:** `[id, title, status, statusLabel, permalink, editUrl, appCount, appLabel]` (in `api/endpoints/class-employers-endpoint.php:102`)
- **JS reads:** `data.reason` — not present in response shape.
- **Class:** Silent UI bug. JS path never fires the error branch because the key is always `undefined`.
- **Fix:** Either rename the PHP key to `reason` (if JS is the contract) OR update JS to read one of the existing keys. Add a JSON contract fixture so this drifts loudly next time.

---

## Medium-severity findings (1)

### PLUGIN-DEV-RULES-001 — 8 distinct CSS breakpoints
- frontend-responsive Rule 1 wants ≤3 breakpoints (640/1024/1440 typical).
- Found: 600, 640, 768, 782, 900, 960, 1024, 1100 px.
- Cause: per-component fixes that should be solved by adjusting the component shape, not a new breakpoint.
- Where: scattered across `assets/css/admin*.css`, `frontend-components.css`, `wcb-ui.css`.

---

## Low-severity findings (8)

All `tap-target-small` — admin-only buttons rendered at < 40 px. None are customer-facing primary actions. Defer to a UX polish pass.

| File | Line | Height |
|---|---|---|
| `assets/css/admin/shared.css` | 60 | 32 px |
| `assets/css/admin/shared.css` | 72 | 28 px |
| `assets/css/admin.css` | 811 | 13 px (likely a chip, not a button) |
| `assets/css/frontend-components.css` | 196 | 32 px |
| `assets/css/frontend-components.css` | 875 | 30 px |
| `assets/css/wcb-ui.css` | 193 | 32 px |
| `assets/css/wcb-ui.css` | 767 | 16 px (likely a close icon) |
| `assets/css/wcb-ui.css` | 1207 | 32 px |

---

## Release-readiness verdict

NOT release-ready. The `data.reason` envelope mismatch is a silent customer-facing bug — the admin job-rejection flow reads a property that never exists, so the rejection-reason UI is dead. Must fix before tagging 1.2.1.

Breakpoint discipline is a debt item, not a release-blocker. Tap targets are admin-side; can ship.
