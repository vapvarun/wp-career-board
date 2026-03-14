<?php
/**
 * Block render: wcb/company-profile — public company page with owner inline-edit.
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

$wcb_employer_id = (int) ( $attributes['employerId'] ?? 0 );
if ( ! $wcb_employer_id ) {
	$wcb_employer_id = (int) get_queried_object_id();
}

$wcb_employer = $wcb_employer_id ? get_userdata( $wcb_employer_id ) : false;

if ( ! $wcb_employer ) {
	return;
}

$wcb_company_name = (string) get_user_meta( $wcb_employer_id, '_wcb_company_name', true );
$wcb_company_desc = (string) get_user_meta( $wcb_employer_id, '_wcb_company_description', true );
$wcb_company_site = (string) get_user_meta( $wcb_employer_id, '_wcb_company_website', true );
$wcb_company_logo = (string) get_user_meta( $wcb_employer_id, '_wcb_company_logo', true );
$wcb_is_owner     = get_current_user_id() === $wcb_employer_id;

wp_interactivity_state(
	'wcb-company-profile',
	array(
		'employerId'  => $wcb_employer_id,
		'isOwner'     => $wcb_is_owner,
		'editing'     => false,
		'saving'      => false,
		'saved'       => false,
		'error'       => '',
		'companyName' => $wcb_company_name,
		'companyDesc' => $wcb_company_desc,
		'companySite' => $wcb_company_site,
		'jobs'        => array(),
		'loading'     => false,
		'apiBase'     => rest_url( 'wcb/v1' ),
		'nonce'       => wp_create_nonce( 'wp_rest' ),
	)
);
?>
<div
	<?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-company-profile"
	data-wp-init="actions.init"
>
	<!-- Logo -->
	<?php if ( $wcb_company_logo ) : ?>
		<img
			class="wcb-company-logo"
			src="<?php echo esc_url( $wcb_company_logo ); ?>"
			alt="<?php echo esc_attr( $wcb_company_name ); ?>"
		/>
	<?php endif; ?>

	<!-- Read view -->
	<div class="wcb-company-read" data-wp-show="!state.editing">
		<h1 class="wcb-company-name" data-wp-text="state.companyName"></h1>

		<a
			class="wcb-company-site"
			href="<?php echo esc_url( $wcb_company_site ); ?>"
			rel="noopener noreferrer"
			target="_blank"
			data-wp-show="state.companySite"
			data-wp-bind--href="state.companySite"
			data-wp-text="state.companySite"
		></a>

		<div class="wcb-company-desc" data-wp-text="state.companyDesc"></div>

		<?php if ( $wcb_is_owner ) : ?>
			<button type="button" class="wcb-btn" data-wp-on--click="actions.toggleEdit">
				<?php esc_html_e( 'Edit Profile', 'wp-career-board' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<!-- Edit view — owner only -->
	<?php if ( $wcb_is_owner ) : ?>
		<div class="wcb-company-edit" data-wp-show="state.editing">
			<h2><?php esc_html_e( 'Edit Company Profile', 'wp-career-board' ); ?></h2>

			<label for="wcb-cp-name"><?php esc_html_e( 'Company Name', 'wp-career-board' ); ?></label>
			<input
				id="wcb-cp-name"
				type="text"
				class="wcb-field"
				data-wcb-field="companyName"
				data-wp-bind--value="state.companyName"
				data-wp-on--input="actions.updateField"
			/>

			<label for="wcb-cp-desc"><?php esc_html_e( 'Description', 'wp-career-board' ); ?></label>
			<textarea
				id="wcb-cp-desc"
				class="wcb-field"
				rows="6"
				data-wcb-field="companyDesc"
				data-wp-on--input="actions.updateField"
			></textarea>

			<label for="wcb-cp-site"><?php esc_html_e( 'Website', 'wp-career-board' ); ?></label>
			<input
				id="wcb-cp-site"
				type="url"
				class="wcb-field"
				data-wcb-field="companySite"
				data-wp-bind--value="state.companySite"
				data-wp-on--input="actions.updateField"
			/>

			<p class="wcb-save-success" data-wp-show="state.saved"><?php esc_html_e( 'Profile saved.', 'wp-career-board' ); ?></p>
			<p class="wcb-error" data-wp-show="state.error" data-wp-text="state.error"></p>

			<div class="wcb-form-nav">
				<button type="button" class="wcb-btn wcb-btn-back" data-wp-on--click="actions.toggleEdit" data-wp-bind--disabled="state.saving">
					<?php esc_html_e( 'Cancel', 'wp-career-board' ); ?>
				</button>
				<button
					type="button"
					class="wcb-btn"
					data-wp-on--click="actions.saveProfile"
					data-wp-bind--disabled="state.saving"
				>
					<span data-wp-show="!state.saving"><?php esc_html_e( 'Save', 'wp-career-board' ); ?></span>
					<span data-wp-show="state.saving"><?php esc_html_e( 'Saving…', 'wp-career-board' ); ?></span>
				</button>
			</div>
		</div>
	<?php endif; ?>

	<!-- Active job listings -->
	<section class="wcb-company-jobs">
		<h2><?php esc_html_e( 'Open Positions', 'wp-career-board' ); ?></h2>
		<div class="wcb-loading" data-wp-show="state.loading"><?php esc_html_e( 'Loading…', 'wp-career-board' ); ?></div>

		<div data-wp-show="!state.loading">
			<template data-wp-each--job="state.jobs" data-wp-each-key="context.job.id">
				<div class="wcb-job-card">
					<h3><a data-wp-bind--href="context.job.permalink" data-wp-text="context.job.title"></a></h3>
					<span data-wp-text="context.job.location"></span>
					<span data-wp-text="context.job.type"></span>
				</div>
			</template>
		</div>
	</section>
</div>
