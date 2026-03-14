<?php
/**
 * Block render: wcb/job-single — full job detail with slide-in apply panel.
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

$wcb_job_id = get_queried_object_id();
$wcb_job    = $wcb_job_id ? get_post( $wcb_job_id ) : null;

if ( ! $wcb_job || 'wcb_job' !== $wcb_job->post_type ) {
	return;
}

$wcb_location_terms = wp_get_object_terms( $wcb_job_id, 'wcb_location', array( 'fields' => 'names' ) );
$wcb_type_terms     = wp_get_object_terms( $wcb_job_id, 'wcb_job_type', array( 'fields' => 'names' ) );

$wcb_settings   = (array) get_option( 'wcb_settings', array() );
$wcb_currency   = isset( $wcb_settings['salary_currency'] ) ? $wcb_settings['salary_currency'] : '$';
$wcb_company    = (string) get_post_meta( $wcb_job_id, '_wcb_company_name', true );
$wcb_location   = is_wp_error( $wcb_location_terms ) ? '' : implode( ', ', $wcb_location_terms );
$wcb_type       = is_wp_error( $wcb_type_terms ) ? '' : implode( ', ', $wcb_type_terms );
$wcb_remote     = '1' === get_post_meta( $wcb_job_id, '_wcb_remote', true );
$wcb_salary_min = (string) get_post_meta( $wcb_job_id, '_wcb_salary_min', true );
$wcb_salary_max = (string) get_post_meta( $wcb_job_id, '_wcb_salary_max', true );
$wcb_deadline   = (string) get_post_meta( $wcb_job_id, '_wcb_deadline', true );
$wcb_has_apply  = function_exists( 'wp_is_ability_granted' )
	? wp_is_ability_granted( 'wcb_apply_jobs' )
	: current_user_can( 'wcb_apply_jobs' );
$wcb_can_apply  = is_user_logged_in() && $wcb_has_apply;

wp_interactivity_state(
	'wcb-job-single',
	array(
		'jobId'       => $wcb_job_id,
		'apiBase'     => rest_url( 'wcb/v1' ),
		'nonce'       => wp_create_nonce( 'wp_rest' ),
		'panelOpen'   => false,
		'submitting'  => false,
		'submitted'   => false,
		'coverLetter' => '',
		'error'       => '',
	)
);
?>
<div
	<?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-job-single"
>
	<header class="wcb-job-header">
		<h1 class="wcb-job-title"><?php echo esc_html( $wcb_job->post_title ); ?></h1>

		<?php if ( $wcb_company ) : ?>
			<p class="wcb-job-company"><?php echo esc_html( $wcb_company ); ?></p>
		<?php endif; ?>

		<ul class="wcb-job-meta-list">
			<?php if ( $wcb_location ) : ?>
				<li class="wcb-job-meta-item"><?php echo esc_html( $wcb_location ); ?></li>
			<?php endif; ?>
			<?php if ( $wcb_type ) : ?>
				<li class="wcb-job-meta-item"><?php echo esc_html( $wcb_type ); ?></li>
			<?php endif; ?>
			<?php if ( $wcb_remote ) : ?>
				<li class="wcb-job-meta-item wcb-remote-badge"><?php esc_html_e( 'Remote', 'wp-career-board' ); ?></li>
			<?php endif; ?>
			<?php if ( $wcb_salary_min || $wcb_salary_max ) : ?>
				<li class="wcb-job-meta-item">
					<?php
					if ( $wcb_salary_min && $wcb_salary_max ) {
						echo esc_html( $wcb_currency . $wcb_salary_min . ' – ' . $wcb_currency . $wcb_salary_max );
					} elseif ( $wcb_salary_min ) {
						echo esc_html( $wcb_currency . $wcb_salary_min . '+' );
					} else {
						/* translators: %s: maximum salary with currency prefix */
						printf( esc_html__( 'Up to %s', 'wp-career-board' ), esc_html( $wcb_currency . $wcb_salary_max ) );
					}
					?>
				</li>
			<?php endif; ?>
			<?php if ( $wcb_deadline ) : ?>
				<li class="wcb-job-meta-item">
					<?php
					/* translators: %s: application deadline date */
					printf( esc_html__( 'Apply by %s', 'wp-career-board' ), esc_html( $wcb_deadline ) );
					?>
				</li>
			<?php endif; ?>
		</ul>

		<?php if ( $wcb_can_apply ) : ?>
			<button
				type="button"
				class="wcb-apply-btn"
				data-wp-on--click="actions.openPanel"
				data-wp-show="!state.submitted"
			>
				<?php esc_html_e( 'Apply Now', 'wp-career-board' ); ?>
			</button>
			<p class="wcb-apply-success" data-wp-show="state.submitted">
				<?php esc_html_e( 'Application submitted! We\'ll be in touch.', 'wp-career-board' ); ?>
			</p>
		<?php elseif ( ! is_user_logged_in() ) : ?>
			<p class="wcb-login-prompt">
				<?php esc_html_e( 'Please log in to apply for this job.', 'wp-career-board' ); ?>
			</p>
		<?php endif; ?>
	</header>

	<div class="wcb-job-description">
		<?php echo wp_kses_post( apply_filters( 'the_content', $wcb_job->post_content ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>
	</div>

	<?php if ( $wcb_can_apply ) : ?>
		<!-- Apply panel overlay -->
		<div class="wcb-apply-panel-overlay" data-wp-show="state.panelOpen" data-wp-on--click="actions.closePanel"></div>

		<div class="wcb-apply-panel" data-wp-show="state.panelOpen" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Apply for this job', 'wp-career-board' ); ?>">
			<button type="button" class="wcb-panel-close" data-wp-on--click="actions.closePanel" aria-label="<?php esc_attr_e( 'Close', 'wp-career-board' ); ?>">
				&times;
			</button>

			<h2><?php esc_html_e( 'Apply for this job', 'wp-career-board' ); ?></h2>

			<p class="wcb-apply-error" data-wp-show="state.error" data-wp-text="state.error"></p>

			<label for="wcb-cover-letter"><?php esc_html_e( 'Cover Letter', 'wp-career-board' ); ?></label>
			<textarea
				id="wcb-cover-letter"
				class="wcb-cover-letter"
				rows="8"
				placeholder="<?php esc_attr_e( 'Tell the employer why you are a great fit…', 'wp-career-board' ); ?>"
				data-wp-bind--value="state.coverLetter"
				data-wp-on--input="actions.updateCoverLetter"
			></textarea>

			<button
				type="button"
				class="wcb-submit-btn"
				data-wp-on--click="actions.submitApplication"
				data-wp-bind--disabled="state.submitting"
			>
				<span data-wp-show="!state.submitting"><?php esc_html_e( 'Submit Application', 'wp-career-board' ); ?></span>
				<span data-wp-show="state.submitting"><?php esc_html_e( 'Submitting…', 'wp-career-board' ); ?></span>
			</button>
		</div>
	<?php endif; ?>
</div>
