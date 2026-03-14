<?php
/**
 * Applications postmeta registration.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Applications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers postmeta for the wcb_application CPT so values are exposed in the REST API.
 *
 * @since 1.0.0
 */
final class ApplicationsMeta {

	/**
	 * Boot the meta registration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/**
	 * Register each application postmeta key for REST API exposure.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_meta(): void {
		$meta_fields = array(
			'_wcb_job_id'       => array( 'type' => 'integer' ),
			'_wcb_candidate_id' => array( 'type' => 'integer' ),
			'_wcb_cover_letter' => array( 'type' => 'string' ),
			'_wcb_resume_id'    => array( 'type' => 'integer' ),
			'_wcb_status'       => array( 'type' => 'string' ),
		);

		foreach ( $meta_fields as $key => $schema ) {
			register_post_meta(
				'wcb_application',
				$key,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => $schema['type'],
					'auth_callback' => static function (): bool {
						// phpcs:ignore WordPress.WP.Capabilities.Unknown -- wcb_view_applications is a custom WCB ability/cap.
						return current_user_can( 'wcb_view_applications' );
					},
				)
			);
		}
	}
}
