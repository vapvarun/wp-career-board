# Featured Listing Expiry

You can feature a job so it lists first, and it stops being featured on its own after a set number of days. The job stays published. Only the Featured boost ends.

## Set the duration

Go to **Career Board → Settings → Jobs** and set **Featured Duration (days)**. The default is 30 and the range is 1 to 365. There is no setting for permanent Featured status. To keep a job featured longer, set a larger number or feature it again after it ends.

The length is counted from the day the job was marked Featured, using the value in Settings at the time of the daily check.

## Feature a job

- **As an administrator or moderator:** tick **Featured listing** on the job's edit screen.
- **As an employer, when Pro prices it:** tick the Feature checkbox on the job form, or use the **Feature** button on the job in **My Jobs**. If the featuring charge cannot be paid, the job is still posted and the employer is told why.

## Where featured jobs list

Featured jobs list first when the job order is **Featured, then newest**. That is the default order, and it applies to every page of results including "Load more". A keyword search shows the best matches first, and the **Closing soonest**, **Highest salary** and **Oldest first** orders do not put featured jobs first.

## When the boost ends

A daily check ends the Featured status of every job whose featured period is over. Developers can react to it with the `wcb_job_featured_expired` action, which receives the job ID. Pro uses it to email the employer.

The check runs on WordPress's scheduled tasks, which fire when someone visits the site. On a low-traffic site, run a real cron job that requests `wp-cron.php` to keep the timing accurate.

## With Pro

Pro lets the same job pay for Featured status more than once. After it ends, the employer can spend credits to feature the job again. Read **Featured upgrade** in the Pro documentation.
