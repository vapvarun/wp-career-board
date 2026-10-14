<?php
/**
 * Category picker: a button that opens nested category checkboxes.
 *
 * Shared by the job filters bar and the search hero. Ticking a parent ticks
 * every category under it; the store is the @wcb/category-picker module.
 *
 * Expects:
 *   $wcb_picker_options  Taxonomy::options( 'wcb_category' ) rows.
 *   $wcb_picker_selected Chosen slugs as a comma list (a parent stands for its children).
 *   $wcb_picker_name     Name of the hidden field ('' for none).
 *   $wcb_picker_filter   Filter key for the job filters bar ('' for none): the
 *                        field then calls wcb-search's updateFilter when the
 *                        panel closes with a new choice.
 *
 * @package WP_Career_Board
 * @since   1.8.1
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcb_picker_options = (array) ( $wcb_picker_options ?? array() );
$wcb_picker_chosen  = array_filter( explode( ',', (string) ( $wcb_picker_selected ?? '' ) ) );
$wcb_picker_slug_of = wp_list_pluck( $wcb_picker_options, 'slug', 'id' );

// Tick each chosen category and everything under it (rows are in tree order).
$wcb_picker_on    = array();
$wcb_picker_under = PHP_INT_MAX;
foreach ( $wcb_picker_options as $wcb_picker_opt ) {
	if ( $wcb_picker_opt->depth <= $wcb_picker_under ) {
		$wcb_picker_under = in_array( $wcb_picker_opt->slug, $wcb_picker_chosen, true ) ? $wcb_picker_opt->depth : PHP_INT_MAX;
	}
	if ( PHP_INT_MAX !== $wcb_picker_under ) {
		$wcb_picker_on[ $wcb_picker_opt->slug ] = $wcb_picker_opt->name;
	}
}

// One label per possible count, plural-correct in every locale (_n() needs the real number).
$wcb_picker_counts = array();
for ( $wcb_picker_n = 2, $wcb_picker_max = count( $wcb_picker_options ); $wcb_picker_n <= $wcb_picker_max; $wcb_picker_n++ ) {
	/* translators: %s: number of categories ticked, always 2 or more. */
	$wcb_picker_counts[ $wcb_picker_n ] = sprintf( _n( '%s category', '%s categories', $wcb_picker_n, 'wp-career-board' ), number_format_i18n( $wcb_picker_n ) );
}
$wcb_picker_any = __( 'All Categories', 'wp-career-board' );
// Count the way the URL does: a fully ticked parent is one choice, named after itself.
$wcb_picker_short = array();
foreach ( $wcb_picker_options as $wcb_picker_opt ) {
	if ( isset( $wcb_picker_on[ $wcb_picker_opt->slug ] ) && ! isset( $wcb_picker_on[ $wcb_picker_slug_of[ $wcb_picker_opt->parent ] ?? '' ] ) ) {
		$wcb_picker_short[] = $wcb_picker_opt->name;
	}
}
if ( count( $wcb_picker_short ) > 1 ) {
	$wcb_picker_label = $wcb_picker_counts[ count( $wcb_picker_short ) ];
} else {
	$wcb_picker_label = $wcb_picker_short ? (string) $wcb_picker_short[0] : $wcb_picker_any;
}

$wcb_picker_context = array(
	'open'       => false,
	'openedWith' => '',
	'label'      => $wcb_picker_label,
	'any'        => $wcb_picker_any,
	'counts'     => (object) $wcb_picker_counts,
);
$wcb_picker_panel   = wp_unique_id( 'wcb-cat-picker-' );
wp_enqueue_script_module( '@wcb/category-picker' );
?>
<div
	class="wcb-cat-picker"
	data-wp-interactive="wcb-category-picker"
	data-wp-context="<?php echo esc_attr( (string) wp_json_encode( $wcb_picker_context ) ); ?>"
	data-wp-init="callbacks.init"
	data-wp-on--keydown="actions.onKeydown"
	data-wp-on--focusout="actions.onFocusout"
	data-wp-on-document--click="actions.onDocumentClick"
>
	<input
		type="hidden"
		<?php if ( ! empty( $wcb_picker_name ) ) : ?>
			name="<?php echo esc_attr( $wcb_picker_name ); ?>"
		<?php endif; ?>
		<?php if ( ! empty( $wcb_picker_filter ) ) : ?>
			data-wcb-filter="<?php echo esc_attr( $wcb_picker_filter ); ?>"
			data-wp-on--change="wcb-search::actions.updateFilter"
		<?php endif; ?>
		value="<?php echo esc_attr( implode( ',', $wcb_picker_chosen ) ); ?>"
	/>
	<button type="button" class="wcb-cat-picker__toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $wcb_picker_panel ); ?>" data-wp-bind--aria-expanded="context.open" data-wp-on--click="actions.toggleOpen">
		<span class="screen-reader-text"><?php esc_html_e( 'Job category:', 'wp-career-board' ); ?></span>
		<span class="wcb-cat-picker__label" data-wp-text="context.label"><?php echo esc_html( $wcb_picker_label ); ?></span>
		<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
	</button>
	<div id="<?php echo esc_attr( $wcb_picker_panel ); ?>" class="wcb-cat-picker__panel" hidden data-wp-bind--hidden="!context.open" data-wp-watch="callbacks.focusPanel">
		<?php if ( count( $wcb_picker_options ) > 12 ) : ?>
			<input type="search" class="wcb-filter-panel__search" aria-label="<?php esc_attr_e( 'Search categories', 'wp-career-board' ); ?>" placeholder="<?php esc_attr_e( 'Search categories', 'wp-career-board' ); ?>" data-wp-on--input="actions.search" />
		<?php endif; ?>
		<fieldset class="wcb-cat-picker__fieldset" data-wp-on--change="actions.toggle">
			<legend class="screen-reader-text"><?php esc_html_e( 'Job categories', 'wp-career-board' ); ?></legend>
			<ul class="wcb-filter-panel__list">
				<?php foreach ( $wcb_picker_options as $wcb_picker_opt ) : ?>
					<li data-depth="<?php echo esc_attr( (string) min( 3, $wcb_picker_opt->depth ) ); ?>">
						<label class="wcb-filter-panel__option">
							<input type="checkbox" value="<?php echo esc_attr( $wcb_picker_opt->slug ); ?>" data-parent="<?php echo esc_attr( (string) ( $wcb_picker_slug_of[ $wcb_picker_opt->parent ] ?? '' ) ); ?>" <?php checked( isset( $wcb_picker_on[ $wcb_picker_opt->slug ] ) ); ?> />
							<span><?php echo esc_html( $wcb_picker_opt->name ); ?></span>
							<?php echo \WCB\Core\Taxonomy::parent_note( $wcb_picker_opt->parent_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( count( $wcb_picker_options ) > 12 ) : ?>
				<?php echo \WCB\Core\Taxonomy::no_match_note(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
			<?php endif; ?>
		</fieldset>
		<div class="wcb-cat-picker__actions">
			<button type="button" class="wcb-btn wcb-btn--ghost wcb-btn--sm" data-wp-on--click="actions.clear"><?php esc_html_e( 'Clear', 'wp-career-board' ); ?></button>
			<button type="button" class="wcb-btn wcb-btn--primary wcb-btn--sm" data-wp-on--click="actions.done"><?php esc_html_e( 'Done', 'wp-career-board' ); ?></button>
		</div>
	</div>
</div>
