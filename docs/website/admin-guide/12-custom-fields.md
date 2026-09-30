# Custom fields

You can add your own fields to the job form, company profile, candidate profile and apply form with one `add_filter` call. The plugin renders the field, checks required fields, and saves the value.

## The four filters

| Filter | Form | Arguments |
|---|---|---|
| `wcb_job_form_fields` | Post a Job | `$groups`, `$board_id` |
| `wcb_company_form_fields` | Company profile editor | `$groups`, `$company_id` |
| `wcb_candidate_form_fields` | Candidate profile editor | `$groups`, `$user_id` |
| `wcb_application_form_fields_groups` | Apply form | `$groups`, `$job_id` |

Each callback receives the current list of groups and returns it with your group added. All four use the same group shape.

## Group shape

```php
array(
    'id'     => 'partner',
    'label'  => 'Partner association',
    'fields' => array(
        array(
            'key'         => 'partner_id',
            'label'       => 'Partner',
            'type'        => 'text',
            'required'    => true,
            'placeholder' => 'Optional',
            'description' => 'Optional hint shown under the field',
            'options'     => array( 'value' => 'Label' ),
        ),
    ),
)
```

The group `label` shows as a heading above its fields. `options` applies to `select`, `radio` and `multiselect`.

## Field types

| Type | Shows |
|---|---|
| `text`, `email`, `tel`, `url`, `number`, `date` | Matching single-line input |
| `textarea` | Multi-line text box |
| `select` | Dropdown |
| `radio` | Radio buttons |
| `multiselect` | Checkboxes, saved as a comma-separated list |
| `checkbox` | One on/off checkbox |
| `repeater` | Text box with one entry per line |

## Example: add a portfolio link to the candidate profile

```php
add_filter( 'wcb_candidate_form_fields', function ( $groups ) {
    $groups[] = array(
        'id'     => 'links',
        'label'  => __( 'Online presence', 'wp-career-board' ),
        'fields' => array(
            array(
                'key'      => 'portfolio_url',
                'label'    => __( 'Portfolio URL', 'wp-career-board' ),
                'type'     => 'url',
                'required' => false,
            ),
        ),
    );
    return $groups;
} );
```

## Example: add a question to the apply form

```php
add_filter( 'wcb_application_form_fields_groups', function ( $groups, $job_id ) {
    $groups[] = array(
        'id'     => 'screening',
        'label'  => __( 'Quick screen', 'wp-career-board' ),
        'fields' => array(
            array(
                'key'      => 'years_relevant',
                'label'    => __( 'Years of relevant experience', 'wp-career-board' ),
                'type'     => 'number',
                'required' => true,
            ),
        ),
    );
    return $groups;
}, 10, 2 );
```

This adds the group to every job's apply form. To limit it to some jobs, check `$job_id` in the callback.

## Where values are saved

| Filter | Saved as | Meta key |
|---|---|---|
| `wcb_job_form_fields` | Job post meta | The field `key` |
| `wcb_company_form_fields` | Company post meta | The field `key` |
| `wcb_candidate_form_fields` | User meta | The field `key` |
| `wcb_application_form_fields_groups` | Application post meta | `_wcb_application_field_` plus the field `key` |

To change or reject a value before it is saved, use the `wcb_save_custom_field` filter. It receives the sanitized value, the field key and the owner ID. Return `null` to skip saving.

## Where answers show

- The employer dashboard shows an applicant's answers under "Application answers".
- The applications CSV export has a "Screening Answers" column.
- The job page fires the `wcb_job_single_after_description` action after the description, with the job ID. Use it to print your own job fields.
- The application REST response includes a `custom_fields` entry.

## Pro

WP Career Board Pro adds a field builder that lets you create these fields without code. See the Pro documentation for the field builder guide.
