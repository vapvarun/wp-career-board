---
id: candidate-mention-or-message
priority: medium
personas: sarah.chen
requires: mu:autologin
last_verified: 2026-05-09
needs: cli
---

# Candidate uses any in-plugin messaging or mention surface

**Why this journey exists:** documents whether a direct-messaging or @mention surface exists in Free v1.1.0; if absent, guards that absence (no 500, no broken UI stub) and records the correct surface location for when it ships.

## Steps

1. As `sarah.chen`, navigate to `/?autologin=sarah.chen` → expect HTTP 200, candidate is logged in
2. Navigate to `/candidate-dashboard/?autologin=sarah.chen` → expect HTTP 200, review the rendered dashboard for any "Messages", "Inbox", "Mentions", or "Chat" surface → record presence or absence
3. GET `/wp-json/wcb/v1/` (REST root) → inspect the routes index; look for any route matching `/messages`, `/inbox`, `/chat`, or `/mentions` under the `wcb/v1` namespace → expect either: (a) a matching route exists and returns 200 when hit, OR (b) no such route exists (absence is acceptable at v1.1.0)
4. If a messaging route exists: POST a test message to it as `sarah.chen` → expect HTTP 200 or 201 with a non-empty response body
5. If NO messaging route exists: verify the dashboard does NOT render a broken/blank "Messages" panel (an empty-state with explanatory text is acceptable; a React error boundary or PHP warning is not)
6. GET `/wp-json/wcb/v1/settings/app-config` → inspect response for any `messaging_enabled` or `chat_enabled` flag → record value (true/false/absent)
7. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

None — read-only journey (no persistent data created).

## Notes

- This journey is intentionally marked `medium` because Free v1.1.0 does not include a built-in messaging or @mention feature. It exists to document the absence explicitly and prevent a future regression where a broken UI surface silently renders.
- If BuddyPress is active (`bp_is_active('messages')`), a messaging surface may be present via the BuddyPress integration. Record that source if discovered.
- When a messaging module ships, update this journey to `high` or `critical` and expand the steps accordingly.
