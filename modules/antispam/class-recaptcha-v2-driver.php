<?php
/**
 * Google reCAPTCHA v2 (invisible badge) CAPTCHA driver.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

declare( strict_types=1 );

namespace WCB\Modules\AntiSpam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verifies reCAPTCHA v2 tokens. Unlike v3 there is no score: Google shows
 * suspicious visitors a picture challenge and only issues a token once it is
 * passed, so a successful siteverify is the whole check.
 *
 * @since 1.8.0
 */
class RecaptchaV2Driver {

	/**
	 * Constructor.
	 *
	 * @since 1.8.0
	 *
	 * @param string $site_key   reCAPTCHA v2 (invisible) site key.
	 * @param string $secret_key reCAPTCHA v2 secret key.
	 */
	public function __construct(
		private readonly string $site_key,
		private readonly string $secret_key,
	) {}

	/**
	 * Verify a token via the siteverify API.
	 *
	 * @since 1.8.0
	 *
	 * @param string $token Token from grecaptcha.execute().
	 * @return bool
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
		return is_array( $body ) && ! empty( $body['success'] );
	}

	/**
	 * Enqueue the reCAPTCHA API (explicit render) and the WCB shim.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function enqueue(): void {
		wp_enqueue_script(
			'wcb-recaptcha-v2-api',
			'https://www.google.com/recaptcha/api.js?render=explicit',
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_register_script(
			'wcb-recaptcha-v2',
			WCB_URL . 'assets/js/wcb-recaptcha-v2.js',
			array( 'wcb-recaptcha-v2-api' ),
			WCB_VERSION,
			array( 'in_footer' => true )
		);

		wp_localize_script(
			'wcb-recaptcha-v2',
			'wcbAntispam',
			array(
				'provider' => 'recaptcha_v2',
				'siteKey'  => $this->site_key,
			)
		);

		wp_enqueue_script( 'wcb-recaptcha-v2' );
	}
}
