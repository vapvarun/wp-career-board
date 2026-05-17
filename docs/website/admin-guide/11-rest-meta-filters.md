# REST Meta Filters

The `GET /wcb/v1/jobs` REST endpoint accepts postmeta filters via
`?meta_<key>=<value>`. The Job Listings block exposes the same
surface through its `metaFilter` attribute.

## Default-allow rule (1.2.0+)

Any meta key in the `_wcb_*` namespace is allowed by default. The
plugin owns that prefix, so there is no probe risk for fields like
`_wcb_visa_sponsorship`, `_wcb_seniority_score`, `_wcb_department`,
etc. Drop the block in the editor or hit the REST endpoint directly
without any PHP setup:

```
GET /wp-json/wcb/v1/jobs?meta__wcb_visa_sponsorship=1
GET /wp-json/wcb/v1/jobs?meta__wcb_department=engineering
```

```
[wcb_job_listings metaFilter="_wcb_visa_sponsorship:1"]
[wcb_job_listings metaFilter="_wcb_department:engineering"]
```

## Custom (non-WCB) meta still needs opt-in

Custom or third-party meta keys — anything that doesn't start with
`_wcb_` — still need to be added to the `wcb_jobs_allowed_meta_filters`
filter before they can be queried. This prevents anonymous probes
against arbitrary site-internal postmeta (e.g. a private membership
flag set by another plugin):

```php
add_filter( 'wcb_jobs_allowed_meta_filters', function( $keys ) {
    $keys[] = 'partner_company_id';       // not _wcb_*, must opt in
    $keys[] = 'crm_sync_state';           // same
    return $keys;
} );
```

Then anonymous callers can hit:

```
GET /wp-json/wcb/v1/jobs?meta_partner_company_id=42
```

## Why this split?

Without any allowlist, any caller could query against any postmeta —
including private fields the plugin or other plugins use for internal
bookkeeping (e.g. `_wcb_employer_banned`, `_wcb_pending_review_token`,
or a membership plugin's `_member_level` field). The pre-1.2.0
behavior required allowlisting every key, which made the common case
(filter jobs by a `_wcb_*` field set by the plugin itself) require
PHP. The 1.2.0 split allows the namespace WCB owns while still gating
foreign meta.

## Block + shortcode integration

The Job Listings block exposes a `metaFilter` attribute on every
shipped surface — Gutenberg inserter, shortcode wrapper, and
page-builder embeds:

![metaFilter attribute in the block inspector](../images/metafilter-block-attr.png)

If you reference a key that's NOT in the `_wcb_*` namespace and NOT
on the explicit allowlist, the block falls back to showing all jobs
(no error, but the filter is silently ignored) and a
`_doing_it_wrong` notice fires in `WP_DEBUG` mode telling you which
filter to register.

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
