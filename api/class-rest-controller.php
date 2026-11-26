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
	 * Add a snake_case twin for every camelCase key in a response row.
	 *
	 * REST keys are snake_case; the camelCase keys predate that and are read by
	 * the bundled blocks and by existing integrations, so they stay and are
	 * marked deprecated in the OpenAPI document. Existing keys are never
	 * overwritten.
	 *
	 * @since 1.8.4
	 *
	 * @param array<string, mixed> $row Response row.
	 * @return array<string, mixed>
	 */
	public static function with_snake_keys( array $row ): array {
		foreach ( $row as $key => $value ) {
			$snake = strtolower( (string) preg_replace( '/(?<!^)[A-Z]/', '_$0', (string) $key ) );
			if ( $snake !== $key && ! array_key_exists( $snake, $row ) ) {
				$row[ $snake ] = $value;
			}
		}
		return $row;
	}

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
	 * Whether a stored owner id is the signed-in user.
	 *
	 * A signed-out visitor is user 0, and so is every guest application's
	 * candidate and any post with no author. A bare `get_current_user_id() ===
	 * $owner` therefore made the visitor the owner of all of them:
	 * GET /candidates/0/applications listed every guest application to anyone.
	 *
	 * @since 1.8.0
	 *
	 * @param int $owner_id Owner user ID read from the object or the route.
	 * @return bool
	 */
	protected function is_current_user( int $owner_id ): bool {
		return $owner_id > 0 && get_current_user_id() === $owner_id;
	}

	/**
	 * Add the paging headers to a list response.
	 *
	 * X-WP-Total and X-WP-TotalPages are the WordPress standard that apiFetch,
	 * the mobile app and headless clients read. X-WCB-Total and X-WCB-TotalPages
	 * are what this plugin sent before 1.8.5; they stay so existing integrations
	 * keep working.
	 *
	 * @since 1.8.5
	 *
	 * @param \WP_REST_Response $response List response.
	 * @param int               $total    Rows matching the query.
	 * @param int               $pages    Number of pages.
	 * @return \WP_REST_Response
	 */
	protected function with_paging( \WP_REST_Response $response, int $total, int $pages ): \WP_REST_Response {
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );
		$response->header( 'X-WCB-Total', (string) $total );
		$response->header( 'X-WCB-TotalPages', (string) $pages );
		return $response;
	}

	/**
	 * Whether an application sits in the trash for this viewer.
	 *
	 * A trashed application is gone for everyone but an admin, the same as its
	 * CV file: no read, no status change, note, rating, withdrawal or Pro stage
	 * move. The admin restores it first.
	 *
	 * @since 1.8.6
	 *
	 * @param \WP_Post $application Application post.
	 * @return bool
	 */
	protected function application_in_trash( \WP_Post $application ): bool {
		return 'trash' === $application->post_status && ! $this->check_ability( 'wcb/manage-settings' );
	}

	/**
	 * Refusal for a status or stage change on a trashed application. The admin
	 * who can still open it gets told why, not "no change".
	 *
	 * @since 1.8.6
	 * @return \WP_Error
	 */
	protected function trashed_application_error(): \WP_Error {
		return new \WP_Error(
			'wcb_application_trashed',
			__( 'This application is in the trash. Restore it before changing its status.', 'wp-career-board' ),
			array( 'status' => 409 )
		);
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
		return $this->rate_limit_reached( $bucket, \WCB\Auth\AppCredentials::client_ip(), $limit );
	}

	/**
	 * Count one request from a subject (an IP, a user ID) against an hourly
	 * limit. The one rate limiter for Free and Pro routes.
	 *
	 * @since 1.8.1
	 * @param string $bucket  Transient prefix naming the limit.
	 * @param string $subject Who is counted, e.g. an IP or "user:12".
	 * @param int    $limit   Requests per hour; 0 disables the limit.
	 * @return bool True when the subject is over the limit (the request is not counted).
	 */
	protected function rate_limit_reached( string $bucket, string $subject, int $limit ): bool {
		if ( $limit <= 0 ) {
			return false;
		}
		global $wpdb;
		$key  = $bucket . md5( wp_salt() . $subject );
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
	 * (the anti-spam module's honeypot; a CAPTCHA plugin hooks it too), a per-IP limit, then
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
	 * The IP is stored as a keyed hash (AppCredentials::client_ip_hash()), never in plaintext.
	 * A visitor counts once per job per hour.
	 *
	 * @since 1.0.0
	 *
	 * @param int $job_id Post ID of the wcb_job.
	 * @return void
	 */
	protected function record_job_view( int $job_id ): void {
		// An employer opening their own listing is not a view of it.
		if ( $this->is_bot_request() || ( get_current_user_id() > 0 && get_current_user_id() === (int) get_post_field( 'post_author', $job_id ) ) ) {
			return;
		}

		// One view per visitor and job an hour: a script reloading a page does not
		// inflate the count or grow the table. Checked in the views table itself
		// (KEY job_visitor, 1.8.6), not a transient and lock per view, which wrote
		// two wp_options rows on every first view without a persistent cache.
		// ponytail: two requests in the same instant can both count; harmless for a view tally.
		global $wpdb;
		// A member counts as themselves (two colleagues on one office IP are two
		// visitors), a guest by their address; both hashed, never stored raw.
		$visitor = get_current_user_id() ? hash_hmac( 'sha256', 'user:' . get_current_user_id(), wp_salt( 'auth' ) ) : \WCB\Auth\AppCredentials::client_ip_hash();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed existence check on the job_visitor key.
		$seen = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}wcb_job_views WHERE job_id = %d AND ip_hash = %s AND viewed_at > %s LIMIT 1",
				$job_id,
				$visitor,
				gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS )
			)
		);
		if ( $seen ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insert into custom wcb_job_views table; no caching needed for write-only analytics.
		$wpdb->insert(
			$wpdb->prefix . 'wcb_job_views',
			array(
				'job_id'    => $job_id,
				'viewed_at' => current_time( 'mysql', true ),
				'ip_hash'   => $visitor,
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
