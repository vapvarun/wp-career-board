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
			'_wcb_guest_name'   => array( 'type' => 'string' ),
			'_wcb_guest_email'  => array( 'type' => 'string' ),
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
						return wp_is_ability_granted( 'wcb/view-applications' );
					},
				)
			);
		}
	}
	/**
	 * Who applied, as the admin screens name them.
	 *
	 * One answer for the Applications list and the dashboard's Recent
	 * Applications: a member by display name, a guest by the name they gave
	 * (flagged so the screen can badge it), and "(deleted)" only when the
	 * member account is gone. The dashboard called every guest "(deleted)".
	 *
	 * @since 1.8.3
	 *
	 * @param int $app_id Application ID.
	 * @return array{name:string, guest:bool, email:string, user:?\WP_User}
	 */
	public static function admin_applicant( int $app_id ): array {
		$user_id = (int) get_post_meta( $app_id, '_wcb_candidate_id', true );
		$user    = $user_id ? get_userdata( $user_id ) : false;
		if ( $user instanceof \WP_User ) {
			return array(
				'name'  => $user->display_name,
				'guest' => false,
				'email' => $user->user_email,
				'user'  => $user,
			);
		}
		if ( 0 === $user_id ) {
			$name = (string) get_post_meta( $app_id, '_wcb_guest_name', true );
			return array(
				'name'  => '' !== $name ? $name : __( '(unknown)', 'wp-career-board' ),
				'guest' => true,
				'email' => (string) get_post_meta( $app_id, '_wcb_guest_email', true ),
				'user'  => null,
			);
		}
		return array(
			'name'  => __( '(deleted)', 'wp-career-board' ),
			'guest' => false,
			'email' => '',
			'user'  => null,
		);
	}
}
