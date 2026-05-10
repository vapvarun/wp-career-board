# Quick Job Form (single-page)

The Quick Job Form is a **one-screen** alternative to the multi-step
job-form wizard. Drop it into any sidebar, modal, partner page, or
page builder. Captures the core fields an employer needs to publish
in one submit — no tab switching, no wizard navigation.

Sibling of the full [Post a Job](02-post-a-job.md) form (the 4-step
wizard) — both share the same custom-field hook and persistence
layer, so anything you add via `wcb_job_form_fields` appears on
both surfaces.

## When to use it

- **Embed in a sidebar widget** so partners can post a job from any
  page on your site without navigating to the dashboard.
- **Drop into a modal** triggered from a "Post a job" CTA on a
  marketing page.
- **Embed on a partner / co-marketing site** — the Block + shortcode
  both forward the REST namespace, so cross-site posts work as long
  as the partner has a logged-in employer session.
- **Onboarding flows** where you want to capture the first job inline
  during account creation, not after.

## How to add it

### As a block

In the WordPress editor, add the **Job Form (Single-Page)** block.
Available attributes:

| Attribute | Type | Default | What it does |
|---|---|---|---|
| `redirectUrl` | URL | `''` | Where to send the employer after a successful post (defaults to the new job's permalink) |
| `boardId` | int | `0` | Lock the form to a specific board (useful for multi-board sites) |
| `categoryDefault` | string slug | `''` | Pre-select a job category |

### As a shortcode

For page builders or classic editor:

```
[wcb_job_form_simple]
[wcb_job_form_simple boardId="42"]
[wcb_job_form_simple redirectUrl="/thanks/"]
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
| Job Title | Yes | |
| Job Description | Yes | Block editor / rich text |
| Location | No | Free text or taxonomy term |
| Job Type | No | Full-time / Part-time / Contract / Freelance / Internship |
| Salary range | No | min + max, currency, period (yearly / monthly / hourly) |
| Application deadline | No | Date picker |
| Custom fields | Optional | Whatever your `wcb_job_form_fields` filter contributes |

Fields specific to your industry can be added via the
[Custom fields filter](../admin-guide/12-custom-fields.md) — they'll
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
  this form — those still happen via the company profile editor.
