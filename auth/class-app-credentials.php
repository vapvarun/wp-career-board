<?php
/**
 * Trade a member's ordinary WordPress login for an Application Password.
 *
 * WordPress core will not do this exchange: its Basic auth handler validates
 * against stored application passwords ONLY, so the core route that mints one
 * already requires one, and every core path to a member's FIRST credential
 * runs through a wp-admin screen under cookie auth. Candidates and employers
 * have a WordPress password and expect to type it — this is what lets them.
 *
 * NOT a second authentication system. `wp_authenticate()` does the actual
 * authentication, exactly as wp-login.php does, so every `authenticate` filter
 * on the site still runs — security plugins, membership gates and 2FA all get
 * their say. This class only decides what to hand back once core has said yes.
 *
 * THE DELETION-WINDOW GATE, and why it is not optional here. Career Board lets
 * a member schedule their own account deletion (Apple 5.1.1(v)); during the
 * grace window `AccountDeletionService` suspends the account AND REVOKES ITS
 * CREDENTIALS. A password exchange that ignored that would hand the member a
 * brand-new working credential the moment they typed their password — quietly
 * undoing the revocation the grace window exists to perform. So a member with
 * a scheduled deletion is refused here, with copy that tells them to cancel
 * the deletion first.
 *
 * THE RISK, STATED PLAINLY: this route accepts a real account password, which
 * makes it a brute-force oracle in a way wp-login.php is not (that page
 * inherits whatever protection the site has installed). It is therefore
 * TLS-gated, rate-limited per IP AND per username before any credential is
 * read (failures only — an honest member's typo is cleared on success), capped
 * per IP on TOTAL attempts so even a slow distributed probe is bounded,
 * uniform in its failure message so it cannot enumerate accounts, off when the
 * owner turns it off, and silent about the password in every log and error
 * path.
 *
 * @package WP_Career_Board
 * @since   1.7.2
 */

declare( strict_types=1 );

namespace WCB\Auth;

use WCB\Modules\Account\AccountDeletionService;
use WP_Error;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The credentials-for-app-password exchange.
 *
 * @since 1.7.2
 */
final class AppCredentials {

	/**
	 * Failed attempts allowed per bucket before lockout.
	 *
	 * @var int
	 */
	private const MAX_FAILURES = 5;

	/**
	 * Failure-count window (and lockout length) in seconds.
	 *
	 * @var int
	 */
	private const FAILURE_WINDOW = 900;

	/**
	 * Total attempts (successful or not) allowed per IP per hour.
	 *
	 * Career Board has no shared rate-limiter service, so the ceiling lives
	 * here rather than being borrowed from one that might not enforce.
	 *
	 * @var int
	 */
	private const MAX_ATTEMPTS_PER_IP = 20;

	/**
	 * Attempt-ceiling window in seconds.
	 *
	 * @var int
	 */
	private const ATTEMPT_WINDOW = 3600;

	/**
	 * Error codes that mean "those credentials were wrong".
	 *
	 * Anything else from `wp_authenticate()` means the site rejected the
	 * sign-in for its OWN reason — a second factor, a security plugin — which
	 * is a different answer and gets a different status. Collapsed so the
	 * response cannot answer "does this account exist?".
	 *
	 * @var string[]
	 */
	private const CREDENTIAL_FAILURE_CODES = array(
		'invalid_username',
		'invalid_email',
		'incorrect_password',
		'authentication_failed',
	);

	/**
	 * Is the credentials exchange available on this site?
	 *
	 * OWNER SWITCH, DEFAULT OFF — and the default is the point.
	 *
	 * Most Career Board sites never install the app. Shipping this ON would put
	 * a password-accepting endpoint on every one of them to serve the minority
	 * that do, and a route that accepts real account passwords is exactly the
	 * kind of surface that should be opted into rather than out of. An owner who
	 * never heard of it should not be running it.
	 *
	 * Defaulting OFF costs the app nothing it cannot afford, because password
	 * sign-in is NOT the app's only door: `AppConnect`'s browser hand-off is the
	 * primary flow and the one the sign-in screen offers first. It sends the
	 * member to their own wp-login, where 2FA and every security plugin apply,
	 * and comes back with a credential — no password ever reaches this route.
	 * Password sign-in is the convenience path, so it is the one that waits for
	 * a deliberate yes.
	 *
	 * @since 1.7.2
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$on = \WCB\Admin\Settings::bool( 'app_password_login', false );

		/**
		 * Filter whether members may exchange a WordPress password for an
		 * Application Password.
		 *
		 * @since 1.7.2
		 *
		 * @param bool $on Whether the exchange is available.
		 */
		return (bool) apply_filters( 'wcb_app_password_login_enabled', $on );
	}

	/**
	 * Exchange credentials for an Application Password.
	 *
	 * @since 1.7.2
	 *
	 * @param string $username Username or email.
	 * @param string $password The member's account password.
	 * @param string $app_name Label shown in the member's profile.
	 * @param string $app_id   Stable per-install id; `AppConnect`'s pruner
	 *                         keys reconnect-replacement on it.
	 * @return array|WP_Error { user_login, password, app_id } or an error.
	 */
	public static function exchange( string $username, string $password, string $app_name, string $app_id ) {
		if ( ! self::is_enabled() ) {
			return new WP_Error(
				'wcb_app_passwords_off',
				__( 'This site has turned off app sign-in. Ask the site owner to enable it.', 'wp-career-board' ),
				array( 'status' => 403 )
			);
		}

		// Core refuses to issue Application Passwords without TLS, and so must
		// we: this request carries the real account password. A local dev
		// environment is the documented exception, and it is core's own.
		if ( ! wp_is_application_passwords_available() ) {
			return new WP_Error(
				'wcb_app_passwords_off',
				__( 'This site cannot issue app passwords. It needs a secure (https) connection.', 'wp-career-board' ),
				array( 'status' => 403 )
			);
		}

		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			return self::translate_auth_error( $user );
		}

		if ( ! $user instanceof WP_User ) {
			return new WP_Error(
				'wcb_login_failed',
				__( 'The username or password you entered is incorrect.', 'wp-career-board' ),
				array( 'status' => 401 )
			);
		}

		// `wp_authenticate()` does not log anyone in, but a site's own filters
		// may have primed a session. The app is a stateless client; the
		// credential it gets back IS the session.
		wp_clear_auth_cookie();

		if ( self::is_pending_deletion( $user ) ) {
			return new WP_Error(
				'wcb_account_pending_deletion',
				__( 'This account is scheduled for deletion. Cancel the deletion from the website first, then sign in again.', 'wp-career-board' ),
				array( 'status' => 403 )
			);
		}

		if ( ! wp_is_application_passwords_available_for_user( $user ) ) {
			return new WP_Error(
				'wcb_app_passwords_off',
				__( 'App sign-in is not available for this account.', 'wp-career-board' ),
				array( 'status' => 403 )
			);
		}

		// No pre-prune needed: AppConnect::forget_older_installs() runs on
		// core's wp_create_application_password action and replaces older rows
		// for this app_id, whichever door minted the new one.
		$created = \WP_Application_Passwords::create_new_application_password(
			$user->ID,
			array(
				'name'   => $app_name,
				'app_id' => $app_id,
			)
		);

		if ( is_wp_error( $created ) ) {
			return new WP_Error(
				'wcb_app_passwords_off',
				__( 'This site could not issue an app password. Ask the site owner to check their settings.', 'wp-career-board' ),
				array( 'status' => 403 )
			);
		}

		/**
		 * Fires after a member exchanges their password for an app credential.
		 *
		 * The credential itself is deliberately NOT passed: a listener that
		 * logged it would undo the reason this class is careful.
		 *
		 * @since 1.7.2
		 *
		 * @param int    $user_id  Member who signed in.
		 * @param string $app_id   Stable per-install id.
		 * @param string $app_name Label stored on the credential.
		 */
		do_action( 'wcb_app_credential_issued', $user->ID, $app_id, $app_name );

		return array(
			'user_login' => $user->user_login,
			'password'   => $created[0],
			'app_id'     => $app_id,
		);
	}

	/**
	 * Has this member scheduled their own account deletion?
	 *
	 * Read directly from the service's own meta key so there is one source of
	 * truth; the guard holds even if the service is not booted on this request.
	 *
	 * @since 1.7.2
	 *
	 * @param WP_User $user Authenticated member.
	 * @return bool
	 */
	private static function is_pending_deletion( WP_User $user ): bool {
		if ( ! class_exists( AccountDeletionService::class ) ) {
			return false;
		}

		return '' !== (string) get_user_meta( $user->ID, AccountDeletionService::META_SCHEDULED, true );
	}

	/**
	 * Is this bucket (ip:… or user:…) currently locked out?
	 *
	 * @since 1.7.2
	 *
	 * @param string $bucket Bucket key.
	 * @return bool
	 */
	public static function is_locked_out( string $bucket ): bool {
		$data = get_transient( self::failure_key( $bucket ) );

		if ( ! is_array( $data ) ) {
			return false;
		}

		$count = isset( $data['count'] ) ? (int) $data['count'] : 0;
		$start = isset( $data['start'] ) ? (int) $data['start'] : 0;

		return $count >= self::max_failures() && ( time() - $start ) < self::FAILURE_WINDOW;
	}

	/**
	 * Failed attempts allowed per bucket before lockout.
	 *
	 * Filterable because the right ceiling is a property of the site, not of
	 * this plugin: a board behind SSO with three staff accounts and a public
	 * board with fifty thousand members do not want the same number, and an
	 * owner who has to edit a constant to find out will not tune it at all.
	 *
	 * @since 1.7.2
	 *
	 * @return int
	 */
	private static function max_failures(): int {
		/**
		 * Filter how many failed sign-ins a bucket tolerates before lockout.
		 *
		 * @since 1.7.2
		 *
		 * @param int $max Default 5.
		 */
		return max( 1, (int) apply_filters( 'wcb_app_password_max_failures', self::MAX_FAILURES ) );
	}

	/**
	 * Total attempts allowed per IP per window.
	 *
	 * The ceiling that most needs tuning, and the one most likely to be wrong
	 * out of the box — see `client_ip()` for why a proxied site collapses every
	 * member onto one address.
	 *
	 * @since 1.7.2
	 *
	 * @return int
	 */
	private static function max_attempts_per_ip(): int {
		/**
		 * Filter the per-IP ceiling on total sign-in attempts per hour.
		 *
		 * @since 1.7.2
		 *
		 * @param int $max Default 20.
		 */
		return max( 1, (int) apply_filters( 'wcb_app_password_max_attempts_per_ip', self::MAX_ATTEMPTS_PER_IP ) );
	}

	/**
	 * The client's address, as well as this site can know it.
	 *
	 * `REMOTE_ADDR` is the only value a PHP process can trust, and on a direct
	 * connection it is right. Behind Cloudflare, a load balancer or any reverse
	 * proxy it is the PROXY's address — identical for every visitor — which
	 * turns the per-IP ceiling from a brute-force bound into a site-wide outage:
	 * twenty sign-ins an hour for the entire membership, then everyone is locked
	 * out. That is a worse failure than the one the ceiling prevents.
	 *
	 * Forwarded headers are NOT read by default, because anyone can send one and
	 * a spoofed header makes the limiter trivially bypassable — the opposite
	 * mistake. So the owner names their trusted header, which is the only party
	 * that knows the proxy in front of their own site:
	 *
	 *     add_filter( 'wcb_app_password_client_ip_header', fn() => 'HTTP_CF_CONNECTING_IP' );
	 *
	 * The leftmost address is taken (the original client; later hops are
	 * appended by each proxy) and validated, so a malformed header degrades to
	 * REMOTE_ADDR rather than poisoning a bucket key with attacker-chosen text.
	 *
	 * @since 1.7.2
	 *
	 * @return string
	 */
	public static function client_ip(): string {
		/**
		 * Filter which `$_SERVER` key carries the real client IP.
		 *
		 * Empty (the default) means trust only REMOTE_ADDR. Set this ONLY when
		 * a proxy you control always overwrites the header — an unvalidated
		 * forwarded header is attacker-controlled.
		 *
		 * @since 1.7.2
		 *
		 * @param string $header A `$_SERVER` key, e.g. `HTTP_CF_CONNECTING_IP`.
		 */
		$header = (string) apply_filters( 'wcb_app_password_client_ip_header', '' );

		if ( '' !== $header && ! empty( $_SERVER[ $header ] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by rest_is_ip_address below; sanitize_text_field would not make a bad IP good.
			$raw   = (string) wp_unslash( $_SERVER[ $header ] );
			$first = trim( strtok( $raw, ',' ) ?: '' );

			if ( '' !== $first && rest_is_ip_address( $first ) ) {
				return $first;
			}
		}

		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return 'unknown';
		}

		$remote = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

		return rest_is_ip_address( $remote ) ? $remote : 'unknown';
	}

	/**
	 * Count one attempt against the per-IP ceiling.
	 *
	 * Runs for EVERY attempt, right or wrong, so a slow probe that never
	 * trips the failure lockout is still bounded.
	 *
	 * @since 1.7.2
	 *
	 * @param string $ip Client IP.
	 * @return bool True while the caller is under the ceiling.
	 */
	public static function record_attempt( string $ip ): bool {
		$key   = 'wcb_apw_try_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= self::max_attempts_per_ip() ) {
			return false;
		}

		set_transient( $key, $count + 1, self::ATTEMPT_WINDOW );

		return true;
	}

	/**
	 * Count a rejected CREDENTIAL against a bucket.
	 *
	 * Only wrong passwords count — a 2FA hand-off, a pending deletion or a
	 * disabled switch are all "your password was fine", and counting those
	 * would lock out members who did nothing wrong.
	 *
	 * @since 1.7.2
	 *
	 * @param string $bucket Bucket key.
	 * @return void
	 */
	public static function record_failure( string $bucket ): void {
		$key  = self::failure_key( $bucket );
		$data = get_transient( $key );

		$start = is_array( $data ) && isset( $data['start'] ) ? (int) $data['start'] : 0;

		if ( ! is_array( $data ) || ( time() - $start ) >= self::FAILURE_WINDOW ) {
			$data = array(
				'count' => 0,
				'start' => time(),
			);
		}

		$data['count'] = ( isset( $data['count'] ) ? (int) $data['count'] : 0 ) + 1;

		set_transient( $key, $data, self::FAILURE_WINDOW );
	}

	/**
	 * An honest member who mistyped twice should not carry those failures.
	 *
	 * @since 1.7.2
	 *
	 * @param string $bucket Bucket key.
	 * @return void
	 */
	public static function clear_failures( string $bucket ): void {
		delete_transient( self::failure_key( $bucket ) );
	}

	/**
	 * Transient key for a bucket's failure counter.
	 *
	 * @since 1.7.2
	 *
	 * @param string $bucket Bucket key.
	 * @return string
	 */
	private static function failure_key( string $bucket ): string {
		return 'wcb_apw_fail_' . md5( $bucket );
	}

	/**
	 * Decide what a failed `wp_authenticate()` actually means.
	 *
	 * A wrong password and "this site wants a second factor" are different
	 * answers and must not collapse into one: reporting a 2FA block as a bad
	 * password sends the member round a failing loop; reporting it as success
	 * walks them past a factor the owner configured. Known credential codes
	 * become a uniform 401; everything else becomes a 409 telling the app to
	 * hand off to the interactive flow, which CAN complete a second step.
	 *
	 * @since 1.7.2
	 *
	 * @param WP_Error $error The error `wp_authenticate()` returned.
	 * @return WP_Error
	 */
	private static function translate_auth_error( WP_Error $error ): WP_Error {
		foreach ( $error->get_error_codes() as $code ) {
			if ( in_array( $code, self::CREDENTIAL_FAILURE_CODES, true ) ) {
				// Uniform on purpose: `invalid_username` vs
				// `incorrect_password` is exactly the oracle that answers
				// "does this account exist?".
				return new WP_Error(
					'wcb_login_failed',
					__( 'The username or password you entered is incorrect.', 'wp-career-board' ),
					array( 'status' => 401 )
				);
			}
		}

		return new WP_Error(
			'wcb_second_factor',
			__( 'This site needs another step to sign you in. Use the WordPress approval screen instead.', 'wp-career-board' ),
			array( 'status' => 409 )
		);
	}
}
