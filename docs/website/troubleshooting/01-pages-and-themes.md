# Troubleshooting: pages and themes

Find your symptom below. Each entry gives the cause and the fix.

## Site Health says your theme has outdated template copies

**Symptom.** Under **Tools > Site Health > Status**, the item "Your theme has outdated WP Career Board template copies" is listed as a recommended improvement. It names your file and the plugin file to compare it with.

**Cause.** Your theme (or child theme) contains its own copy of `single-wcb_job.php` or `single-wcb_company.php`. With WP Career Board Pro, `single-wcb_resume.php` is checked too. A plugin update changed the plugin's version of that template, and your copy is older or has no `Template version` line. Your copy still works, but it does not get the plugin's changes.

**Fix.** Open the plugin file named in the item, carry your customisations over to a fresh copy of it (or merge the plugin's changes into yours), and set the `Template version` line in your copy to the plugin's. The item clears on the next check. If you do not need the customisation, delete your copy. See [Template overrides](../developer-guide/06-template-overrides.md).

## A block theme shows a blog-style loop on job, company or category archives

**Symptom.** The jobs archive, the company archive or a job category page shows a list of full posts under a heading such as "Archives: Jobs" instead of the job cards.

**Cause.** On a block theme, WordPress renders the theme's own archive template. The plugin registers archive templates that hold the Job Listings and Company Archive blocks, but a template file with the same slug in your theme (`archive-wcb_job`, `archive-wcb_company`, `taxonomy-wcb_category`, `taxonomy-wcb_job_type`, `taxonomy-wcb_tag`, `taxonomy-wcb_location`, `taxonomy-wcb_experience`) takes priority over the plugin's. A customised template that lacks the block prints the generic loop.

**Fix.** Open **Appearance > Editor > Templates**. If a customised template with one of those slugs exists, add the Job Listings (or Company Archive) block to it, or remove the customisation so the plugin's template is used. If the file lives in the theme folder, edit or delete it there. See [Block themes](../developer-guide/06-template-overrides.md#block-themes).

## Job, company and resume pages are narrower or wider than the rest of the site

**Cause.** Career Board pages follow your theme's content width by default. If someone set **Content Width (px)** under **Settings > Advanced**, that value wins. Values are limited to 720 to 1920 px.

**Fix.** Set **Content Width (px)** back to 0 to follow the theme, or to the width you want.

## Pages return 404 after the setup wizard

**Fix.** Go to **Settings > Permalinks** and click **Save Changes** to flush rewrite rules. If a page is blank, edit it and insert the matching block. See [Troubleshooting](../admin-guide/07-troubleshooting.md).

## The missing pages banner will not go away

**Cause.** A page is unassigned or not published. The banner names each one.

**Fix.** Click **Create Missing Pages**, or assign a published page under **Settings > Pages**. A page that exists but is a draft or in the trash counts as missing.
