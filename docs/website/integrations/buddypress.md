# BuddyPress Integration

When BuddyPress is active on your site, WP Career Board automatically connects with it to enhance the community experience around your job board.

## What You Get

- Job board activity appears in BuddyPress activity streams
- Employer and candidate roles map to BuddyPress member types
- "Post a Job" and "Apply" activity updates can appear in community feeds
- Candidate profiles link to their BuddyPress member profile

## Requirements

- BuddyPress (any recent version) active and configured
- WP Career Board 0.1.0 or higher

## Setup

No configuration required. Activate both plugins and the integration detects BuddyPress automatically.

Check **WP Career Board → Settings → System Status** to confirm BuddyPress is detected and the integration is active.

## Activity Stream Integration

When a job is published, a BuddyPress activity item can appear in the site-wide activity stream, showing the company name and a link to the job. Candidates can comment, react, or share the listing through BuddyPress activity features.

## Member Types

WP Career Board registers two BuddyPress member types:

- **Employer** — users with the WP Career Board employer role
- **Candidate** — users with the WP Career Board candidate role

This lets you filter members in the BuddyPress member directory by their job board role.

## BuddyBoss Platform

BuddyBoss Platform is fully compatible. The same integration applies — member types, activity streams, and profile links all work the same way in BuddyBoss.

## Disabling the Integration

If you want BuddyPress active but don't want job board activity in the activity stream, you can disable specific activity types from **BuddyPress → Settings → Activity** or filter them via the `wcb_bp_activity_enabled` filter hook.
