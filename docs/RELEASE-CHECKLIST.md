# WP Career Board — Release Checklist

> Last shipped: **v1.1.0 — 2026-04-27**. Use this checklist for every
> point release: replace the version string in §3 / §4 / §5, then walk
> through every section. Local CI gate (`bin/ci-local.sh` /
> `npm run ci`) runs §1 in one command.

## 1. Code Quality Gate

- [ ] `bash bin/ci-local.sh` → 0 errors (PHP lint + WPCS + PHPStan + size-limit)
- [ ] WPCS: `mcp__wpcs__wpcs_full_check` → 0 errors
- [ ] PHPStan: `mcp__wpcs__wpcs_phpstan_check` → 0 errors
- [ ] PHP lint: every `*.php` file outside `vendor/` and `node_modules/` parses

## 2. QA Sign-off

- [ ] All rows in `docs/PLAN.md` QA checklist marked ✅
- [ ] Backend REST: auth gates verified (401/403 on unauthenticated writes)
- [ ] Frontend blocks: every block renders and interacts (current count in `docs/ARCHITECTURE.md`)
- [ ] Admin UI: list tables, forms, modals, application detail (six widgets) render
- [ ] Email notifications: every trigger/recipient combination verified
- [ ] Security scan: no unescaped output, every query is prepared
- [ ] SEO: JobPosting schema valid
- [ ] GDPR: export + erase verified
- [ ] Mobile: pages verified at 390px viewport
- [ ] Theme parity: render at least one job-form / dashboard / archive page in Reign + BuddyX Pro, light + dark mode

## 3. Version Bump

- [ ] `wp-career-board.php` — `Version:` header and `WCB_VERSION` constant
- [ ] `readme.txt` — `Stable tag:` plus a customer-friendly changelog block
- [ ] `package.json` — `"version"` field
- [ ] `docs/CHANGELOG.md` — technical changelog entry with hooks added
- [ ] `docs/HOOKS.md` and `docs/SHORTCODES.md` — fold in any new extension points

## 4. Build

- [ ] `npm run build` — passes with no errors
- [ ] `build/` directory updated with compiled blocks
- [ ] `dist/wp-career-board-<version>.zip` generated via Grunt

## 5. Final Commit + Tag

```bash
git add -A
git commit -m "release(wcb): v<version>"
git tag v<version>
git push origin <branch> --tags
```

- [ ] Commit pushed to `origin/<branch>`
- [ ] Tag visible on remote

## 6. Distribution

- [ ] Zip uploaded to EDD product on wbcomdesigns.com
- [ ] Changelog published on docs site
- [ ] Bundled with Reign + BuddyX Pro release notes if their next theme cut is imminent
- [ ] Release announcement drafted
