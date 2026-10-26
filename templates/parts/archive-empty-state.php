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
 *   empty_title        string  Title when no filter is active, so an empty
 *                              site is not blamed on filters (Basecamp
 *                              10427548090). Empty = one title only.
 *   empty_body         string  Body to pair with empty_title.
 *   filtered_bind      string  Directive value that is truthy while a filter
 *                              is active (e.g. "state.hasActiveFilters").
 *   ssr_filtered       bool    Whether a filter is active at first paint.
 *
 * @package WP_Career_Board
 * @since   1.2.7
 *
 * @var array<string,mixed> $wcb_empty
 */

defined( 'ABSPATH' ) || exit;

$wcb_empty       = wp_parse_args(
	$wcb_empty ?? array(),
	array(
		'wp_bind_hidden'    => '',
		'ssr_hidden'        => false,
		'icon'              => 'inbox',
		'title'             => '',
		'body'              => '',
		'clear_action'      => '',
		'clear_hidden_bind' => '',
		'clear_label'       => __( 'Clear filters', 'wp-career-board' ),
		'empty_title'       => '',
		'empty_body'        => '',
		'filtered_bind'     => '',
		'ssr_filtered'      => false,
	)
);
$wcb_empty_split = '' !== (string) $wcb_empty['empty_title'] && '' !== (string) $wcb_empty['filtered_bind'];
?>
<div
	class="wcb-empty-state"
	role="status"
	<?php if ( '' !== (string) $wcb_empty['wp_bind_hidden'] ) : ?>
	data-wp-bind--hidden="<?php echo esc_attr( (string) $wcb_empty['wp_bind_hidden'] ); ?>"
	<?php endif; ?>
	<?php echo (bool) $wcb_empty['ssr_hidden'] ? 'hidden' : ''; ?>
>
	<div class="wcb-empty-state__icon" aria-hidden="true">
		<?php echo \WCB\Core\Icon::svg( (string) $wcb_empty['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?>
	</div>
	<?php if ( $wcb_empty_split ) : ?>
	<div data-wp-bind--hidden="!<?php echo esc_attr( (string) $wcb_empty['filtered_bind'] ); ?>"<?php echo $wcb_empty['ssr_filtered'] ? '' : ' hidden'; ?>>
	<?php endif; ?>
	<h3 class="wcb-empty-state__title"><?php echo esc_html( (string) $wcb_empty['title'] ); ?></h3>
	<p class="wcb-empty-state__body"><?php echo esc_html( (string) $wcb_empty['body'] ); ?></p>
	<?php if ( $wcb_empty_split ) : ?>
	</div>
	<div data-wp-bind--hidden="<?php echo esc_attr( (string) $wcb_empty['filtered_bind'] ); ?>"<?php echo $wcb_empty['ssr_filtered'] ? ' hidden' : ''; ?>>
		<h3 class="wcb-empty-state__title"><?php echo esc_html( (string) $wcb_empty['empty_title'] ); ?></h3>
		<p class="wcb-empty-state__body"><?php echo esc_html( (string) $wcb_empty['empty_body'] ); ?></p>
	</div>
	<?php endif; ?>
	<?php if ( '' !== (string) $wcb_empty['clear_action'] ) : ?>
	<button
		type="button"
		class="wcb-btn wcb-btn--outline wcb-btn--sm"
		data-wp-on--click="<?php echo esc_attr( (string) $wcb_empty['clear_action'] ); ?>"
		<?php if ( '' !== (string) $wcb_empty['clear_hidden_bind'] ) : ?>
		data-wp-bind--hidden="<?php echo esc_attr( (string) $wcb_empty['clear_hidden_bind'] ); ?>"
		<?php endif; ?>
	>
		<?php echo esc_html( (string) $wcb_empty['clear_label'] ); ?>
	</button>
	<?php endif; ?>
</div>
