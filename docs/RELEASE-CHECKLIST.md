# WP Career Board — v1.0.0 Release Checklist

## 1. Code Quality Gate

- [ ] WPCS: `mcp__wpcs__wpcs_full_check` → 0 errors
- [ ] PHPStan: `mcp__wpcs__wpcs_phpstan_check` → 0 errors
- [ ] PHP lint: `find . -name "*.php" -not -path "*/vendor/*" -not -path "*/node_modules/*" | xargs php -l` → no errors

## 2. QA Sign-off

- [ ] All rows in `docs/PLAN.md` QA checklist marked ✅
- [ ] Backend REST: auth gates verified (401/403 on unauthenticated writes)
- [ ] Frontend blocks: all 14 blocks render and interact correctly
- [ ] Admin UI: all list tables, forms, and modals function
- [ ] Email notifications: all 6 trigger/recipient combinations verified
- [ ] Security scan: no unescaped output, all queries prepared
- [ ] SEO: JobPosting schema valid
- [ ] GDPR: export + erase verified

## 3. Version Bump

- [ ] `wp-career-board.php` — update `Version:` header and `WCB_VERSION` constant
- [ ] `readme.txt` — update `Stable tag:` and add changelog entry
- [ ] `package.json` — update `"version"` field
- [ ] `docs/CHANGELOG.md` — add v1.0.0 entry with summary of features

## 4. Build

- [ ] `npm run build` — passes with no errors
- [ ] `build/` directory updated with compiled blocks
- [ ] `dist/wp-career-board-1.0.0.zip` generated via Grunt

## 5. Final Commit + Tag

```bash
git add -A
git commit -m "release(wcb): v1.0.0"
git tag v1.0.0
git push origin master --tags
```

- [ ] Commit pushed to `origin/master`
- [ ] Tag `v1.0.0` visible on remote

## 6. Distribution

- [ ] Zip uploaded to EDD product on store.wbcomdesigns.com
- [ ] Changelog published on docs site
- [ ] Release announcement drafted
