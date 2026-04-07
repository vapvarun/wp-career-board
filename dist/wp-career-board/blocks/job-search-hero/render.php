<?php
/**
 * Block render: wp-career-board/job-search-hero — hero search form.
 *
 * Submits a plain GET form to the jobs archive page. Params consumed by
 * wp-career-board/job-listings and wp-career-board/job-filters:
 *   wcb_search, wcb_category, wcb_location, wcb_job_type
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$wcb_layout        = in_array( (string) ( $attributes['layout'] ?? 'horizontal' ), array( 'horizontal', 'vertical' ), true )
	? (string) $attributes['layout']
	: 'horizontal';
$wcb_placeholder   = trim( (string) ( $attributes['placeholder'] ?? '' ) );
$wcb_placeholder   = '' !== $wcb_placeholder ? $wcb_placeholder : __( 'Search jobs…', 'wp-career-board' );
$wcb_button_label  = trim( (string) ( $attributes['buttonLabel'] ?? '' ) );
$wcb_button_label  = '' !== $wcb_button_label ? $wcb_button_label : __( 'Search', 'wp-career-board' );
$wcb_show_category = (bool) ( $attributes['showCategoryFilter'] ?? true );
$wcb_show_location = (bool) ( $attributes['showLocationFilter'] ?? true );
$wcb_show_type     = (bool) ( $attributes['showJobTypeFilter'] ?? true );

$wcb_settings   = (array) get_option( 'wcb_settings', array() );
$wcb_action_url = ! empty( $wcb_settings['jobs_archive_page'] )
	? (string) get_permalink( (int) $wcb_settings['jobs_archive_page'] )
	: home_url( '/' );

// Pre-populate from current GET params for coexistence with job-filters block.
$wcb_current_search   = isset( $_GET['wcb_search'] ) ? sanitize_text_field( wp_unslash( $_GET['wcb_search'] ) ) : '';
$wcb_current_category = isset( $_GET['wcb_category'] ) ? sanitize_text_field( wp_unslash( $_GET['wcb_category'] ) ) : '';
$wcb_current_location = isset( $_GET['wcb_location'] ) ? sanitize_text_field( wp_unslash( $_GET['wcb_location'] ) ) : '';
$wcb_current_type     = isset( $_GET['wcb_job_type'] ) ? sanitize_text_field( wp_unslash( $_GET['wcb_job_type'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// Load taxonomy terms for filter dropdowns.
// Registered taxonomy slug is 'wcb_category' (confirmed in core/class-plugin.php).
$wcb_categories = $wcb_show_category ? get_terms(
	array(
		'taxonomy'   => 'wcb_category',
		'hide_empty' => true,
	)
) : array();
$wcb_locations  = $wcb_show_location ? get_terms(
	array(
		'taxonomy'   => 'wcb_location',
		'hide_empty' => true,
	)
) : array();
$wcb_job_types  = $wcb_show_type ? get_terms(
	array(
		'taxonomy'   => 'wcb_job_type',
		'hide_empty' => true,
	)
) : array();

$wcb_categories = is_wp_error( $wcb_categories ) ? array() : $wcb_categories;
$wcb_locations  = is_wp_error( $wcb_locations ) ? array() : $wcb_locations;
$wcb_job_types  = is_wp_error( $wcb_job_types ) ? array() : $wcb_job_types;
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-search-hero wcb-search-hero--' . esc_attr( $wcb_layout ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<form class="wcb-search-hero__form" method="get" action="<?php echo esc_url( $wcb_action_url ); ?>" role="search">

		<div class="wcb-search-hero__field wcb-search-hero__field--keyword">
			<label class="screen-reader-text" for="wcb-hero-search">
				<?php esc_html_e( 'Search jobs', 'wp-career-board' ); ?>
			</label>
			<input
				id="wcb-hero-search"
				type="search"
				name="wcb_search"
				class="wcb-search-hero__input"
				aria-label="<?php esc_attr_e( 'Search jobs', 'wp-career-board' ); ?>"
				placeholder="<?php echo esc_attr( $wcb_placeholder ); ?>"
				value="<?php echo esc_attr( $wcb_current_search ); ?>"
			/>
		</div>

		<?php if ( $wcb_show_category && ! empty( $wcb_categories ) ) : ?>
			<div class="wcb-search-hero__field wcb-search-hero__field--select">
				<label class="screen-reader-text" for="wcb-hero-category">
					<?php esc_html_e( 'Job category', 'wp-career-board' ); ?>
				</label>
				<select id="wcb-hero-category" name="wcb_category" class="wcb-search-hero__select">
					<option value=""><?php esc_html_e( 'All Categories', 'wp-career-board' ); ?></option>
					<?php foreach ( $wcb_categories as $wcb_term ) : ?>
						<option value="<?php echo esc_attr( $wcb_term->slug ); ?>" <?php selected( $wcb_current_category, $wcb_term->slug ); ?>>
							<?php echo esc_html( $wcb_term->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>

		<?php if ( $wcb_show_location && ! empty( $wcb_locations ) ) : ?>
			<div class="wcb-search-hero__field wcb-search-hero__field--select">
				<label class="screen-reader-text" for="wcb-hero-location">
					<?php esc_html_e( 'Location', 'wp-career-board' ); ?>
				</label>
				<select id="wcb-hero-location" name="wcb_location" class="wcb-search-hero__select">
					<option value=""><?php esc_html_e( 'All Locations', 'wp-career-board' ); ?></option>
					<?php foreach ( $wcb_locations as $wcb_term ) : ?>
						<option value="<?php echo esc_attr( $wcb_term->slug ); ?>" <?php selected( $wcb_current_location, $wcb_term->slug ); ?>>
							<?php echo esc_html( $wcb_term->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>

		<?php if ( $wcb_show_type && ! empty( $wcb_job_types ) ) : ?>
			<div class="wcb-search-hero__field wcb-search-hero__field--select">
				<label class="screen-reader-text" for="wcb-hero-type">
					<?php esc_html_e( 'Job type', 'wp-career-board' ); ?>
				</label>
				<select id="wcb-hero-type" name="wcb_job_type" class="wcb-search-hero__select">
					<option value=""><?php esc_html_e( 'All Types', 'wp-career-board' ); ?></option>
					<?php foreach ( $wcb_job_types as $wcb_term ) : ?>
						<option value="<?php echo esc_attr( $wcb_term->slug ); ?>" <?php selected( $wcb_current_type, $wcb_term->slug ); ?>>
							<?php echo esc_html( $wcb_term->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>

		<div class="wcb-search-hero__field wcb-search-hero__field--submit">
			<button type="submit" class="wcb-search-hero__button">
				<i data-lucide="search" aria-hidden="true"></i>
				<?php echo esc_html( $wcb_button_label ); ?>
			</button>
		</div>

	</form>
</div>
