# Migrating from Another Job Board Plugin

If you already run a job board on WordPress with WP Job Manager,
Simple Job Board, WP Jobster, or a similar plugin, you can migrate to
WP Career Board without losing data or breaking your existing URLs.
This page walks through the path.

## Before you migrate — make these decisions

1. **Same URL structure or new one?**
   The old plugin used `/job/{slug}/` or `/jobs/{slug}/`. Career Board
   defaults to `/job/{slug}/` (same as WP Job Manager). If your old
   structure differs, set up redirects so the old URLs still work —
   covered below.

2. **Move applications and candidate accounts, or only jobs?**
   - **Jobs only** is the fast migration: ~30 minutes.
   - **Jobs + applications + candidate accounts** is the slow
     migration: 1–3 hours, more variables.

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

The most common migration. Career Board ships a Pro **Migration**
module that handles WP Job Manager directly.

**Prerequisites:**

- WP Career Board Free + Pro both installed and active.
- Old WP Job Manager still active during the migration.

**Path:**

1. **Career Board → Migration → From WP Job Manager.**
2. Pre-flight check: the tool inventories everything to migrate:
   - **Jobs** (CPT `job_listing` → `wcb_job`)
   - **Companies** (post meta `_company_name` → CPT `wcb_company`)
   - **Categories** (taxonomy `job_listing_category` → `wcb_job_category`)
   - **Types** (taxonomy `job_listing_type` → `wcb_job_type`)
   - **Regions** (taxonomy `job_listing_region` → `wcb_job_region`)
   - **Applications** (if you also had WP Job Manager Applications
     add-on)
3. Review the inventory. Untick anything you don't want migrated.
4. Click **Migrate.** The tool runs in batches of 50 (configurable).
   A progress bar shows; you can pause and resume.
5. Verification step: open `/find-jobs/` in a new tab. You should see
   all old jobs. URLs are preserved (Career Board sets the slug to the
   old job's slug, so `/job/senior-engineer/` stays `/job/senior-engineer/`).
6. **Deactivate WP Job Manager** when you're confident.
7. **Test apply flow** as a candidate — important because the apply
   form is the most common breakage point.

**What doesn't transfer automatically:**

- **Resumes uploaded through WPJM Resumes add-on** — these need a
  separate import step (Career Board → Migration → WPJM Resumes).
- **Bookmarks/saved jobs in WPJM** — the data model is different;
  bookmarks reset.
- **Custom fields added through WPJM Field Editor** — the migrator
  detects them but maps them as raw meta. Use Pro's Field Builder
  to recreate the fields in Career Board and the data populates.

### From Simple Job Board (PressTigers)

Less common but supported.

**Path:**

1. **Career Board → Migration → From Simple Job Board.**
2. Pre-flight check — Simple Job Board uses CPT `jobpost` and
   taxonomies `jobpost_category` + `jobpost_location`.
3. Run the migration. Same batch flow as WP Job Manager.
4. **URL note:** Simple Job Board uses `/jobs/` (plural) as the
   archive slug. Career Board's default is `/find-jobs/`. If you want
   to keep `/jobs/`, change Career Board's archive slug in
   **Settings → Permalinks → Job Archive Slug** before activation.
5. Add redirects for `/jobpost/{slug}/` → `/job/{slug}/` if you want
   old links to keep working.

### From WP Jobster (themeforest)

WP Jobster is heavier than the other two — it includes a payment
gateway, freelancer support, employer/candidate dashboards, etc.
The migration covers the job-board parts; the freelancer marketplace
parts are out of scope.

**Path:**

1. **Career Board → Migration → From WP Jobster.**
2. Pre-flight check: jobs, companies, candidates, applications.
3. **Payment / credit data:** Jobster's credit system has different
   semantics; Career Board's adapter approximates by migrating the
   employer's current balance to a single topup row. Historical
   transactions don't transfer.
4. Run migration.
5. Plan extra time for theme migration — most Jobster sites use the
   Jobster theme, which has heavy template overrides. You'll likely
   need to switch theme or do CSS work after the migration.

### From a custom / unsupported plugin

If your old plugin isn't in the supported list:

1. **Export old data to CSV** — most plugins have an export tool;
   if not, query the database directly:
   ```sql
   SELECT post_title, post_content, post_date, post_status
   FROM wp_posts WHERE post_type = 'your_old_cpt';
   ```
2. **Use the Career Board CSV importer** in
   **WP Admin → Career Board → Import.**
3. Map CSV columns to Career Board fields.
4. Run import. Career Board creates one `wcb_job` per CSV row.

Custom-plugin migrations are more art than science — budget extra
time and test thoroughly.

## Preserving URLs (redirects)

Most plugins use different URL patterns. To avoid breaking SEO:

| Old plugin | Old URL pattern | Career Board pattern | Redirect needed? |
|---|---|---|---|
| WP Job Manager | `/job/{slug}/` | `/job/{slug}/` | No — slugs preserved |
| Simple Job Board | `/jobpost/{slug}/` | `/job/{slug}/` | Yes |
| WP Jobster | `/jobs/{slug}/` | `/job/{slug}/` | Yes |
| Custom CPT | varies | `/job/{slug}/` | Yes |

### Setting up redirects

**Option A — Redirection plugin (recommended)**

1. Install [Redirection](https://wordpress.org/plugins/redirection/).
2. Add a regex rule:
   - Source: `/jobpost/(.*)$`
   - Target: `/job/$1`
   - 301 permanent.
3. Verify with a sample URL in Redirection's "Check Redirect" tool.

**Option B — .htaccess** (Apache)

```apache
RewriteRule ^jobpost/(.*)$ /job/$1 [R=301,L]
```

**Option C — Career Board's built-in redirect map**

If the Migration module was used:

1. **Career Board → Migration → URL Map.**
2. The tool generated a list of old→new slug pairs.
3. Toggle "Activate redirect rules" — Career Board serves the 301s
   from PHP. No plugin needed.

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
   results should be similar (Career Board's search is different —
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
  surface as PHP notices or warnings — check `wp-content/debug.log`
  for anything Career Board-related.
- **Watch for support tickets** about login issues. The user account
  table is unchanged, but roles / capabilities may have shifted (your
  old plugin might have used `pgs_employer` as a role; Career Board
  uses capabilities, not custom roles for employers).
- **Update the FAQ / help docs on your site** — old links, old
  screenshots, old terminology.
- **Set a 30-day reminder** to deactivate redirects you no longer
  need (after most traffic has stopped hitting old URLs).

## What you'll likely have to rebuild

Some things don't migrate cleanly because the underlying models differ:

- **Email templates** — Career Board ships its own; old plugin's
  custom subject lines / branding need to be re-created.
- **Application form field order** — Career Board's order is
  consistent (name, email, resume, cover, custom fields); if your old
  plugin had a custom layout, re-create with the Field Builder.
- **Theme template overrides** — old plugin's templates won't apply
  to Career Board's blocks. If you had heavy theme customisation, you
  may need to redo it with Career Board's filter hooks instead.
- **Integration with third-party tools** — Zapier connections, ATS
  integrations, etc. The Career Board REST API is well-documented; you'll
  rebuild the integrations against the new endpoints.

## Where to go next

- [01-first-day-as-site-owner.md](01-first-day-as-site-owner.md) — set
  up Career Board correctly before importing.
- [../pro-features/07-migration.md](../pro-features/07-migration.md) —
  the Migration module reference (Pro).
- [../admin-guide/05-import.md](../admin-guide/05-import.md) — CSV
  import details for custom-plugin migrations.
