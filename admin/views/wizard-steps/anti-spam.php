<?php
/**
 * Wizard step partial: CAPTCHA provider.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

defined( 'ABSPATH' ) || exit;

$wcb_provider = \WCB\Admin\Settings::string( 'captcha_provider', 'none' );
$wcb_key_rows = array(
	'turnstile'    => array( 'turnstile_site_key', 'turnstile_secret_key', 'https://dash.cloudflare.com/?to=/:account/turnstile' ),
	'recaptcha'    => array( 'recaptcha_site_key', 'recaptcha_secret_key', 'https://www.google.com/recaptcha/admin' ),
	'recaptcha_v2' => array( 'recaptcha_v2_site_key', 'recaptcha_v2_secret_key', 'https://www.google.com/recaptcha/admin' ),
);
?>
<div class="wcb-settings-row">
	<div class="wcb-settings-row-label"><label for="wcb-wz-captcha"><?php esc_html_e( 'CAPTCHA', 'wp-career-board' ); ?></label></div>
	<div class="wcb-settings-row-control">
		<select id="wcb-wz-captcha" name="captcha_provider" data-wcb-captcha-provider>
			<option value="none" <?php selected( $wcb_provider, 'none' ); ?>><?php esc_html_e( 'None (hidden honeypot only)', 'wp-career-board' ); ?></option>
			<option value="turnstile" <?php selected( $wcb_provider, 'turnstile' ); ?>><?php esc_html_e( 'Cloudflare Turnstile (recommended)', 'wp-career-board' ); ?></option>
			<option value="recaptcha" <?php selected( $wcb_provider, 'recaptcha' ); ?>><?php esc_html_e( 'Google reCAPTCHA v3 (invisible, score)', 'wp-career-board' ); ?></option>
			<option value="recaptcha_v2" <?php selected( $wcb_provider, 'recaptcha_v2' ); ?>><?php esc_html_e( 'Google reCAPTCHA v2 (invisible badge)', 'wp-career-board' ); ?></option>
		</select>
		<span class="description"><?php esc_html_e( 'A hidden honeypot always protects sign-up, apply and post-a-job forms. Add a CAPTCHA if bots still get through.', 'wp-career-board' ); ?></span>
	</div>
</div>
<?php foreach ( $wcb_key_rows as $wcb_prov => $wcb_row ) : ?>
<div data-wcb-provider-fields="<?php echo esc_attr( $wcb_prov ); ?>" <?php echo $wcb_prov === $wcb_provider ? '' : 'hidden'; ?>>
	<div class="wcb-settings-row">
		<div class="wcb-settings-row-label"><label for="wcb-wz-<?php echo esc_attr( $wcb_row[0] ); ?>"><?php esc_html_e( 'Site key', 'wp-career-board' ); ?></label></div>
		<div class="wcb-settings-row-control">
			<input type="text" id="wcb-wz-<?php echo esc_attr( $wcb_row[0] ); ?>" name="<?php echo esc_attr( $wcb_row[0] ); ?>" value="<?php echo esc_attr( \WCB\Admin\Settings::string( $wcb_row[0], '' ) ); ?>" class="regular-text" autocomplete="off">
			<span class="description">
				<?php
				printf(
					/* translators: %s: link to the provider's key dashboard. */
					esc_html__( 'Get your keys at %s.', 'wp-career-board' ),
					'<a href="' . esc_url( $wcb_row[2] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( (string) wp_parse_url( $wcb_row[2], PHP_URL_HOST ) ) . '</a>'
				);
				?>
			</span>
		</div>
	</div>
	<div class="wcb-settings-row">
		<div class="wcb-settings-row-label"><label for="wcb-wz-<?php echo esc_attr( $wcb_row[1] ); ?>"><?php esc_html_e( 'Secret key', 'wp-career-board' ); ?></label></div>
		<div class="wcb-settings-row-control">
			<input type="password" id="wcb-wz-<?php echo esc_attr( $wcb_row[1] ); ?>" name="<?php echo esc_attr( $wcb_row[1] ); ?>" value="<?php echo esc_attr( \WCB\Admin\Settings::string( $wcb_row[1], '' ) ); ?>" class="regular-text" autocomplete="off">
		</div>
	</div>
</div>
<?php endforeach; ?>
<?php
require __DIR__ . '/_footer.php';
