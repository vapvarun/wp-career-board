# Community Job Board with BuddyPress

If your site already runs a BuddyPress (or BuddyBoss) community, Career
Board integrates so members can post jobs from their group, candidates
can be discovered by their member profile, and applications can flow
through the BuddyPress activity stream. This page walks through the
setup and the design decisions.

## When this combination makes sense

Common patterns:

- **Industry association** — a community of professionals where each
  member's organisation occasionally hires. Jobs posted within
  member-only groups, not on a public board.
- **University alumni network** — alumni hire other alumni; the board
  lives inside the alumni community.
- **Bootcamp / cohort community** — graduates support each other's job
  search; the board is part of the cohort experience, not a public
  service.
- **Vertical industry community** — e.g. a developer Slack-style
  community where a paid plan unlocks the job board area.

If you don't already run BuddyPress, you don't need it. Career Board
works fine standalone.

## Prerequisites

- WordPress 6.5+, PHP 8.1+.
- **BuddyPress 13+** (or BuddyBoss equivalent).
- WP Career Board **Free or Pro** — most community features work in
  Free; Pro adds the Multi-Board feature that maps boards to BP
  groups.
- Groups component enabled in **WP Admin → BuddyPress → Components.**
- Activity Streams component enabled if you want activity broadcasts.
- Member Types if you want member-type-specific visibility (Pro).

## Architecture: how Career Board and BuddyPress fit together

Career Board uses **boards** as a high-level container for jobs. A
board can be:

- **Public** — visible to everyone, jobs listed on `/find-jobs/`.
- **Tied to a BP group** (Pro) — only group members see and can post
  to it.
- **Member-only** — anyone logged in can see and post, no group
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

## Step 1 — Install and verify components

1. **Activate Career Board and BuddyPress** in the order: BuddyPress
   first, then Career Board.
2. **BuddyPress → Components** — confirm "Groups" and "Activity
   Streams" are enabled.
3. **Career Board → Settings → Integrations** — the BuddyPress
   integration should show as "Active" with a green checkmark. If
   not, deactivate-reactivate Career Board after BP is on.

## Step 2 — Map a BP group to a Career Board board (Pro)

For each BP group that should have its own job board:

1. **BP group → Manage → Career Board tab** (the tab appears once
   Pro is on).
2. Click **Create board for this group.** Set:
   - **Board name** — usually "Group Name Jobs."
   - **Slug** — auto-generated from the name; tweak if needed.
   - **Default category** — optional; jobs without an explicit
     category land here.
   - **Posting cost** — per-board credit cost. 0 if free.
   - **Visibility** — Group Members Only, Site Members, Public.
3. Save.

A new board exists, ready to receive postings. Group members navigating
to the group's Jobs tab see the listing.

## Step 3 — Add a Jobs tab to each group

The Career Board integration registers a **Jobs** tab on each
group automatically. To customise:

1. **BP group → Manage → Members → Visibility.**
2. The Jobs tab is visible to all group members by default. If you
   want it visible only to certain member types (e.g. "Verified
   employer"), filter via the standard BP group permissions or use
   the `wcbp_board_visibility` filter for fine-grained control.

To rename the tab (e.g. "Hiring" instead of "Jobs"), edit the BP
group nav label or use the `wcbp_group_jobs_nav_label` filter.

## Step 4 — Member profile job-history field

Career Board adds a "Current role" + "Open to Work" pair to BP member
profiles automatically when the integration is on. Each candidate's
profile shows:

- Their current role and headline.
- Their "Open to Work" flag (publicly visible if they set it).
- A link to their public candidate profile (`/candidate/slug/`).

To extend with custom fields:

1. **BuddyPress → Profile Fields** — add new fields (e.g. "Years of
   experience," "Preferred work mode").
2. These fields are stored as standard BP XProfile data. Career Board
   reads them via the `wcb_candidate_profile_fields` filter to
   include in candidate searches.

## Step 5 — Activity broadcasts

When a member posts a job or applies for a job, Career Board can
broadcast that to the activity stream. Each event is configurable:

**Career Board → Settings → Integrations → BuddyPress → Activity.**

| Event | Default | What it broadcasts |
|---|---|---|
| Job posted | Off | "[Member] posted a new job: [Title] at [Company]." |
| Application sent | Off (privacy) | "[Member] applied for [Job Title]." |
| Hired | Off (privacy) | "[Member] was hired for [Job Title]." |
| New company joined | On | "[Member] added a company: [Company Name]." |

Application-side activity is off by default — most job searches are
private. Turn on at your discretion. If you do enable, double-check
your privacy policy reflects it.

## Step 6 — Notifications via BP

Career Board integrates with BuddyPress's bell notifications. By
default, these fire as BP notifications **in addition to** standard
email:

- New application on your job → bell notification.
- Application status changed → bell notification.
- New job posted in your group → bell notification (if member of the
  group's board).

To toggle individual events: **Career Board → Settings →
Notifications → Channels** — pick "BP notifications" alongside email.

## Step 7 — Member types and gating (Pro)

If your BP install uses Member Types (e.g. "employer," "candidate,"
"student," "alumni"), Career Board can gate posting capability and
board visibility by member type.

**Career Board → Settings → Permissions → Member Type Gating.**

Example mapping:

- **Member type "Verified employer"** — can post jobs to any board.
- **Member type "Student"** — can apply to jobs marked "Open to students."
- **Member type "Alumni"** — can post AND apply, full access.
- **Default** — applies to anyone not in a member type.

This requires Pro and a one-time custom mapping configuration.

## Step 8 — Test the integration end-to-end

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

1. Career Board is active AND Pro is active AND license is valid.
2. The group is mapped to a board in **Group Manage → Career Board.**
3. The current user has permission to see the tab (member of the
   group OR the board is set to "Site Members" / "Public").
4. The integration is enabled in **Settings → Integrations →
   BuddyPress.**

### Member sees jobs they shouldn't

Board visibility is set too permissively. Check:

- **Multi-Board → Boards** — the board's "Visibility" field. Should
  be "Group Members Only" for group-tied boards.
- Members listed in the group — make sure the user isn't in the
  group when they shouldn't be.

### BP notification not firing on application

1. **Settings → Notifications → Channels** has "BP notifications" on.
2. The receiving user has BP notifications enabled in their account
   settings.
3. The BP notification component is active.
4. Test with `wp cron event list` — Career Board fires notifications
   through standard hooks, BP picks them up immediately.

### Activity stream missing the job-posted event

1. Activity broadcasts for "Job posted" is on in
   **Settings → Integrations → BuddyPress → Activity.**
2. The Activity Streams component is enabled in BP.
3. The job was posted from within a BP group (not from the global
   Post a Job page) — the activity event only fires when the post
   originates from a group context.

## Where to go next

- [../integrations/buddypress.md](../integrations/buddypress.md) — full
  integration reference.
- [04-monetizing-your-board.md](04-monetizing-your-board.md) — if you're
  pairing BP with paid memberships.
- [../pro-features/02-multi-board.md](../pro-features/02-multi-board.md) —
  more on the multi-board feature that powers the group mapping.
