<?php
/**
 * Wizard step partial: sign-ups.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

defined( 'ABSPATH' ) || exit;

$wcb_can_register = (bool) get_option( 'users_can_register' );
$wcb_verify       = \WCB\Admin\Settings::bool( 'require_email_verification', false );
?>
<div class="wcb-settings-row">
	<div class="wcb-settings-row-label"><label for="wcb-wz-register"><?php esc_html_e( 'Let people sign up', 'wp-career-board' ); ?></label></div>
	<div class="wcb-settings-row-control">
		<label class="wcb-toggle">
			<input type="checkbox" id="wcb-wz-register" name="users_can_register" <?php checked( $wcb_can_register ); ?>>
			<span class="wcb-toggle-slider"></span>
		</label>
		<span class="description"><?php esc_html_e( 'Candidates and employers create their own accounts. Off: only you can add users, and the sign-up forms show a "registration closed" notice. This is WordPress\'s "Anyone can register" setting.', 'wp-career-board' ); ?></span>
	</div>
</div>
<div class="wcb-settings-row">
	<div class="wcb-settings-row-label"><label for="wcb-wz-verify"><?php esc_html_e( 'Confirm email addresses', 'wp-career-board' ); ?></label></div>
	<div class="wcb-settings-row-control">
		<label class="wcb-toggle">
			<input type="checkbox" id="wcb-wz-verify" name="require_email_verification" <?php checked( $wcb_verify ); ?>>
			<span class="wcb-toggle-slider"></span>
		</label>
		<span class="description"><?php esc_html_e( 'New accounts click a link in their inbox before they can post or apply. Stops fake and mistyped sign-ups.', 'wp-career-board' ); ?></span>
	</div>
</div>
<?php
require __DIR__ . '/_footer.php';
