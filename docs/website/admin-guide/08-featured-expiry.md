# Featured Listing Expiry

Featured listings now expire automatically after a configurable
duration. The previous behavior - "once Featured, always Featured" - made it hard to sell Featured as a paid SKU. Auto-expiry sets up
Featured as a real time-bound boost.

## How it works

When a job is marked Featured (manually by an admin, or via the
[Featured-upgrade credit consumer](https://docs.wbcomdesigns.com/docs/wp-career-board-pro/credit-system/04-featured-upgrade/)
in Pro), the plugin records the expiry timestamp on the job.

A daily cron event (`wcb_expire_featured_jobs`) runs every 24 hours,
finds jobs whose featured-expiry timestamp is in the past, and
clears the `_wcb_featured` flag on each. The job stays published - only its Featured boost ends.

## Configuration

Navigate to **Career Board → Settings → Job Listings**, find the
**Featured Duration (days)** field. Set the number of days a
Featured boost lasts after activation.

Default: **30 days**. The value is clamped to the 1-365 range, so the
minimum boost is one day - there is no `0` setting for permanent
Featured status. If you need a Featured listing to persist longer,
set a larger value (up to 365) or re-feature the job after it
expires.

## Cron schedule

The cleanup cron runs daily, registered as `wcb_expire_featured_jobs`.
WordPress's wp-cron triggers it on the next page load after the
scheduled time - for low-traffic sites, install a real cron job that
hits `wp-cron.php` to keep timing accurate.

If you ever need to manually trigger expiry:

```bash
wp cron event run wcb_expire_featured_jobs
```

## Per-job override

Site admins can extend a specific job's Featured duration via the
job's edit screen, **Featured** meta box. Changing the value updates
the per-job expiry without affecting other jobs.

## With Pro: pay-to-renew

Pro's [Featured-upgrade credit consumer](https://docs.wbcomdesigns.com/docs/wp-career-board-pro/credit-system/04-featured-upgrade/)
lets the same job pay for Featured status more than once over its
life. After auto-expiry, the employer can spend more credits to
re-feature.

## What stays the same

- Already-featured jobs at upgrade time get a default expiry of
  `now + 30 days` (or whatever the site default is at upgrade
  time). They're not back-dated.
- The `wcb_featured` taxonomy / query parameter still works
  identically - only the `_wcb_featured` postmeta gets cleared on
  expiry.
- Templates that filter on Featured (`is_featured` block attribute,
  REST `?featured=true`) automatically respect expired status with
  no template changes.
