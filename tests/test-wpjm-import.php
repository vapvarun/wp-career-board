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
if ( ! get_post_status_object( 'expired' ) ) {
	// WPJM registers it excluded from search, which is why 'any' never returns it.
	register_post_status( 'expired', array( 'public' => false, 'exclude_from_search' => true ) );
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

$wcb_depot_pre = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => "{$wcb_tag} Depot", 'post_author' => 1 ) );
$wcb_expired   = $wcb_source(
	"{$wcb_tag} Expired driver",
	array(
		'_company_name'    => "{$wcb_tag} Depot",
		'_company_website' => "https://{$wcb_tag}-depot.example",
	)
);
wp_update_post( array( 'ID' => $wcb_expired, 'post_status' => 'expired' ) );
$wcb_source_app = static function ( string $status, string $name, int $parent ) use ( $wcb_tag ): int {
	return (int) wp_insert_post(
		array(
			'post_type'   => 'job_application',
			'post_status' => $status,
			'post_title'  => $name,
			'post_parent' => $parent,
			'meta_input'  => array( '_candidate_email' => strtolower( str_replace( ' ', '', $name ) ) . "-{$wcb_tag}@example.test" ),
		)
	);
};
$wcb_app_new_on_filled   = $wcb_source_app( 'new', 'Undecided Una', $wcb_filled );
$wcb_app_hired_on_filled = $wcb_source_app( 'hired', 'Hired Hal', $wcb_filled );
$wcb_app_on_expired      = $wcb_source_app( 'new', 'Expired Eve', $wcb_expired );

$wcb_importer = new WpjmImporter();
$wcb_preview  = $wcb_importer->preview();
wcb_assert( $wcb_importer->wpjm_jobs_total() >= 3 && 1 <= count( get_posts( array( 'post_type' => 'job_listing', 'post_status' => WpjmImporter::job_statuses(), 'include' => array( $wcb_expired ), 'fields' => 'ids' ) ) ), 'the job count and the batch query both see an expired WPJM job' );
wcb_assert( array( 'expired' ) === WpjmImporter::job_statuses( 'expired' ) && in_array( 'expired', WpjmImporter::job_statuses( 'any' ), true ), 'the CLI can ask for expired jobs, and "any" includes them' );
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
wcb_assert( ! str_contains( \WCB\Core\SalaryFormat::format( get_post_meta( $wcb_job_filled, '_wcb_salary_min', true ), get_post_meta( $wcb_job_filled, '_wcb_salary_max', true ), 'USD', 'hourly' ), '–' ), 'a single WPJM salary shows as one figure, not a range of the same number' );
wcb_assert( 'publish' === get_post_status( $wcb_job_open ), 'an open WPJM job imports live' );
$wcb_job_expired = $wcb_new( $wcb_expired );
wcb_assert( (int) get_post_meta( $wcb_job_expired, '_wcb_company_id', true ) === $wcb_depot_pre && "https://{$wcb_tag}-depot.example" === get_post_meta( $wcb_depot_pre, '_wcb_website', true ), 'a company matched by name alone keeps its page and gains the website WPJM has' );
wcb_assert( $wcb_job_expired > 0 && 'wcb_expired' === get_post_status( $wcb_job_expired ), 'an expired WPJM job imports as Expired instead of being skipped' );
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

$wcb_app_of = static function ( int $source ): ?WP_Post {
	$found = get_posts( array( 'post_type' => 'wcb_application', 'post_status' => 'any', 'meta_key' => '_wcb_migrated_from', 'meta_value' => $source ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	return $found[0] ?? null;
};
$wcb_una = $wcb_app_of( $wcb_app_new_on_filled );
$wcb_hal = $wcb_app_of( $wcb_app_hired_on_filled );
$wcb_eve = $wcb_app_of( $wcb_app_on_expired );
wcb_assert( $wcb_una instanceof WP_Post && 'position_closed' === get_post_meta( $wcb_una->ID, '_wcb_status', true ), 'an undecided applicant on a filled (closed) job imports as Position closed' );
wcb_assert( $wcb_hal instanceof WP_Post && 'hired' === get_post_meta( $wcb_hal->ID, '_wcb_status', true ), 'a decided applicant on a filled job keeps their decision' );
wcb_assert( $wcb_eve instanceof WP_Post && (int) get_post_meta( $wcb_eve->ID, '_wcb_job_id', true ) === $wcb_job_expired, 'an application on an expired job imports onto it' );
// The close the job's import queued may run before or after the applications; it must change and announce nothing more.
\WCB\Modules\Applications\ApplicationLifecycle::close_job_applications( $wcb_job_filled, \WCB\Modules\Applications\ApplicationStatus::POSITION_CLOSED );
wcb_assert( 'position_closed' === get_post_meta( $wcb_una->ID, '_wcb_status', true ) && 'hired' === get_post_meta( $wcb_hal->ID, '_wcb_status', true ), 'the queued close finds nothing left to change' );
wcb_assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wcb_notifications_log WHERE id > %d", $wcb_mail_before ) ), 'no email goes out about an imported application, before or after the close runs' );

$wcb_again = $wcb_importer->migrate_applications_batch( 0, 500 );
wcb_assert( 0 === $wcb_again['imported'] && $wcb_again['skipped'] >= 1, 're-running skips what was imported' );
$wcb_jobs_again = $wcb_importer->migrate_jobs_batch( 0, 500 );
wcb_assert( 0 === $wcb_jobs_again['imported'], 're-running the jobs import does not duplicate a closed job' );

// Teardown.
$wcb_extra = array_filter( array( $wcb_una, $wcb_hal, $wcb_eve ) );
$wcb_depot = (int) get_post_meta( $wcb_job_expired, '_wcb_company_id', true );
foreach ( array_merge( $wcb_apps, $wcb_extra, array( $wcb_job_filled, $wcb_job_open, $wcb_job_expired, $wcb_company, $wcb_depot, $wcb_filled, $wcb_open, $wcb_expired, $wcb_app_src, $wcb_app_new_on_filled, $wcb_app_hired_on_filled, $wcb_app_on_expired ) ) as $wcb_id ) {
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
