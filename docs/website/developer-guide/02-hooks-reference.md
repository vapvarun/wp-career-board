# Hooks Reference - Actions and Filters

Use these actions and filters to change how WP Career Board behaves, react to events such as a new job or application, and add your own fields, tabs and emails. Every hook below is fired by the Free plugin, except where a row says Pro fires it. Pro adds its own hooks; see the Pro developer guide.

> **How to use this list:** each hook is fired with `do_action()` or `apply_filters()` in the plugin source. To see the exact arguments at the call site, run
> `grep -rn "'wcb_job_created'" wp-content/plugins/wp-career-board/` and open the match. Hooks whose name ends in `<slug>` are dynamic. The Pro-side hooks are listed in the Pro developer guide, page "Hooks Reference".

## Jobs

| Hook | Type | Fires when |
|---|---|---|
| `wcb_pre_job_submit` | Filter | Before a job is created. Args: `$error` (null), `$request`. Return a `WP_Error` to stop the request. |
| `wcb_before_create_job` | Filter | Change the `wp_insert_post` arguments before a job is created. Args: `$post_data, $request`. |
| `wcb_job_created` | Action | After a job is created. Args: `$job_id, $request`. |
| `wcb_before_update_job` | Filter | Change the update arguments before a job is saved. Return a `WP_Error` to stop the update. Args: `$data, $post, $request`. |
| `wcb_job_updated` | Action | After a job is updated. Args: `$job_id, $request`. |
| `wcb_before_delete_job` | Filter | Before a job is moved to the trash by `DELETE /jobs/{id}`. Return a `WP_Error` to stop it. Args: `$can` (true), `$post, $request`. |
| `wcb_job_deleted` | Action | After a job is moved to the trash by `DELETE /jobs/{id}`. Args: `$job_id`. |
| `wcb_job_republished` | Action | After an expired or closed job is set back to published. Args: `$job_id, $previous_status`. |
| `wcb_job_approved` | Action | When a pending job changes to published. Args: `$job_id`. |
| `wcb_job_rejected` | Action | When a moderator rejects a job (REST or WP-CLI). Args: `$job_id, $reason`. |
| `wcb_job_expired` | Action | When a job is set to expired. The hourly sweep fires it only for jobs whose deadline passed in the last 7 days; `wp wcb job expire` always fires it. Args: `$job_id`. |
| `wcb_check_job_expiry` | Action | The WP-Cron event that runs the job-expiry sweep, scheduled hourly. `wp wcb job run-expiry` fires it too. |
| `wcb_job_expiring_soon` | Action | Once per deadline, 3 days before a job ends, for its employer. Args: `$job_id, $days_left`. |
| `wcb_deadline_reminder` | Action | Once per candidate who saved a job but has not applied, 3 days and 1 day before the deadline. Args: `$user_id, $job_id, $days_left`. The cron event that drives it is `wcb_send_deadline_reminders`. |
| `wcb_job_featured_expired` | Action | A job's featured period ended. Args: `$job_id`. |
| `wcb_featured_expired` | Action | Fires right after `wcb_job_featured_expired`, for the same event. Args: `$job_id`. The daily cron event behind both is `wcb_expire_featured_jobs`. |
| `wcb_job_default_status` | Filter | The status a new job gets. Return `publish`, `pending` or `draft`; anything else is ignored. Args: `$status, $request`. |
| `wcb_job_default_expiry_days` | Filter | The listing length in days when the request has no deadline. Args: `$days, $request`. |
| `wcb_job_deadline_passed` | Filter | Keep applications open past the stored deadline, or close them early. Args: `$passed, $job_id, $deadline`. |
| `wcb_job_board_id` | Filter | Resolve which board a job belongs to. Args: `$board_id, $job_id`. |
| `wcb_job_allow_new_terms` | Filter | Whether a submission may create new category, type, location or experience terms. Default: moderators only. Tags are always free-form. Args: `$allow, $request`. |
| `wcb_job_payment` | Filter | Return `true` when the job is paid or free, or a `WP_Error` (402) when it is not. `$event` is `create`, `resubmit`, `republish`, `board_change` or `feature`. Pro's credits answer it. Args: `$paid, $job_id, $event`. |
| `wcb_job_imported` | Action | After a job is imported from WP Job Manager, once its meta and terms are written. Args: `$new_id`. |

## Job search and listings

| Hook | Type | Use it to |
|---|---|---|
| `wcb_job_search_args` | Filter | Change the `WP_Query` arguments of every job search (REST, the listing's first paint, alerts). Put every value that changes the result in `$args`, because the REST cache key is built from them. Args: `$args, $params`. |
| `wcb_jobs_post_filter` | Filter | Change the list of prepared job rows that `GET /jobs` returns, before it is cached. Args: `$jobs, $query, $request`. |
| `wcb_jobs_collection_params` | Filter | Change the query parameters `GET /jobs` accepts. |
| `wcb_jobs_allowed_meta_filters` | Filter | The meta keys the `metaFilter` block attribute may query. |
| `wcb_job_listings_query_args` | Filter | Change the `WP_Query` arguments of the listings block's first paint. |
| `wcb_job_listings_board_options` | Filter | The boards offered in the listings board filter. |
| `wcb_job_listings_api_base` | Filter | The REST base URL the listings block fetches from. |
| `wcb_job_listing_data` | Filter | Change the data of one listing card. Args: `$card, $post`. |
| `wcb_default_filter_order` | Filter | Reorder or extend the listings filter groups. Default `['type', 'experience', 'category', 'tags', 'location', 'board', 'salary']`. A block's saved `filterOrder` is intersected with this list, so a group missing here is never shown. |
| `wcb_job_listings_filters_top` | Action | Top of the listings filter panel. No arguments. |
| `wcb_job_listings_filters_bottom` | Action | Bottom of the listings filter panel. No arguments. |
| `wcb_before_card_footer` | Action | Inside each job card, before the footer row. Args: `$job_card, $job_post`. |
| `wcb_after_card_footer` | Action | Inside each job card, after the footer row. Args: `$job_card, $job_post`. |
| `wcb_job_single_after_description` | Action | Right after the description on the job page. Args: `$job_id`. Pro prints its custom-field details here. |
| `wcb_job_posting_schema` | Filter | Change a job's Google for Jobs `JobPosting` data, or return `[]` to leave the job out. Args: `$schema, $job, $data`. |

Listing filter chips for custom fields toggle `meta_<key>` in the listing's `activeFilters` (`actions.toggleMetaChip` with context `{ metaKey, metaValue }`). A `meta_<key>` URL parameter is applied on the first paint.

## Job form

| Hook | Type | Use it to |
|---|---|---|
| `wcb_job_form_fields` | Filter | Add field groups to the job form. Fields you define here are saved with the job. Args: `$groups, $board_id`. |
| `wcb_job_form_step1_fields` | Action | Print markup in step 1 of the 4-step job form. Args: `$attributes`. The same applies to `wcb_job_form_step2_fields`, `wcb_job_form_step3_fields` and `wcb_job_form_step4_preview`. |
| `wcb_job_form_simple_extra_fields` | Action | Print markup in the single-page job form. Args: `$attributes`. |
| `wcb_job_form_initial_state` | Filter | Add keys to the 4-step form's Interactivity API initial state. Args: `$state, $attributes`. |
| `wcb_job_form_simple_initial_state` | Filter | The same for the single-page form. Args: `$state, $attributes`. |

Markup printed by the step and extra-field actions is not collected when the form is submitted. Use `wcb_job_form_fields` for fields that must be saved.

## Moderation and reporting

| Hook | Type | Fires when |
|---|---|---|
| `wcb_job_reported` | Action | A logged-in user reports a job. A second report from the same user is ignored. Args: `$job_id, $reason, $user_id`. |
| `wcb_job_flag_resolved` | Action | A moderator dismisses or unpublishes a reported job. Args: `$job_id, $action` (`dismiss` or `unpublish`). |
| `wcb_moderate_jobs_ability_check` | Filter | Grant moderation for one job to a user who lacks the ability. Return `true` to allow. Args: `$allowed` (false), `$job_id`. |
| `wcb_reporter_has_standing` | Filter | Whether a reporter's report counts toward the auto-hide. Default: the account is at least a week old, or the member has an application or a published job. Args: `$standing, $user_id`. |
| `wcb_member_reported` | Action | A member reports another member. A second report from the same reporter is ignored. Args: `$target_user_id, $reason, $reporter_user_id`. |
| `wcb_member_blocked` | Action | A member blocks another member. Args: `$blocker_user_id, $blocked_user_id`. |
| `wcb_member_unblocked` | Action | A member unblocks another member. Args: `$blocker_user_id, $unblocked_user_id`. |
| `wcb_member_flags_resolved` | Action | Reports on a member were dismissed from the Candidates or Employers list. Args: `$user_id`. |
| `wcb_member_suspended` | Action | An admin suspends a member with the Candidates list bulk action. Args: `$user_id`. |
| `wcb_member_unsuspended` | Action | An admin lifts a suspension from the Candidates list. Args: `$user_id`. |
| `wcb_employer_banned` | Action | An admin bans an employer from the Employers list. Args: `$user_id`. |
| `wcb_employer_unbanned` | Action | An admin lifts an employer ban. Args: `$user_id`. |

A ban (the `_wcb_employer_banned` user meta, from any writer) hides the member's public jobs, company page and resumes, and removing it restores them. Hidden posts carry `_wcb_hidden_by` (`ban` or `reports`) and `_wcb_hidden_status`.

## Account deletion

| Hook | Type | Fires when |
|---|---|---|
| `wcb_account_deletion_requested` | Action | A member schedules deletion of their own account. Args: `$user_id, $scheduled_timestamp`. |
| `wcb_account_deletion_cancelled` | Action | A member cancels a pending deletion. Args: `$user_id`. |
| `wcb_account_deletion_executing` | Action | Immediately before `wp_delete_user()` runs, from the daily `wcb_process_account_deletions` cron or at once when the grace period is 0. Args: `$user_id`. |
| `wcb_account_deletion_grace_days` | Filter | Days between a deletion request and the deletion. Default `14`; `0` deletes at once. |
| `wcb_account_deletion_password_required` | Filter | Return `false` to skip the password check, for example for SSO accounts. Args: `$required, $user_id`. |

## Applications

| Hook | Type | Fires when |
|---|---|---|
| `wcb_pre_application_submit` | Filter | Before an application is created. Args: `$error` (null), `$request`. Return a `WP_Error` to stop it. |
| `wcb_before_create_application` | Filter | Change the `wp_insert_post` arguments for a new application. Args: `$data, $job_id, $candidate_id, $request`. `$candidate_id` is `0` for a guest. |
| `wcb_application_submitted` | Action | After an application is created. Args: `$app_id, $job_id, $candidate_id` (`0` for a guest). |
| `wcb_application_status_changed` | Action | Once per real status change that should notify the applicant. Silent changes (a job closing or reopening, migrations) skip it. Args: `$app_id, $old_status, $new_status, $reason, $actor` (`0` for the system). |
| `wcb_application_status_updated` | Action | Every status change, including the silent ones. Use it to keep data in step with the status; do not send messages from it. Args: `$app_id, $old_status, $new_status, $reason`. |
| `wcb_application_status_label` | Filter | The words shown for a status. Args: `$label, $status, $audience` (`candidate`, `employer` or `admin`). Candidates see "Not selected" where employers see "Rejected". |
| `wcb_employer_actionable_statuses` | Filter | The statuses an employer may set. Default: `submitted`, `reviewing`, `shortlisted`, `rejected`, `hired`. |
| `wcb_application_withdrawn` | Action | A candidate withdrew. The application stays with status `withdrawn` and the employer is emailed. Args: `$app_id, $job_id, $candidate_id`. |
| `wcb_application_deleted` | Action | Before an application post is permanently deleted. Args: `$app_id, $job_id`. |
| `wcb_close_job_applications` | Action | A background batch that moves a closing job's undecided applications to `job_removed` or `position_closed`. Scheduled by the plugin; listen, do not fire. Args: `$job_id, $status`. |
| `wcb_application_form_fields` | Action | Inside the apply panel on the job page. Print extra markup here. It is not validated or saved for you. Args: `$job_id`. |
| `wcb_application_form_fields_groups` | Filter | Add field groups to the apply form. The plugin renders, validates and saves them as `_wcb_application_field_<key>`. Args: `$groups, $job_id`. |
| `wcb_guest_applications_claimed` | Action | An account took over the guest applications sent from its email address. This happens once the address is proven (email confirmed, or a password set from an emailed link), or at once for an account an admin or WP-CLI created. Args: `$user_id, $claimed_application_ids`. |
| `wcb_resume_pdf_attachment_id` | Filter | The attachment ID used as a candidate's resume PDF. Args: `$id` (0), `$resume_id, $candidate_id`. |

Application statuses are `submitted`, `reviewing`, `shortlisted`, `rejected`, `hired`, `withdrawn`, `job_removed` and `position_closed`.

## Sign-up and accounts

| Hook | Type | Fires when |
|---|---|---|
| `wcb_pre_registration` | Filter | Before a candidate or employer account is created. Return a `WP_Error` to refuse the sign-up. Args: `$error` (null), `$request`. |
| `wcb_registration_rate_limit` | Filter | Sign-ups one IP may make per hour. Default `5`; `0` turns the limit off. |
| `wcb_candidate_registered` | Action | After a candidate signs up. Args: `$user_id`. |
| `wcb_employer_registered` | Action | After an employer signs up. Args: `$user_id, $company_id`. |
| `wcb_email_verification_requested` | Action | A new account needs to confirm its email. The confirmation email listens here. Args: `$user_id, $verify_url`. |
| `wcb_email_verified` | Action | A member confirmed their email address from the link. Guest applications from that address are claimed here. Args: `$user_id`. |
| `wcb_employer_login_redirect_enabled` | Filter | Return `false` to stop the redirect to the employer dashboard after login. Args: `$enabled, $user, $redirect_to, $requested_redirect_to`. |
| `wcb_candidate_requires_role` | Filter | Return `true` to require the `wcb_candidate` capability (or `manage_options`) for candidate actions instead of allowing any logged-in member. Default follows the `candidate_requires_role` setting, which is off. |
| `wcb_candidate_form_fields` | Filter | Add fields to the candidate profile form. Args: `$fields, $user_id`. |
| `wcb_company_form_fields` | Filter | Add fields to the company profile form. Args: `$groups, $company_id`. |
| `wcb_save_custom_field` | Filter | Change or reject a custom field value before it is saved. Return `null` to skip saving. Args: `$value, $key, $owner_id`. |
| `wcb_can_view_public_resume` | Filter | Whether a viewer may open a public resume. Pro applies the owner's access setting. Args: `$allowed, $post_id, $viewer_id`. |
| `wcb_private_file_can_download` | Filter | Grant a private resume file to another audience. Args: `$allowed, $attachment_id, $user_id`. |
| `wcb_resume_rest_query_args` | Filter | Change the resume REST query. Args: `$args, $request`. |
| `wcb_resume_sitemap_query_args` | Filter | Change the resume sitemap query. Args: `$args`. |

## Personal data

| Hook | Type | Args | Purpose |
|---|---|---|---|
| `wcb_personal_data_providers` | Filter | `$providers` | Register personal data once for the WordPress exporter, the eraser and user deletion. Add `'key' => array( 'label' => string, 'export' => callable, 'erase' => callable )`. Both callables receive `array( 'user_id' => int, 'email' => string )`; `user_id` is `0` for a guest, and `closing` is `true` only when the account itself is being deleted. `export` returns WordPress exporter items. `erase` returns `array( 'removed' => int, 'retained' => int, 'messages' => string[] )`. |
| `wcb_logs_pruned` | Action | `$cutoff` | After the daily prune of email history older than **Keep Email History**. `$cutoff` is UTC `Y-m-d H:i:s`. |

## Credits and pricing

| Hook | Type | Fires when |
|---|---|---|
| `wcb_credits_enabled` | Filter | Return `true` when credits are active (Pro). |
| `wcb_employer_credit_balance` | Filter | The employer's credit balance. Args: `$balance` (0), `$user_id`. |
| `wcb_employer_credit_has_history` | Filter | Whether the employer has held credits before, so a zero balance shows the low-balance warning. Args: `$has_history, $employer_id`. |
| `wcb_credit_purchase_url` | Filter | The URL the **Buy Credits** button points at. |
| `wcb_credit_low_threshold` | Filter | A balance below this shows the low-credits banner. Default `0` (no banner). |
| `wcb_board_credit_cost` | Filter | Credits needed to post to a board. Args: `$cost, $board_id`. |
| `wcb_featured_upgrade_cost` | Filter | What featuring a job costs. `0` hides the job-form checkbox and the My Jobs **Feature** action. Args: `$cost`. |
| `wcb_board_currency` | Filter | Override the currency of one board. Args: `$currency, $board_id`. |
| `wcb_board_options_for_employer` | Filter | Change the boards offered to an employer in the job form. Each entry is `array( 'id' => int, 'title' => string )`. Args: `$options, $user_id`. |

Free fires these filters so blocks and the employer dashboard share one surface, but they return meaningful values only when Pro is active. In Free they default to credits off, an empty URL and a zero balance. Pro also fires `wcb_job_republish_credit_cost` (args: `$cost, $post, $previous_status`).

## Active job limit

| Hook | Type | Fires when |
|---|---|---|
| `wcb_employer_active_job_limit` | Filter | Maximum concurrently active jobs per employer; `0` means unlimited (default). Args: `$limit, $user_id, $request`. |
| `wcb_employer_active_job_statuses` | Filter | Post statuses that occupy a slot. Default `array( 'publish' )`. Args: `$statuses, $user_id`. |
| `wcb_employer_active_job_limit_message` | Filter | The message shown when the cap blocks a post. Args: `$message, $limit, $count`. |

Cap the number of live listings one employer may hold:

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

The limit is checked in `POST /wcb/v1/jobs`, and again in `PUT /wcb/v1/jobs/{id}` when a listing goes back to `publish`. The job being republished is not counted against itself. A blocked request returns HTTP `403` with code `wcb_active_job_limit`; `data.limit` and `data.count` carry the numbers.

## REST response shaping

The `wcb_rest_prepare_*` filters run after the controller builds a row and before it is returned.

| Hook | Adjusts | Filter args |
|---|---|---|
| `wcb_rest_prepare_job` | A job (single and list items) | `$data, $post, null` |
| `wcb_rest_prepare_application` | An application | `$data, $post, $request, $viewer_role` |
| `wcb_rest_prepare_candidate` | A candidate profile | `$data, $user, null` |
| `wcb_rest_prepare_company` | A company profile | `$data, $post, null` |
| `wcb_job_response` | A job, fired just before `wcb_rest_prepare_job` | `$data, $post` |

- For `wcb_rest_prepare_candidate` the second argument is a `WP_User`.
- The third argument is `null` on the job, candidate and company filters; do not rely on it.
- The fourth argument of `wcb_rest_prepare_application` is `candidate` or `employer`, so you can redact fields per audience.

Pro adds `wcb_rest_prepare_board`, `wcb_rest_prepare_board_stage`, `wcb_rest_prepare_notification` and `wcb_rest_prepare_resume`.

Two more filters shape API output: `wcb_admin_email_log_response` (`$response, $request`) for the email-log route, and `wcb_rest_app_config` (`$data`) for `GET /settings/app-config`; see [03-rest-api.md](03-rest-api.md).

## Blocks, pages and assets

| Hook | Type | Use it to |
|---|---|---|
| `wcb_module_renders` | Filter | Let an add-on supply dashboard modules. Args: `$renders` (empty array, keyed by module name, values are markup) and `$context` (`candidate-dashboard`, `employer-dashboard` or `archive-toolbar`). Pro supplies its notification bell this way. |
| `wcb_candidate_resumes_state` | Filter | Add resume-limit keys (such as `maxResumes` and `resumeCount`) to the candidate dashboard's Interactivity state. Args: `$state, $candidate_user_id`. |
| `wcb_company_sidebar_before` | Action | Company profile, before the sidebar cards. Args: `$company_id`. |
| `wcb_company_sidebar_after` | Action | Company profile, after the sidebar cards. Args: `$company_id`. |
| `wcb_company_sidebar_blocks` | Filter | Change the sidebar block list. Default: Similar Companies and Job Alerts. Each entry is a block comment string. Args: `$blocks, $company_id`. |
| `wcb_shortcode_attr_aliases` | Filter | Add to the lowercase to camelCase attribute map, so `[wcb_job_listings boardId="1"]` works. |
| `wcb_search_active_shortcodes` | Filter | Shortcode prefixes (default `wcb_`, `wcbp_`) that mark a page as a Career Board page. |
| `wcb_page_needs_frontend_assets` | Filter | Return `true` to load the Career Board frontend styles on a request the plugin does not detect (for example blocks rendered outside the post content). |
| `wcb_app_page_ids` | Filter | Add page IDs that get the `wcb-page` body class and page template. |
| `wcb_apply_page_class` | Filter | Return `false` to keep a page from getting the `wcb-page` body class and the plugin page template. Args: `$is_wcb_page, $page_id`. |
| `wcb_fullwidth_block_names` | Filter | Add a block name (`namespace/slug`) that triggers the full-width page template. |
| `wcb_container_max_width` | Filter | The content column width in pixels. The value is kept between 720 and 1920. |
| `wcb_theme_accent_primary` | Filter | The primary accent colour blocks use. Args: `$accent, $template`. |
| `wcb_industries` | Filter | Add or rename the industries offered on the company profile. |
| `wcb_currency_catalog` | Filter | Add a currency to the salary-currency dropdown. |
| `wcb_bot_ua_pattern` | Filter | The PCRE pattern (no delimiters) that detects bot User-Agents. Job views from bots are not counted. |
| `wcb_job_views_retention_days` | Filter | Days of `wcb_job_views` analytics rows kept before the daily prune. Default `90`, never below `30`. |

## Settings, setup and email

| Hook | Type | Use it to |
|---|---|---|
| `wcb_settings_schema` | Filter | Add setting keys: `$fields['my_key'] = array( 'default' => false, 'sanitize' => 'rest_sanitize_boolean' )`. A key outside the schema is never written by a settings form. |
| `wcb_settings_sanitize` | Filter | Change the cleaned settings before they are saved. Args: `$output, $input`. |
| `wcb_install_default_settings` | Filter | Change the default settings seeded on install. |
| `wcb_settings_tabs` | Filter | Add a tab to the Settings screen (`slug => label`). |
| `wcb_settings_tab_groups` | Filter | Change the Settings sidebar groups: group key => `label` and `tabs` (slugs, in order). A tab not listed lands in "Site". |
| `wcb_settings_tab_<slug>` | Action | Render the content of a tab you added. Args: `$settings`. The form around your content is yours to print. |
| `wcb_page_definitions` | Filter | Add a page the setup wizard and Pages tab create: `title`, `slug`, `content` (block markup), `label`, `desc`, optional `aliases`. Register the page's setting key in `wcb_settings_schema` too. |
| `wcb_page_settings`, `wcb_wizard_required_pages` | Filter | Deprecated. Still applied; use `wcb_page_definitions`. |
| `wcb_safer_defaults_notice` | Filter | The list shown once to owners of sites created before the current defaults. |
| `wcb_import_extra_cards` | Action | Print extra cards after the last built-in card on the Import page. |
| `wcb_wizard_steps` | Filter | Add a step to the setup wizard. Each entry, keyed by a unique slug, has `title`, `template` (absolute path) and `button_text`. |
| `wcb_wizard_completed` | Action | After the wizard's last step. |
| `wcb_wizard_force_render` | Filter | Show the wizard even when setup is complete. |
| `wcb_wizard_complete_redirect` | Filter | The URL the wizard redirects to when it finishes. |
| `wcb_sample_data_installed` | Action | After the wizard installs sample content. Args: `$created_ids`. |
| `wcb_sample_data_removed` | Action | After the wizard removes sample content. |
| `wcb_registered_emails` | Filter | Add an email object to the email registry. See the [cookbook](05-extension-cookbook.md). |
| `wcb_email_template_dirs` | Filter | Add a folder searched for `{slug}.php` email templates. A theme file at `{theme}/wp-career-board/emails/{slug}.php` still wins first. |
| `wcb_apply_ai_notice` | Filter | The notice above **Submit Application** when applications may be screened with AI. An empty string shows nothing. Args: `$text, $job_id`. |
| `wcb_template_override_docs_url` | Filter | The "Learn how template overrides work" link in Site Health. |
| `wcb_overridable_templates` | Filter | Add a theme-overridable template to the Site Health version check. See [Template Overrides](./06-template-overrides.md). |
| `wcb_companions` | Filter | Add an entry to the companion-plugin catalog. |

`wcb_settings_tab_emails` and `wcb_settings_tab_antispam` are the built-in renderers for those two tabs. Do not use them to add settings; add your own tab instead.

## Notifications

| Hook | Type | Args | Purpose |
|---|---|---|---|
| `wcb_notification_created` | Action | `$notification, $contract` | Fires once per notification-worthy event so a notification center (such as BuddyNext) can mirror it. Free fires it once per real email send; admin test sends are skipped. `$notification` is `array( user_id, event_type, message, link, id )`; `message` is the email subject and `id` is `0` on Free. When Pro's bell records the same event, the bell fires the hook and the email does not. |
| `wcb_email_announces_notification` | Filter | `$announce, $email_id, $user_id` | Return `false` when your own channel records the event and fires the signal itself, so listeners get it once. |
| `wcb_community_notification_types` | Filter | `$types` | Free adds the types its emails can fire here: `slug => array( label, description, default_on )`. A notification center applies this filter to list them. |
| `wcb_community_notification_visible` | Filter | `$visible, $viewer_id, $targets` | Free answers this for `job` and `application` rows. A row is hidden once the object is trashed, for the employer once the candidate withdraws, and for a job that is not yet public when the viewer is not its employer. |
| `wcb_community_notification_removed` | Action | `$object_type, $object_id` | A job or application was permanently deleted. Not fired on trash. |

The second argument of `wcb_notification_created` is the contract payload, so a notification center can show one row per event with the plugin's own words and link:

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

## Mobile app

| Hook | Type | Args | Purpose |
|---|---|---|---|
| `wcb_min_app_version` | Filter | `$version` | The minimum app version `settings/app-config` reports, so the app can force an upgrade. Default `"1.0.0"`. |
| `wcb_app_enabled` | Filter | `$enabled` | Whether `settings/app-config` reports the app as enabled. Default: whether Pro is active. |
| `wcb_app_password_login_enabled` | Filter | `$on` | Whether password sign-in is available. Backs the **App Password Sign-In** setting, off by default. |
| `wcb_app_password_max_failures` | Filter | `$max` | Failed sign-ins per bucket before lockout. Default 5. |
| `wcb_app_password_max_attempts_per_ip` | Filter | `$max` | Attempts per IP per hour. Default 20. |
| `wcb_client_ip_header` | Filter | `$header` | The `$_SERVER` key that carries the real client IP behind a proxy, for every per-IP limit and count. Defaults to the **Visitor IP address** setting (empty means `REMOTE_ADDR`). |
| `wcb_app_password_client_ip_header` | Filter | `$header` | Older name, still applied after `wcb_client_ip_header`. Use `wcb_client_ip_header` in new code. |
| `wcb_app_credential_issued` | Action | `$user_id, $app_id, $app_name` | A member traded their password for a credential. The credential itself is never passed. |
| `wcb_app_credential_revoked` | Action | `$user_id, $uuid` | A member signed out of the app and revoked their credential. |
| `wcb_app_connect_schemes` | Filter | `$schemes` | URL schemes the app-connect flow may hand a credential to. Add only schemes for an app you ship. |
| `wcb_app_connect_bridge` | Filter | `$info` | The resolved app-connect bridge for this site: `owner`, `connect_url`, `connect_schemes`. |
| `wcb_app_scheme` | Filter | `$scheme` | The mobile app's deep-link scheme. |

## Pro coordination

Free checks these filters to see whether Pro is active. Pro returns the real value; in Free they default to `false` or empty.

| Hook | Returns |
|---|---|
| `wcb_pro_active` | bool - Pro is running. |
| `wcb_pro_licensed` | bool - the Pro license is valid. |
| `wcb_pro_version` | string - the Pro version. |
| `wcb_pro_ai_enabled` | bool - the Pro AI bundle is enabled. |
| `wcb_pro_upsell_url` | string - where the "Upgrade to Pro" link points. |
| `wcb_pro_settings_saved_notice` | string - message for the notice after a Pro settings save. |
| `wcb_ai_description_enabled` | bool - show the AI description helper in the job forms. |
| `wcb_ai_matching_available` | bool - the candidate dashboard shows AI job matching. |
| `wcb_ai_ranking_available` | bool - the employer dashboard shows AI applicant ranking. |
| `wcb_ai_completion_available` | bool - the job page offers the AI cover-letter helper. |
| `wcb_pro_alerts_enabled` | bool - the job alerts feature is active. |
| `wcb_pro_resumes_enabled` | bool - the resume directory is active. |

## WP-CLI

| Hook | Type | Args | Purpose |
|---|---|---|---|
| `wcb_cli_abilities` | Filter | `$abilities` | Add entries to the `slug => label` list that `wp wcb abilities` reports. |
| `wcb_scale_budgets` | Filter | `$budgets` | Per-query time budgets (ms) for `wp wcb scale benchmark`. |
| `wcb_scale_ops` | Filter | `$ops, $per_page` | Add a named callable (`name => callable`) for the benchmark to time. Add a matching entry in `wcb_scale_budgets`; an operation without a budget is timed but never fails the run. |

## Browser events

Two blocks coordinate through `document`-level `CustomEvent`s, so listeners (including Pro's job-map block) can react without a shared Interactivity store.

| Event | Dispatched by | Detail | Fires when |
|---|---|---|---|
| `wcb:search` | `job-search` and `job-filters` blocks | `{ query, filters }` | The visitor changes the search query or a filter, after the URL is updated with `history.pushState`. |
| `wcb:results` | `job-listings` block | `{ jobIds: number[], total: number }` | After a listings fetch resolves, so a listener can follow the jobs actually shown. |

```js
document.addEventListener( 'wcb:results', ( event ) => {
    console.log( 'Visible job IDs:', event.detail.jobIds );
} );
```

## Listening pattern

```php
add_action( 'wcb_job_created', function ( $job_id, $request ) {
    // Notify a Slack channel when a featured job lands.
    if ( $request->get_param( 'featured' ) ) {
        my_slack_post( 'Featured job posted: ' . get_the_title( $job_id ) );
    }
}, 10, 2 );
```

```php
add_filter( 'wcb_rest_prepare_job', function ( $row, $post ) {
    // Add an `is_remote_friendly` flag based on a meta value.
    $row['is_remote_friendly'] = (bool) get_post_meta( $post->ID, '_remote_friendly', true );
    return $row;
}, 10, 2 );
```
