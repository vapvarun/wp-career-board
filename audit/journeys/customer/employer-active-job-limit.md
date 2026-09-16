---
id: employer-active-job-limit
area: customer
priority: high
personas: employer.figma
requires: mu:autologin, filter-fixture
last_verified: 2026-07-27
bug_ref: 10134733032
---

# Employer active-job limit caps free posting and yields to credits

## Why this exists

Sites running a free board need a way to cap how many listings one employer
keeps live at once. The cap is opt-in (`wcb_employer_active_job_limit` returns
0 = unlimited by default), so the whole feature is inert until a site filters
it — which means a regression here is silent on most installs and only shows up
on the sites that depend on it.

Two contracts are easy to break and are the real reason this journey exists:

1. **Credits outrank the quota.** When `wcb_credits_enabled` is true the cap is
   skipped entirely. Paid posting already meters volume; charging an employer a
   credit and then refusing the post would be the worst of both models. If a
   refactor ever lets both run, employers on credit-enabled sites start hitting
   a quota they already paid past.
2. **Republish excludes the job being republished.** Reopening an expired or
   closed listing counts the employer's *other* live jobs only. Counting the job
   against itself makes the final slot permanently unusable — an employer at the
   cap could never reopen anything, even to swap one listing for another.

## Fixture

```php
// mu-plugin
add_filter( 'wcb_employer_active_job_limit', static fn(): int => 5 );
```

Note the live site has Pro credits enabled, so the cap is skipped by design.
To exercise the quota, also force credits off in the fixture:

```php
add_filter( 'wcb_credits_enabled', '__return_false', 99 );
```

## Steps

1. With the fixture active and credits OFF, confirm the employer holds **more
   than 5** published `wcb_job` posts:
   `wp eval 'echo ( new WP_Query( array( "post_type"=>"wcb_job","post_status"=>"publish","author"=><uid>,"posts_per_page"=>1,"fields"=>"ids" ) ) )->found_posts;'`
2. `POST /wcb/v1/jobs` as that employer → expect **403**, code
   `wcb_active_job_limit`, with `data.limit` = 5 and `data.count` = the real
   number. The `message` must name both numbers and tell the employer what to
   do (close or expire a listing).
3. Close listings until the employer is under the cap, then `POST /wcb/v1/jobs`
   again → expect **201**.
4. Bring the employer back up to the cap, then `PUT /wcb/v1/jobs/{id}` with
   `status=publish` on a closed/expired job → expect **403**
   `wcb_active_job_limit`.
5. Close one other listing so the employer is under the cap of *other* live
   jobs, then republish that same job again → expect **200**. A 403 here means
   the job is being counted against itself (contract 2 above).
6. Flip `wcb_credits_enabled` to true and `POST /wcb/v1/jobs` while still over
   the former cap → expect **201**. Any 403 here means contract 1 broke.
7. Remove the fixture entirely and post again → expect **201**; the default
   limit of 0 must leave posting unlimited.
8. tail `debug.log` → zero new fatals or warnings.

## Notes

- Counted with `posts_per_page => 1` + `found_posts`, i.e. one COUNT query. If
  a refactor switches this to `posts_per_page => -1` or `count( get_posts() )`,
  an agency account with thousands of listings pays for a full hydrate on every
  job submission. See the big-site checklist item 4.
- `wcb_employer_active_job_statuses` decides what occupies a slot; the default
  `['publish']` deliberately leaves pending, draft, expired and closed jobs
  free. A site that wants pending posts to count adds them there.
- The job form needs no special handling — `blocks/job-form/view.js` already
  surfaces `err.message` from any REST error, so the filtered copy reaches the
  employer as-is.
