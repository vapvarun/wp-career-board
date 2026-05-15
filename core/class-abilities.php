<?php
/**
 * Registers WCB abilities via the WordPress Abilities API.
 *
 * Gracefully degrades if the Abilities API is not available (WP < 6.9 or missing).
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
 * Registers context-aware abilities for the job board.
 *
 * @since 1.0.0
 */
final class Abilities {

	/**
	 * Permission chokepoint for every WCB ability.
	 *
	 * Encapsulates the three checks every ability needs:
	 *   1. A logged-in user exists (anonymous requests fail closed).
	 *   2. The user is not under an active employer ban — the
	 *      `_wcb_employer_banned = '1'` user-meta strips every WCB ability
	 *      regardless of cap. Closes the gap where banning an employer
	 *      via admin persisted the meta but never enforced it (Basecamp
	 *      9874928178).
	 *   3. The user has either the specific cap OR the `manage_options`
	 *      admin fallback.
	 *
	 * Cross-cutting checks (rate-limits, soft-deletes, account holds)
	 * extend this method. Do NOT duplicate the shape across individual
	 * permission_callbacks — every ability routes through here.
	 *
	 * @since 1.1.1
	 *
	 * @param string $cap Plugin-specific capability name (e.g. `wcb_post_jobs`).
	 * @return bool
	 */
	private static function gate( string $cap ): bool {
		$user = wp_get_current_user();
		if ( ! $user || 0 === $user->ID ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- domain-specific user-meta gate.
		if ( '1' === (string) get_user_meta( $user->ID, '_wcb_employer_banned', true ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- $cap is a plugin-registered cap.
		return $user->has_cap( $cap ) || $user->has_cap( 'manage_options' );
	}

	/**
	 * Register the WCB ability category.
	 *
	 * Must be called on the `wp_abilities_api_categories_init` action (WP 6.9+).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'wcb',
			array(
				'label'       => __( 'WP Career Board', 'wp-career-board' ),
				'description' => __( 'Abilities for the WP Career Board job board (employer, candidate, moderation, and admin actions).', 'wp-career-board' ),
			)
		);
	}

	/**
	 * Register all WCB abilities.
	 *
	 * Must be called on the `wp_abilities_api_init` action (WP 6.9+).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_employer_abilities();
		$this->register_candidate_abilities();
		$this->register_moderation_ability();
		$this->register_admin_abilities();
	}

	/**
	 * Register employer-related abilities.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_employer_abilities(): void {
		wp_register_ability(
			'wcb/post-jobs',
			array(
				'category'            => 'wcb',
				'label'               => __( 'Post Jobs', 'wp-career-board' ),
				'description'         => __( 'Allows an employer to submit job listings.', 'wp-career-board' ),
				'permission_callback' => static fn(): bool => self::gate( 'wcb_post_jobs' ),
				'execute_callback'    => static function (): bool {
					return true;
				},
			)
		);

		wp_register_ability(
			'wcb/manage-company',
			array(
				'category'            => 'wcb',
				'label'               => __( 'Manage Company Profile', 'wp-career-board' ),
				'description'         => __( 'Allows an employer to edit their company profile.', 'wp-career-board' ),
				'permission_callback' => static fn(): bool => self::gate( 'wcb_manage_company' ),
				'execute_callback'    => static function (): bool {
					return true;
				},
			)
		);

		wp_register_ability(
			'wcb/view-applications',
			array(
				'category'            => 'wcb',
				'label'               => __( 'View Applications', 'wp-career-board' ),
				'description'         => __( 'Allows an employer to view applications for their jobs.', 'wp-career-board' ),
				'permission_callback' => static fn(): bool => self::gate( 'wcb_view_applications' ),
				'execute_callback'    => static function (): bool {
					return true;
				},
			)
		);

		wp_register_ability(
			'wcb/access-employer-dashboard',
			array(
				'category'            => 'wcb',
				'label'               => __( 'Access Employer Dashboard', 'wp-career-board' ),
				'description'         => __( 'Allows an employer to access their management dashboard.', 'wp-career-board' ),
				'permission_callback' => static fn(): bool => self::gate( 'wcb_access_employer_dashboard' ),
				'execute_callback'    => static function (): bool {
					return true;
				},
			)
		);
	}

	/**
	 * Register candidate-related abilities.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_candidate_abilities(): void {
		wp_register_ability(
			'wcb/apply-jobs',
			array(
				'category'            => 'wcb',
				'label'               => __( 'Apply to Jobs', 'wp-career-board' ),
				'description'         => __( 'Allows a candidate to submit job applications.', 'wp-career-board' ),
				'permission_callback' => static fn(): bool => self::gate( 'wcb_apply_jobs' ),
				'execute_callback'    => static function (): bool {
					return true;
				},
			)
		);

		wp_register_ability(
			'wcb/manage-resume',
			array(
				'category'            => 'wcb',
				'label'               => __( 'Manage Resume', 'wp-career-board' ),
				'description'         => __( 'Allows a candidate to create and update their resume.', 'wp-career-board' ),
				'permission_callback' => static fn(): bool => self::gate( 'wcb_manage_resume' ),
				'execute_callback'    => static function (): bool {
					return true;
				},
			)
		);

		wp_register_ability(
			'wcb/bookmark-jobs',
			array(
				'category'            => 'wcb',
				'label'               => __( 'Bookmark Jobs', 'wp-career-board' ),
				'description'         => __( 'Allows a candidate to save jobs to their bookmark list.', 'wp-career-board' ),
				'permission_callback' => static fn(): bool => self::gate( 'wcb_bookmark_jobs' ),
				'execute_callback'    => static function (): bool {
					return true;
				},
			)
		);

		wp_register_ability(
			'wcb/access-candidate-dashboard',
			array(
				'category'            => 'wcb',
				'label'               => __( 'Access Candidate Dashboard', 'wp-career-board' ),
				'description'         => __( 'Allows candidates and site admins to access the candidate dashboard.', 'wp-career-board' ),
				// Candidates only - and site admins for support. The earlier
				// permissive "any logged-in user" check let employers reach
				// the candidate dashboard (their job listings showed up in
				// the candidate apply flow); the dashboard block already
				// gates content for the role, but the ability check should
				// match the product intent. Banned employers stay denied.
				'permission_callback' => static function (): bool {
					$user = wp_get_current_user();
					if ( ! $user || 0 === $user->ID ) {
						return false;
					}
					if ( '1' === (string) get_user_meta( $user->ID, '_wcb_employer_banned', true ) ) {
						return false;
					}
					if ( in_array( 'administrator', (array) $user->roles, true ) ) {
						return true;
					}
					return in_array( 'wcb_candidate', (array) $user->roles, true );
				},
				'execute_callback'    => static function (): bool {
					return true;
				},
			)
		);

		wp_register_ability(
			'wcb/withdraw-application',
			array(
				'category'            => 'wcb',
				'label'               => __( 'Withdraw Application', 'wp-career-board' ),
				'description'         => __( 'Allows a candidate to retract a previously submitted job application.', 'wp-career-board' ),
				'permission_callback' => static fn(): bool => self::gate( 'wcb_withdraw_application' ),
				'execute_callback'    => static function (): bool {
					return true;
				},
			)
		);
	}

	/**
	 * Register the board-scoped moderation ability.
	 *
	 * When board_id context is provided, checks _wcb_assigned_boards usermeta.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_moderation_ability(): void {
		wp_register_ability(
			'wcb/moderate-jobs',
			array(
				'category'            => 'wcb',
				'label'               => __( 'Moderate Jobs', 'wp-career-board' ),
				'description'         => __( 'Allows a moderator or admin to approve or reject job listings.', 'wp-career-board' ),
				'permission_callback' => static fn(): bool => self::gate( 'wcb_moderate_jobs' ),
				'execute_callback'    => static function (): bool {
					return true;
				},
			)
		);
	}

	/**
	 * Register admin-only abilities.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_admin_abilities(): void {
		wp_register_ability(
			'wcb/manage-settings',
			array(
				'category'            => 'wcb',
				'label'               => __( 'Manage Settings', 'wp-career-board' ),
				'description'         => __( 'Allows an admin to configure the WP Career Board plugin.', 'wp-career-board' ),
				'permission_callback' => static fn(): bool => self::gate( 'manage_options' ),
				'execute_callback'    => static function (): bool {
					return true;
				},
			)
		);

		// Reserved for Pro analytics feature.
		wp_register_ability(
			'wcb/view-analytics',
			array(
				'category'            => 'wcb',
				'label'               => __( 'View Analytics', 'wp-career-board' ),
				'description'         => __( 'Allows an admin to view job board analytics and reports.', 'wp-career-board' ),
				'permission_callback' => static fn(): bool => self::gate( 'manage_options' ),
				'execute_callback'    => static function (): bool {
					return true;
				},
			)
		);
	}
}
