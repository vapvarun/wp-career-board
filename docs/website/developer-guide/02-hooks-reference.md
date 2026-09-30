# Hooks Reference - Actions and Filters

WP Career Board fires **181 unique `wcb_`-prefixed hooks** (60
actions and 121 filters, Free only - Pro adds its own; see the Pro
developer guide), plus 2 deprecated filters that still apply. The
count comes from every literal `do_action()` / `apply_filters()` call
site in the plugin source; dynamic hooks such as
`wcb_settings_tab_{slug}` are listed once. The most useful
integration hooks are grouped by area below. Section "New in 1.8.0"
at the end covers the rest. Signatures for anything not listed can be
read at the call site (see the last section).

> **How to use this list:** every hook is fired with `do_action()`
> or `apply_filters()` somewhere in the plugin source. A longer
> narrative for the 1.8.0 hooks, with the reasoning behind each, is in
> the plugin's `docs/HOOKS.md`.

## Lifecycle / job posting

| Hook | Type | Fires when |
|---|---|---|
| `wcb_pre_job_submit` | Filter | Before a job is created. Return `WP_Error` to abort. |
| `wcb_before_create_job` | Filter | Modify the `wp_insert_post` arg array before creation. |
| `wcb_job_created` | Action | After a job is inserted. Args: `$job_id, $request`. |
| `wcb_before_update_job` | Filter | Modify the update arg array before save. |
| `wcb_job_updated` | Action | After a job update completes. Args: `$job_id, $request`. |
| `wcb_before_delete_job` | Filter | Return false to abort the delete. |
| `wcb_job_deleted` | Action | After job is removed. Args: `$job_id`. |
| `wcb_job_republished` | Action | When an expired or closed job is republished. Args: `$job_id, $previous_status`. |
| `wcb_job_approved` | Action | When admin approves a pending job. Args: `$job_id`. |
| `wcb_job_rejected` | Action | When admin rejects a job. Args: `$job_id, $reason`. |
| `wcb_job_expired` | Action | When a job passes its deadline during the hourly sweep (or `wp wcb job expire`). Args: `$job_id`. |
| `wcb_check_job_expiry` | Action | Cron schedule hook - the hourly WP-Cron event that runs the job-expiry sweep. |
| `wcb_deadline_reminder` | Action | Fires once per candidate while sending a deadline reminder. Args: `$user_id, $job_id, $days_left`. (The cron schedule hook that drives this is `wcb_send_deadline_reminders`.) |
| `wcb_featured_expired` | Action | When a featured job's promotion window ends. Args: `$job_id`. Fires right after `wcb_job_featured_expired`, which is the same event added in 1.8.0 (driven by the `wcb_expire_featured_jobs` daily cron event). |

## Moderation / report a job

The Report-a-Job flow (any logged-in user can flag a listing; a
moderator dismisses or unpublishes it) fires these:

| Hook | Type | Fires when |
|---|---|---|
| `wcb_job_reported` | Action | A logged-in user reports a job (deduped per user). Args: `$job_id, $reason, $user_id`. |
| `wcb_job_flag_resolved` | Action | A moderator resolves a job's flags (dismiss or unpublish). Args: `$job_id, $action`. |
| `wcb_moderate_jobs_ability_check` | Filter | Return a bool to override the moderation permission check. |

## Moderation / member safety (1.7.0)

The member report/block surface backing `MembersEndpoint`
(`POST /users/{id}/report`, `POST`/`DELETE /users/{id}/block`) and
the admin Candidates screen's suspend bulk action - see
[03-rest-api.md](03-rest-api.md).

| Hook | Type | Fires when |
|---|---|---|
| `wcb_member_reported` | Action | A member reports another member (deduped per reporter). Args: `$target_user_id, $reason, $reporter_user_id`. |
| `wcb_member_blocked` | Action | A member blocks another member. Args: `$blocker_user_id, $blocked_user_id`. |
| `wcb_member_unblocked` | Action | A member unblocks another member. Args: `$blocker_user_id, $unblocked_user_id`. |
| `wcb_member_suspended` | Action | An admin suspends a member from the Candidates admin screen bulk action. Args: `$user_id`. |
| `wcb_member_unsuspended` | Action | An admin lifts a member suspension. Args: `$user_id`. |

## Account deletion (1.7.0)

Self-service account deletion (`AccountDeletionEndpoint` /
`AccountDeletionService`) - see [03-rest-api.md](03-rest-api.md).

| Hook | Type | Fires when |
|---|---|---|
| `wcb_account_deletion_requested` | Action | A member schedules deletion of their own account (grace period > 0). Args: `$user_id, $scheduled_timestamp`. |
| `wcb_account_deletion_cancelled` | Action | A member cancels a pending deletion. Args: `$user_id`. |
| `wcb_account_deletion_executing` | Action | Immediately before a due deletion calls `wp_delete_user()` (daily `wcb_process_account_deletions` cron, or immediately if the grace period is 0). Args: `$user_id`. |
| `wcb_account_deletion_grace_days` | Filter | Override the grace period in days before a requested deletion is finalized. Default `14`; `0` deletes immediately. |
| `wcb_account_deletion_password_required` | Filter | Return `false` to skip the password re-check (e.g. for SSO/passwordless accounts). Args: `$required, $user_id`. Default `true`. |

## Lifecycle / applications

| Hook | Type | Fires when |
|---|---|---|
| `wcb_pre_application_submit` | Filter | Before an application is created. Return `WP_Error` to abort (custom anti-spam, eligibility checks, etc.). |
| `wcb_before_create_application` | Filter | Modify `wp_insert_post` arg array. |
| `wcb_application_submitted` | Action | After successful submit. Args: `$app_id, $job_id, $candidate_id`. |
| `wcb_application_status_changed` | Action | Once per real status change (`submitted -> reviewing -> shortlisted -> rejected/hired/withdrawn/job_removed`); a save that keeps the same status fires nothing. Args: `$app_id, $old_status, $new_status, $reason, $actor`. |
| `wcb_application_status_updated` | Action | Every status change, including silent ones (job close / reopen, migrations) that skip `wcb_application_status_changed`. For keeping data in step with the status; never send a message from it. Args: `$app_id, $old_status, $new_status, $reason`. |
| `wcb_application_status_label` | Filter | The words shown for a status. Args: `$label, $status, $audience` (`candidate`, `employer`, `admin`). Candidates see "Not selected" where employers see "Rejected". |
| `wcb_application_withdrawn` | Action | Candidate withdrew; the application stays with status `withdrawn` and the employer is emailed. Args: `$app_id, $job_id, $candidate_id`. |
| `wcb_application_deleted` | Action | Before an application post is permanently deleted (admin delete, account erasure, removing a row whose job is gone). Args: `$app_id, $job_id`. |
| `wcb_application_form_fields` | Action | Inside the apply form template - render extra `<input>`s here. |
| `wcb_application_form_fields_groups` | Filter | Add a group of custom fields to the apply form. |
| `wcb_guest_applications_claimed` | Action | After a newly-registered user's prior guest applications (matched by email, `post_author=0` + `_wcb_guest_email`) are reassigned to their new account. Args: `$user_id, $claimed_application_ids`. |

## Lifecycle / candidates and employers

| Hook | Type | Fires when |
|---|---|---|
| `wcb_candidate_registered` | Action | After a candidate signup completes. Args: `$user_id` (one argument). |
| `wcb_employer_registered` | Action | After an employer signup completes. Args: `$user_id, $company_id`. |
| `wcb_employer_banned` | Action | After an admin bans an employer from the admin Employers screen. Args: `$user_id`. |
| `wcb_employer_unbanned` | Action | After an admin lifts an employer ban. Args: `$user_id`. |
| `wcb_candidate_form_fields` | Filter | Add fields to the candidate registration form. |
| `wcb_company_form_fields` | Filter | Add fields to the company-profile edit form. |

## Credits and pricing

| Hook | Type | Fires when |
|---|---|---|
| `wcb_credits_enabled` | Filter | Return true if Pro credits are active. |
| `wcb_employer_credit_balance` | Filter | Return the current user's credit balance (Pro routes to SDK). |
| `wcb_credit_purchase_url` | Filter | URL the "Buy Credits" button points at. |
| `wcb_credit_low_threshold` | Filter | Balance below this triggers the low-credits banner. |
| `wcb_board_credit_cost` | Filter | Credits required to post to a board. Args: `$cost, $board_id`. |
| `wcb_job_republish_credit_cost` | Filter | Cost to republish an expired job. |

All credit filters above are fired by Free (so blocks and the
employer dashboard have a consistent surface) but only return
meaningful values when Pro is active. In Free they default to
"credits disabled" / empty URL / zero balance.

## Active job limit (free tier quota)

| Hook | Type | Fires when |
|---|---|---|
| `wcb_employer_active_job_limit` | Filter | Max concurrently active jobs per employer; **0 = unlimited (default)**. Args: `$limit, $user_id, $request`. |
| `wcb_employer_active_job_statuses` | Filter | Post statuses that occupy a slot. Default `array( 'publish' )`. Args: `$statuses, $user_id`. |
| `wcb_employer_active_job_limit_message` | Filter | Copy shown when the cap blocks a post. Args: `$message, $limit, $count`. |

Cap the number of live listings one employer may hold at once - the
usual shape for a site that gives away a few free posts and sells
volume on top:

```php
add_filter( 'wcb_employer_active_job_limit', static fn(): int => 5 );

add_filter(
    'wcb_employer_active_job_limit_message',
    static function ( string $message, int $limit ): string {
        return sprintf( 'Free plan allows %d live jobs. Close one or use credits.', $limit );
    },
    10,
    2
);
```

Checked in `POST /wcb/v1/jobs` and again in `PUT /wcb/v1/jobs/{id}`
when a listing flips back to `publish` (the job being republished is
excluded from its own count, so reopening while under the cap of your
*other* live jobs is allowed). Blocked requests return HTTP **403**
with code `wcb_active_job_limit`; `data.limit` and `data.count` carry
the numbers, and the job form surfaces `message` directly.

**The cap is skipped entirely when `wcb_credits_enabled` returns
true.** Paid posting already meters volume - charging an employer a
credit and then refusing the post would be the worst of both models.
The quota is the free-board mechanism; credits replace it.

## REST response shaping

The `wcb_rest_prepare_*` family is your hook into every REST
response. Each fires after the controller builds the row and
before it's returned - modify, redact, or augment.

| Hook | Adjusts the response for | Filter args |
|---|---|---|
| `wcb_rest_prepare_job` | Single job + collection items | `$data, $post, null` |
| `wcb_rest_prepare_application` | Single application + lists | `$data, $post, $request, $viewer_role` |
| `wcb_rest_prepare_candidate` | Candidate profile | `$data, $user, null` |
| `wcb_rest_prepare_company` | Company profile | `$data, $post, null` |
| `wcb_job_response` | Legacy alias fired alongside `wcb_rest_prepare_job` for back-compat | `$data, $post` |

Notes:

- For `wcb_rest_prepare_candidate` the second argument is a
  `WP_User`, not a `WP_Post`.
- The third argument is a `WP_REST_Request` placeholder and is
  `null` on the job/candidate/company filters - do not depend on
  it there.
- `wcb_rest_prepare_application`'s fourth argument is the viewer
  role string (`candidate` or `employer`), letting you redact
  fields per audience. There is no generic `single`/`collection`/
  `embed` context argument.

Pro adds: `wcb_rest_prepare_board`, `wcb_rest_prepare_board_stage`,
`wcb_rest_prepare_notification`, `wcb_rest_prepare_resume`.

## Block + shortcode extension

| Hook | Type | Use it to |
|---|---|---|
| `wcb_module_renders` | Filter | Pass an array of dashboard module IDs to control which modules the employer/candidate dashboards render. |
| `wcb_job_form_fields` | Filter | Add field groups to the job form. |
| `wcb_job_form_step1_fields` | Action | Inject extra fields into the 4-step wizard's step 1. Args: `$attributes`. Companion actions: `wcb_job_form_step2_fields`, `wcb_job_form_step3_fields`, and `wcb_job_form_step4_preview`. |
| `wcb_job_form_simple_extra_fields` | Action | Inject extra fields into the single-page job form. Args: `$attributes`. |
| `wcb_application_form_fields` | Action | Inject extra fields into the apply panel. Args: `$job_id`. |
| `wcb_application_form_fields_groups` | Filter | Register grouped custom apply-form fields that the apply endpoint will persist as `_wcb_application_field_<key>`. Args: `$groups, $job_id`. |
| `wcb_job_listing_data` | Filter | Per-card data in the listings block. Args: `$card, $post`. |
| `wcb_job_listings_board_options` | Filter | The boards shown in the listings UI's board picker. |
| `wcb_job_listings_query_args` | Filter | Modify the `WP_Query` args for the listings server-side query. |
| `wcb_job_listings_api_base` | Filter | Override the REST base the listings block fetches from. |
| `wcb_default_filter_order` | Filter | Reorder or add to the listings block's filter groups. Default `['type', 'experience', 'category', 'tags', 'location', 'board', 'salary']`; a saved-per-block `filterOrder` attribute is then intersected against this list, so a group not present here can never be shown. Since 1.6.0. |
| `wcb_job_listings_filters_top` | Action | Inside the listings block template, immediately above the filter bar. No args. |
| `wcb_job_listings_filters_bottom` | Action | Inside the listings block template, immediately below the filter bar. No args. |
| `wcb_before_card_footer` | Action | Inside each job card, before the footer row. Args: `$job_card, $job_post`. |
| `wcb_after_card_footer` | Action | Inside each job card, after the footer row. Args: `$job_card, $job_post`. |
| `wcb_job_form_initial_state` | Filter | Add keys to the 4-step job form's Interactivity API initial state. Extend `view.js` to read the new key. Args: `$state, $attributes`. |
| `wcb_job_form_simple_initial_state` | Filter | Same, for the single-page job form. Args: `$state, $attributes`. |
| `wcb_candidate_resumes_state` | Filter | Inject `maxResumes`/`resumeCount` (or other resume-cap fields) into the candidate dashboard's Interactivity state. Pro uses this for resume-archive cap enforcement. Args: `$state, $candidate_user_id`. |
| `wcb_company_sidebar_before` | Action | Company-profile block, before the sidebar renders. Args: `$company_id`. |
| `wcb_company_sidebar_after` | Action | Company-profile block, after the sidebar renders. Args: `$company_id`. |
| `wcb_company_sidebar_blocks` | Filter | Add or remove sidebar block IDs shown on the company-profile page. Args: `$blocks, $company_id`. Default since 1.8.0: Similar Companies and Job Alerts (the site-wide Recent Jobs block was removed because it listed other companies' jobs). |
| `wcb_job_expiring_soon` | Action | Once per deadline, 3 days before a job ends, for its employer. Args: `$job_id, $days_left`. |
| `wcb_save_custom_field` | Filter | Sanitize a custom profile/form field before it is persisted. Args: `$value, $key, $owner_id`. |
| `wcb_shortcode_attr_aliases` | Filter | Add to the camelCase to lowercase attribute map (so `[wcb_job_listings boardId="1"]` works). |
| `wcb_search_active_shortcodes` | Filter | Tag/prefix names for the body-class detector. Extend if you register custom shortcodes that should also force the `wcb-page` body class. |
| `wcb_page_needs_frontend_assets` | Filter | Force-load (or skip) the Career Board frontend bundle on a given request. |
| `wcb_jobs_collection_params` | Filter | Modify the `get_collection_params()` schema (query args) accepted by `GET /jobs`. |

## Frontend JS events (browser `CustomEvent`, not PHP hooks)

Two blocks coordinate through `document`-level `CustomEvent`s so
listeners (including Pro's job-map block) can react without a shared
Interactivity store:

| Event | Dispatched by | Detail | Fires when |
|---|---|---|---|
| `wcb:search` | `job-search` and `job-filters` blocks | `{ query, filters }` | The visitor changes the search query or a filter, after the URL is updated with `history.pushState`. |
| `wcb:results` | `job-listings` block | `{ jobIds: number[], total: number }` | Since 1.6.0. After a listings fetch resolves, so a listener can sync to the *actually-visible* job set - `wcb:search` alone only carries the query/filters, not which jobs matched. |

```js
document.addEventListener( 'wcb:results', ( event ) => {
    console.log( 'Visible job IDs:', event.detail.jobIds );
} );
```

## Settings and pages

| Hook | Type | Use it to |
|---|---|---|
| `wcb_install_default_settings` | Filter | Modify the seed values on plugin install. |
| `wcb_settings_sanitize` | Filter | Sanitize a custom settings key before save. |
| `wcb_settings_tabs` | Filter | Add a tab to the Settings UI. |
| `wcb_settings_tab_<slug>` | Action | Render content for a custom tab (the slug becomes the suffix). |
| `wcb_settings_tab_antispam` | Action | The built-in Anti-Spam tab. Hook to add additional anti-spam controls. |
| `wcb_settings_tab_emails` | Action | The Emails tab - extend with custom email templates. |
| `wcb_page_settings` | Filter | Deprecated in 1.8.0, still applied. Use `wcb_page_definitions`. |
| `wcb_app_page_ids` | Filter | Add page IDs that should get the `wcb-page` body class. |
| `wcb_apply_page_class` | Filter | Opt out a page from the `wcb-page` body class entirely. |
| `wcb_container_max_width` | Filter | Override the 1200px content-column default. |
| `wcb_registered_emails` | Filter | Add a new transactional email type to the plugin's email registry. |
| `wcb_email_template_dirs` | Filter | Add a directory Career Board searches for `{slug}.php` email template overrides (theme override still wins first). |
| `wcb_fullwidth_block_names` | Filter | Add a block name (`namespace/slug`) to the list that triggers the full-width page template + body class. Pro adds its resume-archive/recruiter-search blocks here. |
| `wcb_candidate_requires_role` | Filter | Return `true` to require the explicit `wcb_candidate` role/cap before a logged-in user can apply (default follows the `candidate_requires_role` setting, off). |
| `wcb_bot_ua_pattern` | Filter | Override the PCRE pattern (no delimiters) used to detect bot User-Agents for anti-spam/analytics purposes. |
| `wcb_job_views_retention_days` | Filter | Override how many days of `wcb_job_views` analytics rows are kept before the daily prune deletes them. Default `90`, floored at `30`. Since 1.2.9. |
| `wcb_min_app_version` | Filter | Set the minimum mobile-app version the `settings/app-config` endpoint reports, letting the app force an upgrade. Default `"1.0.0"`. Since 1.7.0. |
| `wcb_app_enabled` | Filter | Return `true`/`false` to override whether `settings/app-config` reports the mobile app as enabled for this site. Default: whether Pro is active. Since 1.7.0. |

## Setup wizard

| Hook | Type | Use it to |
|---|---|---|
| `wcb_wizard_steps` | Filter | Add a step to the setup wizard. Each entry: `title`, `template` (absolute path), `button_text`, keyed by a unique slug. |
| `wcb_wizard_required_pages` | Filter | Deprecated in 1.8.0, still applied. Use `wcb_page_definitions`. |
| `wcb_wizard_completed` | Action | After the wizard's last step. |
| `wcb_wizard_force_render` | Filter | Force the wizard to render even when `is_setup_complete()` is true. |
| `wcb_wizard_complete_redirect` | Filter | Override the URL the wizard redirects to on finish. |
| `wcb_companions` | Filter | Add an entry (label, description, why) to the companion-plugin catalog shown by the installer and admin screen. Since 1.4.6. |

## Pro-coordination filters (Free side)

These let Free check whether Pro is active and gate behavior. Pro
hooks them to return true / version / license status.

| Hook | Returns |
|---|---|
| `wcb_pro_active` | bool - is Pro plugin running? |
| `wcb_pro_licensed` | bool - is the Pro license valid? |
| `wcb_pro_version` | string - Pro version, e.g. "1.8.0" |
| `wcb_pro_ai_enabled` | bool - is the Pro AI bundle enabled? |
| `wcb_pro_upsell_url` | string - where the "Upgrade to Pro" CTA points |
| `wcb_pro_settings_saved_notice` | Filter - message for the post-save admin notice |

### AI feature-availability filters

Free fires these so blocks can render the right call-to-action;
Pro returns true when the corresponding AI feature is licensed and
enabled. In Free they default to `false`.

| Hook | Returns |
|---|---|
| `wcb_ai_description_enabled` | bool - show the AI job-description helper in the job forms. |
| `wcb_ai_matching_available` | bool - candidate dashboard shows AI job matching. |
| `wcb_ai_ranking_available` | bool - employer dashboard shows AI applicant ranking. |
| `wcb_ai_completion_available` | bool - job-single page offers the AI cover-letter / completion helper. |
| `wcb_pro_alerts_enabled` | bool - Pro job-alerts feature is active. |
| `wcb_pro_resumes_enabled` | bool - Pro resume directory is active. |

## Miscellaneous

| Hook | Type | Use it to |
|---|---|---|
| `wcb_industries` | Filter | Add or rename industry categories used by the company profile. |
| `wcb_currency_catalog` | Filter | Add a currency to the salary-currency dropdown. |
| `wcb_board_currency` | Filter | Override per-board currency (Pro typically). |
| `wcb_board_options_for_employer` | Filter | Modify the boards dropdown shown to an employer (Pro filters to user-accessible groups). |
| `wcb_job_board_id` | Filter | Resolve which board a job belongs to. |
| `wcb_job_default_status` | Filter | Initial status on submission. |
| `wcb_job_default_expiry_days` | Filter | Default job-expiry window. |
| `wcb_jobs_post_filter` | Filter | After the listings query but before render - add transformations. |
| `wcb_jobs_allowed_meta_filters` | Filter | Allowlist of meta keys the `metaFilter` block attribute may query (prevents arbitrary-meta probes). |
| `wcb_resume_pdf_attachment_id` | Filter | Resolve the attachment ID used as a candidate's resume PDF. |
| `wcb_theme_accent_primary` | Filter | Primary accent color used by blocks. Args: `$accent, $template`. Driven by the theme accent bridge (`core/class-theme-accent-bridge.php`). |
| `wcb_admin_email_log_response` | Filter | Modify the email-log REST response. |
| `wcb_cli_abilities` | Filter | Map WP-CLI runs to ability slugs for permission checks. |
| `wcb_import_extra_cards` | Action | Add cards to the Import admin page. |
| `wcb_rest_app_config` | Filter | Frontend boot config shipped to the Interactivity API and the mobile/companion app - see the app-config route in [03-rest-api.md](03-rest-api.md). |
| `wcb_sample_data_installed` | Action | After the setup wizard seeds sample content. |
| `wcb_sample_data_removed` | Action | After sample content is removed (wizard or uninstall). |
| `wcb_notification_created` | Action | Fires after a notification is created so a centralised notification center (e.g. BuddyNext) can mirror it without re-deriving the message. Free has no in-app bell, so it fires once per real email send (admin test sends are skipped). Single arg: an array `{ user_id, event_type, message, link, id }` - `message` is the rendered email subject, `link` is a best-effort deep link, `id` is `0` on Free. Pro fires the same hook from its notification bell with the inserted row id. Since 1.8.0 each event fires it once: when Pro's bell records an event (status changed, application received, job approved/rejected/expired, featured expired) the bell fires it and the email does not. |
| `wcb_email_announces_notification` | Filter | Whether an email fires `wcb_notification_created`. Args: `$announce, $email_id, $user_id`. Return false when your own channel records the event and fires the signal itself, so listeners (push, BuddyNext) get it once. |

## New in 1.8.0

### Settings, pages and setup

| Hook | Type | Args | Purpose |
|---|---|---|---|
| `wcb_settings_schema` | Filter | `$fields` | Add setting keys: `$fields['my_key'] = array( 'default' => false, 'sanitize' => 'rest_sanitize_boolean' )`. A key outside the schema is never written by a settings form. |
| `wcb_settings_tab_groups` | Filter | `$groups` | Change the Settings sidebar groups: group key => `label` and `tabs` (slugs, in order). A tab not listed lands in "Site". |
| `wcb_page_definitions` | Filter | `$defs` | Add a page: `title`, `slug`, `content` (block markup), `label`, `desc`, optional `aliases`. Register the key in `wcb_settings_schema` too. `wcb_wizard_required_pages` and `wcb_page_settings` are deprecated and still applied. |
| `wcb_safer_defaults_notice` | Filter | `$items` | The list shown once to owners of sites that predate the 1.8.0 defaults. |
| `wcb_apply_ai_notice` | Filter | `$text, $job_id` | Notice above **Submit Application** when applications may be screened with AI. Empty shows nothing. |
| `wcb_template_override_docs_url` | Filter | `$url` | The "Learn how template overrides work" link in Site Health. |
| `wcb_overridable_templates` | Filter | `$templates` | Add a theme-overridable template to the Site Health version check. See [Template Overrides](./06-template-overrides.md). |

### Jobs, payment and search

| Hook | Type | Args | Purpose |
|---|---|---|---|
| `wcb_job_payment` | Filter | `$paid, $job_id, $event` | Return `true` when the job is paid or free, or a `WP_Error` (402) when not. `$event` is `create`, `resubmit`, `republish`, `board_change` or `feature`. Pro's credits answer it. |
| `wcb_featured_upgrade_cost` | Filter | `$cost` | What featuring a job costs. 0 hides the job-form checkbox and the My Jobs **Feature** action. |
| `wcb_job_featured_expired` | Action | `$job_id` | A job's featured period ended. |
| `wcb_job_allow_new_terms` | Filter | `$allow, $request` | Whether a submission may create new category, type, location or experience terms. Default: moderators only. Tags are always free-form. |
| `wcb_job_search_args` | Filter | `$args, $params` | Change the `WP_Query` args of every job search (REST, the listing's first paint, alerts). Put every result-changing value in `$args`: the REST cache key is built from them. |
| `wcb_job_deadline_passed` | Filter | `$passed, $job_id, $deadline` | Keep applications open past the stored deadline, or close them early. |
| `wcb_job_pipeline_url` | Filter | `$url, $job_id` | URL for the **Pipeline** button on a My Jobs row. Empty hides it. Pro's Application Pipeline supplies it. |
| `wcb_job_single_after_description` | Action | `$job_id` | Right after the job description on the job page. Pro prints custom-field "Additional details" here. |
| `wcb_job_posting_schema` | Filter | `$schema, $job, $data` | Change a job's Google for Jobs JobPosting, or return `[]` to leave the job out. |
| `wcb_job_imported` | Action | `$new_id` | After a job is imported from WP Job Manager, once meta and terms are written. |

Listing filter chips for custom fields toggle `meta_<key>` in the listing's `activeFilters` (`actions.toggleMetaChip` with context `{ metaKey, metaValue }`). A `meta_<key>` URL parameter is applied on first paint.

### Applications

| Hook | Type | Args | Purpose |
|---|---|---|---|
| `wcb_employer_actionable_statuses` | Filter | `$statuses` | The statuses an employer may set. Default: submitted, reviewing, shortlisted, rejected, hired. |
| `wcb_close_job_applications` | Action | `$job_id, $status` | Background batch that moves a closing job's undecided applications. Scheduled by the plugin; listen, do not fire. |

### Accounts, sign-up and files

| Hook | Type | Args | Purpose |
|---|---|---|---|
| `wcb_pre_registration` | Filter | `$error, $request` | Return a `WP_Error` to refuse a sign-up before the account exists. |
| `wcb_registration_rate_limit` | Filter | `$limit` | Sign-ups one IP may make per hour. Default 5, 0 disables. |
| `wcb_email_verification_requested` | Action | `$user_id, $verify_url` | A new account needs to confirm its email. The confirmation email listens here. |
| `wcb_employer_login_redirect_enabled` | Filter | `$enabled, $user, $redirect_to, $requested_redirect_to` | Return `false` to stop the employer-dashboard redirect after login. |
| `wcb_can_view_public_resume` | Filter | `$allowed, $post_id, $viewer_id` | Whether a viewer may open a public resume. Pro applies the owner's access setting. |
| `wcb_private_file_can_download` | Filter | `$allowed, $attachment_id, $user_id` | Grant a private resume file to another audience. |
| `wcb_personal_data_providers` | Filter | `$providers` | Register personal data once for the WordPress exporter, eraser and account deletion. Add `'key' => array( 'label' => string, 'export' => callable, 'erase' => callable )`. Both callables receive `array( 'user_id' => int, 'email' => string )` (`user_id` is 0 for a guest). `erase` returns `array( 'removed' => int, 'retained' => int, 'messages' => string[] )`. |
| `wcb_logs_pruned` | Action | `$cutoff` | After the daily prune of email history older than **Keep Email History**. `$cutoff` is UTC `Y-m-d H:i:s`. |
| `wcb_resume_rest_query_args` | Filter | `$args, $request` | Change the resume REST query. |
| `wcb_resume_sitemap_query_args` | Filter | `$args` | Change the resume sitemap query. |
| `wcb_employer_credit_has_history` | Filter | `$has_history, $employer_id` | Whether the employer has held credits before, so a zero balance shows the low-balance warning. |

### Moderation

| Hook | Type | Args | Purpose |
|---|---|---|---|
| `wcb_member_flags_resolved` | Action | `$user_id` | Reports on a member were dismissed from the Candidates or Employers list. |
| `wcb_reporter_has_standing` | Filter | `$standing, $user_id` | Whether a reporter's report counts toward the auto-hide. Default: account at least a week old, or a member with an application or a published job. |

A ban (the `_wcb_employer_banned` user meta, from any writer) hides the member's live and pending jobs, company and resume, and removing it restores them. Hidden posts carry `_wcb_hidden_by` (`ban` or `reports`) and `_wcb_hidden_status`.

### Community notification contract

`wcb_notification_created` carries a second argument, the contract payload, so a notification center (BuddyNext or another add-on) can show one row per event with the plugin's own words and link:

```php
add_action( 'wcb_notification_created', function ( array $legacy, ?array $contract ) {
    if ( null === $contract ) {
        return; // A transactional or admin-only email: no community object.
    }
    // $contract: recipient_id, type, actor_id, object_type ('job'|'application'),
    // object_id, message, url, group_key, notification_id.
}, 10, 2 );
```

Listeners registered with one accepted argument are unaffected. An email whose send call names no `object_type` (email verification, admin alerts) gets `null`.

| Hook | Type | Args | Purpose |
|---|---|---|---|
| `wcb_community_notification_types` | Filter | `$types` | Declares every type Free's emails can fire: `slug => array( label, description, default_on )`. |
| `wcb_community_notification_visible` | Filter | `$visible, $viewer_id, $targets` | Whether the viewer may still see a row about a `job` or `application`. Hidden once trashed, and for the employer once the candidate withdraws. |
| `wcb_community_notification_removed` | Action | `$object_type, $object_id` | A job or application was permanently deleted. Not fired on trash. |
| `wcb_email_announces_notification` | Filter | `$announce, $email_id, $user_id` | Return `false` when an add-on records the same event itself, so listeners never get it twice. |

### Mobile app sign-in

| Hook | Type | Args | Purpose |
|---|---|---|---|
| `wcb_app_password_login_enabled` | Filter | `$on` | Whether password sign-in is available. Backs the **App Password Sign-In** setting, off by default. |
| `wcb_app_password_max_failures` | Filter | `$max` | Failed sign-ins per bucket before lockout. Default 5. |
| `wcb_app_password_max_attempts_per_ip` | Filter | `$max` | Attempts per IP per hour. Default 20. |
| `wcb_app_password_client_ip_header` | Filter | `$header` | The `$_SERVER` key that carries the real client IP behind a proxy. Empty by default. |
| `wcb_app_credential_issued` | Action | `$user_id, $app_id, $app_name` | A member traded their password for a credential. The credential itself is never passed. |
| `wcb_app_credential_revoked` | Action | `$user_id, $uuid` | A member signed out of the app and revoked their credential. |
| `wcb_app_connect_schemes` | Filter | `$schemes` | URL schemes the app-connect flow may hand a credential to. Add only schemes for an app you ship. |
| `wcb_app_connect_bridge` | Filter | `$info` | The resolved app-connect bridge for this site: `owner`, `connect_url`, `connect_schemes`. |
| `wcb_app_scheme` | Filter | `$scheme` | The mobile app's deep-link scheme. |

### WP-CLI

| Hook | Type | Args | Purpose |
|---|---|---|---|
| `wcb_scale_budgets` | Filter | `$budgets` | Per-query time budgets (ms) for `wp wcb scale benchmark`. |
| `wcb_scale_ops` | Filter | `$ops, $per_page` | Add a named callable (`name => callable`) for the benchmark to time. Add a matching entry in `wcb_scale_budgets`; an operation without a budget is timed but never fails the run. |

## Listening pattern (example)

```php
add_action( 'wcb_job_created', function ( $job_id, $request ) {
    // Notify a Slack channel when a new job lands.
    if ( $request->get_param( 'featured' ) ) {
        my_slack_post( "Featured job posted: " . get_the_title( $job_id ) );
    }
}, 10, 2 );
```

```php
add_filter( 'wcb_rest_prepare_job', function ( $row, $post ) {
    // Add a `is_remote_friendly` flag based on a meta value.
    $row['is_remote_friendly'] = (bool) get_post_meta( $post->ID, '_remote_friendly', true );
    return $row;
}, 10, 2 );
```

## How to confirm a hook signature

The fastest way to see the actual arg signature:

```bash
grep -rn "do_action\\s*(\\s*'wcb_job_created'" wp-content/plugins/wp-career-board/
```

That returns the file:line of every firer; open it and read the
surrounding lines for the parameter shapes.

For the Pro-side hooks, see
[docs.wbcomdesigns.com/docs/wp-career-board-pro/developer-guide/03-hooks-reference](https://docs.wbcomdesigns.com/docs/wp-career-board-pro/developer-guide/03-hooks-reference/).
