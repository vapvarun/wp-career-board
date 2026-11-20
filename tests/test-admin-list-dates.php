<?php
/**
 * Admin list dates use the one date formatter (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-admin-list-dates.php
 *
 * Pins: the Date column of the Jobs, Companies and Applications lists prints
 * Time::date_label() of the post's UTC time in the site date format, the same
 * date the front end shows, even when the site timezone is far from UTC. A
 * draft (no GMT date yet) still shows its date, not a dash.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Core\Time;

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
wp_set_current_user( 1 );

$tz_was  = get_option( 'timezone_string' );
$off_was = get_option( 'gmt_offset' );
$fmt_was = get_option( 'date_format' );
update_option( 'timezone_string', 'UTC' );
update_option( 'date_format', 'F j, Y' );

$gmt   = '2026-03-10 23:30:00'; // Posted just before midnight UTC.
$posts = array(
	'AdminJobs'         => array( 'wcb_job', 'Lead Brewer, Harbour Street Taproom' ),
	'AdminCompanies'    => array( 'wcb_company', 'Harbour Street Brewing Co.' ),
	'AdminApplications' => array( 'wcb_application', 'Application: Lead Brewer' ),
);
$ids   = array();
foreach ( $posts as $class => $spec ) {
	$ids[ $class ] = (int) wp_insert_post(
		array(
			'post_type'     => $spec[0],
			'post_status'   => 'publish',
			'post_title'    => $spec[1],
			'post_date_gmt' => $gmt,
			'post_author'   => 1,
		)
	);
}

// The site moves to UTC+14: on the front end the record now reads March 11.
update_option( 'timezone_string', 'Pacific/Kiritimati' );
$front = Time::date_label( $gmt );
$ok( 'March 11, 2026' === $front, 'setup: the front-end formatter shows March 11 at UTC+14 (got ' . $front . ')' );

foreach ( $ids as $class => $id ) {
	$fqcn  = '\\WCB\\Admin\\' . $class;
	$table = ( new ReflectionClass( $fqcn ) )->newInstanceWithoutConstructor();
	$cell  = ( new ReflectionMethod( $fqcn, 'column_date' ) )->invoke( $table, get_post( $id ) );
	$ok( $front === $cell, $class . ' Date column matches the front end (got ' . $cell . ')' );
}

$draft = (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'draft',
		'post_title'  => 'Cellar Hand (draft)',
	)
);
$table = ( new ReflectionClass( '\WCB\Admin\AdminJobs' ) )->newInstanceWithoutConstructor();
$cell  = ( new ReflectionMethod( '\WCB\Admin\AdminJobs', 'column_date' ) )->invoke( $table, get_post( $draft ) );
$ok( Time::date_label( time() ) === $cell, 'a draft (zero GMT date) shows today, not a dash (got ' . $cell . ')' );

foreach ( array_merge( array_values( $ids ), array( $draft ) ) as $id ) {
	wp_delete_post( $id, true );
}
update_option( 'timezone_string', $tz_was );
update_option( 'gmt_offset', $off_was );
update_option( 'date_format', $fmt_was );

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Admin list date tests passed.' );
