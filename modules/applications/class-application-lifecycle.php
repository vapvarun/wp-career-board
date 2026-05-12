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
	 * Boot the module.
	 *
	 * @since 1.1.2
	 * @return void
	 */
	public function boot(): void {
		add_action( 'before_delete_post', array( $this, 'on_job_deleted' ), 10, 2 );
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
		if ( ! $post instanceof \WP_Post || 'wcb_job' !== $post->post_type ) {
			return;
		}

		$application_ids = get_posts(
			array(
				'post_type'      => 'wcb_application',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_wcb_job_id',
						'value' => $post_id,
					),
				),
			)
		);

		foreach ( $application_ids as $application_id ) {
			self::transition( (int) $application_id, ApplicationStatus::JOB_REMOVED, 'job_deleted' );
		}
	}

	/**
	 * Transition an application to a new status with audit trail + the standard event.
	 *
	 * Centralises the three steps every status change needs: write the new
	 * status, append to `_wcb_status_log`, fire `wcb_application_status_changed`.
	 * Returns false when the status is unchanged so callers can skip noise.
	 *
	 * @since 1.1.2
	 *
	 * @param int    $application_id Application post ID.
	 * @param string $new_status     Target status slug (use ApplicationStatus constants).
	 * @param string $reason         Optional machine-readable reason (e.g. job_deleted, candidate_withdrew).
	 * @return bool Whether the status actually changed.
	 */
	public static function transition( int $application_id, string $new_status, string $reason = '' ): bool {
		if ( ! ApplicationStatus::is_valid( $new_status ) ) {
			return false;
		}

		$old_status = (string) get_post_meta( $application_id, '_wcb_status', true );
		if ( $old_status === $new_status ) {
			return false;
		}

		update_post_meta( $application_id, '_wcb_status', $new_status );

		$log   = (array) get_post_meta( $application_id, '_wcb_status_log', true );
		$log[] = array(
			'from'   => $old_status,
			'to'     => $new_status,
			'at'     => gmdate( 'c' ),
			'reason' => $reason,
		);
		update_post_meta( $application_id, '_wcb_status_log', $log );

		/** This action is documented in api/endpoints/class-applications-endpoint.php */
		do_action( 'wcb_application_status_changed', $application_id, $new_status, $old_status );

		return true;
	}
}
