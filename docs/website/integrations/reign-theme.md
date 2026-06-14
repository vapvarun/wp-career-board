# Reign Theme Integration

WP Career Board includes built-in support for the **Reign** theme by Wbcom Designs. When Reign is active, the job board uses Reign-tuned page templates, adds its links to Reign's navigation, exposes a Customizer color control, and inherits Reign's accent color.

## What You Get

- A Reign-compatible single job template for `wcb_job` posts
- A Reign-compatible archive template for the jobs post-type archive
- A Customizer color control under a dedicated "WP Career Board" section
- WP Career Board links added to Reign's navigation (Browse Jobs, plus role-aware Employer Dashboard and My Applications)
- An accent-color bridge that maps Reign's accent color onto WP Career Board's primary color
- A compatibility stylesheet (`reign-compat.css`) loaded on every WP Career Board page

## Requirements

- Reign theme active (template slug `reign-theme`)
- WP Career Board 1.4.3

The integration is selected by the active theme's template slug and boots automatically on `after_setup_theme`.

## Setup

Activate Reign and WP Career Board. No additional settings are required; the integration activates automatically when Reign is the active theme.

## Reign Customizer Control

With the integration active, **Appearance > Customize** shows a "WP Career Board" section. It contains a single control:

- **Primary Color** - a color picker (default `#4f46e5`) that sets the job board's primary accent color to match your Reign theme.

This is the only Customizer control the Reign integration registers. Other styling is handled by the compatibility stylesheet and the accent-color bridge described below.

## Accent Color Bridge

WP Career Board reads Reign's accent color from the Customizer and injects it as the plugin's `--wcb-primary` color so buttons, links, and highlights match the theme. Reign stores the accent color per color scheme (the active scheme plus `reign_accent_color`), with a fallback to the legacy single-key setting; the integration reads whichever is set. The color is only injected when a WP Career Board stylesheet is actually on the page.

Developers can override or disable the resolved color with the `wcb_theme_primary_color` filter. Return an empty string to turn the override off:

```php
add_filter( 'wcb_theme_primary_color', '__return_empty_string' );
```

## Reign Navigation Links

The integration appends WP Career Board links to Reign's navigation through the `reign_nav_items` filter:

- **Browse Jobs** - always shown; links to your configured Find Jobs page, or `/jobs/` if no page is set
- **Employer Dashboard** - shown only to users who can post jobs; links to the configured Employer Dashboard page
- **My Applications** - shown only to users who can apply to jobs; links to the configured Candidate Dashboard page

The Employer Dashboard and My Applications links are gated by the WP Career Board abilities `wcb/post-jobs` and `wcb/apply-jobs`, so each member sees only the links relevant to their role.

## Compatibility Stylesheet

WP Career Board enqueues `reign-compat.css` (after Reign's main stylesheet) on:

- Any WP Career Board single post (`wcb_job`, `wcb_application`, `wcb_company`, `wcb_resume`)
- Any WP Career Board post-type archive
- Any WP Career Board taxonomy archive (category, job type, tag, location, experience)
- Any page or post whose content embeds a `wp-career-board/*` or `wcb/*` block

The stylesheet is token-driven and follows Reign's dark mode, so WP Career Board components re-color cleanly when Reign's dark mode is active.

## Reign Add-Ons Compatibility

WP Career Board works alongside Reign's add-ons (such as the BuddyPress and LearnDash add-ons). Running those add-ons does not affect the job board. If you run the BuddyPress add-on, WP Career Board's own BuddyPress integration also applies; see [BuddyPress Integration](./buddypress.md).

## Custom CSS

Add overrides to your Reign child theme or via **Appearance > Customize > Additional CSS**. All WP Career Board styles use the `.wcb-*` prefix and `--wcb-*` CSS variables, so re-mapping a single variable restyles every block.
