<?php
/**
 * Plugin install, activation, deactivation, and upgrade handling.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation, deactivation, and database schema management.
 *
 * @since 1.0.0
 */
final class Install {

	/**
	 * Current database schema version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DB_VERSION = '1.2.4';

	/**
	 * Prevent instantiation — all methods are static.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/**
	 * Run on plugin activation.
	 *
	 * Checks requirements, creates database tables, registers roles,
	 * and stores version options.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate(): void {
		self::check_requirements();
		self::maybe_upgrade();
		( new Roles() )->register();
		flush_rewrite_rules();
		// `wcb_db_version` is bumped inside maybe_upgrade() only when every
		// expected table actually exists. Setting it here unconditionally
		// (as we did pre-1.1.0) hid silent dbDelta failures: the version
		// bumped, the next activation skipped create_tables, and the missing
		// tables stayed missing forever. Closes the underlying class behind
		// the MariaDB 11.7+ `vector` collision.
		update_option( 'wcb_version', WCB_VERSION, false );
		set_transient( 'wcb_activation_redirect', true, 30 );
	}

	/**
	 * Idempotent runtime self-heal — runs from `init` priority 5 when the
	 * stored DB version is older than the file constant. Hosts that update
	 * via WP-CLI / managed-host auto-update skip register_activation_hook
	 * entirely, which left the 1.2 migration unrun on otherwise-current
	 * installs. The 1.2 block also reads the `wcb_pro_active` filter, which
	 * Pro registers from its `plugins_loaded@10` boot — `init@5` is the
	 * earliest hook guaranteed to fire after Pro's filter is registered.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function maybe_migrate(): void {
		$installed = (string) get_option( 'wcb_db_version', '0' );
		if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
			self::maybe_upgrade();
		}
	}

	/**
	 * Run on plugin deactivation.
	 *
	 * Clears every plugin-owned cron event by iterating the canonical
	 * CronRegistry list. Adding a new wp_schedule_event() call requires
	 * adding the hook to CronRegistry::all() so this teardown stays
	 * coherent — the registry is the single source of truth.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function deactivate(): void {
		foreach ( CronRegistry::all() as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
		flush_rewrite_rules();
	}

	/**
	 * Check minimum PHP and WordPress version requirements.
	 *
	 * Deactivates the plugin and halts if requirements are not met.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function check_requirements(): void {
		global $wp_version;

		if ( version_compare( PHP_VERSION, '8.1', '<' ) || version_compare( $wp_version, '6.9', '<' ) ) {
			deactivate_plugins( WCB_BASENAME );
			wp_die(
				esc_html__( 'WP Career Board requires PHP 8.1+ and WordPress 6.9+.', 'wp-career-board' ),
				esc_html__( 'Plugin Activation Error', 'wp-career-board' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Canonical list of plugin-owned tables that `create_tables()` must produce.
	 *
	 * Used by `verify_tables_exist()` to gate the `wcb_db_version` bump on
	 * actual schema state. Adding a new dbDelta call to `create_tables()`
	 * requires adding the bare table name (no `$wpdb->prefix`) here so the
	 * gate stays coherent.
	 *
	 * @since 1.1.0
	 * @return list<string>
	 */
	public static function expected_tables(): array {
		return array(
			'wcb_notifications_log',
			'wcb_job_views',
			'wcb_gdpr_log',
		);
	}

	/**
	 * Verify every expected table actually exists in the database.
	 *
	 * Returns false if even one expected table is missing — the caller (the
	 * version-bump in maybe_upgrade) must NOT advance `wcb_db_version` in
	 * that case so the next activation / upgrade-in-place gets to retry
	 * `create_tables()`. Pre-1.1.0 the version bumped unconditionally,
	 * which hid the MariaDB 11.7+ `vector`-column dbDelta failure.
	 *
	 * @since 1.1.0
	 * @return bool True when every expected table exists, false otherwise.
	 */
	private static function verify_tables_exist(): bool {
		global $wpdb;
		foreach ( self::expected_tables() as $table ) {
			$full = $wpdb->prefix . $table;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name comes from a static method-internal allowlist.
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full ) );
			if ( $exists !== $full ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Create custom database tables using dbDelta.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}wcb_notifications_log (
				id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id      BIGINT UNSIGNED NOT NULL,
				event_type   VARCHAR(80)     NOT NULL,
				channel      VARCHAR(20)     NOT NULL DEFAULT 'email',
				payload      LONGTEXT,
				status       VARCHAR(20)     NOT NULL DEFAULT 'sent',
				sent_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY user_id  (user_id),
				KEY event_type  (event_type)
			) ENGINE=InnoDB {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}wcb_job_views (
				id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				job_id     BIGINT UNSIGNED NOT NULL,
				viewed_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
				ip_hash    VARCHAR(64),
				PRIMARY KEY  (id),
				KEY job_id  (job_id),
				KEY viewed_at  (viewed_at)
			) ENGINE=InnoDB {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}wcb_gdpr_log (
				id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id    BIGINT UNSIGNED NOT NULL,
				action     VARCHAR(20)     NOT NULL,
				metadata   LONGTEXT,
				ip_hash    VARCHAR(64),
				created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY user_id  (user_id)
			) ENGINE=InnoDB {$charset};"
		);
	}

	/**
	 * Run upgrade routines when the DB version is outdated.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function maybe_upgrade(): void {
		$installed = get_option( 'wcb_db_version', '0' );

		if ( version_compare( (string) $installed, self::DB_VERSION, '<' ) ) {
			self::create_tables();
			self::seed_default_settings();
			// Reserved location terms ('remote', 'other') are seeded by
			// Plugin::init on init@20 — taxonomy registration happens on
			// init@10 and is unavailable during activation. Idempotent.

			// 1.2 — F-3: resume CPT visibility moved from Pro filter to Free
			// setting. Pre-existing sites with Pro active expect the resume
			// archive to stay public, so seed the setting from the install
			// state rather than letting the new default flip URLs to 404.
			if ( version_compare( (string) $installed, '1.2', '<' ) ) {
				$settings = \WCB\Admin\Settings::all();
				if ( ! array_key_exists( 'resume_archive_enabled', $settings ) ) {
					$settings['resume_archive_enabled'] = (bool) apply_filters( 'wcb_pro_active', false );
					update_option( 'wcb_settings', $settings );
					update_option( 'wcb_flush_rewrite_rules', 1 );
				}

				// 1.2 — F-4: allow_withdraw setting → wcb_withdraw_application
				// ability. Default ability grant covers true; only the false case
				// needs explicit revocation so site-owner intent survives the
				// migration. Setting is left in place for one cycle as a
				// deprecated tombstone, removed in 1.3.0.
				if ( array_key_exists( 'allow_withdraw', $settings ) && false === (bool) $settings['allow_withdraw'] ) {
					$candidate_role = get_role( 'wcb_candidate' );
					if ( $candidate_role && $candidate_role->has_cap( 'wcb_withdraw_application' ) ) {
						$candidate_role->remove_cap( 'wcb_withdraw_application' );
					}
				}

				// 1.2 — F-5: consolidate wcb_email_settings + wcb_captcha_driver
				// under wcb_settings so the Settings API is the single write
				// path. After migration the legacy options are deleted; reads
				// go through the wcb_get_email_settings / wcb_get_captcha_driver
				// helpers that fall back to the legacy rows during the upgrade
				// window.
				$current = \WCB\Admin\Settings::all();
				$dirty   = false;

				$legacy_emails = get_option( 'wcb_email_settings', null );
				if ( null !== $legacy_emails && ! isset( $current['emails'] ) ) {
					$current['emails'] = (array) $legacy_emails;
					$dirty             = true;
				}

				$legacy_captcha = get_option( 'wcb_captcha_driver', null );
				if ( null !== $legacy_captcha && ! isset( $current['captcha']['driver'] ) ) {
					$current['captcha']           = isset( $current['captcha'] ) && is_array( $current['captcha'] ) ? $current['captcha'] : array();
					$current['captcha']['driver'] = (string) $legacy_captcha;
					$dirty                        = true;
				}

				if ( $dirty ) {
					update_option( 'wcb_settings', $current );
					delete_option( 'wcb_email_settings' );
					delete_option( 'wcb_captcha_driver' );
				}
			}

			// 1.2.1 — F-5: every wcb_resume must carry an explicit
			// `_wcb_resume_public` flag so the resume-single render gate
			// (Pro) can default to private when the meta is absent. Pre-1.2.1
			// installs may have unset rows because the meta was only written
			// when the user toggled the public flag in the editor. Default
			// to private on migration: candidates explicitly opt back in via
			// the resume editor toggle. Idempotent — only writes rows that
			// have NO existing meta value.
			if ( version_compare( (string) $installed, '1.2.1', '<' ) ) {
				self::migrate_resume_public_flag();
			}

			// 1.2.3 — Backfill `_wcb_hq_location` company meta into the
			// `wcb_location` taxonomy. Pre-1.2.3 the HQ value was stored as
			// post meta only, so the employer-scoped job-form dropdown found
			// no terms attached to the company and fell back to "no location".
			// Idempotent: companies that already have HQ terms attached are
			// left untouched. Runs on init@20 once the taxonomy is registered.
			if ( version_compare( (string) $installed, '1.2.3', '<' ) ) {
				add_action(
					'init',
					static function (): void {
						Locations::backfill_existing_company_hq_terms();
					},
					25
				);
			}

			// 1.2.4 — Backfill canonical page IDs into wcb_settings by slug.
			// Sites that ran the setup wizard before \WCB\Admin\Pages existed,
			// or that lost their wcb_settings page assignments without losing
			// the underlying pages, end up with empty Pages-tab dropdowns and
			// look broken on first admin visit. The resolver writes assigned
			// IDs only for keys that are missing or stale, so site-owner
			// edits survive the migration.
			if ( version_compare( (string) $installed, '1.2.4', '<' ) ) {
				\WCB\Admin\Pages::backfill_from_slugs();
			}

			// 1.2.2 — F-2: absorb Pro's wcbp_resume_settings into wcb_settings.
			// Pre-1.2.2 the legacy migration ran inside Pro's resume module and
			// wrote to Free's option, violating the dependency arrow (Pro → Free,
			// never Free → Pro). Free now owns the migration; Pro reads via the
			// \WCB\Admin\Settings accessor. Idempotent: the per-key existence
			// guard plus delete_option() of the legacy row mean re-runs no-op.
			if ( version_compare( (string) $installed, '1.2.2', '<' ) ) {
				$legacy_resume = get_option( 'wcbp_resume_settings', null );
				if ( is_array( $legacy_resume ) && ! empty( $legacy_resume ) ) {
					$settings = (array) get_option( 'wcb_settings', array() );
					foreach ( array( 'max_resumes', 'resume_archive_page' ) as $key ) {
						if ( ! isset( $settings[ $key ] ) && isset( $legacy_resume[ $key ] ) ) {
							$settings[ $key ] = $legacy_resume[ $key ];
						}
					}
					update_option( 'wcb_settings', $settings );
					delete_option( 'wcbp_resume_settings' );
				}
				delete_option( 'wcbp_resume_settings_migrated' );
			}

			// Only bump the stored DB version if every expected table now
			// exists. A silently-failed dbDelta (e.g. the MariaDB 11.7+
			// `vector` collision pre-fa3a337) used to bump the version
			// anyway, which masked the failure forever — subsequent
			// activations skipped `create_tables` because the version
			// looked current. The verification below makes the bump
			// dbDelta-success-conditional.
			if ( self::verify_tables_exist() ) {
				update_option( 'wcb_db_version', self::DB_VERSION, false );
			}
		}
	}

	/**
	 * Seed default `wcb_settings` keys that are absent.
	 *
	 * Runs from `maybe_upgrade()` on Free's own activation/upgrade and from
	 * Pro's activation hook. Idempotent: only writes keys that the existing
	 * option does not already have, so site-owner choices survive re-runs
	 * untouched. Pro contributes its own defaults through the
	 * `wcb_install_default_settings` filter — Pro registers the filter at
	 * plugins_loaded priority 10 and also calls this method directly from
	 * its own activation hook, so the filter contribution lands without a
	 * second pageload.
	 *
	 * The dependency arrow is preserved: Pro never writes wcb_settings on
	 * its own behalf — Free's installer reads Pro's filter contribution and
	 * performs the write.
	 *
	 * Public so Pro's activation/upgrade hook can invoke it; not part of any
	 * documented extension surface for third-party plugins (use the
	 * `wcb_install_default_settings` filter to contribute defaults).
	 *
	 * @since 1.2.2
	 * @return void
	 */
	public static function seed_default_settings(): void {
		$existing = (array) get_option( 'wcb_settings', array() );

		/**
		 * Filter the default wcb_settings values written on plugin install/upgrade.
		 *
		 * Pro hooks this to seed Pro-specific defaults (e.g. resume_archive_enabled).
		 * Free's installer merges the filter output onto the existing option using
		 * key-absence as the gate, so user-configured values are never overwritten.
		 *
		 * Use this filter only for keys that have a sensible Pro-side default; do
		 * not overwrite Free-owned defaults from Pro.
		 *
		 * @since 1.2.2
		 *
		 * @param array<string,mixed> $defaults Default settings array.
		 */
		$defaults = (array) apply_filters( 'wcb_install_default_settings', array() );

		if ( empty( $defaults ) ) {
			return;
		}

		$dirty = false;
		foreach ( $defaults as $key => $value ) {
			if ( ! array_key_exists( $key, $existing ) ) {
				$existing[ $key ] = $value;
				$dirty            = true;
			}
		}

		if ( $dirty ) {
			update_option( 'wcb_settings', $existing );
		}
	}

	/**
	 * Backfill `_wcb_resume_public` on every wcb_resume that lacks the meta.
	 *
	 * Default: private (`'0'`). Candidates re-opt-in through the resume
	 * editor toggle which writes `'1'`. Idempotent — only touches rows
	 * with no existing value, so re-running the migration cannot flip an
	 * existing public resume back to private.
	 *
	 * Resumes whose author has been deleted are still defaulted to private —
	 * the orphaned resume cannot consent for itself.
	 *
	 * @since 1.2.1
	 * @return void
	 */
	private static function migrate_resume_public_flag(): void {
		// Pro might not be active when this runs from Free's activation hook;
		// the wcb_resume CPT is registered by Pro. The query stays safe — if
		// the post type isn't registered yet, `get_posts` returns an empty
		// array and we exit cleanly. The migration re-runs idempotently next
		// time it boots.
		$resume_ids = get_posts(
			array(
				'post_type'      => 'wcb_resume',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one-time migration.
					array(
						'key'     => '_wcb_resume_public',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		foreach ( $resume_ids as $resume_id ) {
			update_post_meta( (int) $resume_id, '_wcb_resume_public', '0' );
		}
	}
}
