<?php
/**
 * PHPStan bootstrap — define plugin constants so static analysis can resolve them.
 *
 * @package WP_Career_Board
 */

// WordPress core constant.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress core constant.
}

// Plugin constants.
define( 'WCB_VERSION', '1.0.0' );
define( 'WCB_FILE', __DIR__ . '/wp-career-board.php' );
define( 'WCB_PLUGIN_FILE', WCB_FILE );
define( 'WCB_DIR', __DIR__ . '/' );
define( 'WCB_URL', 'https://example.com/wp-content/plugins/wp-career-board/' );
define( 'WCB_EDD_ITEM_ID', 1659888 );
