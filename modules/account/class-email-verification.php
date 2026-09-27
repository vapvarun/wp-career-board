<?php
/**
 * Email verification for accounts created through the plugin's sign-up forms.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Account;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * When the `require_email_verification` setting is on (default for new
 * installs), a new candidate or employer account stays signed out until the
 * link in the "Confirm your email" message is opened. Signing in, including
 * the mobile app's password exchange, is refused until then. Existing users
 * and accounts created elsewhere (wp-admin, other plugins) are unaffected:
 * only accounts carrying {@see self::META} are blocked.
 *
 * @since 1.8.0
 */
class EmailVerification {

	/**
	 * User meta holding the hashed token and its issue time.
	 *
	 * @since 1.8.0
	 * @var string
	 */
	public const META = '_wcb_email_unverified';

	/**
	 * How long a link stays valid.
	 *
	 * @since 1.8.0
	 * @var int
	 */
	private const TTL = 7 * DAY_IN_SECONDS;

	/**
	 * Register the link handler and the sign-in block.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'template_redirect', array( $this, 'maybe_verify' ), 0 );
		add_filter( 'wp_authenticate_user', array( $this, 'block_unverified' ), 20 );
	}

	/**
	 * Whether new sign-ups must confirm their email.
	 *
	 * @since 1.8.0
	 * @return bool
	 */
	public static function is_required(): bool {
		// Only while the confirmation email itself is on: switching that email
		// off with verification still required would lock every new member out.
		return \WCB\Admin\Settings::bool( 'require_email_verification', false )
			&& ( new \WCB\Modules\Notifications\Emails\EmailVerifyAccount() )->is_enabled();
	}

	/**
	 * Mark an account unverified and send the confirmation link.
	 *
	 * @since 1.8.0
	 * @param int $user_id New account.
	 * @return void
	 */
	public static function start( int $user_id ): void {
		$token = wp_generate_password( 32, false, false );
		update_user_meta(
			$user_id,
			self::META,
			array(
				'hash' => wp_hash( $token ),
				'time' => time(),
			)
		);

		/**
		 * Fires when a new account needs to confirm its email.
		 *
		 * The "Confirm your email" email listens here.
		 *
		 * @since 1.8.0
		 *
		 * @param int    $user_id    Account ID.
		 * @param string $verify_url Link that confirms the address and signs the member in.
		 */
		do_action( 'wcb_email_verification_requested', $user_id, add_query_arg( 'wcb_verify', $user_id . '.' . $token, home_url( '/' ) ) );
	}

	/**
	 * Whether an account still has to confirm its email.
	 *
	 * @since 1.8.0
	 * @param int $user_id Account ID.
	 * @return bool
	 */
	public static function is_pending( int $user_id ): bool {
		return metadata_exists( 'user', $user_id, self::META );
	}

	/**
	 * `wp_authenticate_user`: refuse sign-in until the email is confirmed.
	 *
	 * @since 1.8.0
	 * @param \WP_User|\WP_Error $user User being authenticated.
	 * @return \WP_User|\WP_Error
	 */
	public function block_unverified( \WP_User|\WP_Error $user ): \WP_User|\WP_Error {
		if ( $user instanceof \WP_User && self::is_pending( $user->ID ) ) {
			return new \WP_Error(
				'wcb_email_unverified',
				__( 'Please confirm your email address first. We sent you a link when you signed up.', 'wp-career-board' )
			);
		}
		return $user;
	}

	/**
	 * `template_redirect`: handle `?wcb_verify=<user>.<token>`.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function maybe_verify(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token in the link is the proof.
		if ( ! isset( $_GET['wcb_verify'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token in the link is the proof.
		$parts   = explode( '.', sanitize_text_field( wp_unslash( $_GET['wcb_verify'] ) ), 2 );
		$user_id = (int) $parts[0];
		$token   = (string) ( $parts[1] ?? '' );
		$pending = get_user_meta( $user_id, self::META, true );

		$valid = is_array( $pending )
			&& '' !== $token
			&& hash_equals( (string) ( $pending['hash'] ?? '' ), wp_hash( $token ) )
			&& time() - (int) ( $pending['time'] ?? 0 ) < self::TTL;

		// Opened again after it worked (or pre-fetched by a mail scanner): the
		// account is already confirmed, so send the member on to sign in.
		if ( ! is_array( $pending ) && get_userdata( $user_id ) ) {
			wp_safe_redirect( wp_login_url( self::dashboard_url( $user_id ) ) );
			exit;
		}

		if ( ! $valid ) {
			wp_die(
				esc_html__( 'This confirmation link is invalid or has expired. Try signing in to get a new one.', 'wp-career-board' ),
				esc_html__( 'Link expired', 'wp-career-board' ),
				array( 'response' => 400 )
			);
		}

		delete_user_meta( $user_id, self::META );
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, false );

		wp_safe_redirect( self::dashboard_url( $user_id ) );
		exit;
	}

	/**
	 * The dashboard a member lands on after confirming.
	 *
	 * @since 1.8.0
	 * @param int $user_id Account ID.
	 * @return string
	 */
	private static function dashboard_url( int $user_id ): string {
		$user     = get_userdata( $user_id );
		$page_key = $user && in_array( 'wcb_employer', (array) $user->roles, true ) ? 'employer_dashboard_page' : 'candidate_dashboard_page';
		$page_id  = \WCB\Admin\Settings::int( $page_key, 0 );
		return $page_id > 0 ? (string) get_permalink( $page_id ) : home_url( '/' );
	}

	/**
	 * Send a fresh link to a pending account.
	 *
	 * Silent when the address has no pending account, so the route can't be
	 * used to find out who is registered.
	 *
	 * @since 1.8.0
	 * @param string $email Address typed by the member.
	 * @return void
	 */
	public static function resend( string $email ): void {
		$user = get_user_by( 'email', $email );
		if ( $user instanceof \WP_User && self::is_pending( $user->ID ) ) {
			self::start( $user->ID );
		}
	}
}
