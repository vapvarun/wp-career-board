<?php
/**
 * Anti-spam module - the honeypot on every submission form.
 *
 * A hidden field real visitors never fill. A site that wants a CAPTCHA or a
 * spam service adds one with its own plugin on the wcb_pre_job_submit,
 * wcb_pre_application_submit and wcb_pre_registration filters.
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
 * Boots the honeypot check.
 *
 * @since 1.0.0
 */
class AntiSpamModule {

	/**
	 * Register all hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_filter( 'wcb_pre_job_submit', array( $this, 'verify_request' ), 10, 2 );
		add_filter( 'wcb_pre_application_submit', array( $this, 'verify_request' ), 10, 2 );
		add_filter( 'wcb_pre_registration', array( $this, 'verify_request' ), 10, 2 );
	}

	/**
	 * Refuse a request whose honeypot field is filled.
	 *
	 * Hooked onto wcb_pre_job_submit, wcb_pre_application_submit and
	 * wcb_pre_registration. Bots that autofill every field populate the hidden
	 * input.
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

		if ( ! empty( $request->get_param( 'hp' ) ) ) {
			return new \WP_Error(
				'wcb_spam',
				__( 'Spam detected.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		return $result;
	}
}
