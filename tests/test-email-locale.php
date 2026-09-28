<?php
/**
 * Recipient-language email tests (1.8.0).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-email-locale.php
 *
 * Pins: values an email builds from the language (dates, status labels) are made
 * after the switch to the recipient's locale, not in the language of whoever
 * triggered it. No language pack is needed: month names are faked per locale.
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
function wcb_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

// Let the switcher accept de_DE without a language pack, and fake its month names.
$wcb_switcher = new ReflectionProperty( $GLOBALS['wp_locale_switcher'], 'available_languages' );
$wcb_switcher->setAccessible( true );
$wcb_was = $wcb_switcher->getValue( $GLOBALS['wp_locale_switcher'] );
$wcb_switcher->setValue( $GLOBALS['wp_locale_switcher'], array_merge( (array) $wcb_was, array( 'de_DE' ) ) );

$wcb_fake = static function ( $translation, $text ) {
	return 'de_DE' === determine_locale() && 'March' === $text ? 'DE-' . $text : $translation;
};
add_filter( 'gettext', $wcb_fake, 10, 2 );
add_filter( 'pre_option_date_format', static fn() => 'F' );

$wcb_mail = array();
add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) use ( &$wcb_mail ) {
		$wcb_mail[] = (string) ( $atts['message'] ?? '' );
		return true;
	},
	10,
	2
);

$wcb_tag  = 'el' . strtolower( wp_generate_password( 5, false, false ) );
$wcb_user = (int) wp_insert_user( array( 'user_login' => "{$wcb_tag}_de", 'user_email' => "{$wcb_tag}@example.test", 'user_pass' => wp_generate_password(), 'role' => 'wcb_candidate' ) );
update_user_meta( $wcb_user, 'locale', 'de_DE' );

$wcb_when = (int) strtotime( '2026-03-15 12:00:00 UTC' );
$wcb_mail = array();
do_action( 'wcb_account_deletion_requested', $wcb_user, $wcb_when );
wcb_assert( 1 === count( $wcb_mail ) && str_contains( $wcb_mail[0], 'DE-March' ), 'the deletion date is written in the recipient\'s language' );

update_user_meta( $wcb_user, 'locale', '' );
$wcb_mail = array();
do_action( 'wcb_account_deletion_requested', $wcb_user, $wcb_when );
wcb_assert( 1 === count( $wcb_mail ) && str_contains( $wcb_mail[0], 'March' ) && ! str_contains( $wcb_mail[0], 'DE-' ), 'a member on the site language is unchanged' );

// Teardown.
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $wcb_user );
$wcb_switcher->setValue( $GLOBALS['wp_locale_switcher'], $wcb_was );

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All email locale tests passed.' );
}
