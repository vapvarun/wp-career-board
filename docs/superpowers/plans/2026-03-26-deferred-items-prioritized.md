# Deferred Items — Prioritized by User Impact

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the remaining 14 deferred items from the backend audit, prioritized by what matters most to site owners and end users — not by technical severity.

**Perspective 1 — Site Owner (Admin):**
"I installed this plugin to run a job board. I need payments working, my API keys secure, features I configured to actually work, and clean removal if I switch plugins."

**Perspective 2 — End User (Employer/Candidate):**
"I posted a job / applied for a job. I want to see my rejection reasons, track application history, know how many views my job got, and get push notifications on my phone."

---

## Priority 1: Revenue & Security (Site Owner Blocker)

These prevent the site owner from making money or put their business at risk.

### Task 1: Stripe Currency Configuration
**User story:** "I run a UK job board. All my prices show in USD and I can't change it."

- [ ] Add `wcbp_currency` field to the Credits settings tab (dropdown: USD, EUR, GBP, CAD, AUD, INR, etc.)
- [ ] Read from `wcbp_currency` in StripeDriver (already reads it, just needs the UI)
- [ ] Default to USD if not set

**Files:** `admin/class-pro-admin.php` (Credits tab render + save), `admin/class-admin-credits.php`

### Task 2: API Key Encryption at Rest
**User story:** "A security audit flagged that my Stripe live key is stored in plaintext in wp_options. My hosting provider can see it."

- [ ] Create a `KeyEncryption` utility class using `wp_salt('auth')` + `openssl_encrypt/decrypt`
- [ ] Wrap `wcbp_stripe_secret_key_live`, `wcbp_stripe_secret_key_test`, `wcbp_ai_api_key`, `wcbp_webhook_secret` with encrypt-on-save, decrypt-on-read
- [ ] Backward compatible — if stored value is not encrypted (legacy), read as-is and re-encrypt on next save
- [ ] Add `[encrypted]` visual indicator in Settings UI

**Files:** New `core/class-key-encryption.php`, modify `admin/class-pro-admin.php` save handlers, modify `modules/credits/class-stripe-driver.php`, `modules/ai/class-ai-module.php`

### Task 3: License Enforcement
**User story:** "People are sharing my Pro plugin without buying a license. I need some features to degrade gracefully."

- [ ] Define which Pro features are gated (suggestion: AI matching, Stripe credits, Job Feed — leave resume builder, kanban, alerts ungated)
- [ ] Add `wcbp_is_licensed()` check before AI endpoint permission callback
- [ ] Add `wcbp_is_licensed()` check before Stripe checkout session creation
- [ ] Add `wcbp_is_licensed()` check before feed XML generation
- [ ] Show admin notice: "Activate your license to unlock AI matching, credit system, and job feeds."
- [ ] Don't break existing features — just disable premium revenue features

**Files:** `api/endpoints/class-ai-endpoint.php` (already has the check!), `modules/credits/class-stripe-driver.php`, `modules/feed/class-job-feed-module.php`, `admin/class-pro-admin.php`

---

## Priority 2: Feature Completeness (Site Owner Expectation)

These are features the site owner expects to work based on what the settings UI promises.

### Task 4: Google Maps / Mapbox Admin UI
**User story:** "I selected Google Maps as the map provider but jobs don't show on the map. There's nowhere to enter my Google Maps API key."

- [ ] Add "Map Settings" section to Integrations tab (or a new Maps tab)
- [ ] Fields: Map Provider (Leaflet/Google/Mapbox dropdown), Google API Key, Mapbox Access Token
- [ ] Save to `wcbp_map_provider` and `wcbp_map_settings`
- [ ] Show provider-specific help text ("Get your API key at console.cloud.google.com")
- [ ] Validate API key format on save

**Files:** `admin/class-pro-admin.php` (Integrations tab)

### Task 5: PWA Push Notification Setup
**User story:** "The plugin says it supports push notifications but nothing happens when I enable PWA."

- [ ] Generate VAPID key pair on first Pro activation using `sodium_crypto_sign_keypair()`
- [ ] Store in `wcbp_vapid_public_key` and `wcbp_vapid_private_key`
- [ ] Add PWA settings to Integrations tab: Enable/Disable toggle, Theme Color picker
- [ ] Save `wcbp_pwa_theme_color`
- [ ] Only generate keys once — check if already exists

**Files:** `core/class-pro-install.php` (activation hook), `modules/pwa/class-pwa-module.php`, `admin/class-pro-admin.php`

### Task 6: Credit Ledger Atomicity
**User story:** "A server crash during job approval left an employer with extra credits they didn't pay for."

- [ ] Wrap `deduct_on_approval()` in `$wpdb->query('START TRANSACTION')` / `COMMIT` / `ROLLBACK`
- [ ] Same for `cancel_hold()` which does a DELETE
- [ ] Add error handling — if any query fails, rollback and return WP_Error

**Files:** `modules/credits/class-credits-module.php`

---

## Priority 3: End User Visibility (Candidate/Employer Value)

These surface data that's already being collected but is invisible to users.

### Task 7: Expose Job View Counts to Employers
**User story:** "I posted 5 jobs but I have no idea which ones are getting the most views."

- [ ] Read from `wcb_job_views` table (already collected by Free plugin)
- [ ] Show view count on employer dashboard "My Jobs" tab — next to applicant count
- [ ] Show in admin Jobs list table as a column
- [ ] REST API: include `view_count` in job response

**Files:** Free `api/endpoints/class-jobs-endpoint.php`, `blocks/employer-dashboard/render.php`, `admin/class-admin-jobs.php`

### Task 8: Expose Application Status History
**User story:** "The employer changed my application status 3 times but I only see the current status. I want to see the history."

- [ ] `_wcb_status_log` is already written on every status change — just needs a reader
- [ ] Add status timeline to application detail view (candidate dashboard)
- [ ] Show in admin application metabox
- [ ] REST API: include `status_history` in application response

**Files:** Free `api/endpoints/class-applications-endpoint.php`, `blocks/candidate-dashboard/view.js`

### Task 9: Show Rejection Reason to Employers
**User story:** "I rejected a job submission with a reason but I can't see what reason I gave when I look at the job later."

- [ ] `_wcb_rejection_reason` is already written — just needs display
- [ ] Show in admin Jobs list for rejected jobs (hover tooltip or column)
- [ ] Show in employer dashboard "My Jobs" tab for rejected jobs
- [ ] Include in the rejection email (already done via action hook)

**Files:** Free `admin/class-admin-jobs.php`, `blocks/employer-dashboard/render.php`

### Task 10: Resume Attachment Access in Applications
**User story:** "A candidate uploaded their resume when applying but I can't download it from the applications list."

- [ ] `_wcb_resume_attachment_id` is already written — just needs a download link
- [ ] Add "Download Resume" link in employer dashboard application detail
- [ ] Add in admin Applications list as a column or row action
- [ ] Generate download URL via `wp_get_attachment_url()`

**Files:** Free `api/endpoints/class-applications-endpoint.php`, `blocks/employer-dashboard/render.php`, `admin/class-admin-applications.php`

---

## Priority 4: Data Hygiene (Admin Expectations)

### Task 11: Expose Notification Log
**User story:** "I want to see what emails were sent by the plugin for audit/support purposes."

- [ ] Add "Email Log" admin page under Career Board menu
- [ ] Query `wcb_notifications_log` table — show sender, recipient, subject, date, status
- [ ] Paginated table with search
- [ ] Optional: add "Resend" action for failed emails

**Files:** New `admin/class-admin-email-log.php`, Free `core/class-plugin.php` (register menu)

### Task 12: GDPR Log Viewer
**User story:** "A user requested data export. I need to see what personal data actions were taken."

- [ ] Add "Privacy Log" tab to Settings or Tools
- [ ] Query `wcb_gdpr_log` table — show user, action type, date, details
- [ ] Already written by `GdprModule::log_action()`

**Files:** New `admin/class-admin-gdpr-log.php` or add tab to existing admin

### Task 13: Preferred Currency Pre-population
**User story:** "Every time I post a new job, I have to select USD as the currency again."

- [ ] `_wcb_preferred_currency` is already written — just not read back
- [ ] Pre-populate the job form currency dropdown from user's preferred currency
- [ ] In `blocks/job-form/render.php`, read `_wcb_preferred_currency` and pass to state

**Files:** Free `blocks/job-form/render.php`

### Task 14: Board-Scoped Moderation (Future Feature)
**User story:** "I have multiple boards (Tech Jobs, Marketing Jobs). I want a moderator for each board, not one moderator for everything."

- [ ] Implement `_wcb_assigned_boards` usermeta (currently documented but not implemented)
- [ ] On moderation permission check, verify the moderator is assigned to the job's board
- [ ] Admin UI: board assignment on user profile
- [ ] This is a NEW FEATURE, not a bug fix — schedule for v1.1

**Files:** Free `core/class-abilities.php`, `modules/moderation/class-moderation-module.php`

---

## Execution Order

| Phase | Tasks | Effort | Impact |
|-------|-------|--------|--------|
| **Phase 1: Revenue** | Tasks 1-3 | 1 day | Site owner can charge money, keys are secure, license enforced |
| **Phase 2: Features** | Tasks 4-6 | 1 day | Maps work, push works, credits are safe |
| **Phase 3: User Value** | Tasks 7-10 | 1 day | Employers see views/history, candidates see rejection reasons, resume downloads work |
| **Phase 4: Hygiene** | Tasks 11-14 | 1 day | Email log, GDPR log, UX polish, future feature |

Total: ~4 days of focused development across 4 phases.
