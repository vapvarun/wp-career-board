# Page-builder Embeds (Elementor, Divi, Bricks, Beaver Builder)

Every WP Career Board block has a matching shortcode, and every
shortcode now accepts the same attributes the block does. So if you
build pages with Elementor, Divi, Bricks, Beaver Builder, or the
classic editor, you can scope blocks the same way you would in the
block editor.

## Shortcode reference

| Block | Shortcode |
|---|---|
| Job Listings | `[wcb_job_listings]` |
| Job Form (multi-step) | `[wcb_job_form]` |
| Job Form (single-page) | `[wcb_job_form_simple]` |
| Job Search | `[wcb_job_search]` |
| Job Single | `[wcb_job_single]` |
| Job Filters | `[wcb_job_filters]` |
| Company Archive | `[wcb_company_archive]` |
| Company Profile | `[wcb_company_profile]` |
| Candidate Dashboard | `[wcb_candidate_dashboard]` |
| Employer Dashboard | `[wcb_employer_dashboard]` |
| Employer Registration | `[wcb_employer_registration]` |
| Modular Widgets | `[wcb_widget id="..."]` |

## Attribute passthrough

All shortcode attributes match the block attribute name (camelCase
becomes lowercase-with-no-separator in some cases). For example:

```
[wcb_job_listings perPage="6" boardId="42" layout="list"]
```

Renders the same thing as the Job Listings block with `perPage=6`,
`boardId=42`, `layout=list`.

## Common scoping patterns

### Show a board-scoped listing

```
[wcb_job_listings boardId="42" perPage="10"]
```

Only jobs assigned to board `42`. Used on board-specific landing
pages or partner pages.

### Filter by custom meta

If you've registered a custom meta key via the
[`wcb_jobs_allowed_meta_filters`](../admin-guide/11-rest-meta-filters.md)
filter, you can scope a listing by that meta:

```
[wcb_job_listings metaFilter="industry:fintech" perPage="6"]
```

### Embed a form on a marketing page

```
[wcb_job_form_simple redirectUrl="/thanks/"]
```

After successful submission, the employer is redirected to `/thanks/`
instead of the new job's permalink.

### Render a single widget from the application screen

The new modular widget system on the [Application Editor](../admin-guide/13-application-editor.md)
exposes its widgets as shortcodes too, so you can embed e.g. an
applicant card on a partner profile page:

```
[wcb_widget id="applicant_card" application_id="987"]
```

## Tips for page-builder users

- **Elementor** — use the **Shortcode** widget, not "HTML". The HTML
  widget escapes shortcodes.
- **Divi** — drop a **Code** module and paste the shortcode. The
  Visual Builder renders the live block.
- **Bricks** — use the **Shortcode** element under Basic.
- **Beaver Builder** — use the **HTML** module; Beaver Builder runs
  shortcodes through `do_shortcode()` automatically.
- **Classic editor** — paste the shortcode anywhere. Works in posts,
  pages, custom post types, widgets.

All blocks render identically across these surfaces — same CSS,
same Interactivity API behavior, same REST data flow.
