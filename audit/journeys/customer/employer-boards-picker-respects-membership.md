---
id: employer-boards-picker-respects-membership
priority: critical
personas: employer.stripe, employer.vercel
requires: mu:autologin, buddypress:groups, pro:bp-group-boards
last_verified: 2026-05-12
bug_ref: Basecamp 9877868161
---

# Boards picker in Post-a-Job only lists boards for groups the employer belongs to

**Why this journey exists:** Pro auto-creates a board for every BuddyPress group. Pre-1.1.1 the job form pulled every published `wcb_board` post unconditionally, so an employer who belonged to one group saw every group's board in the dropdown — exposing private group names and letting them post into communities they had no relationship with.

## Steps

1. Confirm `employer.stripe` is the creator (auto-member) of at least one group ("Acme Engineering") and NOT a member of at least one other group ("Globex Hiring").
2. As `employer.stripe`, navigate to `/post-a-job/?autologin=employer.stripe` → expect HTTP 200 and the multi-step job form to render.
3. Inspect the Boards dropdown → `document.querySelectorAll('#wcb-board-picker option')` (or the `[data-wcb-field="boardId"] option` equivalent) returns options whose names INCLUDE every group `employer.stripe` belongs to plus every non-group admin board.
4. Confirm the dropdown does NOT include any group's name that `employer.stripe` is not a member, mod, or admin of → e.g. "Globex Hiring" must be absent.
5. Repeat steps 2-4 as `employer.vercel` → expect the mirror result: their groups appear, `employer.stripe`'s groups do not.
6. As any user with `manage_options` (site admin), open the same page → confirm EVERY published board appears in the dropdown (site admin bypass is intentional).
7. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

None required — the journey only reads.

## Notes

The filter `wcb_board_options_for_employer` (Free, registered at `blocks/job-form/render.php`) is the contract. Pro's `BpGroupBoards::restrict_boards_to_user_groups()` implements it. Future Pro refactors that re-architect group/board mapping must keep the filter's behaviour intact or this journey will catch the regression.
