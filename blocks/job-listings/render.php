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

$wcb_setting_per_page = \WCB\Admin\Settings::int( 'jobs_per_page', 10 );
$wcb_per_page         = ! empty( $attributes['perPage'] ) ? (int) $attributes['perPage'] : $wcb_setting_per_page;
$wcb_raw_layout       = (string) ( $attributes['layout'] ?? 'grid' );
$wcb_layout           = in_array( $wcb_raw_layout, array( 'grid', 'list' ), true ) ? $wcb_raw_layout : 'grid';
$wcb_raw_columns      = (int) ( $attributes['columns'] ?? 3 );
$wcb_columns          = in_array( $wcb_raw_columns, array( 3, 4 ), true ) ? $wcb_raw_columns : 3;
$wcb_show_filters     = ( $attributes['showFilters'] ?? true ) ? true : false;

$wcb_author_id_attr = (int) ( $attributes['authorId'] ?? 0 );
$wcb_saved_by_attr  = (int) ( $attributes['savedBy'] ?? 0 );
$wcb_board_id_attr  = (int) ( $attributes['boardId'] ?? 0 );

// Parse metaFilter "key:value" attribute. Format kept as a single string so the
// shortcode wrapper can pass it without quoting nightmares; meta_key is
// validated against the allowlist in the REST endpoint at runtime.
$wcb_meta_filter_attr = (string) ( $attributes['metaFilter'] ?? '' );
$wcb_meta_filter_key  = '';
$wcb_meta_filter_val  = '';
if ( '' !== $wcb_meta_filter_attr && false !== strpos( $wcb_meta_filter_attr, ':' ) ) {
	[ $wcb_meta_filter_key, $wcb_meta_filter_val ] = array_map( 'trim', explode( ':', $wcb_meta_filter_attr, 2 ) );
}

$wcb_query_args = array(
	'post_type'   => 'wcb_job',
	'post_status' => 'publish',
	'numberposts' => $wcb_per_page,
	'orderby'     => 'date',
	'order'       => 'DESC',
);
if ( $wcb_author_id_attr > 0 ) {
	$wcb_query_args['author'] = $wcb_author_id_attr;
} elseif ( $wcb_saved_by_attr > 0 ) {
	$wcb_bookmark_ids = array_map( 'intval', (array) get_user_meta( $wcb_saved_by_attr, '_wcb_bookmark', false ) );
	// Cap the bookmark IN-clause + numberposts so a power user with 1k+
	// saved jobs can't blow the request memory budget on first paint. The
	// view.js layer paginates beyond this via the REST endpoint, which
	// streams the rest in pages of `perPage`.
	$wcb_bookmark_ids              = array_slice( $wcb_bookmark_ids, 0, max( $wcb_per_page, 200 ) );
	$wcb_query_args['post__in']    = ! empty( $wcb_bookmark_ids ) ? $wcb_bookmark_ids : array( 0 );
	$wcb_query_args['numberposts'] = $wcb_per_page;
}

// Apply boardId + metaFilter to first-paint server query so the initial
// render is already scoped (avoids a flash of unfiltered jobs before JS
// re-fetches with the same filters).
if ( $wcb_board_id_attr > 0 ) {
	$wcb_query_args['meta_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'key'   => '_wcb_board_id',
		'value' => $wcb_board_id_attr,
	);
}
if ( '' !== $wcb_meta_filter_key && '' !== $wcb_meta_filter_val ) {
	/**
	 * Allowlist of meta keys that metaFilter may query.
	 *
	 * Any `_wcb_*` prefixed key is allowed by default — the plugin owns
	 * that namespace, so admins can drop the block in the editor and use
	 * any of our job meta as a filter without writing PHP. Custom or
	 * non-WCB meta still requires opting in via this filter to keep
	 * arbitrary-meta probes blocked. See docs/HOOKS.md.
	 *
	 * @since 1.0.0
	 * @param array<int,string> $keys Allowlisted meta keys.
	 */
	$wcb_meta_filter_allowed = (array) apply_filters( 'wcb_jobs_allowed_meta_filters', array() );
	$wcb_is_wcb_meta_key     = str_starts_with( $wcb_meta_filter_key, '_wcb_' );
	if ( $wcb_is_wcb_meta_key || in_array( $wcb_meta_filter_key, $wcb_meta_filter_allowed, true ) ) {
		$wcb_query_args['meta_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'key'   => $wcb_meta_filter_key,
			'value' => $wcb_meta_filter_val,
		);
	} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		_doing_it_wrong(
			'wcb_job_listings',
			sprintf(
				/* translators: %s: meta key the integrator tried to filter on. */
				esc_html__( 'metaFilter key "%s" is not in the WCB namespace (_wcb_*) and is not in the allowlist. Register it via add_filter( \'wcb_jobs_allowed_meta_filters\', ... ) to enable filtering on custom meta keys.', 'wp-career-board' ),
				esc_html( $wcb_meta_filter_key )
			),
			'1.1.0'
		);
	}
}

$wcb_jobs_raw = get_posts( apply_filters( 'wcb_job_listings_query_args', $wcb_query_args ) );

if ( $wcb_jobs_raw ) {
	$wcb_job_ids = wp_list_pluck( $wcb_jobs_raw, 'ID' );
	if ( function_exists( 'update_postmeta_cache' ) ) {
		update_postmeta_cache( $wcb_job_ids );
	}
	if ( function_exists( 'update_object_term_cache' ) ) {
		update_object_term_cache( $wcb_job_ids, 'wcb_job' );
	}
}

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
	$wcb_catalog = \WCB\Admin\AdminSettings::get_currency_catalog();
	$wcb_code    = strtoupper( $wcb_currency );
	$wcb_symbol  = isset( $wcb_catalog[ $wcb_code ]['symbol'] ) ? (string) $wcb_catalog[ $wcb_code ]['symbol'] : $wcb_code . ' ';
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
	/* translators: %s: formatted maximum salary with currency symbol and period suffix */
	return sprintf( __( 'Up to %s', 'wp-career-board' ), $wcb_fmt( $wcb_max ) . $wcb_suffix );
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

$wcb_trust_badges = array(
	'verified' => array( 'label' => __( 'Verified', 'wp-career-board' ) ),
	'trusted'  => array( 'label' => __( 'Trusted', 'wp-career-board' ) ),
	'premium'  => array( 'label' => __( 'Premium', 'wp-career-board' ) ),
);

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
	$wcb_trust            = $wcb_company_post_id ? sanitize_key( (string) get_post_meta( $wcb_company_post_id, '_wcb_trust_level', true ) ) : '';
	$wcb_trust_info       = $wcb_trust_badges[ $wcb_trust ] ?? null;

	$wcb_job_card = array(
		'id'           => $wcb_job_post->ID,
		'title'        => $wcb_job_post->post_title,
		'permalink'    => get_permalink( $wcb_job_post->ID ),
		'company'      => $wcb_company_name_val,
		'initials'     => $wcb_initials( $wcb_company_name_val ),
		'trust'        => $wcb_trust,
		'trust_label'  => $wcb_trust_info['label'] ?? '',
		'verified'     => null !== $wcb_trust_info,
		'location'     => is_wp_error( $wcb_location_terms ) ? '' : implode( ', ', $wcb_location_terms ),
		'type'         => is_wp_error( $wcb_type_terms ) ? '' : implode( ', ', $wcb_type_terms ),
		'experience'   => is_wp_error( $wcb_exp_terms ) ? '' : implode( ', ', $wcb_exp_terms ),
		'category'     => is_wp_error( $wcb_cat_terms ) ? '' : implode( ', ', $wcb_cat_terms ),
		'remote'       => '1' === get_post_meta( $wcb_job_post->ID, '_wcb_remote', true ),
		'featured'     => '1' === get_post_meta( $wcb_job_post->ID, '_wcb_featured', true ),
		'board_id'     => (int) get_post_meta( $wcb_job_post->ID, '_wcb_board_id', true ),
		'board_name'   => '',
		'salary_min'   => $wcb_salary_min,
		'salary_max'   => $wcb_salary_max,
		'salary_label' => $wcb_format_salary( $wcb_salary_min, $wcb_salary_max, $wcb_salary_currency ? $wcb_salary_currency : 'USD', $wcb_salary_type ),
		'deadline'     => $wcb_deadline_val ? date_i18n( get_option( 'date_format' ), (int) strtotime( $wcb_deadline_val ) ) : '',
		'days_ago'     => human_time_diff( (int) strtotime( $wcb_job_post->post_date ), time() ) . ' ago',
		'bookmarked'   => in_array( $wcb_job_post->ID, $wcb_bookmarks, true ),
		'excerpt'      => wp_trim_words( (string) preg_replace( '/[*_#`]+/', '', wp_strip_all_tags( $wcb_job_post->post_content ) ), 25, '…' ),
	);

	/**
	 * Filter job card data before it's passed to the Interactivity API state.
	 *
	 * Pro uses this to inject board_name and auto-feature premium board jobs.
	 *
	 * @since 1.0.0
	 * @param array<string,mixed> $data Job card data array.
	 * @param \WP_Post            $post Job post object.
	 */
	$wcb_jobs_state[] = (array) apply_filters( 'wcb_job_listing_data', $wcb_job_card, $wcb_job_post );
}

// Sort featured jobs first, then by date (newest).
usort(
	$wcb_jobs_state,
	static function ( array $a, array $b ): int {
		$fa = ( $a['featured'] ?? false ) ? 1 : 0;
		$fb = ( $b['featured'] ?? false ) ? 1 : 0;
		if ( $fa !== $fb ) {
			return $fb - $fa; // featured first.
		}
		return ( $b['id'] ?? 0 ) - ( $a['id'] ?? 0 ); // newest first.
	}
);

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

$wcb_term_opt = static function ( \WP_Term $t ): array {
	return array(
		'slug' => $t->slug,
		'name' => $t->name,
	);
};

$wcb_cat_terms_raw = get_terms(
	array(
		'taxonomy'   => 'wcb_category',
		'hide_empty' => false,
	)
);
$wcb_cat_opts      = array_map( $wcb_term_opt, is_array( $wcb_cat_terms_raw ) ? $wcb_cat_terms_raw : array() );

$wcb_tag_terms_raw = get_terms(
	array(
		'taxonomy'   => 'wcb_tag',
		'hide_empty' => false,
	)
);
$wcb_tag_opts      = array_map( $wcb_term_opt, is_array( $wcb_tag_terms_raw ) ? $wcb_tag_terms_raw : array() );

$wcb_board_opts = (array) apply_filters( 'wcb_job_listings_board_options', array() );

if ( $wcb_saved_by_attr > 0 ) {
	// Count only valid published wcb_job posts the user has bookmarked.
	// Counting raw usermeta rows over-reports because stale bookmark IDs
	// (deleted / unpublished / wrong post type) still live in the table
	// and would falsely keep the Load More button visible after the last
	// real bookmark renders. Mirrors the post__in scope used by the
	// SSR query so totals stay consistent with what gets painted.
	$wcb_all_bookmark_ids = array_map( 'intval', (array) get_user_meta( $wcb_saved_by_attr, '_wcb_bookmark', false ) );
	if ( empty( $wcb_all_bookmark_ids ) ) {
		$wcb_total_count = 0;
	} else {
		$wcb_count_query = new \WP_Query(
			array(
				'post_type'      => 'wcb_job',
				'post_status'    => 'publish',
				'post__in'       => $wcb_all_bookmark_ids,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			)
		);
		$wcb_total_count = (int) $wcb_count_query->found_posts;
	}
} else {
	// Mirror $wcb_query_args (author + board + metaFilter + Pro filters) so the
	// found_posts count matches the filtered listing instead of the site-wide
	// publish count. Site-wide count showed Load More on filtered shortcodes
	// (e.g. boardId=42 with 3 jobs on a site with 50 total) — clicking it
	// fetched a second page that REST correctly returned empty.
	$wcb_count_args                   = (array) apply_filters( 'wcb_job_listings_query_args', $wcb_query_args );
	$wcb_count_args['posts_per_page'] = 1;
	$wcb_count_args['fields']         = 'ids';
	$wcb_count_args['no_found_rows']  = false;
	unset( $wcb_count_args['numberposts'] );
	$wcb_count_query = new \WP_Query( $wcb_count_args );
	$wcb_total_count = (int) $wcb_count_query->found_posts;
}

$wcb_state = array(
	'jobs'           => $wcb_jobs_state,
	'page'           => 1,
	'perPage'        => $wcb_per_page,
	'layout'         => $wcb_layout,
	'loading'        => false,
	// Render Load More only when there are actually more rows beyond what we
	// just rendered. The previous heuristic (count >= per_page) showed the
	// button even when the first batch was the only batch (count == total).
	// Saved tab participates in Load More now that it paginates instead
	// of returning every bookmark in one shot.
	'hasMore'        => count( $wcb_jobs_raw ) < $wcb_total_count,
	'apiBase'        => untrailingslashit( (string) apply_filters( 'wcb_job_listings_api_base', rest_url( 'wcb/v1/jobs' ) ) ),
	'nonce'          => wp_create_nonce( 'wp_rest' ),
	'totalCount'     => $wcb_total_count,
	'searchQuery'    => '',
	// User-controlled filters (type chips, exp chips, remote, salary,
	// external filter block keys). Removable pills + "Clear all" only
	// touch this map - never the shortcode-baked scope.
	'activeFilters'  => (object) array(),
	// Immutable shortcode/block scope (boardId + metaFilter). Merged into
	// every REST fetch and into "is active" UI signals, but never
	// surfaced as a removable chip and never wiped by "Clear all". Keeps
	// the integrator's baked-in scope (e.g. [wcb_job_listings
	// metaFilter="department:engineering"]) intact across user
	// interactions and Load more.
	'baseFilters'    => (object) array_filter(
		array(
			'board_' . $wcb_board_id_attr  => $wcb_board_id_attr > 0 ? (string) $wcb_board_id_attr : '',
			'meta_' . $wcb_meta_filter_key => ( '' !== $wcb_meta_filter_key && '' !== $wcb_meta_filter_val ) ? $wcb_meta_filter_val : '',
		)
	),
	'sortBy'         => 'date_desc',
	'alertSaved'     => false,
	'alertSaving'    => false,
	'authorId'       => $wcb_author_id_attr,
	'savedBy'        => $wcb_saved_by_attr,
	'boardId'        => $wcb_board_id_attr,
	'metaFilter'     => $wcb_meta_filter_attr,
	'salaryMin'      => 0,
	'salaryMax'      => 0,
	// Symbol used for the salary-filter chip + slider tooltips. Salary
	// filtering is currency-agnostic (compares raw min/max numbers across
	// jobs of any currency), so we surface the SITE default currency's
	// symbol to label the slider — site owners on INR/EUR sites should
	// see ₹ or € on the filter, not the hardcoded $ the JS used to emit.
	'currencySymbol' => (
		static function (): string {
			$wcb_settings_default = \WCB\Admin\Settings::string( 'salary_currency', 'USD' );
			$wcb_catalog          = \WCB\Admin\AdminSettings::get_currency_catalog();
			return isset( $wcb_catalog[ $wcb_settings_default ]['symbol'] )
				? (string) $wcb_catalog[ $wcb_settings_default ]['symbol']
				: '$';
		}
	)(),
	'filterOptions'  => array(
		'types'       => $wcb_type_opts,
		'experiences' => $wcb_exp_opts,
		'categories'  => $wcb_cat_opts,
		'tags'        => $wcb_tag_opts,
		'boards'      => $wcb_board_opts,
	),
	'strings'        => array(
		'bookmarkRemove'    => __( 'Saved', 'wp-career-board' ),
		'bookmarkAdd'       => __( 'Save job', 'wp-career-board' ),
		'salaryChipDefault' => __( 'Salary', 'wp-career-board' ),
		'anyLabel'          => __( 'Any', 'wp-career-board' ),
		/* translators: %d: number of jobs */
		'jobCountSingle'    => __( '1 job', 'wp-career-board' ),
		/* translators: %d: number of jobs */
		'jobCountPlural'    => __( '%d jobs', 'wp-career-board' ),
		/* translators: 1: number of shown jobs, 2: total number of jobs */
		'jobCountOf'        => __( '%1$d of %2$d jobs', 'wp-career-board' ),
	),
);

$wcb_page_heading = \WCB\Core\ArchiveHeading::resolve( 'wcb_job', 'jobs_archive_page' );

wp_interactivity_state( 'wcb-job-listings', $wcb_state );
?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-job-listings' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-job-listings"
	data-wp-init="callbacks.init"
>
	<?php if ( $wcb_page_heading && ( $attributes['showHeading'] ?? false ) ) : ?>
	<h1 class="wcb-page-heading"><?php echo esc_html( $wcb_page_heading ); ?></h1>
	<?php endif; ?>
	<?php
	$wcb_jl_has_filter_ui = ( 0 === $wcb_author_id_attr && 0 === $wcb_saved_by_attr && $wcb_show_filters );
	?>
	<?php if ( $wcb_jl_has_filter_ui ) : ?>

		<?php
		$wcb_toolbar = array(
			'show_search'         => ! has_block( 'wp-career-board/job-search' ),
			'search_id'           => 'wcb-job-search',
			'search_sr_label'     => __( 'Search jobs', 'wp-career-board' ),
			'search_placeholder'  => __( 'Search jobs…', 'wp-career-board' ),
			'sort_aria_label'     => __( 'Sort jobs', 'wp-career-board' ),
			'sort_options'        => array(
				'date_desc' => __( 'Newest first', 'wp-career-board' ),
				'date_asc'  => __( 'Oldest first', 'wp-career-board' ),
			),
			'inject_slot_key'     => 'alerts_subscribe',
			'switcher_aria_label' => __( 'View layout', 'wp-career-board' ),
			'switcher_list_label' => __( 'List view', 'wp-career-board' ),
			'switcher_grid_label' => __( 'Grid view', 'wp-career-board' ),
		);
		require WCB_DIR . 'templates/parts/archive-toolbar.php';
		?>

		<?php
		/*
		── 2-col layout: filter sidebar + result cards. Mirrors
			/companies/ and /find-candidates/ so the three archives share one
			shape. The existing chip actions (toggleTypeChip, toggleExpChip,
			toggleRemote, toggleBoardChip) stay as-is — we just stack the
			chips vertically inside .wcb-filter-panel__group sections. */
		?>
	<div class="wcb-archive-layout">

		<aside class="wcb-filter-panel" aria-label="<?php esc_attr_e( 'Filter jobs', 'wp-career-board' ); ?>">
			<input type="checkbox" id="wcb-jobs-filters-toggle" class="wcb-filter-panel__toggle-input" />
			<div class="wcb-filter-panel__header">
				<h2 class="wcb-filter-panel__heading"><?php esc_html_e( 'Filters', 'wp-career-board' ); ?></h2>
				<label for="wcb-jobs-filters-toggle" class="wcb-filter-panel__toggle" aria-label="<?php esc_attr_e( 'Toggle filters', 'wp-career-board' ); ?>">
					<i data-lucide="chevron-down" aria-hidden="true"></i>
				</label>
				<button type="button" class="wcb-filter-panel__clear" data-wp-on--click="actions.clearFilters" data-wp-class--wcb-hidden="state.noActiveFilters"><?php esc_html_e( 'Clear all', 'wp-career-board' ); ?></button>
			</div>

			<?php do_action( 'wcb_job_listings_filters_top' ); ?>
			<?php if ( $wcb_type_opts ) : ?>
			<div class="wcb-filter-panel__group">
				<span class="wcb-filter-panel__group-title"><?php esc_html_e( 'Job type', 'wp-career-board' ); ?></span>
				<ul class="wcb-filter-panel__list">
					<?php foreach ( $wcb_type_opts as $wcb_opt ) : ?>
					<li>
						<label class="wcb-filter-panel__option" data-wp-context="<?php echo esc_attr( wp_json_encode( array( 'typeSlug' => $wcb_opt['slug'] ) ) ); ?>">
							<input type="checkbox" data-wp-on--change="actions.toggleTypeChip" data-wp-bind--checked="state.isTypeActive" />
							<span><?php echo esc_html( $wcb_opt['name'] ); ?></span>
						</label>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>

			<?php if ( $wcb_exp_opts ) : ?>
			<div class="wcb-filter-panel__group">
				<span class="wcb-filter-panel__group-title"><?php esc_html_e( 'Experience', 'wp-career-board' ); ?></span>
				<ul class="wcb-filter-panel__list">
					<?php foreach ( $wcb_exp_opts as $wcb_opt ) : ?>
					<li>
						<label class="wcb-filter-panel__option" data-wp-context="<?php echo esc_attr( wp_json_encode( array( 'expSlug' => $wcb_opt['slug'] ) ) ); ?>">
							<input type="checkbox" data-wp-on--change="actions.toggleExpChip" data-wp-bind--checked="state.isExpActive" />
							<span><?php echo esc_html( $wcb_opt['name'] ); ?></span>
						</label>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>

			<?php if ( $wcb_cat_opts ) : ?>
			<div class="wcb-filter-panel__group">
				<span class="wcb-filter-panel__group-title"><?php esc_html_e( 'Category', 'wp-career-board' ); ?></span>
				<ul class="wcb-filter-panel__list">
					<?php foreach ( $wcb_cat_opts as $wcb_opt ) : ?>
					<li>
						<label class="wcb-filter-panel__option" data-wp-context="<?php echo esc_attr( wp_json_encode( array( 'catSlug' => $wcb_opt['slug'] ) ) ); ?>">
							<input type="checkbox" data-wp-on--change="actions.toggleCatChip" data-wp-bind--checked="state.isCatActive" />
							<span><?php echo esc_html( $wcb_opt['name'] ); ?></span>
						</label>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>

			<?php if ( $wcb_tag_opts ) : ?>
			<div class="wcb-filter-panel__group">
				<span class="wcb-filter-panel__group-title"><?php esc_html_e( 'Tags', 'wp-career-board' ); ?></span>
				<ul class="wcb-filter-panel__list">
					<?php foreach ( $wcb_tag_opts as $wcb_opt ) : ?>
					<li>
						<label class="wcb-filter-panel__option" data-wp-context="<?php echo esc_attr( wp_json_encode( array( 'tagSlug' => $wcb_opt['slug'] ) ) ); ?>">
							<input type="checkbox" data-wp-on--change="actions.toggleTagChip" data-wp-bind--checked="state.isTagActive" />
							<span><?php echo esc_html( $wcb_opt['name'] ); ?></span>
						</label>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>

			<div class="wcb-filter-panel__group">
				<span class="wcb-filter-panel__group-title"><?php esc_html_e( 'Location', 'wp-career-board' ); ?></span>
				<ul class="wcb-filter-panel__list">
					<li>
						<label class="wcb-filter-panel__option">
							<input type="checkbox" data-wp-on--change="actions.toggleRemote" data-wp-bind--checked="state.isRemoteActive" />
							<span><?php esc_html_e( 'Remote only', 'wp-career-board' ); ?></span>
						</label>
					</li>
				</ul>
			</div>

			<?php if ( $wcb_board_opts ) : ?>
			<div class="wcb-filter-panel__group">
				<span class="wcb-filter-panel__group-title"><?php esc_html_e( 'Job board', 'wp-career-board' ); ?></span>
				<ul class="wcb-filter-panel__list">
					<?php foreach ( $wcb_board_opts as $wcb_opt ) : ?>
					<li>
						<label class="wcb-filter-panel__option" data-wp-context="
						<?php
						echo esc_attr(
							wp_json_encode(
								array(
									'boardId'   => $wcb_opt['id'],
									'boardName' => $wcb_opt['name'],
								)
							)
						);
						?>
																					">
							<input type="checkbox" data-wp-on--change="actions.toggleBoardChip" data-wp-bind--checked="state.isBoardActive" />
							<span><?php echo esc_html( $wcb_opt['name'] ); ?></span>
						</label>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>

			<div class="wcb-filter-panel__group">
				<span class="wcb-filter-panel__group-title"><?php esc_html_e( 'Salary', 'wp-career-board' ); ?></span>
				<div class="wcb-filter-panel__salary">
					<label class="wcb-salary-popover__label" for="wcb-salary-min-range">
						<?php esc_html_e( 'Minimum', 'wp-career-board' ); ?>
						<span class="wcb-salary-popover__value" data-wp-text="state.salaryMinDisplay"></span>
					</label>
					<input
						id="wcb-salary-min-range"
						type="range"
						class="wcb-salary-popover__slider"
						min="0"
						max="500000"
						step="5000"
						data-wp-bind--value="state.salaryMin"
						data-wp-on--change="actions.updateSalaryMin"
						data-wp-on--input="actions.previewSalaryMin"
					/>
					<label class="wcb-salary-popover__label" for="wcb-salary-max-range">
						<?php esc_html_e( 'Maximum', 'wp-career-board' ); ?>
						<span class="wcb-salary-popover__value" data-wp-text="state.salaryMaxDisplay"></span>
					</label>
					<input
						id="wcb-salary-max-range"
						type="range"
						class="wcb-salary-popover__slider"
						min="0"
						max="500000"
						step="5000"
						data-wp-bind--value="state.salaryMax"
						data-wp-on--change="actions.updateSalaryMax"
						data-wp-on--input="actions.previewSalaryMax"
					/>

					<?php do_action( 'wcb_job_listings_filters_bottom' ); ?>
					<button type="button" class="wcb-salary-popover__reset" data-wp-on--click="actions.resetSalary">
						<?php esc_html_e( 'Reset', 'wp-career-board' ); ?>
					</button>
				</div>
			</div>
		</aside>

		<main class="wcb-archive-results">

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
			</div>
	<?php endif; ?>

	<div
		class="wcb-jobs-container wcb-cols-<?php echo esc_attr( (string) $wcb_columns ); ?>"
		data-wp-class--wcb-grid="state.isGrid"
		data-wp-class--wcb-list="state.isList"
	>
		<?php
		// The card markup below is a client-side Interactivity prototype
		// (data-wp-each clones it per job in the browser), so there is no
		// per-job PHP scope here. The card-footer extension hooks still fire
		// once on the prototype to let add-ons inject static per-card markup;
		// pass null-safe defaults rather than leaking the last $wcb_job_post
		// from the state-building loop above.
		$wcb_job_card = array();
		$wcb_job_post = null;
		?>
		<template data-wp-each--job="state.jobs" data-wp-each-key="context.job.id">
			<article class="wcb-job-card" data-wp-class--wcb-featured="context.job.featured">

				<div class="wcb-card-avatar" aria-hidden="true" data-wp-text="context.job.initials"></div>

				<div class="wcb-card-body">

					<div class="wcb-card-header">
						<div class="wcb-card-title-wrap">
							<h3 class="wcb-card-title">
								<a class="wcb-card-title-link" role="link" tabindex="0" aria-label="<?php esc_attr_e( 'Job listing', 'wp-career-board' ); ?>" data-wp-bind--href="context.job.permalink" data-wp-bind--aria-label="context.job.title" data-wp-text="context.job.title"></a>
							</h3>
							<p class="wcb-card-company">
								<span data-wp-text="context.job.company"></span>
								<?php
								/*
								Inline green tick after the company name. Tooltip
										+ aria-label carry the trust level for assistive
										tech; the word "Verified" was dropped from the UI
										because the icon already communicates it. */
								?>
								<span
									class="wcb-ca-trust-tick"
									role="img"
									aria-label="<?php esc_attr_e( 'Verified', 'wp-career-board' ); ?>"
									data-wp-class--wcb-shown="context.job.verified"
									data-wp-bind--data-trust="context.job.trust"
									data-wp-bind--title="context.job.trust_label"
								><?php echo \WCB\Core\Icon::svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?></span>
							</p>
						</div>
						<?php
						$wcb_bookmark = array(
							'aria_label'            => __( 'Save job', 'wp-career-board' ),
							'aria_label_bind'       => 'state.bookmarkLabel',
							'bookmarked_class_bind' => 'context.job.bookmarked',
						);
						require WCB_DIR . 'templates/parts/archive-card-bookmark.php';
						?>
					</div>

					<div class="wcb-card-badges">
						<span class="wcb-cbadge wcb-cbadge--featured" role="status" data-wp-class--wcb-shown="context.job.featured"><?php esc_html_e( 'Featured', 'wp-career-board' ); ?></span>
					<span class="wcb-cbadge wcb-cbadge--board" role="status" data-wp-class--wcb-shown="context.job.board_name" data-wp-text="context.job.board_name"></span>
						<span class="wcb-cbadge wcb-cbadge--remote" role="status" data-wp-class--wcb-shown="context.job.remote"><?php esc_html_e( 'Remote', 'wp-career-board' ); ?></span>
						<span class="wcb-cbadge wcb-cbadge--type" role="status" data-wp-class--wcb-shown="context.job.type" data-wp-text="context.job.type"></span>
						<span class="wcb-cbadge wcb-cbadge--exp" role="status" data-wp-class--wcb-shown="context.job.experience" data-wp-text="context.job.experience"></span>
						<span class="wcb-cbadge wcb-cbadge--location" role="status" data-wp-class--wcb-shown="context.job.location">
							<?php echo \WCB\Core\Icon::svg( 'map-pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?>
							<span data-wp-text="context.job.location"></span>
						</span>
					</div>

				<p class="wcb-card-excerpt"
					data-wp-class--wcb-shown="context.job.excerpt"
					data-wp-text="context.job.excerpt"
				></p>

				<div class="wcb-card-footer">
					<?php do_action( 'wcb_before_card_footer', $wcb_job_card, $wcb_job_post ); ?>
						<span class="wcb-card-salary" data-wp-class--wcb-shown="context.job.salary_label" data-wp-text="context.job.salary_label"></span>
						<span class="wcb-card-deadline" data-wp-class--wcb-shown="context.job.deadline" data-wp-text="context.job.deadline"></span>
						<span class="wcb-card-date" data-wp-text="context.job.days_ago"></span>
						<a class="wcb-cbtn wcb-cbtn--ghost wcb-cbtn--sm" data-wp-bind--href="context.job.permalink"><?php esc_html_e( 'View Job', 'wp-career-board' ); ?></a>
					<?php do_action( 'wcb_after_card_footer', $wcb_job_card, $wcb_job_post ); ?>
					</div>

				</div>
			</article>
		</template>
		<?php
		/*
		Empty state renders as a self-contained card so it carries
				visible weight inside the wide results column instead of
				floating as a thin icon + line. Title (heading), body (hint),
				and a "Clear filters" CTA that only shows when filters are
				active - clicking it routes to `actions.clearFilters` which
				wipes activeFilters + re-fetches. Mirrors the same card
				chrome used by Companies + Find Candidates empty states. */
		?>
		<?php
		$wcb_empty = array(
			'wp_bind_hidden'    => '!state.hasNoJobs',
			'ssr_hidden'        => ! empty( $wcb_jobs_raw ),
			'title'             => __( 'No jobs match your filters', 'wp-career-board' ),
			'body'              => __( 'Try removing a filter or clearing them all to see more results.', 'wp-career-board' ),
			'clear_action'      => 'actions.clearFilters',
			'clear_hidden_bind' => 'state.noActiveFilters',
			'clear_label'       => __( 'Clear filters', 'wp-career-board' ),
		);
		require WCB_DIR . 'templates/parts/archive-empty-state.php';
		?>
	</div>

	<?php
	$wcb_load_more = array( 'label' => __( 'Load more jobs', 'wp-career-board' ) );
	require WCB_DIR . 'templates/parts/archive-load-more.php';
	?>

	<?php if ( $wcb_jl_has_filter_ui ) : ?>
		</main>
	</div><!-- /.wcb-archive-layout -->
	<?php endif; ?>
</div>
