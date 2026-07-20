# Content Filtering

New in 1.7.0 - job listings are now filtered server-side based on
member blocking, so a blocked member's content never reaches the
people who blocked them (or the people they blocked).

## What it does

When a member blocks another member (see
[Moderation](./03-moderation.md#members-blocking-members)), Career
Board hides that member's job listings from the other side of the
block, everywhere a job can be seen:

- The Find Jobs / job listings archive and blocks.
- The single job page, both the pretty-permalink URL and the REST
  response - a blocked employer's job 404s instead of loading.
- The mobile REST API, using the same filtering logic as the website.

The filtering is **mutual**: it doesn't matter which side did the
blocking. If either member blocked the other, neither one sees the
other's job listings.

## Where it runs

This is a server-side filter, not something hidden with CSS in the
browser. The job listings query and the single-job lookup both exclude
blocked authors before the results are ever sent to the browser or the
REST client, so there's no way to see a blocked member's listings by
disabling JavaScript, calling the REST API directly, or using the
mobile app.

## Configuration

There is no settings screen for this - it is automatic and always on
wherever member blocking exists. It runs entirely off each member's
own block list, so there's nothing for the site owner to turn on,
tune, or configure. If a site owner wants to remove the effect of a
block (for example, to review a listing during a dispute), that's done
by unblocking from the member's account, or by an admin editing user
meta directly - there is no admin override toggle.

## Related

- [Moderation](./03-moderation.md) - reporting and blocking members,
  and suspending candidate accounts.
- [Reported Jobs](./03-moderation.md#reported-jobs-flagged) - the
  separate flow for reporting a specific job listing (as opposed to a
  member).
