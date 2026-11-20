---
id: remote-shown-once-on-cards
priority: high
personas: anonymous, aisha.bello, employer.figma
requires: seed:jobs
last_verified: 2026-11-20
needs: cli
bug_ref: 10516698646
---

# A remote job shows Remote once: the work-mode chip, never also as its location

**Why this journey exists:** on a translated site the reserved `remote` term carries the translated name ("À distance"), so the setup wizard's English "Remote" sample location landed on a second term (`remote-2`) and cards read "Remote" chip + "Lisbon, PT, Remote". `Locations::place_terms()` now skips a "Remote" term on any slug, and the wizard seeds no "Remote" location (its remote samples use the reserved term).

## Steps

1. Seed the old wizard term: `wp term create wcb_location Remote --slug=remote-2`, a published job "Customer Happiness Lead" with `_wcb_remote` = `1` and locations `remote-2` + a city (e.g. `lisbon-pt`).
2. As anonymous, `/jobs/?wcb_search=Customer+Happiness+Lead` → the card shows a **Remote** chip and the location chip "Lisbon, PT" (no "Remote" in it), at 1440, 1024 and 390, light and dark, RTL.
3. Apply any filter or Load more (REST cards) → same location; `GET /wp-json/wcb/v1/jobs/{id}` has `location: "Lisbon, PT"` and `remote: true` (the mobile app reads these).
4. Job page: the Location row reads "Lisbon, PT"; Work Mode reads Remote; JSON-LD keeps `"jobLocationType":"TELECOMMUTE"` and has no address named Remote.
5. As candidate `aisha.bello`, save the job → Saved Jobs reads "Copperleaf Analytics · Lisbon, PT".
6. `/jobs/?wcb_location=remote` still lists jobs located Remote (the reserved term); the Remote only toggle still works.
7. Fresh install, run the setup wizard with sample data → no new "Remote" location term; the remote sample jobs carry the reserved `remote` term.
8. tail debug.log diff → expect ZERO new fatal/warning lines.

Automated: `wp eval-file wp-content/plugins/wp-career-board/tests/test-reserved-location-terms.php`.

## Teardown

```bash
wp post delete <job-id> --force
wp term delete wcb_location <remote-2-term-id>
```
