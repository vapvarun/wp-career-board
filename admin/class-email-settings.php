<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Emails settings admin page.
 *
 * Renders brand settings and per-email enable/subject controls.
 * Populated by wcb_registered_emails filter — Pro emails appear automatically.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Notifications settings page — brand config and per-email subject/enable controls.
 *
 * @since 1.0.0
 */
class EmailSettings {

	/**
	 * Register admin_init save handler.
	 *
	 * @since 1.0.0
	 */
	public function boot(): void {
		add_action( 'admin_init', array( $this, 'save' ) );
	}

	/**
	 * Render just the email settings form — used when embedded as a Settings tab.
	 *
	 * @since 1.0.0
	 */
	public function render_form(): void {
		$settings = (array) get_option( 'wcb_email_settings', array() );
		$brand    = isset( $settings['brand'] ) ? (array) $settings['brand'] : array();
		$emails   = (array) apply_filters( 'wcb_registered_emails', array() );
		?>
		<div class="wcb-settings-card">
			<div class="wcb-settings-card-header">
				<h2 class="wcb-settings-card-title"><?php esc_html_e( 'Brand Settings', 'wp-career-board' ); ?></h2>
			</div>
			<form method="post">
				<?php wp_nonce_field( 'wcb_email_settings_save', 'wcb_email_nonce' ); ?>
				<div class="wcb-settings-row">
					<div class="wcb-settings-row-label">
						<label for="wcb-email-header-color"><?php esc_html_e( 'Header Color', 'wp-career-board' ); ?></label>
					</div>
					<div class="wcb-settings-row-control">
						<input type="color" id="wcb-email-header-color" name="wcb_email[brand][header_color]"
							value="<?php echo esc_attr( isset( $brand['header_color'] ) ? $brand['header_color'] : '#4f46e5' ); ?>">
					</div>
				</div>
				<div class="wcb-settings-row">
					<div class="wcb-settings-row-label">
						<label for="wcb-email-logo-id"><?php esc_html_e( 'Logo', 'wp-career-board' ); ?></label>
					</div>
					<div class="wcb-settings-row-control">
						<input type="number" id="wcb-email-logo-id" name="wcb_email[brand][logo_id]"
							value="<?php echo (int) ( isset( $brand['logo_id'] ) ? $brand['logo_id'] : 0 ); ?>"
							placeholder="<?php esc_attr_e( 'Attachment ID', 'wp-career-board' ); ?>">
						<span class="description"><?php esc_html_e( 'Enter the attachment ID of your logo image.', 'wp-career-board' ); ?></span>
					</div>
				</div>
				<div class="wcb-settings-row">
					<div class="wcb-settings-row-label">
						<label for="wcb-email-footer-text"><?php esc_html_e( 'Footer Text', 'wp-career-board' ); ?></label>
					</div>
					<div class="wcb-settings-row-control">
						<textarea id="wcb-email-footer-text" name="wcb_email[brand][footer_text]" rows="2" style="width:400px"><?php echo esc_textarea( isset( $brand['footer_text'] ) ? $brand['footer_text'] : '' ); ?></textarea>
					</div>
				</div>
		</div>

		<div class="wcb-settings-card">
			<div class="wcb-settings-card-header">
				<h2 class="wcb-settings-card-title"><?php esc_html_e( 'Email Templates', 'wp-career-board' ); ?></h2>
			</div>
			<div style="padding: 0 24px 16px;">
				<table class="widefat striped" style="margin-top:12px">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Email', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Recipient', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Subject', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Enabled', 'wp-career-board' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $emails as $email ) : ?>
						<?php
						if ( ! $email instanceof \WCB\Modules\Notifications\AbstractEmail ) {
							continue; }
						?>
						<?php
						$id      = $email->get_id();
						$saved   = isset( $settings[ $id ] ) ? (array) $settings[ $id ] : array();
						$enabled = isset( $saved['enabled'] ) ? (bool) $saved['enabled'] : true;
						$subject = isset( $saved['subject'] ) ? $saved['subject'] : '';
						?>
						<tr>
							<td><strong><?php echo esc_html( $email->get_title() ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( $email->get_recipient() ) ); ?></td>
							<td>
								<input type="text" name="wcb_email[<?php echo esc_attr( $id ); ?>][subject]"
									value="<?php echo esc_attr( $subject ); ?>"
									placeholder="<?php echo esc_attr( $email->get_default_subject() ); ?>"
									style="width:100%;max-width:400px;">
							</td>
							<td>
								<input type="checkbox" name="wcb_email[<?php echo esc_attr( $id ); ?>][enabled]"
									value="1" <?php checked( $enabled ); ?>>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="wcb-settings-footer">
			<?php submit_button( __( 'Save Email Settings', 'wp-career-board' ) ); ?>
		</div>
		</form>
		<?php
	}

	/**
	 * Render the Emails settings page.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		$settings = (array) get_option( 'wcb_email_settings', array() );
		$brand    = isset( $settings['brand'] ) ? (array) $settings['brand'] : array();
		$emails   = (array) apply_filters( 'wcb_registered_emails', array() );
		?>
		<div class="wrap wcb-admin">
			<h1><?php esc_html_e( 'Email Notifications', 'wp-career-board' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'wcb_email_settings_save', 'wcb_email_nonce' ); ?>

				<h2><?php esc_html_e( 'Brand Settings', 'wp-career-board' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Header Color', 'wp-career-board' ); ?></th>
						<td>
							<input type="color" name="wcb_email[brand][header_color]"
								value="<?php echo esc_attr( isset( $brand['header_color'] ) ? $brand['header_color'] : '#4f46e5' ); ?>">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Logo', 'wp-career-board' ); ?></th>
						<td>
							<input type="number" name="wcb_email[brand][logo_id]"
								value="<?php echo (int) ( isset( $brand['logo_id'] ) ? $brand['logo_id'] : 0 ); ?>"
								placeholder="<?php esc_attr_e( 'Attachment ID', 'wp-career-board' ); ?>">
							<p class="description"><?php esc_html_e( 'Enter the attachment ID of your logo image.', 'wp-career-board' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Footer Text', 'wp-career-board' ); ?></th>
						<td>
							<textarea name="wcb_email[brand][footer_text]" rows="2" style="width:400px"><?php echo esc_textarea( isset( $brand['footer_text'] ) ? $brand['footer_text'] : '' ); ?></textarea>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Email Templates', 'wp-career-board' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Email', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Recipient', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Subject', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Enabled', 'wp-career-board' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					foreach ( $emails as $email ) :
						if ( ! $email instanceof \WCB\Modules\Notifications\AbstractEmail ) {
							continue; }
						$id      = $email->get_id();
						$saved   = isset( $settings[ $id ] ) ? (array) $settings[ $id ] : array();
						$enabled = isset( $saved['enabled'] ) ? (bool) $saved['enabled'] : true;
						$subject = isset( $saved['subject'] ) ? $saved['subject'] : '';
						?>
						<tr>
							<td><strong><?php echo esc_html( $email->get_title() ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( $email->get_recipient() ) ); ?></td>
							<td>
								<input type="text" name="wcb_email[<?php echo esc_attr( $id ); ?>][subject]"
									value="<?php echo esc_attr( $subject ); ?>"
									placeholder="<?php echo esc_attr( $email->get_default_subject() ); ?>"
									style="width:100%;max-width:400px;">
							</td>
							<td>
								<input type="checkbox" name="wcb_email[<?php echo esc_attr( $id ); ?>][enabled]"
									value="1" <?php checked( $enabled ); ?>>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<?php submit_button( __( 'Save Email Settings', 'wp-career-board' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save posted email settings.
	 *
	 * @since 1.0.0
	 */
	public function save(): void {
		if ( ! isset( $_POST['wcb_email_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wcb_email_nonce'] ) ), 'wcb_email_settings_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'wcb_manage_settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		$raw      = isset( $_POST['wcb_email'] ) ? (array) wp_unslash( $_POST['wcb_email'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$settings = array();

		// Brand settings.
		if ( isset( $raw['brand'] ) ) {
			$brand             = (array) $raw['brand'];
			$sanitized_color   = sanitize_hex_color( isset( $brand['header_color'] ) ? $brand['header_color'] : '#4f46e5' );
			$settings['brand'] = array(
				'header_color' => $sanitized_color ? $sanitized_color : '#4f46e5',
				'logo_id'      => absint( isset( $brand['logo_id'] ) ? $brand['logo_id'] : 0 ),
				'footer_text'  => wp_kses_post( isset( $brand['footer_text'] ) ? $brand['footer_text'] : '' ),
			);
		}

		// Per-email settings — only save keys that match registered emails.
		$emails = (array) apply_filters( 'wcb_registered_emails', array() );
		foreach ( $emails as $email ) {
			if ( ! $email instanceof \WCB\Modules\Notifications\AbstractEmail ) {
				continue;
			}
			$id              = $email->get_id();
			$settings[ $id ] = array(
				'enabled' => ! empty( $raw[ $id ]['enabled'] ),
				'subject' => sanitize_text_field( isset( $raw[ $id ]['subject'] ) ? $raw[ $id ]['subject'] : '' ),
			);
		}

		update_option( 'wcb_email_settings', $settings );
		add_action(
			'admin_notices',
			static function () {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Email settings saved.', 'wp-career-board' ) . '</p></div>';
			}
		);
	}
}
