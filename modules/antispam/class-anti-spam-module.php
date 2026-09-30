<?php
/**
 * Anti-spam module — honeypot + optional CAPTCHA provider for all submission forms.
 *
 * Two layers of protection:
 *  1. Honeypot field (always active, zero performance cost, catches most bots).
 *  2. Optional second-layer CAPTCHA provider (Cloudflare Turnstile recommended).
 *
 * Providers available in Settings → Anti-Spam:
 *  - None          — honeypot only (default)
 *  - Cloudflare Turnstile — fast, privacy-friendly, free tier
 *  - Google reCAPTCHA v3  — score-based, requires Google account
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\AntiSpam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the anti-spam layer and exposes the Settings → Anti-Spam admin tab.
 *
 * @since 1.0.0
 */
class AntiSpamModule {

	/**
	 * Active CAPTCHA driver, or null when provider is 'none'.
	 *
	 * @since 1.0.0
	 * @var TurnstileDriver|RecaptchaDriver|RecaptchaV2Driver|null
	 */
	private TurnstileDriver|RecaptchaDriver|RecaptchaV2Driver|null $driver = null;

	/**
	 * The CAPTCHA in force: the chosen provider when both of its keys are
	 * set, else null (honeypot only). One answer for the web forms and the
	 * app-config the mobile app reads, so the app never demands a token the
	 * site doesn't check, or skips one it does.
	 *
	 * @since 1.8.0
	 * @return array{provider: string, site_key: string, secret_key: string}|null
	 */
	public static function active(): ?array {
		$provider = \WCB\Admin\Settings::string( 'captcha_provider', 'none' );
		$prefix   = array(
			'turnstile'    => 'turnstile',
			'recaptcha'    => 'recaptcha',
			'recaptcha_v2' => 'recaptcha_v2',
		)[ $provider ] ?? '';
		if ( '' === $prefix ) {
			return null;
		}
		$site   = \WCB\Admin\Settings::string( $prefix . '_site_key', '' );
		$secret = \WCB\Admin\Settings::string( $prefix . '_secret_key', '' );
		if ( '' === $site || '' === $secret ) {
			return null;
		}
		return array(
			'provider'   => $provider,
			'site_key'   => $site,
			'secret_key' => $secret,
		);
	}

	/**
	 * Register all hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		$active       = self::active();
		$this->driver = match ( $active['provider'] ?? '' ) {
			'turnstile'    => new TurnstileDriver( $active['site_key'], $active['secret_key'] ),
			'recaptcha'    => new RecaptchaDriver( $active['site_key'], $active['secret_key'], (float) \WCB\Admin\Settings::get( 'recaptcha_threshold', 0.5 ) ),
			'recaptcha_v2' => new RecaptchaV2Driver( $active['site_key'], $active['secret_key'] ),
			default        => null,
		};

		add_filter( 'wcb_pre_job_submit', array( $this, 'verify_request' ), 10, 2 );
		add_filter( 'wcb_pre_application_submit', array( $this, 'verify_request' ), 10, 2 );
		add_filter( 'wcb_pre_registration', array( $this, 'verify_request' ), 10, 2 );

		if ( null !== $this->driver ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		}

		add_filter( 'wcb_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'wcb_settings_tab_antispam', array( $this, 'render_settings_tab' ) );
	}

	/**
	 * Check the honeypot field and optional CAPTCHA token for a REST request.
	 *
	 * Hooked onto wcb_pre_job_submit, wcb_pre_application_submit and
	 * wcb_pre_registration. Returns a
	 * WP_Error to short-circuit the endpoint if spam is detected.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed            $result  Existing filter value (null = pass, WP_Error = already failed).
	 * @param \WP_REST_Request $request Incoming REST request.
	 * @return mixed null on pass, WP_Error on failure.
	 */
	public function verify_request( mixed $result, \WP_REST_Request $request ): mixed {
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Honeypot — bots that autofill all fields will populate this hidden input.
		if ( ! empty( $request->get_param( 'hp' ) ) ) {
			return new \WP_Error(
				'wcb_spam',
				__( 'Spam detected.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		if ( null === $this->driver ) {
			return $result;
		}

		$token = (string) ( $request->get_param( 'wcb_captcha_token' ) ?? '' );

		if ( ! $this->driver->verify( $token ) ) {
			return new \WP_Error(
				'wcb_captcha_failed',
				__( 'CAPTCHA verification failed. Please try again.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		return $result;
	}

	/**
	 * Enqueue the active CAPTCHA provider's frontend scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend(): void {
		$this->driver?->enqueue();
	}

	/**
	 * Register the Anti-Spam settings tab via the wcb_settings_tabs filter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,string> $tabs Existing settings tabs.
	 * @return array<string,string>
	 */
	public function add_settings_tab( array $tabs ): array {
		$tabs['antispam'] = __( 'Anti-Spam', 'wp-career-board' );
		return $tabs;
	}

	/**
	 * Render the Anti-Spam settings tab content.
	 *
	 * Called via do_action( 'wcb_settings_tab_antispam', $settings ).
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $settings Current wcb_settings values.
	 * @return void
	 */
	public function render_settings_tab( array $settings ): void {
		if ( ! wp_is_ability_granted( 'wcb/manage-settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
			return;
		}

		$wcb_provider  = (string) ( $settings['captcha_provider'] ?? 'none' );
		$wcb_ts_site   = (string) ( $settings['turnstile_site_key'] ?? '' );
		$wcb_ts_secret = (string) ( $settings['turnstile_secret_key'] ?? '' );
		$wcb_rc_site   = (string) ( $settings['recaptcha_site_key'] ?? '' );
		$wcb_rc_secret = (string) ( $settings['recaptcha_secret_key'] ?? '' );
		$wcb_rc_thresh = (float) ( $settings['recaptcha_threshold'] ?? 0.5 );

		$wcb_v2_site   = (string) ( $settings['recaptcha_v2_site_key'] ?? '' );
		$wcb_v2_secret = (string) ( $settings['recaptcha_v2_secret_key'] ?? '' );
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'wcb_settings_group' ); ?>
			<?php \WCB\Admin\SettingsSchema::form_fields(); ?>

			<div class="wcb-card">
				<div class="wcb-card__head">
					<p class="wcb-card__title"><?php esc_html_e( 'Anti-Spam', 'wp-career-board' ); ?></p>
					<p class="wcb-card__desc"><?php esc_html_e( 'A honeypot field is always active on all submission forms at zero performance cost. Add a CAPTCHA provider as a second layer for high-traffic sites.', 'wp-career-board' ); ?></p>
				</div>
				<div class="wcb-card__body">
					<div class="wcb-settings-row">
						<div class="wcb-settings-row-label">
							<label for="wcb-captcha-provider"><?php esc_html_e( 'CAPTCHA Provider', 'wp-career-board' ); ?></label>
						</div>
						<div class="wcb-settings-row-control">
							<select id="wcb-captcha-provider" name="wcb_settings[captcha_provider]">
								<option value="none" <?php selected( $wcb_provider, 'none' ); ?>><?php esc_html_e( 'None (Honeypot only)', 'wp-career-board' ); ?></option>
								<option value="turnstile" <?php selected( $wcb_provider, 'turnstile' ); ?>><?php esc_html_e( 'Cloudflare Turnstile', 'wp-career-board' ); ?></option>
								<option value="recaptcha" <?php selected( $wcb_provider, 'recaptcha' ); ?>><?php esc_html_e( 'Google reCAPTCHA v3 (invisible, score)', 'wp-career-board' ); ?></option>
								<option value="recaptcha_v2" <?php selected( $wcb_provider, 'recaptcha_v2' ); ?>><?php esc_html_e( 'Google reCAPTCHA v2 (invisible badge)', 'wp-career-board' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Cloudflare Turnstile is recommended - fast, privacy-friendly, and free.', 'wp-career-board' ); ?></p>
						</div>
					</div>
				</div>
			</div>

			<div class="wcb-card">
				<div class="wcb-card__head">
					<p class="wcb-card__title"><?php esc_html_e( 'Cloudflare Turnstile', 'wp-career-board' ); ?></p>
				</div>
				<div class="wcb-card__body">
					<div class="wcb-settings-row">
						<div class="wcb-settings-row-label">
							<label for="wcb-turnstile-site-key"><?php esc_html_e( 'Site Key', 'wp-career-board' ); ?></label>
						</div>
						<div class="wcb-settings-row-control">
							<input type="text" id="wcb-turnstile-site-key" name="wcb_settings[turnstile_site_key]" value="<?php echo esc_attr( $wcb_ts_site ); ?>" class="regular-text">
							<p class="description">
								<?php
								printf(
									/* translators: %s: URL to Cloudflare dashboard */
									esc_html__( 'Get your keys at %s for Turnstile.', 'wp-career-board' ),
									'<a href="https://dash.cloudflare.com/" target="_blank" rel="noopener noreferrer">dash.cloudflare.com</a>'
								);
								?>
							</p>
						</div>
					</div>
					<div class="wcb-settings-row">
						<div class="wcb-settings-row-label">
							<label for="wcb-turnstile-secret-key"><?php esc_html_e( 'Secret Key', 'wp-career-board' ); ?></label>
						</div>
						<div class="wcb-settings-row-control">
							<input type="password" id="wcb-turnstile-secret-key" name="wcb_settings[turnstile_secret_key]" value="<?php echo esc_attr( $wcb_ts_secret ); ?>" class="regular-text" autocomplete="off">
						</div>
					</div>
				</div>
			</div>

			<div class="wcb-card">
				<div class="wcb-card__head">
					<p class="wcb-card__title"><?php esc_html_e( 'Google reCAPTCHA v3', 'wp-career-board' ); ?></p>
				</div>
				<div class="wcb-card__body">
					<div class="wcb-settings-row">
						<div class="wcb-settings-row-label">
							<label for="wcb-recaptcha-site-key"><?php esc_html_e( 'Site Key', 'wp-career-board' ); ?></label>
						</div>
						<div class="wcb-settings-row-control">
							<input type="text" id="wcb-recaptcha-site-key" name="wcb_settings[recaptcha_site_key]" value="<?php echo esc_attr( $wcb_rc_site ); ?>" class="regular-text">
							<p class="description">
								<?php
								printf(
									/* translators: %s: link to the provider's key dashboard. */
									esc_html__( 'Get your keys at %s.', 'wp-career-board' ),
									'<a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener noreferrer">google.com/recaptcha/admin</a>'
								);
								?>
							</p>
						</div>
					</div>
					<div class="wcb-settings-row">
						<div class="wcb-settings-row-label">
							<label for="wcb-recaptcha-secret-key"><?php esc_html_e( 'Secret Key', 'wp-career-board' ); ?></label>
						</div>
						<div class="wcb-settings-row-control">
							<input type="password" id="wcb-recaptcha-secret-key" name="wcb_settings[recaptcha_secret_key]" value="<?php echo esc_attr( $wcb_rc_secret ); ?>" class="regular-text" autocomplete="off">
						</div>
					</div>
					<div class="wcb-settings-row">
						<div class="wcb-settings-row-label">
							<label for="wcb-recaptcha-threshold"><?php esc_html_e( 'Score Threshold', 'wp-career-board' ); ?></label>
						</div>
						<div class="wcb-settings-row-control">
							<input type="number" id="wcb-recaptcha-threshold" name="wcb_settings[recaptcha_threshold]" value="<?php echo esc_attr( (string) $wcb_rc_thresh ); ?>" min="0" max="1" step="0.1" class="small-text">
							<p class="description"><?php esc_html_e( 'Requests scoring below this are rejected as bots (0.0-1.0). Default: 0.5.', 'wp-career-board' ); ?></p>
						</div>
					</div>
				</div>
			</div>

			<div class="wcb-card">
				<div class="wcb-card__head">
					<p class="wcb-card__title"><?php esc_html_e( 'Google reCAPTCHA v2', 'wp-career-board' ); ?></p>
					<p class="wcb-card__desc"><?php esc_html_e( 'Create the keys as reCAPTCHA v2, "Invisible reCAPTCHA badge". Suspicious visitors get a picture challenge; everyone else sees nothing.', 'wp-career-board' ); ?></p>
				</div>
				<div class="wcb-card__body">
					<div class="wcb-settings-row">
						<div class="wcb-settings-row-label">
							<label for="wcb-recaptcha-v2-site-key"><?php esc_html_e( 'Site Key', 'wp-career-board' ); ?></label>
						</div>
						<div class="wcb-settings-row-control">
							<input type="text" id="wcb-recaptcha-v2-site-key" name="wcb_settings[recaptcha_v2_site_key]" value="<?php echo esc_attr( $wcb_v2_site ); ?>" class="regular-text">
						</div>
					</div>
					<div class="wcb-settings-row">
						<div class="wcb-settings-row-label">
							<label for="wcb-recaptcha-v2-secret-key"><?php esc_html_e( 'Secret Key', 'wp-career-board' ); ?></label>
						</div>
						<div class="wcb-settings-row-control">
							<input type="password" id="wcb-recaptcha-v2-secret-key" name="wcb_settings[recaptcha_v2_secret_key]" value="<?php echo esc_attr( $wcb_v2_secret ); ?>" class="regular-text" autocomplete="off">
						</div>
					</div>
				</div>
			</div>

			<?php
			$wcb_ip_header  = (string) ( $settings['client_ip_header'] ?? '' );
			$wcb_ip_options = array(
				''                      => __( 'Direct connection (no proxy or CDN)', 'wp-career-board' ),
				'HTTP_CF_CONNECTING_IP' => __( 'Cloudflare (CF-Connecting-IP)', 'wp-career-board' ),
				'HTTP_X_FORWARDED_FOR'  => __( 'Load balancer or proxy (X-Forwarded-For)', 'wp-career-board' ),
				'HTTP_X_REAL_IP'        => __( 'Nginx proxy (X-Real-IP)', 'wp-career-board' ),
			);
			?>
			<div class="wcb-card">
				<div class="wcb-card__head">
					<p class="wcb-card__title"><?php esc_html_e( 'Visitor IP address', 'wp-career-board' ); ?></p>
					<p class="wcb-card__desc"><?php esc_html_e( 'Sign-ups, guest applications and sign-ins are limited per visitor IP address. Behind a proxy or CDN every visitor arrives from the proxy\'s address, so tell the plugin where the real address is.', 'wp-career-board' ); ?></p>
				</div>
				<div class="wcb-card__body">
					<div class="wcb-settings-row">
						<div class="wcb-settings-row-label">
							<label for="wcb-client-ip-header"><?php esc_html_e( 'Your site runs behind', 'wp-career-board' ); ?></label>
						</div>
						<div class="wcb-settings-row-control">
							<select id="wcb-client-ip-header" name="wcb_settings[client_ip_header]">
								<?php foreach ( $wcb_ip_options as $wcb_ip_value => $wcb_ip_label ) : ?>
									<option value="<?php echo esc_attr( $wcb_ip_value ); ?>" <?php selected( $wcb_ip_header, $wcb_ip_value ); ?>><?php echo esc_html( $wcb_ip_label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Choose a proxy only if your site really is behind it. Anyone can send these headers, so on a direct connection they would let a visitor dodge the limits.', 'wp-career-board' ); ?></p>
						</div>
					</div>
				</div>
			</div>

			<div class="wcb-settings-footer">
			<?php submit_button( __( 'Save Anti-Spam Settings', 'wp-career-board' ), 'primary', 'submit', false ); ?>
			</div>
		</form>
		<?php
	}
}
