<?php
/**
 * Abstract widget — every modular UI block extends this.
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
 * Base class for modular widgets.
 *
 * A widget is a self-contained renderer with one render() method that returns
 * HTML. It registers once with WidgetRegistry and is automatically renderable
 * from admin metaboxes and the [wcb_widget id="…"] shortcode.
 *
 * @since 1.1.0
 */
abstract class AbstractWidget {

	/**
	 * Unique widget id, e.g. "application/applicant-card".
	 *
	 * @since 1.1.0
	 * @return string
	 */
	abstract public function id(): string;

	/**
	 * Human-readable title shown in the inserter / shortcode picker.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	abstract public function title(): string;

	/**
	 * Render the widget HTML.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $args Merged arguments (defaults + caller).
	 * @return string Rendered HTML, already escaped.
	 */
	abstract public function render( array $args ): string;

	/**
	 * Default args. Subclasses override to declare expected keys.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function default_args(): array {
		return array();
	}

	/**
	 * Capability/ability required to render this widget. Empty = public.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function ability(): string {
		return '';
	}

	/**
	 * Whether the current user is allowed to render this widget.
	 *
	 * @since 1.1.0
	 * @return bool
	 */
	public function user_can_render(): bool {
		$ability = $this->ability();
		if ( '' === $ability ) {
			return true;
		}
		if ( function_exists( 'wp_is_ability_granted' ) ) {
			return (bool) wp_is_ability_granted( $ability );
		}
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WCB abilities registered via the Abilities API.
		return current_user_can( $ability );
	}
}
