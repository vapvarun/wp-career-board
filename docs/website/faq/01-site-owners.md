# FAQ for site owners

## Why does settings say some pages are missing?

The banner at the top of **Career Board → Settings** lists, by name, each page that is not assigned or not published. Click **Create Missing Pages**. It keeps pages you already have, reuses a page that already holds the right block, and creates only what is missing. See [Pages](../admin-guide/01-settings.md#pages).

## What is the registration URL?

`/employer-registration/`, the page titled **Employer Registration**. One form serves both roles: visitors choose **Hire Talent** to become employers or **Find a Job** to become candidates. If you moved it, the page assigned under **Settings → Pages → Employer Registration Page** is the one the plugin links to.

## Should I use the setup wizard or settings?

Either. The wizard saves the same settings, through the same cleaning rules, as the Settings tabs. Use the wizard for a first pass: pages, sign-ups, jobs, emails and spam protection. Use Settings for everything else and for later changes. You can run the wizard again at any time with **Re-run Setup Wizard** at the bottom of Settings. Pages you already have are kept.

## Why are new jobs not published straight away?

**Auto-Publish Jobs** is off by default, so every job waits as Pending for your approval under **Career Board → Jobs**. Turn it on under **Settings → Jobs** to publish at once. See [Moderation](../admin-guide/03-moderation.md).

## How do I remove all data when I delete the plugin?

Turn on **Remove Data on Delete** under **Settings → Advanced**, then delete the plugin. It erases jobs, applications, companies, resumes, uploaded resume files, credit balances, settings and roles, and cannot be undone. With the setting off (the default), deleting keeps everything so a reinstall picks up where you left off. Deactivating never deletes data. The WordPress pages the plugin created are not deleted either; remove them yourself.

## Do candidates need a candidate role?

No. By default any signed-in member can apply, save jobs and keep a resume, which suits a community site. Turn on **Require Candidate Role** under **Settings → Sign-ups** to reserve the candidate experience for users with that role.

## Can visitors apply without an account?

Yes, unless you turn on **Require login to apply** under **Settings → Sign-ups**. Guests give a name and email, get a confirmation and status emails, and are limited to 10 applications an hour from one connection. If a guest later registers with the same email, their earlier applications move to the new account. See [Apply as a Guest](../for-candidates/09-guest-apply.md).

## How do I change the sender of Career Board emails?

Set **From Name**, **From Email** and **Admin Notification Email** in the **Sender** card under **Settings → Emails**. Use an address on your own domain and an SMTP plugin for reliable delivery. See [Email Notifications](../admin-guide/02-email-notifications.md).

## How do I turn off Google for Jobs markup?

Untick **Google for Jobs** under **Settings → Jobs → Search engines and sharing**. Leave it on unless another plugin already adds job markup. See [Google for Jobs and Social Sharing](../admin-guide/17-google-for-jobs.md).

## How do I make the plugin's pages match my theme?

Pages and dashboards follow your theme. To change how a single job or company page is built, copy the plugin's template into your theme. Site Health then tells you when the plugin's version moves ahead of your copy. See [Template Overrides](../developer-guide/06-template-overrides.md).
