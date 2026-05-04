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
$wcb_industry = \WCB\Core\Industries::label( (string) get_post_meta( $wcb_company_id, '_wcb_industry', true ) );
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
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-company-profile wcb-cp-wrap' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php /* ── Hero ── */ ?>
	<div class="wcb-cp-hero">
		<div class="wcb-cp-cover" aria-hidden="true"></div>

		<div class="wcb-cp-hero-body">
			<?php /* Avatar / Logo */ ?>
			<div class="wcb-cp-avatar-wrap">
				<?php if ( $wcb_logo_url ) : ?>
					<img class="wcb-cp-logo" src="<?php echo esc_url( $wcb_logo_url ); ?>" alt="<?php echo esc_attr( $wcb_name ); ?>" />
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
						<span class="wcb-cp-trust-badge <?php echo esc_attr( $wcb_trust_info['class'] ); ?>" role="status">
							<?php if ( 'premium' === $wcb_trust ) : ?>
								<i data-lucide="star" aria-hidden="true"></i>
							<?php else : ?>
								<i data-lucide="check" aria-hidden="true"></i>
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
							<i data-lucide="briefcase" aria-hidden="true"></i>
							<?php echo esc_html( $wcb_industry ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $wcb_size_label ) : ?>
						<span class="wcb-cp-chip">
							<i data-lucide="users" aria-hidden="true"></i>
							<?php echo esc_html( $wcb_size_label ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $wcb_hq ) : ?>
						<span class="wcb-cp-chip">
							<i data-lucide="map-pin" aria-hidden="true"></i>
							<?php echo esc_html( $wcb_hq ); ?>
						</span>
					<?php endif; ?>
				</div>

				<?php /* External links */ ?>
				<div class="wcb-cp-links">
					<?php if ( $wcb_website ) : ?>
						<a class="wcb-cp-link wcb-cp-link--web" href="<?php echo esc_url( $wcb_website ); ?>" target="_blank" rel="noopener noreferrer">
							<i data-lucide="globe" aria-hidden="true"></i>
							<?php esc_html_e( 'Website', 'wp-career-board' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $wcb_linkedin ) : ?>
						<a class="wcb-cp-link wcb-cp-link--linkedin" href="<?php echo esc_url( $wcb_linkedin ); ?>" target="_blank" rel="noopener noreferrer">
							<i data-lucide="linkedin" aria-hidden="true"></i>
							<?php esc_html_e( 'LinkedIn', 'wp-career-board' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $wcb_twitter ) : ?>
						<a class="wcb-cp-link wcb-cp-link--twitter" href="<?php echo esc_url( $wcb_twitter ); ?>" target="_blank" rel="noopener noreferrer">
							<i data-lucide="twitter" aria-hidden="true"></i>
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
				'post_type'     => 'wcb_job',
				'post_status'   => 'publish',
				'numberposts'   => $wcb_cp_per_page,
				'no_found_rows' => true,
				'meta_query'    => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
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
				'apiBase'   => untrailingslashit( rest_url( 'wcb/v1/jobs' ) ),
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
								<a aria-label="<?php esc_attr_e( 'Job listing', 'wp-career-board' ); ?>" data-wp-bind--href="context.job.permalink" data-wp-bind--aria-label="context.job.title" data-wp-text="context.job.title"></a>
							</h3>
							<div class="wcb-cp-job-badges">
								<span class="wcb-cjbadge wcb-cjbadge--type" data-wp-class--wcb-shown="context.job.type" data-wp-text="context.job.type"></span>
								<span class="wcb-cjbadge wcb-cjbadge--location" data-wp-class--wcb-shown="context.job.location">
									<i data-lucide="map-pin" aria-hidden="true"></i>
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
