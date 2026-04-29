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
	 * Add or sync the Employer role.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function add_employer_role(): void {
		$this->sync_role(
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
	 * Add or sync the Candidate role.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function add_candidate_role(): void {
		$this->sync_role(
			'wcb_candidate',
			__( 'Candidate', 'wp-career-board' ),
			array(
				'read'                           => true,
				'wcb_apply_jobs'                 => true,
				'wcb_manage_resume'              => true,
				'wcb_bookmark_jobs'              => true,
				'wcb_access_candidate_dashboard' => true,
				'wcb_withdraw_application'       => true,
			)
		);
	}

	/**
	 * Add or sync the Board Moderator role.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function add_moderator_role(): void {
		$this->sync_role(
			'wcb_board_moderator',
			__( 'Board Moderator', 'wp-career-board' ),
			array(
				'read'              => true,
				'wcb_moderate_jobs' => true,
			)
		);
	}

	/**
	 * Create a role or top up missing capabilities on an existing one.
	 *
	 * Idempotent: safe to run on every `init` so cap changes shipped in plugin
	 * upgrades reach existing installs without requiring re-activation.
	 *
	 * @since 1.0.2
	 * @param string             $role_slug Role slug.
	 * @param string             $label     Human-readable role label.
	 * @param array<string,bool> $caps      Capability map.
	 * @return void
	 */
	private function sync_role( string $role_slug, string $label, array $caps ): void {
		$role = get_role( $role_slug );
		if ( ! $role ) {
			add_role( $role_slug, $label, $caps );
			return;
		}
		foreach ( $caps as $cap => $grant ) {
			if ( $grant && ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
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
			'wcb_access_candidate_dashboard',
			'wcb_withdraw_application',
		);

		foreach ( $caps as $cap ) {
			if ( ! $admin->has_cap( $cap ) ) {
				$admin->add_cap( $cap );
			}
		}
	}
}
