<?php
/**
 * WP Career Board companion registry.
 *
 * A single declarative, filterable catalog of the Wbcom plugins WP Career Board
 * integrates with (BuddyNext, Jetonomy, WB Gamification, Learnomy). Each entry
 * is DATA, not code - Pro and third parties extend the list via the
 * `wcb_companions` filter. Every UI + integration decision keys off
 * `status()` / `is_active()` (a runtime capability probe), never a hardcoded
 * plugin path, so "works standalone" and "no duplication" both hold:
 * capability present -> delegate; absent -> hide.
 *
 * Self-contained on purpose: this whole `integrations/companions/` wrapper is
 * designed to copy cleanly into other Wbcom plugins (they all bundle the
 * identical EDD SL SDK the installer speaks to).
 *
 * @package WP_Career_Board
 * @since   1.4.6
 */

declare( strict_types=1 );

namespace WCB\Integrations\Companions;

defined( 'ABSPATH' ) || exit;

final class CompanionRegistry {

	/**
	 * Resolve the companion catalog. Each entry:
	 *   label      string   Display name.
	 *   why        string   One-line value proposition.
	 *   detect     callable Returns true when the companion's capability is live.
	 *   free       array    { item_id, key, basename } for one-click free install.
	 *   store_url  string   Product page for the store link.
	 *   unlocks    string   What this turns on inside WP Career Board when connected.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		/**
		 * Filter the WP Career Board companion catalog. Pro + third-party plugins
		 * add their own entries here; the installer + admin screen render whatever
		 * this returns.
		 *
		 * @since 1.4.6
		 *
		 * @param array<string, array<string, mixed>> $companions Slug => entry.
		 */
		return (array) apply_filters(
			'wcb_companions',
			array(
				'buddynext'       => array(
					'label'     => __( 'BuddyNext', 'wp-career-board' ),
					'why'       => __( 'Community engine - profiles, activity feeds, and member spaces.', 'wp-career-board' ),
					'detect'    => static fn() => defined( 'BUDDYNEXT_VERSION' ),
					'free'      => array(
						'item_id'  => 1664401,
						'key'      => 'buddynext9a3c7e1d5f2b8a4c6e0d9b7f1a2c8e55',
						'basename' => 'buddynext/buddynext.php',
					),
					'store_url' => 'https://wbcomdesigns.com/downloads/buddynext/',
					'unlocks'   => __( 'Member profiles and activity feeds for job seekers.', 'wp-career-board' ),
				),
				'jetonomy'        => array(
					'label'     => __( 'Jetonomy', 'wp-career-board' ),
					'why'       => __( 'Discussions and Q&A spaces around your job board.', 'wp-career-board' ),
					'detect'    => static fn() => function_exists( 'jetonomy' ) || class_exists( '\\Jetonomy\\Plugin' ),
					'free'      => array(
						'item_id'  => 1660320,
						'key'      => 'wbcomfreec7e2a9b45d8f1c3e6a0b9d2f7c4e8a11',
						'basename' => 'jetonomy/jetonomy.php',
					),
					'store_url' => 'https://wbcomdesigns.com/downloads/jetonomy/',
					'unlocks'   => __( 'Community forums and Q&A tied to job listings.', 'wp-career-board' ),
				),
				'wb-gamification' => array(
					'label'     => __( 'WB Gamification', 'wp-career-board' ),
					'why'       => __( 'Points and badges for applications and hires.', 'wp-career-board' ),
					'detect'    => static fn() => defined( 'WB_GAM_VERSION' ) || function_exists( 'wb_gam_submit_event' ),
					'free'      => array(
						'item_id'  => 1662147,
						'key'      => 'wbcomfree6e2a9c1d7b4f3c8a0e5d9b2f1a7c6e11',
						'basename' => 'wb-gamification/wb-gamification.php',
					),
					'store_url' => 'https://wbcomdesigns.com/downloads/wordpress-gamification-plugin/',
					'unlocks'   => __( 'Engagement rewards for applicants and hiring milestones.', 'wp-career-board' ),
				),
				'learnomy'        => array(
					'label'     => __( 'Learnomy', 'wp-career-board' ),
					'why'       => __( 'Courses and certificates tied to job roles.', 'wp-career-board' ),
					'detect'    => static fn() => defined( 'LEARNOMY_VERSION' ) || class_exists( '\\Learnomy\\Learnomy' ),
					'free'      => array(
						'item_id'  => 1662698,
						'key'      => 'wbcomfree5d8a1f3c7b2e9a4c6f0d1e8b3c9a7f25',
						'basename' => 'learnomy/learnomy.php',
					),
					'store_url' => 'https://wbcomdesigns.com/downloads/learnomy/',
					'unlocks'   => __( 'Skill-building courses linked to job requirements.', 'wp-career-board' ),
				),
				'wpmediaverse'    => array(
					'label'     => __( 'MediaVerse', 'wp-career-board' ),
					'why'       => __( 'Direct messaging and media galleries for members.', 'wp-career-board' ),
					'detect'    => static fn() => defined( 'MVS_VERSION' ) || class_exists( '\\WPMediaVerse\\Core\\Plugin' ),
					'free'      => array(
						'item_id'  => 1660826,
						'key'      => 'wbcomfree7a9c2e5d1f8b4c6a3e0d9b2f7c1a8e44',
						'basename' => 'wpmediaverse/wpmediaverse.php',
					),
					'store_url' => 'https://wbcomdesigns.com/downloads/mediaverse/',
					'unlocks'   => __( 'Direct messages between employers and candidates.', 'wp-career-board' ),
				),
				'wb-listora'      => array(
					'label'     => __( 'Listora', 'wp-career-board' ),
					'why'       => __( 'Member-submitted directory listings.', 'wp-career-board' ),
					'detect'    => static fn() => defined( 'WB_LISTORA_VERSION' ),
					'free'      => array(
						'item_id'  => 1662779,
						'key'      => 'wbcomfree8a5d1c7e3f2b9a4c6e0d1b7f9c2a6e55',
						'basename' => 'wb-listora/wb-listora.php',
					),
					'store_url' => 'https://wbcomdesigns.com/downloads/listora/',
					'unlocks'   => __( 'A company/services directory alongside the job board.', 'wp-career-board' ),
				),
			)
		);
	}

	/**
	 * A single companion entry, or null when the slug is unknown.
	 *
	 * @param string $slug Companion slug.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $slug ): ?array {
		$all = self::all();
		return $all[ $slug ] ?? null;
	}

	/**
	 * Lifecycle status of a companion:
	 *   'active'             - its capability probe returns true (installed + on).
	 *   'installed_inactive' - the plugin file is present but not active.
	 *   'not_installed'      - absent.
	 *
	 * @param string $slug Companion slug.
	 * @return string
	 */
	public static function status( string $slug ): string {
		$entry = self::get( $slug );
		if ( null === $entry ) {
			return 'not_installed';
		}

		$detect = $entry['detect'] ?? null;
		if ( is_callable( $detect ) && (bool) $detect() ) {
			return 'active';
		}

		// Capability absent - is the free plugin at least on disk (so we offer
		// "Activate" instead of "Install")?
		$basename = (string) ( $entry['free']['basename'] ?? '' );
		if ( '' !== $basename && self::plugin_file_exists( $basename ) ) {
			return 'installed_inactive';
		}

		return 'not_installed';
	}

	/**
	 * Whether a companion's capability is live. The single gate WCB
	 * integration code should call before delegating to a companion.
	 *
	 * @param string $slug Companion slug.
	 * @return bool
	 */
	public static function is_active( string $slug ): bool {
		return 'active' === self::status( $slug );
	}

	/**
	 * Whether a plugin file exists under wp-content/plugins, without loading the
	 * (potentially expensive) full plugin list on every call.
	 *
	 * @param string $basename e.g. "buddynext/buddynext.php".
	 * @return bool
	 */
	private static function plugin_file_exists( string $basename ): bool {
		$path = trailingslashit( WP_PLUGIN_DIR ) . ltrim( $basename, '/' );
		return file_exists( $path );
	}
}
