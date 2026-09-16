<?php
/**
 * REST exposure contract: every CPT this plugin registers states its read gate.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/audit/rest-exposure.php
 *
 * Reads the LIVE registration rather than parsing register_post_type() out of
 * the source. Two reasons, both learned here: the args are wrapped across lines
 * so a literal grep undercounts them, and what ships is whatever survived every
 * filter - which is the thing worth asserting.
 *
 * The defect this guards has now recurred three times. wcb_board got a
 * controller in 1.2.1, wcb_application in 1.7.1, and wcb_resume shipped a
 * collection-only filter pair that left reads by id open (Basecamp 10304194315).
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * The contract, per post type.
 *
 * 'gate' is how reads are restricted:
 *   'none'       - intentionally world-readable, nothing to assert.
 *   'controller' - must declare a rest_controller_class of our own.
 */
$wcb_contract = array(
	'wcb_job'         => 'none',
	'wcb_company'     => 'none',
	'wcb_resume'      => 'controller',
	'wcb_application' => 'controller',
	'wcb_board'       => 'controller',
);

$wcb_fail = 0;
$wcb_pass = 0;

/**
 * Report one assertion.
 *
 * @param bool   $ok    Whether it held.
 * @param string $label What was checked.
 * @return void
 */
$wcb_check = static function ( bool $ok, string $label ) use ( &$wcb_fail, &$wcb_pass ): void {
	if ( $ok ) {
		++$wcb_pass;
		WP_CLI::log( "  PASS: {$label}" );
		return;
	}
	++$wcb_fail;
	WP_CLI::warning( "  FAIL: {$label}" );
};

WP_CLI::log( '' );
WP_CLI::log( '=== REST exposure contract ===' );

foreach ( $wcb_contract as $wcb_type => $wcb_gate ) {
	$wcb_obj = get_post_type_object( $wcb_type );

	if ( ! $wcb_obj ) {
		$wcb_check( false, "{$wcb_type} is registered" );
		continue;
	}

	if ( ! $wcb_obj->show_in_rest ) {
		// Not on the REST surface at all: nothing to leak.
		$wcb_check( true, "{$wcb_type} is not exposed to REST" );
		continue;
	}

	if ( 'controller' === $wcb_gate ) {
		$wcb_class = (string) $wcb_obj->rest_controller_class;
		$wcb_own   = '' !== $wcb_class && 0 === strpos( ltrim( $wcb_class, '\\' ), 'WCB' );
		$wcb_check(
			$wcb_own,
			"{$wcb_type} declares its own REST controller (got: " . ( $wcb_class ?: 'core default' ) . ')'
		);
		continue;
	}

	$wcb_check( true, "{$wcb_type} is intentionally public, no gate required" );
}

// A post type added later must be added to the contract above, not silently
// inherit core's controller - which is how each of the three leaks happened.
$wcb_ours = array();
foreach ( get_post_types( array(), 'objects' ) as $wcb_pt ) {
	if ( 0 === strpos( $wcb_pt->name, 'wcb_' ) ) {
		$wcb_ours[] = $wcb_pt->name;
	}
}
$wcb_unlisted = array_diff( $wcb_ours, array_keys( $wcb_contract ) );
$wcb_check(
	empty( $wcb_unlisted ),
	'every wcb_ post type is covered by the contract' . ( $wcb_unlisted ? ' (missing: ' . implode( ', ', $wcb_unlisted ) . ')' : '' )
);

WP_CLI::log( '' );
WP_CLI::log( "  Pass: {$wcb_pass}   Fail: {$wcb_fail}" );

if ( $wcb_fail > 0 ) {
	WP_CLI::error( "{$wcb_fail} REST exposure contract failure(s)." );
} else {
	WP_CLI::success( 'REST exposure contract holds.' );
}
