<?php
/**
 * Canonical "Load more" button for archive pages.
 *
 * Shared across the 3 archives (Find Jobs, Companies, Find Candidates).
 * The wrapper toggles visibility via `state.hasMore` and the inner button
 * is disabled while `state.loading` is true. Label text swaps to "Loading…"
 * via the .wcb-load-more-loading span.
 *
 * Expected in $wcb_load_more:
 *   label    string  Pre-translated visible label (e.g. "Load more jobs").
 *                    REQUIRED.
 *   loading  string  Pre-translated loading label.
 *                    Default "Loading…" in wp-career-board.
 *   action   string  Interactivity action name.
 *                    Default "actions.loadMore".
 *
 * @package WP_Career_Board
 * @since   1.2.7
 *
 * @var array<string,mixed> $wcb_load_more
 */

defined( 'ABSPATH' ) || exit;

$wcb_load_more = wp_parse_args(
	$wcb_load_more ?? array(),
	array(
		'label'   => '',
		'loading' => __( 'Loading&hellip;', 'wp-career-board' ),
		'action'  => 'actions.loadMore',
	)
);
?>
<div class="wcb-load-more-wrap" data-wp-class--wcb-shown="state.hasMore">
	<button
		type="button"
		class="wcb-cbtn wcb-cbtn--ghost wcb-load-more-btn"
		data-wp-on--click="<?php echo esc_attr( (string) $wcb_load_more['action'] ); ?>"
		data-wp-bind--disabled="state.loading"
	>
		<span data-wp-class--wcb-hidden="state.loading"><?php echo esc_html( (string) $wcb_load_more['label'] ); ?></span>
		<span class="wcb-load-more-loading" data-wp-class--wcb-shown="state.loading"><?php echo esc_html( (string) $wcb_load_more['loading'] ); ?></span>
	</button>
</div>
