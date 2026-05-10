# REST Meta Filters

The `GET /wcb/v1/jobs` REST endpoint accepts arbitrary postmeta
filters via `?meta_<key>=<value>` — but only for keys that the site
admin has explicitly allowlisted via the `wcb_jobs_allowed_meta_filters`
filter. This prevents anonymous probes against arbitrary postmeta.

## How it works

By default, no meta keys are filterable. To enable filtering on a
custom meta key, register it via the filter:

```php
add_filter( 'wcb_jobs_allowed_meta_filters', function( $keys ) {
    $keys[] = '_wcb_visa_sponsorship';   // employer-set custom flag
    $keys[] = '_wcb_seniority_score';    // numeric meta
    return $keys;
} );
```

Then anonymous callers can hit:

```
GET /wp-json/wcb/v1/jobs?meta__wcb_visa_sponsorship=1
GET /wp-json/wcb/v1/jobs?meta__wcb_seniority_score=8
```

## Why allowlist?

Without an allowlist, any caller could query against any postmeta —
including private fields the plugin uses for internal bookkeeping
(e.g. `_wcb_employer_banned`, `_wcb_pending_review_token`). The
allowlist gates the surface to fields the site has explicitly
declared safe for public filtering.

## Block + shortcode integration

The Job Listings block exposes a `metaFilter` attribute that uses
this REST surface:

```
[wcb_job_listings metaFilter="_wcb_visa_sponsorship:1"]
```

The block validates that the key is on the allowlist before passing
it to the query. If you reference a key that's NOT allowlisted, the
block falls back to showing all jobs (no error, but the filter is
silently ignored).

## Common patterns

### Boolean meta

```php
$keys[] = '_wcb_visa_sponsorship';
$keys[] = '_wcb_relocation_offered';
$keys[] = '_wcb_remote_friendly';
```

Then on the front end:

```
?meta__wcb_visa_sponsorship=1
?meta__wcb_relocation_offered=1
```

### Range meta (numeric)

For range queries, register both bounds:

```php
$keys[] = '_wcb_team_size';
```

Then use comparison operators in the URL (the endpoint accepts
`_min` / `_max` suffix conventions for any allowlisted numeric key):

```
?meta__wcb_team_size_min=10
?meta__wcb_team_size_max=50
```

### Slug-list meta

For meta storing serialized arrays of slugs:

```php
$keys[] = '_wcb_skills_required';
```

```
?meta__wcb_skills_required=python,docker
```

The endpoint matches if any of the comma-separated values appear in
the meta array.

## Performance notes

- All allowlisted meta filters are added to the WP_Query `meta_query`
  array. WordPress core handles indexing.
- For high-traffic boards, add an index on the relevant rows in
  `wp_postmeta`:
  ```sql
  ALTER TABLE wp_postmeta ADD INDEX wcb_meta_visa (meta_key, meta_value(20));
  ```
- The REST endpoint caches per-user-locale + per-query-hash for 5
  minutes by default; use `wcb_cache_ttl` filter to change.

## See also

- [Custom fields filter API](12-custom-fields.md) — declare custom
  fields on the job form so employers can fill them when posting.
- [Page-builder embeds](../for-employers/11-page-builder-embeds.md) —
  scope listings via `metaFilter` shortcode attribute.
