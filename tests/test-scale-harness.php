<?php
/**
 * Scale harness tests: the two extension filters and the budget rules.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-scale-harness.php
 *
 * These cover the seam Pro rides on. `wcb_scale_ops` is how a second plugin
 * adds its own hot paths to `wp wcb scale benchmark`; `wcb_scale_budgets` is
 * how it says what "too slow" means for them. Both are cross-plugin contracts,
 * so a change that breaks them breaks Pro's harness silently.
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
		WP_CLI::log( "  PASS: {$label}" );
		++$GLOBALS['wcb_test_pass'];
	} else {
		WP_CLI::warning( "  FAIL: {$label}" );
		++$GLOBALS['wcb_test_fail'];
	}
}

WP_CLI::log( '' );
WP_CLI::log( '=== Scale harness: budget rules ===' );

$wcb_status = array( '\WCB\Cli\ScaleCommand', 'row_status' );

wcb_assert(
	'OK' === call_user_func( $wcb_status, array( 'duration_ms' => 10.0, 'skipped' => false ), 100 ),
	'an op inside its budget reads OK'
);
wcb_assert(
	'OVER BUDGET' === call_user_func( $wcb_status, array( 'duration_ms' => 250.0, 'skipped' => false ), 100 ),
	'an op past its budget reads OVER BUDGET'
);
wcb_assert(
	'SKIPPED' === call_user_func( $wcb_status, array( 'duration_ms' => 0.0, 'skipped' => true ), 100 ),
	'an op with no sample data reads SKIPPED, not a misleading 0 ms OK'
);
// The regression this guards: before the budget<=0 branch existed, an op added
// through wcb_scale_ops had no entry in BUDGETS_MS, so it compared against 0
// and failed the whole run on every invocation.
wcb_assert(
	'no budget' === call_user_func( $wcb_status, array( 'duration_ms' => 1.0, 'skipped' => false ), 0 ),
	'an op with no agreed budget is reported, never failed'
);
wcb_assert(
	'SKIPPED' === call_user_func( $wcb_status, array( 'duration_ms' => 0.0, 'skipped' => true ), 0 ),
	'skipped outranks no-budget'
);

WP_CLI::log( '' );
WP_CLI::log( '=== Scale harness: extension filters ===' );

$wcb_ops_seen = null;
add_filter(
	'wcb_scale_ops',
	function ( $ops, $per_page ) use ( &$wcb_ops_seen ) {
		$wcb_ops_seen           = $per_page;
		$ops['test.probe_op']   = static function () {
			return true;
		};
		return $ops;
	},
	10,
	2
);

add_filter(
	'wcb_scale_budgets',
	function ( $budgets ) {
		$budgets['test.probe_op'] = 42;
		return $budgets;
	}
);

// Ask build_ops() for the real op list rather than scraping the benchmark's
// output: WP-CLI writes its table with fwrite( STDOUT ), which neither
// runcommand's capture nor ob_start() can see.
$wcb_build = new ReflectionMethod( '\WCB\Cli\ScaleCommand', 'build_ops' );
$wcb_build->setAccessible( true );
$wcb_ops = $wcb_build->invoke( new \WCB\Cli\ScaleCommand(), 7 );

wcb_assert( 7 === $wcb_ops_seen, 'wcb_scale_ops receives the resolved per-page value' );
wcb_assert( isset( $wcb_ops['test.probe_op'] ), 'an op added through wcb_scale_ops reaches the benchmark list' );
wcb_assert( is_callable( $wcb_ops['test.probe_op'] ?? null ), 'the added op arrives as a callable the harness can time' );

// BUDGETS_MS is private; read it the way benchmark() would see it.
$wcb_defaults = ( new ReflectionClass( '\WCB\Cli\ScaleCommand' ) )->getConstant( 'BUDGETS_MS' );
$wcb_budgets  = (array) apply_filters( 'wcb_scale_budgets', $wcb_defaults );

wcb_assert( ( $wcb_budgets['jobs.list_50'] ?? 0 ) > 0, 'the filter builds on Free\'s own budgets rather than replacing them' );
wcb_assert( 42 === ( $wcb_budgets['test.probe_op'] ?? 0 ), 'wcb_scale_budgets supplies the budget for the added op' );

// The pairing that matters: a filtered-in op must not read OVER BUDGET.
wcb_assert(
	'OK' === call_user_func( $wcb_status, array( 'duration_ms' => 1.0, 'skipped' => false ), (int) ( $wcb_budgets['test.probe_op'] ?? 0 ) ),
	'a fast op added through the filters reads OK, not OVER BUDGET'
);

// Pro registers through the same seam, so if Pro is active its ops must be here.
if ( class_exists( '\WCB\Pro\Core\ScaleOps' ) ) {
	$wcb_pro_ops = array( 'pro.resumes_list_50', 'pro.alerts_due_sweep', 'pro.kanban_for_board', 'pro.credit_balance' );
	foreach ( $wcb_pro_ops as $wcb_pro_op ) {
		wcb_assert( isset( $wcb_ops[ $wcb_pro_op ] ), "Pro op {$wcb_pro_op} rides the same filter" );
		wcb_assert( ( $wcb_budgets[ $wcb_pro_op ] ?? 0 ) > 0, "Pro op {$wcb_pro_op} carries a real budget, not 0" );
	}
} else {
	WP_CLI::log( '  SKIP: Pro not active, cross-plugin assertions not run' );
}

WP_CLI::log( '' );
WP_CLI::log( '========================================' );
WP_CLI::log( '  Scale Harness Test Summary' );
WP_CLI::log( '========================================' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) );
WP_CLI::log( '  Pass:  ' . $GLOBALS['wcb_test_pass'] );
WP_CLI::log( '  Fail:  ' . $GLOBALS['wcb_test_fail'] );
WP_CLI::log( '' );

if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All scale harness tests passed.' );
}
