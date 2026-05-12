# wppqa baseline — 2026-05-12

Per Phase 0 of `/wp-plugin-onboard`: run wppqa BEFORE manifest analysis, save
the findings, classify real vs heuristic false positives. This is the
baseline at `master` HEAD `2483a69` (post-1.1.1 platform work).

## Summary

| Check | Failed | Passed | Real bugs | False positives |
|---|---|---|---|---|
| plugin-dev-rules | 3 high, 5 warnings | 6 | 0 high | 3 high, 1 warning |
| rest-js-contract | 1 high | 34 | 0 | 1 |
| wiring-completeness | 0 | (skipped) | 0 | — |

**Net real bugs: 0.** Net warnings worth attention: 4 (1 breakpoint count + 3 tap targets).

## High-severity findings — all false positives

### nonce-no-cap (×3)

The scanner expects `current_user_can(...)` immediately after a nonce check.
This plugin uses `wp_is_ability_granted('wcb/...')` instead (Abilities API
polyfill, see `core/abilities-api-polyfill.php`). The scanner doesn't
recognize the call. Every flagged site DOES have a permission check:

| File:line | Permission check on line | Verified |
|---|---|---|
| `admin/class-admin-settings.php:292` | `wp_is_ability_granted('wcb/manage-settings')` line 294 | ✓ |
| `admin/class-email-settings.php:435` | `wp_is_ability_granted('wcb/manage-settings')` line 438 | ✓ |
| `modules/antispam/class-anti-spam-module.php:277` | `wp_is_ability_granted('wcb/manage-settings')` line 279 | ✓ |

**Action:** none. Tracked here so future audits don't re-flag.

### rest-js-mismatch — `data.reason` (×1)

`assets/js/admin.js:183` writes `data.reason = result;` while building a
POST body for `/wcb/v1/jobs/{id}/reject`. The scanner mapped the access
to the wrong route (`/employers/(?P<id>\d+)/jobs`, which doesn't take
`reason`) and read it as response-key access instead of request-body
construction.

- Real target route: `/wcb/v1/jobs/(?P<id>\d+)/reject` (registered in
  `modules/moderation/class-moderation-module.php:75`).
- The route's schema explicitly declares `reason` as an input param
  (lines 82-83) and returns it in the response too (line 225).
- The JS is correct end-to-end.

**Action:** none. Tracked here so future audits don't re-flag.

## Medium / low warnings — worth addressing

| Severity | Code | File:line | Fix |
|---|---|---|---|
| medium | `breakpoint-proliferation` | 5 distinct breakpoints (1100, 600, 640, 768, 782px) | Consolidate to the canonical 640/1024/1440 trio per frontend-responsive Rule 1. |
| low | `tap-target-small` | `assets/css/admin/shared.css:60` (32px button) | Bump to ≥40px or scope to non-touch contexts. |
| low | `tap-target-small` | `assets/css/admin/shared.css:72` (28px button) | Same. |
| low | `tap-target-small` | `assets/css/admin.css:811` (13px button — likely a typo or icon-only) | Investigate — 13px height is unusable on touch. |
| low | `tap-target-small` | `assets/css/frontend-components.css:196` (32px button) | Bump to ≥40px on touch viewports. |

These don't block 1.1.1 ship but are tracked for the next CSS pass.

## Notes for the wppqa team

The two heuristic shortcuts that misfired here are general enough that
fixing them upstream would improve every WBcom plugin's baseline:

1. Teach `plugin-dev-rules` to recognize `wp_is_ability_granted` as an
   authorization check (it's the canonical Abilities API gate in WP 6.5+).
2. Teach `rest-js-contract` to distinguish request-body construction
   (`data.X = ...` ASSIGNMENT before an `apiFetch` POST) from response-key
   reading (`response.X` ACCESS after `.then(response => ...)`).

A `plan/` ticket for those two upstream improvements would track the
work across plugins.
