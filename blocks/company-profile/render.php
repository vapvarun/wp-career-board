<?php
/**
 * Block render: wcb/company-profile — LinkedIn-style public company profile page.
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

// ── Resolve company post ──────────────────────────────────────────────────────
$wcb_company_id = (int) ( $attributes['companyId'] ?? 0 );
if ( ! $wcb_company_id ) {
	$wcb_queried = get_queried_object();
	if ( $wcb_queried instanceof \WP_Post && 'wcb_company' === $wcb_queried->post_type ) {
		$wcb_company_id = $wcb_queried->ID;
	}
}

$wcb_company = $wcb_company_id ? get_post( $wcb_company_id ) : null;
if ( ! $wcb_company instanceof \WP_Post ) {
	return;
}

// ── Meta fields ───────────────────────────────────────────────────────────────
$wcb_name     = $wcb_company->post_title;
$wcb_desc     = $wcb_company->post_content;
$wcb_tagline  = (string) get_post_meta( $wcb_company_id, '_wcb_tagline', true );
$wcb_website  = (string) get_post_meta( $wcb_company_id, '_wcb_website', true );
$wcb_linkedin = (string) get_post_meta( $wcb_company_id, '_wcb_linkedin', true );
$wcb_twitter  = (string) get_post_meta( $wcb_company_id, '_wcb_twitter', true );
$wcb_industry = (string) get_post_meta( $wcb_company_id, '_wcb_industry', true );
$wcb_size     = (string) get_post_meta( $wcb_company_id, '_wcb_company_size', true );
$wcb_type     = (string) get_post_meta( $wcb_company_id, '_wcb_company_type', true );
$wcb_founded  = (string) get_post_meta( $wcb_company_id, '_wcb_founded', true );
$wcb_hq       = (string) get_post_meta( $wcb_company_id, '_wcb_hq_location', true );
$wcb_trust    = (string) get_post_meta( $wcb_company_id, '_wcb_trust_level', true );
$wcb_logo_url = (string) get_the_post_thumbnail_url( $wcb_company_id, 'medium' );
$wcb_is_owner = get_current_user_id() === (int) $wcb_company->post_author;

// ── Initials avatar ───────────────────────────────────────────────────────────
$wcb_words    = array_filter( explode( ' ', trim( $wcb_name ) ) );
$wcb_initials = '';
foreach ( array_slice( $wcb_words, 0, 2 ) as $wcb_word ) {
	$wcb_initials .= mb_strtoupper( mb_substr( $wcb_word, 0, 1 ) );
}
$wcb_initials = $wcb_initials ? $wcb_initials : '?';

// ── Trust badge ───────────────────────────────────────────────────────────────
$wcb_trust_map  = array(
	'verified' => array(
		'label' => __( 'Verified', 'wp-career-board' ),
		'class' => 'wcb-trust--verified',
	),
	'trusted'  => array(
		'label' => __( 'Trusted', 'wp-career-board' ),
		'class' => 'wcb-trust--trusted',
	),
	'premium'  => array(
		'label' => __( 'Premium', 'wp-career-board' ),
		'class' => 'wcb-trust--premium',
	),
);
$wcb_trust_info = $wcb_trust_map[ $wcb_trust ] ?? null;

// ── Size labels ───────────────────────────────────────────────────────────────
$wcb_size_labels = array(
	'1-10'      => __( '1–10 employees', 'wp-career-board' ),
	'11-50'     => __( '11–50 employees', 'wp-career-board' ),
	'51-200'    => __( '51–200 employees', 'wp-career-board' ),
	'201-500'   => __( '201–500 employees', 'wp-career-board' ),
	'501-1000'  => __( '501–1,000 employees', 'wp-career-board' ),
	'1001-5000' => __( '1,001–5,000 employees', 'wp-career-board' ),
	'5000+'     => __( '5,000+ employees', 'wp-career-board' ),
);
$wcb_size_label  = $wcb_size_labels[ $wcb_size ] ?? $wcb_size;

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-cp-wrap' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php /* ── Hero ── */ ?>
	<div class="wcb-cp-hero">
		<div class="wcb-cp-cover" aria-hidden="true"></div>

		<div class="wcb-cp-hero-body">
			<?php /* Avatar / Logo */ ?>
			<div class="wcb-cp-avatar-wrap">
				<?php if ( $wcb_logo_url ) : ?>
					<img
						class="wcb-cp-logo"
						src="<?php echo esc_url( $wcb_logo_url ); ?>"
						alt="<?php echo esc_attr( $wcb_name ); ?>"
					/>
				<?php else : ?>
					<div class="wcb-cp-avatar" aria-hidden="true">
						<?php echo esc_html( $wcb_initials ); ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="wcb-cp-hero-info">
				<div class="wcb-cp-name-row">
					<h1 class="wcb-cp-name"><?php echo esc_html( $wcb_name ); ?></h1>
					<?php if ( $wcb_trust_info ) : ?>
						<span class="wcb-cp-trust-badge <?php echo esc_attr( $wcb_trust_info['class'] ); ?>">
							<?php if ( 'premium' === $wcb_trust ) : ?>
								<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
							<?php else : ?>
								<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
							<?php endif; ?>
							<?php echo esc_html( $wcb_trust_info['label'] ); ?>
						</span>
					<?php endif; ?>
				</div>

				<?php if ( $wcb_tagline ) : ?>
					<p class="wcb-cp-tagline"><?php echo esc_html( $wcb_tagline ); ?></p>
				<?php endif; ?>

				<?php /* Quick meta chips */ ?>
				<div class="wcb-cp-meta-chips">
					<?php if ( $wcb_industry ) : ?>
						<span class="wcb-cp-chip">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 6h-2.18c.07-.44.18-.86.18-1.3C18 2.12 15.88 0 13.3 0c-1.48 0-2.67.73-3.58 1.68L12 4l2.28-2.28C14.8 1.28 15.5 1 16.3 1c2.21 0 4 1.79 4 4 0 .44-.1.86-.18 1.3H20v14H4V6h4V4H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2z"/></svg>
							<?php echo esc_html( $wcb_industry ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $wcb_size_label ) : ?>
						<span class="wcb-cp-chip">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
							<?php echo esc_html( $wcb_size_label ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $wcb_hq ) : ?>
						<span class="wcb-cp-chip">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
							<?php echo esc_html( $wcb_hq ); ?>
						</span>
					<?php endif; ?>
				</div>

				<?php /* External links */ ?>
				<div class="wcb-cp-links">
					<?php if ( $wcb_website ) : ?>
						<a class="wcb-cp-link wcb-cp-link--web" href="<?php echo esc_url( $wcb_website ); ?>" target="_blank" rel="noopener noreferrer">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.94-.49-7-3.85-7-7.93s3.06-7.44 7-7.93v15.86zm2 0V4.07c3.94.49 7 3.85 7 7.93s-3.06 7.44-7 7.93z"/></svg>
							<?php esc_html_e( 'Website', 'wp-career-board' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $wcb_linkedin ) : ?>
						<a class="wcb-cp-link wcb-cp-link--linkedin" href="<?php echo esc_url( $wcb_linkedin ); ?>" target="_blank" rel="noopener noreferrer">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
							<?php esc_html_e( 'LinkedIn', 'wp-career-board' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $wcb_twitter ) : ?>
						<a class="wcb-cp-link wcb-cp-link--twitter" href="<?php echo esc_url( $wcb_twitter ); ?>" target="_blank" rel="noopener noreferrer">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.736l7.737-8.835L1.254 2.25H8.08l4.259 5.63zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
							<?php esc_html_e( 'X / Twitter', 'wp-career-board' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<?php /* ── Body ── */ ?>
	<div class="wcb-cp-body">

		<?php /* About */ ?>
		<?php if ( $wcb_desc ) : ?>
			<section class="wcb-cp-section">
				<h2 class="wcb-cp-section-title"><?php esc_html_e( 'About', 'wp-career-board' ); ?></h2>
				<div class="wcb-cp-desc">
					<?php echo wp_kses_post( wpautop( $wcb_desc ) ); ?>
				</div>
			</section>
		<?php endif; ?>

		<?php /* Company Details */ ?>
		<?php if ( $wcb_industry || $wcb_size || $wcb_type || $wcb_founded || $wcb_hq || $wcb_website ) : ?>
			<section class="wcb-cp-section">
				<h2 class="wcb-cp-section-title"><?php esc_html_e( 'Company Details', 'wp-career-board' ); ?></h2>
				<dl class="wcb-cp-details-grid">
					<?php if ( $wcb_industry ) : ?>
						<div class="wcb-cp-detail-item">
							<dt><?php esc_html_e( 'Industry', 'wp-career-board' ); ?></dt>
							<dd><?php echo esc_html( $wcb_industry ); ?></dd>
						</div>
					<?php endif; ?>
					<?php if ( $wcb_size_label ) : ?>
						<div class="wcb-cp-detail-item">
							<dt><?php esc_html_e( 'Company size', 'wp-career-board' ); ?></dt>
							<dd><?php echo esc_html( $wcb_size_label ); ?></dd>
						</div>
					<?php endif; ?>
					<?php if ( $wcb_type ) : ?>
						<div class="wcb-cp-detail-item">
							<dt><?php esc_html_e( 'Type', 'wp-career-board' ); ?></dt>
							<dd><?php echo esc_html( $wcb_type ); ?></dd>
						</div>
					<?php endif; ?>
					<?php if ( $wcb_founded ) : ?>
						<div class="wcb-cp-detail-item">
							<dt><?php esc_html_e( 'Founded', 'wp-career-board' ); ?></dt>
							<dd><?php echo esc_html( $wcb_founded ); ?></dd>
						</div>
					<?php endif; ?>
					<?php if ( $wcb_hq ) : ?>
						<div class="wcb-cp-detail-item">
							<dt><?php esc_html_e( 'Headquarters', 'wp-career-board' ); ?></dt>
							<dd><?php echo esc_html( $wcb_hq ); ?></dd>
						</div>
					<?php endif; ?>
					<?php if ( $wcb_website ) : ?>
						<div class="wcb-cp-detail-item">
							<dt><?php esc_html_e( 'Website', 'wp-career-board' ); ?></dt>
							<dd>
								<a href="<?php echo esc_url( $wcb_website ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( preg_replace( '#^https?://#', '', rtrim( $wcb_website, '/' ) ) ); ?>
								</a>
							</dd>
						</div>
					<?php endif; ?>
				</dl>
			</section>
		<?php endif; ?>

		<?php
		/* ── Open Positions — Interactivity API (paginated) ── */
		$wcb_cp_per_page  = 10;
		$wcb_cp_author_id = (int) $wcb_company->post_author;

		$wcb_open_jobs = get_posts(
			array(
				'post_type'      => 'wcb_job',
				'post_status'    => 'publish',
				'numberposts'    => $wcb_cp_per_page,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_wcb_company_id',
						'value' => $wcb_company->ID,
					),
				),
			)
		);

		$wcb_cp_jobs_state = array();
		foreach ( $wcb_open_jobs as $wcb_jpost ) {
			$wcb_jloc            = wp_get_object_terms( $wcb_jpost->ID, 'wcb_location', array( 'fields' => 'names' ) );
			$wcb_jtype           = wp_get_object_terms( $wcb_jpost->ID, 'wcb_job_type', array( 'fields' => 'names' ) );
			$wcb_cp_jobs_state[] = array(
				'id'        => $wcb_jpost->ID,
				'title'     => $wcb_jpost->post_title,
				'permalink' => (string) get_permalink( $wcb_jpost->ID ),
				'type'      => is_wp_error( $wcb_jtype ) ? '' : implode( ', ', $wcb_jtype ),
				'location'  => is_wp_error( $wcb_jloc ) ? '' : implode( ', ', $wcb_jloc ),
			);
		}

		wp_interactivity_state(
			'wcb-company-profile',
			array(
				'jobs'      => $wcb_cp_jobs_state,
				'page'      => 1,
				'perPage'   => $wcb_cp_per_page,
				'author'    => $wcb_cp_author_id,
				'loading'   => false,
				'hasMore'   => count( $wcb_open_jobs ) >= $wcb_cp_per_page,
				'hasNoJobs' => empty( $wcb_cp_jobs_state ),
				'apiBase'   => rest_url( 'wcb/v1/jobs' ),
			)
		);
		?>
		<section
			class="wcb-cp-section"
			data-wp-interactive="wcb-company-profile"
		>
			<h2 class="wcb-cp-section-title"><?php esc_html_e( 'Open Positions', 'wp-career-board' ); ?></h2>

			<p class="wcb-cp-no-jobs" data-wp-bind--hidden="!state.hasNoJobs">
				<?php esc_html_e( 'No open positions at the moment. Check back soon.', 'wp-career-board' ); ?>
			</p>

			<div class="wcb-cp-jobs-list">
				<template data-wp-each--job="state.jobs" data-wp-each-key="context.job.id">
					<article class="wcb-cp-job-card">
						<div class="wcb-cp-job-main">
							<h3 class="wcb-cp-job-title">
								<a data-wp-bind--href="context.job.permalink" data-wp-text="context.job.title"></a>
							</h3>
							<div class="wcb-cp-job-badges">
								<span class="wcb-cjbadge wcb-cjbadge--type" data-wp-class--wcb-shown="context.job.type" data-wp-text="context.job.type"></span>
								<span class="wcb-cjbadge wcb-cjbadge--location" data-wp-class--wcb-shown="context.job.location">
									<svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
									<span data-wp-text="context.job.location"></span>
								</span>
							</div>
						</div>
						<a class="wcb-cp-job-apply" data-wp-bind--href="context.job.permalink"><?php esc_html_e( 'View Job', 'wp-career-board' ); ?></a>
					</article>
				</template>
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

		</section>

	</div>

</div>
