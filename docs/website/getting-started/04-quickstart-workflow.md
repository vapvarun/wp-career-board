# Day-1 Quickstart Workflow

You've installed the plugin. Now what?

This is the **shortest path** from "fresh install" to "first job
posted, first applicant reviewed." Five tasks, in order, plus where
to go next when each is done.

## 1 - Finish the setup wizard

If you skipped the wizard during activation, re-run it from
**Career Board → Settings** using the **Run Setup Wizard** button in
the page header. The wizard:

- Creates the six required pages (Find Jobs, Post a Job, Employer
  Registration, Employer Dashboard, Candidate Dashboard, and
  Companies).
- Optionally installs sample data (3 companies, 8 jobs, and all
  taxonomy terms) so you can see real-looking content before you
  start typing your own.

**Skip if:** you've already completed it. Verify under
**Career Board → Settings → Pages** that all six pages are mapped.

## 2 - Add the menu items your site needs

Open **Appearance → Menus** and add at least:

- **For Jobs** → `/find-jobs/`
- **For Employers** → `/post-a-job/` or `/employer-dashboard/`
- **For Candidates** → `/candidate-dashboard/`
- **Companies** → `/companies/`

A career-board site that doesn't surface these on the main navigation
silently loses both candidates and employers - they can't find the
pages even when they exist.

## 3 - Post your first job (yourself, as admin)

Treat this as a smoke test of the whole pipeline.

1. Go to **`/post-a-job/`** (or click "Post a Job" from your menu).
2. Fill the form with a real-looking listing - title, company,
   description, salary range, location, category.
3. Submit. If moderation is on (Auto-Publish Jobs off), the job
   lands in **Career Board → Jobs → Pending Review**. Approve it.
4. Visit **`/find-jobs/`** - your job appears in the listing.
5. Click into it - the single job page should look the way you want
   candidates to see it. If something looks wrong (e.g. company
   info missing), it's worth fixing now before you invite real
   employers.

## 4 - Apply to it (yourself, as a test candidate)

1. Log out, or open a private window.
2. Register a test candidate account at
   **`/employer-registration/`** and choose **Find a Job**. (Or
   apply as a guest - guest applications are on by default in Free.)
3. Apply to the job you just posted. Upload a real resume PDF and
   write a real cover letter - see what the experience feels like.
4. Switch back to admin. Go to **Career Board → Applications**.
   Your test application should be there.
5. Click into it. This is the screen your real employers will see
   when they review applicants. If you don't like how it looks,
   tweak the Application Details widget order (admin guide
   covers customization).

## 5 - Decide on moderation, credits, emails

Three settings that determine the day-2 experience:

- **Moderation** (Career Board → Settings → Job Listings, the
  "Auto-Publish Jobs" toggle). Leave it off to require admin
  approval, or turn it on to auto-publish. Most marketplace sites
  use approval; most internal job boards auto-publish.
- **Credits** (Pro only - Career Board → Settings → Credits).
  Turn on if you want to charge employers per posting using the
  built-in credit system.
- **Email notifications** (Career Board → Settings → Emails). Make
  sure the candidate "application received" and employer "new
  application" emails are configured - they're how your candidates
  know they successfully applied and how your employers know to log
  in.

## You're ready

Once steps 1-5 are done, the site is operationally ready. Real
employers can register, post jobs, and review applicants. Real
candidates can browse, apply, and track their applications.

What comes next depends on what you're building:

| If you're running... | Read next |
|---|---|
| A community / public job board | [for-employers/02-post-a-job.md](../for-employers/02-post-a-job.md) - the full employer flow |
| A paid job board | [admin-guide/06-credit-system.md](../admin-guide/06-credit-system.md) - credit setup |
| An internal team hiring board | [admin-guide/01-settings.md](../admin-guide/01-settings.md) - auto-publish + role config |
| A site with existing classic-editor pages | [for-employers/11-page-builder-embeds.md](../for-employers/11-page-builder-embeds.md) - shortcodes |

## Troubleshooting Day-1 issues

- **Pages 404 after wizard** - flush rewrite rules: **Settings →
  Permalinks → Save** (no changes, just save).
- **Apply button missing on jobs** - the job must be published and
  open (not expired or closed). Guest applications are enabled by
  default in Free, so visitors can apply without an account. If you
  turned on **Require Candidate Role** (Settings -> Job Listings),
  only users with the Candidate role can apply.
- **No "Post a Job" link for employers** - the employer needs the
  `wcb_post_jobs` capability. Site admin has it by default; for
  other users, grant it via the Users page or use a role manager.
- **Emails not arriving** - your site's transactional email isn't
  set up. Install an SMTP plugin (FluentSMTP, WP Mail SMTP, etc.)
  and configure a real sending domain. Career Board's emails go
  through `wp_mail` like every other plugin.
