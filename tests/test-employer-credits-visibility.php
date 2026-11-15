<?php
/**
 * Employer dashboard Credits (nav item, Overview tile, panel) show only where
 * credits mean something: on, and a balance, a way to buy, or a board that charges.
 *
 * Run: wp eval-file tests/test-employer-credits-visibility.php
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

$wcb_pass = 0;
$wcb_fail = 0;
$wcb_chk  = static function ( bool $ok, string $label ) use ( &$wcb_pass, &$wcb_fail ): void {
	$ok ? ++$wcb_pass : ++$wcb_fail;
	WP_CLI::log( ( $ok ? '  PASS: ' : '  FAIL: ' ) . $label );
};

$wcb_user = wp_insert_user( array( 'user_login' => 'qa_credits_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'wcb_employer' ) );
wp_set_current_user( $wcb_user );

// Each case decides every input, so whatever Pro answers on this site is replaced.
$wcb_case = static function ( bool $on, int $balance, string $buy, int $cost, int $just_added = 0 ) use ( $wcb_user ): bool {
	$filters = array(
		'wcb_credits_enabled'         => static fn () => $on,
		'wcb_employer_credit_balance' => static fn () => $balance,
		'wcb_credit_purchase_url'     => static fn () => $buy,
		'wcb_board_credit_cost'       => static fn () => $cost,
	);
	foreach ( $filters as $hook => $cb ) {
		add_filter( $hook, $cb, PHP_INT_MAX );
	}
	$shows = \WCB\Modules\Employers\EmployersModule::shows_credits( $wcb_user, $just_added );
	foreach ( $filters as $hook => $cb ) {
		remove_filter( $hook, $cb, PHP_INT_MAX );
	}
	return $shows;
};

$wcb_chk( ! $wcb_case( false, 5, 'https://example.com/buy', 3 ), 'credits off: hidden, whatever else is true' );
$wcb_chk( ! $wcb_case( true, 0, '', 0 ), 'on, no balance, nothing to buy, every board free: hidden' );
$wcb_chk( $wcb_case( true, 4, '', 0 ), 'a balance above 0 shows it' );
$wcb_chk( $wcb_case( true, 0, 'https://example.com/buy', 0 ), 'a way to buy credits shows it' );
$wcb_chk( $wcb_case( true, 0, '', 3 ), 'a board that charges shows it' );
$wcb_chk( $wcb_case( true, 0, '', 0, 10 ), 'credits just bought show it' );

wp_set_current_user( 0 );
wp_delete_user( $wcb_user );

WP_CLI::log( "  Total: {$wcb_pass}+{$wcb_fail}  Pass: {$wcb_pass}  Fail: {$wcb_fail}" );
$wcb_fail > 0 ? WP_CLI::error( "{$wcb_fail} test(s) failed." ) : WP_CLI::success( 'Credits visibility holds.' );
