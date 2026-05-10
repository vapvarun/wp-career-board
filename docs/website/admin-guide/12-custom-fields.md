# Custom Fields (declarative filters)

Add custom fields to any plugin form — Job Form, Company Form,
Candidate Profile, Application Form — with one `add_filter` call.
The filter takes a single field-group schema; the plugin handles
rendering, validation, persistence, REST exposure, and admin display.

## The four filters

| Filter | Form |
|---|---|
| `wcb_job_form_fields` | Post a Job (multi-step + single-page forms) |
| `wcb_company_form_fields` | Company profile editor |
| `wcb_candidate_form_fields` | Candidate profile editor |
| `wcb_application_form_fields_groups` | Apply to a job |

All four use the same field-group schema — once you've learned one,
you've learned all four.

## Schema

A field group looks like this:

```php
[
    'group_id'    => 'employer_screening',
    'group_label' => __( 'Screening Questions', 'wp-career-board' ),
    'fields'      => [
        [
            'key'         => 'years_experience',
            'label'       => __( 'Years of relevant experience', 'wp-career-board' ),
            'type'        => 'number',
            'required'    => true,
            'min'         => 0,
            'max'         => 60,
        ],
        [
            'key'         => 'visa_status',
            'label'       => __( 'Current visa status', 'wp-career-board' ),
            'type'        => 'select',
            'required'    => true,
            'options'     => [
                'us-citizen'    => 'US Citizen',
                'green-card'    => 'Green Card',
                'h1b'           => 'H-1B',
                'opt'           => 'OPT',
                'needs-sponsor' => 'Needs sponsorship',
            ],
        ],
        [
            'key'         => 'portfolio_url',
            'label'       => __( 'Portfolio URL', 'wp-career-board' ),
            'type'        => 'url',
            'required'    => false,
            'placeholder' => 'https://',
        ],
    ],
]
```

## Field types

| Type | Renders | Stored as |
|---|---|---|
| `text` | Single-line input | string |
| `textarea` | Multi-line textarea | string |
| `email` | Email input + validation | string |
| `url` | URL input + validation | string |
| `number` | Numeric input with min/max | int / float |
| `select` | Dropdown | string (option key) |
| `radio` | Radio button group | string (option key) |
| `checkbox` | Single boolean checkbox | `'1'` / `''` |
| `multi-checkbox` | Multiple checkboxes | array of option keys |
| `date` | Date picker | YYYY-MM-DD string |

## Example: Add a "Portfolio URL" field to the candidate profile

```php
add_filter( 'wcb_candidate_form_fields', function( $groups ) {
    $groups[] = [
        'group_id'    => 'links',
        'group_label' => __( 'Online presence', 'wp-career-board' ),
        'fields'      => [
            [
                'key'      => 'portfolio_url',
                'label'    => __( 'Portfolio URL', 'wp-career-board' ),
                'type'     => 'url',
                'required' => false,
            ],
            [
                'key'      => 'github_url',
                'label'    => __( 'GitHub profile URL', 'wp-career-board' ),
                'type'     => 'url',
                'required' => false,
            ],
        ],
    ];
    return $groups;
} );
```

After this filter is in place:

- The candidate profile editor renders both fields.
- The fields validate on save (URL format).
- Values persist as user meta `_wcb_candidate_field_portfolio_url`
  and `_wcb_candidate_field_github_url`.
- They appear in the candidate's REST response on
  `GET /wcb/v1/candidates/{id}`.

## Example: Add a screening question to the application form

```php
add_filter( 'wcb_application_form_fields_groups', function( $groups, $job_id ) {
    $groups[] = [
        'group_id'    => 'screening',
        'group_label' => __( 'Quick screen', 'wp-career-board' ),
        'fields'      => [
            [
                'key'      => 'years_relevant',
                'label'    => __( 'Years of relevant experience', 'wp-career-board' ),
                'type'     => 'number',
                'required' => true,
            ],
            [
                'key'      => 'salary_expectation',
                'label'    => __( 'Salary expectation (USD/yr)', 'wp-career-board' ),
                'type'     => 'number',
                'required' => false,
            ],
        ],
    ];
    return $groups;
}, 10, 2 );
```

This adds the screening group to every job's apply form. To scope to
specific jobs, branch on `$job_id` inside the callback.

## Per-job custom fields (Pro field builder)

The above filter applies globally. For per-job configuration without
writing PHP, install Pro and use the
[Field Builder](../../../wp-career-board-pro/docs/website/field-builder/01-overview.md)
admin page — the builder writes the same data structure to the
`wcb_field_groups` / `wcb_field_definitions` Pro tables and contributes
to the same filters automatically.

## Where the data appears

Custom field values appear:

- **In the admin Edit Application screen** — under a "Custom fields"
  section per group.
- **In the bulk CSV export** — one column per field key.
- **In the REST API** — under the `custom_fields` key of the job /
  company / candidate / application response.
- **In templates** — via the `Icon::svg()` style helpers and direct
  postmeta reads (`get_post_meta($id, '_wcb_application_field_<key>', true)`).

## Persistence keys

Per surface:

| Filter | Stored where | Meta key prefix |
|---|---|---|
| `wcb_job_form_fields` | `wp_postmeta` (job) | `_wcb_job_field_<key>` |
| `wcb_company_form_fields` | `wp_postmeta` (company) | `_wcb_company_field_<key>` |
| `wcb_candidate_form_fields` | `wp_usermeta` (candidate) | `_wcb_candidate_field_<key>` |
| `wcb_application_form_fields_groups` | `wp_postmeta` (application) | `_wcb_application_field_<key>` |

A bundle of all field values also lives at the corresponding
`_wcb_*_fields_bundle` key for one-shot reads.
