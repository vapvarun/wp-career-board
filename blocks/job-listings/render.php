<?php
/**
 * Block render: wcb/job-listings — server-renders job cards and seeds Interactivity API state.
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

$wcb_site_settings    = (array) get_option( 'wcb_settings', array() );
$wcb_setting_per_page = ! empty( $wcb_site_settings['jobs_per_page'] ) ? (int) $wcb_site_settings['jobs_per_page'] : 10;
$wcb_per_page         = ! empty( $attributes['perPage'] ) ? (int) $attributes['perPage'] : $wcb_setting_per_page;
$wcb_raw_layout       = (string) ( $attributes['layout'] ?? 'grid' );
$wcb_layout           = in_array( $wcb_raw_layout, array( 'grid', 'list' ), true ) ? $wcb_raw_layout : 'grid';

$wcb_jobs_raw = get_posts(
	apply_filters(
		'wcb_job_listings_query_args',
		array(
			'post_type'   => 'wcb_job',
			'post_status' => 'publish',
			'numberposts' => $wcb_per_page,
			'orderby'     => 'date',
			'order'       => 'DESC',
		)
	)
);

$wcb_current_user_id = get_current_user_id();
$wcb_bookmarks       = $wcb_current_user_id
	? array_map( 'intval', (array) get_user_meta( $wcb_current_user_id, '_wcb_bookmark', false ) )
	: array();

/**
 * Format salary as a human-readable label.
 *
 * @param string $wcb_min      Minimum salary.
 * @param string $wcb_max      Maximum salary.
 * @param string $wcb_currency Currency code (default USD).
 * @return string
 */
$wcb_format_salary = static function ( string $wcb_min, string $wcb_max, string $wcb_currency = 'USD', string $wcb_type = 'yearly' ): string {
	if ( ! $wcb_min && ! $wcb_max ) {
		return '';
	}
	$wcb_symbols = array(
		'USD' => '$',
		'EUR' => '€',
		'GBP' => '£',
		'CAD' => 'CA$',
		'AUD' => 'A$',
		'INR' => '₹',
		'SGD' => 'S$',
	);
	$wcb_symbol  = isset( $wcb_symbols[ $wcb_currency ] ) ? $wcb_symbols[ $wcb_currency ] : $wcb_currency . ' ';
	$wcb_suffix  = match ( $wcb_type ) {
		'monthly' => '/mo',
		'hourly'  => '/hr',
		default   => '/yr',
	};
	$wcb_fmt = static function ( string $n ) use ( $wcb_symbol ): string {
		$wcb_n = (int) $n;
		if ( $wcb_n >= 1000 ) {
			return $wcb_symbol . round( $wcb_n / 1000 ) . 'k';
		}
		return $wcb_symbol . $wcb_n;
	};
	if ( $wcb_min && $wcb_max ) {
		return $wcb_fmt( $wcb_min ) . '–' . $wcb_fmt( $wcb_max ) . $wcb_suffix;
	}
	if ( $wcb_min ) {
		return $wcb_fmt( $wcb_min ) . '+' . $wcb_suffix;
	}
	return 'Up to ' . $wcb_fmt( $wcb_max ) . $wcb_suffix;
};

/**
 * Get company initials from name (up to 2 chars).
 *
 * @param string $wcb_name Company name.
 * @return string
 */
$wcb_initials = static function ( string $wcb_name ): string {
	$wcb_words = array_filter( explode( ' ', trim( $wcb_name ) ) );
	$wcb_init  = '';
	foreach ( array_slice( $wcb_words, 0, 2 ) as $wcb_word ) {
		$wcb_init .= mb_strtoupper( mb_substr( $wcb_word, 0, 1 ) );
	}
	return $wcb_init ? $wcb_init : '?';
};

$wcb_jobs_state = array();

foreach ( $wcb_jobs_raw as $wcb_job_post ) {
	$wcb_location_terms   = wp_get_object_terms( $wcb_job_post->ID, 'wcb_location', array( 'fields' => 'names' ) );
	$wcb_type_terms       = wp_get_object_terms( $wcb_job_post->ID, 'wcb_job_type', array( 'fields' => 'names' ) );
	$wcb_exp_terms        = wp_get_object_terms( $wcb_job_post->ID, 'wcb_experience', array( 'fields' => 'names' ) );
	$wcb_cat_terms        = wp_get_object_terms( $wcb_job_post->ID, 'wcb_category', array( 'fields' => 'names' ) );
	$wcb_salary_min       = (string) get_post_meta( $wcb_job_post->ID, '_wcb_salary_min', true );
	$wcb_salary_max       = (string) get_post_meta( $wcb_job_post->ID, '_wcb_salary_max', true );
	$wcb_salary_currency  = (string) get_post_meta( $wcb_job_post->ID, '_wcb_salary_currency', true );
	$wcb_salary_type_raw  = (string) get_post_meta( $wcb_job_post->ID, '_wcb_salary_type', true );
	$wcb_salary_type      = in_array( $wcb_salary_type_raw, array( 'yearly', 'monthly', 'hourly' ), true ) ? $wcb_salary_type_raw : 'yearly';
	$wcb_deadline_val     = (string) get_post_meta( $wcb_job_post->ID, '_wcb_deadline', true );
	$wcb_company_name_val = (string) get_post_meta( $wcb_job_post->ID, '_wcb_company_name', true );
	$wcb_author_id        = (int) $wcb_job_post->post_author;
	$wcb_company_post_id  = (int) get_user_meta( $wcb_author_id, '_wcb_company_id', true );
	$wcb_trust            = $wcb_company_post_id ? (string) get_post_meta( $wcb_company_post_id, '_wcb_trust_level', true ) : '';

	$wcb_jobs_state[] = array(
		'id'           => $wcb_job_post->ID,
		'title'        => $wcb_job_post->post_title,
		'permalink'    => get_permalink( $wcb_job_post->ID ),
		'company'      => $wcb_company_name_val,
		'initials'     => $wcb_initials( $wcb_company_name_val ),
		'verified'     => in_array( $wcb_trust, array( 'verified', 'trusted', 'premium' ), true ),
		'location'     => is_wp_error( $wcb_location_terms ) ? '' : implode( ', ', $wcb_location_terms ),
		'type'         => is_wp_error( $wcb_type_terms ) ? '' : implode( ', ', $wcb_type_terms ),
		'experience'   => is_wp_error( $wcb_exp_terms ) ? '' : implode( ', ', $wcb_exp_terms ),
		'category'     => is_wp_error( $wcb_cat_terms ) ? '' : implode( ', ', $wcb_cat_terms ),
		'remote'       => '1' === get_post_meta( $wcb_job_post->ID, '_wcb_remote', true ),
		'featured'     => '1' === get_post_meta( $wcb_job_post->ID, '_wcb_featured', true ),
		'salary_min'   => $wcb_salary_min,
		'salary_max'   => $wcb_salary_max,
		'salary_label' => $wcb_format_salary( $wcb_salary_min, $wcb_salary_max, $wcb_salary_currency ? $wcb_salary_currency : 'USD', $wcb_salary_type ),
		'deadline'     => $wcb_deadline_val ? date_i18n( get_option( 'date_format' ), (int) strtotime( $wcb_deadline_val ) ) : '',
		'days_ago'     => human_time_diff( (int) strtotime( $wcb_job_post->post_date ), time() ) . ' ago',
		'bookmarked'   => in_array( $wcb_job_post->ID, $wcb_bookmarks, true ),
		'excerpt'      => wp_trim_words( (string) preg_replace( '/[*_#`]+/', '', wp_strip_all_tags( $wcb_job_post->post_content ) ), 25, '…' ),
	);
}

$wcb_type_terms_raw = get_terms(
	array(
		'taxonomy'   => 'wcb_job_type',
		'hide_empty' => false,
	)
);
$wcb_type_opts      = array_values(
	array_map(
		static function ( \WP_Term $t ): array {
			return array(
				'slug' => $t->slug,
				'name' => $t->name,
			);
		},
		array_filter(
			is_array( $wcb_type_terms_raw ) ? $wcb_type_terms_raw : array(),
			static function ( \WP_Term $t ): bool {
				return 'remote' !== $t->slug;
			}
		)
	)
);

$wcb_exp_terms_raw = get_terms(
	array(
		'taxonomy'   => 'wcb_experience',
		'hide_empty' => false,
	)
);
$wcb_exp_opts      = array_map(
	static function ( \WP_Term $t ): array {
		return array(
			'slug' => $t->slug,
			'name' => $t->name,
		);
	},
	is_array( $wcb_exp_terms_raw ) ? $wcb_exp_terms_raw : array()
);

$wcb_total_count = (int) wp_count_posts( 'wcb_job' )->publish;

$wcb_state = array(
	'jobs'          => $wcb_jobs_state,
	'page'          => 1,
	'perPage'       => $wcb_per_page,
	'layout'        => $wcb_layout,
	'loading'       => false,
	'hasMore'       => count( $wcb_jobs_raw ) >= $wcb_per_page,
	'apiBase'       => (string) apply_filters( 'wcb_job_listings_api_base', rest_url( 'wcb/v1/jobs' ) ),
	'nonce'         => wp_create_nonce( 'wp_rest' ),
	'totalCount'    => $wcb_total_count,
	'searchQuery'   => '',
	'activeFilters' => (object) array(),
	'sortBy'        => 'date_desc',
	'filterOptions' => array(
		'types'       => $wcb_type_opts,
		'experiences' => $wcb_exp_opts,
	),
);

$wcb_archive_page_id = (int) ( $wcb_site_settings['jobs_archive_page'] ?? 0 );
$wcb_page_heading    = ( $wcb_archive_page_id && (int) get_queried_object_id() === $wcb_archive_page_id )
	? (string) get_the_title( $wcb_archive_page_id )
	: '';

wp_interactivity_state( 'wcb-job-listings', $wcb_state );
?>
<div
	<?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-job-listings"
	data-wp-init="callbacks.init"
>
	<?php if ( $wcb_page_heading ) : ?>
	<h1 class="wcb-page-heading"><?php echo esc_html( $wcb_page_heading ); ?></h1>
	<?php endif; ?>
	<div class="wcb-listings-header">
		<div class="wcb-search-sort-row">
			<div class="wcb-search-wrap">
				<span class="wcb-search-icon" aria-hidden="true">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
				</span>
				<input
					type="search"
					class="wcb-listings-search"
					placeholder="<?php esc_attr_e( 'Search jobs…', 'wp-career-board' ); ?>"
					data-wp-bind--value="state.searchQuery"
					data-wp-on--input="actions.updateSearch"
				/>
			</div>
			<select class="wcb-sort-select" data-wp-on--change="actions.changeSort" data-wp-bind--value="state.sortBy">
				<option value="date_desc"><?php esc_html_e( 'Newest first', 'wp-career-board' ); ?></option>
				<option value="date_asc"><?php esc_html_e( 'Oldest first', 'wp-career-board' ); ?></option>
			</select>
		</div>

		<div class="wcb-chip-bar" role="group" aria-label="<?php esc_attr_e( 'Filter by job type', 'wp-career-board' ); ?>">
			<?php foreach ( $wcb_type_opts as $wcb_opt ) : ?>
			<button type="button" class="wcb-chip"
				data-type-slug="<?php echo esc_attr( $wcb_opt['slug'] ); ?>"
				data-wp-class--wcb-chip-active="state.isTypeActive"
				data-wp-on--click="actions.toggleTypeChip"
				data-wp-context="<?php echo esc_attr( wp_json_encode( array( 'typeSlug' => $wcb_opt['slug'] ) ) ); ?>"
			><?php echo esc_html( $wcb_opt['name'] ); ?></button>
			<?php endforeach; ?>

			<span class="wcb-chip-divider" aria-hidden="true"></span>

			<button type="button" class="wcb-chip"
				data-wp-class--wcb-chip-active="state.isRemoteActive"
				data-wp-on--click="actions.toggleRemote"
			><?php esc_html_e( 'Remote', 'wp-career-board' ); ?></button>

			<span class="wcb-chip-divider" aria-hidden="true"></span>

			<?php foreach ( $wcb_exp_opts as $wcb_opt ) : ?>
			<button type="button" class="wcb-chip"
				data-exp-slug="<?php echo esc_attr( $wcb_opt['slug'] ); ?>"
				data-wp-class--wcb-chip-active="state.isExpActive"
				data-wp-on--click="actions.toggleExpChip"
				data-wp-context="<?php echo esc_attr( wp_json_encode( array( 'expSlug' => $wcb_opt['slug'] ) ) ); ?>"
			><?php echo esc_html( $wcb_opt['name'] ); ?></button>
			<?php endforeach; ?>
		</div>

		<div class="wcb-active-filters" data-wp-class--wcb-shown="state.hasActiveFilters">
			<template data-wp-each--chip="state.activeFilterChips" data-wp-each-key="context.chip.key">
				<span class="wcb-active-chip">
					<span data-wp-text="context.chip.label"></span>
					<button type="button" class="wcb-active-chip-remove"
						aria-label="<?php esc_attr_e( 'Remove filter', 'wp-career-board' ); ?>"
						data-wp-on--click="actions.removeFilter"
					>&times;</button>
				</span>
			</template>
			<button type="button" class="wcb-clear-all" data-wp-on--click="actions.clearFilters"><?php esc_html_e( 'Clear all', 'wp-career-board' ); ?></button>
		</div>

		<div class="wcb-listings-toolbar">
			<p class="wcb-results-count" aria-live="polite" data-wp-text="state.resultsLabel"></p>
			<div class="wcb-view-switcher" role="group" aria-label="<?php esc_attr_e( 'View layout', 'wp-career-board' ); ?>">
				<button type="button" class="wcb-view-btn"
					data-wp-class--wcb-view-btn--active="state.isGrid"
					data-wp-on--click="actions.setGridLayout"
					aria-label="<?php esc_attr_e( 'Grid view', 'wp-career-board' ); ?>"
				>
					<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/></svg>
				</button>
				<button type="button" class="wcb-view-btn"
					data-wp-class--wcb-view-btn--active="state.isList"
					data-wp-on--click="actions.setListLayout"
					aria-label="<?php esc_attr_e( 'List view', 'wp-career-board' ); ?>"
				>
					<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="3" y="4" width="18" height="2" rx="1"/><rect x="3" y="11" width="18" height="2" rx="1"/><rect x="3" y="18" width="18" height="2" rx="1"/></svg>
				</button>
			</div>
		</div>
	</div>

	<div
		class="wcb-jobs-container"
		data-wp-class--wcb-grid="state.isGrid"
		data-wp-class--wcb-list="state.isList"
	>
		<template data-wp-each--job="state.jobs" data-wp-each-key="context.job.id">
			<article class="wcb-job-card" data-wp-class--wcb-featured="context.job.featured">

				<a class="wcb-card-block-link" data-wp-bind--href="context.job.permalink" tabindex="-1" aria-hidden="true"></a>

				<div class="wcb-card-avatar" aria-hidden="true" data-wp-text="context.job.initials"></div>

				<div class="wcb-card-body">

					<div class="wcb-card-header">
						<div class="wcb-card-title-wrap">
							<h3 class="wcb-card-title">
								<a data-wp-bind--href="context.job.permalink" data-wp-text="context.job.title"></a>
							</h3>
							<p class="wcb-card-company">
								<span data-wp-text="context.job.company"></span>
								<span class="wcb-verified-sm" data-wp-class--wcb-shown="context.job.verified">&#10003; <?php esc_html_e( 'Verified', 'wp-career-board' ); ?></span>
							</p>
						</div>
						<button
							type="button"
							class="wcb-bookmark-btn"
							data-wp-on--click="actions.toggleBookmark"
							data-wp-class--wcb-bookmarked="context.job.bookmarked"
							data-wp-bind--aria-label="state.bookmarkLabel"
							aria-label="<?php esc_attr_e( 'Bookmark job', 'wp-career-board' ); ?>"
						>
							<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17 3H7a2 2 0 0 0-2 2v16l7-3 7 3V5a2 2 0 0 0-2-2z"/></svg>
						</button>
					</div>

					<div class="wcb-card-badges">
						<span class="wcb-cbadge wcb-cbadge--featured" data-wp-class--wcb-shown="context.job.featured"><?php esc_html_e( 'Featured', 'wp-career-board' ); ?></span>
						<span class="wcb-cbadge wcb-cbadge--remote" data-wp-class--wcb-shown="context.job.remote"><?php esc_html_e( 'Remote', 'wp-career-board' ); ?></span>
						<span class="wcb-cbadge wcb-cbadge--type" data-wp-class--wcb-shown="context.job.type" data-wp-text="context.job.type"></span>
						<span class="wcb-cbadge wcb-cbadge--exp" data-wp-class--wcb-shown="context.job.experience" data-wp-text="context.job.experience"></span>
						<span class="wcb-cbadge wcb-cbadge--location" data-wp-class--wcb-shown="context.job.location">
							<svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
							<span data-wp-text="context.job.location"></span>
						</span>
					</div>

				<p class="wcb-card-excerpt"
					data-wp-class--wcb-shown="context.job.excerpt"
					data-wp-text="context.job.excerpt"
				></p>

				<div class="wcb-card-footer">
						<span class="wcb-card-salary" data-wp-class--wcb-shown="context.job.salary_label" data-wp-text="context.job.salary_label"></span>
						<span class="wcb-card-deadline" data-wp-class--wcb-shown="context.job.deadline" data-wp-text="context.job.deadline"></span>
						<span class="wcb-card-date" data-wp-text="context.job.days_ago"></span>
						<a class="wcb-cbtn wcb-cbtn--apply" data-wp-bind--href="context.job.permalink"><?php esc_html_e( 'View Job', 'wp-career-board' ); ?></a>
					</div>

				</div>
			</article>
		</template>
		<p class="wcb-no-results" data-wp-bind--hidden="!state.hasNoJobs"><?php esc_html_e( 'No jobs match your search. Try adjusting your filters.', 'wp-career-board' ); ?></p>
	</div>

	<div class="wcb-load-more-wrap" data-wp-class--wcb-shown="state.hasMore">
		<button
			type="button"
			class="wcb-load-more-btn"
			data-wp-on--click="actions.loadMore"
			data-wp-bind--disabled="state.loading"
		>
			<span data-wp-class--wcb-hidden="state.loading"><?php esc_html_e( 'Load more jobs', 'wp-career-board' ); ?></span>
			<span class="wcb-loading-label" data-wp-class--wcb-shown="state.loading"><?php esc_html_e( 'Loading&hellip;', 'wp-career-board' ); ?></span>
		</button>
	</div>
</div>
