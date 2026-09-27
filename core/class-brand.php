<?php
/**
 * The site Brand: one colour and one logo for emails, the app and the PWA.
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
 * Reads the Brand set under Settings > Brand. Every surface that paints the
 * product's colour or logo reads it here, so an owner sets them once.
 *
 * @since 1.8.0
 */
final class Brand {

	/**
	 * Brand colour as #RRGGBB.
	 *
	 * @since 1.8.0
	 * @return string
	 */
	public static function color(): string {
		return \WCB\Admin\Settings::string( 'accent_color', '#4F46E5' );
	}

	/**
	 * Logo URL at a given size, or '' when no logo is set.
	 *
	 * @since 1.8.0
	 * @param string $size Image size.
	 * @return string
	 */
	public static function logo_url( string $size = 'medium' ): string {
		$id = \WCB\Admin\Settings::int( 'logo_id', 0 );
		return $id ? (string) wp_get_attachment_image_url( $id, $size ) : '';
	}
}
