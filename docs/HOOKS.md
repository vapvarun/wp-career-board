# WP Career Board — Hook Reference

Customer-facing extension points for theme integrators and add-on
developers. Stable across Free + Pro: every form-field hook listed here
works the same whether the form is in Free or Pro.

> The `wcb_` prefix family is the **shared customer-facing surface**.
> Pro internals also expose `wcbp_*` hooks, but theme integrators rarely
> need them. Stick to `wcb_*` unless documented otherwise.

## The Field-Group Schema

Every "form-fields" filter returns the same shape — an array of *groups*,
each containing *fields*:

```php
return array(
    array(
        'id'     => 'partner',                      // unique slug
        'label'  => __( 'Partner', 'my-theme' ),    // shown as section heading
        'fields' => array(
            array(
                'key'         => '_wcb_partner_id', // post-meta key (or arbitrary ID for non-meta data)
                'label'       => __( 'Partner', 'my-theme' ),
                'type'        => 'select',          // text | email | tel | url | number | date | textarea | select | checkbox
                'required'    => true,              // optional — defaults false
                'placeholder' => __( 'Choose…', 'my-theme' ),
                'description' => __( 'Helper text below the field', 'my-theme' ),
                'options'     => array(             // required for `select`; ignored for other types
                    1 => 'Acme',
                    2 => 'Beta',
                ),
            ),
        ),
    ),
);
```

Fields rendered via this schema bind their values to the block's
Interactivity API store via `data-wp-on--input="actions.updateCustomField"`
and `data-wcb-field="<key>"`. Submitted values land in the `custom_fields`
property on the REST request body.

## Form-fields filters (one per form)

| Form | Filter name | Args | Where rendered |
|---|---|---|---|
| **Job Form (multi-step wizard)** | `wcb_job_form_fields` | `(array $groups, int $board_id)` | After step 1 fields |
| **Job Form (single-page)** | `wcb_job_form_fields` (shared) | same | After all default fields |
| **Application form (apply modal)** | `wcb_application_form_fields_groups` | `(array $groups, int $job_id)` | After cover letter, before submit |
| **Company / Employer Profile** | `wcb_company_form_fields` | `(array $groups, int $company_id)` | Below default company fields |
| **Candidate Profile** | `wcb_candidate_form_fields` | `(array $groups, int $candidate_id)` | Below default candidate fields |
| **Resume Builder** *(Pro)* | `wcb_resume_form_fields` | `(array $groups, int $resume_id)` | After default resume sections |
| **Resume Form Simple** *(Pro)* | `wcb_resume_form_fields` (shared) | same | After default fields |

Add-ons targeting **both** the wizard and the single-page job form add a
custom field once via `wcb_job_form_fields` and it shows in both. Same
applies to Pro's resume-builder + resume-form-simple sharing
`wcb_resume_form_fields`.

## Initial-state filters (modify Interactivity API state)

For state keys beyond field values — e.g. computed flags, lookup data
pre-fetched server-side — use the per-form initial-state filter:

| Form | Filter |
|---|---|
| Job Form (wizard) | `wcb_job_form_initial_state` |
| Job Form (simple) | `wcb_job_form_simple_initial_state` |
| Resume Builder *(Pro)* | `wcb_resume_form_initial_state` |
| Resume Form Simple *(Pro)* | `wcb_resume_form_simple_initial_state` |

```php
add_filter( 'wcb_job_form_initial_state', function( $state, $attributes ) {
    $state['partnerOptions'] = my_theme_get_partner_list();
    return $state;
}, 10, 2 );
```

## Action hooks (raw HTML injection — escape hatch)

Use these only when the declarative filter above can't model your need
(e.g. interactive widgets that aren't simple form fields).

| Form / step | Action |
|---|---|
| Job Form wizard, step 1 | `wcb_job_form_step1_fields` |
| Job Form wizard, step 2 | `wcb_job_form_step2_fields` |
| Job Form wizard, step 3 | `wcb_job_form_step3_fields` |
| Job Form wizard, step 4 (preview) | `wcb_job_form_step4_preview` |
| Job Form simple | `wcb_job_form_simple_extra_fields` |
| Application form (legacy) | `wcb_application_form_fields` |

The application action is **legacy** — prefer the new
`wcb_application_form_fields_groups` filter for new code.

## Listings + REST hooks

For filtering the job listings block by custom relationships (partners,
sponsors, brands, etc.):

| Hook | Purpose |
|---|---|
| `wcb_jobs_allowed_meta_filters` | Allowlist post-meta keys for `?meta_<key>=<value>` REST query params on `/wcb/v1/jobs` |
| `wcb_jobs_query_args` | Modify the WP_Query args used by the listings block on first paint |
| `wcb_jobs_post_filter` | Post-process the prepared job array before REST response |
| `wcb_job_response` | Shape an individual job's REST response |
| `wcb_job_listings_query_args` | Modify the listings block's initial query |
| `wcb_job_listings_board_options` | Add custom chips to the listings filter bar |
| `wcb_job_listing_data` | Shape per-card data on the listings block |

## Lifecycle actions

Fire side effects on key plugin events:

| Action | Args |
|---|---|
| `wcb_job_created` | `(int $job_id, WP_REST_Request $request)` |
| `wcb_job_updated` | `(int $job_id, WP_REST_Request $request)` |
| `wcb_job_approved` | `(int $job_id)` |
| `wcb_job_rejected` | `(int $job_id, string $reason)` |
| `wcb_job_expired` | `(int $job_id)` |
| `wcb_job_deleted` | `(int $job_id)` |
| `wcb_application_submitted` | `(int $app_id, int $job_id, int $candidate_id)` |
| `wcb_application_status_changed` | `(int $app_id, string $old_status, string $new_status)` |
| `wcb_deadline_reminder` | `(int $user_id, int $job_id, int $days_left)` |
| `wcb_featured_expired` | `(int $job_id)` |

## Filter early-rejection on submission

Both job and application submissions pass through a "pre-submit" filter
where add-ons (anti-spam, validation, paywall checks) can return a
`WP_Error` to short-circuit the submission:

```php
add_filter( 'wcb_pre_application_submit', function( $err, $request ) {
    if ( /* validation fails */ ) {
        return new WP_Error( 'my_theme_blocked',
            __( 'Application blocked', 'my-theme' ),
            array( 'status' => 400 )
        );
    }
    return $err;
}, 10, 2 );
```

| Filter | Purpose |
|---|---|
| `wcb_pre_job_submit` | Short-circuit job creation |
| `wcb_pre_application_submit` | Short-circuit application submission |

## Convention

- **`wcb_*`** — customer-facing extension surface. Stable.
- **`wcbp_*`** — Pro-internal hooks. May change between Pro versions; not
  intended for theme integrators.
- All form-fields filters return the same group/field schema documented at
  the top of this file. Use it once, use it everywhere.
