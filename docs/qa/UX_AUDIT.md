# WP Career Board — UX Audit

> **Per-template surface check.** Every view × every persona × every viewport × every theme mode.
> Run this when a release touches UI, or at least once per minor version.

The goal: catch silent surface regressions (broken spacing, wrong color token, hover/focus/visited state stripped by the theme, dark-mode bleed, mobile overflow) before a customer notices.

## Axes

| Axis | Values |
|------|--------|
| **Persona** | Anonymous, Candidate, Employer, Admin |
| **Viewport** | Desktop 1440px, Tablet 1024px (spot), Mobile 390px |
| **Theme mode** | OS-Light, OS-Dark (via `emulateMedia({ colorScheme: "dark" })`), site-toggle if plugin / theme ships one |
| **Browser** | Chromium primary; Firefox + Safari iOS in `manual_required[]` |

Every row below × every axis combination that applies = one audit cell.
Don't re-audit identical cells across releases — audit the ones that changed or the ones flagged in the last regression guard.

---

## Visual contract (every template)

- [ ] Primary layout renders at 1440px — no horizontal scrollbar
- [ ] At 390px — no horizontal scrollbar, no clipped text, no off-screen buttons
- [ ] Typography hierarchy intact (H1 > H2 > H3 > body) — use computed `font-size` spot check
- [ ] Spacing consistent with design tokens (`--wcb-space-*`, `--wcb-color-*`)
- [ ] Color tokens used (no hardcoded `#ffffff` outside debug/print styles)
- [ ] Icons load (no broken `<img>`, no 404 on SVG sprite)
- [ ] Images `loading="lazy"` where appropriate, `alt` set on content images

## Interactive states (every clickable element)

- [ ] **default** — visible, legible, correct color
- [ ] **hover** — discoverable change (color, bg, border, underline)
- [ ] **focus-visible** — clear focus ring, meets contrast, not suppressed by theme
- [ ] **active** — visual feedback on click
- [ ] **disabled** — clearly distinguishable, cursor `not-allowed`
- [ ] **visited** (links only) — different from default where meaningful

**Common trap:** theme CSS overrides button states. Always verify against the live site with the plugin's active theme, not just in isolation.

## Dark mode

- [ ] `emulateMedia({ colorScheme: "dark" })` → page remains readable
- [ ] No bleed of light-mode tokens (e.g. `#fff` background inside a dark container)
- [ ] Images / illustrations have dark variants or sufficient contrast
- [ ] Form inputs visible (borders, placeholder text)
- [ ] Focus rings visible against dark bg
- [ ] Code blocks, callouts, badges — all have dark variants

## Accessibility (spot check)

- [ ] Tab order logical
- [ ] Skip links present on templates with heavy navigation
- [ ] ARIA labels on icon-only buttons (apply / save-job / share / reject)
- [ ] Form inputs have `<label>` (or `aria-label` / `aria-labelledby`)
- [ ] Color contrast ≥ 4.5:1 for body text, ≥ 3:1 for large text
- [ ] `prefers-reduced-motion` respected (no auto-play animations)

---

## Plugin-specific template list

| Template | Route / Selector | Personas | Audit cells |
|----------|------------------|----------|-------------|
| Job board archive | `/jobs/` | Anonymous, Candidate, Employer | Desktop-L, Desktop-D, Mobile-L, Mobile-D |
| Single job | `/jobs/<slug>/` | Anonymous, Candidate, Employer | Desktop-L, Desktop-D, Mobile-L, Mobile-D |
| Apply form | Apply CTA on single-job | Candidate | Desktop-L, Desktop-D, Mobile-L, Mobile-D |
| Candidate profile (public) | `/candidate/<slug>/` | Anonymous, Candidate, Employer | Desktop-L, Desktop-D, Mobile-L, Mobile-D |
| Candidate dashboard | `/dashboard/` (or wherever the plugin routes the front-end candidate view) | Candidate | Desktop-L, Desktop-D, Mobile-L, Mobile-D |
| Employer dashboard / "My jobs" | `/employer/` | Employer | Desktop-L, Desktop-D, Mobile-L, Mobile-D |
| Post-a-job composer | `/post-a-job/` (or modal) | Employer | Desktop-L, Desktop-D, Mobile-L, Mobile-D |
| Applicants list | Per-job applicants view | Employer | Desktop-L, Desktop-D |
| Pipeline (Pro) | Per-job pipeline view | Employer | Desktop-L, Desktop-D |
| Search results | `/jobs/?s=...&filters=...` | Anonymous, Candidate | Desktop-L, Desktop-D, Mobile-L, Mobile-D |
| Company profile | `/company/<slug>/` | Anonymous, Candidate | Desktop-L, Desktop-D |
| Saved jobs | Candidate dashboard tab | Candidate | Desktop-L, Mobile-L |

(Delete or expand rows to match the live plugin once URL slugs are confirmed.)

---

## Block / component surfaces

If the plugin registers Gutenberg blocks (jobs list, single job, application form, etc.), audit each one rendered:

| Block / shortcode | Block editor preview | Front-end render | Dark mode | Mobile |
|-------------------|----------------------|------------------|-----------|--------|
| `wcb/jobs` (placeholder) | ☐ | ☐ | ☐ | ☐ |
| `wcb/single-job` (placeholder) | ☐ | ☐ | ☐ | ☐ |
| `wcb/apply-form` (placeholder) | ☐ | ☐ | ☐ | ☐ |

> Replace placeholders with the actual `block.json` `name` values from `blocks/*/block.json`.

Block editor checks:
- [ ] Inspector controls render without PHP/JS errors
- [ ] Preview matches front-end render (no "frontend-only" CSS surprises)
- [ ] Block validates (no "block contains unexpected content" warning on reload)

---

## Admin surfaces

For each plugin admin page (`admin.php?page=wcb*`):

- [ ] Page renders without `Notice:` or `Warning:` in debug.log
- [ ] Every tab renders — iterate `.nav-tab` and click each
- [ ] Every settings section has a label, help text, and saves
- [ ] List tables (jobs, applications, candidates, employers, fields, alerts): search, filter, pagination, bulk actions
- [ ] Action buttons on list rows: view, edit, delete, custom actions
- [ ] Admin responsive: WP collapses sidebar at 782px — verify plugin pages still usable
- [ ] Screen options / Help tabs (if plugin adds them)

---

## Email surfaces

For each transactional email the plugin sends (new application, job approved, alert match, application-status change, weekly digest):

- [ ] Rendered HTML opens in Mailpit / Mailhog without layout break
- [ ] Dark mode email client (Gmail dark, Apple Mail dark) — text readable, buttons visible
- [ ] Merge tags resolve (`{{candidate_name}}` not literal)
- [ ] Unsubscribe / manage-preferences link works
- [ ] Plain-text fallback present

---

## Dark mode protocol (MCP-specific)

```javascript
// Chromium
browser_run_code({
  code: `await page.emulateMedia({ colorScheme: "dark" })`
})
browser_take_screenshot({ filename: "dark-<template>.png" })

// Reset before exiting
browser_run_code({
  code: `await page.emulateMedia({ colorScheme: "light" })`
})
```

Every dark-mode screenshot in this audit is one snapshot to attach to the PR that changed the surface.

---

## Output

If invoked as part of an agent walk, append to `manual_required[]` anything that can only be verified on Firefox or Safari iOS. The Chromium walk can cover Chrome-mode + dark-mode + viewport matrix.

If invoked as a human audit, treat each unchecked row as a blocking issue, file a Basecamp card, and halt the release.

## Regression guard promotion

After two clean release cycles where a UX row passes without touching it, the row is stable and can be moved to a structural assertion in `AGENT_SMOKE_RUNBOOK.md`. The rest stay here as slower, human-verified surface checks.
