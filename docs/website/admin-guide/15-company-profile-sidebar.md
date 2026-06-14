# Company Profile Sidebar

The Company Profile block renders a right-column sidebar on every
company single page (`/companies/<slug>/`). The sidebar always shows
three useful Career Board cards out of the box, so the column is never
wasted. Customisation is done with PHP filters, not the WordPress
widget screen (see [Customising the sidebar](#customising-the-sidebar)).

## What it looks like

On desktop (>1024px) the company profile renders in a two-column grid:

- **Main column** (left): the About, Company Details, and Open
  Positions sections.
- **Sidebar column** (right, 320px): the three Career Board cards
  below.

On mobile (1024px and under) the sidebar stacks below the main
content.

## Default behaviour (no setup required)

The sidebar always renders these three Career Board blocks:

| Block | What it shows |
|---|---|
| **Similar Companies** (`wp-career-board/similar-companies-card`) | Companies in the same industry as the company being viewed. |
| **Recent Jobs** (`wp-career-board/recent-jobs`) | The 5 most recently published jobs across the board. |
| **Job Alerts CTA** (`wp-career-board/job-alert-card`) | A small card linking candidates to the alerts signup flow. |

You don't have to do anything to get these.

> **Note:** earlier builds let admins place widgets in a
> "Company Profile Sidebar" widget area under **Appearance → Widgets**.
> That widget area was retired because generic footer/sidebar widgets
> were routinely misassigned there and rendered incorrectly on the
> company page. The sidebar is now driven entirely by the block and the
> filters below.

## Customising the sidebar {#customising-the-sidebar}

Replace, reorder, or extend the three default cards with the
`wcb_company_sidebar_blocks` filter. Each entry is a Gutenberg
block-comment string passed to `do_blocks()`:

```php
add_filter(
    'wcb_company_sidebar_blocks',
    function ( array $blocks, int $company_id ): array {
        // Append the Job Stats card to the defaults.
        $blocks[] = '<!-- wp:wp-career-board/job-stats /-->';
        return $blocks;
    },
    10,
    2
);
```

Return an empty array to render no cards. To inject arbitrary markup
before or after the cards, use the companion action hooks - both run
inside the `<aside class="wcb-cp-sidebar">` element:

```php
add_action( 'wcb_company_sidebar_before', function ( int $company_id ) { /* echo markup */ } );
add_action( 'wcb_company_sidebar_after',  function ( int $company_id ) { /* echo markup */ } );
```

## Which Career Board blocks fit well in the sidebar

These Career Board blocks are 320px-friendly and work well as sidebar
cards:

- **Similar Companies** (`wp-career-board/similar-companies-card`) - same-industry companies, with optional Company ID for use on non-company pages.
- **Recent Jobs** (`wp-career-board/recent-jobs`) - latest published jobs.
- **Featured Jobs** (`wp-career-board/featured-jobs`) - featured listings grid.
- **Job Alerts CTA** (`wp-career-board/job-alert-card`) - signup nudge card.
- **Job Stats** (`wp-career-board/job-stats`) - aggregate counts.

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

These sidebar blocks also work outside the company-profile context:

- **`wp-career-board/similar-companies-card`** has a **Company ID**
  attribute in the editor inspector. Drop the block on any page and
  set the ID to anchor it to a specific company. Leave blank when used
  inside the Company Profile Sidebar (it auto-resolves there).

- **`wp-career-board/job-alert-card`** has fully editable title, body,
  button text, and URL. Use it on landing pages, footer columns, or
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
