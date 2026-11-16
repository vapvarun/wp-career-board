<?php
/**
 * Site Health flags a missing or overdue job-expiry sweep.
 *
 * Run: wp eval-file tests/test-cron-health.php
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

$wcb_pass = 0;
$wcb_fail = 0;
$wcb_chk  = static function ( bool $ok, string $label ) use ( &$wcb_pass, &$wcb_fail ): void {
	$ok ? ++$wcb_pass : ++$wcb_fail;
	WP_CLI::log( ( $ok ? '  PASS: ' : '  FAIL: ' ) . $label );
};

$wcb_tests = apply_filters( 'site_status_tests', array( 'direct' => array() ) );
$wcb_chk( isset( $wcb_tests['direct']['wcb_scheduled_jobs'] ), 'the test is registered with Site Health' );

$wcb_saved = wp_next_scheduled( 'wcb_check_job_expiry' );
$wcb_run   = static fn(): string => \WCB\Core\CronRegistry::health_test()['status'];

wp_clear_scheduled_hook( 'wcb_check_job_expiry' );
$wcb_chk( 'recommended' === $wcb_run(), 'a sweep that is not scheduled is flagged' );

wp_schedule_event( time() - 5 * HOUR_IN_SECONDS, 'hourly', 'wcb_check_job_expiry' );
$wcb_chk( 'recommended' === $wcb_run(), 'a sweep 5 hours overdue is flagged' );

wp_clear_scheduled_hook( 'wcb_check_job_expiry' );
wp_schedule_event( time() + 600, 'hourly', 'wcb_check_job_expiry' );
$wcb_chk( 'good' === $wcb_run(), 'a sweep due in 10 minutes is healthy' );

wp_clear_scheduled_hook( 'wcb_check_job_expiry' );
if ( false !== $wcb_saved ) {
	wp_schedule_event( $wcb_saved, 'hourly', 'wcb_check_job_expiry' );
}

WP_CLI::log( "  Total: {$wcb_pass}+{$wcb_fail}  Pass: {$wcb_pass}  Fail: {$wcb_fail}" );
$wcb_fail > 0 ? WP_CLI::error( "{$wcb_fail} test(s) failed." ) : WP_CLI::success( 'Cron health test holds.' );
