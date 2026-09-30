# BuddyPress integration

You can bring your job board into your BuddyPress community. With BuddyPress active, employers and candidates get their own member types, and new jobs appear in the site-wide activity stream. There is nothing to configure. WP Career Board turns the integration on when it finds BuddyPress.

## Member types

The integration registers two member types, **Employer** and **Candidate**. A user gets the matching type when their role is set to the WP Career Board Employer (`wcb_employer`) or Candidate (`wcb_candidate`) role. Users who only have another role added alongside their existing one do not get a member type.

You can use the member types anywhere BuddyPress supports them, for example member type directories or `bp_get_member_type()` in your templates.

## Job activity

When a job is created already published, a BuddyPress activity item is posted to the site-wide stream. It reads "{name} posted a new job: {job title}", is posted under the job author, and links to the job. Members can comment on it like any other activity.

- The activity component is `wp-career-board` and the type is `wcb_job_posted`.
- The item is added on the `wcb_job_created` action. The activity `item_id` is the job ID.
- A job that is created as Pending and published later does not post an item.
- Submitting an application does not post an item.

There is no setting to turn the job activity off. To hide it, use BuddyPress's own tools for the `wcb_job_posted` type, or delete single items from the activity stream.

## BuddyBoss Platform

The integration checks for the `buddypress()` function and uses standard BuddyPress member type and activity functions. It has no BuddyBoss-specific code.

## Pro

WP Career Board Pro adds more BuddyPress features. See the Pro documentation.
