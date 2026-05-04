<?php
/**
 * Block render: wcb/job-single — enterprise-grade job detail with apply panel.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// Resolve job: explicit `jobId` attribute (page builders, shortcode) wins
// over the queried-object context (canonical single-job CPT template).
// Lets the block + [wcb_job_single jobId="..."] shortcode render outside
// the single-job archive — Elementor / Bricks / classic editor / a manually
// composed page in Gutenberg.
$wcb_job_id = (int) ( $attributes['jobId'] ?? 0 );
if ( ! $wcb_job_id ) {
	$wcb_job_id = get_queried_object_id();
}
$wcb_job = $wcb_job_id ? get_post( $wcb_job_id ) : null;

if ( ! $wcb_job || 'wcb_job' !== $wcb_job->post_type ) {
	return;
}

// ── Taxonomies — full term objects for link generation ───────────────────────
$wcb_location_terms   = wp_get_object_terms( $wcb_job_id, 'wcb_location' );
$wcb_type_terms       = wp_get_object_terms( $wcb_job_id, 'wcb_job_type' );
$wcb_experience_terms = wp_get_object_terms( $wcb_job_id, 'wcb_experience' );
$wcb_category_terms   = wp_get_object_terms( $wcb_job_id, 'wcb_category' );
$wcb_tag_terms        = wp_get_object_terms( $wcb_job_id, 'wcb_tag' );

// Plain-text strings used in sidebar detail list.
$wcb_location   = ! is_wp_error( $wcb_location_terms ) ? implode( ', ', wp_list_pluck( $wcb_location_terms, 'name' ) ) : '';
$wcb_type       = ! is_wp_error( $wcb_type_terms ) ? implode( ' · ', wp_list_pluck( $wcb_type_terms, 'name' ) ) : '';
$wcb_experience = ! is_wp_error( $wcb_experience_terms ) ? implode( ', ', wp_list_pluck( $wcb_experience_terms, 'name' ) ) : '';
$wcb_categories = ! is_wp_error( $wcb_category_terms ) ? $wcb_category_terms : array();
$wcb_tags       = ! is_wp_error( $wcb_tag_terms ) ? $wcb_tag_terms : array();

// Normalize to arrays for safe iteration.
$wcb_location_terms   = is_wp_error( $wcb_location_terms ) ? array() : $wcb_location_terms;
$wcb_type_terms       = is_wp_error( $wcb_type_terms ) ? array() : $wcb_type_terms;
$wcb_experience_terms = is_wp_error( $wcb_experience_terms ) ? array() : $wcb_experience_terms;

// ── Job meta ─────────────────────────────────────────────────────────────────
$wcb_currency_code_raw = (string) get_post_meta( $wcb_job_id, '_wcb_salary_currency', true );
$wcb_currency_code     = '' !== $wcb_currency_code_raw ? $wcb_currency_code_raw : 'USD';
$wcb_symbol_map        = array(
	'USD' => '$',
	'EUR' => '€',
	'GBP' => '£',
	'CAD' => 'CA$',
	'AUD' => 'A$',
	'INR' => '₹',
	'SGD' => 'S$',
);
$wcb_currency          = isset( $wcb_symbol_map[ $wcb_currency_code ] ) ? $wcb_symbol_map[ $wcb_currency_code ] : $wcb_currency_code . ' ';
$wcb_remote            = '1' === (string) get_post_meta( $wcb_job_id, '_wcb_remote', true );
$wcb_salary_min        = (string) get_post_meta( $wcb_job_id, '_wcb_salary_min', true );
$wcb_salary_max        = (string) get_post_meta( $wcb_job_id, '_wcb_salary_max', true );
$wcb_salary_type_raw   = (string) get_post_meta( $wcb_job_id, '_wcb_salary_type', true );
$wcb_salary_type       = in_array( $wcb_salary_type_raw, array( 'yearly', 'monthly', 'hourly' ), true ) ? $wcb_salary_type_raw : 'yearly';
$wcb_salary_suffix     = match ( $wcb_salary_type ) {
	'monthly' => '/' . esc_html__( 'mo', 'wp-career-board' ),
	'hourly'  => '/' . esc_html__( 'hr', 'wp-career-board' ),
	default   => '/' . esc_html__( 'yr', 'wp-career-board' ),
};
$wcb_deadline           = (string) get_post_meta( $wcb_job_id, '_wcb_deadline', true );
$wcb_deadline_formatted = $wcb_deadline ? date_i18n( get_option( 'date_format' ), (int) strtotime( $wcb_deadline ) ) : '';
$wcb_featured           = '1' === (string) get_post_meta( $wcb_job_id, '_wcb_featured', true );

// ── Salary display ────────────────────────────────────────────────────────────
$wcb_salary_str = '';
if ( $wcb_salary_min && $wcb_salary_max ) {
	$wcb_salary_str = $wcb_currency . number_format( (int) $wcb_salary_min ) . ' – ' . $wcb_currency . number_format( (int) $wcb_salary_max ) . $wcb_salary_suffix;
} elseif ( $wcb_salary_min ) {
	$wcb_salary_str = $wcb_currency . number_format( (int) $wcb_salary_min ) . '+' . $wcb_salary_suffix;
} elseif ( $wcb_salary_max ) {
	/* translators: %s: maximum salary with period suffix */
	$wcb_salary_str = sprintf( __( 'Up to %s', 'wp-career-board' ), $wcb_currency . number_format( (int) $wcb_salary_max ) . $wcb_salary_suffix );
}

// ── Company ───────────────────────────────────────────────────────────────────
$wcb_company_name = (string) get_post_meta( $wcb_job_id, '_wcb_company_name', true );
// Prefer the company ID stored on the job post (set at submission time); fall back to the employer's user meta.
$wcb_company_id = (int) get_post_meta( $wcb_job_id, '_wcb_company_id', true );
if ( ! $wcb_company_id ) {
	$wcb_author_id  = (int) $wcb_job->post_author;
	$wcb_company_id = (int) get_user_meta( $wcb_author_id, '_wcb_company_id', true );
}
$wcb_company_post = $wcb_company_id ? get_post( $wcb_company_id ) : null;

if ( ! $wcb_company_name && $wcb_company_post instanceof \WP_Post ) {
	$wcb_company_name = $wcb_company_post->post_title;
}

$wcb_company_url   = ( $wcb_company_post instanceof \WP_Post ) ? (string) get_permalink( $wcb_company_id ) : '';
$wcb_company_desc  = $wcb_company_post instanceof \WP_Post ? wp_trim_words( $wcb_company_post->post_content, 40 ) : '';
$wcb_company_site  = $wcb_company_id ? (string) get_post_meta( $wcb_company_id, '_wcb_website', true ) : '';
$wcb_company_trust = $wcb_company_id ? sanitize_key( (string) get_post_meta( $wcb_company_id, '_wcb_trust_level', true ) ) : '';
$wcb_trust_map     = array(
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
$wcb_trust_info    = $wcb_trust_map[ $wcb_company_trust ] ?? null;

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

// Guests may always apply — the endpoint accepts unauthenticated submissions.
$wcb_show_apply = $wcb_can_apply || ! is_user_logged_in();

// ── Job owner check — employers see "View Applications" instead of "Apply Now" ─
$wcb_is_job_owner = is_user_logged_in()
	&& ( get_current_user_id() === (int) $wcb_job->post_author
		|| ( function_exists( 'wp_is_ability_granted' ) && wp_is_ability_granted( 'wcb_manage_settings' ) ) );

if ( $wcb_is_job_owner ) {
	$wcb_show_apply = false;
}

// Suppress Apply Now for any employer — they post jobs, not apply to them.
if ( $wcb_show_apply && is_user_logged_in() ) {
	// phpcs:disable WordPress.WP.Capabilities.Unknown -- wcb_post_jobs is a registered custom capability.
	$wcb_is_employer_user = ( function_exists( 'wp_is_ability_granted' ) && wp_is_ability_granted( 'wcb_post_jobs' ) )
	|| current_user_can( 'wcb_post_jobs' );
	// phpcs:enable WordPress.WP.Capabilities.Unknown
	if ( $wcb_is_employer_user ) {
		$wcb_show_apply = false;
	}
}

$wcb_dashboard_url = '';
if ( $wcb_is_job_owner ) {
	$wcb_js_settings = (array) get_option( 'wcb_settings', array() );
	if ( ! empty( $wcb_js_settings['employer_dashboard_page'] ) ) {
		$wcb_dashboard_url = (string) get_permalink( (int) $wcb_js_settings['employer_dashboard_page'] );
	}
}

// ── Already-applied check ─────────────────────────────────────────────────────
$wcb_current_user_id = get_current_user_id();
$wcb_has_applied     = false;
if ( $wcb_current_user_id && $wcb_show_apply ) {
	$wcb_has_applied = (bool) get_posts(
		array(
			'post_type'      => 'wcb_application',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => '_wcb_job_id',
						'value' => $wcb_job_id,
					),
					array(
						'key'   => '_wcb_candidate_id',
						'value' => $wcb_current_user_id,
					),
			),
		)
	);
}

// ── Bookmark state ────────────────────────────────────────────────────────────
$wcb_bookmarks     = $wcb_current_user_id
	? array_map( 'intval', (array) get_user_meta( $wcb_current_user_id, '_wcb_bookmark', false ) )
	: array();
$wcb_is_bookmarked = in_array( $wcb_job_id, $wcb_bookmarks, true );

// ── Resume data for apply panel ───────────────────────────────────────────────
$wcb_user_resumes            = array();
$wcb_resume_page_url         = '';
$wcb_career_board_pro_active = (bool) apply_filters( 'wcb_pro_active', false );
if ( post_type_exists( 'wcb_resume' ) ) {
	$wcb_resume_posts = get_posts(
		array(
			'post_type'      => 'wcb_resume',
			'post_status'    => 'publish',
			'author'         => $wcb_current_user_id,
			'posts_per_page' => 20,
			'no_found_rows'  => true,
		)
	);
	foreach ( $wcb_resume_posts as $wcb_r ) {
		$wcb_user_resumes[] = array(
			'id'    => $wcb_r->ID,
			'title' => $wcb_r->post_title,
		);
	}
	$wcb_rb_pages = get_posts(
		array(
			'post_type'      => 'page',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			's'              => 'wcb/resume-builder',
			'post_status'    => 'publish',
		)
	);
	if ( $wcb_rb_pages ) {
		$wcb_resume_page_url = get_permalink( $wcb_rb_pages[0]->ID );
	}
}

$wcb_settings_arr = (array) get_option( 'wcb_settings', array() );
// Mirror the server-side default in ApplicationsEndpoint::resume_required():
// resume is required out-of-the-box; only an explicit "off" disables it.
$wcb_resume_required = array_key_exists( 'apply_resume_required', $wcb_settings_arr )
	? ! empty( $wcb_settings_arr['apply_resume_required'] )
	: true;
$wcb_resume_max_mb   = isset( $wcb_settings_arr['apply_resume_max_mb'] )
	? max( 1, min( 20, (int) $wcb_settings_arr['apply_resume_max_mb'] ) )
	: 5;

wp_interactivity_state(
	'wcb-job-single',
	array(
		'jobId'                => $wcb_job_id,
		'apiBase'              => untrailingslashit( rest_url( 'wcb/v1' ) ),
		'nonce'                => wp_create_nonce( 'wp_rest' ),
		'panelOpen'            => false,
		'submitting'           => false,
		'submitted'            => $wcb_has_applied,
		'bookmarked'           => $wcb_is_bookmarked,
		'bookmarking'          => false,
		'coverLetter'          => '',
		'error'                => '',
		'userResumes'          => $wcb_user_resumes,
		'selectedResumeId'     => 0,
		'resumePageUrl'        => $wcb_resume_page_url,
		'proActive'            => post_type_exists( 'wcb_resume' ),
		'careerBoardProActive' => $wcb_career_board_pro_active,
		'jobPermalink'         => (string) get_permalink( $wcb_job_id ),
		'jobTitle'             => $wcb_job->post_title,
		'linkCopied'           => false,
		'isLoggedIn'           => is_user_logged_in(),
		'guestName'            => '',
		'guestEmail'           => '',
		'resumeFileName'       => '',
		'resumeRequired'       => $wcb_resume_required,
		'resumeMaxMb'          => $wcb_resume_max_mb,
		'alertFromJobSaved'    => false,
		'alertFromJobSaving'   => false,
		'jobCategories'        => (array) wp_get_object_terms( $wcb_job_id, 'wcb_category', array( 'fields' => 'slugs' ) ),
		'jobTypes'             => (array) wp_get_object_terms( $wcb_job_id, 'wcb_job_type', array( 'fields' => 'slugs' ) ),
		'jobRemote'            => (bool) get_post_meta( $wcb_job_id, '_wcb_remote', true ),
		'strings'              => array(
			'bookmarkSaved'       => __( 'Saved', 'wp-career-board' ),
			'bookmarkSave'        => __( 'Save Job', 'wp-career-board' ),
			'guestFieldsRequired' => __( 'Please enter your name and email to apply.', 'wp-career-board' ),
			'resumeUploadFailed'  => __( 'Resume upload failed. Please try again.', 'wp-career-board' ),
			'resumeRequiredError' => __( 'Please attach your resume to apply.', 'wp-career-board' ),
			'applicationFailed'   => __( 'Application could not be submitted. Please try again.', 'wp-career-board' ),
			'connectionError'     => __( 'Connection error. Please check your network and try again.', 'wp-career-board' ),
		),
	)
);
?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-job-single' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
					<?php if ( $wcb_trust_info ) : ?>
							<span class="wcb-verified-badge" data-trust="<?php echo esc_attr( $wcb_company_trust ); ?>">
						<?php echo esc_html( $wcb_trust_info['icon'] . ' ' . $wcb_trust_info['label'] ); ?>
							</span>
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
			<?php if ( $wcb_is_job_owner && $wcb_dashboard_url ) : ?>
				<a
					href="<?php echo esc_url( add_query_arg( 'job_apps', $wcb_job_id, $wcb_dashboard_url ) ); ?>"
					class="wcb-btn wcb-btn--primary"
				>
				<?php esc_html_e( 'View Applications', 'wp-career-board' ); ?>
				</a>
			<?php elseif ( $wcb_show_apply ) : ?>
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
				<?php if ( apply_filters( 'wcb_pro_alerts_enabled', false ) ) : ?>
				<div class="wcb-post-apply-alert" style="display:none" data-wp-class--wcb-shown="state.submitted" data-wp-class--wcb-alert-done="state.alertFromJobSaved">
					<button
						type="button"
						class="wcb-post-apply-alert-btn"
						data-wp-on--click="actions.createAlertFromJob"
						data-wp-bind--disabled="state.alertFromJobSaving"
						data-wp-class--wcb-hidden="state.alertFromJobSaved"
					>&#128276; <?php esc_html_e( 'Get notified about similar jobs', 'wp-career-board' ); ?></button>
					<span class="wcb-post-apply-alert-done" style="display:none" data-wp-class--wcb-shown="state.alertFromJobSaved">
					<?php esc_html_e( '✓ You will be notified about similar jobs', 'wp-career-board' ); ?>
					</span>
				</div>
				<?php endif; ?>
				<?php if ( $wcb_deadline_formatted ) : ?>
					<p class="wcb-deadline-note">
					<?php
					/* translators: %s: deadline date */
					printf( esc_html__( 'Apply by %s', 'wp-career-board' ), esc_html( $wcb_deadline_formatted ) );
					?>
					</p>
				<?php endif; ?>
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
					<i data-lucide="bookmark" aria-hidden="true"></i>
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
					<?php
					// Convert common markdown patterns (bold, italic, headings, lists) that users
					// naturally type in the frontend textarea to HTML before rendering.
					$wcb_job_desc     = (string) $wcb_job->post_content;
					$wcb_job_desc     = (string) preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $wcb_job_desc );
					$wcb_job_desc     = (string) preg_replace( '/(?<!\*)\*(?![\*\s])(.+?)(?<!\s)\*(?!\*)/m', '<em>$1</em>', $wcb_job_desc );
					$wcb_job_desc     = (string) preg_replace( '/^## (.+)$/m', '<h4>$1</h4>', $wcb_job_desc );
					$wcb_job_desc     = (string) preg_replace( '/^# (.+)$/m', '<h3>$1</h3>', $wcb_job_desc );
					$wcb_desc_lines   = explode( "\n", $wcb_job_desc );
					$wcb_desc_out     = array();
					$wcb_desc_in_list = false;
					foreach ( $wcb_desc_lines as $wcb_desc_line ) {
						if ( preg_match( '/^[*-] (.+)$/', $wcb_desc_line, $wcb_desc_m ) ) {
							if ( ! $wcb_desc_in_list ) {
										$wcb_desc_out[]   = '<ul>';
										$wcb_desc_in_list = true;
							}
							$wcb_desc_out[] = '<li>' . $wcb_desc_m[1] . '</li>';
						} else {
							if ( $wcb_desc_in_list ) {
									$wcb_desc_out[]   = '</ul>';
									$wcb_desc_in_list = false;
							}
							$wcb_desc_out[] = $wcb_desc_line;
						}
					}
					if ( $wcb_desc_in_list ) {
						$wcb_desc_out[] = '</ul>';
					}
					$wcb_job_desc = implode( "\n", $wcb_desc_out );
					echo wp_kses_post( apply_filters( 'the_content', $wcb_job_desc ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
					?>
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
			<?php if ( ! empty( $wcb_tags ) ) : ?>
				<div class="wcb-section">
					<h3 class="wcb-section-heading-sm"><?php esc_html_e( 'Skills & Tags', 'wp-career-board' ); ?></h3>
					<div class="wcb-tag-row">
				<?php foreach ( $wcb_tags as $wcb_tag_item ) : ?>
							<a href="<?php echo esc_url( (string) get_term_link( $wcb_tag_item ) ); ?>" class="wcb-tag">
					<?php echo esc_html( $wcb_tag_item->name ); ?>
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

					<?php if ( $wcb_deadline_formatted ) : ?>
						<div class="wcb-detail-row">
							<dt><?php esc_html_e( 'Apply By', 'wp-career-board' ); ?></dt>
							<dd><?php echo esc_html( $wcb_deadline_formatted ); ?></dd>
						</div>
					<?php endif; ?>
				</dl>

				<?php if ( $wcb_is_job_owner && $wcb_dashboard_url ) : ?>
					<a
						href="<?php echo esc_url( add_query_arg( 'job_apps', $wcb_job_id, $wcb_dashboard_url ) ); ?>"
						class="wcb-btn wcb-btn--primary wcb-btn--full"
					>
					<?php esc_html_e( 'View Applications', 'wp-career-board' ); ?>
					</a>
				<?php elseif ( $wcb_show_apply ) : ?>
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
				<?php endif; ?>
			</div>

			<?php /* Share bar */ ?>
			<?php
			$wcb_share_url    = rawurlencode( (string) get_permalink( $wcb_job_id ) );
			$wcb_share_title  = rawurlencode( $wcb_job->post_title );
			$wcb_twitter_url  = 'https://x.com/intent/tweet?text=' . $wcb_share_title . '&url=' . $wcb_share_url;
			$wcb_linkedin_url = 'https://www.linkedin.com/sharing/share-offsite/?url=' . $wcb_share_url;
			?>
			<div class="wcb-share-bar">
				<span class="wcb-share-label"><?php esc_html_e( 'Share:', 'wp-career-board' ); ?></span>
				<a
					href="<?php echo esc_url( $wcb_twitter_url ); ?>"
					class="wcb-share-btn"
					target="_blank"
					rel="noopener noreferrer"
					aria-label="<?php esc_attr_e( 'Share on X', 'wp-career-board' ); ?>"
				>
					<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.739l7.73-8.835L1.254 2.25H8.08l4.259 5.63 5.905-5.63zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
				</a>
				<a
					href="<?php echo esc_url( $wcb_linkedin_url ); ?>"
					class="wcb-share-btn"
					target="_blank"
					rel="noopener noreferrer"
					aria-label="<?php esc_attr_e( 'Share on LinkedIn', 'wp-career-board' ); ?>"
				>
					<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg>
				</a>
				<button
					type="button"
					class="wcb-share-btn"
					data-wp-on--click="actions.copyLink"
					aria-label="<?php esc_attr_e( 'Copy link', 'wp-career-board' ); ?>"
				>
					<span data-wp-bind--hidden="state.linkCopied">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
					</span>
					<span data-wp-bind--hidden="!state.linkCopied" hidden>
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
					</span>
				</button>
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
				<?php if ( $wcb_trust_info ) : ?>
								<span class="wcb-verified-badge" data-trust="<?php echo esc_attr( $wcb_company_trust ); ?>">
					<?php echo esc_html( $wcb_trust_info['icon'] . ' ' . $wcb_trust_info['label'] ); ?>
								</span>
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
	<?php if ( $wcb_show_apply ) : ?>
		<div
			class="wcb-panel-overlay"
			data-wp-class--wcb-open="state.panelOpen"
			data-wp-on--click="actions.closePanel"
		></div>

		<div
			class="wcb-apply-panel"
			role="dialog"
			aria-modal="true"
			aria-labelledby="wcb-apply-title"
			data-wp-class--wcb-open="state.panelOpen"
			data-wp-on--keydown="actions.handlePanelKeydown"
		>
			<button
				type="button"
				class="wcb-panel-close"
				data-wp-on--click="actions.closePanel"
				aria-label="<?php esc_attr_e( 'Close application panel', 'wp-career-board' ); ?>"
			>&times;</button>

			<div class="wcb-panel-body">
				<h2 id="wcb-apply-title" class="wcb-panel-title"><?php esc_html_e( 'Apply for this job', 'wp-career-board' ); ?></h2>
				<p class="wcb-panel-subtitle"><?php echo esc_html( $wcb_job->post_title ); ?></p>
		<?php if ( $wcb_company_name ) : ?>
					<p class="wcb-panel-company"><?php echo esc_html( $wcb_company_name ); ?></p>
		<?php endif; ?>

				<p class="wcb-apply-error" data-wp-class--wcb-shown="state.error" data-wp-text="state.error"></p>

		<?php if ( ! is_user_logged_in() ) : ?>
					<div class="wcb-apply-guest-fields">
						<label class="wcb-field-label" for="wcb-guest-name">
			<?php esc_html_e( 'Your Name', 'wp-career-board' ); ?>
							<span class="wcb-field-required" aria-hidden="true">*</span>
						</label>
						<input
							type="text"
							id="wcb-guest-name"
							class="wcb-guest-field"
							autocomplete="name"
							required
							data-wp-on--input="actions.updateGuestName"
						/>
						<label class="wcb-field-label" for="wcb-guest-email">
			<?php esc_html_e( 'Your Email', 'wp-career-board' ); ?>
							<span class="wcb-field-required" aria-hidden="true">*</span>
						</label>
						<input
							type="email"
							id="wcb-guest-email"
							class="wcb-guest-field"
							autocomplete="email"
							required
							data-wp-on--input="actions.updateGuestEmail"
						/>
					</div>
		<?php endif; ?>

		<?php $wcb_show_pro_picker = is_user_logged_in() && post_type_exists( 'wcb_resume' ); ?>

				<div class="wcb-apply-resume-section">
		<?php if ( $wcb_show_pro_picker ) : ?>
						<label class="wcb-field-label" for="wcb-resume-select">
			<?php esc_html_e( 'Select Resume', 'wp-career-board' ); ?>
						</label>
			<?php if ( ! empty( $wcb_user_resumes ) ) : ?>
							<select
								id="wcb-resume-select"
								class="wcb-apply-resume-select"
								data-wp-on--change="actions.selectResume"
							>
								<option value="0"><?php esc_html_e( '— Select a resume —', 'wp-career-board' ); ?></option>
				<?php foreach ( $wcb_user_resumes as $wcb_r ) : ?>
									<option value="<?php echo (int) $wcb_r['id']; ?>">
					<?php echo esc_html( $wcb_r['title'] ); ?>
									</option>
				<?php endforeach; ?>
							</select>
						<?php else : ?>
							<p class="wcb-apply-no-resume">
								<span data-wp-class--wcb-hidden="state.resumeFileName">
							<?php esc_html_e( 'No resume found.', 'wp-career-board' ); ?>
								</span>
							<?php if ( $wcb_resume_page_url && $wcb_career_board_pro_active ) : ?>
									<a href="<?php echo esc_url( $wcb_resume_page_url ); ?>">
								<?php esc_html_e( 'Create your resume →', 'wp-career-board' ); ?>
									</a>
							<?php endif; ?>
							</p>
						<?php endif; ?>

						<p class="wcb-apply-or-divider"><?php esc_html_e( '— or upload a file —', 'wp-career-board' ); ?></p>
					<?php else : ?>
						<label class="wcb-field-label" for="wcb-resume-file">
						<?php esc_html_e( 'Resume', 'wp-career-board' ); ?>
						<?php if ( $wcb_resume_required ) : ?>
								<span class="wcb-field-required" aria-hidden="true">*</span>
							<?php else : ?>
								<span class="wcb-field-hint"><?php esc_html_e( '(optional)', 'wp-career-board' ); ?></span>
							<?php endif; ?>
						</label>
					<?php endif; ?>

					<label class="wcb-upload-zone" for="wcb-resume-file" data-wp-class--wcb-has-file="state.resumeFileName">
						<span class="wcb-upload-icon">&#8593;</span>
						<span class="wcb-upload-text"><?php esc_html_e( 'Click to upload resume', 'wp-career-board' ); ?></span>
						<span class="wcb-upload-hint">
		<?php
		/* translators: %d: max upload size in MB */
		printf( esc_html__( 'PDF, DOC or DOCX — max %d MB', 'wp-career-board' ), absint( $wcb_resume_max_mb ) );
		?>
						</span>
						<span class="wcb-upload-filename" data-wp-text="state.resumeFileName"></span>
						<input
							type="file"
							id="wcb-resume-file"
							class="wcb-apply-resume-file"
							accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
							data-wp-on--change="actions.selectResumeFile"
		<?php echo $wcb_resume_required ? 'required' : ''; ?>
						/>
					</label>
				</div>

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

		<?php
		/**
		 * Filter: declarative custom field groups for the apply form.
		 *
		 * Mirrors wcb_job_form_fields, wcb_company_form_fields,
		 * wcb_candidate_form_fields, wcb_resume_form_fields. Returns an
		 * array of groups, each with `id`, `label`, and `fields` —
		 * see docs/HOOKS.md for the schema.
		 *
		 *   add_filter( 'wcb_application_form_fields_groups',
		 *     function( $groups, $job_id ) {
		 *         $groups[] = array(
		 *             'id'     => 'screening',
		 *             'label'  => __( 'Screening', 'my-theme' ),
		 *             'fields' => array(
		 *                 array(
		 *                     'key'      => 'phone',
		 *                     'label'    => __( 'Phone', 'my-theme' ),
		 *                     'type'     => 'tel',
		 *                     'required' => true,
		 *                 ),
		 *             ),
		 *         );
		 *         return $groups;
		 *     }, 10, 2
		 *   );
		 *
		 * @since 1.1.0
		 *
		 * @param array<int,array<string,mixed>> $groups Empty by default.
		 * @param int                            $job_id Job being applied to.
		 */
		$wcb_app_field_groups = (array) apply_filters( 'wcb_application_form_fields_groups', array(), $wcb_job_id );
		if ( ! empty( $wcb_app_field_groups ) ) {
			echo '<div class="wcb-apply-custom-fields" data-wp-context="' . esc_attr(
				(string) wp_json_encode( array( 'fieldGroups' => $wcb_app_field_groups ) )
			) . '">';
			foreach ( $wcb_app_field_groups as $wcb_group ) {
				if ( ! is_array( $wcb_group ) || empty( $wcb_group['fields'] ) ) {
					continue;
				}
				if ( ! empty( $wcb_group['label'] ) ) {
					echo '<h3 class="wcb-apply-custom-fields__heading">' . esc_html( (string) $wcb_group['label'] ) . '</h3>';
				}
				foreach ( (array) $wcb_group['fields'] as $wcb_field ) {
					if ( ! is_array( $wcb_field ) || empty( $wcb_field['key'] ) || empty( $wcb_field['type'] ) ) {
						continue;
					}
					$wcb_key = sanitize_key( (string) $wcb_field['key'] );
					$wcb_id  = 'wcb-apply-' . $wcb_key;
					echo '<div class="wcb-form-field">';
					if ( ! empty( $wcb_field['label'] ) ) {
						echo '<label class="wcb-field-label" for="' . esc_attr( $wcb_id ) . '">' . esc_html( (string) $wcb_field['label'] );
						if ( ! empty( $wcb_field['required'] ) ) {
							echo ' <span class="wcb-field-required" aria-hidden="true">*</span>';
						}
						echo '</label>';
					}
							$wcb_type        = (string) $wcb_field['type'];
							$wcb_required    = ! empty( $wcb_field['required'] ) ? ' required aria-required="true"' : '';
							$wcb_placeholder = isset( $wcb_field['placeholder'] ) ? ' placeholder="' . esc_attr( (string) $wcb_field['placeholder'] ) . '"' : '';
					if ( 'textarea' === $wcb_type ) {
							echo '<textarea id="' . esc_attr( $wcb_id ) . '" class="wcb-field" rows="4" data-wp-on--input="actions.updateCustomField" data-wcb-field="' . esc_attr( $wcb_key ) . '"' . $wcb_placeholder . $wcb_required . '></textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs already escaped.
					} elseif ( 'select' === $wcb_type && ! empty( $wcb_field['options'] ) && is_array( $wcb_field['options'] ) ) {
							echo '<select id="' . esc_attr( $wcb_id ) . '" class="wcb-field" data-wp-on--change="actions.updateCustomField" data-wcb-field="' . esc_attr( $wcb_key ) . '"' . $wcb_required . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						foreach ( $wcb_field['options'] as $wcb_val => $wcb_label ) {
							echo '<option value="' . esc_attr( (string) $wcb_val ) . '">' . esc_html( (string) $wcb_label ) . '</option>';
						}
						echo '</select>';
					} else {
						$wcb_input_type = in_array( $wcb_type, array( 'text', 'email', 'tel', 'url', 'number', 'date' ), true ) ? $wcb_type : 'text';
						echo '<input type="' . esc_attr( $wcb_input_type ) . '" id="' . esc_attr( $wcb_id ) . '" class="wcb-field" data-wp-on--input="actions.updateCustomField" data-wcb-field="' . esc_attr( $wcb_key ) . '"' . $wcb_placeholder . $wcb_required . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					if ( ! empty( $wcb_field['description'] ) ) {
						echo '<span class="wcb-field-hint">' . esc_html( (string) $wcb_field['description'] ) . '</span>';
					}
							echo '</div>';
				}
			}
			echo '</div>';
		}

		/**
		 * Action: legacy raw-HTML injection point — kept for back-compat.
		 *
		 * Prefer the wcb_application_form_fields_groups filter above for
		 * new integrations; this action stays so existing add-ons keep
		 * working without changes.
		 *
		 * @since 1.0.0
		 * @param int $wcb_job_id The job being applied to.
		 */
		do_action( 'wcb_application_form_fields', $wcb_job_id );
		?>

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

	<!-- ── Honeypot anti-spam (apply form) ──────────────────────────────── -->
	<div class="wcb-hp-wrap" aria-hidden="true">
		<label for="wcb-hp-apply"><?php esc_html_e( 'Leave this field blank', 'wp-career-board' ); ?></label>
		<input
			type="text"
			id="wcb-hp-apply"
			name="wcb_hp_apply"
			class="wcb-hp"
			tabindex="-1"
			autocomplete="off"
		/>
	</div>
</div>
