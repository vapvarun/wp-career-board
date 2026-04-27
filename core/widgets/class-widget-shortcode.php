<?php
/**
 * [wcb_widget] shortcode — exposes any registered widget on a classic page.
 *
 * Usage:  [wcb_widget id="application/applicant-card" application_id="123"]
 *
 * Every key/value passed as a shortcode attribute (other than `id`) is
 * forwarded to the widget's render() args, so the same component renders
 * identically inside an admin metabox or on a public WordPress page.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

declare( strict_types=1 );

namespace WCB\Core\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the [wcb_widget] shortcode.
 *
 * @since 1.1.0
 */
final class WidgetShortcode {

	/**
	 * Boot the shortcode.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function boot(): void {
		add_shortcode( 'wcb_widget', array( $this, 'render' ) );
	}

	/**
	 * Render handler for [wcb_widget].
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ): string {
		$raw = is_array( $atts ) ? $atts : array();

		$id = isset( $raw['id'] ) ? self::sanitize_widget_id( (string) $raw['id'] ) : '';
		if ( '' === $id ) {
			return '';
		}

		$args = array();
		foreach ( $raw as $key => $value ) {
			if ( 'id' === $key || ! is_scalar( $value ) ) {
				continue;
			}
			$args[ sanitize_key( (string) $key ) ] = (string) $value;
		}

		$html = WidgetRegistry::instance()->render( $id, $args );

		if ( '' !== $html && str_starts_with( $id, 'application/' ) ) {
			$this->enqueue_application_assets();
		}

		return $html;
	}

	/**
	 * Sanitize a widget id like "application/applicant-card".
	 *
	 * Allows lowercase letters, digits, hyphens, underscores, and forward slashes.
	 * Used in place of sanitize_key() because the registry namespaces widgets
	 * with a slash (e.g. "application/cover-letter").
	 *
	 * @since 1.1.0
	 *
	 * @param string $id Raw shortcode id attribute.
	 * @return string
	 */
	private static function sanitize_widget_id( string $id ): string {
		$id = strtolower( $id );
		return (string) preg_replace( '/[^a-z0-9_\-\/]/', '', $id );
	}

	/**
	 * Lazily enqueue the application-detail bundle when an application widget
	 * shortcode renders on the front end.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function enqueue_application_assets(): void {
		if ( wp_style_is( 'wcb-application-detail', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_style(
			'wcb-application-detail',
			WCB_URL . 'assets/css/admin/application-detail.css',
			array(),
			WCB_VERSION
		);
		wp_enqueue_script(
			'wcb-application-detail',
			WCB_URL . 'assets/js/admin/application-detail.js',
			array(),
			WCB_VERSION,
			true
		);
		wp_localize_script(
			'wcb-application-detail',
			'wcbAppDetail',
			array(
				'savedLabel' => __( 'Saved.', 'wp-career-board' ),
				'errorLabel' => __( 'Could not save.', 'wp-career-board' ),
				'labels'     => \WCB\Modules\Applications\Widgets\StatusTimeline::status_labels(),
			)
		);
	}
}
