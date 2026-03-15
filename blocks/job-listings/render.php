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

$wcb_per_page   = (int) ( $attributes['perPage'] ?? 20 );
$wcb_raw_layout = (string) ( $attributes['layout'] ?? 'grid' );
$wcb_layout     = in_array( $wcb_raw_layout, array( 'grid', 'list' ), true ) ? $wcb_raw_layout : 'grid';

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
$wcb_format_salary = static function ( string $wcb_min, string $wcb_max, string $wcb_currency = 'USD' ): string {
	if ( ! $wcb_min && ! $wcb_max ) {
		return '';
	}
	$wcb_symbol = 'USD' === $wcb_currency ? '$' : $wcb_currency . ' ';
	$wcb_fmt    = static function ( string $n ) use ( $wcb_symbol ): string {
		$wcb_n = (int) $n;
		if ( $wcb_n >= 1000 ) {
			return $wcb_symbol . round( $wcb_n / 1000 ) . 'k';
		}
		return $wcb_symbol . $wcb_n;
	};
	if ( $wcb_min && $wcb_max ) {
		return $wcb_fmt( $wcb_min ) . '–' . $wcb_fmt( $wcb_max ) . '/yr';
	}
	if ( $wcb_min ) {
		return $wcb_fmt( $wcb_min ) . '+/yr';
	}
	return 'Up to ' . $wcb_fmt( $wcb_max ) . '/yr';
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
		'salary_label' => $wcb_format_salary( $wcb_salary_min, $wcb_salary_max, $wcb_salary_currency ? $wcb_salary_currency : 'USD' ),
		'days_ago'     => human_time_diff( (int) strtotime( $wcb_job_post->post_date ), time() ) . ' ago',
		'bookmarked'   => in_array( $wcb_job_post->ID, $wcb_bookmarks, true ),
	);
}

$wcb_state = array(
	'jobs'    => $wcb_jobs_state,
	'page'    => 1,
	'perPage' => $wcb_per_page,
	'layout'  => $wcb_layout,
	'loading' => false,
	'hasMore' => count( $wcb_jobs_raw ) >= $wcb_per_page,
	'apiBase' => (string) apply_filters( 'wcb_job_listings_api_base', rest_url( 'wcb/v1/jobs' ) ),
	'nonce'   => wp_create_nonce( 'wp_rest' ),
);

wp_interactivity_state( 'wcb-job-listings', $wcb_state );
?>
<div
	<?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-job-listings"
>
	<div class="wcb-listings-toolbar">
		<p class="wcb-results-count" data-wp-text="state.resultsLabel" aria-live="polite"></p>
		<div class="wcb-layout-toggle" role="group" aria-label="<?php esc_attr_e( 'View layout', 'wp-career-board' ); ?>">
			<button
				type="button"
				class="wcb-layout-btn"
				title="<?php esc_attr_e( 'List view', 'wp-career-board' ); ?>"
				data-wp-on--click="actions.setList"
				data-wp-class--wcb-active="state.isList"
			>
				<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M2 4h12v1.5H2V4zm0 3.25h12v1.5H2V7.25zm0 3.25h12v1.5H2v-1.5z"/></svg>
			</button>
			<button
				type="button"
				class="wcb-layout-btn"
				title="<?php esc_attr_e( 'Grid view', 'wp-career-board' ); ?>"
				data-wp-on--click="actions.setGrid"
				data-wp-class--wcb-active="state.isGrid"
			>
				<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M2 2h5v5H2V2zm7 0h5v5H9V2zm-7 7h5v5H2V9zm7 0h5v5H9V9z"/></svg>
			</button>
		</div>
	</div>

	<div
		class="wcb-jobs-container"
		data-wp-class--wcb-grid="state.isGrid"
		data-wp-class--wcb-list="state.isList"
	>
		<template data-wp-each--job="state.jobs" data-wp-each-key="context.job.id">
			<article class="wcb-job-card" data-wp-class--wcb-featured="context.job.featured">

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

					<div class="wcb-card-footer">
						<span class="wcb-card-salary" data-wp-class--wcb-shown="context.job.salary_label" data-wp-text="context.job.salary_label"></span>
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
