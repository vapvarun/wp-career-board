<?php
/**
 * First-paint guard for the dashboards' restored tab.
 *
 * @package WP_Career_Board
 * @since   1.8.1
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps the server-rendered default tab from flashing before the tab in the
 * URL hash or this session is restored.
 *
 * The server cannot see the hash, and the view module runs after first paint.
 * This guard runs before the dashboard markup, hides its panels only when a
 * different tab is about to be restored, and the @wcb/ui tabRouter lifts it.
 *
 * @since 1.8.1
 */
final class DashboardTabs {

	/**
	 * Print the guard. Call right before the dashboard wrapper.
	 *
	 * @param array<int,string> $valid       Tab slugs; the first is the default.
	 * @param string            $storage_key sessionStorage key the view module uses.
	 * @param string            $server_tab  Tab the server rendered.
	 * @return void
	 */
	public static function print_guard( array $valid, string $storage_key, string $server_tab ): void {
		$script = sprintf(
			'(function(v,k,d){try{var h=location.hash.slice(1),t=v.indexOf(h)>-1?h:(d===v[0]?sessionStorage.getItem(k):null);if(t&&t!==d&&v.indexOf(t)>-1){document.documentElement.setAttribute("data-wcb-tab-pending","")}}catch(e){}})(%s,%s,%s);',
			wp_json_encode( array_values( $valid ) ),
			wp_json_encode( $storage_key ),
			wp_json_encode( $server_tab )
		);
		wp_print_inline_script_tag( $script );
	}
}
