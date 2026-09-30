# Company profile sidebar

You can show extra cards next to a company's profile, and change or remove the default ones with a filter. Every company page at `/companies/<slug>/` has a sidebar on the right.

## Default cards

The sidebar shows two cards with no setup:

| Card | What it shows |
|---|---|
| Similar Companies (`wp-career-board/similar-companies-card`) | Companies in the same industry as the company being viewed |
| Job Alerts (`wp-career-board/job-alert-card`) | A card that links candidates to job alerts |

On screens wider than 1024px the sidebar sits in a 320px column to the right of the About, Company Details and Open Positions sections. On smaller screens it stacks below them.

## Change the cards

Use the `wcb_company_sidebar_blocks` filter. Each entry is a block comment string. The filter receives the list and the company ID.

```php
add_filter(
    'wcb_company_sidebar_blocks',
    function ( array $blocks, int $company_id ): array {
        $blocks[] = '<!-- wp:wp-career-board/job-stats /-->';
        return $blocks;
    },
    10,
    2
);
```

Return an empty array to show no cards.

To add your own markup, use these actions. Both receive the company ID and run inside the sidebar element:

```php
add_action( 'wcb_company_sidebar_before', function ( int $company_id ) { /* echo markup */ } );
add_action( 'wcb_company_sidebar_after',  function ( int $company_id ) { /* echo markup */ } );
```

## Blocks that fit the sidebar

| Block | Shortcode |
|---|---|
| Similar Companies (`wp-career-board/similar-companies-card`) | `[wcb_similar_companies count="5"]` |
| Recent Jobs (`wp-career-board/recent-jobs`) | `[wcb_recent_jobs count="5"]` |
| Featured Jobs (`wp-career-board/featured-jobs`) | `[wcb_featured_jobs perPage="3"]` |
| Job Alerts (`wp-career-board/job-alert-card`) | `[wcb_job_alert_card]` |
| Job Stats (`wp-career-board/job-stats`) | `[wcb_job_stats]` |

Recent Jobs lists the newest jobs across the whole board, not only this company's jobs.

## Use the cards on other pages

- Similar Companies has a **Company ID** setting. Set it to show similar companies for a specific company on any page. Leave it empty inside the company sidebar.
- Job Alerts lets you edit the title, text, button text and URL. Use it on landing pages or in a footer.

## Your theme's sidebar

On company pages, Career Board hides the theme's own sidebar so the company sidebar takes its place.
