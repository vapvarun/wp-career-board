# BuddyX and BuddyX Pro

You can run WP Career Board on the BuddyX or BuddyX Pro theme with no setup. The job board picks up the theme's colors, dark mode and job page templates. The integration turns on when the active theme is `buddyx` or `buddyx-pro`, including a child theme of either.

## What you get

- A BuddyX single job template and jobs archive template.
- Colors that follow the theme. The plugin's primary color reads BuddyX's own color variables, and the components switch with the theme's dark mode.
- A compatibility stylesheet, `buddyx-compat.css`, loaded wherever Career Board content shows: on job, company, resume and application pages, on the job archive and job taxonomy archives, and wherever a Career Board block renders.
- An #OpenToWork badge on BuddyX Pro member profiles (see below).

## Set up

Activate BuddyX or BuddyX Pro and WP Career Board. There is nothing to configure.

## Change the colors

The plugin's primary color is the CSS variable `--wcb-primary`. To use a different color, override it in **Appearance > Customize > Additional CSS** or in your child theme. All Career Board styles use the `.wcb-*` class prefix and `--wcb-*` variables, so changing one variable restyles every block.

## #OpenToWork badge

On BuddyX Pro member profiles, an "#OpenToWork" badge appears next to the member name when the member's `_wcb_open_to_work` user meta is set. WP Career Board Pro sets it when a candidate ticks open to work on their profile. The badge uses the `buddyx_pro_after_member_name` hook, so it is a BuddyX Pro feature.

## Use your own template

If your theme has its own `single-wcb_job.php` or `archive-wcb_job.php`, it is used instead of the bundled BuddyX template. A generic `single.php` or `archive.php` does not count. See [Template overrides](../developer-guide/06-template-overrides.md).

## Page width

Career Board pages follow the theme's content width. To set a fixed width, use **Content Width (px)** under **Career Board > Settings > Advanced**. Leave it at 0 to follow the theme.

## BuddyPress

If BuddyPress is active with BuddyX, the plugin's BuddyPress integration also applies. See [BuddyPress integration](03-buddypress.md).
