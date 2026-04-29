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
	 * @var TurnstileDriver|RecaptchaDriver|null
	 */
	private TurnstileDriver|RecaptchaDriver|null $driver = null;

	/**
	 * Register all hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		$settings = (array) get_option( 'wcb_settings', array() );
		$provider = (string) ( $settings['captcha_provider'] ?? 'none' );

		$this->driver = match ( $provider ) {
			'turnstile' => ( '' !== ( $settings['turnstile_site_key'] ?? '' ) && '' !== ( $settings['turnstile_secret_key'] ?? '' ) )
				? new TurnstileDriver(
					(string) $settings['turnstile_site_key'],
					(string) $settings['turnstile_secret_key']
				)
				: null,
			'recaptcha' => ( '' !== ( $settings['recaptcha_site_key'] ?? '' ) && '' !== ( $settings['recaptcha_secret_key'] ?? '' ) )
				? new RecaptchaDriver(
					(string) $settings['recaptcha_site_key'],
					(string) $settings['recaptcha_secret_key'],
					(float) ( $settings['recaptcha_threshold'] ?? 0.5 )
				)
				: null,
			default     => null,
		};

		add_filter( 'wcb_pre_job_submit', array( $this, 'verify_request' ), 10, 2 );
		add_filter( 'wcb_pre_application_submit', array( $this, 'verify_request' ), 10, 2 );

		if ( null !== $this->driver ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		}

		add_filter( 'wcb_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'wcb_settings_tab_antispam', array( $this, 'render_settings_tab' ) );
		add_action( 'admin_post_wcb_save_antispam', array( $this, 'save_settings' ) );
	}

	/**
	 * Check the honeypot field and optional CAPTCHA token for a REST request.
	 *
	 * Hooked onto wcb_pre_job_submit and wcb_pre_application_submit. Returns a
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
		if ( ! current_user_can( 'wcb_manage_settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		$wcb_provider  = (string) ( $settings['captcha_provider'] ?? 'none' );
		$wcb_ts_site   = (string) ( $settings['turnstile_site_key'] ?? '' );
		$wcb_ts_secret = (string) ( $settings['turnstile_secret_key'] ?? '' );
		$wcb_rc_site   = (string) ( $settings['recaptcha_site_key'] ?? '' );
		$wcb_rc_secret = (string) ( $settings['recaptcha_secret_key'] ?? '' );
		$wcb_rc_thresh = (float) ( $settings['recaptcha_threshold'] ?? 0.5 );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['wcb-antispam-saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' .
				esc_html__( 'Anti-Spam settings saved.', 'wp-career-board' ) . '</p></div>';
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wcb_save_antispam">
			<?php wp_nonce_field( 'wcb_save_antispam' ); ?>

			<h2><?php esc_html_e( 'Anti-Spam', 'wp-career-board' ); ?></h2>
			<p>
				<?php esc_html_e( 'A honeypot field is always active on all submission forms at zero performance cost. Add a CAPTCHA provider as a second layer for high-traffic sites.', 'wp-career-board' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="wcb-captcha-provider"><?php esc_html_e( 'CAPTCHA Provider', 'wp-career-board' ); ?></label>
					</th>
					<td>
						<select id="wcb-captcha-provider" name="captcha_provider">
							<option value="none" <?php selected( $wcb_provider, 'none' ); ?>><?php esc_html_e( 'None (Honeypot only)', 'wp-career-board' ); ?></option>
							<option value="turnstile" <?php selected( $wcb_provider, 'turnstile' ); ?>>Cloudflare Turnstile</option>
							<option value="recaptcha" <?php selected( $wcb_provider, 'recaptcha' ); ?>>Google reCAPTCHA v3</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Cloudflare Turnstile is recommended — fast, privacy-friendly, and free.', 'wp-career-board' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row" colspan="2"><h3 style="margin:0"><?php esc_html_e( 'Cloudflare Turnstile', 'wp-career-board' ); ?></h3></th>
				</tr>
				<tr>
					<th scope="row">
						<label for="wcb-turnstile-site-key"><?php esc_html_e( 'Site Key', 'wp-career-board' ); ?></label>
					</th>
					<td>
						<input type="text" id="wcb-turnstile-site-key" name="turnstile_site_key"
							value="<?php echo esc_attr( $wcb_ts_site ); ?>" class="regular-text">
						<p class="description">
							<?php
							printf(
								/* translators: %s: URL to Cloudflare dashboard */
								esc_html__( 'Get your keys at %s → Turnstile.', 'wp-career-board' ),
								'<a href="https://dash.cloudflare.com/" target="_blank" rel="noopener noreferrer">dash.cloudflare.com</a>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wcb-turnstile-secret-key"><?php esc_html_e( 'Secret Key', 'wp-career-board' ); ?></label>
					</th>
					<td>
						<input type="password" id="wcb-turnstile-secret-key" name="turnstile_secret_key"
							value="<?php echo esc_attr( $wcb_ts_secret ); ?>" class="regular-text" autocomplete="off">
					</td>
				</tr>

				<tr>
					<th scope="row" colspan="2"><h3 style="margin:0"><?php esc_html_e( 'Google reCAPTCHA v3', 'wp-career-board' ); ?></h3></th>
				</tr>
				<tr>
					<th scope="row">
						<label for="wcb-recaptcha-site-key"><?php esc_html_e( 'Site Key', 'wp-career-board' ); ?></label>
					</th>
					<td>
						<input type="text" id="wcb-recaptcha-site-key" name="recaptcha_site_key"
							value="<?php echo esc_attr( $wcb_rc_site ); ?>" class="regular-text">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wcb-recaptcha-secret-key"><?php esc_html_e( 'Secret Key', 'wp-career-board' ); ?></label>
					</th>
					<td>
						<input type="password" id="wcb-recaptcha-secret-key" name="recaptcha_secret_key"
							value="<?php echo esc_attr( $wcb_rc_secret ); ?>" class="regular-text" autocomplete="off">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wcb-recaptcha-threshold"><?php esc_html_e( 'Score Threshold', 'wp-career-board' ); ?></label>
					</th>
					<td>
						<input type="number" id="wcb-recaptcha-threshold" name="recaptcha_threshold"
							value="<?php echo esc_attr( (string) $wcb_rc_thresh ); ?>"
							min="0" max="1" step="0.1" class="small-text">
						<p class="description"><?php esc_html_e( 'Requests scoring below this are rejected as bots (0.0–1.0). Default: 0.5.', 'wp-career-board' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Anti-Spam Settings', 'wp-career-board' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Handle admin-post save for the Anti-Spam settings form.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function save_settings(): void {
		check_admin_referer( 'wcb_save_antispam' );

		if ( ! current_user_can( 'wcb_manage_settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'Permission denied.', 'wp-career-board' ) );
		}

		$allowed   = array( 'none', 'turnstile', 'recaptcha' );
		$wcb_input = isset( $_POST['captcha_provider'] ) ? sanitize_key( wp_unslash( $_POST['captcha_provider'] ) ) : 'none';
		$provider  = in_array( $wcb_input, $allowed, true ) ? $wcb_input : 'none';

		$settings = (array) get_option( 'wcb_settings', array() );

		$settings['captcha_provider']     = $provider;
		$settings['turnstile_site_key']   = sanitize_text_field( wp_unslash( $_POST['turnstile_site_key'] ?? '' ) );
		$settings['turnstile_secret_key'] = sanitize_text_field( wp_unslash( $_POST['turnstile_secret_key'] ?? '' ) );
		$settings['recaptcha_site_key']   = sanitize_text_field( wp_unslash( $_POST['recaptcha_site_key'] ?? '' ) );
		$settings['recaptcha_secret_key'] = sanitize_text_field( wp_unslash( $_POST['recaptcha_secret_key'] ?? '' ) );
		$settings['recaptcha_threshold']  = max( 0.0, min( 1.0, (float) sanitize_text_field( wp_unslash( $_POST['recaptcha_threshold'] ?? '0.5' ) ) ) );

		update_option( 'wcb_settings', $settings );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'               => 'wcb-settings',
					'tab'                => 'antispam',
					'wcb-antispam-saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
