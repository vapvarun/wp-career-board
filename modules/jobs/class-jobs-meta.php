<?php
/**
 * Jobs postmeta helpers.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers postmeta for the wcb_job CPT so values are exposed in the REST API.
 *
 * @since 1.0.0
 */
final class JobsMeta {

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
	 * Register each job postmeta key for REST API exposure.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_meta(): void {
		$meta_fields = array(
			'_wcb_deadline'        => array( 'type' => 'string' ),
			'_wcb_salary_min'      => array( 'type' => 'number' ),
			'_wcb_salary_max'      => array( 'type' => 'number' ),
			'_wcb_salary_currency' => array( 'type' => 'string' ),
			'_wcb_remote'          => array( 'type' => 'string' ),
			'_wcb_board_id'        => array( 'type' => 'integer' ),
		);

		foreach ( $meta_fields as $key => $schema ) {
			register_post_meta(
				'wcb_job',
				$key,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => $schema['type'],
					'auth_callback' => static function (): bool {
						// phpcs:ignore WordPress.WP.Capabilities.Unknown -- wcb_post_jobs is a custom WCB ability/cap.
						return current_user_can( 'wcb_post_jobs' );
					},
				)
			);
		}
	}
}
