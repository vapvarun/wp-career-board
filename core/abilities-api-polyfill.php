<?php
/**
 * Polyfill for wp_is_ability_granted().
 *
 * WP 6.9 ships the Abilities API registry (wp_register_ability,
 * wp_get_ability, wp_get_abilities, wp_has_ability) but no top-level
 * "is granted" helper. This polyfill wraps wp_get_ability( $name )
 * ->check_permissions() so call sites can use the documented
 * wp_is_ability_granted() pattern uniformly.
 *
 * Becomes a no-op via function_exists guard when WP core eventually
 * ships its own helper — the polyfill yields, core's wins.
 *
 * Resolution order for a given ability name:
 *
 *   1. If the ability is registered, return its check_permissions() result.
 *   2. Otherwise, fall back to current_user_can( $name ) so plugins whose
 *      Abilities API registrations have not yet been fixed for the WP 6.9
 *      "namespace/slug" format keep working off their cap registrations.
 *
 * The cap fallback is the same one every site in the codebase had been
 * using before this polyfill existed (the function_exists guards always
 * fell through, since wp_is_ability_granted() did not exist in core).
 * Keeping it preserves current behaviour exactly and is the safe migration
 * step. A later task ports the plugin's ability slugs to "wcb/foo" form,
 * at which point this branch becomes dead and can be removed.
 *
 * @package WP_Career_Board
 * @since   1.2.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_is_ability_granted' ) ) {
	/**
	 * Whether the current request is authorized for the named ability.
	 *
	 * @since 1.2.0
	 *
	 * @param string $name  Ability name (e.g., 'wcb_post_jobs').
	 * @param mixed  $input Optional input passed to the ability's permissions callback.
	 * @return bool
	 */
	function wp_is_ability_granted( string $name, mixed $input = null ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- intentional polyfill for a future WP core function.
		if ( function_exists( 'wp_get_ability' ) ) {
			$ability = wp_get_ability( $name );
			if ( $ability ) {
				$result = $ability->check_permissions( $input );
				return ! is_wp_error( $result ) && (bool) $result;
			}
		}

		// Cap fallback for abilities not yet registered (or when the
		// Abilities API is unavailable). Mirrors the previous codebase
		// behaviour where every function_exists() guard fell through to
		// current_user_can().
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- cap names are wcb_* customs registered by core/class-roles.php.
		return current_user_can( $name );
	}
}
