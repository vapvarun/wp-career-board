<?php
/**
 * Widget Registry — central directory of modular UI widgets.
 *
 * Each widget registers once and is renderable from admin metaboxes,
 * shortcodes, and Interactivity API blocks.
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
 * Singleton registry mapping widget id → instance.
 *
 * @since 1.1.0
 */
final class WidgetRegistry {

	/**
	 * Singleton instance.
	 *
	 * @since 1.1.0
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Registered widgets.
	 *
	 * @since 1.1.0
	 * @var array<string, AbstractWidget>
	 */
	private array $widgets = array();

	/**
	 * Private constructor — use instance().
	 *
	 * @since 1.1.0
	 */
	private function __construct() {}

	/**
	 * Get or create the singleton.
	 *
	 * @since 1.1.0
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register a widget.
	 *
	 * @since 1.1.0
	 *
	 * @param AbstractWidget $widget Widget instance.
	 * @return void
	 */
	public function register( AbstractWidget $widget ): void {
		$this->widgets[ $widget->id() ] = $widget;
	}

	/**
	 * Get a widget by id.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id Widget id.
	 * @return AbstractWidget|null
	 */
	public function get( string $id ): ?AbstractWidget {
		return $this->widgets[ $id ] ?? null;
	}

	/**
	 * Get all registered widgets.
	 *
	 * @since 1.1.0
	 * @return array<string, AbstractWidget>
	 */
	public function all(): array {
		return $this->widgets;
	}

	/**
	 * Render a widget by id with the given args.
	 *
	 * Returns an empty string when the widget is unknown or the user lacks the
	 * required ability — never an error message — so failed lookups don't
	 * leak details on the front end.
	 *
	 * @since 1.1.0
	 *
	 * @param string               $id   Widget id.
	 * @param array<string, mixed> $args Caller args; merged over the widget's defaults.
	 * @return string Rendered HTML.
	 */
	public function render( string $id, array $args = array() ): string {
		$widget = $this->get( $id );
		if ( ! $widget instanceof AbstractWidget ) {
			return '';
		}
		if ( ! $widget->user_can_render() ) {
			return '';
		}
		return $widget->render( array_merge( $widget->default_args(), $args ) );
	}
}
