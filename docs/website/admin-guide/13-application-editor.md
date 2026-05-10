# Application Editor (re-built)

The Edit Application admin screen has been rebuilt around the
applicant — replacing the previously empty native post-edit screen
with a full review surface that has everything an employer needs in
one place.

## What's on the screen

| Section | What it shows |
|---|---|
| **Applicant card** | Avatar, name, email, phone (if collected), location |
| **Cover letter** | Full text, formatted |
| **Resume preview** | Inline preview with **Open** + **Download** buttons |
| **Status changer** | Submitted / Reviewing / Shortlisted / Rejected — instant save on change |
| **Quick action buttons** | Shortlist / Mark Hired / Reject / Message |
| **Status history** | Full audit trail — who changed status, when, from / to |
| **Custom fields** | Whatever the site has registered via [`wcb_application_form_fields_groups`](12-custom-fields.md) |

## Where to find it

`wp-admin → Career Board → Applications → click any application row`

Or via direct URL: `/wp-admin/post.php?post=<id>&action=edit` (the
plugin redirects native post-edit URLs to the new admin screen).

## Quick actions

The four quick-action buttons (Shortlist / Mark Hired / Reject /
Message) each fire the corresponding workflow:

- **Shortlist** — sets status to `shortlisted`, sends the
  configured shortlist email to the applicant, posts a status-change
  history entry.
- **Mark Hired** — sets status to `hired`, sends hire email, posts
  to BuddyPress activity stream (Pro), and triggers the candidate-
  side "congratulations" notification.
- **Reject** — sets status to `rejected`, sends rejection email
  (templated, customizable), records history.
- **Message** — opens an inline reply composer that uses the same
  `wp_mail()` chokepoint as automated emails. Uses the configured
  email-template merge tags (`{{candidate_name}}`, `{{job_title}}`,
  etc.).

## Status history

Every status change writes a row to the application's status history:

- Who made the change (user ID + display name)
- When (timestamp)
- From → To (status slugs)
- Optional note (employer can add a note when changing status)

The history shows in reverse-chronological order on the application
screen. It's also exposed as `application.status_history` on the REST
endpoint for ATS integrations.

## Modular widget system

Every component on the application screen — applicant card, cover
letter, resume preview, status changer, action buttons, history —
also works as a standalone shortcode you can embed anywhere:

```
[wcb_widget id="applicant_card" application_id="987"]
[wcb_widget id="resume_preview" application_id="987"]
[wcb_widget id="status_history" application_id="987"]
[wcb_widget id="status_changer" application_id="987"]
[wcb_widget id="action_buttons" application_id="987"]
```

This is useful for:

- **Partner profile pages** — embed the applicant card on a partner's
  candidate-facing page
- **Custom admin dashboards** — composite widgets into a different
  arrangement using a dashboard plugin
- **Email templates** — generate a snapshot of the applicant card to
  attach to a forwarded email

The widget shortcodes respect the same capabilities as the admin
screen — embedding `[wcb_widget id="status_changer" application_id="987"]`
on a public page only renders the changer for users with
`wcb_manage_applications` ability.

## Bulk operations

The list table (one level up from the editor) supports bulk
operations:

- **Bulk export to CSV** — see [Bulk CSV Export](../for-employers/09-csv-export.md).
- **Bulk status change** — set multiple applications to the same
  status in one action.
- **Bulk delete** — same as native WP bulk delete on CPTs.

## Permissions

| Capability | What it grants |
|---|---|
| `wcb_manage_applications` | Read the editor screen, run quick actions, change status |
| `edit_post` (per-application) | Standard WP per-post edit gate; required to modify applicant data |
| `wcb_employer` role | Site default — gets `wcb_manage_applications` for applications belonging to their own jobs only |
| `manage_options` | Site admin override — can edit any application |

## See also

- [Review applications](../for-employers/04-review-applications.md) —
  employer-side workflow guide
- [Email notifications](02-email-notifications.md) — configure the
  email templates that quick-actions trigger
- [Custom fields](12-custom-fields.md) — extend the applicant data
  collected by the apply form
