<?php
/**
 * A job with no location still publishes on every path; only the Google feed skips it (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-location-optional.php
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

wp_set_current_user( 1 );
$job = static fn ( string $status, string $title ): int => (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => $status,
		'post_title'  => $title,
		'post_author' => 1,
	)
);
$st  = static fn ( int $id ): string => (string) get_post_status( $id );
$ids = array();

// Create as published.
$ids[] = $a = $job( 'publish', 'Senior Product Designer' );
$ok( 'publish' === $st( $a ), 'wp_insert_post publish with no location stays published' );

// Draft then update, Quick Edit and Bulk Edit path.
$ids[] = $b = $job( 'draft', 'Data Analyst' );
wp_update_post( array( 'ID' => $b, 'post_status' => 'publish' ) );
$ok( 'publish' === $st( $b ), 'wp_update_post draft to publish stays published' );

// wp_publish_post writes the status by a direct DB update.
$ids[] = $c = $job( 'draft', 'Support Engineer' );
wp_publish_post( $c );
$ok( 'publish' === $st( $c ), 'wp_publish_post stays published' );

// A pending job approved.
$ids[] = $d = $job( 'pending', 'Marketing Lead' );
wp_update_post( array( 'ID' => $d, 'post_status' => 'publish' ) );
$ok( 'publish' === $st( $d ), 'a pending job moves to published' );

// A scheduled job going live.
$ids[] = $e = $job( 'draft', 'Recruiter' );
$when = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
wp_update_post(
	array(
		'ID'            => $e,
		'post_status'   => 'future',
		'post_date'     => get_date_from_gmt( $when ),
		'post_date_gmt' => $when,
		'edit_date'     => true,
	)
);
$ok( 'future' === $st( $e ), 'scheduling with no location stays scheduled' );
wp_publish_post( $e );
$ok( 'publish' === $st( $e ), 'the scheduled job goes live' );

// The feed leaves it out, and the setting is gone.
$ok( array() === \WCB\Modules\Seo\SeoModule::job_posting( get_post( $a ) ), 'the Google for Jobs feed leaves the job out' );
$ok( ! array_key_exists( 'require_job_location', \WCB\Admin\SettingsSchema::fields() ), 'no require_job_location setting remains' );
$ok( ! has_filter( 'wp_insert_post_data', array( \WCB\Core\Locations::class, 'guard_publish' ) ), 'no publish guard is hooked' );

foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Location-optional tests passed.' );
