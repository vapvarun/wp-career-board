<?php
/**
 * Canonical empty-state card for archive pages.
 *
 * Shared across the 3 archives (Find Jobs, Companies, Find Candidates)
 * and the 6 Saved tabs on the candidate + employer dashboards. Each
 * caller sets `$wcb_empty` and `include`s this partial; per-block
 * markup duplication is gone.
 *
 * Expected in $wcb_empty:
 *   container_class    string  Outer wrapper class. Default 'wcb-empty-state'.
 *                              Some surfaces also need a custom class (e.g.
 *                              `wcb-ra-empty` on resumes) so the existing JS
 *                              selectors keep working - pass it through.
 *   wp_bind_hidden     string  data-wp-bind--hidden directive value (e.g.
 *                              "!state.hasNoJobs"). Leave empty to opt out.
 *   ssr_hidden         bool    Whether to render the `hidden` HTML attribute
 *                              at first paint. Use when the SSR query
 *                              returned items so the empty state stays
 *                              hidden until Interactivity hydrates.
 *   icon               string  Lucide icon key. Default 'inbox'.
 *   title              string  Pre-translated title text. REQUIRED.
 *   body               string  Pre-translated body text. REQUIRED.
 *   clear_action       string  Interactivity action name on the clear-filters
 *                              button. Empty string suppresses the button.
 *   clear_hidden_bind  string  data-wp-bind--hidden value for the clear
 *                              button. Empty string = button always visible
 *                              when the empty state is rendered.
 *   clear_label        string  Pre-translated label for the clear button.
 *                              Default 'Clear filters' in wp-career-board.
 *
 * @package WP_Career_Board
 * @since   1.2.7
 *
 * @var array<string,mixed> $wcb_empty
 */

defined( 'ABSPATH' ) || exit;

$wcb_empty = wp_parse_args(
	$wcb_empty ?? array(),
	array(
		'container_class'   => 'wcb-empty-state',
		'wp_bind_hidden'    => '',
		'ssr_hidden'        => false,
		'icon'              => 'inbox',
		'title'             => '',
		'body'              => '',
		'clear_action'      => '',
		'clear_hidden_bind' => '',
		'clear_label'       => __( 'Clear filters', 'wp-career-board' ),
	)
);
?>
<div
	class="<?php echo esc_attr( (string) $wcb_empty['container_class'] ); ?>"
	role="status"
	<?php if ( '' !== (string) $wcb_empty['wp_bind_hidden'] ) : ?>
	data-wp-bind--hidden="<?php echo esc_attr( (string) $wcb_empty['wp_bind_hidden'] ); ?>"
	<?php endif; ?>
	<?php echo (bool) $wcb_empty['ssr_hidden'] ? 'hidden' : ''; ?>
>
	<div class="wcb-empty-state__icon" aria-hidden="true">
		<?php echo \WCB\Core\Icon::svg( (string) $wcb_empty['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?>
	</div>
	<h3 class="wcb-empty-state__title"><?php echo esc_html( (string) $wcb_empty['title'] ); ?></h3>
	<p class="wcb-empty-state__body"><?php echo esc_html( (string) $wcb_empty['body'] ); ?></p>
	<?php if ( '' !== (string) $wcb_empty['clear_action'] ) : ?>
	<button
		type="button"
		class="wcb-cbtn wcb-cbtn--ghost wcb-cbtn--sm"
		data-wp-on--click="<?php echo esc_attr( (string) $wcb_empty['clear_action'] ); ?>"
		<?php if ( '' !== (string) $wcb_empty['clear_hidden_bind'] ) : ?>
		data-wp-bind--hidden="<?php echo esc_attr( (string) $wcb_empty['clear_hidden_bind'] ); ?>"
		<?php endif; ?>
	>
		<?php echo esc_html( (string) $wcb_empty['clear_label'] ); ?>
	</button>
	<?php endif; ?>
</div>
