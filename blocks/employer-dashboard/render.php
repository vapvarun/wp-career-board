<?php
/**
 * Block render: wcb/employer-dashboard — tabbed employer interface.
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

$wcb_can_manage = function_exists( 'wp_is_ability_granted' )
	? wp_is_ability_granted( 'wcb_manage_company' )
	: current_user_can( 'wcb_manage_company' );

if ( ! is_user_logged_in() || ! $wcb_can_manage ) {
	echo '<p>' . esc_html__( 'You must be logged in as an employer to view this dashboard.', 'wp-career-board' ) . '</p>';
	return;
}

$wcb_employer_id  = get_current_user_id();
$wcb_company_name = (string) get_user_meta( $wcb_employer_id, '_wcb_company_name', true );
$wcb_company_desc = (string) get_user_meta( $wcb_employer_id, '_wcb_company_description', true );
$wcb_company_site = (string) get_user_meta( $wcb_employer_id, '_wcb_company_website', true );

wp_interactivity_state(
	'wcb-employer-dashboard',
	array(
		'tab'         => 'jobs',
		'jobs'        => array(),
		'loading'     => false,
		'error'       => '',
		'apiBase'     => rest_url( 'wcb/v1' ),
		'nonce'       => wp_create_nonce( 'wp_rest' ),
		'employerId'  => $wcb_employer_id,
		'companyName' => $wcb_company_name,
		'companyDesc' => $wcb_company_desc,
		'companySite' => $wcb_company_site,
		'saving'      => false,
		'saved'       => false,
	)
);
?>
<div
	<?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-employer-dashboard"
	data-wp-init="actions.init"
>
	<!-- Tab nav -->
	<nav class="wcb-dashboard-tabs" aria-label="<?php esc_attr_e( 'Employer dashboard sections', 'wp-career-board' ); ?>">
		<button
			type="button"
			class="wcb-tab-btn"
			data-wp-class--active="state.isTabJobs"
			data-wp-on--click="actions.switchToJobs"
		><?php esc_html_e( 'My Jobs', 'wp-career-board' ); ?></button>
		<button
			type="button"
			class="wcb-tab-btn"
			data-wp-class--active="state.isTabProfile"
			data-wp-on--click="actions.switchToProfile"
		><?php esc_html_e( 'Company Profile', 'wp-career-board' ); ?></button>
	</nav>

	<!-- Tab: My Jobs -->
	<div class="wcb-tab-panel" data-wp-show="state.isTabJobs">
		<div class="wcb-loading" data-wp-show="state.loading"><?php esc_html_e( 'Loading…', 'wp-career-board' ); ?></div>
		<p class="wcb-error" data-wp-show="state.error" data-wp-text="state.error"></p>

		<div data-wp-show="!state.loading">
			<template data-wp-each--job="state.jobs" data-wp-each-key="context.job.id">
				<div class="wcb-employer-job-card">
					<h3>
						<a data-wp-bind--href="context.job.permalink" data-wp-text="context.job.title"></a>
					</h3>
					<span class="wcb-job-status" data-wp-text="context.job.status"></span>
					<span class="wcb-job-apps" data-wp-text="context.job.appCount"></span>
				</div>
			</template>
		</div>
	</div>

	<!-- Tab: Company Profile -->
	<div class="wcb-tab-panel" data-wp-show="state.isTabProfile">
		<h2><?php esc_html_e( 'Company Profile', 'wp-career-board' ); ?></h2>

		<label for="wcb-company-name"><?php esc_html_e( 'Company Name', 'wp-career-board' ); ?></label>
		<input
			id="wcb-company-name"
			type="text"
			class="wcb-field"
			data-wcb-field="companyName"
			data-wp-bind--value="state.companyName"
			data-wp-on--input="actions.updateField"
		/>

		<label for="wcb-company-desc"><?php esc_html_e( 'Description', 'wp-career-board' ); ?></label>
		<textarea
			id="wcb-company-desc"
			class="wcb-field"
			rows="5"
			data-wcb-field="companyDesc"
			data-wp-on--input="actions.updateField"
		></textarea>

		<label for="wcb-company-site"><?php esc_html_e( 'Website', 'wp-career-board' ); ?></label>
		<input
			id="wcb-company-site"
			type="url"
			class="wcb-field"
			data-wcb-field="companySite"
			data-wp-bind--value="state.companySite"
			data-wp-on--input="actions.updateField"
		/>

		<p class="wcb-save-success" data-wp-show="state.saved"><?php esc_html_e( 'Profile saved.', 'wp-career-board' ); ?></p>
		<p class="wcb-error" data-wp-show="state.error" data-wp-text="state.error"></p>

		<button
			type="button"
			class="wcb-btn"
			data-wp-on--click="actions.saveProfile"
			data-wp-bind--disabled="state.saving"
		>
			<span data-wp-show="!state.saving"><?php esc_html_e( 'Save Profile', 'wp-career-board' ); ?></span>
			<span data-wp-show="state.saving"><?php esc_html_e( 'Saving…', 'wp-career-board' ); ?></span>
		</button>
	</div>
</div>
