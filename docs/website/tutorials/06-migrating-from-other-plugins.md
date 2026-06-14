# Migrating from Another Job Board Plugin

If you already run a job board on WordPress with WP Job Manager,
Simple Job Board, WP Jobster, or a similar plugin, you can migrate to
WP Career Board without losing data or breaking your existing URLs.
This page walks through the path.

## Before you migrate - make these decisions

1. **Same URL structure or new one?**
   Career Board registers its jobs CPT at the `jobs` slug, so single
   jobs live at `/jobs/{slug}/` and the CPT archive is `/jobs/`. WP Job
   Manager defaults to `/job/{slug}/` (singular), so even a WPJM
   migration needs a redirect from `/job/...` to `/jobs/...`. Set up
   redirects so the old URLs still work - covered below.

2. **Move applications and candidate accounts, or only jobs?**
   - **Jobs only** is the fast migration: ~30 minutes.
   - **Jobs + applications + candidate accounts** is the slow
     migration: 1-3 hours, more variables.

3. **Hard cutover or soft?**
   - **Hard:** deactivate the old plugin the moment Career Board is
     ready. URLs flip in one window.
   - **Soft:** run both plugins for a couple of weeks; the old plugin
     handles existing listings; new postings go through Career Board.
     Migrate old listings on a schedule.

4. **Same theme or new?**
   If your old plugin had a heavily-customised template, your theme
   likely has overrides for it. Plan a quick visual QA after switching.

## Migration paths by source plugin

### From WP Job Manager (Astoundify / Automattic)

The most common migration, and the only built-in importer. **WP Job
Manager migration ships in Free** - you do not need Pro for it.

**Prerequisites:**

- WP Career Board (Free) installed and active.
- Old WP Job Manager still active during the migration so its data is
  readable.

**Path:**

1. **WP Admin → Career Board → Import.** The page shows a card for each
   importer and whether the source plugin is detected.
2. **WP Job Manager → Jobs.** This migrates `job_listing` posts to
   `wcb_job`, carrying:
   - **Company** (post meta `_company_name` → `_wcb_company_name`, logo
     included).
   - **Categories** (taxonomy `job_listing_category` → `wcb_category`).
   - **Types** (taxonomy `job_listing_type` → `wcb_job_type`).
   Each migrated job is tagged with `_wcb_migrated_source` =
   `wp-job-manager`, so re-running the importer safely skips records
   already migrated.
3. Run it. The importer works in batches and is idempotent (safe to run
   again).
4. **WP Job Manager → Resumes.** A separate card on the same Import page
   migrates WPJM Resumes (`resume` CPT) into `wcb_resume`. (Resumes are
   a Pro feature, so this card is useful when Pro is active.)
5. Verification: open `/find-jobs/` (or the `/jobs/` archive) in a new
   tab. You should see the old jobs. Note the new single-job URL is
   `/jobs/{slug}/`, so add a redirect from the old `/job/{slug}/`
   (singular) pattern - see "Preserving URLs" below.
6. **Deactivate WP Job Manager** when you're confident.
7. **Test apply flow** as a candidate - the apply form is the most
   common breakage point.

**What doesn't transfer automatically:**

- **Bookmarks/saved jobs in WPJM** - the data model is different;
  bookmarks reset.
- **Custom fields added through WPJM Field Editor** - not mapped.
  Recreate them with Pro's Field Builder.

### From other plugins (Simple Job Board, WP Jobster, custom CPTs)

There is **no dedicated importer for Simple Job Board, WP Jobster, or
other plugins.** The only non-WPJM path is the **CSV importer, which is
part of Pro's Migration module** (`Career Board → Migration`, Pro).

For any non-WPJM source:

1. **Export old data to CSV** - most plugins have an export tool;
   if not, query the database directly:
   ```sql
   SELECT post_title, post_content, post_date, post_status
   FROM wp_posts WHERE post_type = 'your_old_cpt';
   ```
2. **Use Pro's CSV importer** in **WP Admin → Career Board →
   Migration** (requires Pro).
3. Map CSV columns to Career Board fields.
4. Run import. Career Board creates one `wcb_job` per CSV row.

These migrations are more art than science - budget extra time and test
thoroughly.

## Preserving URLs (redirects)

Most plugins use different URL patterns. To avoid breaking SEO:

Career Board serves single jobs at `/jobs/{slug}/`.

| Old plugin | Old URL pattern | Career Board pattern | Redirect needed? |
|---|---|---|---|
| WP Job Manager | `/job/{slug}/` | `/jobs/{slug}/` | Yes |
| Simple Job Board | `/jobpost/{slug}/` | `/jobs/{slug}/` | Yes |
| WP Jobster | `/jobs/{slug}/` | `/jobs/{slug}/` | Depends on slug overlap |
| Custom CPT | varies | `/jobs/{slug}/` | Yes |

### Setting up redirects

**Option A - Redirection plugin (recommended)**

1. Install [Redirection](https://wordpress.org/plugins/redirection/).
2. Add a regex rule:
   - Source: `/jobpost/(.*)$`
   - Target: `/jobs/$1`
   - 301 permanent.
3. Verify with a sample URL in Redirection's "Check Redirect" tool.

**Option B - .htaccess** (Apache)

```apache
RewriteRule ^jobpost/(.*)$ /jobs/$1 [R=301,L]
```

Career Board does not ship a built-in redirect-map UI, so use one of
the two options above (a redirects plugin or `.htaccess`).

## Testing the migration

A solid test path before going live:

1. **Staging copy first.** Run the migration on a staging copy, not
   live, the first time. Spend a day kicking the tires.
2. **Sample 20 random old listings.** Verify each one rendered
   correctly on Career Board. Check:
   - Title, description (formatting preserved).
   - Featured image (if any) carried over.
   - Application form works.
   - Old URL redirects to new URL.
3. **Test the apply flow** as a fresh candidate. The most common
   regression: the old plugin used a different field schema, and
   the application form expects fields the import didn't set.
4. **Run a few search queries** that worked on the old board. The
   results should be similar (Career Board's search is different -
   not pixel-identical results, but the relevant jobs surface).
5. **Run an SEO crawl.** A simple ScreamingFrog scan against the new
   site catches broken redirects, missing meta, lost canonicals.
6. **Email your top 20 employers and top 100 candidates** a week
   before the cutover, telling them what's changing and what to
   expect (their login still works, their data is preserved, the
   URL of their saved searches may change).

## After migration

Once live:

- **Monitor the error log for the first 48 hours.** Edge cases
  surface as PHP notices or warnings - check `wp-content/debug.log`
  for anything Career Board-related.
- **Watch for support tickets** about login issues. The user account
  table is unchanged, but roles / capabilities may have shifted. Career
  Board ships its own roles (Employer, Candidate, Job Moderator) backed
  by `wcb_*` capabilities; assign the Employer role to migrated posters
  if their old role doesn't carry the Career Board capabilities.
- **Update the FAQ / help docs on your site** - old links, old
  screenshots, old terminology.
- **Set a 30-day reminder** to deactivate redirects you no longer
  need (after most traffic has stopped hitting old URLs).

## What you'll likely have to rebuild

Some things don't migrate cleanly because the underlying models differ:

- **Email templates** - Career Board ships its own; old plugin's
  custom subject lines / branding need to be re-created.
- **Application form field order** - Career Board's order is
  consistent (name, email, resume, cover, custom fields); if your old
  plugin had a custom layout, re-create with the Field Builder.
- **Theme template overrides** - old plugin's templates won't apply
  to Career Board's blocks. If you had heavy theme customisation, you
  may need to redo it with Career Board's filter hooks instead.
- **Integration with third-party tools** - Zapier connections, ATS
  integrations, etc. The Career Board REST API is well-documented; you'll
  rebuild the integrations against the new endpoints.

## Where to go next

- [01-first-day-as-site-owner.md](01-first-day-as-site-owner.md) - set
  up Career Board correctly before importing.
- The built-in WP Job Manager importer lives on **Career Board →
  Import** (Free).
- Pro's Migration module (with the CSV importer) is documented in the
  Pro docs - use it for non-WPJM sources.
