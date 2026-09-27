<?php
/**
 * Wizard step partial: email sender and admin address.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

defined( 'ABSPATH' ) || exit;

$wcb_from_name  = \WCB\Admin\Settings::string( 'from_name', '' );
$wcb_from_email = \WCB\Admin\Settings::string( 'from_email', '' );
$wcb_notify     = \WCB\Admin\Settings::string( 'notification_email', '' );
?>
<div class="wcb-settings-row">
	<div class="wcb-settings-row-label"><label for="wcb-wz-from-name"><?php esc_html_e( 'Sender name', 'wp-career-board' ); ?></label></div>
	<div class="wcb-settings-row-control">
		<input type="text" id="wcb-wz-from-name" name="from_name" value="<?php echo esc_attr( '' !== $wcb_from_name ? $wcb_from_name : (string) get_option( 'blogname' ) ); ?>" class="regular-text">
		<span class="description"><?php esc_html_e( 'Shown as "From" on every Career Board email.', 'wp-career-board' ); ?></span>
	</div>
</div>
<div class="wcb-settings-row">
	<div class="wcb-settings-row-label"><label for="wcb-wz-from-email"><?php esc_html_e( 'Sender email', 'wp-career-board' ); ?></label></div>
	<div class="wcb-settings-row-control">
		<input type="email" id="wcb-wz-from-email" name="from_email" value="<?php echo esc_attr( '' !== $wcb_from_email ? $wcb_from_email : (string) get_option( 'admin_email' ) ); ?>" class="regular-text">
		<span class="description"><?php esc_html_e( 'Use an address on your own domain so emails don\'t land in spam.', 'wp-career-board' ); ?></span>
	</div>
</div>
<div class="wcb-settings-row">
	<div class="wcb-settings-row-label"><label for="wcb-wz-notify"><?php esc_html_e( 'Send admin alerts to', 'wp-career-board' ); ?></label></div>
	<div class="wcb-settings-row-control">
		<input type="email" id="wcb-wz-notify" name="notification_email" value="<?php echo esc_attr( '' !== $wcb_notify ? $wcb_notify : (string) get_option( 'admin_email' ) ); ?>" class="regular-text">
		<span class="description"><?php esc_html_e( 'New jobs waiting for review, reports and other admin notices go here.', 'wp-career-board' ); ?></span>
	</div>
</div>
<?php
require __DIR__ . '/_footer.php';
