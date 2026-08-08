# Plugin ↔ app functionality catalogue

> **⚠️ FOUNDATION, NOT A FINISHED AUDIT.** Scaffolded 2026-08-08. The **counts are real** — probed
> live, not read from the manifest. The **verdicts are starting points** and every `TODO` needs a
> person who knows the product. Fill them in and delete this banner: a half-filled matrix that looks
> finished is worse than an empty one, because it reads as assurance.

| | |
|---|---|
| **Plugin** | WP Career Board (`wcb/v1`) |
| **App** | `~/apps/careerboard-app` |
| **Scaffolded** | 2026-08-08 |
| **Live routes** | 82 |
| **Called by the app** | **53 of 82** |
| **Uncalled** | **29** — grouped below, each needs a verdict |

## Why this file exists

The capability catalogue is **plugin-owned** (`CAPABILITIES.md`, per rule 7 of the
`wbcom-mobile-app` skill). The app never re-enumerates features — it maps coverage against this
spine. This file is the other direction: **what the plugin still owes the app.**

Several app rows typically sit at Deferred for *plugin* reasons rather than app ones — a missing
config field, an undeclared enum, an endpoint that exists but is not member-reachable. The app-side
gate cannot express that. This file can.

The companion gate is `careerboard-app/docs/FEATURE-COVERAGE.md`, which blocks release on any `❌ Missing` row.

## How to finish this file

1. **Classify every uncalled route** as `🚫 Web-only` (by design), `⚠️ Deferred` (reason **and**
   target) or `❌ Missing`. There is no fourth answer.
2. **List what the plugin owes** — the config fields, enums and endpoints the app needs and does not
   have. That is the section that makes this file worth keeping.
3. **Run the faithfulness pass** (below).
4. Keep it current in the PR that moves a row.

---

## Uncalled routes — 29 of 82

| Group | Count | Verdict — fill in |
|---|---|---|
| `wizard` | 7 | TODO |
| `fields` | 5 | TODO |
| `admin` | 3 | TODO |
| `boards` | 3 | TODO |
| `candidates` | 3 | TODO |
| `ai` | 2 | TODO |
| `import` | 2 | TODO |
| `analytics` | 1 | TODO |
| `employers` | 1 | TODO |
| `geocode` | 1 | TODO |
| `search` | 1 | TODO |

**Read these as hypotheses.** A route name is not a verdict: the same name is an owner surface on one
product and a member surface on another. Money and moderation groups especially — member-side
*report* and *block* are App Store requirements, so never classify a whole `moderation` group as
admin without separating those two out first.

---

## What the plugin owes the app

<!-- Fill this in. Recurring shapes across the fleet:

  * A vocabulary the app has to hardcode because nothing publishes it (statuses, roles, stages).
    Publish it from the same array the plugin already validates against.
  * A route with no `enum` on its args, so client drift fails silently instead of 400-ing.
  * A capability that exists on the web but has no REST route at all.
-->

**Not yet written.**

---

## Faithfulness — the check that earns this file

A coverage matrix catches **absence**. It does not catch **divergence**: a screen that exists, works,
and shows something the site never said still scores ✅. WP Career Board hardcoded five pipeline
stages while the site's real, admin-editable stages were different, and the row read ✅ Done for
months.

The check is mechanical — diff every hardcoded list in app source against the live route schema's
`enum`:

```bash
curl -s http://career-board.local/wp-json/wcb/v1 | python3 -c "
import sys,json
d=json.load(sys.stdin)
for path,info in d.get('routes',{}).items():
    for ep in info.get('endpoints',[]):
        for name,spec in (ep.get('args') or {}).items():
            if spec.get('enum'): print(name, spec['enum'], path)
"
grep -rnE \"=\\s*\\[\\s*['\\\"][a-z_]+['\\\"]\\s*,\" ~/apps/careerboard-app/api ~/apps/careerboard-app/app
```

On Jetonomy this cleared five lists outright and found one real bug in the two the server did not
enum-validate. Where the server declares no enum, that is itself the finding: nothing stops the two
sides drifting.

---

## A warning about the route diff

The numbers above came from matching endpoint strings in the app's `api/` layer. On Jetonomy that
same extraction first reported **30 uncalled routes including `/search`** — on an app with a
`search.tsx` screen. It matched one call shape and missed others; the real number was 8. On
MediaVerse it missed three whole `api/` modules.

**Spot-check the list against the source before writing any row that says "missing".** A generated
list is not evidence.

---

## Verification status

| Level | State |
|---|---|
| Route reachability | ✅ 82 routes probed live |
| App endpoint usage | ⚠️ extracted, not spot-checked |
| Uncalled classification | ❌ not done |
| Enum faithfulness | ❌ not run |
| Runtime behaviour | ❌ not exercised |
| Ban gate (skill rule 2) | ❌ untested — a banned member holding a valid app password must 403 on every write |
