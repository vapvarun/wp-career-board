---
id: admin-lists-views-hold
priority: high
personas: varundubey
requires: mu:autologin, seed:jobs, seed:companies, seed:applications
last_verified: 2026-10-25
bug_ref: 10412595845, 10417317114, 10417326173, 10430677630, 10430657014, 10412591612, 10417521347
---

# Admin lists keep their view, say why they are empty, and offer the right actions

**Why this journey exists:** the Career Board lists are custom `WP_List_Table` screens, so each piece core gives `edit.php` for free (Trash actions, a view surviving a bulk action, a "no match" message, menu highlight, term order) had to be built here, and several were missing.

## Steps

1. Jobs → Trash view, with at least one trashed job → expect the bulk actions to be exactly Restore and Delete Permanently. Restore one → expect it back in All and the page still on `post_status=trash`. Delete Permanently one → expect `get_post()` null. Repeat on Companies (card:10412595845).
2. Empty the Trash view on Jobs and Companies → expect "The Trash is empty." with a View all link, not "No jobs yet".
3. Candidates with one suspended candidate → expect a "Suspended (1)" view; open it → only that candidate; unsuspend from the row → expect the page to stay on the Suspended view and show "match your filters" with Clear filters (card:10417317114). Search the list for `qa-rft` and suspend a row from the results → the page returns with `s=qa-rft` kept and the search box filled; every row action link (Suspend, Unsuspend, Dismiss reports) carries the current view and search (bounced 2026-10-24: the actions dropped both).
4. Employers → expect no Banned view while no employer is banned; ban one → "Banned (1)" appears and filters to them (card:10417326173). Unban from the Banned view → the page stays on Banned, now "Banned (0)" with the empty state, not All.
5. Applications → search for a string no applicant has → expect "No applications match your filters" with Clear filters and no Export CSV button; clear → Export CSV is back (card:10430657014).
6. Open Add New Job, Edit Job, Add New Company, Add New Resume and Add New Board → expect Career Board open in the admin menu with the matching submenu item current, never Posts or nothing (card:10430677630).
7. Settings → expect a Help group with Documentation opening `docs.wbcomdesigns.com/products/wp-career-board/` in a new tab, with "(opens in a new tab)" for screen readers; `wcb_docs_url` filters the link (card:10412591612).
8. Job Types → expect an Order column (dash plus "Sorted by name" when empty). Quick Edit a term → the Order field is prefilled with its current value; set 1 and 2 on two terms and save → term meta `wcb_order` holds them; sort by Order ascending → unordered terms first by name, then 1, 2; descending reverses (card:10417521347). Use the Order column header link (`orderby=order`) and read the row order, not only the meta: on 2026-10-24 the meta saved but the list order did not change; the sort now runs in `pre_get_terms`, ties break by name.
9. Repeat step 1 and step 5 at 390 → actions and empty states fit, no horizontal scroll.
10. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

Delete the QA-RFT posts and users created, and `delete_term_meta( $term, 'wcb_order' )` on every term ordered in step 8.
