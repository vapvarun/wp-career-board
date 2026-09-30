<?php
/**
 * RTL stylesheets - switch our styles to their generated -rtl twin.
 *
 * The build (`grunt rtl`) writes `foo-rtl.css` next to every stylesheet that
 * has direction-dependent rules and skips the ones that do not. WordPress
 * requests the -rtl file for a style registered with `rtl => replace` without
 * checking that it exists, so this only opts a style in when its twin is on
 * disk. Free's hook also covers Pro's styles: anything under either plugin's
 * folder is handled here, so no enqueue needs its own `wp_style_add_data()`.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Opts each of our registered stylesheets into its generated RTL twin.
 */
class Rtl {

	/**
	 * Hook in before WordPress prints head and footer styles, front and admin.
	 *
	 * @return void
	 */
	public static function boot(): void {
		foreach ( array( 'wp_print_styles', 'admin_print_styles', 'wp_print_footer_scripts', 'admin_print_footer_scripts' ) as $hook ) {
			add_action( $hook, array( self::class, 'enable' ), 1 );
		}
	}

	/**
	 * Set `rtl => replace` on every registered style of ours that has a twin.
	 *
	 * @return void
	 */
	public static function enable(): void {
		if ( ! is_rtl() ) {
			return;
		}

		$roots = array( WCB_URL => WCB_DIR );
		if ( defined( 'WCBP_URL' ) && defined( 'WCBP_DIR' ) ) {
			$roots[ WCBP_URL ] = WCBP_DIR;
		}

		foreach ( wp_styles()->registered as $handle => $style ) {
			if ( ! is_string( $style->src ) ) {
				continue;
			}
			foreach ( $roots as $url => $dir ) {
				if ( 0 !== strpos( $style->src, $url ) ) {
					continue;
				}
				$file = (string) strtok( substr( $style->src, strlen( $url ) ), '?' );
				if ( '.css' === substr( $file, -4 ) && is_file( $dir . substr( $file, 0, -4 ) . '-rtl.css' ) ) {
					wp_style_add_data( $handle, 'rtl', 'replace' );
					// Core registers block styles with rtl=replace and a `.min` suffix
					// when SCRIPT_DEBUG is off, then swaps only `.min.css` for
					// `-rtl.css`, which never matches our unminified files. An empty
					// suffix makes it swap the `.css` we actually ship.
					wp_style_add_data( $handle, 'suffix', '' );
				}
				break;
			}
		}
	}
}
