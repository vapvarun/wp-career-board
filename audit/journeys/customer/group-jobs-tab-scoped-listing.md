---
id: group-jobs-tab-scoped-listing
priority: critical
personas: wcbp_p5_candidate
requires: mu:autologin, buddypress:groups, pro:bp-group-boards, seed:jobs
last_verified: 2026-06-27
bug_ref: Basecamp 9877872730, 9895174032
---

# BuddyPress group Jobs tab lists only that group's jobs

**Why this journey exists:** Every BuddyPress group has an auto-created Career Board, and the group's Jobs tab embeds the `wp-career-board/job-listings` block scoped to that board. Pre-1.1.1 Pro emitted the wrong attribute name (`defaultBoardId` instead of `boardId`), so the listing block silently ignored the scope and rendered every job on the site inside every group's tab.

## Steps

1. Confirm at least two BuddyPress groups exist with auto-created boards — `wp bp group list` returns groups where each has a `wcbp_board_id` group-meta value.
2. Pick two groups (Group A, Group B) with distinct `wcbp_board_id` values. Assign one job to Group A's board, one to Group B's board, and leave at least one other published job assigned to neither.
3. As `wcbp_p5_candidate`, navigate to `/groups/<group-a-slug>/jobs/?autologin=wcbp_p5_candidate` → expect HTTP 200.
4. Confirm exactly ONE job card renders → `document.querySelectorAll('.wcb-job-card').length === 1` AND its title matches Group A's seeded job.
5. Confirm the active-filter chip reads Group A's name → `document.querySelector('.wcb-active-filter-tag')?.textContent` includes Group A's title.
6. Confirm the results count reflects the scoped result → `document.body.innerText` contains `1 job` (verified 2026-06-27). The proof that the filter is active — not just one job site-wide — is step 4: only Group A's job renders while other published jobs (e.g. Group B's) are excluded.
7. Navigate to `/groups/<group-b-slug>/jobs/` → repeat the single-card assertion for Group B's job.
8. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

```bash
# Leave groups + boards in place; only revert the test job assignments.
wp post meta update <test-job-a> _wcb_board_id <original-board-or-default>
wp post meta update <test-job-b> _wcb_board_id <original-board-or-default>
```

## Notes

The fix was a one-character attribute rename in `wp-career-board-pro/integrations/buddypress/class-bp-group-boards.php` — the journey verifies the contract from the candidate's POV (right jobs in right tab), not the attribute itself, so it survives any refactor of the embed mechanism.

Since 1.2.0 the BuddyPress group Jobs tab also depends on the `wcb_page_needs_frontend_assets` filter (Basecamp 9895174032). If the filter is not wired, `frontend.css` / `frontend-tokens.css` / `frontend-components.css` do not load for the BP group tab context and primitives like `.wcb-hidden` will not resolve, causing both states of any Interactivity API toggle to render stacked. Add a step asserting ZERO stacked `.wcb-hidden` elements are visible in the rendered tab.
