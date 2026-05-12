# Company Profile Sidebar

WP Career Board 1.1.1 adds a right-column sidebar to every company
single page (`/companies/<slug>/`). Site admins customise it like any
other WordPress widget area; if left empty, three useful default
blocks render automatically so the column is never wasted.

## What it looks like

On desktop (>1024px) the company profile renders in a two-column grid:

- **Main column** (left): the existing About, Company Details, and
  Open Positions sections.
- **Sidebar column** (right, 320px): the widget area named
  **"Company Profile Sidebar"**.

On mobile (≤1024px) the sidebar stacks below the main content.

## Default behaviour (no admin setup required)

When the **Company Profile Sidebar** widget area is empty, the page
auto-renders three Career Board blocks:

| Block | What it shows |
|---|---|
| **Similar Companies** (`wcb/similar-companies-card`) | Top 5 companies in the same industry as the company being viewed. |
| **Recent Jobs** (`wcb/recent-jobs`) | The 5 most recently published jobs across the board. |
| **Job Alerts CTA** (`wcb/job-alert-card`) | A small card linking candidates to the alerts signup flow. |

You don't have to do anything to get these. They're the soft fallback.

## Adding your own widgets

Override the defaults by adding any blocks to the widget area:

1. Go to **Appearance → Widgets**.
2. Find the **Company Profile Sidebar** area.
3. Drop in any block: a Career Board block, a built-in WordPress
   block (Image, Heading, Custom HTML, etc.), or any third-party
   block.
4. Save.

As soon as the area contains **one or more** widgets, the defaults
are replaced. If you later remove all widgets, the defaults come
back automatically.

## Which Career Board blocks fit well in the sidebar

The Career Board sidebar blocks (all 320px-friendly):

- **Similar Companies** (`wcb/similar-companies-card`) - same-industry companies, with optional Company ID for use on non-company pages.
- **Recent Jobs** (`wcb/recent-jobs`) - latest published jobs.
- **Featured Jobs** (`wcb/featured-jobs`) - featured listings grid.
- **Job Alerts CTA** (`wcb/job-alert-card`) - signup nudge card.
- **Job Stats** (`wcb/job-stats`) - aggregate counts.

All ship as shortcodes too if you're using a page builder:

- `[wcb_similar_companies count="5"]`
- `[wcb_recent_jobs count="5"]`
- `[wcb_featured_jobs perPage="3"]`
- `[wcb_job_alert_card]`
- `[wcb_job_stats]`

## Suppression of the theme's own sidebar

On company singles, Career Board hides the active theme's primary
sidebar (`#secondary`, `.widget-area`, `aside.sidebar`, etc.) and
forces the parent content column to full width. This is intentional:
themes often pre-populate their sidebars with **Archives**,
**Categories**, **Recent Posts** widgets that don't make sense on a
company page. The company-profile block takes over that space and
renders its own sidebar instead.

If you want the theme's primary sidebar to remain visible on company
pages too, you can filter it back in with a tiny mu-plugin:

```php
add_action(
    'wp_enqueue_scripts',
    function () {
        if ( is_singular( 'wcb_company' ) ) {
            wp_add_inline_style(
                'wp-career-board-company-profile-style',
                '.wcb-company-page #secondary { display: block !important; }'
            );
        }
    },
    20
);
```

This is rarely needed - the in-block sidebar is the cleaner UX -
but it's available as an override.

## Standalone use on other pages

The two new blocks also work outside the company-profile context:

- **`wcb/similar-companies-card`** has a **Company ID** attribute
  in the editor inspector. Drop the block on any page and set the
  ID to anchor it to a specific company. Leave blank when used
  inside the Company Profile Sidebar (it auto-resolves there).

- **`wcb/job-alert-card`** has fully editable title, body, button
  text, and URL. Use it on landing pages, footer columns, or
  wherever you want a "Get Job Alerts" CTA.

## Theme compatibility

Tested across:

- **BuddyX Pro** (the company-page CSS overrides BuddyX's
  `grid-template-columns: 978px 260px` to single-column on company
  singles so our content fills the freed space).
- **Reign**.
- **Astra**, **Kadence**, **GeneratePress** (collapse via
  `.ast-container`, `.container-grid` selector overrides).
- **Twenty Twenty-Three / Four / Five** (block themes work
  out of the box).

If you find a theme where the layout looks wrong, file an issue
with the active theme name plus a screenshot - the per-theme CSS
shim is a one-line addition.

## Where to go next

- [../tutorials/02-employer-end-to-end.md](../tutorials/02-employer-end-to-end.md) - how employers use the company profile.
- [../for-candidates/02-finding-jobs.md](../for-candidates/02-finding-jobs.md) - candidate-side experience the sidebar is designed to help.
