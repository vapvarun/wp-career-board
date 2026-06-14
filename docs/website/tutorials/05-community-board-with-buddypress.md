# Community Job Board with BuddyPress

If your site already runs a BuddyPress (or BuddyBoss) community, Career
Board integrates so members can post jobs from their group, candidates
can be discovered by their member profile, and applications can flow
through the BuddyPress activity stream. This page walks through the
setup and the design decisions.

## When this combination makes sense

Common patterns:

- **Industry association** - a community of professionals where each
  member's organisation occasionally hires. Jobs posted within
  member-only groups, not on a public board.
- **University alumni network** - alumni hire other alumni; the board
  lives inside the alumni community.
- **Bootcamp / cohort community** - graduates support each other's job
  search; the board is part of the cohort experience, not a public
  service.
- **Vertical industry community** - e.g. a developer Slack-style
  community where a paid plan unlocks the job board area.

If you don't already run BuddyPress, you don't need it. Career Board
works fine standalone.

## Prerequisites

- WordPress 6.9+, PHP 8.1+.
- **BuddyPress** (or BuddyBoss equivalent).
- WP Career Board **Free or Pro**. Free's BuddyPress integration is
  deliberately thin (see below); the community features in this guide -
  per-group boards, the group Jobs tab, member filters, activity
  broadcasts, and the BuddyPress notification bell - are Pro.
- Groups component enabled in **WP Admin → BuddyPress → Components.**
- Activity Streams component enabled if you want activity broadcasts.

### What Free's BuddyPress integration actually does

Free's integration is limited to two things:

- It registers `employer` and `candidate` BuddyPress **member types**
  and keeps them in sync with the Career Board roles.
- It posts a single **activity entry** to the stream when a job is
  published ("[Member] posted a new job: [Title]"). This fires
  automatically and has no on/off setting in Free.

Everything else described below requires Pro.

## Architecture: how Career Board and BuddyPress fit together

Career Board uses **boards** as a high-level container for jobs. A
board can be:

- **Public** - visible to everyone, jobs listed on `/find-jobs/`.
- **Tied to a BP group** (Pro) - only group members see and can post
  to it.
- **Member-only** - anyone logged in can see and post, no group
  required.

The mapping is:

```
BuddyPress Group "Frontend Engineers"
    └── Career Board: "Frontend Jobs" (group-tied board)
            ├── Job: "Senior React at Acme"
            ├── Job: "Frontend Lead at Beta"
            └── Job: "Junior Frontend at Gamma"
```

Members of the "Frontend Engineers" BP group see and can post to the
"Frontend Jobs" board. Non-members don't see those listings.

> **Pro feature.** The board-to-group mapping requires Pro's
> **Multi-Board** module. Free supports a single global board only.

## Step 1 - Install and verify components

1. **Activate Career Board and BuddyPress** in the order: BuddyPress
   first, then Career Board.
2. **BuddyPress → Components** - confirm "Groups" and "Activity
   Streams" are enabled.
3. The integration boots automatically when BuddyPress is active -
   there is no "Settings → Integrations" tab in Free to toggle. If the
   member types or activity entries don't appear, confirm BuddyPress
   loaded before Career Board (deactivate-reactivate Career Board after
   BP is on).

## Step 2 - Map a BP group to a Career Board board (Pro)

For each BP group that should have its own job board:

1. **BP group → Manage → Career Board tab** (the tab appears once
   Pro is on).
2. Click **Create board for this group.** Set:
   - **Board name** - usually "Group Name Jobs."
   - **Slug** - auto-generated from the name; tweak if needed.
   - **Default category** - optional; jobs without an explicit
     category land here.
   - **Posting cost** - per-board credit cost. 0 if free.
   - **Visibility** - Group Members Only, Site Members, Public.
3. Save.

A new board exists, ready to receive postings. Group members navigating
to the group's Jobs tab see the listing.

## Step 3 - Add a Jobs tab to each group

The Career Board integration registers a **Jobs** tab on each
group automatically. To customise:

1. **BP group → Manage → Members → Visibility.**
2. The Jobs tab is visible to all group members by default. If you
   want it visible only to certain member types (e.g. "Verified
   employer"), filter via the standard BP group permissions or use
   the `wcbp_board_visibility` filter for fine-grained control.

To rename the tab (e.g. "Hiring" instead of "Jobs"), edit the BP
group nav label or use the `wcbp_group_jobs_nav_label` filter.

## Step 4 - Member profile and candidate directory (Pro)

The candidate directory, the public candidate profile, and the
"Open to Work" flag are Pro features (Pro's BuddyPress member filters
module surfaces candidates by their Career Board profile). Free
registers the `employer` / `candidate` member types but does not add a
"Current role" / "Open to Work" pair to BP profiles by itself.

In Free you can still use standard BuddyPress XProfile fields for extra
candidate data; Pro's member filters read them when building the
candidate directory.

## Step 5 - Activity broadcasts

In Free, exactly one activity event fires - a "[Member] posted a new
job: [Title]" entry when a job is published. It is always on and has no
setting.

**Pro** adds the configurable broadcast set (job posted, application
sent, hired, etc.) through its BuddyPress activity module. Application-
and hire-side activity should stay off unless your privacy policy
covers it - most job searches are private.

## Step 6 - Notifications via BP (Pro)

The BuddyPress notification **bell** integration is a Pro module
(`notificationsbell`). Once Pro is active it surfaces Career Board
events - new application on your job, application status changed, new
job in your group - in the BP bell **in addition to** the standard
email. Free sends the emails but does not write BP bell notifications.

There is no "Settings → Notifications → Channels" toggle; the bell is
driven by the Pro module rather than a per-channel setting.

## Step 7 - Member directory filters (Pro)

Pro's BuddyPress member filters add **Open to work** and **Hiring**
filter chips to the BuddyPress members directory (`/members/`), scoping
it to candidates who set themselves open to work or to employers. This
is a directory filter, not a permission gate.

There is no "Member Type Gating" settings screen. To restrict who can
post or what a board shows, use the capability system (the Career Board
roles) and the board/permission filters (for example
`wcb_board_options_for_employer`). Member-type-specific logic is wired
through code/filters rather than an admin mapping UI.

## Step 8 - Test the integration end-to-end

The standard test path:

1. Create two test BP user accounts: one "employer," one
   "candidate" / member.
2. Add both to a BP group, then map that group to a Career Board board.
3. As employer: post a job to that board from the group's Jobs tab.
   Confirm it appears on the group's Jobs listing.
4. As candidate: navigate to the group's Jobs tab. See the listing.
   Apply.
5. Back as employer: receive the application notification (email AND
   BP bell).
6. Move through statuses. Candidate receives matching notifications.
7. Verify: a non-member of the group navigating to
   `/find-jobs/?wcb_board=group-frontend` gets "not authorised" (or
   "redirect to login" depending on board visibility setting).

## Common patterns

### Pattern 1: closed community with multiple sub-boards

Run a paid community where membership unlocks job board access. Each
sub-group has its own board.

- **PMPro / MemberPress + BP** for the paywall.
- **Multi-Board (Pro)** for per-group boards.
- **Job posting cost = 0** in credit settings (membership is the
  paywall, not per-post fees).

### Pattern 2: open community, restricted hiring

Anyone can join the community and view jobs. Only verified employers
can post.

- BP open registration.
- Career Board roles: candidate role auto-assigned on registration.
- "Verified employer" role requires manual admin approval (or use the
  `wcb_employer_default_role` filter to set a "pending verification"
  state).

### Pattern 3: alumni network

Members are organised by graduation year. Each year cohort has a
group with its own job board.

- **BP groups** named by year.
- **One board per year group.**
- Some boards public for cross-year networking, others private.

## Troubleshooting

### Jobs tab missing from BP group

1. Career Board is active AND Pro is active. (Pro's license drives
   updates only - it never gates whether the Jobs tab appears.)
2. The group is mapped to a board in **Group Manage → Career Board.**
3. The current user has permission to see the tab (member of the
   group OR the board is set to "Site Members" / "Public").
4. BuddyPress loaded before Career Board so the integration booted (the
   integration is automatic - there is no enable toggle).

### Member sees jobs they shouldn't

Board visibility is set too permissively. Check:

- **Multi-Board → Boards** - the board's "Visibility" field. Should
  be "Group Members Only" for group-tied boards.
- Members listed in the group - make sure the user isn't in the
  group when they shouldn't be.

### BP bell notification not firing on application (Pro)

1. Pro is active (the notification bell is the Pro `notificationsbell`
   module; Free sends email only).
2. The receiving user has BP notifications enabled in their account
   settings.
3. The BP Notifications component is active.
4. The custom component is registered with BuddyPress (Career Board
   notifications only show in the theme bell when registered via the
   `bp_notifications_get_registered_components` filter).

### Activity stream missing the job-posted event

1. The Activity Streams component is enabled in BP.
2. The job actually reached **Published** status - Free's activity entry
   fires on publish (`wcb_job_created` for a published job). A job stuck
   at Pending Review won't post an activity entry yet.
3. For the broader configurable broadcast set (applications, hires),
   confirm Pro is active - those are Pro's activity module, not Free.

## Where to go next

- [../integrations/buddypress.md](../integrations/buddypress.md) - full
  integration reference.
- [04-monetizing-your-board.md](04-monetizing-your-board.md) - if you're
  pairing BP with paid memberships.
- [../pro-features/02-multi-board.md](../pro-features/02-multi-board.md) -
  more on the multi-board feature that powers the group mapping.
