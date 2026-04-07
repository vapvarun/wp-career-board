<?php
/**
 * Cloudflare Turnstile CAPTCHA driver.
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
 * Verifies tokens against the Cloudflare Turnstile siteverify API and enqueues
 * the invisible widget JS on the frontend.
 *
 * @since 1.0.0
 */
class TurnstileDriver {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $site_key   Cloudflare Turnstile site key.
	 * @param string $secret_key Cloudflare Turnstile secret key.
	 */
	public function __construct(
		private readonly string $site_key,
		private readonly string $secret_key,
	) {}

	/**
	 * Verify a Turnstile token via the siteverify API.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token Token from the frontend widget.
	 * @return bool True if valid, false on failure or empty token.
	 */
	public function verify( string $token ): bool {
		if ( '' === $token || '' === $this->secret_key ) {
			return false;
		}

		$response = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
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
	 * Enqueue the Cloudflare Turnstile API script and the WCB integration shim.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue(): void {
		// Cloudflare Turnstile must load from their CDN — cannot be self-hosted.
		wp_enqueue_script(
			'wcb-turnstile-api',
			'https://challenges.cloudflare.com/turnstile/v0/api.js',
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_register_script(
			'wcb-turnstile',
			WCB_URL . 'assets/js/wcb-turnstile.js',
			array( 'wcb-turnstile-api' ),
			WCB_VERSION,
			array( 'in_footer' => true )
		);

		wp_localize_script(
			'wcb-turnstile',
			'wcbAntispam',
			array(
				'provider' => 'turnstile',
				'siteKey'  => $this->site_key,
			)
		);

		wp_enqueue_script( 'wcb-turnstile' );
	}
}
