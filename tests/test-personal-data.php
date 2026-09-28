<?php
/**
 * Personal-data lifecycle tests (1.8.0, W13, owner decision D3).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-personal-data.php
 *
 * Pins: one registry (`wcb_personal_data_providers`) drives the WordPress
 * privacy tools and user deletion; a deleted candidate's applications stay
 * for the employer as "Deleted candidate" with no personal data or files;
 * guests are found by email; an erase never lifts a ban; Pro's resumes,
 * alerts and bell go and its credit ledger is kept without the user link;
 * old email and bell history is pruned after the retention window.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Modules\Gdpr\GdprModule;

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

/**
 * Create a private-file attachment.
 *
 * @param int $author Owner.
 * @param int $parent Parent post.
 * @return int
 */
function wcb_pd_file( int $author, int $parent = 0 ): int {
	$id = wp_insert_attachment(
		array(
			'post_title'     => 'PD file',
			'post_author'    => $author,
			'post_mime_type' => 'application/pdf',
			'post_status'    => 'inherit',
		),
		false,
		$parent
	);
	update_post_meta( $id, '_wcb_private_file', 1 );
	return (int) $id;
}

/**
 * Create an application.
 *
 * @param int   $job  Job.
 * @param int   $user Candidate (0 = guest).
 * @param array $meta Extra meta.
 * @return int
 */
function wcb_pd_app( int $job, int $user, array $meta ): int {
	$id = wp_insert_post(
		array(
			'post_type'   => 'wcb_application',
			'post_status' => 'publish',
			'post_title'  => 'Application: PD Person',
			'post_author' => $user,
		)
	);
	update_post_meta( $id, '_wcb_job_id', $job );
	update_post_meta( $id, '_wcb_status', 'submitted' );
	update_post_meta( $id, '_wcb_candidate_id', $user );
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	return (int) $id;
}

/**
 * Run a WordPress privacy callback through every page.
 *
 * @param array  $callback Exporter or eraser callback.
 * @param string $email    Email.
 * @return array<int, array> Page results.
 */
function wcb_pd_pages( array $callback, string $email ): array {
	$pages = array();
	for ( $page = 1; $page < 20; $page++ ) {
		$pages[] = call_user_func( $callback, $email, $page );
		if ( ! empty( end( $pages )['done'] ) ) {
			break;
		}
	}
	return $pages;
}

global $wpdb;
require_once ABSPATH . 'wp-admin/includes/user.php';

$wcb_pro       = class_exists( '\WCB\Pro\Modules\Privacy\PrivacyModule' );
$wcb_module    = new GdprModule();
$wcb_employer  = wp_insert_user( array( 'user_login' => 'pd_employer_' . wp_generate_password( 6, false ), 'user_email' => 'pd-emp-' . wp_generate_password( 6, false ) . '@example.test', 'user_pass' => wp_generate_password(), 'role' => 'wcb_employer' ) );
$wcb_email     = 'pd-cand-' . wp_generate_password( 6, false ) . '@example.test';
$wcb_candidate = wp_insert_user( array( 'user_login' => 'pd_cand_' . wp_generate_password( 6, false ), 'user_email' => $wcb_email, 'user_pass' => wp_generate_password(), 'role' => 'wcb_candidate' ) );
$wcb_job       = wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'draft', 'post_title' => 'PD Job', 'post_author' => $wcb_employer ) );

WP_CLI::log( '--- registry ---' );
$wcb_keys = array_keys( GdprModule::providers() );
wcb_assert( ! array_diff( array( 'applications', 'profile', 'email-log' ), $wcb_keys ), 'Free registers applications, profile and email-log providers' );
wcb_assert( ! $wcb_pro || ( in_array( 'pro-resumes', $wcb_keys, true ) && in_array( 'pro-activity', $wcb_keys, true ) ), 'Pro adds its providers to the same registry' );
$wcb_addon_erased = array();
add_filter(
	'wcb_personal_data_providers',
	static function ( array $providers ) use ( &$wcb_addon_erased ): array {
		$providers['zz-addon'] = array(
			'label'  => 'Add-on',
			'export' => static fn ( array $subject ): array => array(),
			'erase'  => static function ( array $subject ) use ( &$wcb_addon_erased ): array {
				$wcb_addon_erased[] = $subject['user_id'];
				return array( 'removed' => 1 );
			},
		);
		return $providers;
	}
);
wcb_assert( isset( GdprModule::providers()['zz-addon'] ), 'an add-on joins the registry through the filter' );
$wcb_exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
$wcb_erasers   = apply_filters( 'wp_privacy_personal_data_erasers', array() );
wcb_assert( isset( $wcb_exporters['wp-career-board'], $wcb_erasers['wp-career-board'] ) && ! isset( $wcb_exporters['wp-career-board-pro'] ) && ! isset( $wcb_erasers['wp-career-board-pro'] ), 'one exporter and one eraser, no parallel Pro copies' );

// ── Member data ─────────────────────────────────────────────────────────
$wcb_cv    = wcb_pd_file( $wcb_candidate );
$wcb_app_a = wcb_pd_app( $wcb_job, $wcb_candidate, array( '_wcb_cover_letter' => 'PD secret cover', '_wcb_resume_attachment_id' => $wcb_cv, '_wcb_application_field_phone' => '555-0100', '_wcb_job_title_snapshot' => 'PD Job' ) );
$wcb_app_b = wcb_pd_app( $wcb_job, $wcb_candidate, array( '_wcb_cover_letter' => 'PD second cover' ) );
$wcb_child = wcb_pd_file( $wcb_candidate, $wcb_app_b );
update_user_meta( $wcb_candidate, '_wcb_job_title', 'PD Headline' );
add_user_meta( $wcb_candidate, '_wcb_bookmark', $wcb_job );
$wcb_loose = wcb_pd_file( $wcb_candidate );
$wpdb->insert( $wpdb->prefix . 'wcb_notifications_log', array( 'user_id' => $wcb_candidate, 'event_type' => 'pd_test', 'channel' => 'email', 'payload' => wp_json_encode( array( 'to' => $wcb_email ) ), 'status' => 'sent', 'sent_at' => current_time( 'mysql', true ) ) );

$wcb_resume = 0;
if ( $wcb_pro ) {
	$wcb_resume = wp_insert_post( array( 'post_type' => 'wcb_resume', 'post_status' => 'publish', 'post_title' => 'PD Resume', 'post_author' => $wcb_candidate ) );
	$wcb_photo  = wcb_pd_file( $wcb_candidate );
	update_post_meta( $wcb_resume, '_wcb_resume_photo_id', $wcb_photo );
	update_post_meta( $wcb_resume, '_wcb_resume_summary', 'PD summary' );
	$wpdb->insert( $wpdb->prefix . 'wcb_job_alerts', array( 'user_id' => $wcb_candidate, 'search_query' => 'pd', 'filters' => '{}', 'frequency' => 'daily', 'created_at' => current_time( 'mysql', true ) ) );
	$wpdb->insert( $wpdb->prefix . 'wcb_notifications', array( 'user_id' => $wcb_candidate, 'event_type' => 'pd_test', 'message' => 'PD bell', 'link' => '', 'is_read' => 0, 'created_at' => current_time( 'mysql', true ) ) );
	$wpdb->insert( $wpdb->prefix . 'wcb_credit_ledger', array( 'user_id' => $wcb_candidate, 'entry_type' => 'topup', 'amount' => 5, 'note' => 'pd_test', 'created_at' => current_time( 'mysql', true ) ) );
	$wcb_ledger = (int) $wpdb->insert_id;
}

WP_CLI::log( '--- export (member) ---' );
$wcb_export = wp_json_encode( wcb_pd_pages( array( $wcb_module, 'export_user_data' ), $wcb_email ) );
wcb_assert( str_contains( $wcb_export, 'PD secret cover' ) && str_contains( $wcb_export, 'PD second cover' ), 'export includes both cover letters' );
wcb_assert( str_contains( $wcb_export, 'PD Headline' ) && str_contains( $wcb_export, 'PD Job' ), 'export includes profile fields and saved jobs' );
wcb_assert( str_contains( $wcb_export, 'pd_test' ), 'export includes email history' );
wcb_assert( ! $wcb_pro || ( str_contains( $wcb_export, 'PD summary' ) && str_contains( $wcb_export, 'PD bell' ) ), 'export includes Pro resume and bell' );

WP_CLI::log( '--- erase keeps a ban ---' );
update_user_meta( $wcb_candidate, '_wcb_employer_banned', 1 );
GdprModule::erase_profile( array( 'user_id' => $wcb_candidate, 'email' => $wcb_email ) );
wcb_assert( '1' === (string) get_user_meta( $wcb_candidate, '_wcb_employer_banned', true ), 'profile erase keeps the ban flag' );
wcb_assert( '' === get_user_meta( $wcb_candidate, '_wcb_job_title', true ) && null === get_post( $wcb_loose ), 'profile erase removes fields and private files' );

WP_CLI::log( '--- account deletion ---' );
wp_delete_user( $wcb_candidate );
foreach ( array( $wcb_app_a, $wcb_app_b ) as $wcb_app ) {
	$wcb_post = get_post( $wcb_app );
	wcb_assert( $wcb_post instanceof WP_Post && 0 === (int) $wcb_post->post_author, "application {$wcb_app} survives deletion, unowned" );
	wcb_assert( 'Deleted candidate' === get_post_meta( $wcb_app, '_wcb_guest_name', true ) && 'submitted' === get_post_meta( $wcb_app, '_wcb_status', true ) && (int) $wcb_job === (int) get_post_meta( $wcb_app, '_wcb_job_id', true ), "application {$wcb_app} shows Deleted candidate and keeps job and status" );
	wcb_assert( '' === get_post_meta( $wcb_app, '_wcb_cover_letter', true ) && '' === get_post_meta( $wcb_app, '_wcb_application_field_phone', true ), "application {$wcb_app} has no cover letter or answers" );
}
wcb_assert( 'PD Job' === get_post_meta( $wcb_app_a, '_wcb_job_title_snapshot', true ), 'job title snapshot kept for the employer pipeline' );
wcb_assert( in_array( (int) $wcb_candidate, $wcb_addon_erased, true ), 'account deletion runs the add-on eraser too' );
wcb_assert( null === get_post( $wcb_cv ) && null === get_post( $wcb_child ), 'resume attachment and application files are deleted' );
wcb_assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wcb_notifications_log WHERE user_id = %d", $wcb_candidate ) ), 'email log rows are deleted' );
if ( $wcb_pro ) {
	wcb_assert( null === get_post( $wcb_resume ) && null === get_post( $wcb_photo ), 'Pro resume and its photo are deleted' );
	wcb_assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wcb_job_alerts WHERE user_id = %d", $wcb_candidate ) ) + (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wcb_notifications WHERE user_id = %d", $wcb_candidate ) ), 'Pro alerts and bell rows are deleted' );
	wcb_assert( '0' === (string) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->prefix}wcb_credit_ledger WHERE id = %d", $wcb_ledger ) ), 'credit ledger row is kept with user_id 0' );
	$wpdb->delete( $wpdb->prefix . 'wcb_credit_ledger', array( 'id' => $wcb_ledger ) );
}

WP_CLI::log( '--- guest by email ---' );
$wcb_guest_email = 'pd-guest-' . wp_generate_password( 6, false ) . '@example.test';
$wcb_guest_app   = wcb_pd_app( $wcb_job, 0, array( '_wcb_guest_email' => $wcb_guest_email, '_wcb_guest_name' => 'PD Guest', '_wcb_cover_letter' => 'PD guest cover' ) );
$wpdb->insert( $wpdb->prefix . 'wcb_notifications_log', array( 'user_id' => 0, 'event_type' => 'pd_guest', 'channel' => 'email', 'payload' => wp_json_encode( array( 'to' => $wcb_guest_email ) ), 'status' => 'sent', 'sent_at' => current_time( 'mysql', true ) ) );
$wpdb->insert( $wpdb->prefix . 'wcb_notifications_log', array( 'user_id' => 0, 'event_type' => 'pd_guest_other', 'channel' => 'email', 'payload' => wp_json_encode( array( 'to' => 'pd-other-' . $wcb_guest_email ) ), 'status' => 'sent', 'sent_at' => current_time( 'mysql', true ) ) );
$wcb_export = wp_json_encode( wcb_pd_pages( array( $wcb_module, 'export_user_data' ), $wcb_guest_email ) );
wcb_assert( ! str_contains( $wcb_export, 'pd_guest_other' ), 'guest export holds only the requester\'s email history, not other guests\'' );
wcb_assert( str_contains( $wcb_export, 'PD guest cover' ) && str_contains( $wcb_export, 'pd_guest' ), 'guest export finds the application and email history by address' );
$wcb_pages = wcb_pd_pages( array( $wcb_module, 'erase_user_data' ), $wcb_guest_email );
wcb_assert( ! empty( end( $wcb_pages )['done'] ) && count( $wcb_pages ) === count( GdprModule::providers() ), 'eraser pages once per provider and finishes' );
wcb_assert( 'Deleted candidate' === get_post_meta( $wcb_guest_app, '_wcb_guest_name', true ) && '' === get_post_meta( $wcb_guest_app, '_wcb_guest_email', true ), 'guest application is anonymised' );
wcb_assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wcb_notifications_log WHERE event_type = %s", 'pd_guest' ) ), 'guest email history is deleted' );
wcb_assert( 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wcb_notifications_log WHERE event_type = %s", 'pd_guest_other' ) ), 'erasing one guest leaves every other guest\'s email history' );

WP_CLI::log( '--- Tools > Erase on an account that stays open ---' );
$wcb_emp_email = (string) get_userdata( $wcb_employer )->user_email;
update_user_meta( $wcb_employer, '_wcb_company_id', 4242 );
update_user_meta( $wcb_employer, '_wcb_email_unverified', '1' );
update_user_meta( $wcb_employer, '_wcb_member_flag_count', 2 );
update_user_meta( $wcb_employer, '_wcb_job_title', 'Head of Talent' );
if ( $wcb_pro ) {
	$wpdb->insert( $wpdb->prefix . 'wcb_credit_ledger', array( 'user_id' => $wcb_employer, 'entry_type' => 'topup', 'amount' => 5, 'note' => 'pd_keep', 'created_at' => current_time( 'mysql', true ) ) );
	$wcb_keep_row = (int) $wpdb->insert_id;
}
wcb_pd_pages( array( $wcb_module, 'erase_user_data' ), $wcb_emp_email );
wcb_assert( '' === get_user_meta( $wcb_employer, '_wcb_job_title', true ), 'the erase removes personal profile data' );
wcb_assert( '4242' === (string) get_user_meta( $wcb_employer, '_wcb_company_id', true ), 'the account keeps its company link' );
wcb_assert( '1' === (string) get_user_meta( $wcb_employer, '_wcb_email_unverified', true ), 'an unverified account does not become verified' );
wcb_assert( '2' === (string) get_user_meta( $wcb_employer, '_wcb_member_flag_count', true ), 'moderation reports are kept' );
wcb_assert( ! $wcb_pro || (string) $wcb_employer === (string) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->prefix}wcb_credit_ledger WHERE id = %d", $wcb_keep_row ) ), 'paid credits stay with the account' );
if ( $wcb_pro ) {
	$wpdb->delete( $wpdb->prefix . 'wcb_credit_ledger', array( 'id' => $wcb_keep_row ) );
}
foreach ( array( '_wcb_company_id', '_wcb_email_unverified', '_wcb_member_flag_count' ) as $wcb_key ) {
	delete_user_meta( $wcb_employer, $wcb_key );
}

WP_CLI::log( '--- retention ---' );
$wcb_old = gmdate( 'Y-m-d H:i:s', time() - 400 * DAY_IN_SECONDS );
$wpdb->insert( $wpdb->prefix . 'wcb_notifications_log', array( 'user_id' => 0, 'event_type' => 'pd_old', 'channel' => 'email', 'payload' => '{}', 'status' => 'sent', 'sent_at' => $wcb_old ) );
$wpdb->insert( $wpdb->prefix . 'wcb_notifications_log', array( 'user_id' => 0, 'event_type' => 'pd_new', 'channel' => 'email', 'payload' => '{}', 'status' => 'sent', 'sent_at' => current_time( 'mysql', true ) ) );
if ( $wcb_pro ) {
	$wpdb->insert( $wpdb->prefix . 'wcb_notifications', array( 'user_id' => 0, 'event_type' => 'pd_old', 'message' => 'old', 'link' => '', 'is_read' => 1, 'created_at' => $wcb_old ) );
}
wcb_assert( 'wcb_prune_logs' === GdprModule::PRUNE_HOOK && in_array( GdprModule::PRUNE_HOOK, \WCB\Core\CronRegistry::all(), true ), 'prune hook is registered for cleanup on deactivate' );
$wcb_cutoff = '';
add_action(
	'wcb_logs_pruned',
	static function ( string $cutoff ) use ( &$wcb_cutoff ): void {
		$wcb_cutoff = $cutoff;
	}
);
do_action( GdprModule::PRUNE_HOOK );
wcb_assert( (bool) preg_match( '/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $wcb_cutoff ), 'wcb_logs_pruned passes the UTC cutoff to add-ons' );
$wcb_left = $wpdb->get_col( "SELECT event_type FROM {$wpdb->prefix}wcb_notifications_log WHERE event_type IN ('pd_old','pd_new')" );
wcb_assert( array( 'pd_new' ) === $wcb_left, 'rows past the retention window go, recent rows stay' );
wcb_assert( ! $wcb_pro || 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wcb_notifications WHERE event_type = 'pd_old'" ), 'Pro bell prunes with the same cutoff' );

// Teardown.
$wpdb->query( "DELETE FROM {$wpdb->prefix}wcb_notifications_log WHERE event_type LIKE 'pd\_%'" );
$wpdb->query( "DELETE FROM {$wpdb->prefix}wcb_gdpr_log WHERE user_id IN (0, " . (int) $wcb_candidate . ') AND created_at >= ' . "'" . gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) . "'" );
foreach ( array( $wcb_app_a, $wcb_app_b, $wcb_guest_app, $wcb_job ) as $wcb_id ) {
	wp_delete_post( (int) $wcb_id, true );
}
wp_delete_user( (int) $wcb_employer );

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All personal-data tests passed.' );
}
