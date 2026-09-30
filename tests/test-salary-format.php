<?php
/**
 * Salary display tests (1.8.0).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-salary-format.php
 *
 * Pins: a published salary is never rounded up or down in short form. It is
 * abbreviated only when the short form is exact, and a single figure is not
 * shown as a range of the same number.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Core\SalaryFormat;

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

WP_CLI::log( 'Salary format' );

foreach ( array(
	500     => '500',
	4500    => '4.5k',
	4400    => '4.4k',
	4000    => '4k',
	95500   => '95.5k',
	96000   => '96k',
	4450    => '4,450',
	85250   => '85,250',
	1500000 => '1.5M',
	2000000 => '2M',
	1250000 => '1,250k',
	1040000 => '1,040k',
) as $wcb_in => $wcb_expected ) {
	wcb_assert( $wcb_expected === SalaryFormat::abbreviate( $wcb_in ), "abbreviate({$wcb_in}) is {$wcb_expected}" );
}

wcb_assert( '€4.5k/mo' === SalaryFormat::format( 4500, 4500, 'EUR', 'monthly' ), 'a monthly 4,500 shows as €4.5k/mo, not €5k' );
wcb_assert( '$22/hr' === SalaryFormat::format( 22, 22, 'USD', 'hourly' ), 'one figure is not shown as a range of the same number' );
wcb_assert( '$60k–$80k/yr' === SalaryFormat::format( 60000, 80000, 'USD', 'yearly' ), 'a real range is unchanged' );

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All salary format tests passed.' );
}
