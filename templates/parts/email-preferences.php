<?php
/**
 * Dashboard panel: the optional emails a member can turn off.
 *
 * Expects $wcb_email_prefs_for ('candidate' or 'employer').
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WCB\Modules\Notifications\AbstractEmail;

$wcb_email_prefs = AbstractEmail::optional_emails( (string) ( $wcb_email_prefs_for ?? '' ) );
if ( ! $wcb_email_prefs ) {
	return;
}

$wcb_email_optout = AbstractEmail::opted_out( get_current_user_id() );
wp_interactivity_state(
	'wcb-email-prefs',
	array(
		'apiBase' => rest_url( 'wcb/v1' ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
		'optout'  => $wcb_email_optout,
		'msg'     => '',
		'saved'   => __( 'Saved.', 'wp-career-board' ),
		'failed'  => __( 'Could not save. Please try again.', 'wp-career-board' ),
	)
);
wp_enqueue_script_module( '@wcb/email-prefs' );
?>
<div class="wcb-page-header" style="margin-top: var(--wcb-space-xl);">
	<h2 class="wcb-page-subtitle"><?php esc_html_e( 'Email Notifications', 'wp-career-board' ); ?></h2>
</div>
<div class="wcb-panel wcb-panel--form wcb-shown" data-wp-interactive="wcb-email-prefs">
	<div class="wcb-settings-row">
		<div class="wcb-settings-row-label"><?php esc_html_e( 'Send me', 'wp-career-board' ); ?></div>
		<div class="wcb-settings-row-control">
			<fieldset class="wcb-radio-group wcb-email-prefs">
				<legend class="screen-reader-text"><?php esc_html_e( 'Emails you can turn off', 'wp-career-board' ); ?></legend>
				<?php foreach ( $wcb_email_prefs as $wcb_email_id => $wcb_email_title ) : ?>
					<label class="wcb-checkbox-label" data-wp-context="<?php echo esc_attr( (string) wp_json_encode( array( 'id' => $wcb_email_id ) ) ); ?>">
						<input type="checkbox" <?php checked( ! in_array( $wcb_email_id, $wcb_email_optout, true ) ); ?> data-wp-on--change="actions.toggle">
						<?php echo esc_html( (string) preg_replace( '/\s*\([^)]*\)$/', '', $wcb_email_title ) ); // Drop admin-list qualifiers like "(Employer)". ?>
					</label>
				<?php endforeach; ?>
				<p class="wcb-settings-note"><?php esc_html_e( 'Emails about your account, applications and payments are always sent.', 'wp-career-board' ); ?></p>
				<p class="wcb-settings-note" role="status" data-wp-text="state.msg"></p>
			</fieldset>
		</div>
	</div>
</div>
