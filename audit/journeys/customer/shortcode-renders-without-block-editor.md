---
id: shortcode-renders-without-block-editor
priority: critical
personas: admin, employer.stripe, wcbp_p5_candidate
requires: mu:autologin
last_verified: 2026-05-12
---

# Block shortcodes render identically when used on a classic-editor / page-builder page

**Why this journey exists:** A meaningful share of customers don't use the block editor — classic editor sites, Elementor / Divi / Bricks pages, sidebar widgets, theme template files. Every WP Career Board block has a `wcb_<block>` shortcode wrapper so those sites get the same UX. The contract: a page whose `post_content` is ONLY a shortcode (no block markup) must render with full styling, interactivity, and identical DOM to a page using the block.

## Steps

1. As admin, create a Page with `post_content` = `[wcb_job_listings]` (no block markup wrapping it). Pick a title and a slug.
2. Navigate to the published page → expect HTTP 200.
3. Confirm `<body class="...">` contains `wcb-page` → proves Path 3 shortcode detection fired (`apply_filters('wcb_search_active_shortcodes', ...)`).
4. Confirm `document.querySelector('.wcb-job-listings')` exists and `document.querySelector('[data-wp-interactive="wcb-job-listings"]')` exists → block render path ran.
5. Confirm at least one `.wcb-job-card` exists → REST query against `wcb_job` succeeded.
6. Confirm the block's stylesheet auto-enqueued → `document.querySelector('style#wp-career-board-job-listings-style-inline-css')` exists.
7. Confirm `getComputedStyle(card).display` is a layout value (`grid` — the card uses a grid layout; `flex` also acceptable) and `padding` is non-zero → block CSS applied, not just rendered DOM.
8. Repeat steps 1-7 with `[wcbp_resume_archive]` (Pro) on a separate Page.
9. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

```bash
# Delete the test pages.
wp post delete <listings-test-page-id> --force
wp post delete <resume-test-page-id> --force
```

## Notes

The wrapper in `core/class-plugin.php::register_shortcodes()` (Free) and
`core/class-pro-plugin.php::register_shortcodes()` (Pro) is intentionally
generic — it builds a JSON of attrs from the shortcode call and forwards
to `do_blocks()`. There is no per-block logic, so if one block works
standalone, all of them work standalone. This journey covers Job Listings
(simplest customer-facing block, REST-driven, interactivity-bound) and
Resume Archive (Pro, taxonomy filters, candidate-data sensitive) as the
representative sample.

Verified 2026-05-12 on `1.1.1` branch HEAD — both blocks rendered with 9
and 5 cards respectively, full styling, no fatal lines, body class
applied.
