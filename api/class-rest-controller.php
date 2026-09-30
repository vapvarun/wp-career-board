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
	 * Check an ability via the WordPress Abilities API.
	 *
	 * Delegates to wp_is_ability_granted() — provided either by WP core
	 * (eventually) or by the polyfill at core/abilities-api-polyfill.php,
	 * which falls back to current_user_can() for ability slugs that double
	 * as custom caps (Roles::add_admin_caps grants every wcb_* cap to
	 * administrators, so no manage_options bypass is needed).
	 *
	 * @since 1.0.0
	 *
	 * @param string $ability Ability slug.
	 * @param array  $args    Optional context args (e.g. ['board_id' => 3]).
	 * @return bool
	 */
	protected function check_ability( string $ability, array $args = array() ): bool {
		return wp_is_ability_granted( $ability );
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
	 * Count one request from the caller's IP against an hourly limit.
	 *
	 * For public routes that cost something per call (account creation,
	 * outgoing email, paid upstream APIs).
	 *
	 * @since 1.8.0
	 * @param string $bucket Transient prefix naming the limit.
	 * @param int    $limit  Requests per hour; 0 disables the limit.
	 * @return bool True when the caller is over the limit (the request is not counted).
	 */
	protected function ip_limit_reached( string $bucket, int $limit ): bool {
		if ( $limit <= 0 ) {
			return false;
		}
		global $wpdb;
		$ip   = \WCB\Auth\AppCredentials::client_ip();
		$key  = $bucket . md5( wp_salt() . $ip );
		$lock = 'wcb_' . md5( DB_NAME . $wpdb->prefix . $key );

		// The count is read-modify-write: a burst from one IP would otherwise
		// read the same value and undercount. A short named lock serialises it.
		// ponytail: if the lock can't be had in 2s the request is counted
		// without it, so a stuck lock never blocks sign-ups.
		$locked = 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 2 )', $lock ) );
		try {
			$count = (int) get_transient( $key );
			if ( $count >= $limit ) {
				return true;
			}
			set_transient( $key, $count + 1, HOUR_IN_SECONDS );
			return false;
		} finally {
			if ( $locked ) {
				$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock ) );
			}
		}
	}

	/**
	 * Spam gate shared by both registration routes.
	 *
	 * Registration used to create accounts with no honeypot, CAPTCHA or rate
	 * limit, and skipped core's `registration_errors`, so third-party anti-spam
	 * plugins never saw these sign-ups either. Runs `wcb_pre_registration`
	 * (the anti-spam module's honeypot + CAPTCHA), a per-IP limit, then
	 * `registration_errors`.
	 *
	 * @since 1.8.0
	 *
	 * @param \WP_REST_Request $request  Registration request.
	 * @param string           $username Login about to be created.
	 * @param string           $email    Email about to be used.
	 * @return \WP_Error|null Error to return, or null to continue.
	 */
	protected function registration_guard( \WP_REST_Request $request, string $username, string $email ): ?\WP_Error {
		/**
		 * Filter - reject a registration before the account is created.
		 *
		 * @since 1.8.0
		 *
		 * @param \WP_Error|null   $error   Null to allow.
		 * @param \WP_REST_Request $request Registration request.
		 */
		$error = apply_filters( 'wcb_pre_registration', null, $request );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		/**
		 * Filter the number of registrations one IP may make per hour.
		 *
		 * @since 1.8.0
		 *
		 * @param int $limit Default 5. 0 disables the limit.
		 */
		if ( $this->ip_limit_reached( 'wcb_reg_', (int) apply_filters( 'wcb_registration_rate_limit', 5 ) ) ) {
			return new \WP_Error(
				'wcb_rate_limited',
				__( 'Too many sign-ups from your network. Please try again in an hour.', 'wp-career-board' ),
				array( 'status' => 429 )
			);
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook, so third-party anti-spam plugins see these sign-ups.
		$errors = apply_filters( 'registration_errors', new \WP_Error(), $username, $email );
		if ( $errors instanceof \WP_Error && $errors->has_errors() ) {
			return new \WP_Error( 'wcb_registration_rejected', $errors->get_error_message(), array( 'status' => 400 ) );
		}

		return null;
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
		if ( $this->is_bot_request() ) {
			return;
		}

		global $wpdb;

		$ip = \WCB\Auth\AppCredentials::client_ip();

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

	/**
	 * Detect bot/crawler requests by User-Agent.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the request appears to be from a bot.
	 */
	private function is_bot_request(): bool {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		if ( '' === $ua ) {
			return true;
		}

		/**
		 * Filter the regex pattern used to detect bot User-Agents.
		 *
		 * @since 1.0.0
		 * @param string $pattern PCRE pattern (without delimiters).
		 */
		$pattern = (string) apply_filters(
			'wcb_bot_ua_pattern',
			'bot|crawl|spider|slurp|Googlebot|Bingbot|DuckDuckBot|Baiduspider|YandexBot'
			. '|facebookexternalhit|Twitterbot|LinkedInBot|Applebot|MJ12bot|AhrefsBot'
			. '|SemrushBot|DotBot|PetalBot|Bytespider'
		);

		return 1 === preg_match( '/' . $pattern . '/i', $ua );
	}
}
