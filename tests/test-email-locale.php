<?php
/**
 * Email language tests (1.8.2).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-email-locale.php
 *
 * Pins: a guest gets later emails in the language they applied in; an email
 * to the notification address goes out in that account's language, or the
 * site language when no account has it, never in the language of whoever
 * triggered it; strings inside the merge values follow the same switch.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Modules\Notifications\Emails\EmailAppStatus;
use WCB\Modules\Notifications\Emails\EmailJobPending;

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_el_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

/**
 * The language of a German-speaking member's request.
 *
 * @return string
 */
function wcb_el_german(): string {
	return 'de_DE';
}

WP_CLI::log( 'Email language' );

if ( 'en_US' !== get_locale() || ! in_array( 'de_DE', get_available_languages(), true ) ) {
	WP_CLI::error( 'Needs an en_US site with de_DE installed.' );
}

$wcb_mail = array();
add_filter(
	'pre_wp_mail',
	static function ( $short, array $atts ) use ( &$wcb_mail ) {
		$wcb_mail[] = $atts;
		return true;
	},
	10,
	2
);
// A request from a German-speaking member: WordPress loads their language
// through determine_locale(), while the site language stays en_US.
$wcb_german = static function ( bool $on ): void {
	remove_filter( 'determine_locale', 'wcb_el_german', 5 );
	if ( $on ) {
		// Before the locale switcher's own filter, as a member's request is.
		add_filter( 'determine_locale', 'wcb_el_german', 5 );
	}
	unload_textdomain( 'wp-career-board', true );
};
$wcb_last   = static function () use ( &$wcb_mail ): string {
	$m = end( $wcb_mail );
	return $m ? $m['subject'] . "\n" . $m['message'] : '';
};

// A guest who applied on the German site, moved by an English-speaking employer.
$wcb_job = (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'publish',
		'post_title'  => 'EL test job',
	)
);
$wcb_app = (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_application',
		'post_status' => 'publish',
		'post_title'  => 'EL test application',
	)
);
update_post_meta( $wcb_app, '_wcb_job_id', $wcb_job );
update_post_meta( $wcb_app, '_wcb_candidate_id', 0 );
update_post_meta( $wcb_app, '_wcb_guest_email', 'wcb-el-guest@example.test' );
update_post_meta( $wcb_app, '_wcb_guest_name', 'EL Guest' );
update_post_meta( $wcb_app, '_wcb_guest_locale', 'de_DE' );

( new EmailAppStatus() )->handle( $wcb_app, 'submitted', 'reviewing' );
wcb_el_assert( str_contains( $wcb_last(), 'Bewerbungsstatus' ), 'a status email reaches a German guest in German' );

delete_post_meta( $wcb_app, '_wcb_guest_locale' );
$wcb_german( true );
( new EmailAppStatus() )->handle( $wcb_app, 'submitted', 'reviewing' );
$wcb_german( false );
wcb_el_assert( str_contains( $wcb_last(), 'application status' ) && ! str_contains( $wcb_last(), 'Bewerbungsstatus' ), 'a guest from before 1.8.2 gets the site language, not the language of whoever moved them' );

// The notification address: an account's own language, else the site language.
wp_update_post(
	array(
		'ID'          => $wcb_job,
		'post_status' => 'pending',
	)
);
$wcb_admin  = get_user_by( 'email', (string) get_option( 'admin_email' ) );
$wcb_before = $wcb_admin instanceof WP_User ? (string) get_user_meta( $wcb_admin->ID, 'locale', true ) : '';
$wcb_notify = \WCB\Admin\Settings::string( 'notification_email', '' );
$wcb_set    = static function ( string $to ): void {
	add_filter(
		'option_wcb_settings',
		static function ( $s ) use ( $to ) {
			$s                       = (array) $s;
			$s['notification_email'] = $to;
			return $s;
		}
	);
};

$wcb_set( 'wcb-el-nobody@example.test' );
$wcb_german( true );
( new EmailJobPending() )->handle( $wcb_job );
$wcb_german( false );
wcb_el_assert( str_contains( $wcb_last(), 'Action Required' ), 'a German employer posting sends the address with no account an English email' );

if ( $wcb_admin instanceof WP_User ) {
	remove_all_filters( 'option_wcb_settings' );
	$wcb_set( $wcb_admin->user_email );
	update_user_meta( $wcb_admin->ID, 'locale', 'de_DE' );
	( new EmailJobPending() )->handle( $wcb_job );
	wcb_el_assert( str_contains( $wcb_last(), 'Aktion erforderlich' ), 'the notification address of a German admin gets German' );
	update_user_meta( $wcb_admin->ID, 'locale', $wcb_before );
}
remove_all_filters( 'option_wcb_settings' );
wcb_el_assert( 'en_US' === determine_locale(), 'every switch is restored' );

// Cleanup.
remove_all_filters( 'pre_wp_mail' );
wp_delete_post( $wcb_app, true );
wp_delete_post( $wcb_job, true );

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All email language tests passed.' );
}
