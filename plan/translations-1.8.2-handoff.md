# WP Career Board translations: hand-off (1.8.2)

Written 14 Oct 2026, after 1.8.1 shipped. It covers Free (`wp-career-board`) and Pro (`wp-career-board-pro`). Same tools, same steps and the same rules as the Eventonomy hand-off, so an agent who has done one has done both.

It follows the Learnomy precedent (`learnomy/plan/2026-09-14-i18n-completion.md`, owner decision 2026-09-14):
- an AI pass translates the bulk;
- a native speaker reviews only the money strings (credits, payment, refund, price, balance, checkout, receipt, coupon), because a wrong refund string costs a support ticket;
- the gates keep it from drifting afterwards.

**Scope: five locales, the ones Career Board already ships.**

| Agent | Locale | Language | Register |
|---|---|---|---|
| 1 | `de_DE` | German | informal (du) |
| 2 | `fr_FR` | French | informal (tu) |
| 3 | `es_ES` | Spanish (Spain) | informal (tú) |
| 4 | `nl_NL` | Dutch | informal (je) |
| 5 | `ko_KR` | Korean | polite (해요체) |

**Done means:**
- `--strict` passes in **both** plugins: `php -n bin/i18n-check.php --strict` in Free, and `php -n ../wp-career-board/bin/i18n-check.php --strict` in Pro.
- Every locale has 0 empty, 0 fuzzy and 0 broken entries.
- The browser spot-check in step 8 is clean for all five.

**The code side is done.** Every user-facing string is in the two POT files, including the front-end Interactivity stores, admin JavaScript, the block editor, Editor.js's own menus, emails, CSV and PDF exports and the PWA manifest. Both POTs are checked against the code on every CI run, they carry no near-duplicate strings (`bin/i18n-dupes.php`), and the tools in `wp-career-board/bin/` refuse any translation that would break on the site. What's left is to translate the catalogues, compile them and commit the language files.

Paths are relative to the WordPress root, `~/Local Sites/career-board/app/public`. Use `php -n`, because without it this machine's PHP prints extension warnings. WP-CLI and gettext (`msgfmt`, `msgattrib`) are installed.

---

## Lead: step 0 (before any agent starts)

1. **Know the branch flow.** `master` is release-only. Each agent branches off `origin/1.8.2` as `i18n/<locale>` and opens one PR per plugin **into `1.8.2`**. Only the lead merges.

2. **Name the money strings before the AI pass.** This is Learnomy's rule: the reviewed set must be known up front, or nobody knows what to hand the reviewer. Here it is every entry whose English matches the money keywords, about 16 in Free and 78 in Pro per locale:

   ```bash
   msggrep -K -i -E -e 'credit|refund|payment|paid|pay |price|charge|balance|purchase|checkout|invoice|receipt|coupon|tax|billing|fee' languages/<domain>.pot
   ```

   Agents translate these like any other string; the lead extracts them for review afterwards (see "Lead: finish").

3. **Re-measure, so the numbers below are current:**

   ```bash
   cd wp-content/plugins/wp-career-board      && php -n bin/i18n-check.php
   cd ../wp-career-board-pro                  && php -n ../wp-career-board/bin/i18n-check.php
   ```

## Where each catalogue stands (14 Oct 2026, POTs regenerated from the 1.8.2 code)

These numbers come from `bin/i18n-check.php`, which counts against the POT, meaning the strings the code actually emits. Don't use `msgfmt --statistics`: it only counts entries already inside a `.po`, which is why the July catalogues looked 100% done while 1.8.1 showed English on every new screen.

| Plugin | Strings | Done | Fuzzy | Empty | Left per locale | Batches (200 each) |
|---|---:|---:|---:|---:|---:|---:|
| Free `wp-career-board` | 1,846 | 1,186 | 32 | 628 | **660** | 4 |
| Pro `wp-career-board-pro` | 1,110 | 668 | 34 | 408 | **442** | 3 |

Exceptions: `es_ES` Free has 5 fewer done (an off-by-one run in the old Spanish catalogue was cleared, see below), and `ko_KR` has 15 (Free) and 10 (Pro) extra fuzzy entries, old translations that dropped a protected term. About 1,100 entries per locale, 5,500 for the five.

**Fuzzy entries are work, the same as empty ones.**
- A fuzzy entry is a starting point, never shipped: the compile step leaves it out.
- In your batch file it appears as `fuzzy_guess`. Check it and write the real translation in `msgstr`.
- Most fuzzy entries here are an old translation of a twin wording ("Save Changes" became "Save changes"), so the guess is usually right apart from letter case.

**Fixed before hand-off, so you don't trip over them:**
- The old Spanish Free catalogue had six entries in a row each holding the translation of the next string ("Career Board" was "Requiere Pro"). Those are now empty and on your list.
- 75 near-duplicate strings (56 Free, 19 Pro) were merged into one wording each. Don't re-create them (rule 12 below).

## Agent rules

- **One agent per locale.** The agent owns that locale's `.po` in both plugins. Two agents writing the same `.po` would overwrite each other's work.
- **Order:** Free first (4 batches), then Pro (3 batches).
- **Commit after each plugin,** not at the very end, so finished work is never lost.
- **Fewer than five agents?** Give one agent two locales one after the other, never at the same time.
- **Never switch the site language yourself.** The site is shared, so only the lead switches it, once at the end (step 8).

## Files

| File | What it is | Who touches it |
|---|---|---|
| `languages/<domain>-<locale>.po` | The source file. WordPress never loads it; everything else is built from it. | Only `bin/i18n-apply.php` changes it. Never hand-edit it. |
| `.mo` | PHP strings, older format | Built by `make-translations.sh` |
| `.l10n.php` | PHP strings, the format WordPress 6.5+ loads first | Built by `make-translations.sh` |
| `<domain>-<locale>-<md5>.json` | JavaScript strings (block editor sidebars, Pro field builder) | Built by `make-translations.sh` |
| `languages/i18n-config.json` | Protected terms, glossary, locales and register | The lead |

You translate in **batch JSON files**, never in the `.po` itself.

## Steps, per agent

### 1. Branch off 1.8.2 (both plugins; example de_DE)

```bash
cd wp-content/plugins/wp-career-board      && git fetch && git checkout -b i18n/de_DE origin/1.8.2
cd ../wp-career-board-pro                  && git fetch && git checkout -b i18n/de_DE origin/1.8.2
```

Only your locale's files change on your branch, so the five PRs never conflict.

### 2. Export your batches (example: de_DE)

```bash
cd wp-content/plugins/wp-career-board
php -n bin/i18n-todo.php --out=$HOME/Documents/work-artifacts/scratch/i18n/wp-career-board --locale=de_DE
cd ../wp-career-board-pro
php -n ../wp-career-board/bin/i18n-todo.php --out=$HOME/Documents/work-artifacts/scratch/i18n/wp-career-board-pro --locale=de_DE
```

This writes `wp-career-board-de_DE-01.json` … `-04.json` and `wp-career-board-pro-de_DE-01.json` … `-03.json`. Keep the batches outside the plugin folders.

Each entry looks like this:

```json
{
  "key": "Jobs in view: %s",
  "msgid": "Jobs in view: %s",
  "note": "translators: %s: number of jobs, already localised. Heading of the results list beside the map. Label form, so one string fits every count.",
  "where": "blocks/job-map/render.php:99",
  "msgstr": ""
}
```

What the fields mean:
- `note`: the developer's comment for translators. Always read it.
- `where`: the source file the string comes from. Open it when the meaning is unclear: is it a button, a heading or an email?
- `context`: the same English used with a different meaning. Translate for that meaning.
- `msgid_plural`: marks a plural entry. `msgstr` is then an array: form 0 for one item, form 1 for several. de, es, nl use 2 forms; `fr_FR` uses 2 with form 0 also for zero; `ko_KR` uses 1 form.
- `fuzzy_guess`: a starting point only (see above).
- `same_as`: the reviewed translation of the **identical** English elsewhere in the product (this catalogue, or Free's when you are in Pro). Use it as is, unless the `note` or `context` shows a different meaning. This keeps Free and Pro worded the same and saves you translating it twice.

### 3. Translate: fill every `msgstr`

**Enforced by the apply script.** If any entry breaks one of these, the script refuses the whole batch:
1. **printf placeholders stay exactly.** That covers `%s`, `%d`, `%1$s`, `%2$s` and `%.2f`. You may move them, but never drop, add or renumber one.
2. **`{tokens}` stay exactly**, for example `{job_title}` and `{new_balance}`. These are used in emails. Never translate the word inside the braces.
3. **`%TOKENS%` stay exactly**, for example `%TITLE%` and `%JOBS%`. JavaScript fills them in.
4. **HTML tags and attributes stay exactly.** Translate only the text between them.
5. **Protected terms stay in English.** The list is in `languages/i18n-config.json`: WP Career Board, Career Board, Pro, WordPress, BuddyPress, WooCommerce, Stripe, PayPal, Google, LinkedIn, REST, API, URL, CSV, PDF, PWA, ATS and the others there. "AI" is **not** protected: use your language's usual form (IA in French and Spanish, KI in German).
6. **Plural entries need every form,** and every form keeps every placeholder.

**Up to you.** The script can't check these:

7. **Use the register** from the table at the top, the same as the strings already translated. German: when you use "du", change the verb too ("Verwende", not "Verwenden du"). Learnomy's German pass got this wrong 500 times.
8. **Reuse the terminology already in use.** List it before you start:
   ```bash
   msgattrib --translated --no-fuzzy languages/wp-career-board-de_DE.po | grep -A1 -E '^msgid "(Job|Jobs|Application|Applications|Candidate|Employer|Company|Credits|Resume|Job alerts|Featured|Board)"$'
   ```
   The glossary in `i18n-config.json` defines each core term.
9. **Keep short labels short.** Buttons, tabs, chips and status words sit in tight space on a phone.
10. **Write for the site owner, the employer and the candidate.** Use plain words, and don't translate word for word.
11. **Follow your language's typography.** French puts a non-breaking space before `: ! ? ;`, German uses „…" quotes, Korean needs no trailing period on short labels.
12. **One wording per meaning.** The English has exactly one string per meaning now; keep your translations just as consistent. The same English always gets the same translation.
13. **Don't copy the English,** unless the whole string is a protected term. A copied string counts as done and shows English on the site forever.
14. **Unsure of the meaning?** Open the `where` file. If you're still unsure, leave `msgstr` empty and list the key in your report. Never guess.

### 4. Apply each batch (dry run first)

```bash
cd wp-content/plugins/wp-career-board
php -n bin/i18n-apply.php --json=$HOME/Documents/work-artifacts/scratch/i18n/wp-career-board/wp-career-board-de_DE-01.json            # dry run
php -n bin/i18n-apply.php --json=$HOME/Documents/work-artifacts/scratch/i18n/wp-career-board/wp-career-board-de_DE-01.json --apply    # write
```

For Pro, run the same from `wp-career-board-pro` with `php -n ../wp-career-board/bin/i18n-apply.php …`.

How to read the output:
- **`REFUSED`**: nothing was written. Fix the entry in the JSON and run it again.
- **`already reviewed (left alone)`**: the script never overwrites reviewed work. If you think an existing translation is wrong, report it instead.
- **`left blank`**: you left those entries empty. They stay on the to-do list.
- **`not in the catalogue`**: the POT has changed since you exported. Re-export with step 2.

### 5. Compile (only after applying)

```bash
cd wp-content/plugins/wp-career-board      && bash bin/make-translations.sh
cd ../wp-career-board-pro                  && bash ../wp-career-board/bin/make-translations.sh .
```

This step:
- merges each `.po` with the current POT;
- builds the `.mo`, `.l10n.php` and JSON files from reviewed entries only;
- fails if a JSON file points at a script that doesn't exist.

Note: it compiles **every** locale, so other locales' files may change in your working tree. Never commit them; discard them with `git checkout -- languages/` after you commit your own.

### 6. Check

```bash
cd wp-content/plugins/wp-career-board      && php -n bin/i18n-check.php
cd ../wp-career-board-pro                  && php -n ../wp-career-board/bin/i18n-check.php
```

- Any `BROKEN` line fails the check.
- When your locale shows 0 fuzzy and 0 empty in both plugins, you're done.
- `--strict` only passes once all five agents have finished.

### 7. Commit your locale's files only, push, open the PR

```bash
cd wp-content/plugins/wp-career-board
git add languages/wp-career-board-de_DE*
git status --short                       # only your locale staged
git commit -m "Translations: de_DE"
git push -u origin i18n/de_DE
gh pr create --base 1.8.2 --title "Translations: de_DE" --body-file <your report, see below>
```

Do the same in `wp-career-board-pro` with `languages/wp-career-board-pro-de_DE*`.

- **Push Free as soon as it's done,** then add Pro to its own PR. Finished work is never left unpushed.
- **If `1.8.2` moves and the POT changes:** rebase on `origin/1.8.2`, re-export (step 2), then apply and compile again. The apply step is safe to repeat.
- **No attribution anywhere:** not in commits, not in PR bodies. No Co-Authored-By, no session links, no "Generated with".
- **Never merge your own PR.** The lead merges after the money-string review.
- **Never bypass the pre-push hook.** Never set `SKIP_LOCAL_CI`.

## Report back (each agent, in the PR body)

- Locale and batches applied, plus the final `i18n-check` line for your locale in both plugins.
- Keys you left empty on purpose, each with the reason.
- Existing translations you think are wrong.
- Terminology decisions other locales might care about.

---

## Lead: finish

### 8. Browser spot-check (lead only, one locale at a time)

The site language is shared, so check the locales one after another, not in parallel. `de_DE` core is already installed; install the rest first:

```bash
cd ~/Local\ Sites/career-board/app/public
wp language core install fr_FR es_ES nl_NL ko_KR
wp site switch-language de_DE      # then fr_FR, es_ES, nl_NL, ko_KR
```

Use the Playwright MCP. Check as admin (`?autologin=1`), as an employer (`?autologin=ahmed.karim`) and as a candidate (`?autologin=sarah.chen`), at 1440 and at 390. These must show translated text, with no raw `%s`, no `%TOKEN%`, no `{token}`, no English sentences and no labels that overflow:
- `/find-jobs/`, a single job page and its apply form
- `/employer-dashboard/`: Overview, My jobs, Applications, Credits
- `/post-a-job/`, including the description editor's "+" menu (Editor.js)
- `/candidate-dashboard/`: Overview, Applications, Saved, Resumes, Job alerts
- `/job-map/` ("Jobs in view: …"), a resume page and its PDF download
- wp-admin: Jobs, Applications, Settings (every tab), Emails, Credits, the block editor sidebar of one Free and one Pro block
- one email in Mailpit (submit an application)

When you're done, run `wp site switch-language en_US`.

### Money-string review (per locale, on the agent's PR branch)

```bash
cd wp-content/plugins/wp-career-board-pro && git checkout i18n/de_DE
msgattrib --translated --no-fuzzy languages/wp-career-board-pro-de_DE.po \
 | msggrep -K -i -E -e 'credit|refund|payment|paid|pay |price|charge|balance|purchase|checkout|invoice|receipt|coupon|tax|billing|fee' \
 > ~/Documents/work-artifacts/scratch/i18n/review-pro-de_DE.po
```

Same for Free with its own `.po`. Then:
1. A native reviewer corrects the extracted file in Poedit.
2. Merge the corrections back with `msgcat --use-first review.po languages/<domain>-de_DE.po -o languages/<domain>-de_DE.po`.
3. Run `make-translations.sh` and `i18n-check.php`.
4. Push the result to the same PR.

This is the one place a `.po` is changed without `i18n-apply.php`, so the check in step 6 is what keeps it safe. Merge the PR once the review is in.

### Checklist

- [ ] `--strict` passes in Free and in Pro.
- [ ] `make-translations.sh` was run last in both plugins, and `git status` is clean afterwards.
- [ ] The step 8 spot-check is clean for all five locales, and the site is back on en_US.
- [ ] Local CI is green in both plugins (`i18n-pot-fresh`, `i18n-near-duplicates`, `i18n-catalogues`).
- [ ] Every agent's "left empty" and "looks wrong" lists are resolved.
- [ ] Money strings reviewed by a native speaker for each locale.
- [ ] Make `--strict` a release gate in both `bin/build-release.sh` files so the bar cannot slip back. Do this as a separate dev change, not in the translation PRs.
- [ ] readme: at the next release, add one honest line naming the five languages. Never claim full coverage before `--strict` is green; Learnomy had to retract exactly that claim.

### Known issues outside translation scope (code, tracked in Basecamp Bugs)

Don't bounce a translation PR over these; they need code changes:
- Notification bell rows, credit ledger notes and the default hiring-pipeline stage names are stored in the language of whoever triggered them, so a reader in another language sees that language.
- Guest job-alert emails and admin emails go out in the site language, not the recipient's.
- AI-written job descriptions, cover letters and match reasons come back in English.
- WP-CLI output is English (WordPress convention).

## Tools reference

These are the Free paths. Pro uses `../wp-career-board/bin/…` from its own root.

| Command | What it does |
|---|---|
| `php -n bin/i18n-todo.php --out=<dir> [--locale=xx_XX] [--batch=200]` | Exports empty and fuzzy entries as batch JSON, with `same_as` reuse hints |
| `php -n bin/i18n-apply.php --json=<batch> [--apply]` | Validates a batch, then writes it to the `.po` |
| `php -n bin/i18n-check.php [--strict] [--json]` | Prints a table per locale; fails on broken entries, and with `--strict` on anything below 100% |
| `php -n bin/i18n-dupes.php` | Fails on near-duplicate English strings in the POT (developers) |
| `bash bin/make-translations.sh [plugin-dir]` | Merges each `.po` with the POT, then compiles from reviewed entries |
| `bash bin/make-pot.sh [plugin-dir] [--check]` | Regenerates the POT, or checks it matches the code (developers only) |
| `languages/i18n-config.json` | Protected terms, glossary, locales and register (one file per plugin) |
