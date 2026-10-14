# WP Career Board 1.8.2 Translations: hand-off

For the translation team. Branch `1.8.2` in both repos, Free (`wp-career-board`) and Pro (`wp-career-board-pro`). Modelled on the Learnomy 2.1.2 hand-off.

1.8.1 shipped with translation catalogues last rebuilt on 8 July 2026, so every string added since then (multi-company employers, the redesigned dashboards, the pipeline and bell work) is English in every locale. This task brings both plugins to 100% in all five locales and ships the JavaScript translations, which 1.8.1 did not ship at all.

## Locales

Set in `.wbcom-i18n.json` in each repo. Keep the register: the translations already on the site use it.

| Locale | Language | Register |
|---|---|---|
| de_DE | German | informal (du) |
| fr_FR | French | informal (tu) |
| es_ES | Spanish | informal (tú) |
| nl_NL | Dutch | informal (je) |
| ko_KR | Korean | polite (해요체) |

Never translate the protected terms in `.wbcom-i18n.json` (`WP Career Board`, `Career Board`, `Pro`, `WordPress`, `BuddyPress`, `REST`, `API`, `CSV`, `AI`, `PWA`, `ATS` and the rest).

## Where each catalogue stands

Measured 14 Oct 2026: each `.po` merged with a template freshly generated from the `1.8.2` code. Fuzzy entries are hidden on the site (gettext skips them), so they count as work, the same as empty ones. All five locales currently sit at the same numbers.

| Plugin | Strings | Done | Fuzzy | Empty | Committed `.pot` |
|---|---:|---:|---:|---:|---|
| Free | 1,906 | 1,268 | 315 | 323 | 1,372 strings, 8 Jul 2026 (stale) |
| Pro | 1,124 | 699 | 174 | 251 | 772 strings, 8 Jul 2026 (stale) |

## Which files we ship

The plugins need WordPress 6.9, so `.l10n.php` is loaded first for PHP strings and `.mo` is the fallback. Neither covers strings shown by JavaScript (the block editor sidebars, `assets/field-builder.js` in Pro); those need the `.json` files.

| File | What it's for | Today |
|---|---|---|
| `.po` | The source you edit. Every other file is built from it. | Committed |
| `.mo` | PHP strings, fallback, and what Loco Translate and some caching plugins read. | Committed |
| `.l10n.php` | PHP strings, fastest. Preferred on 6.5+. | **Missing for every locale** |
| `.json` | JavaScript strings. | **Missing: `languages/*.json` is in `.gitignore` and `bin/package-dist.sh` never builds them, so the 1.8.1 zips have none** |

Step 5 below fixes both gaps.

## Steps, in order

Run each step in `wp-career-board/` and again in `wp-career-board-pro/`. You need Node 18+, WP-CLI, GNU gettext (`msgmerge`, `msgattrib`, `msgfmt`) and the `claude` CLI on the PATH. Run `npm ci` once per repo for `@wbcom/i18n-ai` (the `wbcom-i18n` command).

### 1. Pull the branch

```bash
git checkout 1.8.2 && git pull --rebase
```

### 2. Regenerate the template

```bash
npx grunt pot
```

Check that `languages/<slug>.pot` now has about 1,910 (Free) or 1,125 (Pro) `msgid` lines, matching the table above (Free's extra few come from `bin/` scripts that never ship; translate them anyway, they are harmless). If it is far off, stop: the template is being built from the wrong files.

### 3. Merge, then translate

```bash
npx wbcom-i18n sync                          # msgmerge: new strings empty, changed ones fuzzy
npx wbcom-i18n translate                     # AI first pass on empty + fuzzy only
npx wbcom-i18n translate --locales=ko_KR     # one locale at a time, if you split the work
```

Korean runs on Sonnet (set in the config). The others run on Haiku.

Then **review every entry the AI touched**, it is a first pass, not a finished translation. Learnomy's review found these, look for the same here:

- **German du/Sie:** a machine pass swapped "Sie" for "du" without changing the verb ("Verwenden du", "du verlieren"). Read the verbs, not just the pronoun.
- **Hallucinated words:** Learnomy found an unrelated noun ("Der Präsident") inserted into 40 strings. Skim for anything that is not in the English.
- **Option names in help text:** when help text quotes a setting by name, use that setting's own translation.
- **Placeholders:** keep `%s`, `%1$s`, `%2$d` exactly. A missing or reordered one breaks the sentence on the site and can leave a gap in an email. The tool marks a mismatch fuzzy, so it falls back to English instead of breaking, but it still has to be fixed.
- **Plurals:** fill every plural form. A `_n()` whose forms use different arguments cannot compile; report it as a code bug rather than working round it.

Once a fuzzy entry is right, remove its `fuzzy` flag, or it stays hidden.

### 4. Compile

```bash
npx wbcom-i18n compile        # .mo (msgfmt -c, leaves fuzzy out) + .json (wp i18n make-json)
```

Then build `.l10n.php` for all five locales:

```bash
P=wp-career-board   # or wp-career-board-pro
T=$(mktemp -d)
for po in languages/$P-*.po; do msgattrib --no-fuzzy -o "$T/$(basename "$po")" "$po"; done
wp i18n make-php "$T" languages
rm -rf "$T"
```

### 5. Ship the JSON files (developer step, once per repo)

The JSON files have to reach the zip. Pick the Learnomy way, so the repo and the zip carry the same files:

1. Remove the `languages/*.json` line (and its comment) from `.gitignore`.
2. Before regenerating, delete old JSON (`rm -f languages/*.json`): `make-json` never removes the file for a script that was renamed or lost its last string.
3. Check every JSON is named for a script WordPress actually loads. `make-json` names each file `<slug>-<locale>-<md5 of the script path>.json`, and it strips `.min.js` with a loose regex, so Learnomy's `assets/js/admin.js` ended up hashed as `a.js` and stayed English everywhere. For each JSON, read its `"source"` value and confirm that path exists in the repo:

```bash
for j in languages/*.json; do s=$(php -r 'echo json_decode(file_get_contents($argv[1]))->source;' "$j"); [ -f "$s" ] || echo "BAD $j -> $s"; done
```

No output means every file is keyed correctly.

### 6. Check

```bash
bash bin/check-i18n.sh        # no hard-coded English in blocks/ or assets/js
for po in languages/*.po; do printf '%s ' "$po"; msgfmt --statistics -o /dev/null "$po"; done
```

Every locale should report only translated messages, with no fuzzy and no untranslated ones.

### 7. Commit the language files only

Other people commit code to the same branch, so add by path:

```bash
git add languages/ .gitignore
git status --short          # expect only languages/ and .gitignore
git commit -m "Translations for 1.8.2: <locales>"
git push origin 1.8.2
```

No `Co-Authored-By`, `Claude-Session` or "Generated with" lines in any commit.

## Before saying done

- [ ] Both plugins: every locale has a `.po`, `.mo`, `.l10n.php` and its `.json` files, and every `.po` is at 100% with nothing fuzzy.
- [ ] `bash bin/check-i18n.sh` passes in both repos, and the JSON check in step 5 prints nothing.
- [ ] Set the site to German (`wp language core install de_DE`, then `wp site switch-language de_DE`) and check, as a candidate and as an employer: Find Jobs, a job page, the employer dashboard (Overview, Jobs, Applications), the candidate dashboard, and the notification bell. Nothing shows English or a raw `%s`.
- [ ] In the block editor, insert a Career Board block and a Pro block: the block name and its sidebar labels are in German.
- [ ] Repeat the front-end check in Korean, since it is the one non-Latin script.
- [ ] Put the site back to `en_US` afterwards.
