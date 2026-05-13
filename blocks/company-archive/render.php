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
	'1-10'      => __( '1-10 employees', 'wp-career-board' ),
	'11-50'     => __( '11-50 employees', 'wp-career-board' ),
	'51-200'    => __( '51-200 employees', 'wp-career-board' ),
	'201-500'   => __( '201-500 employees', 'wp-career-board' ),
	'501-1000'  => __( '501-1,000 employees', 'wp-career-board' ),
	'1001-5000' => __( '1,001-5,000 employees', 'wp-career-board' ),
	'5000+'     => __( '5,000+ employees', 'wp-career-board' ),
);

// ── Fetch first page of companies ────────────────────────────────────────────
// WP_Query (not get_posts) so found_posts is available for the hasMore seed.
// Comparing the page count against per_page would mis-flag exact-boundary
// pages (e.g. exactly 12 companies → infinitely-fetching empty pages).
$wcb_companies_query = new \WP_Query(
	array(
		'post_type'      => 'wcb_company',
		'post_status'    => 'publish',
		'posts_per_page' => $wcb_per_page,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);
$wcb_companies_raw   = $wcb_companies_query->posts;
$wcb_companies_total = (int) $wcb_companies_query->found_posts;

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

// ── Current user's bookmarked companies (for initial card state). ────────────
$wcb_current_user_id = get_current_user_id();
$wcb_bookmarks       = $wcb_current_user_id
	? array_map( 'intval', (array) get_user_meta( $wcb_current_user_id, '_wcb_company_bookmark', false ) )
	: array();

// ── Trust level badge map ───────────────────────────────────────────────────
$wcb_trust_badges = array(
	'verified' => array(
		'label' => __( 'Verified', 'wp-career-board' ),
		'icon'  => '✓',
	),
	'trusted'  => array(
		'label' => __( 'Trusted', 'wp-career-board' ),
		'icon'  => '✓',
	),
	'premium'  => array(
		'label' => __( 'Premium', 'wp-career-board' ),
		'icon'  => '★',
	),
);

// ── Build initial state array ─────────────────────────────────────────────────
$wcb_companies_state = array();

foreach ( $wcb_companies_raw as $wcb_co ) {
	$wcb_co_id      = $wcb_co->ID;
	$wcb_co_name    = $wcb_co->post_title;
	$wcb_logo_url   = (string) get_the_post_thumbnail_url( $wcb_co_id, 'thumbnail' );
	$wcb_trust      = sanitize_key( (string) get_post_meta( $wcb_co_id, '_wcb_trust_level', true ) );
	$wcb_size       = (string) get_post_meta( $wcb_co_id, '_wcb_company_size', true );
	$wcb_job_cnt    = $wcb_jobs_by_company[ $wcb_co_id ] ?? 0;
	$wcb_trust_info = $wcb_trust_badges[ $wcb_trust ] ?? null;

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
		'id'          => $wcb_co_id,
		'name'        => $wcb_co_name,
		'initials'    => $wcb_initials,
		'has_logo'    => '' !== $wcb_logo_url,
		'no_logo'     => '' === $wcb_logo_url,
		'logo'        => $wcb_logo_url,
		'tagline'     => (string) get_post_meta( $wcb_co_id, '_wcb_tagline', true ),
		'industry'    => \WCB\Core\Industries::label( (string) get_post_meta( $wcb_co_id, '_wcb_industry', true ) ),
		'size_label'  => $wcb_size_labels[ $wcb_size ] ?? $wcb_size,
		'hq'          => (string) get_post_meta( $wcb_co_id, '_wcb_hq_location', true ),
		'trust'       => $wcb_trust,
		'trust_label' => $wcb_trust_info['label'] ?? '',
		'trust_icon'  => $wcb_trust_info['icon'] ?? '',
		'verified'    => null !== $wcb_trust_info,
		'permalink'   => get_permalink( $wcb_co_id ),
		'jobs_label'  => $wcb_jobs_label,
		'bookmarked'  => in_array( $wcb_co_id, $wcb_bookmarks, true ),
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

// Build the industry filter from the canonical slug list, restricted to
// slugs that at least one published company actually uses. Falls back to
// any legacy free-text values still stored from before the slug enforcement
// landed, so existing data stays selectable until a migration runs.
$wcb_used_industries = array();
foreach ( $wcb_all_co_ids as $wcb_cid ) {
	$wcb_ind = (string) get_post_meta( $wcb_cid, '_wcb_industry', true );
	if ( '' !== $wcb_ind ) {
		$wcb_used_industries[ $wcb_ind ] = true;
	}
}
$wcb_industry_labels = \WCB\Core\Industries::all();
unset( $wcb_industry_labels[''] );
$wcb_filter_industries = array();
foreach ( $wcb_industry_labels as $wcb_slug => $wcb_label ) {
	if ( isset( $wcb_used_industries[ $wcb_slug ] ) ) {
		$wcb_filter_industries[ $wcb_slug ] = $wcb_label;
		unset( $wcb_used_industries[ $wcb_slug ] );
	}
}
foreach ( array_keys( $wcb_used_industries ) as $wcb_legacy ) {
	$wcb_filter_industries[ $wcb_legacy ] = $wcb_legacy;
}

// ── Seed Interactivity API state ──────────────────────────────────────────────
$wcb_state = array(
	'companies'   => $wcb_companies_state,
	'page'        => 1,
	'perPage'     => $wcb_per_page,
	'layout'      => $wcb_layout,
	'loading'     => false,
	'hasMore'     => count( $wcb_companies_raw ) < $wcb_companies_total,
	'apiBase'     => untrailingslashit( rest_url( 'wcb/v1/companies' ) ),
	'industry'    => '',
	'size'        => '',
	'searchQuery' => '',
	'restNonce'   => wp_create_nonce( 'wp_rest' ),
);

$wcb_ca_page_heading = \WCB\Core\ArchiveHeading::resolve( 'wcb_company', 'company_archive_page' );

wp_interactivity_state( 'wcb-company-archive', $wcb_state );
?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-company-archive' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-company-archive"
>

	<?php if ( $wcb_ca_page_heading ) : ?>
	<h1 class="wcb-page-heading"><?php echo esc_html( $wcb_ca_page_heading ); ?></h1>
	<?php endif; ?>

	<?php
	/* ── Search row mirrors job-listings and resume-archive so all three
			archives share one shape: a full-width search input above the
			toolbar, with the filter sidebar starting below. Wired to
			state.searchQuery + actions.updateSearch in view.js. */
	?>
	<div class="wcb-ca-search-row">
		<div class="wcb-search-wrap">
			<span class="wcb-search-icon" aria-hidden="true" data-wp-ignore>
				<?php echo \WCB\Core\Icon::svg( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?>
			</span>
			<label class="screen-reader-text" for="wcb-company-search"><?php esc_html_e( 'Search companies', 'wp-career-board' ); ?></label>
			<input
				type="search"
				id="wcb-company-search"
				class="wcb-listings-search"
				placeholder="<?php esc_attr_e( 'Search companies…', 'wp-career-board' ); ?>"
				data-wp-bind--value="state.searchQuery"
				data-wp-on--input="actions.updateSearch"
			/>
		</div>
	</div>

	<?php
	/* ── Toolbar (results count + view toggle) sits ABOVE the 2-col grid
			so the filter panel on the left and the card column on the right
			both start at the same Y position. */
	?>
	<div class="wcb-ca-toolbar">
		<p class="wcb-ca-results" data-wp-text="state.resultsLabel" aria-live="polite"></p>

		<div class="wcb-layout-toggle" role="group" aria-label="<?php esc_attr_e( 'View layout', 'wp-career-board' ); ?>">
			<button
				type="button"
				class="wcb-layout-btn"
				aria-label="<?php esc_attr_e( 'List view', 'wp-career-board' ); ?>"
				data-wp-on--click="actions.setList"
				data-wp-class--wcb-active="state.isList"
			>
				<?php echo \WCB\Core\Icon::svg( 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?>
			</button>
			<button
				type="button"
				class="wcb-layout-btn"
				aria-label="<?php esc_attr_e( 'Grid view', 'wp-career-board' ); ?>"
				data-wp-on--click="actions.setGrid"
				data-wp-class--wcb-active="state.isGrid"
			>
				<?php echo \WCB\Core\Icon::svg( 'layout-grid' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?>
			</button>
		</div>
	</div>

	<?php
	/* ── 2-col layout: sidebar filter panel + result cards. Replaces the
			horizontal chip bar pattern that didn't scale once filter counts
			grew. Shared `.wcb-archive-layout` + `.wcb-filter-panel` styles
			live in `assets/css/wcb-ui.css` so Find Jobs and Find Candidates
			inherit the same shell. */
	?>
	<div class="wcb-archive-layout">

		<aside class="wcb-filter-panel" aria-label="<?php esc_attr_e( 'Filter companies', 'wp-career-board' ); ?>">
			<div class="wcb-filter-panel__header">
				<h2 class="wcb-filter-panel__heading"><?php esc_html_e( 'Filters', 'wp-career-board' ); ?></h2>
				<button type="button" class="wcb-filter-panel__clear" data-wp-on--click="actions.clearFilters" data-wp-class--wcb-hidden="callbacks.noActiveFilters"><?php esc_html_e( 'Clear all', 'wp-career-board' ); ?></button>
			</div>

			<div class="wcb-filter-panel__group">
				<span class="wcb-filter-panel__group-title"><?php esc_html_e( 'Industry', 'wp-career-board' ); ?></span>
				<ul class="wcb-filter-panel__list">
					<li>
						<label class="wcb-filter-panel__option">
							<input type="radio" name="wcb-ca-industry" value="" data-wp-on--change="actions.filterIndustry" data-wp-bind--checked="callbacks.isIndustryActive" />
							<span><?php esc_html_e( 'All industries', 'wp-career-board' ); ?></span>
						</label>
					</li>
					<?php foreach ( $wcb_filter_industries as $wcb_ind_val => $wcb_ind_lbl ) : ?>
						<li>
							<label class="wcb-filter-panel__option">
								<input type="radio" name="wcb-ca-industry" value="<?php echo esc_attr( $wcb_ind_val ); ?>" data-wp-on--change="actions.filterIndustry" data-wp-bind--checked="callbacks.isIndustryActive" />
								<span><?php echo esc_html( $wcb_ind_lbl ); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="wcb-filter-panel__group">
				<span class="wcb-filter-panel__group-title"><?php esc_html_e( 'Company size', 'wp-career-board' ); ?></span>
				<ul class="wcb-filter-panel__list">
					<li>
						<label class="wcb-filter-panel__option">
							<input type="radio" name="wcb-ca-size" value="" data-wp-on--change="actions.filterSize" data-wp-bind--checked="callbacks.isSizeActive" />
							<span><?php esc_html_e( 'All sizes', 'wp-career-board' ); ?></span>
						</label>
					</li>
					<?php foreach ( $wcb_size_labels as $wcb_size_key => $wcb_size_lbl ) : ?>
						<li>
							<label class="wcb-filter-panel__option">
								<input type="radio" name="wcb-ca-size" value="<?php echo esc_attr( $wcb_size_key ); ?>" data-wp-on--change="actions.filterSize" data-wp-bind--checked="callbacks.isSizeActive" />
								<span><?php echo esc_html( $wcb_size_lbl ); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</aside>

		<main class="wcb-archive-results">

		<?php /* ── Company cards container ── */ ?>
		<div
			class="wcb-ca-container"
			data-wp-class--wcb-grid="state.isGrid"
			data-wp-class--wcb-list="state.isList"
		>
		<template data-wp-each--company="state.companies" data-wp-each-key="context.company.id">
			<article class="wcb-ca-card">
				<?php
				/* Bookmark button sits OUTSIDE the card-link anchor so clicks
						don't bubble into navigation. Absolute-positioned top-right via
						the shared `.wcb-bookmark-btn` rules in wcb-ui.css so Companies,
						Find Jobs, and Find Candidates share one save affordance. */
				?>
				<button
					type="button"
					class="wcb-bookmark-btn"
					data-wp-on--click="actions.toggleBookmark"
					data-wp-class--wcb-bookmarked="context.company.bookmarked"
					aria-label="<?php esc_attr_e( 'Save company', 'wp-career-board' ); ?>"
				>
					<?php echo \WCB\Core\Icon::svg( 'bookmark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?>
				</button>
				<a class="wcb-ca-card-link" data-wp-bind--href="context.company.permalink" data-wp-bind--aria-label="context.company.name">

					<div class="wcb-ca-card-top">
						<div class="wcb-ca-avatar-wrap">
							<img class="wcb-ca-logo" alt="" data-wp-class--wcb-shown="context.company.has_logo" data-wp-bind--src="context.company.logo" data-wp-bind--alt="context.company.name" />
							<div
								class="wcb-ca-avatar"
								data-wp-class--wcb-shown="context.company.no_logo"
								data-wp-text="context.company.initials"
								aria-hidden="true"
							></div>
						</div>

							<span
								class="wcb-ca-trust-badge"
								role="status"
								data-wp-class--wcb-shown="context.company.verified"
								data-wp-bind--data-trust="context.company.trust"
							>
								<span aria-hidden="true" data-wp-text="context.company.trust_icon"></span>
								<span data-wp-text="context.company.trust_label"></span>
							</span>
						</div>

					<div class="wcb-ca-card-body">
						<h2 class="wcb-ca-name" data-wp-text="context.company.name"></h2>
						<p class="wcb-ca-tagline"
							data-wp-class--wcb-shown="context.company.tagline"
							data-wp-text="context.company.tagline"></p>
					</div>
					<?php
					/* Chip row is a sibling of `.wcb-ca-card-body` so the grid template can
							span it full-width below the avatar/name column rather than indenting
							it under col 2 of the name row. Matches the "name+tagline only beside
							avatar, everything else flush left" layout the audit requested. */
					?>
					<div class="wcb-ca-card-chips">
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

					<div class="wcb-ca-card-footer">
						<span class="wcb-ca-jobs-count" data-wp-text="context.company.jobs_label"></span>
						<span class="wcb-cbtn wcb-cbtn--ghost wcb-cbtn--sm"><?php esc_html_e( 'View Profile', 'wp-career-board' ); ?></span>
					</div>

				</a>
			</article>
		</template>
		<p class="wcb-no-results wcb-notice-error" data-wp-bind--hidden="!state.hasNoCompanies"><?php esc_html_e( 'No companies match your filters.', 'wp-career-board' ); ?></p>
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

		</main>
	</div><!-- /.wcb-archive-layout -->

</div>
