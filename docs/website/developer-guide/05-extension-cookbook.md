# Extension Cookbook

Common things developers ask "how do I…" - with the smallest
working snippet for each. Every recipe uses public hooks; nothing
here forks the source.

## Add a field to the apply form

You want candidates to answer (say) a "LinkedIn URL" question when applying. Return a field group from `wcb_application_form_fields_groups`. The plugin renders the control, validates it (required questions are checked on the server too), saves it, and shows the answer to the employer, in the admin application screen, in the REST API and in the CSV export. See [Custom Fields](../admin-guide/12-custom-fields.md) for the schema and field types.

```php
add_filter( 'wcb_application_form_fields_groups', function ( $groups, $job_id ) {
    $groups[] = array(
        'group_id'    => 'my_addon',
        'group_label' => __( 'Extra questions', 'my-addon' ),
        'fields'      => array(
            array(
                'key'      => 'linkedin',
                'label'    => __( 'LinkedIn URL', 'my-addon' ),
                'type'     => 'url',
                'required' => false,
            ),
        ),
    );
    return $groups;
}, 10, 2 );

// Read the saved answer later. It is stored as `_wcb_application_field_linkedin`.
$url = get_post_meta( $app_id, '_wcb_application_field_linkedin', true );
```

Use the `wcb_application_form_fields` action only for markup a field group cannot express; anything you print there is not validated or saved for you.

## Add a column to the admin applications table

```php
add_filter( 'manage_wcb_application_posts_columns', function ( $cols ) {
    $cols['my_score'] = __( 'Score', 'my-addon' );
    return $cols;
});

add_action( 'manage_wcb_application_posts_custom_column', function ( $col, $post_id ) {
    if ( 'my_score' === $col ) {
        echo (int) get_post_meta( $post_id, '_my_score', true );
    }
}, 10, 2 );
```

## Notify Slack when a job is posted

```php
add_action( 'wcb_job_created', function ( $job_id, $request ) {
    $title = get_the_title( $job_id );
    wp_remote_post( SLACK_WEBHOOK_URL, array(
        'body' => wp_json_encode( array(
            'text' => sprintf( 'New job posted: *%s*', $title ),
        ) ),
        'headers' => array( 'Content-Type' => 'application/json' ),
        'blocking' => false,
    ));
}, 10, 2 );
```

## Add a tab to the settings page

```php
add_filter( 'wcb_settings_tabs', function ( $tabs ) {
    $tabs['my_addon'] = __( 'My Addon', 'my-addon' );
    return $tabs;
});

add_action( 'wcb_settings_tab_my_addon', function () {
    settings_fields( 'my_addon_group' );
    do_settings_sections( 'my_addon_group' );
    submit_button();
});
```

## Override the credit cost for a specific board

```php
add_filter( 'wcb_board_credit_cost', function ( $cost, $board_id ) {
    if ( get_option( 'my_addon_premium_board' ) === $board_id ) {
        return 5; // Override the normal cost
    }
    return $cost;
}, 10, 2 );
```

## Inject a step into the post-a-job wizard

The 4-step wizard fires `wcb_job_form_step1_fields` …
`wcb_job_form_step4_preview` actions inside each step's container.
Adding a fifth step takes a JS-side hook too - but injecting
fields into an existing step is trivial:

```php
add_action( 'wcb_job_form_step3_fields', function () {
    ?>
    <div class="wcb-form-field">
        <label class="wcb-form-label">
            <?php esc_html_e( 'Industry sub-category', 'my-addon' ); ?>
        </label>
        <select name="my_addon_subcat">
            <option value="frontend">Frontend</option>
            <option value="backend">Backend</option>
        </select>
    </div>
    <?php
});
```

## Add a column to the REST jobs response

```php
add_filter( 'wcb_rest_prepare_job', function ( $row, $post ) {
    $row['my_remote_friendly'] = (bool) get_post_meta( $post->ID, '_remote_friendly', true );
    return $row;
}, 10, 2 );
```

This propagates everywhere the jobs API is consumed - the listings
block, the single-job page, third-party integrations.

## Disable a built-in feature

Most Pro features are gated by `wcb_pro_*_enabled` filters. To
turn off resume builder for a specific role:

```php
add_filter( 'wcb_pro_resumes_enabled', function ( $enabled ) {
    if ( current_user_can( 'wcb_employer' ) ) {
        return false; // Hide resume tab from employers
    }
    return $enabled;
});
```

## Customize the "Buy Credits" link

Different gateways for different user segments:

```php
add_filter( 'wcb_credit_purchase_url', function ( $url ) {
    if ( current_user_can( 'wcb_employer_premium' ) ) {
        return '/premium-credits/';
    }
    return $url;
});
```

## Restrict the boards dropdown by user role

```php
add_filter( 'wcb_board_options_for_employer', function ( $options, $user_id ) {
    if ( ! user_can( $user_id, 'wcb_post_to_premium_boards' ) ) {
        // Drop any board whose id is in the "premium" list.
        $premium_ids = (array) get_option( 'my_premium_board_ids', array() );
        $options = array_filter( $options, fn( $o ) => ! in_array( (int) $o['id'], $premium_ids, true ) );
    }
    return $options;
}, 10, 2 );
```

(Pro's BP-groups integration uses this same filter to drop boards
whose linked BuddyPress group the user is not a member of.)

## Add a custom transactional email

The email registry holds **email objects**, not config arrays.
Each one extends `WCB\Modules\Notifications\AbstractEmail`,
declares its identity, and wires its own trigger hook in `boot()`.
Register the object through the `wcb_registered_emails` filter and
it appears automatically in Settings -> Emails (subject override,
enable/disable toggle, and the send log all come for free).

```php
use WCB\Modules\Notifications\AbstractEmail;

class My_Welcome_Email extends AbstractEmail {

    public function get_id(): string {
        return 'my_addon_welcome';
    }

    public function get_title(): string {
        return __( 'Welcome to the board', 'my-addon' );
    }

    public function get_recipient(): string {
        return __( 'New candidates', 'my-addon' );
    }

    public function get_default_subject(): string {
        return __( 'Welcome to our job board', 'my-addon' );
    }

    public function boot(): void {
        // Trigger off any Career Board action hook. wcb_candidate_registered passes one argument.
        add_action( 'wcb_candidate_registered', array( $this, 'handle' ), 10, 1 );
    }

    public function handle( int $user_id ): void {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }
        // send() respects the per-template enable toggle and writes the log row.
        $this->send( $user->user_email, array( 'name' => $user->display_name ), $user_id );
    }
}

add_filter( 'wcb_registered_emails', function ( array $emails ): array {
    $emails[] = new My_Welcome_Email();
    return $emails;
});
```

The base class gives you `is_enabled()`, `get_subject()` (with the
admin override), and the protected `send( $to, $vars, $user_id, $context )`
helper that dispatches and logs. The five methods above are the only
abstract ones. Override `get_default_body()` (merge tags in `{braces}`)
and `get_merge_tags()` to ship a default message and the tag chips in
the editor, and `is_optional()` to let members turn the email off. Pass
a `$context` with `object_type`, `object_id` and `actor_id` when the
email is about a job or application, so notification centers get a row
(see the community notification contract in the hooks reference). Read
`modules/notifications/emails/class-email-job-approved.php` for a
complete working example.

## Change which jobs a search returns

`wcb_job_search_args` runs for every job search: the REST list, the listing block's first paint, the archive and alert matching. Put every value that changes the result in the query args, because the REST cache key is built from them.

```php
add_filter( 'wcb_job_search_args', function ( array $args, array $params ): array {
    // Show only remote jobs everywhere.
    $args['meta_query'][] = array(
        'key'   => '_wcb_remote',
        'value' => '1',
    );
    return $args;
}, 10, 2 );
```

## React to an application status change

`wcb_application_status_changed` fires once per real change, from every writer (dashboard, admin, REST, WP-CLI, withdrawing, closing a job). A save that keeps the same status fires nothing.

```php
add_action( 'wcb_application_status_changed', function ( $app_id, $old, $new, $reason, $actor ) {
    if ( 'hired' === $new ) {
        my_crm_mark_hired( (int) get_post_meta( $app_id, '_wcb_candidate_id', true ) );
    }
}, 10, 5 );
```

Use `wcb_application_status_updated` to keep data in step with the status, including silent changes such as a job closing. Never send a message from it.

## Add a step to the setup wizard

Add an entry with a `title`, a `template` path and a `button_text`. A step template that renders inputs named after settings-schema keys, plus the shared footer, saves with no JavaScript of its own. Register the key in `wcb_settings_schema` so it has a default and a cleaning rule.

```php
add_filter( 'wcb_settings_schema', function ( array $fields ): array {
    $fields['my_addon_flag'] = array(
        'default'  => false,
        'sanitize' => 'rest_sanitize_boolean',
    );
    return $fields;
} );

add_filter( 'wcb_wizard_steps', function ( array $steps ): array {
    $steps['my-addon'] = array(
        'title'       => __( 'My add-on', 'my-addon' ),
        'template'    => MY_ADDON_DIR . 'wizard-step.php',
        'button_text' => __( 'Save & Continue', 'my-addon' ),
    );
    return $steps;
} );
```

In `wizard-step.php`, print `<input type="checkbox" name="my_addon_flag">` and then `require WCB_DIR . 'admin/views/wizard-steps/_footer.php';`.

## Show extra details on the job page

`wcb_job_single_after_description` fires right after the description:

```php
add_action( 'wcb_job_single_after_description', function ( int $job_id ) {
    $note = get_post_meta( $job_id, '_my_addon_note', true );
    if ( $note ) {
        printf( '<p>%s</p>', esc_html( $note ) );
    }
} );
```

## Include your data in privacy exports and erasure

Register a provider once. It is used by Tools → Export/Erase Personal Data and by account deletion.

```php
add_filter( 'wcb_personal_data_providers', function ( array $providers ): array {
    $providers['my_addon'] = array(
        'label'  => __( 'My add-on', 'my-addon' ),
        'export' => function ( array $who ): array {
            // $who: array( 'user_id' => int, 'email' => string ); user_id is 0 for a guest.
            return array(); // WordPress export items.
        },
        'erase'  => function ( array $who ): array {
            return array( 'removed' => 0, 'retained' => 0, 'messages' => array() );
        },
    );
    return $providers;
} );
```

## Replace an email's message

Edit the body under **Settings → Emails**, or ship a file at `{theme}/wp-career-board/emails/{email-id}.php`. A body saved in Settings wins over a theme file, and a theme file wins over the shipped default. Add another template folder with the `wcb_email_template_dirs` filter.

## Where to find the rest

Read [02-hooks-reference.md](02-hooks-reference.md) for the full
inventory. For anything not covered by a hook, the next step is
to extend a Career Board class directly - see
[03-rest-api.md](03-rest-api.md) for the REST controller base
class and the WP-CLI section in
[04-wp-cli.md](04-wp-cli.md) for the CLI base class.
