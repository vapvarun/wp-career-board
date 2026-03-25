<?php
/**
 * Block render: wcb/company-archive — seeds Interactivity API state and renders
 * the interactive company directory with grid/list toggle and filters.
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

// ── Company size labels ──────────────────────────────────────────────────────
$wcb_size_labels = array(
	'1-10'      => __( '1–10 employees', 'wp-career-board' ),
	'11-50'     => __( '11–50 employees', 'wp-career-board' ),
	'51-200'    => __( '51–200 employees', 'wp-career-board' ),
	'201-500'   => __( '201–500 employees', 'wp-career-board' ),
	'501-1000'  => __( '501–1,000 employees', 'wp-career-board' ),
	'1001-5000' => __( '1,001–5,000 employees', 'wp-career-board' ),
	'5000+'     => __( '5,000+ employees', 'wp-career-board' ),
);

// ── Fetch first page of companies ────────────────────────────────────────────
$wcb_companies_raw = get_posts(
	array(
		'post_type'     => 'wcb_company',
		'post_status'   => 'publish',
		'numberposts'   => $wcb_per_page,
		'orderby'       => 'date',
		'order'         => 'DESC',
		'no_found_rows' => true,
	)
);

// ── Build company_id → job count map ─────────────────────────────────────────
$wcb_company_ids = $wcb_companies_raw
	? array_map(
		static function ( \WP_Post $p ) {
			return $p->ID;
		},
		$wcb_companies_raw
	)
	: array();

$wcb_jobs_raw = $wcb_company_ids
	? get_posts(
		array(
			'post_type'     => 'wcb_job',
			'post_status'   => 'publish',
			'numberposts'   => -1,
			'no_found_rows' => true,
			'orderby'       => 'none',
			'meta_query'    => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_wcb_company_id',
					'value'   => $wcb_company_ids,
					'compare' => 'IN',
				),
			),
		)
	)
	: array();

$wcb_jobs_by_company = array();
foreach ( $wcb_jobs_raw as $wcb_jpost ) {
	$wcb_cid                         = (int) get_post_meta( $wcb_jpost->ID, '_wcb_company_id', true );
	$wcb_jobs_by_company[ $wcb_cid ] = ( $wcb_jobs_by_company[ $wcb_cid ] ?? 0 ) + 1;
}

// ── Build initial state array ─────────────────────────────────────────────────
$wcb_companies_state = array();

foreach ( $wcb_companies_raw as $wcb_co ) {
	$wcb_co_id    = $wcb_co->ID;
	$wcb_co_name  = $wcb_co->post_title;
	$wcb_logo_url = (string) get_the_post_thumbnail_url( $wcb_co_id, 'thumbnail' );
	$wcb_trust    = (string) get_post_meta( $wcb_co_id, '_wcb_trust_level', true );
	$wcb_size     = (string) get_post_meta( $wcb_co_id, '_wcb_company_size', true );
	$wcb_job_cnt  = $wcb_jobs_by_company[ $wcb_co_id ] ?? 0;

	// Build up-to-2-letter initials.
	$wcb_words    = array_filter( explode( ' ', trim( $wcb_co_name ) ) );
	$wcb_initials = '';
	foreach ( array_slice( $wcb_words, 0, 2 ) as $wcb_w ) {
		$wcb_initials .= mb_strtoupper( mb_substr( $wcb_w, 0, 1 ) );
	}
	$wcb_initials = $wcb_initials ? $wcb_initials : '?';

	// Jobs count label.
	$wcb_jobs_label = ( 0 === $wcb_job_cnt )
		? __( 'No open positions', 'wp-career-board' )
		: sprintf(
			/* translators: %d: number of open positions */
			_n( '%d open position', '%d open positions', $wcb_job_cnt, 'wp-career-board' ),
			$wcb_job_cnt
		);

	$wcb_companies_state[] = array(
		'id'         => $wcb_co_id,
		'name'       => $wcb_co_name,
		'initials'   => $wcb_initials,
		'has_logo'   => '' !== $wcb_logo_url,
		'no_logo'    => '' === $wcb_logo_url,
		'logo'       => $wcb_logo_url,
		'tagline'    => (string) get_post_meta( $wcb_co_id, '_wcb_tagline', true ),
		'industry'   => (string) get_post_meta( $wcb_co_id, '_wcb_industry', true ),
		'size_label' => $wcb_size_labels[ $wcb_size ] ?? $wcb_size,
		'hq'         => (string) get_post_meta( $wcb_co_id, '_wcb_hq_location', true ),
		'verified'   => in_array( $wcb_trust, array( 'verified', 'trusted', 'premium' ), true ),
		'permalink'  => get_permalink( $wcb_co_id ),
		'jobs_label' => $wcb_jobs_label,
	);
}

// ── Get distinct industries for the filter dropdown ───────────────────────────
$wcb_all_co_ids = get_posts(
	array(
		'post_type'     => 'wcb_company',
		'post_status'   => 'publish',
		'numberposts'   => -1,
		'fields'        => 'ids',
		'no_found_rows' => true,
		'orderby'       => 'none',
	)
);

$wcb_filter_industries = array();
foreach ( $wcb_all_co_ids as $wcb_cid ) {
	$wcb_ind = (string) get_post_meta( $wcb_cid, '_wcb_industry', true );
	if ( $wcb_ind ) {
		$wcb_filter_industries[ $wcb_ind ] = true;
	}
}
$wcb_filter_industries = array_keys( $wcb_filter_industries );
sort( $wcb_filter_industries );

// ── Seed Interactivity API state ──────────────────────────────────────────────
$wcb_state = array(
	'companies' => $wcb_companies_state,
	'page'      => 1,
	'perPage'   => $wcb_per_page,
	'layout'    => $wcb_layout,
	'loading'   => false,
	'hasMore'   => count( $wcb_companies_raw ) >= $wcb_per_page,
	'apiBase'   => rest_url( 'wcb/v1/companies' ),
	'industry'  => '',
	'size'      => '',
);

$wcb_ca_settings     = (array) get_option( 'wcb_settings', array() );
$wcb_ca_archive_id   = (int) ( $wcb_ca_settings['company_archive_page'] ?? 0 );
$wcb_ca_page_heading = ( $wcb_ca_archive_id && (int) get_queried_object_id() === $wcb_ca_archive_id )
	? (string) get_the_title( $wcb_ca_archive_id )
	: '';

wp_interactivity_state( 'wcb-company-archive', $wcb_state );
?>
<div
	<?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-company-archive"
>

	<?php if ( $wcb_ca_page_heading ) : ?>
	<h1 class="wcb-page-heading"><?php echo esc_html( $wcb_ca_page_heading ); ?></h1>
	<?php endif; ?>

	<?php /* ── Toolbar: results count + filters + layout toggle ── */ ?>
	<div class="wcb-ca-toolbar">

		<p class="wcb-ca-results" data-wp-text="state.resultsLabel" aria-live="polite"></p>

		<div class="wcb-ca-filter-bar">

			<select
				class="wcb-ca-filter-select"
				aria-label="<?php esc_attr_e( 'Filter by industry', 'wp-career-board' ); ?>"
				data-wp-on--change="actions.filterIndustry"
			>
				<option value=""><?php esc_html_e( 'All Industries', 'wp-career-board' ); ?></option>
				<?php foreach ( $wcb_filter_industries as $wcb_ind_val ) : ?>
					<option value="<?php echo esc_attr( $wcb_ind_val ); ?>"><?php echo esc_html( $wcb_ind_val ); ?></option>
				<?php endforeach; ?>
			</select>

			<select
				class="wcb-ca-filter-select"
				aria-label="<?php esc_attr_e( 'Filter by company size', 'wp-career-board' ); ?>"
				data-wp-on--change="actions.filterSize"
			>
				<option value=""><?php esc_html_e( 'All Sizes', 'wp-career-board' ); ?></option>
				<?php foreach ( $wcb_size_labels as $wcb_size_key => $wcb_size_lbl ) : ?>
					<option value="<?php echo esc_attr( $wcb_size_key ); ?>"><?php echo esc_html( $wcb_size_lbl ); ?></option>
				<?php endforeach; ?>
			</select>

		</div>

		<div class="wcb-layout-toggle" role="group" aria-label="<?php esc_attr_e( 'View layout', 'wp-career-board' ); ?>">
			<button
				type="button"
				class="wcb-layout-btn"
				aria-label="<?php esc_attr_e( 'List view', 'wp-career-board' ); ?>"
				data-wp-on--click="actions.setList"
				data-wp-class--wcb-active="state.isList"
			>
				<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M2 4h12v1.5H2V4zm0 3.25h12v1.5H2V7.25zm0 3.25h12v1.5H2v-1.5z"/></svg>
			</button>
			<button
				type="button"
				class="wcb-layout-btn"
				aria-label="<?php esc_attr_e( 'Grid view', 'wp-career-board' ); ?>"
				data-wp-on--click="actions.setGrid"
				data-wp-class--wcb-active="state.isGrid"
			>
				<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M2 2h5v5H2V2zm7 0h5v5H9V2zm-7 7h5v5H2V9zm7 0h5v5H9V9z"/></svg>
			</button>
		</div>

	</div>

	<?php /* ── Company cards container ── */ ?>
	<div
		class="wcb-ca-container"
		data-wp-class--wcb-grid="state.isGrid"
		data-wp-class--wcb-list="state.isList"
	>
		<template data-wp-each--company="state.companies" data-wp-each-key="context.company.id">
			<article class="wcb-ca-card">
				<a class="wcb-ca-card-link" data-wp-bind--href="context.company.permalink" data-wp-bind--aria-label="context.company.name">

					<div class="wcb-ca-card-top">
						<div class="wcb-ca-avatar-wrap">
							<img
								class="wcb-ca-logo"
								alt=""
								data-wp-class--wcb-shown="context.company.has_logo"
								data-wp-bind--src="context.company.logo"
								data-wp-bind--alt="context.company.name"
							/>
							<div
								class="wcb-ca-avatar"
								data-wp-class--wcb-shown="context.company.no_logo"
								data-wp-text="context.company.initials"
								aria-hidden="true"
							></div>
						</div>

						<span
							class="wcb-ca-trust-badge wcb-trust--verified"
							data-wp-class--wcb-shown="context.company.verified"
						>&#10003; <?php esc_html_e( 'Verified', 'wp-career-board' ); ?></span>
					</div>

					<div class="wcb-ca-card-body">
						<h2 class="wcb-ca-name" data-wp-text="context.company.name"></h2>
						<p class="wcb-ca-tagline"
							data-wp-class--wcb-shown="context.company.tagline"
							data-wp-text="context.company.tagline"></p>
						<div class="wcb-ca-chips">
							<span class="wcb-ca-chip"
									data-wp-class--wcb-shown="context.company.industry"
									data-wp-text="context.company.industry"></span>
							<span class="wcb-ca-chip"
									data-wp-class--wcb-shown="context.company.size_label"
									data-wp-text="context.company.size_label"></span>
							<span class="wcb-ca-chip"
									data-wp-class--wcb-shown="context.company.hq"
									data-wp-text="context.company.hq"></span>
						</div>
					</div>

					<div class="wcb-ca-card-footer">
						<span class="wcb-ca-jobs-count" data-wp-text="context.company.jobs_label"></span>
						<span class="wcb-ca-cta"><?php esc_html_e( 'View Profile', 'wp-career-board' ); ?></span>
					</div>

				</a>
			</article>
		</template>
		<p class="wcb-no-results" data-wp-bind--hidden="!state.hasNoCompanies"><?php esc_html_e( 'No companies match your filters.', 'wp-career-board' ); ?></p>
	</div>

	<?php /* ── Load more ── */ ?>
	<div class="wcb-load-more-wrap" data-wp-class--wcb-shown="state.hasMore">
		<button
			type="button"
			class="wcb-load-more-btn"
			data-wp-on--click="actions.loadMore"
			data-wp-bind--disabled="state.loading"
		>
			<span data-wp-class--wcb-hidden="state.loading"><?php esc_html_e( 'Load more companies', 'wp-career-board' ); ?></span>
			<span class="wcb-loading-label" data-wp-class--wcb-shown="state.loading"><?php esc_html_e( 'Loading&hellip;', 'wp-career-board' ); ?></span>
		</button>
	</div>

</div>
