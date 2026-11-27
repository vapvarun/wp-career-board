<?php
/**
 * The marker seed-data.php puts on everything it creates, so
 * cleanup-seed-data.php removes those rows and nothing else.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WCB_TEST_SEED_MARKER' ) ) {
	define( 'WCB_TEST_SEED_MARKER', '_wcb_test_seed' );
}
