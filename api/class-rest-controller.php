<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated name follows project autoloader convention.
/**
 * Abstract REST controller base class for all WCB endpoints.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base REST controller that all WCB endpoint classes extend.
 *
 * Provides shared helpers: ability checks (via the WordPress Abilities API),
 * standard permission errors, and GDPR-safe job-view recording.
 *
 * @since 1.0.0
 */
abstract class RestController extends \WP_REST_Controller {

	/**
	 * WCB REST namespace.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $namespace = 'wcb/v1';

	/**
	 * Check an ability via the WordPress Abilities API with cap fallback.
	 *
	 * This is the ONLY place in the codebase where current_user_can() is
	 * permitted — as a graceful fallback when the Abilities API is absent.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ability Ability slug.
	 * @param array  $args    Optional context args (e.g. ['board_id' => 3]).
	 * @return bool
	 */
	protected function check_ability( string $ability, array $args = array() ): bool {
		if ( function_exists( 'wp_is_ability_granted' ) ) {
			return wp_is_ability_granted( $ability, wp_get_current_user(), $args );
		}

		// Graceful fallback: treat ability slug as a capability name.
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- ability slug used as fallback cap.
		return current_user_can( $ability );
	}

	/**
	 * Get the current user ID.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	protected function current_user_id(): int {
		return get_current_user_id();
	}

	/**
	 * Standard permission error response.
	 *
	 * Returns 401 for unauthenticated requests, 403 for authenticated-but-forbidden.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Error
	 */
	protected function permission_error(): \WP_Error {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'wcb_unauthorized',
				__( 'Authentication is required to perform this action.', 'wp-career-board' ),
				array( 'status' => 401 )
			);
		}
		return new \WP_Error(
			'wcb_forbidden',
			__( 'You do not have permission to perform this action.', 'wp-career-board' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Record a job view in the wcb_job_views table.
	 *
	 * IP is hashed (SHA-256) for GDPR compliance — not stored in plaintext.
	 *
	 * @since 1.0.0
	 *
	 * @param int $job_id Post ID of the wcb_job.
	 * @return void
	 */
	protected function record_job_view( int $job_id ): void {
		global $wpdb;

		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insert into custom wcb_job_views table; no caching needed for write-only analytics.
		$wpdb->insert(
			$wpdb->prefix . 'wcb_job_views',
			array(
				'job_id'    => $job_id,
				'viewed_at' => current_time( 'mysql' ),
				'ip_hash'   => hash( 'sha256', $ip ),
			),
			array( '%d', '%s', '%s' )
		);
	}
}
