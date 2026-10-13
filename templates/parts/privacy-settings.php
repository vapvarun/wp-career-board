<?php
/**
 * Dashboard panel: "Privacy & My Data" - data export and account deletion.
 *
 * One panel for both dashboards (members have no wp-admin, so an employer
 * needs it as much as a candidate). Deleting an employer account follows
 * EmployersModule::retire_owned_listings() (owner decision, 1.8.1). Store:
 * the @wcb/privacy module (namespace wcb-privacy).
 *
 * @package WP_Career_Board
 * @since   1.8.1
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() || ! class_exists( '\WCB\Modules\Account\AccountDeletionService' ) ) {
	return;
}

$wcb_deletion       = new \WCB\Modules\Account\AccountDeletionService();
$wcb_deletion_state = $wcb_deletion->status( wp_get_current_user() );
$wcb_deletion_when  = (string) ( $wcb_deletion_state['scheduled_for'] ?? '' );
$wcb_grace_days     = $wcb_deletion->grace_days();

wp_interactivity_state(
	'wcb-privacy',
	array(
		'apiBase'               => untrailingslashit( rest_url( 'wcb/v1' ) ),
		'nonce'                 => wp_create_nonce( 'wp_rest' ),
		'locale'                => \WCB\Core\SalaryFormat::locale(),
		'busy'                  => false,
		'exportRequested'       => false,
		'error'                 => '',
		'deletionStatus'        => (string) ( $wcb_deletion_state['status'] ?? 'active' ),
		'deletionScheduledFor'  => '' === $wcb_deletion_when ? '' : date_i18n( get_option( 'date_format' ), (int) strtotime( $wcb_deletion_when ) ),
		'deletionJustScheduled' => false,
		'deleteFormOpen'        => false,
		'deletePassword'        => '',
		'deleteConfirm'         => '',
		'msgPrivacy'            => __( 'Could not submit your privacy request. Please try again or contact support.', 'wp-career-board' ),
		'msgDelete'             => __( 'Could not schedule deletion. Please try again.', 'wp-career-board' ),
		'msgDeleteCancel'       => __( 'Could not cancel the deletion. Please try again.', 'wp-career-board' ),
		'msgConnection'         => __( 'Connection error. Please check your network and try again.', 'wp-career-board' ),
	)
);
wp_enqueue_script_module( '@wcb/privacy' );

$wcb_is_employer = wp_is_ability_granted( 'wcb/manage-company' );
?>
<div class="wcb-page-header wcb-page-header--section">
	<h2 class="wcb-page-subtitle"><?php esc_html_e( 'Privacy & My Data', 'wp-career-board' ); ?></h2>
</div>
<div class="wcb-privacy-settings" data-wp-interactive="wcb-privacy">
<div class="wcb-panel wcb-panel--form wcb-shown wcb-privacy-panel">
	<div class="wcb-form-field">
		<h3 class="wcb-form-label"><?php esc_html_e( 'Export my data', 'wp-career-board' ); ?></h3>
		<p class="wcb-field-hint"><?php esc_html_e( 'We email you to confirm the request, then send the file once the site has prepared it.', 'wp-career-board' ); ?></p>
		<div class="wcb-form-actions">
			<button type="button" class="wcb-btn wcb-btn--secondary" data-wp-on--click="actions.requestExport" data-wp-bind--disabled="state.busy">
				<span data-wp-class--wcb-hidden="state.exportRequested"><?php esc_html_e( 'Request data export', 'wp-career-board' ); ?></span>
				<span class="wcb-hidden wcb-icon-label" data-wp-class--wcb-hidden="!state.exportRequested"><?php echo \WCB\Core\Icon::svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?><?php esc_html_e( 'Requested - check your email', 'wp-career-board' ); ?></span>
			</button>
		</div>
	</div>

</div>

<?php // Deleting is set apart from everyday settings, in its own danger card. ?>
<div class="wcb-panel wcb-panel--form wcb-shown wcb-danger-zone">
	<div class="wcb-form-field">
		<h3 class="wcb-form-label"><?php esc_html_e( 'Delete my account', 'wp-career-board' ); ?></h3>

		<?php // Scheduled on an earlier visit: they are signed in, so offer the way back. ?>
		<div class="wcb-hidden" data-wp-class--wcb-hidden="!state.showCancelControl">
			<p class="wcb-settings-note">
				<?php esc_html_e( 'Your account is scheduled for deletion on', 'wp-career-board' ); ?>
				<strong data-wp-text="state.deletionScheduledFor"></strong>.
				<?php esc_html_e( 'You can stop this at any time before then.', 'wp-career-board' ); ?>
			</p>
			<div class="wcb-form-actions">
				<button type="button" class="wcb-btn wcb-btn--secondary" data-wp-on--click="actions.cancelDeletion" data-wp-bind--disabled="state.busy"><?php esc_html_e( 'Keep my account', 'wp-career-board' ); ?></button>
			</div>
		</div>

		<?php // Just scheduled: confirming signs them out everywhere, so a cancel button here would only fail. ?>
		<p class="wcb-settings-note wcb-hidden" data-wp-class--wcb-hidden="!state.showSignedOutNotice">
			<?php esc_html_e( 'Your account is scheduled for deletion on', 'wp-career-board' ); ?>
			<strong data-wp-text="state.deletionScheduledFor"></strong>.
			<?php esc_html_e( 'For your security you have been signed out on every device. To stop the deletion, sign back in before that date and choose "Keep my account".', 'wp-career-board' ); ?>
		</p>

		<?php // What deletion does stays in view while the confirmation form is open. ?>
		<p class="wcb-field-hint" data-wp-class--wcb-hidden="state.deletionPending">
			<?php
			echo esc_html(
				$wcb_is_employer
					? __( 'Your live jobs close (applicants are told), jobs waiting for review go back to draft, and your companies are taken offline.', 'wp-career-board' )
					: __( 'This deletes your profile, resumes and uploaded files. Employers keep your past applications with your name and contact details removed.', 'wp-career-board' )
			);
			?>
		</p>
		<div class="wcb-form-actions" data-wp-class--wcb-hidden="state.deleteButtonHidden">
			<button type="button" class="wcb-btn wcb-btn--danger" data-wp-on--click="actions.openDeleteForm" data-wp-bind--disabled="state.busy"><?php esc_html_e( 'Delete my account', 'wp-career-board' ); ?></button>
		</div>

		<?php // Inline, not the shared confirm dialog: this needs a password and a typed confirmation, which the REST route enforces. ?>
		<form class="wcb-hidden wcb-delete-account-form" data-wp-class--wcb-hidden="state.deleteFormHidden" data-wp-on--submit="actions.confirmDeletion">
			<?php // The account's sign-in name, hidden: password managers pair it with the fields below. ?>
			<input type="text" autocomplete="username" value="<?php echo esc_attr( wp_get_current_user()->user_login ); ?>" hidden>
			<p class="wcb-field-hint">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of days before the account is deleted. */
						_n( 'You have %d day to change your mind; signing back in and choosing "Keep my account" stops it.', 'You have %d days to change your mind; signing back in and choosing "Keep my account" stops it.', $wcb_grace_days, 'wp-career-board' ),
						$wcb_grace_days
					)
				);
				?>
			</p>
			<label class="wcb-form-label" for="wcb-delete-password"><?php esc_html_e( 'Your password', 'wp-career-board' ); ?></label>
			<input type="password" id="wcb-delete-password" class="wcb-input" autocomplete="current-password" data-wp-bind--value="state.deletePassword" data-wp-on--input="actions.setPassword">
			<label class="wcb-form-label" for="wcb-delete-confirm"><?php esc_html_e( 'Type DELETE to confirm', 'wp-career-board' ); ?></label>
			<input type="text" id="wcb-delete-confirm" class="wcb-input" autocomplete="off" data-wp-bind--value="state.deleteConfirm" data-wp-on--input="actions.setConfirm">
			<div class="wcb-form-actions">
				<button type="submit" class="wcb-btn wcb-btn--danger" data-wp-bind--disabled="state.busy"><?php esc_html_e( 'Schedule deletion', 'wp-career-board' ); ?></button>
				<button type="button" class="wcb-btn wcb-btn--secondary" data-wp-on--click="actions.closeDeleteForm" data-wp-bind--disabled="state.busy"><?php esc_html_e( 'Cancel', 'wp-career-board' ); ?></button>
			</div>
		</form>
	</div>
</div>
<p class="wcb-privacy-note" role="alert" data-wp-class--wcb-shown="state.error" data-wp-text="state.error"></p>
</div>
