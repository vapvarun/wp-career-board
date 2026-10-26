<?php
/**
 * Default board tests (1.8.2).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-default-board.php
 *
 * Pins: every job stores its board. A job saved without one (wp-admin,
 * import, wp_insert_post) gets the default board on save, a chosen board is
 * kept, and the 1.3.9 upgrade stores the default board on older jobs that
 * have none, so the board filter finds them.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Core\Install;
use WCB\Modules\Boards\BoardsModule;

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_db_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

WP_CLI::log( 'Default board' );

$wcb_default = BoardsModule::get_default_board_id();
if ( $wcb_default <= 0 ) {
	WP_CLI::error( 'No default board on this site.' );
}

$wcb_other = (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_board',
		'post_status' => 'publish',
		'post_title'  => 'DB test board',
	)
);
$wcb_job   = static function ( array $meta = array() ): int {
	return (int) wp_insert_post(
		array(
			'post_type'   => 'wcb_job',
			'post_status' => 'publish',
			'post_title'  => 'DB test job ' . wp_generate_password( 4, false ),
			'meta_input'  => $meta,
		)
	);
};

$wcb_plain  = $wcb_job();
$wcb_chosen = $wcb_job( array( '_wcb_board_id' => $wcb_other ) );
wcb_db_assert( $wcb_default === (int) get_post_meta( $wcb_plain, '_wcb_board_id', true ), 'a job saved without a board stores the default board' );
wcb_db_assert( $wcb_other === (int) get_post_meta( $wcb_chosen, '_wcb_board_id', true ), 'a job saved with a board keeps it' );

// Older jobs: no meta, or an empty one.
delete_post_meta( $wcb_plain, '_wcb_board_id' );
global $wpdb;
$wcb_empty = $wcb_job();
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- test fixture: an empty stored board as older code left it.
$wpdb->update( $wpdb->postmeta, array( 'meta_value' => '' ), array( 'post_id' => $wcb_empty, 'meta_key' => '_wcb_board_id' ) );
wp_cache_delete( $wcb_empty, 'post_meta' );
$wcb_version = (string) get_option( 'wcb_db_version' );
update_option( 'wcb_db_version', '1.3.8', false );
Install::maybe_migrate();
wcb_db_assert( $wcb_default === (int) get_post_meta( $wcb_plain, '_wcb_board_id', true ), 'the upgrade stores the default board on a job with none' );
wcb_db_assert( $wcb_default === (int) get_post_meta( $wcb_empty, '_wcb_board_id', true ), 'the upgrade fixes a stored empty board' );
wcb_db_assert( $wcb_other === (int) get_post_meta( $wcb_chosen, '_wcb_board_id', true ), 'the upgrade leaves a chosen board alone' );
wcb_db_assert( version_compare( (string) get_option( 'wcb_db_version' ), $wcb_version, '>=' ), 'the upgrade records the DB version' );

// The board filter finds them.
$wcb_res  = rest_do_request( new WP_REST_Request( 'GET', '/wcb/v1/jobs' ) );
$wcb_req  = new WP_REST_Request( 'GET', '/wcb/v1/jobs' );
$wcb_req->set_query_params( array( 'board' => $wcb_other ) );
$wcb_data = (array) rest_do_request( $wcb_req )->get_data();
$wcb_ids  = array_map( static fn ( $j ) => (int) ( $j['id'] ?? 0 ), (array) ( $wcb_data['jobs'] ?? array() ) );
wcb_db_assert( array( $wcb_chosen ) === $wcb_ids, 'the board filter returns the board\'s jobs only' );
$wcb_req->set_query_params( array( 'board' => $wcb_default, 'per_page' => 100 ) );
$wcb_data = (array) rest_do_request( $wcb_req )->get_data();
$wcb_ids  = array_map( static fn ( $j ) => (int) ( $j['id'] ?? 0 ), (array) ( $wcb_data['jobs'] ?? array() ) );
wcb_db_assert( in_array( $wcb_plain, $wcb_ids, true ) || (int) ( $wcb_data['total'] ?? 0 ) > 100, 'the default board filter finds a job that had no board' );

// Cleanup.
foreach ( array( $wcb_plain, $wcb_chosen, $wcb_empty, $wcb_other ) as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All default board tests passed.' );
}
