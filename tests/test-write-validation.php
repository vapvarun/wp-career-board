<?php
/**
 * Write validation tests (1.8.2).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-write-validation.php
 *
 * Pins: a job, company, resume or board saved untitled from wp-admin or code
 * stays a draft, and the block editor gets an error; an already-live record
 * keeps its status. The REST routes refuse an empty job title or company
 * name, a minimum salary above the maximum, and a required custom field
 * left empty (values already saved count). `remote` "false" means not
 * remote; `location_custom` on its own replaces the job's locations; a
 * reversed salary written outside REST is put low-high; per_page above 100
 * is a 400, as on WordPress's own routes.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_wv_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

/**
 * Dispatch a REST request as the current user.
 *
 * @param string $method Method.
 * @param string $route  Route.
 * @param array  $params Body or query params.
 * @return WP_REST_Response
 */
function wcb_wv_rest( string $method, string $route, array $params = array() ): WP_REST_Response {
	$request = new WP_REST_Request( $method, $route );
	if ( 'GET' === $method ) {
		$request->set_query_params( $params );
	} else {
		$request->set_body_params( $params );
	}
	return rest_do_request( $request );
}

WP_CLI::log( 'Write validation' );
wp_set_current_user( 1 );

$wcb_settings = static fn ( $v ) => array_merge( (array) $v, array( 'require_job_location' => false ) );
add_filter( 'option_wcb_settings', $wcb_settings );
\WCB\Admin\Settings::flush_cache();

$wcb_posts = array();

// Untitled records stay drafts.
foreach ( \WCB\Core\RequiredTitle::TYPES as $wcb_type ) {
	if ( ! post_type_exists( $wcb_type ) ) {
		continue;
	}
	$wcb_id      = (int) wp_insert_post(
		array(
			'post_type'   => $wcb_type,
			'post_status' => 'publish',
			'post_title'  => '',
		)
	);
	$wcb_posts[] = $wcb_id;
	wcb_wv_assert( 'draft' === get_post_status( $wcb_id ), "an untitled {$wcb_type} published from code is saved as a draft" );
}

// The block editor (wp/v2) gets the refusal.
$wcb_rest_base = get_post_type_object( 'wcb_company' )->rest_base ?: 'wcb_company';
$wcb_res       = wcb_wv_rest(
	'POST',
	'/wp/v2/' . $wcb_rest_base,
	array(
		'title'  => '',
		'status' => 'publish',
	)
);
wcb_wv_assert( 400 === $wcb_res->get_status() && 'wcb_missing_title' === ( $wcb_res->get_data()['code'] ?? '' ), 'the block editor gets "Add a title" for an untitled company' );

// A record that was already live keeps its status.
$wcb_live    = (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'publish',
		'post_title'  => 'WV live job',
	)
);
$wcb_posts[] = $wcb_live;
global $wpdb;
$wpdb->update( $wpdb->posts, array( 'post_title' => '' ), array( 'ID' => $wcb_live ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
clean_post_cache( $wcb_live );
wp_update_post(
	array(
		'ID'           => $wcb_live,
		'post_content' => 'edited',
	)
);
wcb_wv_assert( 'publish' === get_post_status( $wcb_live ), 'an untitled job that was already live stays live' );
wp_update_post(
	array(
		'ID'         => $wcb_live,
		'post_title' => 'WV live job',
	)
);

// REST: empty title and company name.
$wcb_res = wcb_wv_rest( 'PATCH', '/wcb/v1/jobs/' . $wcb_live, array( 'title' => '  ' ) );
wcb_wv_assert( 400 === $wcb_res->get_status() && 'WV live job' === get_the_title( $wcb_live ), 'PATCH title "" is a 400 and the title is kept' );
$wcb_company = (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_company',
		'post_status' => 'publish',
		'post_title'  => 'WV Company',
	)
);
$wcb_posts[] = $wcb_company;
$wcb_res     = wcb_wv_rest( 'PATCH', '/wcb/v1/employers/' . $wcb_company, array( 'name' => '' ) );
wcb_wv_assert( 400 === $wcb_res->get_status() && 'WV Company' === get_the_title( $wcb_company ), 'PATCH company name "" is a 400 and the name is kept' );

// remote "false".
$wcb_res     = wcb_wv_rest(
	'POST',
	'/wcb/v1/jobs',
	array(
		'title'       => 'WV remote',
		'description' => 'x',
		'remote'      => 'false',
	)
);
$wcb_remote  = (int) ( $wcb_res->get_data()['id'] ?? 0 );
$wcb_posts[] = $wcb_remote;
wcb_wv_assert( '0' === get_post_meta( $wcb_remote, '_wcb_remote', true ), 'create with remote "false" stores not remote' );
update_post_meta( $wcb_remote, '_wcb_remote', '1' );
wcb_wv_rest( 'PATCH', '/wcb/v1/jobs/' . $wcb_remote, array( 'remote' => 'false' ) );
wcb_wv_assert( '0' === get_post_meta( $wcb_remote, '_wcb_remote', true ), 'PATCH remote "false" stores not remote' );

// location_custom on its own replaces.
$wcb_loc = wp_insert_term( 'WV Old City', 'wcb_location' );
wp_set_object_terms( $wcb_remote, array( (int) $wcb_loc['term_id'] ), 'wcb_location' );
wcb_wv_rest( 'PATCH', '/wcb/v1/jobs/' . $wcb_remote, array( 'location_custom' => 'WV New City' ) );
$wcb_names = wp_get_object_terms( $wcb_remote, 'wcb_location', array( 'fields' => 'names' ) );
wcb_wv_assert( array( 'WV New City' ) === $wcb_names, 'PATCH location_custom replaces the job\'s locations' );

// Salary range.
$wcb_res = wcb_wv_rest(
	'POST',
	'/wcb/v1/jobs',
	array(
		'title'       => 'WV salary',
		'description' => 'x',
		'salary_min'  => 90000,
		'salary_max'  => 50000,
	)
);
if ( ! empty( $wcb_res->get_data()['id'] ) ) {
	$wcb_posts[] = (int) $wcb_res->get_data()['id'];
}
wcb_wv_assert( 400 === $wcb_res->get_status() && 'wcb_salary_range' === ( $wcb_res->get_data()['code'] ?? '' ), 'create with min above max is a 400' );
update_post_meta( $wcb_remote, '_wcb_salary_max', '60000' );
$wcb_res = wcb_wv_rest( 'PATCH', '/wcb/v1/jobs/' . $wcb_remote, array( 'salary_min' => 70000 ) );
wcb_wv_assert( 400 === $wcb_res->get_status(), 'PATCH of the minimum alone is checked against the stored maximum' );
update_post_meta( $wcb_remote, '_wcb_salary_min', '90000' );
update_post_meta( $wcb_remote, '_wcb_salary_max', '50000' );
\WCB\Modules\Jobs\JobsMeta::order_salary( $wcb_remote );
wcb_wv_assert( '50000' === get_post_meta( $wcb_remote, '_wcb_salary_min', true ) && '90000' === get_post_meta( $wcb_remote, '_wcb_salary_max', true ), 'a reversed pair saved by the admin editor or an import is put low-high' );

// Upgrade repair of older reversed pairs.
$wcb_old = (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'publish',
		'post_title'  => 'WV old salary',
	)
);
$wcb_posts[] = $wcb_old;
// phpcs:disable WordPress.DB.DirectDatabaseQuery
$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $wcb_old, 'meta_key' => '_wcb_salary_min', 'meta_value' => '80000' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $wcb_old, 'meta_key' => '_wcb_salary_max', 'meta_value' => '30000' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
// phpcs:enable WordPress.DB.DirectDatabaseQuery
wp_cache_delete( $wcb_old, 'post_meta' );
$wcb_migrate = new ReflectionMethod( \WCB\Core\Install::class, 'migrate_reversed_salaries' );
$wcb_migrate->setAccessible( true );
$wcb_migrate->invoke( null );
wcb_wv_assert( '30000' === get_post_meta( $wcb_old, '_wcb_salary_min', true ) && '80000' === get_post_meta( $wcb_old, '_wcb_salary_max', true ), 'the 1.4.1 upgrade puts an older reversed pair low-high' );

// per_page above 100.
wcb_wv_assert( 400 === wcb_wv_rest( 'GET', '/wcb/v1/jobs', array( 'per_page' => 101 ) )->get_status(), 'per_page 101 is a 400, like WordPress\'s own routes' );

// Required custom fields.
$wcb_group  = array(
	array(
		'label'  => 'WV group',
		'fields' => array(
			array(
				'key'      => 'wv_req',
				'type'     => 'text',
				'label'    => 'WV required',
				'required' => 1,
			),
		),
	),
);
$wcb_fields = static fn ( $groups ) => array_merge( (array) $groups, $wcb_group );
foreach ( array( 'wcb_job_form_fields', 'wcb_company_form_fields', 'wcb_candidate_form_fields' ) as $wcb_hook ) {
	add_filter( $wcb_hook, $wcb_fields );
}
$wcb_res = wcb_wv_rest(
	'POST',
	'/wcb/v1/jobs',
	array(
		'title'         => 'WV required job',
		'description'   => 'x',
		'custom_fields' => array(),
	)
);
if ( ! empty( $wcb_res->get_data()['id'] ) ) {
	$wcb_posts[] = (int) $wcb_res->get_data()['id'];
}
wcb_wv_assert( 400 === $wcb_res->get_status() && 'wcb_required_fields' === ( $wcb_res->get_data()['code'] ?? '' ), 'a job without a required custom field is a 400' );
$wcb_res = wcb_wv_rest(
	'POST',
	'/wcb/v1/jobs',
	array(
		'title'         => 'WV required job',
		'description'   => 'x',
		'custom_fields' => array( 'wv_req' => 'yes' ),
	)
);
$wcb_req_job = (int) ( $wcb_res->get_data()['id'] ?? 0 );
$wcb_posts[] = $wcb_req_job;
wcb_wv_assert( $wcb_req_job > 0, 'a job with the required field is created' );
$wcb_res = wcb_wv_rest(
	'PATCH',
	'/wcb/v1/jobs/' . $wcb_req_job,
	array(
		'title'         => 'WV required job 2',
		'custom_fields' => array( 'other' => 'x' ),
	)
);
wcb_wv_assert( 200 === $wcb_res->get_status(), 'an update that leaves the saved required value alone passes' );
$wcb_res = wcb_wv_rest( 'PATCH', '/wcb/v1/jobs/' . $wcb_req_job, array( 'custom_fields' => array( 'wv_req' => '' ) ) );
wcb_wv_assert( 400 === $wcb_res->get_status(), 'an update that clears the required value is a 400' );
$wcb_res = wcb_wv_rest( 'PATCH', '/wcb/v1/employers/' . $wcb_company, array( 'custom_fields' => array() ) );
wcb_wv_assert( 400 === $wcb_res->get_status() && 'wcb_required_fields' === ( $wcb_res->get_data()['code'] ?? '' ), 'a company profile without a required custom field is a 400' );
$wcb_res = wcb_wv_rest( 'PATCH', '/wcb/v1/candidates/1', array( 'custom_fields' => array() ) );
wcb_wv_assert( 400 === $wcb_res->get_status(), 'a candidate profile without a required custom field is a 400' );
foreach ( array( 'wcb_job_form_fields', 'wcb_company_form_fields', 'wcb_candidate_form_fields' ) as $wcb_hook ) {
	remove_filter( $wcb_hook, $wcb_fields );
}

// Cleanup.
remove_filter( 'option_wcb_settings', $wcb_settings );
\WCB\Admin\Settings::flush_cache();
foreach ( array_filter( $wcb_posts ) as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}
foreach ( array( 'WV Old City', 'WV New City' ) as $wcb_name ) {
	$wcb_term = get_term_by( 'name', $wcb_name, 'wcb_location' );
	if ( $wcb_term ) {
		wp_delete_term( $wcb_term->term_id, 'wcb_location' );
	}
}

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All write validation tests passed.' );
}
