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

$wcb_author_id_attr = (int) ( $attributes['authorId'] ?? 0 );
$wcb_saved_by_attr  = (int) ( $attributes['savedBy'] ?? 0 );

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
	// Return no results when the user has no bookmarks.
	$wcb_query_args['post__in']    = ! empty( $wcb_bookmark_ids ) ? $wcb_bookmark_ids : array( 0 );
	$wcb_query_args['numberposts'] = -1;
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
		'trust_icon'   => $wcb_trust_info['icon'] ?? '',
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

$wcb_board_opts = (array) apply_filters( 'wcb_job_listings_board_options', array() );

if ( $wcb_author_id_attr > 0 ) {
	$wcb_count_query = new \WP_Query(
		array(
			'post_type'      => 'wcb_job',
			'post_status'    => 'publish',
			'author'         => $wcb_author_id_attr,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	$wcb_total_count = (int) $wcb_count_query->found_posts;
} elseif ( $wcb_saved_by_attr > 0 ) {
	$wcb_total_count = count( $wcb_jobs_raw );
} else {
	$wcb_total_count = (int) wp_count_posts( 'wcb_job' )->publish;
}

$wcb_state = array(
	'jobs'          => $wcb_jobs_state,
	'page'          => 1,
	'perPage'       => $wcb_per_page,
	'layout'        => $wcb_layout,
	'loading'       => false,
	'hasMore'       => 0 === $wcb_saved_by_attr && count( $wcb_jobs_raw ) >= $wcb_per_page,
	'apiBase'       => (string) apply_filters( 'wcb_job_listings_api_base', rest_url( 'wcb/v1/jobs' ) ),
	'nonce'         => wp_create_nonce( 'wp_rest' ),
	'totalCount'    => $wcb_total_count,
	'searchQuery'   => '',
	'activeFilters' => (object) array(),
	'sortBy'        => 'date_desc',
	'alertSaved'    => false,
	'alertSaving'   => false,
	'authorId'      => $wcb_author_id_attr,
	'savedBy'       => $wcb_saved_by_attr,
	'filterOptions' => array(
		'types'       => $wcb_type_opts,
		'experiences' => $wcb_exp_opts,
		'boards'      => $wcb_board_opts,
	),
	'strings'       => array(
		'bookmarkRemove' => __( 'Saved', 'wp-career-board' ),
		'bookmarkAdd'    => __( 'Save job', 'wp-career-board' ),
		/* translators: %d: number of jobs */
		'jobCountSingle' => __( '1 job', 'wp-career-board' ),
		/* translators: %d: number of jobs */
		'jobCountPlural' => __( '%d jobs', 'wp-career-board' ),
		/* translators: 1: number of shown jobs, 2: total number of jobs */
		'jobCountOf'     => __( '%1$d of %2$d jobs', 'wp-career-board' ),
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
	<?php if ( 0 === $wcb_author_id_attr && 0 === $wcb_saved_by_attr ) : ?>
	<div class="wcb-listings-header">
		<div class="wcb-search-sort-row">
			<?php if ( ! has_block( 'wp-career-board/job-search' ) ) : ?>
			<div class="wcb-search-wrap">
				<span class="wcb-search-icon" aria-hidden="true">
					<i data-lucide="search" aria-hidden="true"></i>
				</span>
				<label class="screen-reader-text" for="wcb-job-search"><?php esc_html_e( 'Search jobs', 'wp-career-board' ); ?></label>
				<input
					type="search"
					id="wcb-job-search"
					class="wcb-listings-search"
					placeholder="<?php esc_attr_e( 'Search jobs…', 'wp-career-board' ); ?>"
					data-wp-bind--value="state.searchQuery"
					data-wp-on--input="actions.updateSearch"
				/>
			</div>
			<?php endif; ?>
			<select class="wcb-sort-select" aria-label="<?php esc_attr_e( 'Sort jobs', 'wp-career-board' ); ?>" data-wp-on--change="actions.changeSort" data-wp-bind--value="state.sortBy">
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

			<?php if ( $wcb_board_opts ) : ?>
			<span class="wcb-chip-divider" aria-hidden="true"></span>
			<?php foreach ( $wcb_board_opts as $wcb_opt ) : ?>
			<button type="button" class="wcb-chip"
				data-wp-class--wcb-chip-active="state.isBoardActive"
				data-wp-on--click="actions.toggleBoardChip"
				data-wp-context="<?php echo esc_attr( wp_json_encode( array( 'boardId' => $wcb_opt['id'], 'boardName' => $wcb_opt['name'] ) ) ); ?>"
			><?php echo esc_html( $wcb_opt['name'] ); ?></button>
			<?php endforeach; ?>
			<?php endif; ?>
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
			<div class="wcb-toolbar-start">
				<p class="wcb-results-count" aria-live="polite" data-wp-text="state.resultsLabel"></p>
				<?php if ( class_exists( 'WCB\Pro\Modules\Alerts\AlertsModule' ) ) : ?>
				<button
					type="button"
					class="wcb-alert-me-btn"
					data-wp-on--click="actions.saveSearchAlert"
					data-wp-class--wcb-alert-saved="state.alertSaved"
					data-wp-bind--disabled="state.alertSaving"
				>
					<span data-wp-class--wcb-hidden="state.alertSaved">&#128276; <?php esc_html_e( 'Alert me', 'wp-career-board' ); ?></span>
					<span data-wp-class--wcb-hidden="!state.alertSaved">&#10003; <?php esc_html_e( 'Alert saved', 'wp-career-board' ); ?></span>
				</button>
				<?php endif; ?>
			</div>
			<div class="wcb-view-switcher" role="group" aria-label="<?php esc_attr_e( 'View layout', 'wp-career-board' ); ?>">
				<button type="button" class="wcb-view-btn"
					data-wp-class--wcb-view-btn--active="state.isGrid"
					data-wp-on--click="actions.setGridLayout"
					aria-label="<?php esc_attr_e( 'Grid view', 'wp-career-board' ); ?>"
				>
					<i data-lucide="layout-grid" aria-hidden="true"></i>
				</button>
				<button type="button" class="wcb-view-btn"
					data-wp-class--wcb-view-btn--active="state.isList"
					data-wp-on--click="actions.setListLayout"
					aria-label="<?php esc_attr_e( 'List view', 'wp-career-board' ); ?>"
				>
					<i data-lucide="list" aria-hidden="true"></i>
				</button>
			</div>
		</div>
	</div>
	<?php endif; ?>

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
								<a class="wcb-card-title-link" data-wp-bind--href="context.job.permalink" data-wp-text="context.job.title"></a>
							</h3>
							<p class="wcb-card-company">
								<span data-wp-text="context.job.company"></span>
								<span
									class="wcb-verified-sm"
									role="status"
									data-wp-class--wcb-shown="context.job.verified"
									data-wp-bind--data-trust="context.job.trust"
								>
									<span aria-hidden="true" data-wp-text="context.job.trust_icon"></span>
									<span data-wp-text="context.job.trust_label"></span>
								</span>
							</p>
						</div>
						<button
							type="button"
							class="wcb-bookmark-btn"
							data-wp-on--click="actions.toggleBookmark"
							data-wp-class--wcb-bookmarked="context.job.bookmarked"
							data-wp-bind--aria-label="state.bookmarkLabel"
							aria-label="<?php esc_attr_e( 'Save job', 'wp-career-board' ); ?>"
						>
							<i data-lucide="bookmark" aria-hidden="true"></i>
						</button>
					</div>

					<div class="wcb-card-badges">
						<span class="wcb-cbadge wcb-cbadge--featured" role="status" data-wp-class--wcb-shown="context.job.featured"><?php esc_html_e( 'Featured', 'wp-career-board' ); ?></span>
					<span class="wcb-cbadge wcb-cbadge--board" role="status" data-wp-class--wcb-shown="context.job.board_name" data-wp-text="context.job.board_name"></span>
						<span class="wcb-cbadge wcb-cbadge--remote" role="status" data-wp-class--wcb-shown="context.job.remote"><?php esc_html_e( 'Remote', 'wp-career-board' ); ?></span>
						<span class="wcb-cbadge wcb-cbadge--type" role="status" data-wp-class--wcb-shown="context.job.type" data-wp-text="context.job.type"></span>
						<span class="wcb-cbadge wcb-cbadge--exp" role="status" data-wp-class--wcb-shown="context.job.experience" data-wp-text="context.job.experience"></span>
						<span class="wcb-cbadge wcb-cbadge--location" role="status" data-wp-class--wcb-shown="context.job.location">
							<i data-lucide="map-pin" aria-hidden="true"></i>
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
		<div class="wcb-empty-state" data-wp-bind--hidden="!state.hasNoJobs" role="status" <?php echo $wcb_jobs_raw ? 'hidden' : ''; ?>>
			<i data-lucide="inbox" aria-hidden="true"></i>
			<p class="wcb-empty-state-text"><?php esc_html_e( 'No jobs match your search. Try adjusting your filters.', 'wp-career-board' ); ?></p>
		</div>
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
