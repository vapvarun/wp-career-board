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

// ── Company size buckets ─────────────────────────────────────────────────────
// Slug list only. The seven translated labels live in exactly one place —
// \WCB\Core\CompanyMetaShape::size_label() — which the /wcb/v1/companies REST
// shape also consumes. Re-declaring the gettext map here would duplicate those
// same msgids (that class's docblock forbids the duplication) and let the SSR
// chip drift from the label the client re-fetch paints. Resolve every slug
// through the shared serializer instead.
$wcb_size_keys = array( '1-10', '11-50', '51-200', '201-500', '501-1000', '1001-5000', '5000+' );

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

// Open-positions counter — one aggregate SQL keyed on the (meta_key, meta_value)
// postmeta index instead of materialising every wcb_job into PHP just to count
// it. At 100k jobs the previous numberposts=-1 path allocated 100k WP_Post
// objects per archive render; this is an index-only scan grouped in MySQL.
$wcb_jobs_by_company = array();
if ( $wcb_company_ids ) {
	global $wpdb;
	$wcb_co_ids   = array_map( 'intval', $wcb_company_ids );
	$placeholders = implode( ',', array_fill( 0, count( $wcb_co_ids ), '%d' ) );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wcb_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT pm.meta_value AS company_id, COUNT(*) AS c
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_wcb_company_id'
			   AND p.post_type = 'wcb_job'
			   AND p.post_status = 'publish'
			   AND pm.meta_value IN ({$placeholders})
			 GROUP BY pm.meta_value",
			...$wcb_co_ids
		)
	);
	// phpcs:enable
	foreach ( (array) $wcb_rows as $wcb_row ) {
		$wcb_jobs_by_company[ (int) $wcb_row->company_id ] = (int) $wcb_row->c;
	}
}

// ── Current user's bookmarked companies (for initial card state). ────────────
$wcb_current_user_id = get_current_user_id();
$wcb_bookmarks       = $wcb_current_user_id
	? array_map( 'intval', (array) get_user_meta( $wcb_current_user_id, '_wcb_company_bookmark', false ) )
	: array();

// ── Trust level badge map ───────────────────────────────────────────────────
$wcb_trust_badges = array(
	'verified' => array( 'label' => __( 'Verified', 'wp-career-board' ) ),
	'trusted'  => array( 'label' => __( 'Trusted', 'wp-career-board' ) ),
	'premium'  => array( 'label' => __( 'Premium', 'wp-career-board' ) ),
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
			/* translators: %s: number of open positions, already localised. */
			_n( '%s open position', '%s open positions', $wcb_job_cnt, 'wp-career-board' ),
			number_format_i18n( $wcb_job_cnt )
		);

	$wcb_companies_state[] = array(
		'id'             => $wcb_co_id,
		'name'           => $wcb_co_name,
		'initials'       => $wcb_initials,
		'has_logo'       => '' !== $wcb_logo_url,
		'no_logo'        => '' === $wcb_logo_url,
		'logo'           => $wcb_logo_url,
		'tagline'        => (string) get_post_meta( $wcb_co_id, '_wcb_tagline', true ),
		// Bind the localised label sibling, matching the /wcb/v1/companies REST
		// payload's `industry_label` field. view.js rebuilds state.companies
		// straight from those REST objects on every filter/search, so the SSR
		// seed key must line up with the REST key or the chip would flip from a
		// translated label to the raw slug after the first client fetch.
		'industry_label' => \WCB\Core\Industries::label( (string) get_post_meta( $wcb_co_id, '_wcb_industry', true ) ),
		'size_label'     => \WCB\Core\CompanyMetaShape::size_label( $wcb_size ),
		'hq'             => (string) get_post_meta( $wcb_co_id, '_wcb_hq_location', true ),
		'trust'          => $wcb_trust,
		'trust_label'    => $wcb_trust_info['label'] ?? '',
		'verified'       => null !== $wcb_trust_info,
		'permalink'      => get_permalink( $wcb_co_id ),
		'jobs_label'     => $wcb_jobs_label,
		'bookmarked'     => in_array( $wcb_co_id, $wcb_bookmarks, true ),
	);
}

// Distinct-industry lookup for the filter dropdown. One SELECT DISTINCT on
// the (meta_key) postmeta index, cached for an hour, busted via save_post
// hook elsewhere when an admin saves a company. Replaces the previous
// numberposts=-1 + per-row get_post_meta loop that was O(n) on company
// count even though the answer changes at most a few times per day.
$wcb_used_industries = wp_cache_get( 'wcb_distinct_industries', 'wcb_companies' );
if ( false === $wcb_used_industries ) {
	global $wpdb;
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wcb_industry_rows = $wpdb->get_col(
		"SELECT DISTINCT pm.meta_value
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = '_wcb_industry'
		   AND p.post_type = 'wcb_company'
		   AND p.post_status = 'publish'
		   AND pm.meta_value <> ''"
	);
	// phpcs:enable
	$wcb_used_industries = array();
	foreach ( (array) $wcb_industry_rows as $wcb_ind ) {
		$wcb_used_industries[ (string) $wcb_ind ] = true;
	}
	wp_cache_set( 'wcb_distinct_industries', $wcb_used_industries, 'wcb_companies', HOUR_IN_SECONDS );
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
	// Legacy free-text industry values aren't in the canonical registry and
	// carry no translation, so a __() home is impossible — but painting the
	// raw machine slug ("fin-tech") as a visible checkbox label is wrong too.
	// Humanise the stored slug for display; the raw slug still travels to REST
	// as the filter value via the data-wp-context payload below.
	$wcb_filter_industries[ $wcb_legacy ] = ucwords( str_replace( array( '-', '_' ), ' ', $wcb_legacy ) );
}

// ── Seed Interactivity API state ──────────────────────────────────────────────
$wcb_state = array(
	'companies'    => $wcb_companies_state,
	'page'         => 1,
	'perPage'      => $wcb_per_page,
	'layout'       => $wcb_layout,
	'loading'      => false,
	'hasMore'      => count( $wcb_companies_raw ) < $wcb_companies_total,
	'apiBase'      => untrailingslashit( rest_url( 'wcb/v1/companies' ) ),
	'industries'   => array(),
	'sizes'        => array(),
	'searchQuery'  => '',
	// Sort order pinned to the same option set as jobs + resumes
	// (date_desc | date_asc). View.js piping sets ?orderby=date&order=ASC|DESC
	// on the REST call so the server-side query matches the UI choice.
	'sortBy'       => 'date_desc',
	'restNonce'    => wp_create_nonce( 'wp_rest' ),
	/*
	 * Results-count label, fully resolved server-side.
	 *
	 * _n() picks the correct plural form for ANY locale (Polish needs different
	 * forms at 2 / 5 / 22, Russian at 1 / 2 / 5, Arabic has six). The previous
	 * pass seeded two frozen forms — _n(..., 1, ...) and _n(..., 2, ...) — and
	 * chose between them in JS with `count === 1`, which is only correct in
	 * two-form languages. It also counted state.companies.length (the rows
	 * loaded so far, which grows with pagination) instead of the matches found.
	 *
	 * The value is the FOUND total, not the number of rows painted. view.js
	 * replaces it with the REST response's additive `results_label` field —
	 * likewise _n()-resolved server-side — after every filter / search / sort /
	 * load-more round trip. No plural resolution happens in JS.
	 */
	'resultsLabel' => sprintf(
		/* translators: %s: number of companies found, already localised. */
		_n( '%s company found', '%s companies found', $wcb_companies_total, 'wp-career-board' ),
		number_format_i18n( $wcb_companies_total )
	),
	/*
	 * No `i18n` bag: view.js renders no strings of its own. Every user-facing
	 * string in this block is either painted by this template (already run
	 * through __()/esc_html_e()) or arrives pre-translated on the REST payload
	 * (`jobs_label`, `size_label`, `trust_label`, `results_label`). Seeding an
	 * empty bag plus a `t()` reader would be dead code. If a future change
	 * makes view.js render a literal, re-add `'i18n' => array( … )` here and a
	 * `t( key, fallback )` reader there — script modules cannot load JED
	 * translation files (wp_set_script_module_translations is WP 7.0+; this
	 * plugin's floor is 6.9), so state seeding is the only channel.
	 */
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
	$wcb_toolbar = array(
		'search_id'            => 'wcb-company-search',
		'search_sr_label'      => __( 'Search companies', 'wp-career-board' ),
		'search_placeholder'   => __( 'Search companies…', 'wp-career-board' ),
		'sort_aria_label'      => __( 'Sort companies', 'wp-career-board' ),
		'sort_options'         => array(
			'date_desc' => __( 'Newest first', 'wp-career-board' ),
			'date_asc'  => __( 'Oldest first', 'wp-career-board' ),
		),
		'switcher_aria_label'  => __( 'View layout', 'wp-career-board' ),
		'switcher_list_label'  => __( 'List view', 'wp-career-board' ),
		'switcher_grid_label'  => __( 'Grid view', 'wp-career-board' ),
		'switcher_list_action' => 'actions.setList',
		'switcher_grid_action' => 'actions.setGrid',
	);
	require WCB_DIR . 'templates/parts/archive-toolbar.php';
	?>

	<?php
	/*
	── 2-col layout: sidebar filter panel + result cards. Replaces the
			horizontal chip bar pattern that didn't scale once filter counts
			grew. Shared `.wcb-archive-layout` + `.wcb-filter-panel` styles
			live in `assets/css/wcb-ui.css` so Find Jobs and Find Candidates
			inherit the same shell. */
	?>
	<div class="wcb-archive-layout">

		<aside class="wcb-filter-panel" aria-label="<?php esc_attr_e( 'Filter companies', 'wp-career-board' ); ?>">
			<input type="checkbox" id="wcb-companies-filters-toggle" class="wcb-filter-panel__toggle-input" />
			<div class="wcb-filter-panel__header">
				<h2 class="wcb-filter-panel__heading"><?php esc_html_e( 'Filters', 'wp-career-board' ); ?></h2>
				<label for="wcb-companies-filters-toggle" class="wcb-filter-panel__toggle" aria-label="<?php esc_attr_e( 'Toggle filters', 'wp-career-board' ); ?>">
					<i data-lucide="chevron-down" aria-hidden="true"></i>
				</label>
				<button type="button" class="wcb-filter-panel__clear" data-wp-on--click="actions.clearFilters" data-wp-class--wcb-hidden="callbacks.noActiveFilters"><?php esc_html_e( 'Clear all', 'wp-career-board' ); ?></button>
			</div>

			<?php
			/*
			Industry + Company Size are multi-select - users can OR
					across multiple values, same as Find Jobs (type + experience
					+ board) and Find Candidates (skills + availability). The
					old single-select radio model meant filtering to "Tech OR
					Finance" was impossible. */
			?>
			<div class="wcb-filter-panel__group">
				<span class="wcb-filter-panel__group-title"><?php esc_html_e( 'Industry', 'wp-career-board' ); ?></span>
				<ul class="wcb-filter-panel__list">
					<?php foreach ( $wcb_filter_industries as $wcb_ind_val => $wcb_ind_lbl ) : ?>
						<li>
							<label class="wcb-filter-panel__option" data-wp-context="<?php echo esc_attr( (string) wp_json_encode( array( 'industrySlug' => $wcb_ind_val ) ) ); ?>">
								<input type="checkbox" data-wp-on--change="actions.toggleIndustry" data-wp-bind--checked="callbacks.isIndustryActive" />
								<span><?php echo esc_html( $wcb_ind_lbl ); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="wcb-filter-panel__group">
				<span class="wcb-filter-panel__group-title"><?php esc_html_e( 'Company size', 'wp-career-board' ); ?></span>
				<ul class="wcb-filter-panel__list">
					<?php foreach ( $wcb_size_keys as $wcb_size_key ) : ?>
						<li>
							<label class="wcb-filter-panel__option" data-wp-context="<?php echo esc_attr( (string) wp_json_encode( array( 'sizeSlug' => $wcb_size_key ) ) ); ?>">
								<input type="checkbox" data-wp-on--change="actions.toggleSize" data-wp-bind--checked="callbacks.isSizeActive" />
								<span><?php echo esc_html( \WCB\Core\CompanyMetaShape::size_label( $wcb_size_key ) ); ?></span>
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
				/*
				Bookmark button sits OUTSIDE the card-link anchor so clicks
						don't bubble into navigation. Absolute-positioned top-right via
						the shared `.wcb-bookmark-btn` rules in wcb-ui.css so Companies,
						Find Jobs, and Find Candidates share one save affordance. */
				?>
				<?php
				$wcb_bookmark = array(
					'aria_label'            => __( 'Save company', 'wp-career-board' ),
					'bookmarked_class_bind' => 'context.company.bookmarked',
				);
				require WCB_DIR . 'templates/parts/archive-card-bookmark.php';
				?>
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
					</div>

					<?php
					/*
					Trust mark is a small green checkmark inline AFTER the
							company name. The earlier "✓ Verified" pill rendered below
							the avatar in list view and broke layout - the word
							"Verified" was redundant given the icon. `aria-label` +
							`title` carry the human label for assistive tech. */
					?>
					<div class="wcb-ca-card-body">
						<div class="wcb-ca-name-row">
							<h2 class="wcb-ca-name" data-wp-text="context.company.name"></h2>
							<span
								class="wcb-ca-trust-tick"
								role="img"
								aria-label="<?php esc_attr_e( 'Verified', 'wp-career-board' ); ?>"
								data-wp-class--wcb-shown="context.company.verified"
								data-wp-bind--data-trust="context.company.trust"
								data-wp-bind--title="context.company.trust_label"
							><?php echo \WCB\Core\Icon::svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?></span>
						</div>
						<p class="wcb-ca-tagline"
							data-wp-class--wcb-shown="context.company.tagline"
							data-wp-text="context.company.tagline"></p>
					</div>
					<?php
					/*
					Chip row is a sibling of `.wcb-ca-card-body` so the grid template can
							span it full-width below the avatar/name column rather than indenting
							it under col 2 of the name row. Matches the "name+tagline only beside
							avatar, everything else flush left" layout the audit requested. */
					?>
					<div class="wcb-ca-card-chips">
						<span class="wcb-ca-chip"
								data-wp-class--wcb-shown="context.company.industry_label"
								data-wp-text="context.company.industry_label"></span>
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
		<?php
		/*
		Empty state mirrors the Find Jobs + Find Candidates card chrome
		(`.wcb-empty-state` paint declared in `assets/css/wcb-ui.css`)
		so all 3 archives degrade with the same affordance. The Clear
		all CTA wipes both filters + the search query. */
		?>
<?php
$wcb_empty = array(
	'wp_bind_hidden'    => '!state.hasNoCompanies',
	'title'             => __( 'No companies match your filters', 'wp-career-board' ),
	'body'              => __( 'Try removing a filter or clearing them all to see more results.', 'wp-career-board' ),
	'clear_action'      => 'actions.clearFilters',
	'clear_hidden_bind' => 'callbacks.noActiveFilters',
	'clear_label'       => __( 'Clear filters', 'wp-career-board' ),
);
require WCB_DIR . 'templates/parts/archive-empty-state.php';
?>
	</div>

		<?php
		$wcb_load_more = array( 'label' => __( 'Load more companies', 'wp-career-board' ) );
		require WCB_DIR . 'templates/parts/archive-load-more.php';
		?>

		</main>
	</div><!-- /.wcb-archive-layout -->

</div>
