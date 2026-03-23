<?php
/**
 * GDPR Module — WordPress privacy API exporter and eraser for WCB user data.
 *
 * Registers with WordPress's built-in Tools > Export Personal Data and
 * Tools > Erase Personal Data privacy workflows.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Gdpr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers a data exporter and eraser with the WordPress privacy tools.
 *
 * @since 1.0.0
 */
class GdprModule {

	/**
	 * Boot the module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Register the WCB data exporter with WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['wp-career-board'] = array(
			'exporter_friendly_name' => __( 'WP Career Board', 'wp-career-board' ),
			'callback'               => array( $this, 'export_user_data' ),
		);
		return $exporters;
	}

	/**
	 * Register the WCB data eraser with WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['wp-career-board'] = array(
			'eraser_friendly_name' => __( 'WP Career Board', 'wp-career-board' ),
			'callback'             => array( $this, 'erase_user_data' ),
		);
		return $erasers;
	}

	/**
	 * Export all WCB personal data for the given email address.
	 *
	 * All data is returned in a single call — pagination is not needed given
	 * typical application volumes per candidate.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email_address The user's email address.
	 * @param int    $page          Page number (required by WP callback signature).
	 * @return array
	 */
	public function export_user_data( string $email_address, int $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$user = get_user_by( 'email', $email_address );
		if ( ! $user instanceof \WP_User ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$data = array();

		$apps = get_posts(
			array(
				'post_type'     => 'wcb_application',
				'meta_query'    => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- GDPR export requires fetching all applications for the user.
					array(
						'key'   => '_wcb_candidate_id',
						'value' => $user->ID,
					),
				),
				'numberposts'   => -1,
				'no_found_rows' => true,
			)
		);

		foreach ( $apps as $app ) {
			$job_id = (int) get_post_meta( $app->ID, '_wcb_job_id', true );
			$data[] = array(
				'group_id'    => 'wcb-applications',
				'group_label' => __( 'Job Applications', 'wp-career-board' ),
				'item_id'     => 'application-' . $app->ID,
				'data'        => array(
					array(
						'name'  => __( 'Job', 'wp-career-board' ),
						'value' => $job_id ? get_the_title( $job_id ) : '',
					),
					array(
						'name'  => __( 'Status', 'wp-career-board' ),
						'value' => (string) get_post_meta( $app->ID, '_wcb_status', true ),
					),
					array(
						'name'  => __( 'Submitted', 'wp-career-board' ),
						'value' => $app->post_date,
					),
				),
			);
		}

		$this->log_action( $user->ID, 'export' );

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erase all WCB personal data for the given email address.
	 *
	 * Permanently deletes all application posts and candidate profile meta.
	 * All data is erased in a single call.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email_address The user's email address.
	 * @param int    $page          Page number (required by WP callback signature).
	 * @return array
	 */
	public function erase_user_data( string $email_address, int $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$user = get_user_by( 'email', $email_address );
		if ( ! $user instanceof \WP_User ) {
			return array(
				'items_removed'  => 0,
				'items_retained' => 0,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$removed = 0;

		$apps = get_posts(
			array(
				'post_type'     => 'wcb_application',
				'meta_query'    => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- GDPR erasure requires fetching all applications for the user.
					array(
						'key'   => '_wcb_candidate_id',
						'value' => $user->ID,
					),
				),
				'numberposts'   => -1,
				'no_found_rows' => true,
			)
		);

		foreach ( $apps as $app ) {
			wp_delete_post( $app->ID, true );
			++$removed;
		}

		// Erase candidate profile data.
		delete_user_meta( $user->ID, '_wcb_resume_data' );

		// Delete all bookmark entries — stored as non-unique meta (one row per bookmarked job).
		delete_user_meta( $user->ID, '_wcb_bookmark' );

		$this->log_action( $user->ID, 'erase' );

		return array(
			'items_removed'  => $removed,
			'items_retained' => 0,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Log a GDPR action to the wcb_gdpr_log table.
	 *
	 * The client IP is hashed with SHA-256 for GDPR compliance — not stored in plaintext.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id The user ID the action was performed for.
	 * @param string $action  Action type: 'export' or 'erase'.
	 * @return void
	 */
	private function log_action( int $user_id, string $action ): void {
		global $wpdb;

		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insert into custom wcb_gdpr_log table; no caching needed for write-only audit log.
		$wpdb->insert(
			$wpdb->prefix . 'wcb_gdpr_log',
			array(
				'user_id'    => $user_id,
				'action'     => $action,
				'ip_hash'    => hash( 'sha256', $ip ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}
}
