<?php
/**
 * Canonical inventory of plugin-owned cron hooks.
 *
 * Single source of truth for `Install::deactivate()` and any future
 * tooling that needs to enumerate WCB's scheduled events. Adding a
 * new wp_schedule_event() call ANYWHERE in the plugin requires
 * adding the hook name here so the deactivation teardown clears it.
 *
 * Earlier deactivate paths cleared only `wcb_check_job_expiry`,
 * leaving `wcb_send_deadline_reminders` and `wcb_expire_featured_jobs`
 * orphaned in WP_Cron after deactivate. With the registry, every
 * scheduler's HOOK constant has a corresponding entry here, and
 * deactivate iterates the list, so adding a new cron event in one
 * place forces the developer to update this list, which keeps the
 * teardown coherent.
 *
 * Pro-side cron hooks (wcbp_*) are NOT enumerated here — Pro owns
 * its own teardown via `ProInstall::deactivate()` per the lockstep
 * separation. Pro's registry mirrors this pattern.
 *
 * @package WP_Career_Board
 * @since   1.1.1
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cron-hook registry — canonical list of plugin-owned scheduled events.
 *
 * @since 1.1.1
 */
final class CronRegistry {

	/**
	 * Every cron hook this plugin schedules.
	 *
	 * Keep in sync with the schedulers under modules/. Each entry
	 * corresponds to a class that calls `wp_schedule_event( ..., $hook )`
	 * during its boot path:
	 *
	 *   wcb_check_job_expiry           ← modules/jobs/class-jobs-expiry.php
	 *   wcb_send_deadline_reminders    ← modules/jobs/class-deadline-reminders.php (HOOK const)
	 *   wcb_expire_featured_jobs       ← modules/jobs/class-featured-expiry.php (HOOK const)
	 *   wcb_prune_job_views            ← modules/jobs/class-job-views-retention.php (HOOK const)
	 *   wcb_process_account_deletions  ← modules/account/class-account-deletion-service.php (CRON_HOOK const)
	 *   wcb_prune_logs                 ← modules/gdpr/class-gdpr-module.php (PRUNE_HOOK const)
	 *   wcb_backfill_candidate_role    ← modules/candidates/class-candidates-module.php (BACKFILL_HOOK, single, re-armed per batch)
 *   wcb_restore_default_role       ← core/class-roles.php (REPAIR_HOOK, single, re-armed per batch)
	 *
	 * @since 1.1.1
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			'wcb_check_job_expiry',
			'wcb_send_deadline_reminders',
			'wcb_expire_featured_jobs',
			'wcb_prune_job_views',
			'wcb_process_account_deletions',
			\WCB\Modules\Gdpr\GdprModule::PRUNE_HOOK,
			\WCB\Core\PrivateFiles::MIGRATE_HOOK,
			\WCB\Modules\Jobs\JobSearch::INDEX_HOOK,
			\WCB\Modules\Candidates\CandidatesModule::BACKFILL_HOOK,
			Roles::REPAIR_HOOK,
		);
	}

	/**
	 * Add the "scheduled jobs are running" Site Health test.
	 *
	 * @since 1.8.4
	 * @return void
	 */
	public static function boot(): void {
		add_filter(
			'site_status_tests',
			static function ( array $tests ): array {
				$tests['direct']['wcb_scheduled_jobs'] = array(
					'label' => __( 'Career Board scheduled jobs are running', 'wp-career-board' ),
					'test'  => array( self::class, 'health_test' ),
				);
				return $tests;
			}
		);
	}

	/**
	 * Site Health: is the hourly job-expiry sweep scheduled and on time?
	 *
	 * Expired jobs staying live, deadline reminders and alert emails all depend on
	 * WP-Cron (or a real cron calling it) running. A sweep that is missing or more
	 * than two hours late means it is not.
	 *
	 * @since 1.8.4
	 * @return array<string,mixed>
	 */
	public static function health_test(): array {
		$result = array(
			'label'       => __( 'Career Board scheduled jobs are running', 'wp-career-board' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance', 'wp-career-board' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'Jobs expire, deadline reminders go out and job alerts are sent by scheduled tasks, and they are running on time.', 'wp-career-board' ) . '</p>',
			'test'        => 'wcb_scheduled_jobs',
		);

		$next = wp_next_scheduled( 'wcb_check_job_expiry' );
		if ( false !== $next && $next > time() - 2 * HOUR_IN_SECONDS ) {
			return $result;
		}

		$result['status']      = 'recommended';
		$result['label']       = __( 'Career Board scheduled jobs are not running on time', 'wp-career-board' );
		$result['description'] = '<p>' . esc_html(
			defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON
				? __( 'WP-Cron is switched off here and the hourly job-expiry check is overdue, so no real cron is calling it. Expired jobs stay live and job alerts and reminders are not sent until you set up a server cron that runs wp-cron.php.', 'wp-career-board' )
				: __( 'The hourly job-expiry check is missing or overdue. Expired jobs stay live and job alerts and reminders are not sent. Check that your host allows WP-Cron requests to this site, or deactivate and reactivate Career Board.', 'wp-career-board' )
		) . '</p>';
		return $result;
	}
}
