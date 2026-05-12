# Hooks Reference — Actions and Filters

WP Career Board fires **29 actions** and **63 filters** (Free only —
Pro adds its own; see the Pro developer guide). Hooks are grouped
by area below.

> **How to use this list:** every hook is fired with `do_action()`
> or `apply_filters()` somewhere in the plugin source. The full
> file:line is in `audit/manifest.json#/hooks_fired`. The arg
> signature for any hook can be found by `grep` against the
> hook name in the codebase.

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
| `wcb_job_republished` | Action | When a job is republished after expiry. Args: `$job_id`. |
| `wcb_job_approved` | Action | When admin approves a pending job. Args: `$job_id`. |
| `wcb_job_rejected` | Action | When admin rejects a job. Args: `$job_id, $reason`. |
| `wcb_job_expired` | Action | When the deadline passes. Args: `$job_id`. |
| `wcb_check_job_expiry` | Action | Cron hook — fires daily to expire stale jobs. |
| `wcb_deadline_reminder` | Action | Cron — fires the deadline-reminder email cycle. |
| `wcb_featured_expired` | Action | When a featured job's promotion window ends. Args: `$job_id`. |

## Lifecycle / applications

| Hook | Type | Fires when |
|---|---|---|
| `wcb_pre_application_submit` | Filter | Before an application is created. Return `WP_Error` to abort (anti-spam, rate limits, etc.). |
| `wcb_before_create_application` | Filter | Modify `wp_insert_post` arg array. |
| `wcb_application_submitted` | Action | After successful submit. Args: `$app_id, $job_id, $candidate_id`. |
| `wcb_application_status_changed` | Action | When status moves (`submitted → reviewing → shortlisted → rejected/hired/withdrawn/job_removed`). Args: `$app_id, $new_status, $old_status`. |
| `wcb_application_withdrawn` | Action | Candidate withdrew. Args: `$app_id, $job_id, $candidate_id`. |
| `wcb_application_deleted` | Action | Application post deleted. Args: `$app_id, $job_id`. |
| `wcb_application_form_fields` | Action | Inside the apply form template — render extra `<input>`s here. |
| `wcb_application_form_fields_groups` | Filter | Add a group of custom fields to the apply form. |

## Lifecycle / candidates and employers

| Hook | Type | Fires when |
|---|---|---|
| `wcb_candidate_registered` | Action | After a candidate signup completes. Args: `$user_id, $request`. |
| `wcb_employer_registered` | Action | After an employer signup completes. Args: `$user_id, $request`. |
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
| `wcb_moderate_jobs_ability_check` | Filter | Return bool to override the moderation permission check. |

## REST response shaping

The `wcb_rest_prepare_*` family is your hook into every REST
response. Each fires after the controller builds the row and
before it's returned — modify, redact, or augment.

| Hook | Adjusts the response for |
|---|---|
| `wcb_rest_prepare_job` | Single job + collection items |
| `wcb_rest_prepare_application` | Single application + lists |
| `wcb_rest_prepare_candidate` | Candidate profile |
| `wcb_rest_prepare_company` | Company profile |
| `wcb_job_response` | Legacy alias for backward compat |

Pro adds: `wcb_rest_prepare_board`, `wcb_rest_prepare_board_stage`,
`wcb_rest_prepare_notification`, `wcb_rest_prepare_resume`.

## Block + shortcode extension

| Hook | Type | Use it to |
|---|---|---|
| `wcb_module_renders` | Filter | Skip rendering of specific dashboard modules. |
| `wcb_job_form_initial_state` | Filter | Modify the job-form Interactivity state seed. |
| `wcb_job_form_simple_initial_state` | Filter | Same for the single-page job form. |
| `wcb_job_form_fields` | Filter | Add field groups to the job form. |
| `wcb_job_form_step1_fields` | Action | Inject extra `<input>`s into the wizard's step 1 (similar for step2/3/4). |
| `wcb_job_form_simple_extra_fields` | Action | Inject extra fields into the single-page form. |
| `wcb_application_form_fields` | Action | Inject extra fields into the apply panel. |
| `wcb_shortcode_attr_aliases` | Filter | Add to the camelCase ↔ lowercase attribute map (so `[wcb_job_listings boardId="1"]` works). |
| `wcb_search_active_shortcodes` | Filter | Tag/prefix names for the body-class detector. Extend if you register custom shortcodes that should also force the `wcb-page` body class. |

## Settings and pages

| Hook | Type | Use it to |
|---|---|---|
| `wcb_install_default_settings` | Filter | Modify the seed values on plugin install. |
| `wcb_settings_sanitize` | Filter | Sanitize a custom settings key before save. |
| `wcb_settings_tabs` | Filter | Add a tab to the Settings UI. |
| `wcb_settings_tab_<slug>` | Action | Render content for a custom tab (the slug becomes the suffix). |
| `wcb_settings_tab_antispam` | Action | The built-in Anti-Spam tab. Hook to add additional anti-spam controls. |
| `wcb_settings_tab_emails` | Action | The Emails tab — extend with custom email templates. |
| `wcb_page_settings` | Filter | Modify which pages are mapped for "Career Board page" detection. |
| `wcb_app_page_ids` | Filter | Add page IDs that should get the `wcb-page` body class. |
| `wcb_apply_page_class` | Filter | Opt out a page from the `wcb-page` body class entirely. |
| `wcb_apply_filters` | Filter | Container max-width override. |
| `wcb_container_max_width` | Filter | Override the 1200px content-column default. |
| `wcb_registered_emails` | Filter | Add a new transactional email type to the plugin's email registry. |

## Setup wizard

| Hook | Type | Use it to |
|---|---|---|
| `wcb_wizard_completed` | Action | After the wizard's last step. |
| `wcb_wizard_force_render` | Filter | Force the wizard to render even when `is_setup_complete()` is true. |
| `wcb_wizard_complete_redirect` | Filter | Override the URL the wizard redirects to on finish. |

## Pro-coordination filters (Free side)

These let Free check whether Pro is active and gate behavior. Pro
hooks them to return true / version / license status.

| Hook | Returns |
|---|---|
| `wcb_pro_active` | bool — is Pro plugin running? |
| `wcb_pro_licensed` | bool — is the Pro license valid? |
| `wcb_pro_version` | string — Pro version, e.g. "1.1.1" |
| `wcb_pro_ai_enabled` | bool |
| `wcb_pro_alerts_enabled` | bool |
| `wcb_pro_resumes_enabled` | bool |
| `wcb_pro_upsell_url` | string — where the "Upgrade to Pro" CTA points |
| `wcb_pro_pre_check` | Action — fires before Pro's compatibility check |
| `wcb_pro_settings_saved_notice` | Filter — message for the post-save admin notice |
| `wcb_register_extensions` | Action — Pro hooks this to register its extension classes |

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
| `wcb_job_listings_api_base` | Filter | Override the REST base for the listings block. |
| `wcb_job_listings_query_args` | Filter | Modify the `WP_Query` args for the listings server-side query. |
| `wcb_job_listings_board_options` | Filter | The boards visible in the listings UI's board picker. |
| `wcb_job_listing_data` | Filter | Per-card data (each row in the listings block). |
| `wcb_jobs_post_filter` | Filter | After the listings query but before render — add transformations. |
| `wcb_jobs_allowed_meta_filters` | Filter | Allowlist of meta keys the `metaFilter` block attribute may query (prevents arbitrary-meta probes). |
| `wcb_ai_description_enabled` | Filter | Toggle the AI-assisted description feature (Pro). |
| `wcb_resume_archive_enabled` | Filter | Toggle the resume directory (Pro). |
| `wcb_theme_accent_primary` | Filter | Primary accent color used by blocks (driven by theme integration bridge). |
| `wcb_theme_primary_color` | Filter | Legacy alias. |
| `wcb_admin_email_log_response` | Filter | Modify the email-log REST response. |
| `wcb_cli_abilities` | Filter | Map WP-CLI runs to ability slugs for permission checks. |
| `wcb_import_extra_cards` | Action | Add cards to the Import admin page. |
| `wcb_rest_app_config` | Filter | Frontend boot config shipped to the Interactivity API. |
| `wcb_admin_email_log_response` | Filter | Already listed above. |

## Listening pattern (example)

```php
add_action( 'wcb_job_created', function ( $job_id, $request ) {
    // Notify a Slack channel when a new job lands.
    if ( $request->get_param( 'featured' ) ) {
        my_slack_post( "🔥 Featured job posted: " . get_the_title( $job_id ) );
    }
}, 10, 2 );
```

```php
add_filter( 'wcb_rest_prepare_job', function ( $row, $post, $request, $context ) {
    // Add a `is_remote_friendly` flag based on a meta value.
    $row['is_remote_friendly'] = (bool) get_post_meta( $post->ID, '_remote_friendly', true );
    return $row;
}, 10, 4 );
```

## How to confirm a hook signature

The fastest way to see the actual arg signature:

```bash
grep -rn "do_action\\s*(\\s*'wcb_job_created'" wp-content/plugins/wp-career-board/
```

That returns the file:line of every firer; open it and read the
surrounding lines for the parameter shapes.

For the Pro-side hooks, see
[wp-career-board-pro/docs/developer-guide/03-hooks-reference.md](../../../wp-career-board-pro/docs/website/developer-guide/03-hooks-reference.md).
