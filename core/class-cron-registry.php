<?php
/**
 * Canonical inventory of plugin-owned cron hooks.
 *
 * Single source of truth for `Install::deactivate()` and any future
 * tooling that needs to enumerate WCB's scheduled events. Adding a
 * new wp_schedule_event() call ANYWHERE in the plugin requires
 * adding the hook name here so the deactivation teardown clears it.
 *
 * Closes Basecamp 9874932439 — the deactivate path previously
 * cleared only `wcb_check_job_expiry`, leaving
 * `wcb_send_deadline_reminders` and `wcb_expire_featured_jobs`
 * orphaned in WP_Cron after deactivate. With the registry, every
 * scheduler's HOOK constant has a corresponding entry here, and
 * deactivate iterates the list — adding a new cron event in one
 * place forces the developer to update this list, which forces
 * the teardown to stay coherent.
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
	 *   wcb_check_job_expiry        ← modules/jobs/class-jobs-expiry.php
	 *   wcb_send_deadline_reminders ← modules/jobs/class-deadline-reminders.php (HOOK const)
	 *   wcb_expire_featured_jobs    ← modules/jobs/class-featured-expiry.php (HOOK const)
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
		);
	}
}
