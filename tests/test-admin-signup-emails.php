<?php
/**
 * Admin sign-up emails (1.8.4): new employer on by default, new candidate off.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-admin-signup-emails.php
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

global $wpdb;
$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};
$sent = static fn ( int $since, string $event ): int => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wcb_notifications_log WHERE id > %d AND event_type = %s", $since, $event ) );
$last = static fn (): int => (int) $wpdb->get_var( "SELECT COALESCE( MAX(id), 0 ) FROM {$wpdb->prefix}wcb_notifications_log" );

$old = get_option( 'wcb_settings' );
$set = (array) $old;
unset( $set['emails']['new-employer'], $set['emails']['new-candidate'] );
update_option( 'wcb_settings', $set );

$user = (int) wp_insert_user( array( 'user_login' => 'ase_' . wp_generate_password( 6, false ), 'user_pass' => wp_generate_password(), 'user_email' => 'ase' . wp_rand() . '@example.com', 'display_name' => 'ASE Member' ) );

$emp = new \WCB\Modules\Notifications\Emails\EmailNewEmployer();
$can = new \WCB\Modules\Notifications\Emails\EmailNewCandidate();
$ok( 'admin' === $emp->get_recipient() && 'admin' === $can->get_recipient(), 'both go to the admin' );
$ok( true === $emp->is_enabled(), 'new employer is on by default' );
$ok( false === $can->is_enabled(), 'new candidate is off by default' );

$ids = array_map( static fn ( $e ): string => $e->get_id(), (array) apply_filters( 'wcb_registered_emails', array() ) );
$ok( in_array( 'new-employer', $ids, true ) && in_array( 'new-candidate', $ids, true ), 'both are in the Emails registry' );

$mark = $last();
$emp->handle( $user );
$can->handle( $user );
$ok( 1 === $sent( $mark, 'new-employer' ), 'employer sign-up mails the owner' );
$ok( 0 === $sent( $mark, 'new-candidate' ), 'candidate sign-up mails nobody until switched on' );

$set['emails']['new-candidate'] = array( 'enabled' => 1 );
update_option( 'wcb_settings', $set );
$mark = $last();
$can->handle( $user );
$ok( 1 === $sent( $mark, 'new-candidate' ), 'candidate email sends once the owner turns it on' );

$set['emails']['new-employer'] = array( 'enabled' => 0 );
update_option( 'wcb_settings', $set );
$mark = $last();
$emp->handle( $user );
$ok( 0 === $sent( $mark, 'new-employer' ), 'employer email stops when the owner turns it off' );

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user );
update_option( 'wcb_settings', $old );

WP_CLI::log( "  Total: " . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Admin sign-up email tests passed.' );
