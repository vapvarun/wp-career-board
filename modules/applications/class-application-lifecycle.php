<?php
/**
 * Application lifecycle hooks.
 *
 * @package WP_Career_Board
 * @since   1.1.2
 */

declare( strict_types=1 );

namespace WCB\Modules\Applications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reacts to events that change an application's state of the world.
 *
 * Today: when an employer or admin deletes a wcb_job, every linked
 * wcb_application is transitioned to the `job_removed` status (the
 * candidate's apply history is preserved with the title/company
 * snapshot, but the row clearly signals that the job is gone).
 *
 * @since 1.1.2
 */
final class ApplicationLifecycle {

	/**
	 * Cron hook that marks a deleted job's open applications job_removed.
	 *
	 * @since 1.8.0
	 */
	public const JOB_REMOVED_HOOK = 'wcb_close_deleted_job_applications';

	/**
	 * Applications handled per cron run.
	 *
	 * @since 1.8.0
	 */
	private const BATCH = 200;

	/**
	 * Boot the module.
	 *
	 * @since 1.1.2
	 * @return void
	 */
	public function boot(): void {
		add_action( 'before_delete_post', array( $this, 'on_job_deleted' ), 10, 2 );
		add_action( self::JOB_REMOVED_HOOK, array( self::class, 'close_deleted_job_applications' ) );

		// Site-wide status counts go stale when an application is created,
		// trashed, restored or deleted, not only when its status changes.
		$clear_counts = static function ( int $post_id ): void {
			if ( 'wcb_application' === get_post_type( $post_id ) ) {
				delete_transient( 'wcb_app_status_counts' );
			}
		};
		add_action( 'save_post_wcb_application', $clear_counts );

		// Restoring from Trash puts an application back where it was. Core
		// restores every post as a draft (WP 5.6+), which hid restored
		// applications from every list and count.
		add_filter(
			'wp_untrash_post_status',
			static fn( string $status, int $post_id, string $previous ): string => 'wcb_application' === get_post_type( $post_id ) ? $previous : $status,
			10,
			3
		);
		add_action( 'delete_post', $clear_counts );
	}

	/**
	 * When a wcb_job is permanently deleted, mark every linked application as job_removed.
	 *
	 * Hook fires before WP removes the post + its meta, so we can read the
	 * post type cheaply and run a small targeted query for the linked
	 * applications. Trash doesn't fire this — only permanent deletion does,
	 * which is the right boundary: a trashed job can be restored.
	 *
	 * @since 1.1.2
	 *
	 * @param int           $post_id Post being deleted.
	 * @param \WP_Post|null $post    Post object (WP 5.5+).
	 * @return void
	 */
	public function on_job_deleted( int $post_id, $post = null ): void {
		$post = $post instanceof \WP_Post ? $post : get_post( $post_id );
		if ( $post instanceof \WP_Post && 'wcb_application' === $post->post_type ) {
			/**
			 * Fires before an application post is permanently deleted.
			 *
			 * Since 1.8.0 withdrawing keeps the application (status withdrawn),
			 * so this fires only for a real delete (admin, account erasure).
			 *
			 * @since 1.0.0
			 *
			 * @param int $application_id Application post ID.
			 * @param int $job_id         Job post ID.
			 */
			do_action( 'wcb_application_deleted', $post->ID, (int) get_post_meta( $post->ID, '_wcb_job_id', true ) );
			return;
		}
		if ( ! $post instanceof \WP_Post || 'wcb_job' !== $post->post_type ) {
			return;
		}

		// Background batches: a job with thousands of applicants used to run
		// one transition and one email per applicant inside the delete request.
		if ( ! wp_next_scheduled( self::JOB_REMOVED_HOOK, array( $post_id ) ) ) {
			wp_schedule_single_event( time(), self::JOB_REMOVED_HOOK, array( $post_id ) );
		}
	}

	/**
	 * Mark one batch of a deleted job's open applications job_removed; queue
	 * the next batch until none are left. Each candidate gets the status email
	 * once. Idempotent: a finished application no longer matches.
	 *
	 * @since 1.8.0
	 *
	 * @param int $job_id Deleted job ID.
	 * @return void
	 */
	public static function close_deleted_job_applications( int $job_id ): void {
		$ids = get_posts(
			array(
				'post_type'      => 'wcb_application',
				'post_status'    => 'any',
				'posts_per_page' => self::BATCH,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_wcb_job_id',
						'value' => (string) $job_id,
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => '_wcb_status',
							'value'   => ApplicationStatus::terminal(),
							'compare' => 'NOT IN',
						),
						// Pre-1.1 applications with no status at all.
						array(
							'key'     => '_wcb_status',
							'compare' => 'NOT EXISTS',
						),
					),
				),
			)
		);

		if ( $ids ) {
			update_postmeta_cache( $ids );
		}
		foreach ( $ids as $application_id ) {
			self::transition( (int) $application_id, ApplicationStatus::JOB_REMOVED, 'job_deleted', 0 );
		}

		if ( self::BATCH === count( $ids ) ) {
			wp_schedule_single_event( time(), self::JOB_REMOVED_HOOK, array( $job_id ) );
		}
	}

	/**
	 * The one way an application's status changes.
	 *
	 * Every writer (REST, admin bulk, admin detail screen, CLI, Pro pipeline,
	 * candidate withdraw, job deletion) calls this, so each change is validated,
	 * logged, counted and announced exactly once. A save that does not change
	 * the status returns false and sends nothing.
	 *
	 * Who may set which status is the caller's policy (e.g. the REST endpoint
	 * only lets employers pick ApplicationStatus::employer_actionable()); this
	 * method only refuses slugs that are not statuses at all.
	 *
	 * @since 1.1.2
	 * @since 1.8.0 Added `$actor` and `$note`; clears the status counts cache.
	 *
	 * @param int      $application_id Application post ID.
	 * @param string   $new_status     Target status slug (use ApplicationStatus constants).
	 * @param string   $reason         Machine-readable reason (e.g. job_deleted, candidate_withdrew, pipeline_stage).
	 * @param int|null $actor          User making the change; null = current user, 0 = system.
	 * @param string   $note           Optional human note kept in the log.
	 * @return bool Whether the status actually changed.
	 */
	public static function transition( int $application_id, string $new_status, string $reason = '', ?int $actor = null, string $note = '' ): bool {
		if ( ! ApplicationStatus::is_valid( $new_status ) || 'wcb_application' !== get_post_type( $application_id ) ) {
			return false;
		}

		$old_status = (string) get_post_meta( $application_id, '_wcb_status', true );
		$old_status = '' !== $old_status ? $old_status : ApplicationStatus::SUBMITTED;
		if ( $old_status === $new_status ) {
			return false;
		}

		update_post_meta( $application_id, '_wcb_status', $new_status );

		$entry = array(
			'from'   => $old_status,
			'to'     => $new_status,
			'by'     => null === $actor ? get_current_user_id() : $actor,
			'at'     => gmdate( 'c' ),
			'reason' => $reason,
		);
		if ( '' !== $note ) {
			$entry['note'] = $note;
		}
		$log   = (array) get_post_meta( $application_id, '_wcb_status_log', true );
		$log[] = $entry;
		update_post_meta( $application_id, '_wcb_status_log', array_values( array_filter( $log, 'is_array' ) ) );

		delete_transient( 'wcb_app_status_counts' );

		/**
		 * Fires once after an application's status really changed.
		 *
		 * Arg order is ($id, $old_status, $new_status) at every call site so
		 * consumers (emails, bell, BuddyPress, gamification) read $new_status
		 * reliably. $old_status is never empty: a missing status reads as submitted.
		 *
		 * @since 1.0.0
		 * @since 1.8.0 Added `$reason` and `$actor`.
		 *
		 * @param int    $application_id Application post ID.
		 * @param string $old_status     Previous status slug.
		 * @param string $new_status     New status slug.
		 * @param string $reason         Machine-readable reason.
		 * @param int    $actor          User who made the change, 0 = system.
		 */
		do_action( 'wcb_application_status_changed', $application_id, $old_status, $new_status, $reason, (int) $entry['by'] );

		return true;
	}

	/**
	 * An application's status log, cleaned for display.
	 *
	 * Older writers stored `at` as 'Y-m-d H:i:s' (UTC) and some left a blank
	 * first row; readers get every entry with a status and an ISO 8601 `at`.
	 *
	 * @since 1.8.0
	 *
	 * @param int $application_id Application post ID.
	 * @return array<int,array{from:string,to:string,by:int,at:string,reason:string,note:string}>
	 */
	public static function log( int $application_id ): array {
		$out = array();
		foreach ( (array) get_post_meta( $application_id, '_wcb_status_log', true ) as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['to'] ) ) {
				continue;
			}
			$at    = (string) ( $entry['at'] ?? '' );
			$time  = '' !== $at ? strtotime( str_contains( $at, 'T' ) ? $at : $at . ' UTC' ) : false;
			$out[] = array(
				'from'   => (string) ( $entry['from'] ?? '' ),
				'to'     => (string) $entry['to'],
				'by'     => (int) ( $entry['by'] ?? 0 ),
				'at'     => false !== $time ? gmdate( 'c', $time ) : '',
				'reason' => (string) ( $entry['reason'] ?? '' ),
				'note'   => (string) ( $entry['note'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * Title of the job an application is for, even after the job is deleted.
	 *
	 * Falls back to the snapshot taken at apply time, so "job removed" emails
	 * and notices still name the role.
	 *
	 * @since 1.8.0
	 *
	 * @param int $application_id Application post ID.
	 * @return string Empty only when neither exists.
	 */
	public static function job_title( int $application_id ): string {
		$job_id = (int) get_post_meta( $application_id, '_wcb_job_id', true );
		if ( $job_id > 0 && 'wcb_job' === get_post_type( $job_id ) ) {
			return (string) get_post_field( 'post_title', $job_id );
		}
		return (string) get_post_meta( $application_id, '_wcb_job_title_snapshot', true );
	}
}
