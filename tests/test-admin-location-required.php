<?php
/**
 * "Require a location" in the admin job editor (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-admin-location-required.php
 *
 * Pins: with the setting on, a job moving into Published from the admin editor
 * without a location (term, Remote or typed) is saved as a draft, the same rule
 * REST and the frontend forms enforce; a job already published stays editable.
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
function wcb_alr_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

/**
 * Save a job the way the admin editor does: nonce posted, status Published.
 *
 * @param array $extra wp_insert_post / wp_update_post args to add.
 * @return int Job ID.
 */
function wcb_alr_save( array $extra = array() ): int {
	return (int) wp_insert_post(
		array_merge(
			array(
				'post_type'   => 'wcb_job',
				'post_status' => 'publish',
				'post_title'  => 'ALR Job',
				'post_author' => 1,
			),
			$extra
		)
	);
}

// Booted by Admin on wp-admin requests only; WP-CLI is not one.
( new \WCB\Admin\AdminMetaBoxes() )->boot();
wp_set_current_user( 1 );
$wcb_old = get_option( 'wcb_settings', array() );
update_option( 'wcb_settings', array_merge( (array) $wcb_old, array( 'require_job_location' => true ) ) );
$_POST['wcb_job_meta_nonce'] = wp_create_nonce( 'wcb_job_meta_boxes' );

$wcb_term = wp_insert_term( 'ALR City ' . wp_generate_password( 6, false ), 'wcb_location' );
$wcb_tid  = (int) $wcb_term['term_id'];
$wcb_ids  = array();

WP_CLI::log( '' );
WP_CLI::log( '  Admin Editor: Require a Location Tests' );

$wcb_ids[] = $wcb_none = wcb_alr_save();
wcb_alr_assert( 'draft' === get_post_status( $wcb_none ), 'no location, setting on: saved as a draft' );

$wcb_ids[] = $wcb_loc = wcb_alr_save( array( 'tax_input' => array( 'wcb_location' => array( $wcb_tid ) ) ) );
wcb_alr_assert( 'publish' === get_post_status( $wcb_loc ), 'a location term: publishes' );

$_POST['wcb_remote'] = '1';
$wcb_ids[]           = $wcb_rem = wcb_alr_save();
wcb_alr_assert( 'publish' === get_post_status( $wcb_rem ), 'Remote ticked: publishes' );
unset( $_POST['wcb_remote'] );

$wcb_ids[] = $wcb_pub = wcb_alr_save( array( 'tax_input' => array( 'wcb_location' => array( $wcb_tid ) ) ) );
wp_set_object_terms( $wcb_pub, array(), 'wcb_location' );
wp_update_post( array( 'ID' => $wcb_pub, 'post_title' => 'ALR Job renamed' ) );
wcb_alr_assert( 'publish' === get_post_status( $wcb_pub ), 'already published, location removed later: stays published' );

update_option( 'wcb_settings', array_merge( (array) $wcb_old, array( 'require_job_location' => false ) ) );
$wcb_ids[] = $wcb_off = wcb_alr_save();
wcb_alr_assert( 'publish' === get_post_status( $wcb_off ), 'setting off: publishes without a location' );

foreach ( $wcb_ids as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}
wp_delete_term( $wcb_tid, 'wcb_location' );
update_option( 'wcb_settings', $wcb_old );
unset( $_POST['wcb_job_meta_nonce'] );

WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
}
WP_CLI::success( 'All admin location tests passed.' );
