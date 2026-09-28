<?php
/**
 * WP Job Manager import tests (1.8.0, W17).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-wpjm-import.php
 *
 * Registers WPJM's post types for the run (the plugin need not be active)
 * and pins: a filled job imports closed, not live; HOUR pay becomes hourly;
 * the company becomes a company page with its website, reused by a second
 * job from the same company; tags come across; an application lands on the
 * imported job with its status and no email; the preview counts it all;
 * re-running skips.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Import\WpjmImporter;

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

global $wpdb;
foreach ( array( 'job_listing', 'job_application' ) as $wcb_pt ) {
	if ( ! post_type_exists( $wcb_pt ) ) {
		register_post_type( $wcb_pt, array( 'public' => false ) );
	}
}
if ( ! taxonomy_exists( 'job_listing_tag' ) ) {
	register_taxonomy( 'job_listing_tag', 'job_listing' );
}

$wcb_tag     = 'wpjm' . strtolower( wp_generate_password( 5, false, false ) );
$wcb_site    = "https://{$wcb_tag}.example";
$wcb_source  = static function ( string $title, array $meta ): int {
	return (int) wp_insert_post(
		array(
			'post_type'   => 'job_listing',
			'post_status' => 'publish',
			'post_title'  => $title,
			'post_author' => 1,
			'meta_input'  => $meta,
		)
	);
};
$wcb_filled  = $wcb_source(
	"{$wcb_tag} Filled nurse",
	array(
		'_filled'          => '1',
		'_job_salary'      => '32',
		'_job_salary_unit' => 'HOUR',
		'_company_name'    => "{$wcb_tag} Clinic",
		'_company_website' => $wcb_site,
		'_company_tagline' => 'Care first',
		'_job_expires'     => gmdate( 'Y-m-d', strtotime( '+10 days' ) ),
	)
);
$wcb_open    = $wcb_source(
	"{$wcb_tag} Open porter",
	array(
		'_job_salary'      => '3000',
		'_job_salary_unit' => 'MONTH',
		'_company_name'    => "{$wcb_tag} Clinic",
		'_company_website' => $wcb_site,
	)
);
wp_set_object_terms( $wcb_open, array( "{$wcb_tag}-night" ), 'job_listing_tag' );
$wcb_app_src = (int) wp_insert_post(
	array(
		'post_type'    => 'job_application',
		'post_status'  => 'interviewed',
		'post_title'   => 'Pat Applicant',
		'post_content' => 'I would like this job.',
		'post_parent'  => $wcb_open,
		'meta_input'   => array(
			'_candidate_email' => "pat-{$wcb_tag}@example.test",
			'_attachment'      => array( "https://{$wcb_tag}.example/cv.pdf" ),
		),
	)
);

$wcb_importer = new WpjmImporter();
$wcb_preview  = $wcb_importer->preview();
wcb_assert( $wcb_preview['filled'] >= 1 && $wcb_preview['companies_new'] >= 1 && $wcb_preview['applications'] >= 1, 'the preview counts filled jobs, new companies and applications' );

$wcb_mail_before = (int) $wpdb->get_var( "SELECT COALESCE( MAX(id), 0 ) FROM {$wpdb->prefix}wcb_notifications_log" );
$wcb_importer->migrate_jobs_batch( 0, 500 );
$wcb_new = static function ( int $source ): int {
	$ids = get_posts( array( 'post_type' => 'wcb_job', 'post_status' => array_keys( get_post_stati() ), 'fields' => 'ids', 'meta_key' => '_wcb_migrated_from', 'meta_value' => $source ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	return (int) ( $ids[0] ?? 0 );
};
$wcb_job_filled = $wcb_new( $wcb_filled );
$wcb_job_open   = $wcb_new( $wcb_open );

wcb_assert( 'wcb_closed' === get_post_status( $wcb_job_filled ), 'a filled WPJM job imports closed, not live' );
wcb_assert( 'publish' === get_post_status( $wcb_job_open ), 'an open WPJM job imports live' );
wcb_assert( 'hourly' === get_post_meta( $wcb_job_filled, '_wcb_salary_type', true ) && 'monthly' === get_post_meta( $wcb_job_open, '_wcb_salary_type', true ), 'HOUR and MONTH pay become hourly and monthly' );
$wcb_company = (int) get_post_meta( $wcb_job_filled, '_wcb_company_id', true );
wcb_assert( $wcb_company > 0 && 'wcb_company' === get_post_type( $wcb_company ) && $wcb_site === get_post_meta( $wcb_company, '_wcb_website', true ) && 'Care first' === get_post_meta( $wcb_company, '_wcb_tagline', true ), 'the company becomes a company page with website and tagline' );
wcb_assert( (int) get_post_meta( $wcb_job_open, '_wcb_company_id', true ) === $wcb_company, 'a second job from the same company reuses its page' );
wcb_assert( has_term( "{$wcb_tag}-night", 'wcb_tag', $wcb_job_open ), 'tags come across' );

$wcb_importer->migrate_applications_batch( 0, 500 );
$wcb_apps = get_posts( array( 'post_type' => 'wcb_application', 'post_status' => 'any', 'meta_key' => '_wcb_migrated_from', 'meta_value' => $wcb_app_src ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
$wcb_app  = $wcb_apps[0] ?? null;
wcb_assert( $wcb_app instanceof WP_Post && (int) get_post_meta( $wcb_app->ID, '_wcb_job_id', true ) === $wcb_job_open, 'the application lands on the imported job' );
wcb_assert( $wcb_app instanceof WP_Post && 'shortlisted' === get_post_meta( $wcb_app->ID, '_wcb_status', true ) && 'Pat Applicant' === get_post_meta( $wcb_app->ID, '_wcb_guest_name', true ) && str_contains( (string) get_post_meta( $wcb_app->ID, '_wcb_cover_letter', true ), 'cv.pdf' ), '"interviewed" becomes Shortlisted; guest name, message and file link kept' );
wcb_assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wcb_notifications_log WHERE id > %d", $wcb_mail_before ) ), 'the import sends no email' );

$wcb_again = $wcb_importer->migrate_applications_batch( 0, 500 );
wcb_assert( 0 === $wcb_again['imported'] && $wcb_again['skipped'] >= 1, 're-running skips what was imported' );
$wcb_jobs_again = $wcb_importer->migrate_jobs_batch( 0, 500 );
wcb_assert( 0 === $wcb_jobs_again['imported'], 're-running the jobs import does not duplicate a closed job' );

// Teardown.
foreach ( array_merge( $wcb_apps, array( $wcb_job_filled, $wcb_job_open, $wcb_company, $wcb_filled, $wcb_open, $wcb_app_src ) ) as $wcb_id ) {
	wp_delete_post( is_object( $wcb_id ) ? $wcb_id->ID : (int) $wcb_id, true );
}
foreach ( array( 'wcb_tag', 'job_listing_tag' ) as $wcb_tax ) {
	$wcb_term = get_term_by( 'slug', "{$wcb_tag}-night", $wcb_tax );
	if ( $wcb_term ) {
		wp_delete_term( $wcb_term->term_id, $wcb_tax );
	}
}
delete_user_meta( 1, '_wcb_company_id', $wcb_company );

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All WPJM import tests passed.' );
}
