<?php
/**
 * Block render: wcb/job-filters — server-renders taxonomy filter dropdowns.
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

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$wcb_filter_category = isset( $_GET['wcb_category'] )
	? sanitize_text_field( wp_unslash( $_GET['wcb_category'] ) )
	: '';
$wcb_filter_type     = isset( $_GET['wcb_job_type'] )
	? sanitize_text_field( wp_unslash( $_GET['wcb_job_type'] ) )
	: '';
$wcb_filter_location = isset( $_GET['wcb_location'] )
	? sanitize_text_field( wp_unslash( $_GET['wcb_location'] ) )
	: '';
$wcb_filter_exp      = isset( $_GET['wcb_experience'] )
	? sanitize_text_field( wp_unslash( $_GET['wcb_experience'] ) )
	: '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$wcb_categories_raw  = get_terms(
	array(
		'taxonomy'   => 'wcb_category',
		'hide_empty' => false,
	)
);
$wcb_job_types_raw   = get_terms(
	array(
		'taxonomy'   => 'wcb_job_type',
		'hide_empty' => false,
	)
);
$wcb_locations_raw   = get_terms(
	array(
		'taxonomy'   => 'wcb_location',
		'hide_empty' => false,
	)
);
$wcb_experiences_raw = get_terms(
	array(
		'taxonomy'   => 'wcb_experience',
		'hide_empty' => false,
	)
);

$wcb_categories  = is_wp_error( $wcb_categories_raw ) ? array() : $wcb_categories_raw;
$wcb_job_types   = is_wp_error( $wcb_job_types_raw ) ? array() : $wcb_job_types_raw;
$wcb_locations   = is_wp_error( $wcb_locations_raw ) ? array() : $wcb_locations_raw;
$wcb_experiences = is_wp_error( $wcb_experiences_raw ) ? array() : $wcb_experiences_raw;

// Filters block owns 'filters' in the shared wcb-search namespace.
// Cast to object so JSON-encodes as {} even when no filters are active.
$wcb_active_filters = (object) array_filter(
	array(
		'wcb_category'   => $wcb_filter_category,
		'wcb_job_type'   => $wcb_filter_type,
		'wcb_location'   => $wcb_filter_location,
		'wcb_experience' => $wcb_filter_exp,
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
	<div class="wcb-filters-row">

		<select
			class="wcb-filter-select"
			name="wcb_category"
			aria-label="<?php esc_attr_e( 'Category', 'wp-career-board' ); ?>"
			data-wp-on--change="actions.updateFilter"
			data-wcb-filter="wcb_category"
		>
			<option value=""><?php esc_html_e( 'All Categories', 'wp-career-board' ); ?></option>
			<?php foreach ( $wcb_categories as $wcb_term ) : ?>
				<option value="<?php echo esc_attr( $wcb_term->slug ); ?>" <?php selected( $wcb_filter_category, $wcb_term->slug ); ?>>
					<?php echo esc_html( $wcb_term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<select
			class="wcb-filter-select"
			name="wcb_job_type"
			aria-label="<?php esc_attr_e( 'Job Type', 'wp-career-board' ); ?>"
			data-wp-on--change="actions.updateFilter"
			data-wcb-filter="wcb_job_type"
		>
			<option value=""><?php esc_html_e( 'All Types', 'wp-career-board' ); ?></option>
			<?php foreach ( $wcb_job_types as $wcb_term ) : ?>
				<option value="<?php echo esc_attr( $wcb_term->slug ); ?>" <?php selected( $wcb_filter_type, $wcb_term->slug ); ?>>
					<?php echo esc_html( $wcb_term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<select
			class="wcb-filter-select"
			name="wcb_location"
			aria-label="<?php esc_attr_e( 'Location', 'wp-career-board' ); ?>"
			data-wp-on--change="actions.updateFilter"
			data-wcb-filter="wcb_location"
		>
			<option value=""><?php esc_html_e( 'All Locations', 'wp-career-board' ); ?></option>
			<?php foreach ( $wcb_locations as $wcb_term ) : ?>
				<option value="<?php echo esc_attr( $wcb_term->slug ); ?>" <?php selected( $wcb_filter_location, $wcb_term->slug ); ?>>
					<?php echo esc_html( $wcb_term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<select
			class="wcb-filter-select"
			name="wcb_experience"
			aria-label="<?php esc_attr_e( 'Experience', 'wp-career-board' ); ?>"
			data-wp-on--change="actions.updateFilter"
			data-wcb-filter="wcb_experience"
		>
			<option value=""><?php esc_html_e( 'All Experience Levels', 'wp-career-board' ); ?></option>
			<?php foreach ( $wcb_experiences as $wcb_term ) : ?>
				<option value="<?php echo esc_attr( $wcb_term->slug ); ?>" <?php selected( $wcb_filter_exp, $wcb_term->slug ); ?>>
					<?php echo esc_html( $wcb_term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

	</div>
</div>
