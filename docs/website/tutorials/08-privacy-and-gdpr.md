# Privacy & GDPR Compliance

A complete walkthrough of what data WP Career Board stores, how to
make it GDPR / CCPA / similar-regulation compliant, and the
day-to-day operations a board owner needs to handle (consent,
exports, deletion requests, retention).

This is not legal advice — but it covers the technical operations
that translate "be compliant" into "do this in the dashboard."

## What data Career Board stores about candidates

A summary so you know what's actually on the line:

| Data | Where | When deleted |
|---|---|---|
| **User account** | `wp_users` table | On account deletion request, or admin removal |
| **Candidate profile** (name, headline, bio, skills, location) | `wcb_candidate` CPT post + meta | With user account or on profile deletion |
| **Resumes** (uploaded PDFs) | Media Library + custom resume meta | With user account or via per-resume delete |
| **Applications submitted** | `wp_posts` (CPT `wcb_application`) | On user-account deletion: anonymised, not removed (audit trail preservation) |
| **Cover letters and answers** | Application meta | Same as application |
| **Saved jobs (bookmarks)** | User meta | With user account |
| **Job alerts** | `wcb_job_alerts` table (Pro) | With user account |
| **IP address** (on registration / apply, optional) | Registration log if logging is on | After 30 days unless retained for audit |
| **Email + login history** | Auth logs (WP default) | Per WP's standard retention |
| **AI parse results** (Pro) | Candidate post meta | With user account or on profile deletion |
| **AI vectors** (Pro, jobs only) | `wcb_ai_vectors` table | Jobs (not candidates) — kept until job is deleted |

## What data Career Board stores about employers

| Data | Where | When deleted |
|---|---|---|
| **User account** | `wp_users` | On removal |
| **Company profile** (name, logo, about, locations) | `wcb_company` CPT + meta | With company removal |
| **Jobs posted** | `wcb_job` CPT | When the employer deletes them, or on user removal: status changes to "Job Removed," applications preserved |
| **Credit ledger** | `wcb_credit_ledger` table | NEVER auto-deleted (financial record; admin must manually purge if required) |
| **Payment records** | WooCommerce / PMPro / etc. | Per their plugin's deletion policy |

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

## Step 1 — Privacy policy text

Career Board doesn't generate your privacy policy text, but here's a
template paragraph to add to yours:

> **Job Board Data**: When you register on this site as a candidate
> or employer, we collect the information you provide (name, email,
> resume, profile details, job postings, applications). We store
> this on our servers running WP Career Board. We use this data to
> show your profile / jobs / applications to relevant parties on
> the board. We do not sell this data. You can request a full
> export or deletion of your data at any time from your dashboard
> (Candidate Dashboard → Profile → Delete Account, or Employer
> Dashboard → Settings → Delete Account).
>
> **AI Features**: If we have AI features enabled, your data may be
> sent to a third-party AI provider (OpenAI, Anthropic Claude, or
> Ollama on our own server) for processing. See the AI provider's
> own privacy policy for their handling. You can opt out of AI
> processing by [linking to the opt-out mechanism if you have one].
>
> **Payment Data**: If you purchase services (e.g. job posting
> credits, subscriptions), payment is processed by [WooCommerce /
> PMPro / etc.] — see their privacy policy for payment data
> handling.

**Update** the bracketed bits to your specifics. Always have a
lawyer review the final version.

## Step 2 — Consent capture

Career Board registers a consent checkbox on the registration forms by
default:

- **Candidate registration:** "I agree to the [Privacy Policy](your-policy-url)."
- **Employer registration:** Same.
- **Guest apply form** (Pro, if enabled): "I agree to share my application
  with the employer."

To customise:

1. **Career Board → Settings → Privacy → Consent text.**
2. Edit the text shown for each form.
3. Toggle whether the checkbox is required (recommended: yes for both
   registration and apply).

The consent is logged with a timestamp on the user's account meta. To
view: open the user in WP Admin → look for `_wcb_privacy_consent`
meta.

## Step 3 — Handling data export requests (Right of Access)

When a user requests their data:

**Option A — User self-serves (recommended)**

1. **Candidate Dashboard → Profile → Download My Data.**
2. The system generates a ZIP containing:
   - Profile fields (JSON).
   - All applications (JSON + each cover letter as TXT).
   - Resume PDFs.
   - Saved jobs (JSON).
   - Job alerts (JSON).
3. Email link to the ZIP. The link expires after 24 hours for
   security.

**Option B — Admin handles it manually**

1. **WP Admin → Users → search for user.**
2. **Tools → Export Personal Data** (this is WordPress's built-in
   privacy tool).
3. Career Board hooks into WP's privacy exporter, so candidate /
   employer / application data is included in the export.
4. The user receives an email with a download link.

Test both paths once before going live. The most common failure: the
ZIP generation hits a memory limit on shared hosting if the user has
many applications. Workaround: increase `WP_MEMORY_LIMIT` or use the
async export queue (auto-enabled for users with >100 applications).

## Step 4 — Handling data deletion requests (Right to Erasure)

**For candidates:**

**Option A — User self-serves (recommended)**

1. **Candidate Dashboard → Profile → Delete Account.**
2. The system sends a confirmation email.
3. User clicks the confirmation link.
4. Deletion runs:
   - User account: deleted.
   - Profile fields: deleted.
   - Resumes: deleted from the media library.
   - Applications: **anonymised** (not deleted) — the employer
     still sees an "Anonymous candidate" application in their
     history, but no identifying data. This preserves the audit
     trail for the employer's compliance.
   - Saved jobs, job alerts: deleted.
   - AI parse data (Pro): deleted.

**Option B — Admin handles it manually**

1. **WP Admin → Tools → Erase Personal Data** (WordPress built-in).
2. Enter the user's email.
3. Confirm the erase. Career Board hooks into WP's eraser to handle
   the same anonymization above.

**Important — what's NOT deleted:**

- Applications are **anonymised, not deleted.** This is a deliberate
  policy choice — employers need a record of having received applications
  for their own compliance. The candidate's personal data is wiped, but
  the application row stays.
- If your jurisdiction requires *full* deletion (not anonymization),
  override with the `wcb_anonymize_or_delete` filter, returning
  `'delete'` instead of `'anonymize'`.

**For employers:**

1. **Employer Dashboard → Settings → Delete Account** (or admin
   manual).
2. User account: deleted.
3. Company profile: option to delete or transfer to another user.
4. Jobs: status changes to "Job Removed." All linked applications
   transition to status "Job Removed." Applicants get a notification.
5. Credit ledger: NOT deleted (financial record). If absolutely
   required by jurisdiction, admin must manually purge separately
   from the ledger table.

## Step 5 — Retention policy

Career Board doesn't enforce automatic deletion of old data — you
configure your policy:

1. **Career Board → Settings → Privacy → Retention.**
2. Options:
   - **Anonymise applications older than [N] months.** Strips PII
     from applications older than N months. Useful when applicants
     don't return for years but data has been stored. Default: off.
   - **Delete expired job alerts after [N] months of inactivity.**
     Default: 12 months.
   - **Delete IP addresses from logs after [N] days.** Default: 30.
   - **Purge unsuccessful login attempts after [N] days.** Default: 7.
3. These run on a daily cron — `wcb_privacy_retention_cron`. Schedule
   visible in WP-CLI: `wp cron event list | grep wcb_privacy`.

## Step 6 — Cookie policy

Career Board sets cookies in two places:

1. **Login cookie** — standard WP authentication. Same as any
   WordPress site.
2. **Saved job tracking** (logged-out users) — `wcb_saved_jobs`
   cookie, 90 days. Stores bookmarked job IDs so logged-out users
   keep bookmarks. Encrypted but not personally identifying.

Add to your cookie banner:

```
"wcb_saved_jobs" — saved job bookmarks for logged-out users
```

If you don't want this cookie, disable in **Settings → Privacy →
Cookies → "Allow logged-out saved jobs"** — turning this off means
logged-out bookmarks are not preserved.

## Step 7 — AI features and data exposure (Pro)

If you have Pro AI enabled, additional disclosure is needed:

1. **Update privacy policy** with a section like:
   > "We use AI to power [list of features]. When you submit data
   > (resume, profile, application), it may be sent to our AI provider
   > ([OpenAI / Anthropic Claude / Ollama]). Their data handling is
   > governed by [provider URL]."

2. **Per-feature opt-out (optional, custom).** Add a checkbox on the
   apply form: "Allow AI-powered scoring of this application." Hook
   `wcb_application_pre_save` and skip AI ranking if the checkbox is
   unchecked.

3. **Use Ollama for sensitive data.** If you can't share resume /
   application content with a US LLM (HIPAA, sensitive sectors), run
   Ollama on your server. See
   [../ai-features/02-setup-and-providers.md](../ai-features/02-setup-and-providers.md).

## Step 8 — Data Processing Agreement (DPA)

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

## Step 9 — Breach response checklist

If you suspect a breach (unauthorised access, data leak):

1. **Identify scope.** Which tables / files / accounts were exposed?
2. **Contain.** Force-rotate all admin / employer passwords. Disable
   the affected user accounts if compromised.
3. **Notify affected users within 72 hours** (GDPR requirement).
   Career Board's user list is exportable from
   **WP Admin → Users → Export.**
4. **Notify authorities** if the breach meets the threshold for your
   jurisdiction (each EU state's DPA, ICO in the UK, etc.).
5. **Patch.** Fix the underlying cause. Common causes: outdated
   plugin, weak admin password, compromised host.
6. **Document.** Keep records of what happened, what you did, what
   was disclosed — for regulator audits.

## Common compliance mistakes

- **No consent checkbox at registration.** Easy to forget; required
  for GDPR. Career Board ships it on by default — make sure it's not
  been disabled.
- **Auto-publishing applications to BP activity stream.** If you have
  BP activity for "applied for job" turned on, candidates may not
  realise their job search is public. Update consent text accordingly.
- **Storing IPs forever.** WordPress core stores login IPs in the
  audit log indefinitely unless you configure retention. Set a
  retention period (recommend 30 days).
- **AI providers not disclosed.** Adding Pro AI without updating the
  privacy policy is a quick way to be non-compliant. Always update
  the policy AND notify existing users of the change.
- **Half-deletion.** Deleting a user but not their applications, or
  vice versa. Use the dashboard's Delete Account flow (or WP's
  privacy eraser) — don't manually delete from the database.

## Where to go next

- [../admin-guide/04-gdpr.md](../admin-guide/04-gdpr.md) — full
  GDPR-specific admin reference.
- [01-first-day-as-site-owner.md](01-first-day-as-site-owner.md) —
  if you're still in setup mode, make sure privacy is configured
  from day one.
- [../ai-features/02-setup-and-providers.md](../ai-features/02-setup-and-providers.md) —
  data flow details per AI provider.
