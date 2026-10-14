# Presentation review (judgment layer of the presentation gate)

`bin/presentation-gate.cjs` fails on what can be measured: overflow, stray left edges, mid-word breaks, clipping, contrast and wp-admin markup. Passing it means "nothing is broken". It does not mean "this looks finished". This review answers that question. It runs on Sonnet (repetitive work, owner decision 2026-10-13), after every gate run that touches UI, and always before a release.

## Inputs

- The gate's run folder: `report.json` plus one full-page screenshot per view × width × scheme (`<site>/qa-artifacts/presentation-gate/<run>/`).
- The views list: `docs/qa/presentation-views.json`.
- The bar: the `ux-foundation` skill (same shell, same card, same button ladder, same 5 badge variants, same empty state; one primary action; 40px targets; token colours), plus the owner decisions in this repo's QA handoff.

## Who you judge as

Two people, never QA's taste and never "would a checker pass this":

1. **The site owner** who installed the plugin and opens each page wondering "can I show this to my customers today?"
2. **The member** using it, an employer hiring or a candidate looking for work, at 1440 on a laptop and 390 on a phone.

Compare against the products these people already use (LinkedIn Jobs, Indeed, Greenhouse, Wellfound). If the page would look unfinished next to them, say so.

## What "half-cooked" looks like (call each one out)

- A screen that works but reads like a form dump: no hierarchy, no clear first action, everything the same weight.
- Empty, loading and error states that are a bare sentence instead of the empty-state primitive (icon, title, text, action).
- Several equal buttons where one main action and a menu would do.
- The same thing presented differently on two screens (cards, badges, dates, avatars, buttons, spacing).
- Placeholder or test-looking data in frame, grey avatars, broken images.
- Dark mode that is just inverted: muddy surfaces, lost borders, accents that disappear.
- Phone layouts that are the desktop squeezed: tiny targets, wrapped button grids, orphan buttons, long scrolls of chrome before content.
- Copy that is vague, technical, or blames the user.

## Output: one entry per view

```
<view-id>   Ship | Polish | Rework
  Hard findings: <each gate finding: real (why) | false positive (why the check is wrong, and the gate fix)>
  Impression: <one line as the site owner>
  Suggestions (most valuable first):
    - <what to change> - <why it helps the owner or member> - <ux-foundation rule or reference>
```

- **Ship:** finished; nothing a site owner would hesitate over.
- **Polish:** works, but at least one thing reads unfinished. Name it.
- **Rework:** the layout or flow is wrong for the member and needs redesigning, not tweaking.

Rules for suggestions:

- Each one is concrete enough to build ("move Export into the menu; keep Pipeline as the one main button"), never "improve spacing".
- Where the call is genuinely a matter of taste, recommend the default that is right for the typical owner, and name a filter the owner could use to change it.
- Never pad the list to look thorough. A view with nothing to fix is "Ship" with an empty list.

## After the review

The coding session re-checks the load-bearing findings, fixes them, and reruns the gate. Each gate false positive gets the gate fixed, with a planted sample proving the fix still fires on real defects. Never baseline it away.
