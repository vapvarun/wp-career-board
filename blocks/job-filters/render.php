<?php
/**
 * Block render: wp-career-board/job-filters — server-renders taxonomy filter dropdowns.
 *
 * WordPress injects:
 *   $attributes  (array)    Block attributes defined in block.json.
 *   $content     (string)   Inner block content (empty for this block).
 *   $block       (WP_Block) Block instance object.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// Read the URL the way the listings block does (canonical names and their
// wcb_* aliases), so a shared or Back-navigated link shows matching selects.
$wcb_url               = \WCB\Modules\Jobs\JobSearch::from_url();
$wcb_first             = static fn( string $key ): string => (string) strtok( (string) ( $wcb_url[ $key ] ?? '' ), ',' );
$wcb_filter_category   = (string) ( $wcb_url['category'] ?? '' ); // Several at once: a comma list.
$wcb_filter_type       = $wcb_first( 'type' );
$wcb_filter_location   = $wcb_first( 'location' );
$wcb_filter_exp        = $wcb_first( 'experience' );
$wcb_filter_salary_min = (int) ( $wcb_url['salary_min'] ?? 0 );
$wcb_filter_salary_max = (int) ( $wcb_url['salary_max'] ?? 0 );
$wcb_filter_remote     = ! empty( $wcb_url['remote'] );

$wcb_categories  = \WCB\Core\Taxonomy::options( 'wcb_category' );
$wcb_job_types   = \WCB\Core\Taxonomy::options( 'wcb_job_type' );
$wcb_locations   = \WCB\Core\Taxonomy::options( 'wcb_location' );
$wcb_experiences = \WCB\Core\Taxonomy::options( 'wcb_experience' );

// Filters block owns 'filters' in the shared wcb-search namespace.
// Cast to object so JSON-encodes as {} even when no filters are active.
$wcb_active_filters = (object) array_filter(
	array(
		'wcb_category'   => $wcb_filter_category,
		'wcb_job_type'   => $wcb_filter_type,
		'wcb_location'   => $wcb_filter_location,
		'wcb_experience' => $wcb_filter_exp,
		'salary_min'     => $wcb_filter_salary_min ? $wcb_filter_salary_min : '',
		'salary_max'     => $wcb_filter_salary_max ? $wcb_filter_salary_max : '',
		'remote'         => $wcb_filter_remote ? '1' : '',
	)
);

wp_interactivity_state(
	'wcb-search',
	array(
		'filters' => $wcb_active_filters,
	)
);
?>
<div
	<?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-search"
>
	<?php
	// Tablet and phone: one "Filters (n)" button instead of a wall of
	// dropdowns above the results (CSS-only toggle, no JS needed to open).
	$wcb_filters_toggle_id = wp_unique_id( 'wcb-job-filters-toggle-' );
	?>
	<input type="checkbox" id="<?php echo esc_attr( $wcb_filters_toggle_id ); ?>" class="wcb-filters-toggle-input" />
	<label for="<?php echo esc_attr( $wcb_filters_toggle_id ); ?>" class="wcb-filters-toggle">
		<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M7 12h10M10 18h4"/></svg>
		<?php esc_html_e( 'Filters', 'wp-career-board' ); ?>
		<span class="wcb-filters-toggle__count<?php echo count( (array) $wcb_active_filters ) ? ' wcb-shown' : ''; ?>" data-wp-text="state.activeFilterCount" data-wp-class--wcb-shown="state.activeFilterCount"><?php echo esc_html( (string) count( (array) $wcb_active_filters ) ); ?></span>
	</label>
	<div class="wcb-filters-row">

		<?php
		$wcb_picker_options  = $wcb_categories;
		$wcb_picker_selected = $wcb_filter_category;
		$wcb_picker_name     = '';
		$wcb_picker_filter   = 'wcb_category';
		require WCB_DIR . 'templates/parts/category-picker.php';
		?>

		<select class="wcb-filter-select" name="wcb_job_type" aria-label="<?php esc_attr_e( 'Job type', 'wp-career-board' ); ?>" data-wp-on--change="actions.updateFilter" data-wcb-filter="wcb_job_type">
			<option value=""><?php esc_html_e( 'All Types', 'wp-career-board' ); ?></option>
			<?php foreach ( $wcb_job_types as $wcb_term ) : ?>
				<option value="<?php echo esc_attr( $wcb_term->slug ); ?>" <?php selected( $wcb_filter_type, $wcb_term->slug ); ?>>
					<?php echo esc_html( $wcb_term->label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<select class="wcb-filter-select" name="wcb_location" aria-label="<?php esc_attr_e( 'Location', 'wp-career-board' ); ?>" data-wp-on--change="actions.updateFilter" data-wcb-filter="wcb_location">
			<option value=""><?php esc_html_e( 'All Locations', 'wp-career-board' ); ?></option>
			<?php foreach ( $wcb_locations as $wcb_term ) : ?>
				<option value="<?php echo esc_attr( $wcb_term->slug ); ?>" <?php selected( $wcb_filter_location, $wcb_term->slug ); ?>>
					<?php echo esc_html( $wcb_term->label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<select class="wcb-filter-select" name="wcb_experience" aria-label="<?php esc_attr_e( 'Experience', 'wp-career-board' ); ?>" data-wp-on--change="actions.updateFilter" data-wcb-filter="wcb_experience">
			<option value=""><?php esc_html_e( 'All Experience Levels', 'wp-career-board' ); ?></option>
			<?php foreach ( $wcb_experiences as $wcb_term ) : ?>
				<option value="<?php echo esc_attr( $wcb_term->slug ); ?>" <?php selected( $wcb_filter_exp, $wcb_term->slug ); ?>>
					<?php echo esc_html( $wcb_term->label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<div class="wcb-filter-salary">
			<input type="number" class="wcb-filter-input" name="salary_min" min="0" step="any" aria-label="<?php esc_attr_e( 'Minimum salary', 'wp-career-board' ); ?>" placeholder="<?php esc_attr_e( 'Min salary', 'wp-career-board' ); ?>" value="<?php echo esc_attr( $wcb_filter_salary_min ? (string) $wcb_filter_salary_min : '' ); ?>" data-wp-on--change="actions.updateFilter" data-wcb-filter="salary_min" />
			<input type="number" class="wcb-filter-input" name="salary_max" min="0" step="any" aria-label="<?php esc_attr_e( 'Maximum salary', 'wp-career-board' ); ?>" placeholder="<?php esc_attr_e( 'Max salary', 'wp-career-board' ); ?>" value="<?php echo esc_attr( $wcb_filter_salary_max ? (string) $wcb_filter_salary_max : '' ); ?>" data-wp-on--change="actions.updateFilter" data-wcb-filter="salary_max" />
		</div>

		<label class="wcb-filter-remote">
			<input
				type="checkbox"
				name="remote"
				value="1"
				<?php checked( $wcb_filter_remote ); ?>
				data-wp-on--change="actions.updateFilter"
				data-wcb-filter="remote"
			/>
			<?php esc_html_e( 'Remote only', 'wp-career-board' ); ?>
		</label>

	</div>
</div>
