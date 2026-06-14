# Quick Job Form (single-page)

The Quick Job Form is a **one-screen** alternative to the multi-step
job-form wizard. Drop it into any sidebar, modal, partner page, or
page builder. Captures the core fields an employer needs to publish
in one submit - no tab switching, no wizard navigation.

Sibling of the full [Post a Job](02-post-a-job.md) form (the 4-step
wizard) - both share the same custom-field hook and persistence
layer, so anything you add via `wcb_job_form_fields` appears on
both surfaces.

## When to use it

- **Embed in a sidebar widget** so partners can post a job from any
  page on your site without navigating to the dashboard.
- **Drop into a modal** triggered from a "Post a job" CTA on a
  marketing page.
- **Embed on a partner page** on your own site so a logged-in
  employer can post without first navigating to the dashboard.
- **Onboarding flows** where you want to capture the first job inline
  during account creation, not after.

The submitter must be logged in as a user with the **Employer** role
(the same gate as the dashboard wizard).

## How to add it

### As a block

In the WordPress editor, add the **Job Form (Single-Page)** block.
Available attributes:

| Attribute | Type | Default | What it does |
|---|---|---|---|
| `boardId` | integer | `0` | Lock the form to a specific board (useful for multi-board sites) |
| `showCompanyField` | boolean | `true` | Show or hide the company-name field |
| `compact` | boolean | `false` | Tighter vertical rhythm for narrow embed contexts (sidebars, modals) |

### As a shortcode

For page builders or classic editor:

```
[wcb_job_form_simple]
[wcb_job_form_simple boardId="42"]
[wcb_job_form_simple showCompanyField="false" compact="true"]
```

Every Block attribute forwards to the shortcode using the same name.
Page builders (Elementor, Divi, Bricks, Beaver Builder, classic
editor) all support attribute passthrough.

### As an Elementor / Divi / Bricks / Beaver Builder embed

Use the shortcode widget in your page-builder of choice. See the
[Page-builder embeds](11-page-builder-embeds.md) guide for the full
attribute reference shared by every shortcode.

## Fields captured

| Field | Required | Note |
|---|---|---|
| Board | No | Shown only on multi-board sites (or hidden when `boardId` locks the form) |
| Company | No | Toggle with `showCompanyField` |
| Job Title | Yes | |
| Job Description | Yes | Rich text |
| Category | No | Job category select |
| Job Type | No | Full-time / Part-time / Contract / Freelance / Internship |
| Location | No | Free text |
| Experience | No | Entry / Mid / Senior / Lead / Executive |
| Skills / Tags | No | Comma-separated tags |
| Salary range | No | min + max, currency, period (yearly / monthly / hourly) |
| Application deadline | No | Date picker |
| Apply URL | No | External apply link (must start with `http://` or `https://`) |
| Apply Email | No | Address candidates can email to apply |
| Custom fields | Optional | Whatever your `wcb_job_form_fields` filter contributes |

Fields specific to your industry can be added via the
[Custom fields filter](../admin-guide/12-custom-fields.md) - they'll
appear on this form AND the multi-step form automatically.

## What happens on submit

The form posts to the same REST endpoint as the multi-step form
(`POST /wcb/v1/jobs`), so:

- Moderation rules apply identically (jobs land in `pending` if
  moderation is enabled).
- Featured-status, board assignments, and credit deduction flow
  through the standard pipeline.
- BuddyPress activity-stream entries fire on approval (Pro).
- Email notifications fire to whoever the site config dictates
  (admin moderator / employer-confirmation / etc.).

## Limitations

- Single-screen UX means there's no preview step before publish.
  Employers can still edit immediately afterward via the dashboard.
- Multi-image uploads (company logos, banner art) are not part of
  this form - those still happen via the company profile editor.
