# Reign theme

You can run WP Career Board on the Reign theme with no setup. The job board picks up Reign's colors and dark mode, uses Reign job page templates, and adds job links to Reign's navigation. The integration turns on when Reign is the active theme, including a child theme of Reign.

## What you get

- A Reign single job template and jobs archive template.
- Colors that follow Reign. The plugin's primary color reads Reign's own color variable, so it follows the color palette you choose in Reign. The components switch with Reign's dark mode.
- A compatibility stylesheet, `reign-compat.css`, loaded after Reign's main stylesheet wherever Career Board content shows: on job, company, resume and application pages, on the job archive and job taxonomy archives, and wherever a Career Board block renders.
- Career Board links in Reign's navigation.

## Set up

Activate Reign and WP Career Board. There is nothing to configure.

## Navigation links

The integration adds these links through Reign's `reign_nav_items` filter:

- **Browse Jobs** for everyone. It links to your Find Jobs page.
- **Employer Dashboard** for users who can post jobs. It links to your Employer Dashboard page.
- **My Applications** for users who can apply to jobs. It links to your Candidate Dashboard page.

## Change the colors

The plugin's primary color is the CSS variable `--wcb-primary`. To use a different color, override it in **Appearance > Customize > Additional CSS** or in your child theme. All Career Board styles use the `.wcb-*` class prefix and `--wcb-*` variables, so changing one variable restyles every block.

## Use your own template

If your theme has its own `single-wcb_job.php` or `archive-wcb_job.php`, it is used instead of the bundled Reign template. A generic `single.php` or `archive.php` does not count. See [Template overrides](../developer-guide/06-template-overrides.md).

## Page width

Career Board pages follow the theme's content width. To set a fixed width, use **Content Width (px)** under **Career Board > Settings > Advanced**. Leave it at 0 to follow the theme.

## BuddyPress

If BuddyPress is active with Reign, the plugin's BuddyPress integration also applies. See [BuddyPress integration](03-buddypress.md).
