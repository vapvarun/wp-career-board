# Scope and Schema Standard (all Wbcom plugins and themes)

Owner rule, 2026-11-09. Written after Learnomy reached 87 database tables and its fixes kept growing past the problems they were for.

The owner's words:
- "over cooking tables is also not good idea"
- "we need to put a lock on table count"
- "version are just number ... if we have issue we have to solve right now with proper planning, all blast radius"

## 1. Table lock: no new table without the owner's yes

- Every plugin or theme with custom tables commits `audit/tables.lock`, listing every CREATE TABLE.
- The gate `php ~/.claude/skills/wp-plugin-development/scripts/table-lock-check.php <dir>` fails when:
  - code creates a table that is not in the lock;
  - code has more CREATE TABLE statements than the lock allows;
  - the lock lists a table the code no longer creates.
- It runs in `gate-check.sh` (row TL) at release. Wire it into each plugin's pre-commit hook and local CI as well.
- **Adoption:** `--init` once writes the lock from today's code. The tables that exist on 2026-11-09 are the approved baseline.
- **Changing the lock** (adding a table or a CREATE statement) needs the owner's yes, quoted in the commit body.
- **Before asking for a table**, the card says why each of these cannot hold the data, in this order:
  1. an existing table plus a column;
  2. the plugin's audit or history log (a type plus JSON metadata), for history, maps and records of what changed;
  3. typed categories or tags (type plus object_type), for labels, folders and skills;
  4. a generic config table (type, object_id, JSON), for per-object settings;
  5. post, user or term meta, for small per-object values read with their object.

  A table is right when the rows are queried on their own at scale, with their own indexes: ledgers, queues, per-user-per-item state.
- **Pro reuses Free's tables before adding its own.** Pro always runs on Free, so a Pro table that copies the shape of a Free table is a bug. Free never queries a Pro table.
- **New stored options and settings keys** get the same review: name them on the card, and justify each one against an existing key or a log row.

## 2. Plan first, with the full blast radius

- No code before a plan comment on the card.
- The plan names every reader and writer of the data or behaviour touched:
  - Free and Pro;
  - REST, admin, frontend, blocks, emails;
  - CLI, cron and background jobs;
  - privacy export and erase, uninstall, demo seeder;
  - caches, hooks, the manifest.
- For a data change, the plan also gives the step order, the count check, and the failure path.
- **Data moves follow the plugin's data rules:** copy first, count-check, and drop the old store only when the counts match. Running the step twice is harmless, and the fresh-versus-updated parity check is run on it.
- **No parking.** A real defect or an approved cleanup is fixed in the current release. "Next version" is never the answer to a known problem. Optional ideas that are not problems go to the owner as their own question.

## 3. Smallest complete fix first

- When options go to the owner, the smallest option that fully solves the problem comes first and is the recommended one.
- New notices, screens, helper classes or rules beyond the card's problem need the owner's yes by name.
- Findings next to the card become their own cards. They never widen the card under work or test.

## Who enforces it

| Layer | Where |
|---|---|
| Release gate | `gate-check.sh` row TL, using `table-lock-check.php` |
| Commit gate | each plugin's pre-commit hook and local CI calls the same script |
| Developer | `wp-plugin-development` (plan-first and table rules) |
| QA | `wp-card-qa` owner questions S-2 (new table or option without a yes: bounce), S-3 (plan with full reach posted before code), S-4 (smallest option first) |

Each plugin keeps a synced copy at `docs/standards/scope-and-schema.md`. Link here; don't re-explain per plugin.
