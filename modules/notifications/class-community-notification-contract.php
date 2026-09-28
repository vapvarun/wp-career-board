<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName -- hyphenated file name matches every sibling in this module.
/**
 * Community notification contract — the shared payload BuddyNext (or any
 * centralised notification center) reads off `wcb_notification_created`'s
 * second argument, plus the types/visibility/removal seams the contract
 * requires. One helper for both Free (email dispatch) and Pro (bell insert)
 * so the payload is built in exactly one place.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the contract payload and answers the types/visibility filters for
 * every Free (email-triggered) community notification.
 *
 * @since 1.8.0
 */
final class CommunityNotificationContract {

	/**
	 * The ONE vocabulary of Career Board notification types, shared by Free's
	 * emails and Pro's bell: email id => event type. Pro's bell already stores
	 * these event names, so Free maps onto them; one event has one type (one
	 * settings switch) whichever side announces it.
	 *
	 * The candidate's own "application submitted" confirmation maps to the same
	 * event as the employer's alert and carries the candidate as actor, so a
	 * community inbox drops it as a self-notification (the email still goes).
	 *
	 * @since 1.8.0
	 */
	public const EMAIL_EVENTS = array(
		'application-received'       => 'application_submitted',
		'application-confirmation'   => 'application_submitted',
		'application-status-changed' => 'application_status_changed',
		'application-not-selected'   => 'application_status_changed',
		'application-withdrawn'      => 'application_withdrawn',
		'deadline-reminder'          => 'deadline_reminder',
		'job-approved'               => 'job_approved',
		'job-rejected'               => 'job_rejected',
		'job-expired'                => 'job_expired',
		'job-expiring'               => 'job_expiring',
	);

	/**
	 * Wire the types + visibility filters. Called once from
	 * NotificationsModule::boot().
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wcb_community_notification_types', array( self::class, 'filter_types' ) );
		add_filter( 'wcb_community_notification_visible', array( self::class, 'filter_visible' ), 10, 3 );
	}

	/**
	 * Build the contract payload for `wcb_notification_created`'s second
	 * argument. Callers only build one when the notification is genuinely
	 * about a community object — pass no `object_type` (the default) for a
	 * transactional or admin-only email and nothing is sent.
	 *
	 * @since 1.8.0
	 *
	 * @param int                  $recipient_id WP user ID that should see it.
	 * @param string               $type         The plugin's own subtype slug (an email id or bell event_type).
	 * @param string               $message      Translated, plain-text message (the email subject).
	 * @param string               $url          Where a tap goes.
	 * @param array<string, mixed> $context      Optional: object_type, object_id, actor_id, group_key.
	 * @return array<string, mixed>|null Null when there is no community object to report.
	 */
	public static function build( int $recipient_id, string $type, string $message, string $url, array $context = array() ): ?array {
		$object_type = sanitize_key( (string) ( $context['object_type'] ?? '' ) );
		if ( '' === $object_type || $recipient_id <= 0 ) {
			return null;
		}

		$object_id = (int) ( $context['object_id'] ?? 0 );
		$actor_id  = (int) ( $context['actor_id'] ?? 0 );

		return array(
			'recipient_id'    => $recipient_id,
			'type'            => self::EMAIL_EVENTS[ $type ] ?? $type,
			'actor_id'        => $actor_id,
			'object_type'     => $object_type,
			'object_id'       => $object_id,
			'message'         => wp_strip_all_tags( $message ),
			'url'             => $url,
			'group_key'       => isset( $context['group_key'] ) ? sanitize_key( (string) $context['group_key'] ) : sanitize_key( self::EMAIL_EVENTS[ $type ] ?? $type ) . '_' . $object_id,
			'notification_id' => (int) ( $context['notification_id'] ?? 0 ),
		);
	}

	/**
	 * Declares Free's own notification types on the shared catalogue. Pro
	 * declares its own bell-only types on the same filter separately.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, array<string, mixed>> $types Types declared so far.
	 * @return array<string, array<string, mixed>>
	 */
	public static function filter_types( array $types ): array {
		$types['application_submitted']      = array(
			'label'       => __( 'New application received', 'wp-career-board' ),
			'description' => __( 'A candidate applied for one of your jobs.', 'wp-career-board' ),
			'default_on'  => true,
		);
		$types['application_status_changed'] = array(
			'label'       => __( 'Application status updated', 'wp-career-board' ),
			'description' => __( 'The status of your application changed.', 'wp-career-board' ),
			'default_on'  => true,
		);
		$types['application_withdrawn']      = array(
			'label'       => __( 'Candidate withdrew their application', 'wp-career-board' ),
			'description' => __( 'A candidate withdrew their application to your job.', 'wp-career-board' ),
			'default_on'  => true,
		);
		$types['deadline_reminder']          = array(
			'label'       => __( 'Saved job deadline approaching', 'wp-career-board' ),
			'description' => __( 'A job you applied to is closing soon.', 'wp-career-board' ),
			'default_on'  => true,
		);
		$types['job_approved']               = array(
			'label'       => __( 'Job approved', 'wp-career-board' ),
			'description' => __( 'Your job listing was approved and is now live.', 'wp-career-board' ),
			'default_on'  => true,
		);
		$types['job_expired']                = array(
			'label'       => __( 'Job listing expired', 'wp-career-board' ),
			'description' => __( 'Your job listing has expired.', 'wp-career-board' ),
			'default_on'  => true,
		);
		$types['job_expiring']               = array(
			'label'       => __( 'Job listing expiring soon', 'wp-career-board' ),
			'description' => __( 'Your job listing closes to applications soon.', 'wp-career-board' ),
			'default_on'  => true,
		);
		$types['job_rejected']               = array(
			'label'       => __( 'Job listing rejected', 'wp-career-board' ),
			'description' => __( 'Your job listing was not approved.', 'wp-career-board' ),
			'default_on'  => true,
		);
		return $types;
	}

	/**
	 * Answers `wcb_community_notification_visible` for 'job' and
	 * 'application' bell rows: hidden when trashed, when a candidate
	 * withdrew (the employer's copy only — the candidate keeps their own
	 * record), or when a job is not yet public and the viewer is not its
	 * employer.
	 *
	 * @since 1.8.0
	 *
	 * @param array<int|string, bool>                    $visible Every key defaults true.
	 * @param int                                         $viewer_id Recipient the bell page belongs to.
	 * @param array<int|string, array<string, mixed>>     $targets Key => object_type/object_id/actor_id.
	 * @return array<int|string, bool>
	 */
	public static function filter_visible( array $visible, int $viewer_id, array $targets ): array {
		$job_ids = array();
		$app_ids = array();
		foreach ( $targets as $target ) {
			if ( 'job' === ( $target['object_type'] ?? '' ) ) {
				$job_ids[] = (int) $target['object_id'];
			} elseif ( 'application' === ( $target['object_type'] ?? '' ) ) {
				$app_ids[] = (int) $target['object_id'];
			}
		}

		$applications = self::posts_by_id( 'wcb_application', $app_ids );
		$jobs         = self::posts_by_id( 'wcb_job', $job_ids );

		// An application's visibility also depends on ITS job, which may not
		// already be in $jobs (a target only ever names one object).
		$linked_job_ids = array();
		foreach ( $applications as $app ) {
			$linked_job_ids[] = (int) get_post_meta( $app->ID, '_wcb_job_id', true );
		}
		$jobs += self::posts_by_id( 'wcb_job', array_diff( $linked_job_ids, array_keys( $jobs ) ) );

		foreach ( $targets as $key => $target ) {
			$object_type = (string) ( $target['object_type'] ?? '' );
			if ( 'job' === $object_type ) {
				$visible[ $key ] = self::job_visible( $jobs[ (int) $target['object_id'] ] ?? null, $viewer_id );
			} elseif ( 'application' === $object_type ) {
				$visible[ $key ] = self::application_visible( $applications[ (int) $target['object_id'] ] ?? null, $jobs, $viewer_id );
			}
		}

		return $visible;
	}

	/**
	 * One batched lookup per post type per bell page.
	 *
	 * @since 1.8.0
	 *
	 * @param string        $post_type Post type slug.
	 * @param array<int,int> $ids      Post IDs.
	 * @return array<int,\WP_Post>
	 */
	private static function posts_by_id( string $post_type, array $ids ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( ! $ids ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'post__in'       => $ids,
				'posts_per_page' => count( $ids ),
				'no_found_rows'  => true,
				'orderby'        => 'none',
			)
		);

		$out = array();
		foreach ( $posts as $post ) {
			$out[ $post->ID ] = $post;
		}
		return $out;
	}

	/**
	 * A job bell row's visibility: gone once trashed, otherwise whatever
	 * `EmployersEndpoint::owner_visible_statuses()` already allows the viewer
	 * to see — the same allowlist the employer dashboard and admin queries
	 * use, so a lifecycle status added there is not missed here.
	 *
	 * @since 1.8.0
	 *
	 * @param \WP_Post|null $job       The job, or null when it no longer exists.
	 * @param int           $viewer_id Recipient the bell row belongs to.
	 * @return bool
	 */
	private static function job_visible( ?\WP_Post $job, int $viewer_id ): bool {
		if ( ! $job instanceof \WP_Post || 'trash' === $job->post_status ) {
			return false;
		}
		$is_owner = (int) $job->post_author === $viewer_id;
		return in_array( $job->post_status, \WCB\Api\Endpoints\EmployersEndpoint::owner_visible_statuses( $is_owner ), true );
	}

	/**
	 * An application bell row's visibility: gone once trashed (or its job
	 * is), always visible to the candidate it belongs to, and hidden from
	 * the employer once the candidate withdraws it.
	 *
	 * @since 1.8.0
	 *
	 * @param \WP_Post|null      $application The application, or null.
	 * @param array<int,\WP_Post> $jobs       Job lookup, keyed by job ID.
	 * @param int                $viewer_id   Recipient the bell row belongs to.
	 * @return bool
	 */
	private static function application_visible( ?\WP_Post $application, array $jobs, int $viewer_id ): bool {
		if ( ! $application instanceof \WP_Post || 'trash' === $application->post_status ) {
			return false;
		}

		$job = $jobs[ (int) get_post_meta( $application->ID, '_wcb_job_id', true ) ] ?? null;
		if ( ! $job instanceof \WP_Post || 'trash' === $job->post_status ) {
			return false;
		}

		if ( (int) $job->post_author === $viewer_id ) {
			return \WCB\Modules\Applications\ApplicationStatus::WITHDRAWN !== (string) get_post_meta( $application->ID, '_wcb_status', true );
		}

		return (int) get_post_meta( $application->ID, '_wcb_candidate_id', true ) === $viewer_id;
	}
}
