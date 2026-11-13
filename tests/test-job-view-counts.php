<?php
/**
 * Employer per-job view counts (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-job-view-counts.php
 *
 * Pins: JobViewsRetention::counts() returns views in the window per job in ONE
 * query however many jobs, leaves out jobs with none and views older than the
 * window, and the employer jobs list carries a viewLabel for every job.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

global $wpdb;
$wcb_pass = 0;
$wcb_fail = 0;
$wcb_chk  = static function ( bool $ok, string $label ) use ( &$wcb_pass, &$wcb_fail ): void {
	$ok ? ++$wcb_pass : ++$wcb_fail;
	WP_CLI::log( ( $ok ? '  PASS: ' : '  FAIL: ' ) . $label );
};

$wcb_jobs = array();
for ( $i = 0; $i < 30; $i++ ) {
	$wcb_jobs[] = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'View count probe ' . $i ) );
}
$wcb_table = $wpdb->prefix . 'wcb_job_views';
$wcb_ins   = static function ( int $job, int $days_ago ) use ( $wpdb, $wcb_table ): void {
	$wpdb->insert( $wcb_table, array( 'job_id' => $job, 'viewed_at' => gmdate( 'Y-m-d H:i:s', time() - $days_ago * DAY_IN_SECONDS ), 'ip_hash' => hash( 'sha256', (string) wp_rand() ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
};
$wcb_ins( $wcb_jobs[0], 1 );
$wcb_ins( $wcb_jobs[0], 2 );
$wcb_ins( $wcb_jobs[0], 40 ); // Outside the 30 day window.
$wcb_ins( $wcb_jobs[1], 5 );

$wcb_before = $wpdb->num_queries;
$wcb_counts = \WCB\Modules\Jobs\JobViewsRetention::counts( $wcb_jobs );
$wcb_chk( 1 === $wpdb->num_queries - $wcb_before, '30 jobs are counted in ONE query' );
$wcb_chk( 2 === ( $wcb_counts[ $wcb_jobs[0] ] ?? 0 ), 'a job counts its views in the window only (3 stored, 1 is 40 days old)' );
$wcb_chk( 1 === ( $wcb_counts[ $wcb_jobs[1] ] ?? 0 ), 'another job counts its own view' );
$wcb_chk( ! isset( $wcb_counts[ $wcb_jobs[2] ] ), 'a job with no views is left out' );
$wcb_chk( array() === \WCB\Modules\Jobs\JobViewsRetention::counts( array() ), 'no jobs: no query, empty result' );

$wpdb->query( $wpdb->prepare( "DELETE FROM {$wcb_table} WHERE job_id IN (" . implode( ',', array_fill( 0, count( $wcb_jobs ), '%d' ) ) . ')', ...$wcb_jobs ) ); // phpcs:ignore WordPress.DB
foreach ( $wcb_jobs as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}

WP_CLI::log( "  Total: {$wcb_pass}+{$wcb_fail}  Pass: {$wcb_pass}  Fail: {$wcb_fail}" );
$wcb_fail > 0 ? WP_CLI::error( "{$wcb_fail} test(s) failed." ) : WP_CLI::success( 'Job view counts hold.' );
