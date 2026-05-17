# PLAN — 1.3.0 DTCG Token Pipeline

**Status:** Foundation prep landed in 1.2.x. Pipeline activation queued for 1.3.0.
**Owner:** Frontend / build chain.
**Free + Pro:** Tokens live in Free; Pro consumes via the CSS cascade. Lockstep release.

---

## Why this exists

The 1.2.x release closed the "100% token-ready" cleanup: every block and template now reads from `--wcb-*` custom properties instead of raw hex / px / rem values. The remaining problem is the source-of-truth:

- `assets/css/frontend-tokens.css` is hand-edited and ships as the runtime root.
- There is **one** source per token, but it lives in CSS. Every other consumer — `theme.json`, design tools, documentation, future React/native renderers — needs its own copy and drifts.
- DTCG (W3C Design Tokens Community Group format) is the emerging standard that Figma, Style Dictionary, and major design systems already speak. Authoring there means one edit propagates everywhere.

1.3.0 inverts the flow: **JSON is the source, CSS is generated.**

---

## Current state (1.2.x — landed)

| File                                            | Role                                                                                                                          |
| ----------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| `assets/css/frontend-tokens.css`                | **Runtime source of truth.** Hand-edited. Enqueued on every block-loaded page.                                                |
| `tokens/*.json`                                 | **Mirror copies in DTCG format.** Not yet wired into a build. Foundation only.                                                |
| `tokens/README.md`                              | Inventory + naming convention.                                                                                                |
| Every block `style.css`                         | Reads `var(--wcb-*)`. No hex / raw px outside `:root`. Verified by the 4-agent sweep on 2026-05-13.                            |

There is **no** build step yet. The 1.2.x edit-flow stays "edit the CSS, mirror into JSON if you remember." That is intentional - the 1.2.x release should not ship a half-wired pipeline.

---

## Target state (1.3.0)

### 1. JSON becomes the source

```
tokens/space.json  ────┐
tokens/text.json   ────┤      npm run build:tokens
tokens/color.json  ────┤      (Style Dictionary v4)
tokens/radius.json ────┤  ──────────────────────────►  assets/css/frontend-tokens.css
tokens/shadow.json ────┤                                (generated, never hand-edited)
tokens/transition.json─┤
tokens/avatar.json ────┤
tokens/icon.json   ────┘
```

`frontend-tokens.css` becomes a **generated artifact**. A banner comment marks it as such and points contributors at `tokens/*.json`.

### 2. theme.json + block.json sync

Style Dictionary emits a second target: `theme-tokens.json` consumed by `theme.json` settings.color.palette and settings.spacing.spacingSizes. WP block editor users see the same token palette as the frontend.

### 3. Pro consumes the same artifact

`wp-career-board-pro` enqueues nothing extra. It already inherits `--wcb-*` via the cascade. Lockstep: a Pro release using a Free version that hasn't built `frontend-tokens.css` is a release blocker (CI gate).

---

## Migration phases

### Phase A - Build chain (1.3.0-alpha)

1. Add `style-dictionary` to `devDependencies` (already on npm via `@wordpress/scripts`, no new lockfile churn beyond Style Dictionary itself).
2. Add `tokens.config.mjs` at repo root with a single platform (`css`) targeting `assets/css/frontend-tokens.css`.
3. Add `npm run build:tokens` script. Output of the script must `diff -q` clean against the hand-edited `frontend-tokens.css` from 1.2.x. Any drift = JSON edit required, not CSS edit.
4. Wire `build:tokens` into `npm run build` so the production zip contains a freshly-generated tokens stylesheet.

**Acceptance:** `npm run build:tokens && git diff --quiet` returns 0 on a clean tree.

### Phase B - Author-flow flip (1.3.0-beta)

1. Add banner comment to `frontend-tokens.css`:
   ```css
   /* GENERATED FILE - do not edit by hand. Source: tokens/*.json. Run `npm run build:tokens`. */
   ```
2. Add a `.githooks/pre-commit` check: if `frontend-tokens.css` is staged but `tokens/*.json` is not, reject the commit with a helpful message.
3. Update `CLAUDE.md` to point contributors at `tokens/` for any new design token.
4. Land `docs/CONTRIBUTING-TOKENS.md` (short - "edit JSON, run build, commit both").

**Acceptance:** Hand-editing `frontend-tokens.css` fails CI; the same change via JSON passes.

### Phase C - theme.json + Pro coordination (1.3.0)

1. Add second Style Dictionary platform: `wp-theme-json`. Emits `assets/theme-tokens.json` with a subset of tokens (spacing scale + status palette).
2. Add a small PHP filter in `core/class-plugin.php` that merges `assets/theme-tokens.json` into the active theme's `theme.json` palette/spacingSizes at runtime.
3. Document the Pro contract: Pro depends on Free's generated `frontend-tokens.css` being on disk. If Pro is built standalone (it shouldn't be), CI fails.

**Acceptance:** Block editor color picker shows our status palette; `var(--wp--preset--color--wcb-success)` resolves to the same value as `var(--wcb-success)`.

### Phase D - Editor + design-tool round-trip (post 1.3.0, optional)

DTCG is bi-directional. Figma's Tokens Studio plugin can read/write the same JSON. Once the build is stable:

1. Export DTCG JSON from Figma to `tokens/figma-import/` on a branch.
2. Run a diff script that surfaces "designer changed `color.success.base` from #16a34a to #15803d - review."
3. Cherry-pick desired changes into `tokens/`.

This is *not* in the 1.3.0 scope - it's the payoff that justifies all of the above. The 1.3.0 plan only needs to make it possible, not enable it.

---

## Risks + mitigations

| Risk                                                                 | Mitigation                                                                                                          |
| -------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Style Dictionary output drifts from 1.2.x CSS by accident            | Phase A gate: `diff -q` must be clean. Any drift fails the upgrade PR.                                              |
| Contributors keep hand-editing CSS after Phase B                     | Pre-commit hook + banner comment + CONTRIBUTING-TOKENS.md.                                                          |
| Pro releases out of step with Free's regenerated tokens              | Lockstep version gate (already enforced) plus a Pro CI check that `frontend-tokens.css` exists in the Free sibling. |
| color-mix() lines in current CSS (lines 55-58 of frontend-tokens.css) cannot be represented in pure DTCG | Emit them as a hand-maintained "extras" partial that Style Dictionary appends. Documented in `tokens/README.md`.    |
| Shadow tokens with rgba and multi-stop fall outside Style Dictionary's default shadow transformer | Phase A includes a custom transformer in `tokens.config.mjs`. The 5 shadows here are deliberately single-layer.     |

---

## Out of scope for 1.3.0

- React Native / mobile token emission.
- Per-mode (dark mode) tokens. WCB has no dark mode yet; adding one is its own release.
- Replacing the `--wcb-primary` / `--wcb-base` / `--wcb-contrast` core palette tokens in `frontend.css` - those are theme-overridable by design and stay where they are.

---

## Reference

- DTCG spec: https://design-tokens.github.io/community-group/format/
- DTCG repo: https://github.com/design-tokens/community-group
- Style Dictionary v4: https://styledictionary.com/
- Foundation files landed: `tokens/*.json`, `tokens/README.md`.

---

## Tracker

| Item                                                            | Status     | Notes                                          |
| --------------------------------------------------------------- | ---------- | ---------------------------------------------- |
| `tokens/` directory with DTCG JSON for every token family       | landed     | 1.2.x foundation                               |
| `tokens/README.md` inventory + naming convention                | landed     | 1.2.x foundation                               |
| `docs/PLAN-1.3.0-DTCG-TOKENS.md` (this doc)                     | landed     | 1.2.x foundation                               |
| Style Dictionary config + `npm run build:tokens`                | queued     | Phase A                                        |
| `frontend-tokens.css` becomes generated artifact                | queued     | Phase B                                        |
| `.githooks/pre-commit` rejects hand-edited CSS                  | queued     | Phase B                                        |
| `theme.json` palette sync                                       | queued     | Phase C                                        |
| Pro CI gate for Free's generated CSS                            | queued     | Phase C                                        |
| Figma round-trip via Tokens Studio                              | post-1.3.0 | Phase D, optional                              |
