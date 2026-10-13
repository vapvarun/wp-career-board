---
id: rest-meta-filter-ignores-private-keys
priority: high
personas: anonymous
requires: seed:jobs
last_verified: 2026-10-13
needs: cli
bug_ref: 10355318941
---

# `meta__wcb_*` filters on the public jobs route only work for keys already public in REST

**Why this journey exists:** `GET /wcb/v1/jobs?meta__wcb_<key>=<value>` default-allowed every `_wcb_` key, so an anonymous caller could ask yes/no questions about private values (is this the apply email? is the flag status open?) by watching the result count change. Since 1.8.1 a key must be registered for `wcb_job` with `show_in_rest`; others are ignored.

## Steps

1. Clear caches and record the baseline: `wp transient delete --all`; `curl -s "$WCB_SITE/wp-json/wcb/v1/jobs?per_page=100" | jq .total` → `<T>`.
2. Private key: `wp post meta update <job-id> _wcb_apply_email leak@example.test`; `curl -s "$WCB_SITE/wp-json/wcb/v1/jobs?per_page=100&meta__wcb_apply_email=leak%40example.test" | jq .total` → exactly `<T>` (filter ignored: NOT 1, NOT 0). Repeat with a wrong value → still `<T>`. The two answers are identical, so nothing can be inferred.
3. Same for the flag status: `wp post meta update <job-id> _wcb_flag_status open`; `…&meta__wcb_flag_status=open` → `<T>`. An unregistered key `…&meta__wcb_not_a_key=1` → `<T>`.
4. Public key still filters: `…&meta__wcb_remote=1` → total equals `wp post list --post_type=wcb_job --post_status=publish --meta_key=_wcb_remote --meta_value=1 --format=count`; `…&meta__wcb_featured=1` likewise.
5. Opt-in path unchanged: add an mu-plugin `add_filter( 'wcb_jobs_allowed_meta_filters', fn( $k ) => array_merge( $k, array( 'partner_company_id' ) ) );`, then `…&meta_partner_company_id=42` narrows to jobs with that meta. Core route `GET /wp-json/wp/v2/wcb_job` does not expose `_wcb_apply_email` at all (registered with `show_in_rest` false).
6. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

```bash
wp post meta delete <job-id> _wcb_apply_email
wp post meta delete <job-id> _wcb_flag_status
rm -f wp-content/mu-plugins/zz-allow-meta.php
```

## Notes

- The job-listings block's `metaFilter` attribute is a separate path (`blocks/job-listings/render.php`) and still accepts any `_wcb_` key for the site owner who configured the block; this journey is about the public REST query string only.
- The jobs endpoint caches each filter combination for 5 minutes - delete transients between steps.
