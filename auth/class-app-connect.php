<?php
/**
 * App-connect wiring — which door the Career Board mobile app walks through to
 * acquire its credential, per the Wbcom App Auth standard.
 *
 * The credential is always a WordPress core Application Password. This class
 * answers the SITE-level questions around acquiring one:
 *
 *   WHICH DOOR OWNS THIS SITE? One connect bridge per site. Many customer
 *   sites run BuddyNext alongside Career Board, and BuddyNext owns site auth
 *   there — its connect bridge carries the site's social providers and
 *   two-factor. So when BuddyNext is active, `bridge_info()` points the app at
 *   BN's bridge and Career Board registers its deep-link scheme into BN's
 *   allowlist, so a Career-Board-app connection through BN's door may deliver
 *   the credential to `careerboardapp://`. Standalone, Career Board
 *   deliberately has NO bridge of its own: core's authorize screen (repaired
 *   by `AppAuthorizeAccess`) runs the site's real login — including any second
 *   factor — which is the very thing a bridge exists to provide. The app never
 *   contains any of this logic; it reads the `auth` block on the app-config
 *   and goes where it is pointed.
 *
 *   WHICH SCHEMES? The custom URL schemes this site may hand a credential to.
 *   Career Board's own comes from `AppAuthorizeAccess::app_scheme()` (one
 *   white-label seam, not a second constant); sibling Wbcom apps join through
 *   the `wcb_app_connect_schemes` filter, mirroring the seam BuddyNext exposes
 *   for us.
 *
 *   RECONNECT REPLACES, ON EVERY DOOR. Core's authorize screen mints a fresh
 *   row per approval and cannot be pre-empted, so a listener on
 *   `wp_create_application_password` prunes older rows carrying the same
 *   `app_id` — whichever door minted the new one. Rows without an `app_id`
 *   (hand-created in the member's profile) are never touched.
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
 * Cross-plugin app-connect seams + the app-config auth block.
 *
 * @since 1.7.2
 */
final class AppConnect {

	/**
	 * Hook the seams. Registered unconditionally at boot — the BuddyNext
	 * filter is harmless when BN is absent, and unconditional registration is
	 * what makes plugin activation ORDER irrelevant.
	 *
	 * @since 1.7.2
	 * @return void
	 */
	public function boot(): void {
		add_filter( 'buddynext_app_connect_schemes', array( $this, 'join_buddynext_allowlist' ) );
		add_action( 'wp_create_application_password', array( $this, 'forget_older_installs' ), 10, 2 );
	}

	/**
	 * Register the Career Board app's scheme with BuddyNext's connect bridge.
	 *
	 * @since 1.7.2
	 *
	 * @param mixed $schemes Schemes BN will hand credentials to — a filter
	 *                       value, so guarded rather than trusted.
	 * @return array
	 */
	public function join_buddynext_allowlist( $schemes ): array {
		$schemes   = is_array( $schemes ) ? $schemes : array();
		$schemes[] = AppAuthorizeAccess::app_scheme();

		return array_values( array_unique( array_filter( $schemes ) ) );
	}

	/**
	 * The schemes THIS site's Career Board flows may hand a credential to.
	 *
	 * @since 1.7.2
	 * @return string[]
	 */
	public static function schemes(): array {
		/**
		 * Filter the custom URL schemes the Career Board app-connect flow may
		 * deliver a credential to. Every scheme here can RECEIVE an
		 * Application Password — add a scheme only for an app you ship, never
		 * a wildcard.
		 *
		 * @since 1.7.2
		 *
		 * @param string[] $schemes Allowed schemes.
		 */
		$schemes = (array) apply_filters(
			'wcb_app_connect_schemes',
			array( AppAuthorizeAccess::app_scheme() )
		);

		return array_values( array_filter( array_map( 'strval', $schemes ) ) );
	}

	/**
	 * Which bridge serves this site, per the one-door rule.
	 *
	 * Keys: owner, connect_url, connect_schemes. Typed loosely because the
	 * value passes through a filter and callers must not trust its shape.
	 *
	 * @since 1.7.2
	 * @return array<string,mixed>
	 */
	public static function bridge_info(): array {
		if ( class_exists( '\BuddyNext\App\AppConnectService' ) ) {
			$info = array(
				'owner'           => 'buddynext',
				'connect_url'     => (string) \BuddyNext\App\AppConnectService::connect_url(),
				'connect_schemes' => (array) \BuddyNext\App\AppConnectService::schemes(),
			);
		} else {
			// No Career Board bridge, by decision: standalone sites use core's
			// repaired authorize screen or the credentials exchange. An empty
			// connect_url is how the app learns to route through those.
			$info = array(
				'owner'           => 'wp-career-board',
				'connect_url'     => '',
				'connect_schemes' => self::schemes(),
			);
		}

		/**
		 * Filter the resolved app-connect bridge for this site.
		 *
		 * Tests use it to simulate the BuddyNext-active path; a site with an
		 * unusual auth topology can point the app at its own door.
		 *
		 * @since 1.7.2
		 *
		 * @param array $info { owner, connect_url, connect_schemes }.
		 */
		return (array) apply_filters( 'wcb_app_connect_bridge', $info );
	}

	/**
	 * The `auth` block published on the app-config — the standard shape every
	 * Wbcom product advertises, so ONE app-side reader serves the whole fleet.
	 *
	 * `social_providers` is always empty: Career Board has no social login
	 * system of its own; on combined sites the bridge (BuddyNext's) carries
	 * the site's providers behind its own login page. `twofactor` is false
	 * because the credentials exchange cannot complete an inline second factor
	 * — a 2FA site answers 409 there and the app falls back to the interactive
	 * flow.
	 *
	 * @since 1.7.2
	 * @return array
	 */
	public static function auth_block(): array {
		$bridge = self::bridge_info();

		return array(
			'social_providers'        => array(),
			'twofactor'               => false,
			// The site-level toggle is the truth the app can act on: it opens
			// the site's own registration page in the browser, so whatever
			// candidate-signup flow the owner runs stays intact.
			'register'                => (bool) get_option( 'users_can_register' ),
			'app_passwords_available' => wp_is_application_passwords_available(),
			'connect_url'             => (string) ( $bridge['connect_url'] ?? '' ),
			'connect_schemes'         => array_values( (array) ( $bridge['connect_schemes'] ?? array() ) ),
		);
	}

	/**
	 * After a mint, drop OLDER credential rows carrying the same app_id.
	 *
	 * Keyed on `app_id` so this only ever forgets the same install
	 * reconnecting, never another device.
	 *
	 * @since 1.7.2
	 *
	 * @param int   $user_id  Member the credential was minted for.
	 * @param array $new_item The row core just created (uuid, app_id, ...).
	 * @return void
	 */
	public function forget_older_installs( $user_id, $new_item ): void {
		$app_id   = isset( $new_item['app_id'] ) ? (string) $new_item['app_id'] : '';
		$new_uuid = isset( $new_item['uuid'] ) ? (string) $new_item['uuid'] : '';

		if ( '' === $app_id || '' === $new_uuid ) {
			return;
		}

		foreach ( \WP_Application_Passwords::get_user_application_passwords( (int) $user_id ) as $existing ) {
			$existing_app_id = isset( $existing['app_id'] ) ? (string) $existing['app_id'] : '';
			$existing_uuid   = isset( $existing['uuid'] ) ? (string) $existing['uuid'] : '';

			if ( $app_id === $existing_app_id && '' !== $existing_uuid && $new_uuid !== $existing_uuid ) {
				\WP_Application_Passwords::delete_application_password( (int) $user_id, $existing_uuid );
			}
		}
	}
}
