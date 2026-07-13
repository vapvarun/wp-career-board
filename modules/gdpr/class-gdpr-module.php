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
	 * Export a page of WCB personal data for the given email address.
	 *
	 * Implements the WordPress privacy exporter's page-based contract: WP calls
	 * this repeatedly with an incrementing $page until `done` is true. Paging is
	 * required — a candidate can have thousands of applications and a single
	 * unbounded fetch would time out the export.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email_address The user's email address.
	 * @param int    $page          Page number supplied by the privacy tool.
	 * @return array
	 */
	public function export_user_data( string $email_address, int $page = 1 ): array {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );
		if ( ! $user instanceof \WP_User ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$page     = max( 1, $page );
		$per_page = 100;
		$data     = array();

		$apps = get_posts(
			array(
				'post_type'      => 'wcb_application',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- candidate-scoped lookup; bounded by posts_per_page.
					array(
						'key'   => '_wcb_candidate_id',
						'value' => $user->ID,
					),
				),
			)
		);

		// Prime the linked job posts once so the per-row get_the_title() below is
		// a cache hit instead of one post query per application.
		$wcb_job_ids = array();
		foreach ( $apps as $app ) {
			$wcb_jid = (int) get_post_meta( $app->ID, '_wcb_job_id', true );
			if ( $wcb_jid > 0 ) {
				$wcb_job_ids[] = $wcb_jid;
			}
		}
		if ( $wcb_job_ids ) {
			_prime_post_caches( array_unique( $wcb_job_ids ), false, false );
		}

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

		// Notifications log — page in lockstep with the applications query above
		// using the same $page/$per_page. Rows in this table are typically far
		// fewer than applications, so pages beyond its last one simply come back
		// empty; `done` below only flips once both queries are drained.
		$notification_offset = ( $page - 1 ) * $per_page;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off privacy export, not a hot path.
		$notifications = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_type, channel, status, sent_at FROM {$wpdb->prefix}wcb_notifications_log WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user->ID,
				$per_page,
				$notification_offset
			),
			ARRAY_A
		);

		foreach ( (array) $notifications as $index => $notification ) {
			$data[] = array(
				'group_id'    => 'wcb-notifications',
				'group_label' => __( 'Notifications', 'wp-career-board' ),
				'item_id'     => 'notification-' . $notification_offset . '-' . $index,
				'data'        => array(
					array(
						'name'  => __( 'Event Type', 'wp-career-board' ),
						'value' => (string) $notification['event_type'],
					),
					array(
						'name'  => __( 'Channel', 'wp-career-board' ),
						'value' => (string) $notification['channel'],
					),
					array(
						'name'  => __( 'Status', 'wp-career-board' ),
						'value' => (string) $notification['status'],
					),
					array(
						'name'  => __( 'Sent', 'wp-career-board' ),
						'value' => (string) $notification['sent_at'],
					),
				),
			);
		}

		$done = count( $apps ) < $per_page && count( $notifications ) < $per_page;
		if ( $done ) {
			$this->log_action( $user->ID, 'export' );
		}

		return array(
			'data' => $data,
			'done' => $done,
		);
	}

	/**
	 * Erase a batch of WCB personal data for the given email address.
	 *
	 * Implements the WordPress privacy eraser's page-based contract. Because the
	 * read is destructive (each call deletes the rows it fetches, shrinking the
	 * set from the front), we always read the first page and report `done` based
	 * on whether a full batch came back — not on $page. WP re-invokes the
	 * callback until `done` is true, so a candidate with thousands of
	 * applications is erased across batches instead of one timeout-prone call.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email_address The user's email address.
	 * @param int    $page          Page number from the privacy tool (unused — destructive read always takes page 1).
	 * @return array
	 */
	public function erase_user_data( string $email_address, int $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- destructive read always reads page 1; signature fixed by the privacy API.
		global $wpdb;

		$user = get_user_by( 'email', $email_address );
		if ( ! $user instanceof \WP_User ) {
			return array(
				'items_removed'  => 0,
				'items_retained' => 0,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$per_page = 100;
		$removed  = 0;

		$apps = get_posts(
			array(
				'post_type'      => 'wcb_application',
				'posts_per_page' => $per_page,
				'paged'          => 1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- candidate-scoped lookup; bounded by posts_per_page.
					array(
						'key'   => '_wcb_candidate_id',
						'value' => $user->ID,
					),
				),
			)
		);

		foreach ( $apps as $app_id ) {
			wp_delete_post( (int) $app_id, true );
			++$removed;
		}
		$apps_done = count( $apps ) < $per_page;

		// Notifications log rows are transient UX/delivery notifications, not a
		// legal or financial record — deletion is correct here (mirrors Pro's
		// T6 eraser, which deletes wp_wcb_notifications outright rather than
		// anonymizing it). Destructive read of the first page, same pattern as
		// the applications query above: each pass shrinks the front of the set,
		// so `done` is earned by whether a full batch came back, not by $page.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bulk erase of a custom table; bounded by LIMIT.
		$notification_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}wcb_notifications_log WHERE user_id = %d ORDER BY id ASC LIMIT %d",
				$user->ID,
				$per_page
			)
		);

		if ( $notification_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $notification_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- bulk erase of a custom table; ids bound via $wpdb->prepare().
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wcb_notifications_log WHERE id IN ({$placeholders})", $notification_ids ) );
			$removed += count( $notification_ids );
		}
		$notifications_done = count( $notification_ids ) < $per_page;

		$done = $apps_done && $notifications_done;

		$items_retained = 0;
		$messages       = array();

		if ( $done ) {
			// Profile-level meta is cleared once, on the final batch, so a
			// multi-page erase doesn't repeat the work or double-log.
			delete_user_meta( $user->ID, '_wcb_resume_data' );

			// Bookmarks are stored as non-unique meta (one row per bookmarked job).
			delete_user_meta( $user->ID, '_wcb_bookmark' );

			// wcb_job_views has no user_id (or any other person-linking) column —
			// only a one-way SHA-256 hash of the viewer's IP address at view time
			// (core/class-install.php). There is no reliable way to attribute a
			// row in this table to this specific requester: the hash cannot be
			// reversed, and matching on a hash of the requester's *current* IP
			// would both miss their historical views (IP changes) and risk
			// touching other people's rows on a shared/dynamic IP. It is already
			// anonymous aggregate analytics with no linkage to a person, so it is
			// out of scope for this eraser — nothing to erase or anonymize here.

			// wcb_gdpr_log is the audit trail of GDPR requests themselves —
			// explicit RETAIN, not erased. Legal basis: compliance/audit record
			// of privacy-request handling (DATA-LIFECYCLE.md §9a); erasing it
			// would destroy the evidence that this very request was honoured.
			$items_retained = 1;
			$messages[]     = __( 'GDPR request log entries are retained for compliance auditing.', 'wp-career-board' );

			$this->log_action( $user->ID, 'erase' );
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => $done,
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
