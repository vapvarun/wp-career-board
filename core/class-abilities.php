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
			array( 'label' => __( 'WP Career Board', 'wp-career-board' ) )
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
			'wcb_post_jobs',
			array(
				'category' => 'wcb',
				'label'    => __( 'Post Jobs', 'wp-career-board' ),
				'callback' => static function ( $user ): bool {
					return $user && ( $user->has_cap( 'wcb_post_jobs' ) || $user->has_cap( 'manage_options' ) );
				},
			)
		);

		wp_register_ability(
			'wcb_manage_company',
			array(
				'category' => 'wcb',
				'label'    => __( 'Manage Company Profile', 'wp-career-board' ),
				'callback' => static function ( $user ): bool {
					return $user && ( $user->has_cap( 'wcb_manage_company' ) || $user->has_cap( 'manage_options' ) );
				},
			)
		);

		wp_register_ability(
			'wcb_view_applications',
			array(
				'category' => 'wcb',
				'label'    => __( 'View Applications', 'wp-career-board' ),
				'callback' => static function ( $user ): bool {
					return $user && ( $user->has_cap( 'wcb_view_applications' ) || $user->has_cap( 'manage_options' ) );
				},
			)
		);

		wp_register_ability(
			'wcb_access_employer_dashboard',
			array(
				'category' => 'wcb',
				'label'    => __( 'Access Employer Dashboard', 'wp-career-board' ),
				'callback' => static function ( $user ): bool {
					return $user && ( $user->has_cap( 'wcb_access_employer_dashboard' ) || $user->has_cap( 'manage_options' ) );
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
			'wcb_apply_jobs',
			array(
				'category' => 'wcb',
				'label'    => __( 'Apply to Jobs', 'wp-career-board' ),
				'callback' => static function ( $user ): bool {
					return $user && ( $user->has_cap( 'wcb_apply_jobs' ) || $user->has_cap( 'manage_options' ) );
				},
			)
		);

		wp_register_ability(
			'wcb_manage_resume',
			array(
				'category' => 'wcb',
				'label'    => __( 'Manage Resume', 'wp-career-board' ),
				'callback' => static function ( $user ): bool {
					return $user && ( $user->has_cap( 'wcb_manage_resume' ) || $user->has_cap( 'manage_options' ) );
				},
			)
		);

		wp_register_ability(
			'wcb_bookmark_jobs',
			array(
				'category' => 'wcb',
				'label'    => __( 'Bookmark Jobs', 'wp-career-board' ),
				'callback' => static function ( $user ): bool {
					return $user && ( $user->has_cap( 'wcb_bookmark_jobs' ) || $user->has_cap( 'manage_options' ) );
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
			'wcb_moderate_jobs',
			array(
				'category' => 'wcb',
				'label'    => __( 'Moderate Jobs', 'wp-career-board' ),
				'callback' => static function ( $user, $args = array() ): bool {
					if ( ! $user ) {
						return false;
					}

					if ( $user->has_cap( 'manage_options' ) ) {
						return true;
					}

					if ( ! $user->has_cap( 'wcb_moderate_jobs' ) ) {
						return false;
					}

					$args = is_array( $args ) ? $args : array();

					if ( ! empty( $args['board_id'] ) ) {
						$assigned = (array) get_user_meta( $user->ID, '_wcb_assigned_boards', true );
						$assigned = array_filter( array_map( 'intval', $assigned ) );
						return in_array( (int) $args['board_id'], $assigned, true );
					}

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
			'wcb_manage_settings',
			array(
				'category' => 'wcb',
				'label'    => __( 'Manage Settings', 'wp-career-board' ),
				'callback' => static function ( $user ): bool {
					return $user && $user->has_cap( 'manage_options' );
				},
			)
		);

		wp_register_ability(
			'wcb_view_analytics',
			array(
				'category' => 'wcb',
				'label'    => __( 'View Analytics', 'wp-career-board' ),
				'callback' => static function ( $user ): bool {
					return $user && $user->has_cap( 'manage_options' );
				},
			)
		);
	}
}
