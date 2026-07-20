<?php
/**
 * Member-initiated account deletion (Apple 5.1.1(v)).
 *
 * A member can delete their own account from the app. Deletion is scheduled
 * with a grace period (so a change of mind is possible), the account is
 * suspended and its credentials revoked for the window, and a daily cron
 * finalises anything past its date by ending at wp_delete_user() — which fires
 * the existing delete_user cascade (DataCleanup + core), so this does not
 * re-implement any purge.
 *
 * @package WP_Career_Board
 * @since   1.7.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Account;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedules, cancels and finalises self-service account deletion.
 *
 * @since 1.7.0
 */
class AccountDeletionService {

	/**
	 * User-meta holding the scheduled deletion timestamp (unix, UTC).
	 *
	 * @var string
	 */
	public const META_SCHEDULED = '_wcb_deletion_scheduled_at';

	/**
	 * Daily cron hook that finalises due deletions.
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'wcb_process_account_deletions';

	/**
	 * Default grace period in days.
	 *
	 * @var int
	 */
	private const DEFAULT_GRACE_DAYS = 14;

	/**
	 * Register the finaliser cron.
	 *
	 * @since 1.7.0
	 * @return void
	 */
	public function boot(): void {
		add_action( self::CRON_HOOK, array( $this, 'process_due' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Grace period in days (0 = delete immediately).
	 *
	 * @since 1.7.0
	 * @return int
	 */
	public function grace_days(): int {
		return max( 0, (int) apply_filters( 'wcb_account_deletion_grace_days', self::DEFAULT_GRACE_DAYS ) );
	}

	/**
	 * Request deletion of a member's own account.
	 *
	 * @since 1.7.0
	 *
	 * @param \WP_User $user     The account to delete (always the caller).
	 * @param string   $password The account password, re-checked here.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function request( \WP_User $user, string $password ) {
		if ( user_can( $user, 'manage_options' ) ) {
			return new \WP_Error(
				'wcb_delete_forbidden',
				__( 'Administrator accounts cannot be deleted from the app.', 'wp-career-board' ),
				array( 'status' => 403 )
			);
		}

		if ( ! $this->password_ok( $user, $password ) ) {
			return new \WP_Error(
				'wcb_delete_bad_password',
				__( 'That password is incorrect.', 'wp-career-board' ),
				array( 'status' => 403 )
			);
		}

		$grace = $this->grace_days();

		if ( 0 === $grace ) {
			$this->execute( $user->ID );
			return array(
				'status'  => 'deleted',
				'message' => __( 'Your account has been deleted.', 'wp-career-board' ),
			);
		}

		$when = time() + ( $grace * DAY_IN_SECONDS );
		update_user_meta( $user->ID, self::META_SCHEDULED, $when );
		// Reuse the one ban flag so the account is locked during the window.
		update_user_meta( $user->ID, '_wcb_employer_banned', '1' );
		$this->revoke_credentials( $user->ID );

		do_action( 'wcb_account_deletion_requested', $user->ID, $when );

		return array(
			'status'        => 'scheduled',
			'scheduled_for' => gmdate( 'c', $when ),
			'grace_days'    => $grace,
			'message'       => sprintf(
				/* translators: %d: number of days. */
				__( 'Your account will be deleted in %d days. Sign in again before then to cancel.', 'wp-career-board' ),
				$grace
			),
		);
	}

	/**
	 * Current deletion status for a member.
	 *
	 * @since 1.7.0
	 *
	 * @param \WP_User $user The account.
	 * @return array<string,mixed>
	 */
	public function status( \WP_User $user ): array {
		$when = (int) get_user_meta( $user->ID, self::META_SCHEDULED, true );

		if ( $when <= 0 ) {
			return array( 'status' => 'active' );
		}

		return array(
			'status'        => 'scheduled',
			'scheduled_for' => gmdate( 'c', $when ),
		);
	}

	/**
	 * Cancel a scheduled deletion (a change of mind).
	 *
	 * Must never be gated by the suspension the schedule itself applied, or the
	 * grace period becomes a one-way door.
	 *
	 * @since 1.7.0
	 *
	 * @param \WP_User $user The account.
	 * @return array<string,mixed>
	 */
	public function cancel( \WP_User $user ): array {
		if ( (int) get_user_meta( $user->ID, self::META_SCHEDULED, true ) <= 0 ) {
			return array( 'status' => 'active' );
		}

		delete_user_meta( $user->ID, self::META_SCHEDULED );
		delete_user_meta( $user->ID, '_wcb_employer_banned' );

		do_action( 'wcb_account_deletion_cancelled', $user->ID );

		return array(
			'status'  => 'active',
			'message' => __( 'Your account deletion has been cancelled.', 'wp-career-board' ),
		);
	}

	/**
	 * Finalise every deletion whose date has passed.
	 *
	 * @since 1.7.0
	 * @return void
	 */
	public function process_due(): void {
		$user_ids = get_users(
			array(
				'meta_key'     => self::META_SCHEDULED, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- indexed, bounded by number.
				'meta_compare' => 'EXISTS',
				'number'       => 100,
				'fields'       => 'ID',
			)
		);

		$now = time();
		foreach ( $user_ids as $user_id ) {
			$when = (int) get_user_meta( (int) $user_id, self::META_SCHEDULED, true );
			if ( $when > 0 && $when <= $now ) {
				$this->execute( (int) $user_id );
			}
		}
	}

	/**
	 * Actually remove the account. Ends at wp_delete_user() so the existing
	 * delete_user cascade runs; on multisite, detach from this site instead of
	 * destroying the network identity.
	 *
	 * @since 1.7.0
	 *
	 * @param int $user_id User to delete.
	 * @return void
	 */
	private function execute( int $user_id ): void {
		do_action( 'wcb_account_deletion_executing', $user_id );

		require_once ABSPATH . 'wp-admin/includes/user.php';

		if ( is_multisite() && count( get_blogs_of_user( $user_id ) ) > 1 ) {
			remove_user_from_blog( $user_id, get_current_blog_id() );
			return;
		}

		wp_delete_user( $user_id );
	}

	/**
	 * Whether the supplied password matches (SSO/passwordless accounts opt out).
	 *
	 * @since 1.7.0
	 *
	 * @param \WP_User $user     The account.
	 * @param string   $password Supplied password.
	 * @return bool
	 */
	private function password_ok( \WP_User $user, string $password ): bool {
		$required = (bool) apply_filters( 'wcb_account_deletion_password_required', true, $user->ID );

		if ( ! $required || '' === $user->user_pass ) {
			return true;
		}

		return wp_check_password( $password, $user->user_pass, $user->ID );
	}

	/**
	 * Revoke every credential so the suspended window cannot be used.
	 *
	 * @since 1.7.0
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function revoke_credentials( int $user_id ): void {
		if ( class_exists( '\WP_Application_Passwords' ) ) {
			\WP_Application_Passwords::delete_all_application_passwords( $user_id );
		}
		\WP_Session_Tokens::get_instance( $user_id )->destroy_all();
	}
}
