# REST meta filters

You can filter the jobs list by job meta values, in the REST API or in the Job Listings block. In the REST API, keys that start with `_wcb_` work with no setup as long as the key is public in REST (registered for jobs with `show_in_rest`); private keys such as the apply email are ignored. Any other key needs one line of PHP.

The endpoint is `GET /wp-json/wcb/v1/jobs`. Add `meta_<key>=<value>` to the query string.

```
GET /wp-json/wcb/v1/jobs?meta__wcb_department=engineering
GET /wp-json/wcb/v1/jobs?meta__wcb_visa_sponsorship=1
```

The key is the full meta key, so `_wcb_department` becomes `meta__wcb_department`. Since 1.8.1 a `meta__wcb_*` filter on a key that is not public in REST is ignored (the request returns the unfiltered list), so nobody can learn private values by watching the result count. To make such a key filterable on purpose, register it with `show_in_rest` or add it to the `wcb_jobs_allowed_meta_filters` filter (below).

## Filter in the Job Listings block or shortcode

Set the block's `metaFilter` attribute to `key:value`, or use the shortcode:

```
[wcb_job_listings metaFilter="_wcb_department:engineering"]
```

If the key does not start with `_wcb_` and is not allowed by the filter below, the block shows all jobs and ignores the filter. With `WP_DEBUG` on, WordPress logs a `_doing_it_wrong` notice that names the fix.

## Allow a custom meta key

Keys that do not start with `_wcb_`, and `_wcb_` keys that are not public in REST, are blocked by default, so nobody can query private meta. Allow a key with the `wcb_jobs_allowed_meta_filters` filter:

```php
add_filter( 'wcb_jobs_allowed_meta_filters', function ( $keys ) {
    $keys[] = 'partner_company_id';
    return $keys;
} );
```

Then this request works:

```
GET /wp-json/wcb/v1/jobs?meta_partner_company_id=42
```

## How matching works

Each filter is an exact match on the stored value. There is no range or comma-list syntax. For salary ranges, use the separate `salary_min` and `salary_max` query parameters.

## Speed

- Filters run as a normal `meta_query`. On a large board, add a database index on the meta key and value columns of `wp_postmeta`.
- The jobs endpoint caches each result for 5 minutes. Saving any job clears the cache.

## See also

- [Custom fields](12-custom-fields.md)
- [Page-builder embeds](../for-employers/11-page-builder-embeds.md)
