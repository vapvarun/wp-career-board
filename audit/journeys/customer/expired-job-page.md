---
id: expired-job-page
priority: high
personas: anonymous, employer.stripe, admin
requires: mu:autologin, seed:jobs
last_verified: 2026-09-27
needs: cli
---

# An ended job keeps its page; employers reopen or close it

**Why this journey exists:** owner decision D5 (1.8.0): at its deadline a job stops taking applications and leaves listings, feeds and the sitemap, but its URL stays a 200 "expired" page instead of a 404, so links people shared keep working. D15: when the employer closes a job early, its undecided applications close and each candidate is told once; automatic expiry leaves applications alone.

## Steps

1. With "When a job ends" active (new sites; older sites click **End jobs at their deadline** under Settings > Jobs), set a published job's deadline to yesterday and run `wp cron event run wcb_check_job_expiry` → expect the job's status `wcb_expired`.
2. As anonymous, open the job's `/jobs/{slug}/` URL (get it with `get_permalink()`: it must not be a `?post_type=` link) → expect HTTP 200, the notice "This job has expired and is no longer taking applications.", no apply button, `<meta name="robots" content="... noindex, follow">`, no `JobPosting` JSON-LD, and "Open roles you might like" listing open jobs.
3. The job no longer appears on `/find-jobs/`, in the RSS feed or in `wp-sitemap-posts-wcb_job-1.xml`.
4. As the employer, open the dashboard's My Jobs → the job shows **Expired** with **Reopen**. Click Reopen → it goes live with a deadline in the future (not yesterday).
5. On a job with two open applications (one submitted, one shortlisted) and one hired, click **Close** in My Jobs and confirm → the dialog says undecided applicants are told; within a minute (WP-Cron) the two open applications read **Position closed** for the candidate and **Closed** for the employer, the hired one is unchanged, and Mailpit shows one email per affected candidate.
6. Let a different job expire with open applications → their statuses do not change and no candidate email is sent.
7. tail debug.log diff → expect ZERO new fatal / warning lines.

## Teardown

Delete the test jobs and applications; if you switched expiry on only for this walk, restore the site's previous setting.

## Notes

- Automated coverage: `tests/test-job-lifecycle.php` (21 assertions, including the backlog rule: a job that ended more than 7 days ago is moved without emailing its employer).
- The sweep runs hourly; a job past its deadline shows "Applications closed" and takes no applications even before the sweep moves it.
