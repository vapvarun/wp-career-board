---
id: private-candidate-profile-answers-404
priority: medium
personas: anonymous, sarah.chen, varundubey
requires: mu:autologin
last_verified: 2026-10-13
needs: cli
bug_ref: 10355318941
---

# A private candidate profile answers exactly like a missing one

**Why this journey exists:** `GET /wcb/v1/candidates/<id>` answered 403 `wcb_private` for private profiles and 404 for unknown ids, so the route confirmed which user ids exist and who hides their profile. Since 1.8.1 both answer 404 `wcb_not_found` "Candidate not found.".

## Steps

1. Make a candidate private: `wp user meta update <sarah-id> _wcb_profile_visibility private`.
2. Anonymous: `curl -s -i "$WCB_SITE/wp-json/wcb/v1/candidates/<sarah-id>"` → HTTP 404, body `{"code":"wcb_not_found","message":"Candidate not found."}`. Compare with `…/candidates/99999999` (no such user) → identical status, code and message. No `wcb_private` anywhere.
3. As another candidate or an employer (not self, not admin) → the same 404.
4. Owner and admin still read it: as `sarah.chen` → 200 with her profile; as `varundubey` → 200.
5. Make it public again: `wp user meta update <sarah-id> _wcb_profile_visibility public` → anonymous gets 200 with the public fields.
6. Dashboard: Candidate Dashboard > Profile > visibility toggle still saves (`PUT /candidates/<id>`), and the toggle value matches the user meta.
7. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

```bash
wp user meta update <sarah-id> _wcb_profile_visibility public
```
