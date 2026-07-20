# Privacy & GDPR Compliance

A complete walkthrough of what data WP Career Board stores, how to
make it GDPR / CCPA / similar-regulation compliant, and the
day-to-day operations a board owner needs to handle (consent,
exports, deletion requests, retention).

This is not legal advice - but it covers the technical operations
that translate "be compliant" into "do this in the dashboard."

## What data Career Board stores about candidates

A summary so you know what's actually on the line:

| Data | Where | When deleted |
|---|---|---|
| **User account** | `wp_users` table | On admin-processed erasure, or admin removal |
| **Candidate profile** (email, phone, location, bio) | WordPress user + user meta | With the user account |
| **Resumes** (Pro, uploaded files) | `wcb_resume` CPT + media | On erasure (deleted) |
| **Applications submitted** | `wp_posts` (CPT `wcb_application`) | On erasure: the candidate's applications are deleted |
| **Cover letters and answers** | Application meta | Deleted with the application |
| **Saved jobs (bookmarks)** | User meta | With the user account |
| **Job alerts** (Pro) | `wcb_job_alerts` table (Pro) | With the user account |
| **AI vectors** (Pro, jobs only) | `wcb_ai_vectors` table (Pro) | Kept until the job is deleted |

> Career Board does not anonymise applications - the privacy eraser
> deletes the candidate's applications and resumes outright. There is no
> "Anonymous candidate" preservation and no `wcb_anonymize_or_delete`
> filter.

## What data Career Board stores about employers

| Data | Where | When deleted |
|---|---|---|
| **User account** | `wp_users` | On removal |
| **Company profile** (name, logo, about, locations) | `wcb_company` CPT + meta | With company removal |
| **Jobs posted** | `wcb_job` CPT | When the employer deletes them; when a job is permanently deleted, its applications transition to the `job_removed` status |
| **Credit ledger** (Pro) | `wcb_credit_ledger` table (Pro) | Never auto-deleted (append-only financial record; admin purges manually if required) |
| **Payment records** | WooCommerce / PMPro / etc. (Pro checkout) | Per that plugin's deletion policy |

## What you legally need to do (typical GDPR baseline)

1. **Disclose what you collect** in a privacy policy on the site.
2. **Obtain consent before collecting** (typically a checkbox on
   registration / apply forms).
3. **Provide data exports** when a user requests their data (right of
   access).
4. **Provide data deletion** when a user requests it (right to
   erasure).
5. **Set a retention policy** (don't keep data forever without reason).
6. **Notify on breach** within 72 hours of detection.

The technical operations:

## Step 1 - Privacy policy text

Career Board doesn't generate your privacy policy text, but here's a
template paragraph to add to yours:

> **Job Board Data**: When you register on this site as a candidate
> or employer, we collect the information you provide (name, email,
> resume, profile details, job postings, applications). We store
> this on our servers running WP Career Board. We use this data to
> show your profile / jobs / applications to relevant parties on
> the board. We do not sell this data. You can request a full
> export or deletion of your data at any time from
> **Candidate Dashboard → Settings** (Export my data / Delete my
> account). Requests are processed by the site administrator through
> WordPress's privacy tools.
>
> **AI Features**: If we have AI features enabled, your data may be
> sent to a third-party AI provider (OpenAI, Anthropic Claude, or
> Ollama on our own server) for processing. See the AI provider's
> own privacy policy for their handling. You can opt out of AI
> processing by [linking to the opt-out mechanism if you have one].
>
> **Payment Data**: If you purchase services (e.g. job posting
> credits, subscriptions), payment is processed by [WooCommerce /
> PMPro / etc.] - see their privacy policy for payment data
> handling.

**Update** the bracketed bits to your specifics. Always have a
lawyer review the final version.

## Step 2 - Consent capture

Career Board does not ship a built-in consent checkbox or a "Settings →
Privacy → Consent text" screen, and it does not log a
`_wcb_privacy_consent` meta value. If your jurisdiction requires
explicit consent at registration or apply time, add it yourself:

- Use a consent-management / forms plugin, or
- Add a required checkbox to the registration / apply forms via the form
  field filters (for example `wcb_candidate_form_fields`,
  `wcb_application_form_fields_groups`) and store the result on the user
  or application.

Always link your privacy policy from wherever you collect data.

## Step 3 - Handling data export requests (Right of Access)

Career Board uses WordPress's standard privacy request flow for both
export and erasure - it does not generate its own instant ZIP download.

**Option A - User requests it from the dashboard**

1. **Candidate Dashboard → Settings → Export my data → Request data
   export.**
2. This calls WordPress's `wp_create_user_request()` with the
   `export_personal_data` type, sends a confirmation email to the
   candidate, and enters the request into WordPress's privacy queue.
3. The candidate clicks the confirmation link; the **site
   administrator** then completes the export from WP Admin.

**Option B - Admin handles it directly**

1. **WP Admin → Tools → Export Personal Data** (WordPress's built-in
   privacy tool).
2. Enter the user's email and create the request.
3. Career Board registers a privacy **exporter** (via
   `wp_privacy_personal_data_exporters`), so the candidate's
   applications and status are included in the WordPress export.
4. The export is delivered through WordPress's standard download/email
   mechanism.

Career Board's exporter is paginated, so it works on large accounts
without a single timeout-prone query.

## Step 4 - Handling data deletion requests (Right to Erasure)

**For candidates:**

**Option A - User requests it from the dashboard**

1. **Candidate Dashboard → Settings → Delete my account → Send
   confirmation email.**
2. This calls WordPress's `wp_create_user_request()` with the
   `remove_personal_data` type and emails a confirmation link.
3. The candidate clicks the link; the request enters WordPress's
   privacy queue for the **site administrator** to complete.

**Option A2 - Self-service deletion from the mobile app (1.7.0)**

If the site runs the WP Career Board companion mobile app, a member
can delete their own account from inside the app without waiting on
the administrator: confirm their password and type DELETE to confirm,
and the account is suspended immediately and scheduled for deletion
after a grace period (14 days by default, filterable with
`wcb_account_deletion_grace_days`). Signing back in during the grace
period cancels the deletion. Once the grace period passes, a daily
cron job runs `wp_delete_user()` on the account - the same core
WordPress deletion cascade Option B below relies on, so it removes the
account the same way an admin-processed erasure would.

**Option B - Admin handles it directly**

1. **WP Admin → Tools → Erase Personal Data** (WordPress built-in).
2. Enter the user's email and confirm.
3. Career Board registers a privacy **eraser** (via
   `wp_privacy_personal_data_erasers`) that runs in pages and **deletes**
   the candidate's applications (and their attached resumes).

**What happens on erasure:**

- The candidate's applications are **deleted** (`wp_delete_post`), not
  anonymised. There is no "Anonymous candidate" placeholder and no
  `wcb_anonymize_or_delete` filter - deletion is the only behavior.
- Removing the user account itself (and reassigning or deleting their
  authored content) is handled by WordPress's standard user-deletion
  flow, which the admin runs alongside the erasure.

**For employers:**

1. Use **WP Admin → Tools → Erase Personal Data** (or delete the user
   in WP Admin → Users). There is no employer-dashboard "Delete Account"
   button.
2. When an employer's jobs are permanently deleted, every linked
   application transitions to the `job_removed` status (the candidate
   keeps the row in their history).
3. Credit ledger (Pro): not deleted (append-only financial record). If
   a jurisdiction requires it, the admin must purge the ledger table
   manually.

## Step 5 - Retention policy

Career Board does not ship a retention settings screen or a retention
cron - there is no "Settings → Privacy → Retention" page and no
`wcb_privacy_retention_cron`. The daily crons that do exist handle job
expiry, featured-listing expiry, and deadline reminders, not data
retention.

If you need scheduled PII purges (e.g. delete applications older than N
months), implement them yourself - schedule a WP-Cron event that runs
your own cleanup against the `wcb_application` posts, and document the
policy in your privacy notice.

## Step 6 - Cookie policy

Career Board does not set its own tracking cookies. Saved jobs
(bookmarks) are stored as `_wcb_bookmark` **user meta** for logged-in
users - there is no `wcb_saved_jobs` cookie and no logged-out bookmark
feature in Free.

The only cookies in play are WordPress's standard authentication
cookies, the same as any WordPress site. Cover those in your cookie
banner as you would for any WordPress install.

## Step 7 - AI features and data exposure (Pro)

If you have Pro AI enabled, additional disclosure is needed:

1. **Update privacy policy** with a section like:
   > "We use AI to power [list of features]. When you submit data
   > (resume, profile, application), it may be sent to our AI provider
   > ([OpenAI / Anthropic Claude / Ollama]). Their data handling is
   > governed by [provider URL]."

2. **Per-feature opt-out (optional, custom).** Add a checkbox on the
   apply form via the `wcb_application_form_fields_groups` filter, then
   act on it from the `wcb_application_submitted` action to skip AI
   ranking when it is unchecked.

3. **Use Ollama for sensitive data.** If you can't share resume /
   application content with a US LLM (HIPAA, sensitive sectors), run
   Ollama on your server. See
   [../ai-features/02-setup-and-providers.md](../ai-features/02-setup-and-providers.md).

## Step 8 - Data Processing Agreement (DPA)

For GDPR compliance, your DPA needs to cover:

- **Your role:** controller (you decide what's collected).
- **Sub-processors:**
  - WordPress hosting provider (e.g. SiteGround, Kinsta).
  - Payment processor (WooCommerce + Stripe / PayPal).
  - AI provider (if Pro AI is on).
  - Email service (if using SMTP plugin's provider).
- **Each sub-processor needs its own DPA**, which you reference in
  yours.

This is paperwork, not technical setup. The plugin's role is to make
sure the data flow doesn't include unexpected processors.

## Step 9 - Breach response checklist

If you suspect a breach (unauthorised access, data leak):

1. **Identify scope.** Which tables / files / accounts were exposed?
2. **Contain.** Force-rotate all admin / employer passwords. Disable
   the affected user accounts if compromised.
3. **Notify affected users within 72 hours** (GDPR requirement). Pull
   the affected user list from **WP Admin → Users** (filter by the
   Career Board roles) or with WP-CLI (`wp user list`).
4. **Notify authorities** if the breach meets the threshold for your
   jurisdiction (each EU state's DPA, ICO in the UK, etc.).
5. **Patch.** Fix the underlying cause. Common causes: outdated
   plugin, weak admin password, compromised host.
6. **Document.** Keep records of what happened, what you did, what
   was disclosed - for regulator audits.

## Common compliance mistakes

- **No consent checkbox at registration.** Career Board does not ship
  one - add your own (consent plugin or a required field via the form
  filters) if your jurisdiction requires it.
- **Publishing job activity to the BP stream.** Free posts a "[Member]
  posted a new job" activity entry on publish, and Pro can broadcast
  more events. Make sure your privacy notice covers any activity that
  reveals hiring/job-search behaviour.
- **AI providers not disclosed.** Adding Pro AI without updating the
  privacy policy is a quick way to be non-compliant. Always update
  the policy AND notify existing users of the change.
- **Manual database deletes.** Don't delete rows by hand - use WP's
  privacy eraser (Tools → Erase Personal Data) so Career Board's eraser
  removes the candidate's applications and resumes consistently.

## Where to go next

- [../admin-guide/04-gdpr.md](../admin-guide/04-gdpr.md) - full
  GDPR-specific admin reference.
- [01-first-day-as-site-owner.md](01-first-day-as-site-owner.md) -
  if you're still in setup mode, make sure privacy is configured
  from day one.
- [../ai-features/02-setup-and-providers.md](../ai-features/02-setup-and-providers.md) -
  data flow details per AI provider.
