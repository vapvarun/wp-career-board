<?php
/**
 * Registers WCB user roles and capabilities.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages custom user roles and capabilities for the job board.
 *
 * @since 1.0.0
 */
final class Roles {

	/**
	 * Register all custom roles and add admin capabilities.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void {
		$this->add_employer_role();
		$this->add_candidate_role();
		$this->add_moderator_role();
		$this->add_admin_caps();
	}

	/**
	 * Add the Employer role with job-posting capabilities.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function add_employer_role(): void {
		if ( get_role( 'wcb_employer' ) ) {
			return;
		}

		add_role(
			'wcb_employer',
			__( 'Employer', 'wp-career-board' ),
			array(
				'read'                          => true,
				'wcb_post_jobs'                 => true,
				'wcb_manage_company'            => true,
				'wcb_view_applications'         => true,
				'wcb_access_employer_dashboard' => true,
			)
		);
	}

	/**
	 * Add the Candidate role with job-seeking capabilities.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function add_candidate_role(): void {
		if ( get_role( 'wcb_candidate' ) ) {
			return;
		}

		add_role(
			'wcb_candidate',
			__( 'Candidate', 'wp-career-board' ),
			array(
				'read'              => true,
				'wcb_apply_jobs'    => true,
				'wcb_manage_resume' => true,
				'wcb_bookmark_jobs' => true,
			)
		);
	}

	/**
	 * Add the Board Moderator role with moderation capabilities.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function add_moderator_role(): void {
		if ( get_role( 'wcb_board_moderator' ) ) {
			return;
		}

		add_role(
			'wcb_board_moderator',
			__( 'Board Moderator', 'wp-career-board' ),
			array(
				'read'              => true,
				'wcb_moderate_jobs' => true,
			)
		);
	}

	/**
	 * Grant all WCB capabilities to the administrator role.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function add_admin_caps(): void {
		$admin = get_role( 'administrator' );

		if ( ! $admin ) {
			return;
		}

		$caps = array(
			'wcb_post_jobs',
			'wcb_manage_company',
			'wcb_view_applications',
			'wcb_apply_jobs',
			'wcb_manage_resume',
			'wcb_bookmark_jobs',
			'wcb_moderate_jobs',
			'wcb_manage_settings',
			'wcb_view_analytics',
			'wcb_access_employer_dashboard',
		);

		foreach ( $caps as $cap ) {
			$admin->add_cap( $cap );
		}
	}
}
