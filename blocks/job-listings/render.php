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

defined( 'ABSPATH' ) || exit;

$wcb_per_page   = (int) ( $attributes['perPage'] ?? 20 );
$wcb_raw_layout = (string) ( $attributes['layout'] ?? 'grid' );
$wcb_layout     = in_array( $wcb_raw_layout, array( 'grid', 'list' ), true ) ? $wcb_raw_layout : 'grid';

$wcb_jobs_raw = get_posts(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'publish',
		'numberposts' => $wcb_per_page,
	)
);

$wcb_current_user_id = get_current_user_id();
$wcb_bookmarks       = $wcb_current_user_id
	? (array) get_user_meta( $wcb_current_user_id, '_wcb_bookmark', false )
	: array();

$wcb_jobs_state = array();

foreach ( $wcb_jobs_raw as $wcb_job_post ) {
	$wcb_location_terms = wp_get_object_terms( $wcb_job_post->ID, 'wcb_location', array( 'fields' => 'names' ) );
	$wcb_type_terms     = wp_get_object_terms( $wcb_job_post->ID, 'wcb_job_type', array( 'fields' => 'names' ) );

	$wcb_jobs_state[] = array(
		'id'         => $wcb_job_post->ID,
		'title'      => $wcb_job_post->post_title,
		'permalink'  => get_permalink( $wcb_job_post->ID ),
		'company'    => get_post_meta( $wcb_job_post->ID, '_wcb_company_name', true ),
		'location'   => is_wp_error( $wcb_location_terms ) ? '' : implode( ', ', $wcb_location_terms ),
		'type'       => is_wp_error( $wcb_type_terms ) ? '' : implode( ', ', $wcb_type_terms ),
		'remote'     => '1' === get_post_meta( $wcb_job_post->ID, '_wcb_remote', true ),
		'salary_min' => get_post_meta( $wcb_job_post->ID, '_wcb_salary_min', true ),
		'salary_max' => get_post_meta( $wcb_job_post->ID, '_wcb_salary_max', true ),
		'bookmarked' => in_array( (string) $wcb_job_post->ID, $wcb_bookmarks, true ),
	);
}

$wcb_state = array(
	'jobs'    => $wcb_jobs_state,
	'page'    => 1,
	'layout'  => $wcb_layout,
	'loading' => false,
	'hasMore' => count( $wcb_jobs_raw ) >= $wcb_per_page,
	'apiBase' => rest_url( 'wcb/v1/jobs' ),
	'nonce'   => wp_create_nonce( 'wp_rest' ),
);

wp_interactivity_state( 'wcb-job-listings', $wcb_state );
?>
<div
	<?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-job-listings"
>
	<div class="wcb-listings-header">
		<button
			type="button"
			data-wp-on--click="actions.setGrid"
			data-wp-class--active="state.isGrid"
		><?php esc_html_e( 'Grid', 'wp-career-board' ); ?></button>
		<button
			type="button"
			data-wp-on--click="actions.setList"
			data-wp-class--active="state.isList"
		><?php esc_html_e( 'List', 'wp-career-board' ); ?></button>
	</div>

	<div
		class="wcb-jobs-container"
		data-wp-class--wcb-grid="state.isGrid"
		data-wp-class--wcb-list="state.isList"
	>
		<template data-wp-each--job="state.jobs" data-wp-each-key="context.job.id">
			<article class="wcb-job-card">
				<h3><a data-wp-bind--href="context.job.permalink" data-wp-text="context.job.title"></a></h3>
				<span class="wcb-job-company" data-wp-text="context.job.company"></span>
				<span class="wcb-job-meta" data-wp-text="context.job.location"></span>
				<span class="wcb-job-type" data-wp-text="context.job.type"></span>
				<button
					type="button"
					class="wcb-bookmark"
					data-wp-on--click="actions.toggleBookmark"
					data-wp-class--bookmarked="context.job.bookmarked"
					aria-label="<?php esc_attr_e( 'Bookmark job', 'wp-career-board' ); ?>"
				>☆</button>
			</article>
		</template>
	</div>

	<div data-wp-bind--hidden="!state.hasMore">
		<button
			type="button"
			data-wp-on--click="actions.loadMore"
			data-wp-bind--disabled="state.loading"
		>
			<span data-wp-bind--hidden="state.loading"><?php esc_html_e( 'Load more', 'wp-career-board' ); ?></span>
			<span data-wp-bind--hidden="!state.loading"><?php esc_html_e( 'Loading\u2026', 'wp-career-board' ); ?></span>
		</button>
	</div>
</div>
