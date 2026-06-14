# BuddyPress Integration

When BuddyPress is active on your site, WP Career Board automatically connects with it to add your job board into the community experience.

## What You Get

- Two BuddyPress member types, Employer and Candidate, registered for your community
- A BuddyPress activity item posted to the site-wide stream every time an employer publishes a job
- Member types kept in sync automatically when a user is given the WP Career Board employer or candidate role

## Requirements

- BuddyPress active and configured
- WP Career Board 1.4.3

The BuddyPress integration first shipped in WP Career Board 1.0.0 and has been part of every release since.

## Setup

No configuration required. Activate both plugins and WP Career Board detects BuddyPress automatically (it checks for the `buddypress()` function) and boots the integration.

## Activity Stream Integration

When an employer publishes a job, WP Career Board adds a BuddyPress activity item to the site-wide activity stream. The item is posted under the job author, links to the published job, and reads like "{name} posted a new job: {job title}". Members can comment, react, or share the listing through the normal BuddyPress activity tools.

Technical detail for developers:

- The activity item is registered under the `wp-career-board` activity component with the activity type `wcb_job_posted`.
- It is created on the `wcb_job_created` action, only when the job's status is `publish`.
- The activity `item_id` is the job post ID.

Note: only job publishing generates an activity item. Submitting an application does not post to the activity stream.

## Member Types

WP Career Board registers two BuddyPress member types on `bp_init`:

- **Employer** - applied to users with the WP Career Board employer role (`wcb_employer`)
- **Candidate** - applied to users with the WP Career Board candidate role (`wcb_candidate`)

Member types are assigned automatically through the `set_user_role` hook: when a user is set to the `wcb_employer` role they receive the `employer` member type, and the `wcb_candidate` role maps to the `candidate` member type. Because the assignment runs on role changes, the member type is applied the next time a user's role is set (for example through the Setup Wizard sample data, an employer or candidate signup, or an admin role edit).

Once member types are assigned you can use BuddyPress member-type tools and queries (for example member-type directory URLs or `bp_get_member_type()` in your templates) to surface Employers and Candidates separately.

## BuddyBoss Platform

WP Career Board does not ship BuddyBoss-specific code. Because BuddyBoss Platform provides the same `buddypress()` bootstrap and the same member-type and activity functions, the BuddyPress integration above loads and runs on BuddyBoss Platform as well. The member types and the job-posted activity item work the same way. There are no BuddyBoss-only features.

## Disabling Activity Items

WP Career Board does not expose a dedicated on/off setting or filter for the job-posted activity item. If you want BuddyPress active but do not want the job activity in the stream, use BuddyPress's own activity tools to hide the `wcb_job_posted` activity type, for example by removing it from the registered activity actions or by filtering it out of the stream query in your own code. Site administrators can also delete individual activity items from the activity stream.
