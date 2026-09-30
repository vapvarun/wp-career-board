# Community job board with BuddyPress

You can run your job board inside a BuddyPress or BuddyBoss community. With Free, members are tagged as Employers or Candidates, and the activity stream announces new jobs. Pro adds group boards, profile tabs, notification-bell alerts and member directory filters.

If you do not run BuddyPress, you do not need it. Career Board works on its own.

## What you need

- WordPress 6.9 or newer on PHP 8.1 or newer.
- BuddyPress, or BuddyBoss Platform.
- WP Career Board. Pro is only needed for the extra community features listed below.

The integration switches on by itself when BuddyPress is active. There is no setting to turn it on.

## What Free does

- **Member types.** Career Board registers two BuddyPress member types, Employer and Candidate. A member gets the matching type when their role is set to Employer or Candidate, for example when a new account registers. Use the types in member directories and BuddyPress queries.
- **Activity entries for new jobs.** When an employer submits a job and it publishes straight away, the site-wide activity stream gets an entry, "{name} posted a new job: {job title}". The Activity Streams component must be enabled under **Settings > BuddyPress > Components**.

Free does not post an activity entry when a job is approved later, and it does not post one for applications or hires. There is no setting to switch the job entry off.

## Set it up

1. Activate BuddyPress and Career Board.
2. Under **Settings > BuddyPress > Components**, make sure **Activity Streams** is enabled if you want the job entries.
3. Add the Career Board pages to your menu, as described in [Your first day as a site owner](01-first-day-as-site-owner.md).

## Test the integration

1. Register a test employer and a test candidate. See [Your first day as a site owner](01-first-day-as-site-owner.md).
2. Open the **Members** directory and check that each account has the right member type.
3. Turn on **Auto-Publish Jobs** under **Career Board > Settings > Jobs**, post a job as the employer, and check the activity stream for the new entry.

## What Pro adds

Pro connects the board to more parts of your community. It can give each BuddyPress group its own job board, add **My Jobs** and **My Career** tabs to member profiles, alert members in the notification bell, add Open to work and Hiring filters to the members directory, let group admins moderate their own group's jobs, and price job posts by member type or membership. For setup, use the Pro docs pages under **BuddyPress integration**.

## Where to go next

- [BuddyPress integration reference](../integrations/03-buddypress.md) - the Free integration in detail.
- [Monetizing your board](04-monetizing-your-board.md) - charging for job posts.
- [Multi-board](../pro-features/02-multi-board.md) - more on Pro boards.
