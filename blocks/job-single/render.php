<?php
/**
 * Block render: wcb/job-single — enterprise-grade job detail with apply panel.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$wcb_job_id = get_queried_object_id();
$wcb_job    = $wcb_job_id ? get_post( $wcb_job_id ) : null;

if ( ! $wcb_job || 'wcb_job' !== $wcb_job->post_type ) {
	return;
}

// ── Taxonomies — full term objects for link generation ───────────────────────
$wcb_location_terms   = wp_get_object_terms( $wcb_job_id, 'wcb_location' );
$wcb_type_terms       = wp_get_object_terms( $wcb_job_id, 'wcb_job_type' );
$wcb_experience_terms = wp_get_object_terms( $wcb_job_id, 'wcb_experience' );
$wcb_category_terms   = wp_get_object_terms( $wcb_job_id, 'wcb_category' );

// Plain-text strings used in sidebar detail list.
$wcb_location   = ! is_wp_error( $wcb_location_terms ) ? implode( ', ', wp_list_pluck( $wcb_location_terms, 'name' ) ) : '';
$wcb_type       = ! is_wp_error( $wcb_type_terms ) ? implode( ' · ', wp_list_pluck( $wcb_type_terms, 'name' ) ) : '';
$wcb_experience = ! is_wp_error( $wcb_experience_terms ) ? implode( ', ', wp_list_pluck( $wcb_experience_terms, 'name' ) ) : '';
$wcb_categories = ! is_wp_error( $wcb_category_terms ) ? $wcb_category_terms : array();

// Normalize to arrays for safe iteration.
$wcb_location_terms   = is_wp_error( $wcb_location_terms ) ? array() : $wcb_location_terms;
$wcb_type_terms       = is_wp_error( $wcb_type_terms ) ? array() : $wcb_type_terms;
$wcb_experience_terms = is_wp_error( $wcb_experience_terms ) ? array() : $wcb_experience_terms;

// ── Job meta ─────────────────────────────────────────────────────────────────
$wcb_settings   = (array) get_option( 'wcb_settings', array() );
$wcb_currency   = isset( $wcb_settings['salary_currency'] ) ? $wcb_settings['salary_currency'] : '$';
$wcb_remote     = '1' === (string) get_post_meta( $wcb_job_id, '_wcb_remote', true );
$wcb_salary_min = (string) get_post_meta( $wcb_job_id, '_wcb_salary_min', true );
$wcb_salary_max = (string) get_post_meta( $wcb_job_id, '_wcb_salary_max', true );
$wcb_deadline   = (string) get_post_meta( $wcb_job_id, '_wcb_deadline', true );
$wcb_featured   = '1' === (string) get_post_meta( $wcb_job_id, '_wcb_featured', true );

// ── Salary display ────────────────────────────────────────────────────────────
$wcb_salary_str = '';
if ( $wcb_salary_min && $wcb_salary_max ) {
	$wcb_salary_str = $wcb_currency . number_format( (int) $wcb_salary_min ) . ' – ' . $wcb_currency . number_format( (int) $wcb_salary_max );
} elseif ( $wcb_salary_min ) {
	$wcb_salary_str = $wcb_currency . number_format( (int) $wcb_salary_min ) . '+';
} elseif ( $wcb_salary_max ) {
	/* translators: %s: maximum salary */
	$wcb_salary_str = sprintf( __( 'Up to %s', 'wp-career-board' ), $wcb_currency . number_format( (int) $wcb_salary_max ) );
}

// ── Company ───────────────────────────────────────────────────────────────────
$wcb_company_name = (string) get_post_meta( $wcb_job_id, '_wcb_company_name', true );
$wcb_author_id    = (int) $wcb_job->post_author;
$wcb_company_id   = (int) get_user_meta( $wcb_author_id, '_wcb_company_id', true );
$wcb_company_post = $wcb_company_id ? get_post( $wcb_company_id ) : null;

if ( ! $wcb_company_name && $wcb_company_post instanceof \WP_Post ) {
	$wcb_company_name = $wcb_company_post->post_title;
}

$wcb_company_url   = ( $wcb_company_post instanceof \WP_Post ) ? (string) get_permalink( $wcb_company_id ) : '';
$wcb_company_desc  = $wcb_company_post instanceof \WP_Post ? wp_trim_words( $wcb_company_post->post_content, 40 ) : '';
$wcb_company_site  = $wcb_company_id ? (string) get_post_meta( $wcb_company_id, '_wcb_website', true ) : '';
$wcb_company_trust = $wcb_company_id ? (string) get_post_meta( $wcb_company_id, '_wcb_trust_level', true ) : '';

// ── Posted date ───────────────────────────────────────────────────────────────
$wcb_days_ago = (int) round( ( time() - (int) strtotime( $wcb_job->post_date ) ) / DAY_IN_SECONDS );

// ── Apply permission ──────────────────────────────────────────────────────────
// phpcs:disable WordPress.WP.Capabilities.Unknown -- wcb_apply_jobs is a registered custom capability.
$wcb_can_apply = is_user_logged_in() && (
	function_exists( 'wp_is_ability_granted' )
		? wp_is_ability_granted( 'wcb_apply_jobs' )
		: current_user_can( 'wcb_apply_jobs' )
);
// phpcs:enable WordPress.WP.Capabilities.Unknown

// ── Bookmark state ────────────────────────────────────────────────────────────
$wcb_current_user_id = get_current_user_id();
$wcb_bookmarks       = $wcb_current_user_id
	? array_map( 'intval', (array) get_user_meta( $wcb_current_user_id, '_wcb_bookmark', false ) )
	: array();
$wcb_is_bookmarked   = in_array( $wcb_job_id, $wcb_bookmarks, true );

wp_interactivity_state(
	'wcb-job-single',
	array(
		'jobId'       => $wcb_job_id,
		'apiBase'     => rest_url( 'wcb/v1' ),
		'nonce'       => wp_create_nonce( 'wp_rest' ),
		'panelOpen'   => false,
		'submitting'  => false,
		'submitted'   => false,
		'bookmarked'  => $wcb_is_bookmarked,
		'bookmarking' => false,
		'coverLetter' => '',
		'error'       => '',
	)
);
?>
<div
	<?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-job-single"
>

	<?php /* ── Hero banner ──────────────────────────────────────────────── */ ?>
	<div class="wcb-job-hero">

		<div class="wcb-job-hero-brand">
			<div class="wcb-company-avatar">
				<?php echo esc_html( mb_strtoupper( mb_substr( $wcb_company_name ? $wcb_company_name : $wcb_job->post_title, 0, 2 ) ) ); ?>
			</div>
			<div class="wcb-hero-titles">
				<h1 class="wcb-job-title"><?php echo esc_html( $wcb_job->post_title ); ?></h1>
				<?php if ( $wcb_company_name ) : ?>
					<p class="wcb-hero-company">
						<?php if ( $wcb_company_url ) : ?>
							<a href="<?php echo esc_url( $wcb_company_url ); ?>" class="wcb-hero-company-link">
								<?php echo esc_html( $wcb_company_name ); ?>
							</a>
						<?php else : ?>
							<?php echo esc_html( $wcb_company_name ); ?>
						<?php endif; ?>
						<?php if ( 'verified' === $wcb_company_trust ) : ?>
							<span class="wcb-verified-badge"><?php esc_html_e( '✓ Verified', 'wp-career-board' ); ?></span>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<div class="wcb-job-hero-meta">
			<?php if ( $wcb_featured ) : ?>
				<span class="wcb-badge wcb-badge--featured"><?php esc_html_e( 'Featured', 'wp-career-board' ); ?></span>
			<?php endif; ?>

			<?php if ( $wcb_remote ) : ?>
				<span class="wcb-badge wcb-badge--remote"><?php esc_html_e( 'Remote', 'wp-career-board' ); ?></span>
			<?php elseif ( $wcb_location_terms ) : ?>
				<?php foreach ( $wcb_location_terms as $wcb_term ) : ?>
					<a href="<?php echo esc_url( (string) get_term_link( $wcb_term ) ); ?>" class="wcb-badge wcb-badge--location">
						📍 <?php echo esc_html( $wcb_term->name ); ?>
					</a>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php foreach ( $wcb_type_terms as $wcb_term ) : ?>
				<a href="<?php echo esc_url( (string) get_term_link( $wcb_term ) ); ?>" class="wcb-badge wcb-badge--type">
					<?php echo esc_html( $wcb_term->name ); ?>
				</a>
			<?php endforeach; ?>

			<?php foreach ( $wcb_experience_terms as $wcb_term ) : ?>
				<a href="<?php echo esc_url( (string) get_term_link( $wcb_term ) ); ?>" class="wcb-badge wcb-badge--exp">
					<?php echo esc_html( $wcb_term->name ); ?>
				</a>
			<?php endforeach; ?>

			<?php if ( $wcb_salary_str ) : ?>
				<span class="wcb-badge wcb-badge--salary"><?php echo esc_html( $wcb_salary_str ); ?></span>
			<?php endif; ?>

			<span class="wcb-badge wcb-badge--posted">
				<?php
				if ( 0 === $wcb_days_ago ) {
					esc_html_e( 'Posted today', 'wp-career-board' );
				} elseif ( 1 === $wcb_days_ago ) {
					esc_html_e( 'Posted yesterday', 'wp-career-board' );
				} else {
					/* translators: %d: number of days */
					printf( esc_html__( 'Posted %d days ago', 'wp-career-board' ), absint( $wcb_days_ago ) );
				}
				?>
			</span>
		</div>

		<div class="wcb-hero-cta">
			<?php if ( $wcb_can_apply ) : ?>
				<button
					type="button"
					class="wcb-btn wcb-btn--primary wcb-apply-trigger"
					data-wp-on--click="actions.openPanel"
					data-wp-class--wcb-hidden="state.submitted"
				>
					<?php esc_html_e( 'Apply Now', 'wp-career-board' ); ?>
				</button>
				<p class="wcb-applied-badge" data-wp-class--wcb-shown="state.submitted">
					<?php esc_html_e( '✓ Application Submitted', 'wp-career-board' ); ?>
				</p>
				<?php if ( $wcb_deadline ) : ?>
					<p class="wcb-deadline-note">
						<?php
						/* translators: %s: deadline date */
						printf( esc_html__( 'Apply by %s', 'wp-career-board' ), esc_html( $wcb_deadline ) );
						?>
					</p>
				<?php endif; ?>
			<?php elseif ( ! is_user_logged_in() ) : ?>
				<a href="<?php echo esc_url( wp_login_url( (string) get_permalink() ) ); ?>" class="wcb-btn wcb-btn--primary">
					<?php esc_html_e( 'Sign In to Apply', 'wp-career-board' ); ?>
				</a>
			<?php endif; ?>

			<?php if ( is_user_logged_in() ) : ?>
				<button
					type="button"
					class="wcb-bookmark-hero-btn"
					data-wp-on--click="actions.toggleBookmark"
					data-wp-class--wcb-bookmarked="state.bookmarked"
					data-wp-bind--disabled="state.bookmarking"
					data-wp-bind--aria-label="state.bookmarkLabel"
					aria-label="<?php echo $wcb_is_bookmarked ? esc_attr( __( 'Saved', 'wp-career-board' ) ) : esc_attr( __( 'Save Job', 'wp-career-board' ) ); ?>"
					title="<?php esc_attr_e( 'Save this job', 'wp-career-board' ); ?>"
				>
					<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17 3H7a2 2 0 0 0-2 2v16l7-3 7 3V5a2 2 0 0 0-2-2z"/></svg>
					<span data-wp-text="state.bookmarkLabel"><?php echo $wcb_is_bookmarked ? esc_html( __( 'Saved', 'wp-career-board' ) ) : esc_html( __( 'Save Job', 'wp-career-board' ) ); ?></span>
				</button>
			<?php endif; ?>
		</div>
	</div>

	<?php /* ── Two-column body ─────────────────────────────────────────── */ ?>
	<div class="wcb-job-body">

		<?php /* Main content */ ?>
		<div class="wcb-job-main">
			<div class="wcb-section">
				<h2 class="wcb-section-heading"><?php esc_html_e( 'About This Role', 'wp-career-board' ); ?></h2>
				<div class="wcb-job-description">
					<?php echo wp_kses_post( apply_filters( 'the_content', $wcb_job->post_content ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>
				</div>
			</div>

			<?php if ( ! empty( $wcb_categories ) ) : ?>
				<div class="wcb-section">
					<h3 class="wcb-section-heading-sm"><?php esc_html_e( 'Job Categories', 'wp-career-board' ); ?></h3>
					<div class="wcb-tag-row">
						<?php foreach ( $wcb_categories as $wcb_cat ) : ?>
							<a href="<?php echo esc_url( (string) get_term_link( $wcb_cat ) ); ?>" class="wcb-tag">
								<?php echo esc_html( $wcb_cat->name ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<?php /* Sidebar */ ?>
		<aside class="wcb-job-sidebar">

			<?php /* Job Details card */ ?>
			<div class="wcb-sidebar-card">
				<h3 class="wcb-card-title"><?php esc_html_e( 'Job Details', 'wp-career-board' ); ?></h3>
				<dl class="wcb-detail-list">
					<?php if ( $wcb_type ) : ?>
						<div class="wcb-detail-row">
							<dt><?php esc_html_e( 'Job Type', 'wp-career-board' ); ?></dt>
							<dd><?php echo esc_html( $wcb_type ); ?></dd>
						</div>
					<?php endif; ?>

					<?php if ( $wcb_experience ) : ?>
						<div class="wcb-detail-row">
							<dt><?php esc_html_e( 'Experience', 'wp-career-board' ); ?></dt>
							<dd><?php echo esc_html( $wcb_experience ); ?></dd>
						</div>
					<?php endif; ?>

					<?php if ( $wcb_location ) : ?>
						<div class="wcb-detail-row">
							<dt><?php esc_html_e( 'Location', 'wp-career-board' ); ?></dt>
							<dd><?php echo esc_html( $wcb_location ); ?></dd>
						</div>
					<?php endif; ?>

					<div class="wcb-detail-row">
						<dt><?php esc_html_e( 'Work Mode', 'wp-career-board' ); ?></dt>
						<dd>
							<?php if ( $wcb_remote ) : ?>
								<span class="wcb-badge wcb-badge--remote wcb-badge--sm"><?php esc_html_e( 'Remote', 'wp-career-board' ); ?></span>
							<?php else : ?>
								<?php esc_html_e( 'On-site', 'wp-career-board' ); ?>
							<?php endif; ?>
						</dd>
					</div>

					<?php if ( $wcb_salary_str ) : ?>
						<div class="wcb-detail-row">
							<dt><?php esc_html_e( 'Salary', 'wp-career-board' ); ?></dt>
							<dd class="wcb-salary-highlight"><?php echo esc_html( $wcb_salary_str ); ?></dd>
						</div>
					<?php endif; ?>

					<?php if ( $wcb_deadline ) : ?>
						<div class="wcb-detail-row">
							<dt><?php esc_html_e( 'Apply By', 'wp-career-board' ); ?></dt>
							<dd><?php echo esc_html( $wcb_deadline ); ?></dd>
						</div>
					<?php endif; ?>
				</dl>

				<?php if ( $wcb_can_apply ) : ?>
					<button
						type="button"
						class="wcb-btn wcb-btn--primary wcb-btn--full"
						data-wp-on--click="actions.openPanel"
						data-wp-class--wcb-hidden="state.submitted"
					>
						<?php esc_html_e( 'Apply Now', 'wp-career-board' ); ?>
					</button>
					<p class="wcb-applied-badge wcb-applied-badge--center" data-wp-class--wcb-shown="state.submitted">
						<?php esc_html_e( '✓ Application Submitted', 'wp-career-board' ); ?>
					</p>
				<?php elseif ( ! is_user_logged_in() ) : ?>
					<a href="<?php echo esc_url( wp_login_url( (string) get_permalink() ) ); ?>" class="wcb-btn wcb-btn--primary wcb-btn--full">
						<?php esc_html_e( 'Sign In to Apply', 'wp-career-board' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<?php /* Company card */ ?>
			<?php if ( $wcb_company_name ) : ?>
				<div class="wcb-sidebar-card wcb-company-card">
					<h3 class="wcb-card-title"><?php esc_html_e( 'About the Company', 'wp-career-board' ); ?></h3>
					<div class="wcb-company-card-header">
						<div class="wcb-company-avatar wcb-company-avatar--sm">
							<?php echo esc_html( mb_strtoupper( mb_substr( $wcb_company_name, 0, 2 ) ) ); ?>
						</div>
						<div>
							<?php if ( $wcb_company_url ) : ?>
								<a href="<?php echo esc_url( $wcb_company_url ); ?>" class="wcb-company-card-name">
									<?php echo esc_html( $wcb_company_name ); ?>
								</a>
							<?php else : ?>
								<p class="wcb-company-card-name"><?php echo esc_html( $wcb_company_name ); ?></p>
							<?php endif; ?>
							<?php if ( 'verified' === $wcb_company_trust ) : ?>
								<span class="wcb-verified-badge"><?php esc_html_e( '✓ Verified', 'wp-career-board' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
					<?php if ( $wcb_company_desc ) : ?>
						<p class="wcb-company-bio"><?php echo esc_html( $wcb_company_desc ); ?></p>
					<?php endif; ?>
					<?php if ( $wcb_company_url ) : ?>
						<a href="<?php echo esc_url( $wcb_company_url ); ?>" class="wcb-company-link">
							<?php esc_html_e( 'View Company Profile', 'wp-career-board' ); ?> →
						</a>
					<?php endif; ?>
					<?php if ( $wcb_company_site ) : ?>
						<a
							href="<?php echo esc_url( $wcb_company_site ); ?>"
							class="wcb-company-link"
							target="_blank"
							rel="noopener noreferrer"
						>
							<?php
							$wcb_host = (string) wp_parse_url( $wcb_company_site, PHP_URL_HOST );
							echo esc_html( $wcb_host ? $wcb_host : $wcb_company_site );
							?>
							↗
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

		</aside>
	</div>

	<?php /* ── Slide-in apply panel ───────────────────────────────────── */ ?>
	<?php if ( $wcb_can_apply ) : ?>
		<div
			class="wcb-panel-overlay"
			data-wp-class--wcb-open="state.panelOpen"
			data-wp-on--click="actions.closePanel"
		></div>

		<div
			class="wcb-apply-panel"
			role="dialog"
			aria-modal="true"
			aria-label="<?php esc_attr_e( 'Apply for this job', 'wp-career-board' ); ?>"
			data-wp-class--wcb-open="state.panelOpen"
		>
			<button
				type="button"
				class="wcb-panel-close"
				data-wp-on--click="actions.closePanel"
				aria-label="<?php esc_attr_e( 'Close panel', 'wp-career-board' ); ?>"
			>&times;</button>

			<div class="wcb-panel-body">
				<h2 class="wcb-panel-title"><?php esc_html_e( 'Apply for this job', 'wp-career-board' ); ?></h2>
				<p class="wcb-panel-subtitle"><?php echo esc_html( $wcb_job->post_title ); ?></p>
				<?php if ( $wcb_company_name ) : ?>
					<p class="wcb-panel-company"><?php echo esc_html( $wcb_company_name ); ?></p>
				<?php endif; ?>

				<p class="wcb-apply-error" data-wp-class--wcb-shown="state.error" data-wp-text="state.error"></p>

				<label class="wcb-field-label" for="wcb-cover-letter">
					<?php esc_html_e( 'Cover Letter', 'wp-career-board' ); ?>
					<span class="wcb-field-hint"><?php esc_html_e( '(optional)', 'wp-career-board' ); ?></span>
				</label>
				<textarea
					id="wcb-cover-letter"
					class="wcb-cover-letter"
					rows="8"
					placeholder="<?php esc_attr_e( 'Tell the employer why you are a great fit for this role…', 'wp-career-board' ); ?>"
					data-wp-bind--value="state.coverLetter"
					data-wp-on--input="actions.updateCoverLetter"
				></textarea>

				<button
					type="button"
					class="wcb-btn wcb-btn--primary wcb-btn--full"
					data-wp-on--click="actions.submitApplication"
					data-wp-bind--disabled="state.submitting"
				>
					<span data-wp-class--wcb-hidden="state.submitting"><?php esc_html_e( 'Submit Application', 'wp-career-board' ); ?></span>
					<span class="wcb-submitting-label" data-wp-class--wcb-shown="state.submitting"><?php esc_html_e( 'Submitting…', 'wp-career-board' ); ?></span>
				</button>
			</div>
		</div>
	<?php endif; ?>
</div>
