<?php
/**
 * Google reCAPTCHA v3 CAPTCHA driver.
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
 * Verifies tokens against the Google reCAPTCHA v3 siteverify API.
 *
 * @since 1.0.0
 */
class RecaptchaDriver {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $site_key   Google reCAPTCHA v3 site key.
	 * @param string $secret_key Google reCAPTCHA v3 secret key.
	 * @param float  $threshold  Minimum score threshold (0.0–1.0).
	 */
	public function __construct(
		private readonly string $site_key,
		private readonly string $secret_key,
		private readonly float $threshold = 0.5,
	) {}

	/**
	 * Verify a reCAPTCHA v3 token via the siteverify API.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token Token from grecaptcha.execute().
	 * @return bool True if score meets the threshold, false otherwise.
	 */
	public function verify( string $token ): bool {
		if ( '' === $token || '' === $this->secret_key ) {
			return false;
		}

		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'body'    => array(
					'secret'   => $this->secret_key,
					'response' => $token,
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $body )
			&& ! empty( $body['success'] )
			&& isset( $body['score'] )
			&& (float) $body['score'] >= $this->threshold;
	}

	/**
	 * Enqueue the reCAPTCHA v3 API script and the WCB integration shim.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue(): void {
		wp_enqueue_script(
			'wcb-recaptcha-api',
			'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $this->site_key ),
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_register_script(
			'wcb-recaptcha',
			WCB_URL . 'assets/js/wcb-recaptcha.js',
			array( 'wcb-recaptcha-api' ),
			WCB_VERSION,
			array( 'in_footer' => true )
		);

		wp_localize_script(
			'wcb-recaptcha',
			'wcbAntispam',
			array(
				'provider' => 'recaptcha',
				'siteKey'  => $this->site_key,
			)
		);

		wp_enqueue_script( 'wcb-recaptcha' );
	}
}
