<?php
/**
 * Canonical search + sort + listings toolbar for archive pages.
 *
 * Shared across the 3 archives (Find Jobs, Companies, Find Candidates).
 * Emits two rows:
 *   .wcb-search-sort-row    [search input wrap] [sort select]
 *   .wcb-listings-toolbar   [results count + injected slot] [view switcher]
 *
 * Expected in $wcb_toolbar:
 *   show_search           bool    Render search input. Default true. Jobs sets
 *                                 false when wcb/job-search is on the same page.
 *   search_id             string  <input id> for label association. REQUIRED
 *                                 when show_search is true.
 *   search_sr_label       string  Pre-translated screen-reader label.
 *   search_placeholder    string  Pre-translated placeholder.
 *   search_value_bind     string  data-wp-bind--value directive value.
 *                                 Default 'state.searchQuery'.
 *   search_input_action   string  data-wp-on--input action.
 *                                 Default 'actions.updateSearch'.
 *
 *   show_sort             bool    Render sort dropdown. Default true.
 *   sort_aria_label       string  Pre-translated aria-label.
 *   sort_value_bind       string  data-wp-bind--value directive value.
 *                                 Default 'state.sortBy'.
 *   sort_change_action    string  data-wp-on--change action.
 *                                 Default 'actions.changeSort'.
 *   sort_options          array   [value => translated label] pairs.
 *                                 Default newest/oldest.
 *
 *   results_ssr_html      string  Caller-escaped HTML fallback for SSR.
 *                                 Reactive data-wp-text="state.resultsLabel"
 *                                 replaces it on hydration.
 *   results_aria_live     string  aria-live mode. Default 'polite'.
 *
 *   inject_slot_key       string  Optional `wcb_module_renders` array key
 *                                 whose HTML is injected next to the count
 *                                 (e.g. 'alerts_subscribe' for Pro's Alert
 *                                 Me button on Jobs). Empty = skip.
 *
 *   show_view_switcher    bool    Render grid/list toggle. Default true.
 *   switcher_aria_label   string  Pre-translated aria-label for the group.
 *   switcher_list_label   string  Pre-translated aria-label for list btn.
 *   switcher_grid_label   string  Pre-translated aria-label for grid btn.
 *   switcher_list_action  string  data-wp-on--click for list.
 *                                 Default 'actions.setListLayout'.
 *   switcher_grid_action  string  data-wp-on--click for grid.
 *                                 Default 'actions.setGridLayout'.
 *   switcher_list_active  string  data-wp-class--wcb-active value for list.
 *                                 Default 'state.isList'.
 *   switcher_grid_active  string  data-wp-class--wcb-active value for grid.
 *                                 Default 'state.isGrid'.
 *
 * @package WP_Career_Board
 * @since   1.2.0
 *
 * @var array<string,mixed> $wcb_toolbar
 */

defined( 'ABSPATH' ) || exit;

$wcb_toolbar = wp_parse_args(
	$wcb_toolbar ?? array(),
	array(
		'show_search'          => true,
		'search_id'            => 'wcb-archive-search',
		'search_sr_label'      => '',
		'search_placeholder'   => '',
		'search_value_bind'    => 'state.searchQuery',
		'search_input_action'  => 'actions.updateSearch',
		'show_sort'            => true,
		'sort_aria_label'      => '',
		'sort_value_bind'      => 'state.sortBy',
		'sort_change_action'   => 'actions.changeSort',
		'sort_options'         => array(
			'date_desc' => __( 'Newest first', 'wp-career-board' ),
			'date_asc'  => __( 'Oldest first', 'wp-career-board' ),
		),
		'results_ssr_html'     => '',
		'results_aria_live'    => 'polite',
		'inject_slot_key'      => '',
		'show_view_switcher'   => true,
		'switcher_aria_label'  => __( 'View layout', 'wp-career-board' ),
		'switcher_list_label'  => __( 'List view', 'wp-career-board' ),
		'switcher_grid_label'  => __( 'Grid view', 'wp-career-board' ),
		'switcher_list_action' => 'actions.setListLayout',
		'switcher_grid_action' => 'actions.setGridLayout',
		'switcher_list_active' => 'state.isList',
		'switcher_grid_active' => 'state.isGrid',
	)
);
?>
<div class="wcb-search-sort-row">
	<?php if ( (bool) $wcb_toolbar['show_search'] ) : ?>
	<div class="wcb-search-wrap">
		<span class="wcb-search-icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
		</span>
		<label class="screen-reader-text" for="<?php echo esc_attr( (string) $wcb_toolbar['search_id'] ); ?>">
			<?php echo esc_html( (string) $wcb_toolbar['search_sr_label'] ); ?>
		</label>
		<input
			type="search"
			id="<?php echo esc_attr( (string) $wcb_toolbar['search_id'] ); ?>"
			class="wcb-listings-search"
			placeholder="<?php echo esc_attr( (string) $wcb_toolbar['search_placeholder'] ); ?>"
			data-wp-bind--value="<?php echo esc_attr( (string) $wcb_toolbar['search_value_bind'] ); ?>"
			data-wp-on--input="<?php echo esc_attr( (string) $wcb_toolbar['search_input_action'] ); ?>"
		/>
	</div>
	<?php endif; ?>

	<?php if ( (bool) $wcb_toolbar['show_sort'] ) : ?>
	<select
		class="wcb-sort-select"
		aria-label="<?php echo esc_attr( (string) $wcb_toolbar['sort_aria_label'] ); ?>"
		data-wp-bind--value="<?php echo esc_attr( (string) $wcb_toolbar['sort_value_bind'] ); ?>"
		data-wp-on--change="<?php echo esc_attr( (string) $wcb_toolbar['sort_change_action'] ); ?>"
	>
		<?php foreach ( (array) $wcb_toolbar['sort_options'] as $wcb_sort_value => $wcb_sort_label ) : ?>
			<option value="<?php echo esc_attr( (string) $wcb_sort_value ); ?>"><?php echo esc_html( (string) $wcb_sort_label ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php endif; ?>
</div>

<div class="wcb-listings-toolbar">
	<div class="wcb-toolbar-start">
		<p class="wcb-results-count" aria-live="<?php echo esc_attr( (string) $wcb_toolbar['results_aria_live'] ); ?>" data-wp-text="state.resultsLabel">
			<?php
			// Server-side fallback content. Already-escaped HTML expected
			// (callers use _n() + sprintf() + esc_html()).
			echo $wcb_toolbar['results_ssr_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller-escaped HTML fallback for SSR-only first paint.
			?>
		</p>
		<?php
		if ( '' !== (string) $wcb_toolbar['inject_slot_key'] ) {
			$wcb_module_renders = (array) apply_filters( 'wcb_module_renders', array() );
			$wcb_slot_html      = (string) ( $wcb_module_renders[ (string) $wcb_toolbar['inject_slot_key'] ] ?? '' );
			if ( '' !== $wcb_slot_html ) {
				echo wp_kses_post( $wcb_slot_html );
			}
		}
		?>
	</div>

	<?php if ( (bool) $wcb_toolbar['show_view_switcher'] ) : ?>
	<div class="wcb-view-switcher" role="group" aria-label="<?php echo esc_attr( (string) $wcb_toolbar['switcher_aria_label'] ); ?>">
		<button
			type="button"
			class="wcb-layout-btn"
			data-wp-class--wcb-active="<?php echo esc_attr( (string) $wcb_toolbar['switcher_list_active'] ); ?>"
			data-wp-on--click="<?php echo esc_attr( (string) $wcb_toolbar['switcher_list_action'] ); ?>"
			aria-label="<?php echo esc_attr( (string) $wcb_toolbar['switcher_list_label'] ); ?>"
		>
			<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
		</button>
		<button
			type="button"
			class="wcb-layout-btn"
			data-wp-class--wcb-active="<?php echo esc_attr( (string) $wcb_toolbar['switcher_grid_active'] ); ?>"
			data-wp-on--click="<?php echo esc_attr( (string) $wcb_toolbar['switcher_grid_action'] ); ?>"
			aria-label="<?php echo esc_attr( (string) $wcb_toolbar['switcher_grid_label'] ); ?>"
		>
			<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
		</button>
	</div>
	<?php endif; ?>
</div>
