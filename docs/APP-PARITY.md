# App parity — pointer

The parity gate for the Career Board mobile app lives in the **app repo**, at the fleet-wide path:

    careerboard-app/docs/FEATURE-COVERAGE.md

Every Wbcom app keeps its gate at that exact path, so any app's current status is findable without
asking which repo or what the file is called. This file is a pointer, not a copy — two copies of a
gate is no gate, and the copy in the plugin is the one that would rot.

## What it is

Every capability a member has on the Career Board **website**, mapped to its **app** surface, with a
hard release gate of **zero `❌ Missing`**. The app is a frontend face of this plugin — the website in
a member's pocket — so a member-facing plugin change is a change to that table.

## When plugin work touches it

- **Adding a member-facing capability** (a route, a field, a setting members see): add the row, in the
  same change. A capability that ships without a row is invisible to the app team until release.
- **Changing a response shape**: check the rows that read it. The resume editor once wiped five fields
  because `PUT` accepted what `GET` never returned.
- **Adding a value to a fixed vocabulary** (application statuses, job types, stages): the app mirrors
  some of these. Nothing advertises the application-status set over REST, so a new status silently
  never reaches the app.

## Status vocabulary

`✅ Done` — the app does the same thing, not merely something ·
`⚠️ Partial` — works, with the missing piece named ·
`⚠️ Deferred` — deliberately not built, reason recorded ·
`❌ Missing` — **blocks release** ·
`🚫 Web-only` — belongs to the site owner, by decision.
