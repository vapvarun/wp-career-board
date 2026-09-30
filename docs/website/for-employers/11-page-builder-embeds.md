# Page-builder embeds

You can place any WP Career Board block in Elementor, Divi, Bricks, Beaver Builder or the classic editor with a shortcode. Each shortcode takes the same attributes as its block, so you can scope it the same way you would in the block editor.

## Shortcode reference

Every WP Career Board (Free) block has a shortcode.

| Block | Shortcode |
|---|---|
| Job Listings | `[wcb_job_listings]` |
| Job Search Hero | `[wcb_job_search_hero]` |
| Job Form | `[wcb_job_form]` |
| Job Form (Single-Page) | `[wcb_job_form_simple]` |
| Job Search | `[wcb_job_search]` |
| Job Single | `[wcb_job_single]` |
| Job Filters | `[wcb_job_filters]` |
| Featured Jobs | `[wcb_featured_jobs]` |
| Recent Jobs | `[wcb_recent_jobs]` |
| Job Stats | `[wcb_job_stats]` |
| Company Archive | `[wcb_company_archive]` |
| Company Profile | `[wcb_company_profile]` |
| Candidate Dashboard | `[wcb_candidate_dashboard]` |
| Employer Dashboard | `[wcb_employer_dashboard]` |
| Employer Registration | `[wcb_employer_registration]` |
| Similar Companies | `[wcb_similar_companies]` |
| Job Alerts CTA | `[wcb_job_alert_card]` |
| Application widgets | `[wcb_widget id="..."]` |

`[wcb_registration]` also works as an older name for `[wcb_employer_registration]`. Use `[wcb_employer_registration]` on new pages.

## Pass attributes

Use the block's attribute names. Shortcode attributes are lowercased by WordPress, and the plugin maps them back to the block's names. For example:

```
[wcb_job_listings perPage="6" boardId="42" layout="list"]
```

This gives the same result as the Job Listings block with `perPage` 6, `boardId` 42 and `layout` list.

## Common patterns

### Show one board's jobs

```
[wcb_job_listings boardId="42" perPage="10"]
```

This lists only jobs on board `42`. Use it on a board-specific landing page.

### Filter by custom meta

Register the meta key with the [`wcb_jobs_allowed_meta_filters`](../admin-guide/11-rest-meta-filters.md) filter first. Then scope a listing by it:

```
[wcb_job_listings metaFilter="industry:fintech" perPage="6"]
```

### Embed a compact job form

```
[wcb_job_form_simple compact="true"]
```

See [Quick Job Form](./08-quick-job-form.md) for the settings.

### Show one application widget

The widgets on the [Application Editor](../admin-guide/13-application-editor.md) also work as shortcodes. Widget IDs start with `application/`. For example, to show an applicant card:

```
[wcb_widget id="application/applicant-card" application_id="987"]
```

## Where to paste the shortcode

- **Elementor** - use the **Shortcode** widget.
- **Divi** - use a **Code** module.
- **Bricks** - use the **Shortcode** element.
- **Beaver Builder** - use the **HTML** module.
- **Classic editor** - paste it into the content.
