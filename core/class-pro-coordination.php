<?php
/**
 * Pro coordination filter API — the documented surface Free fires for Pro to hook.
 *
 * Free MUST NOT reference Pro classes, functions, or option keys directly. Every
 * piece of cross-plugin coordination flows through one of the filters declared
 * here. This file is the single source of truth for the Free→Pro contract; if
 * a Pro feature needs to influence Free's UI or response, it hooks one of these
 * filters and Free reads the result.
 *
 * Pro's matching listeners live in `wp-career-board-pro/core/class-free-coordination.php`.
 *
 * Why this exists:
 * - Free is bundled with Reign / BuddyX → it ships standalone to community sites
 *   that may never install Pro. Every Pro-aware branch in Free is dead code on
 *   those installs and a bug-trap on installs where Pro is removed.
 * - Pro renames a class or function → Free breaks. We hit this risk every WPCS
 *   sweep and namespace refactor.
 * - Addon vendors and Pro itself should hook into the same documented surface.
 *   Today Pro uses internal `class_exists` lookups that aren't a public contract.
 *
 * The dependency arrow is **Pro → Free, never Free → Pro**.
 *
 * @package WP_Career_Board
 * @since   1.2.0
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Declares + documents every filter Pro is allowed to hook.
 *
 * Filters return their `$default` argument unchanged when Pro is absent —
 * Free renders correctly with no Pro listener present. None of these filters
 * is load-bearing for a Free-standalone site.
 *
 * @since 1.2.0
 */
final class ProCoordination {

	/**
	 * Documented filter list — for reference + IDE discovery.
	 *
	 * Each filter is also documented inline at its callsite, but this catalog
	 * is the single index of every coordination point Free exposes. Pro's
	 * FreeCoordination class hooks this exact list.
	 *
	 * | Filter | Default | Purpose |
	 * |---|---|---|
	 * | wcb_pro_active             | false                                | Whether Pro is installed and active |
	 * | wcb_pro_licensed           | false                                | Whether Pro license is valid (cached) |
	 * | wcb_pro_version            | (string) ""                          | Pro plugin version string (Pro returns WCBP_VERSION constant) |
	 * | wcb_module_renders         | []                                   | Map slug => HTML; Pro injects HTML for module slots (alerts subscribe button, notifications bell, board switcher) |
	 * | wcb_pro_alerts_enabled     | false                                | Whether Pro Alerts module is loaded — gates Free's "Job Alerts" tab + alert-saving CTAs |
	 * | wcb_pro_resumes_enabled    | false                                | Whether Pro Resumes module is loaded — gates Free's "My Resumes" tab + resume REST calls |
	 * | wcb_board_currency         | (string) $default                    | Currency code for a given board ID |
	 * | wcb_currency_catalog       | array<code,array{name,symbol}>       | Currency catalog — every consumer (admin dropdowns, REST schema, salary formatters, Pro board settings, Pro CSV importer) reads from this single filter |
	 * | wcb_pro_upsell_html        | (string) $default                    | HTML for Pro upsell at a named location ("admin_dashboard", "settings_pro_tab", etc.) |
	 * | wcb_pro_upsell_url         | (string) "https://store.wbcomdesigns.com/wp-career-board-pro/" | Upsell destination URL — overridable for white-label installs |
	 * | wcb_pro_settings_saved_notice | null                              | Text for "Settings saved" notice when Pro fires its own settings save |
	 * | wcb_search_active_shortcodes  | ['wcb_']                          | Shortcode prefix patterns Free's needs-script detection should also look for |
	 *
	 * Pro listeners pair with these filters in the Pro side's
	 * core/class-free-coordination.php file.
	 *
	 * @since 1.2.0
	 * @return array<string, string>
	 */
	public static function documented_filters(): array {
		return array(
			'wcb_pro_active'                => 'Whether Pro is installed and active',
			'wcb_pro_licensed'              => 'Whether Pro license is valid',
			'wcb_pro_version'               => 'Pro plugin version string',
			'wcb_module_renders'            => 'Map of slug => HTML for Pro-rendered module slots',
			'wcb_pro_alerts_enabled'        => 'Whether Pro Alerts module is loaded',
			'wcb_pro_resumes_enabled'       => 'Whether Pro Resumes module is loaded — gates Free\'s "My Resumes" tab + resume REST calls',
			'wcb_board_currency'            => 'Currency code for a given board ID',
			'wcb_currency_catalog'          => 'Currency catalog (code => array{name, symbol}) — single source of truth for every currency consumer',
			'wcb_pro_upsell_html'           => 'HTML for Pro upsell at a named location',
			'wcb_pro_upsell_url'            => 'Upsell destination URL (overridable for white-label)',
			'wcb_pro_settings_saved_notice' => 'Text for the Pro-side settings-saved notice',
			'wcb_search_active_shortcodes'  => 'Shortcode prefix patterns Free should detect',
		);
	}
}
