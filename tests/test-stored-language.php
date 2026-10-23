<?php
/**
 * Stored-language tests (1.8.2).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-stored-language.php
 *
 * Pins: role names are stored in English and shown in the reader's language;
 * a shipped industry the owner kept is stored as "the default" and shown in
 * the reader's language, a renamed one keeps the owner's words; the DB 1.4.0
 * repair turns old German-saved defaults back into defaults.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Core\Industries;

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_sl_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

WP_CLI::log( 'Stored language' );

if ( ! in_array( 'de_DE', get_available_languages(), true ) ) {
	WP_CLI::error( 'Needs de_DE installed.' );
}

// Roles: a German request does not rewrite the stored name.
switch_to_locale( 'de_DE' );
( new \WCB\Core\Roles() )->register();
$wcb_shown = translate_user_role( wp_roles()->role_names['wcb_employer'] );
restore_previous_locale();
wp_roles()->for_site();
wcb_sl_assert( 'Employer' === wp_roles()->role_names['wcb_employer'], 'the Employer role is stored in English after a German request' );
wcb_sl_assert( 'Arbeitgeber' === $wcb_shown, 'a German reader sees the role as Arbeitgeber' );

// Industries: an owner saving in German.
$wcb_before = get_option( Industries::OPTION, null );
switch_to_locale( 'de_DE' );
$wcb_map               = Industries::registry();
$wcb_map['design']     = 'Kreativwirtschaft';
$wcb_german_technology = $wcb_map['technology'];
Industries::save( $wcb_map );
restore_previous_locale();
$wcb_english = Industries::registry();
wcb_sl_assert( 'Technology & Software' === $wcb_english['technology'], 'a kept industry saved in German reads in English for an English reader' );
wcb_sl_assert( 'Kreativwirtschaft' === $wcb_english['design'], 'a renamed industry keeps the owner\'s words' );
wcb_sl_assert( '' === ( get_option( Industries::OPTION )['technology'] ?? 'x' ), 'only changed labels are stored' );

// Old data: saved before 1.8.2 with the German default text.
$wcb_old               = get_option( Industries::OPTION );
$wcb_old['technology'] = $wcb_german_technology;
update_option( Industries::OPTION, $wcb_old );
Industries::forget_default_labels();
wcb_sl_assert( 'Technology & Software' === Industries::registry()['technology'], 'the repair turns an old German-saved default back into the default' );
wcb_sl_assert( 'Kreativwirtschaft' === Industries::registry()['design'], 'the repair keeps renamed labels' );

// Restore.
if ( null === $wcb_before ) {
	delete_option( Industries::OPTION );
} else {
	update_option( Industries::OPTION, $wcb_before );
}

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All stored-language tests passed.' );
}
