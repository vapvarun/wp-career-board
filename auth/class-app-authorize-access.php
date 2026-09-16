<?php
/**
 * Keep WordPress core's Application Passwords authorize screen usable for the
 * mobile app, on every site.
 *
 * The Career Board app signs in with WP core Application Passwords, and core's
 * authorize screen (`wp-admin/authorize-application.php`) is the interactive
 * door that mints the first one. Two things break that door in the wild:
 *
 * 1. Core renders the return URL through `esc_url()`, which drops any scheme
 *    not in the kses allowlist. `careerboardapp` is not a core protocol, so
 *    the hidden `success_url` field rendered EMPTY: the member approved and
 *    landed on a page showing a raw password instead of being handed back to
 *    the app. Verified on this codebase before the fix — `esc_url()` on a
 *    `careerboardapp://` link returned an empty string. That failure hit every
 *    role on every site.
 *
 * 2. WooCommerce (and membership plugins like it) redirect every user without
 *    `edit_posts` / `manage_woocommerce` / `view_admin_dashboard` away from all
 *    of wp-admin, exempting only admin-post.php and admin-ajax.php. Candidates
 *    hold none of those caps, so on such a site they can neither approve the
 *    app nor create a password by hand. Not a capability problem — core allows
 *    Application Passwords for subscriber-level roles — only the screen is
 *    unreachable.
 *
 * Both filters are scoped to the authorize request itself. A global protocol
 * allowance would make the app scheme linkable in job descriptions and company
 * profiles site-wide, which is unnecessary surface for a one-screen need. The
 * WooCommerce filter only ever turns the block OFF for that one screen; every
 * other wp-admin script stays blocked.
 *
 * @package WP_Career_Board
 * @since   1.7.2
 */

declare( strict_types=1 );

namespace WCB\Auth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scoped repairs that keep core's authorize screen working for members.
 *
 * @since 1.7.2
 */
final class AppAuthorizeAccess {

	/**
	 * The core screen that mints a member's first Application Password.
	 *
	 * @var string
	 */
	private const AUTHORIZE_SCRIPT = 'authorize-application.php';

	/**
	 * Default deep-link scheme the mobile app is registered for.
	 *
	 * A white-labelled build ships its own scheme, which is why this is a
	 * starting value behind a filter and not a hardcoded constant.
	 *
	 * @var string
	 */
	private const DEFAULT_APP_SCHEME = 'careerboardapp';

	/**
	 * Wire both filters.
	 *
	 * @since 1.7.2
	 * @return void
	 */
	public function boot(): void {
		add_filter( 'woocommerce_prevent_admin_access', array( $this, 'allow_authorize_screen' ) );
		add_filter( 'kses_allowed_protocols', array( $this, 'allow_app_scheme' ) );
	}

	/**
	 * Is the current request the core authorize screen?
	 *
	 * Matched on SCRIPT_FILENAME rather than a query arg or referer, because
	 * that is what actually determines which PHP file is executing and is not
	 * something a visitor can spoof into pointing at a different screen.
	 *
	 * @since 1.7.2
	 * @return bool
	 */
	public static function is_authorize_request(): bool {
		if ( ! isset( $_SERVER['SCRIPT_FILENAME'] ) ) {
			return false;
		}

		$script = basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_FILENAME'] ) ) );

		return self::AUTHORIZE_SCRIPT === $script;
	}

	/**
	 * The app's deep-link scheme.
	 *
	 * @since 1.7.2
	 * @return string Lowercase scheme with no separator, e.g. `careerboardapp`.
	 */
	public static function app_scheme(): string {
		/**
		 * Filter the mobile app's deep-link scheme.
		 *
		 * @since 1.7.2
		 *
		 * @param string $scheme Scheme with no `://` separator.
		 */
		$scheme = (string) apply_filters( 'wcb_app_scheme', self::DEFAULT_APP_SCHEME );

		return strtolower( (string) preg_replace( '/[^a-z0-9+.-]/i', '', $scheme ) );
	}

	/**
	 * Stop WooCommerce redirecting a member away from the authorize screen.
	 *
	 * Only ever turns the block OFF, and only for that one script. It never
	 * turns a block ON, so a site that already allows admin access is
	 * unchanged.
	 *
	 * @since 1.7.2
	 *
	 * @param mixed $prevent Whether WooCommerce intends to block this request.
	 * @return mixed
	 */
	public function allow_authorize_screen( $prevent ) {
		if ( ! $prevent ) {
			return $prevent;
		}

		return self::is_authorize_request() ? false : $prevent;
	}

	/**
	 * Let `esc_url()` keep the app scheme on the authorize screen.
	 *
	 * Scoped to that request: everywhere else the allowlist is untouched, so
	 * the scheme never becomes linkable in job or company content.
	 *
	 * @since 1.7.2
	 *
	 * @param mixed $protocols Allowed protocols.
	 * @return mixed
	 */
	public function allow_app_scheme( $protocols ) {
		if ( ! is_array( $protocols ) || ! self::is_authorize_request() ) {
			return $protocols;
		}

		$scheme = self::app_scheme();

		if ( '' !== $scheme && ! in_array( $scheme, $protocols, true ) ) {
			$protocols[] = $scheme;
		}

		return $protocols;
	}
}
