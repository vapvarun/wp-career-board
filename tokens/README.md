# WP Career Board — Design Tokens (DTCG)

Source-of-truth design tokens for WP Career Board (Free + Pro), authored in the
[W3C Design Tokens Community Group (DTCG)](https://github.com/design-tokens/community-group)
JSON format.

These JSON files are not yet wired into the build. They land in 1.2.x as
**foundation-only** so that 1.3.0 can flip the switch to a token-driven
pipeline without a separate "rewrite tokens" release.

## Current status (1.2.x)

- `assets/css/frontend-tokens.css` is the runtime source of truth.
- `tokens/*.json` mirror that file in DTCG format.
- A future `npm run build:tokens` step (1.3.0) compiles JSON to CSS.

Until that step lands, **edit `assets/css/frontend-tokens.css` directly** and
mirror the change here. The 1.3.0 plan inverts that flow.

## File map

| File              | DTCG `$type`            | Covers                                                   |
| ----------------- | ----------------------- | -------------------------------------------------------- |
| `space.json`      | `dimension`             | `--wcb-space-*` scale (4–48 px)                          |
| `text.json`       | `dimension`, `fontWeight`, `number` | `--wcb-text-*`, `--wcb-font-*`, `--wcb-leading-*` |
| `radius.json`     | `dimension`             | `--wcb-radius-*`                                         |
| `shadow.json`     | `shadow`                | `--wcb-shadow-*` + focus ring                            |
| `color.json`      | `color`                 | Status palette, neutrals, primary tints                  |
| `transition.json` | `duration`              | `--wcb-transition-*`                                     |
| `avatar.json`     | `dimension`, `color`    | `--wcb-avatar-*`                                         |
| `icon.json`       | `dimension`, `number`   | `--wcb-icon-*` + stroke width                            |

## Naming convention

Output CSS variables are derived as `--wcb-{group}-{name}`. For example:

```
tokens/space.json → "lg": { "$value": "16px" }   →   --wcb-space-lg: 16px;
```

The `wcb-` prefix is fixed; the rest of the path maps 1:1.

## Pro plugin

`wp-career-board-pro` consumes the same tokens at runtime (CSS cascade) and
adds **no** Pro-specific tokens. If Pro ever needs new tokens, they ship from
this directory under a `pro/` subgroup.

## Migration plan

See [`docs/PLAN-1.3.0-DTCG-TOKENS.md`](../docs/PLAN-1.3.0-DTCG-TOKENS.md).
