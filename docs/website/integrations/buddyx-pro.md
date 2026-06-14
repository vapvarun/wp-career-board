# BuddyX and BuddyX Pro Integration

WP Career Board includes built-in support for the **BuddyX** and **BuddyX Pro** themes by Wbcom Designs. The same integration covers both themes, because their content width and layout tokens are identical. When either theme is active, the job board adopts the theme's accent color and uses BuddyX-tuned page templates for job listings without any extra configuration.

## What You Get

- A BuddyX-compatible single job template for `wcb_job` posts
- A BuddyX-compatible archive template for the jobs post-type archive
- An accent-color bridge: WP Career Board re-maps its `--wcb-primary` color to your BuddyX accent color, so buttons, links, and highlights match the theme palette
- A compatibility stylesheet (`buddyx-compat.css`) loaded on every WP Career Board page so the plugin's blocks sit cleanly inside BuddyX layouts
- An #OpenToWork badge on candidate member profiles (see below)

## Requirements

- BuddyX or BuddyX Pro theme active
- WP Career Board 1.4.3

The integration is selected by the active theme's template slug: it loads when the slug is `buddyx` or `buddyx-pro`.

## Setup

No configuration required. Activate BuddyX or BuddyX Pro along with WP Career Board and the integration boots automatically on `after_setup_theme`.

## Accent Color Bridge

WP Career Board reads the BuddyX accent color from the Customizer and injects it as the plugin's primary color, so WP Career Board components inherit your theme palette in light and dark mode. BuddyX Pro uses scheme-scoped color keys (the active color scheme plus `buddyx_accent_color`) and BuddyX Free uses a flat `buddyx_primary_color` key; the integration reads whichever applies. The resolved color is only injected when a WP Career Board stylesheet is actually on the page.

Developers can override or disable the resolved color with the `wcb_theme_primary_color` filter. Return an empty string to turn the override off entirely:

```php
add_filter( 'wcb_theme_primary_color', '__return_empty_string' );
```

## #OpenToWork Badge

On BuddyX Pro member profiles, WP Career Board adds an "#OpenToWork" badge next to the member name (via the `buddyx_pro_after_member_name` hook) for any candidate whose `_wcb_open_to_work` user meta is set. This badge surfaces a candidate's job-seeking status to the rest of the community. The badge appears only when that meta value is present on the member.

## Compatibility Stylesheet

WP Career Board enqueues `buddyx-compat.css` on:

- Any WP Career Board single post (`wcb_job`, `wcb_application`, `wcb_company`, `wcb_resume`)
- Any WP Career Board post-type archive
- Any WP Career Board taxonomy archive (category, job type, tag, location, experience)
- Any page or post whose content embeds a `wp-career-board/*` or `wcb/*` block

## BuddyPress with BuddyX

If you also run BuddyPress alongside BuddyX or BuddyX Pro, WP Career Board's BuddyPress integration adds member types and a job-posted activity item on top of the theme styling. See [BuddyPress Integration](./buddypress.md) for details.

## Customizing the Design

All WP Career Board styles use the `.wcb-*` CSS class namespace and are driven by `--wcb-*` CSS variables. You can override any style by adding custom CSS to your BuddyX child theme or via **Appearance > Customize > Additional CSS**. Because the plugin is token-driven, re-mapping a `--wcb-*` variable restyles every block at once.
