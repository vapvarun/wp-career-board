<?php
/**
 * A second "Remote" place term folds into the built-in one (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-remote-term-merge.php
 *
 * Pins: jobs on a wizard-seeded "Remote" term (a second slug, as on a translated
 * site) move to the built-in `remote` term, the duplicate is deleted, and other
 * places are untouched.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

$keep_created = false;
$keep         = get_term_by( 'slug', 'remote', 'wcb_location' );
if ( ! $keep instanceof WP_Term ) {
	$made         = wp_insert_term( 'Remoto', 'wcb_location', array( 'slug' => 'remote' ) );
	$keep         = get_term( (int) $made['term_id'], 'wcb_location' );
	$keep_created = true;
}
$dupe  = wp_insert_term( 'Remote', 'wcb_location', array( 'slug' => 'remote-2' ) );
$dupe  = (int) ( is_wp_error( $dupe ) ? $dupe->get_error_data() : $dupe['term_id'] );
$paris = wp_insert_term( 'Paris QA', 'wcb_location' );
$paris = (int) ( is_wp_error( $paris ) ? $paris->get_error_data() : $paris['term_id'] );
$job_a = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'draft', 'post_title' => 'QA remote merge A' ) );
$job_b = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'draft', 'post_title' => 'QA remote merge B' ) );
wp_set_object_terms( $job_a, array( $dupe ), 'wcb_location' );
wp_set_object_terms( $job_b, array( $paris ), 'wcb_location' );

\WCB\Core\Install::merge_duplicate_remote_terms();

$ok( null === get_term( $dupe, 'wcb_location' ) || is_wp_error( get_term( $dupe, 'wcb_location' ) ), 'the duplicate Remote term is gone' );
$ok( has_term( (int) $keep->term_id, 'wcb_location', $job_a ), 'a job on the duplicate now sits on the built-in remote term' );
$ok( has_term( $paris, 'wcb_location', $job_b ) && ! has_term( (int) $keep->term_id, 'wcb_location', $job_b ), 'a job on another place is untouched' );

// Running it again changes nothing.
\WCB\Core\Install::merge_duplicate_remote_terms();
$ok( has_term( (int) $keep->term_id, 'wcb_location', $job_a ), 'running it twice is harmless' );

wp_delete_post( $job_a, true );
wp_delete_post( $job_b, true );
wp_delete_term( $paris, 'wcb_location' );
if ( $keep_created ) {
	wp_delete_term( (int) $keep->term_id, 'wcb_location' );
}

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Remote term merge tests passed.' );
