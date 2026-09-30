# Quick job form (single-page)

You can let employers post a job from one screen, with no wizard steps. Place the form on any page, in a sidebar or modal, or in a page builder.

It is a one-screen alternative to the 4-step form in [Post a Job](./02-post-a-job.md). Both forms use the same `wcb_job_form_fields` filter, so custom fields you add appear on both.

The person posting must be logged in as a user with the **Employer** role. Anyone else sees a sign-in or registration message in place of the form.

## Add the form

### With the block

In the WordPress editor, add the **Job Form (Single-Page)** block. It has these settings:

| Setting | Type | Default | What it does |
|---|---|---|---|
| `boardId` | integer | `0` | Uses a specific board. `0` uses the default board |
| `compact` | boolean | `false` | Narrows the form for sidebars and modals |

### With a shortcode

In a page builder or the classic editor, use the shortcode. The attribute names match the block settings.

```
[wcb_job_form_simple]
[wcb_job_form_simple boardId="42"]
[wcb_job_form_simple compact="true"]
```

For Elementor, Divi, Bricks and Beaver Builder, see [Page-builder embeds](./11-page-builder-embeds.md).

## Fields

| Field | Required | Note |
|---|---|---|
| Post to Board | No | Shown only when the site has more than one board |
| Job Title | Yes | |
| Job Description | Yes | Rich text |
| Category | No | |
| Job Type | No | Pick from the lists your site offers |
| Location | No | A location, or **Other (enter manually)** |
| Experience | No | Pick from the lists your site offers |
| Skills / Tags | No | Comma-separated |
| Salary Range | No | Currency, minimum, maximum and period (Year, Month or Hour) |
| Remote-friendly position | No | Tick for a remote job |
| Application Deadline | No | Filled in for you from the board's listing length. You cannot edit it |
| Apply URL | No | External apply link. It must start with `http://` or `https://` |
| Apply Email | No | An address candidates can email to apply |
| Custom fields | Depends | Whatever your `wcb_job_form_fields` filter adds. See [Custom fields](../admin-guide/12-custom-fields.md) |

When the site sells featured placement (Pro), a checkbox lets the employer feature the job.

## What happens on submit

The form posts to `POST /wcb/v1/jobs`, the same route as the multi-step form.

- When the site moderates jobs, the job goes to Pending. Otherwise it is published.
- The form has no preview step. The employer can edit the job afterwards from **My Jobs**.
- Editing always uses the 4-step form in the Employer Dashboard.
- Company logos and banners are not part of this form. Employers set them in the company profile.
