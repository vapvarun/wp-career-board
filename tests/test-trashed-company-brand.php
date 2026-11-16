<?php
/**
 * A trashed company brands nothing (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-trashed-company-brand.php
 *
 * Pins: CompanyMetaShape::for_job() returns 0 once the company (the job's own
 * or the employer's) is trashed or deleted, and returns it again on restore.
 * The job page and list cards read the company only through it.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Core\CompanyMetaShape;

$wcb_pass = 0;
$wcb_fail = 0;
$wcb_chk  = static function ( bool $ok, string $label ) use ( &$wcb_pass, &$wcb_fail ): void {
	$ok ? ++$wcb_pass : ++$wcb_fail;
	WP_CLI::log( ( $ok ? '  PASS: ' : '  FAIL: ' ) . $label );
};

$wcb_s       = wp_generate_password( 6, false );
$wcb_emp     = (int) wp_insert_user( array( 'user_login' => 'tcb_' . $wcb_s, 'user_pass' => wp_generate_password(), 'user_email' => "tcb_{$wcb_s}@example.test", 'role' => 'wcb_employer' ) );
$wcb_company = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_author' => $wcb_emp, 'post_title' => 'Harbor Light ' . $wcb_s ) );
update_user_meta( $wcb_emp, '_wcb_company_id', $wcb_company );
$wcb_job_a = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_author' => $wcb_emp, 'post_title' => 'Roaster ' . $wcb_s ) );
$wcb_job_b = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_author' => $wcb_emp, 'post_title' => 'Barista ' . $wcb_s ) );
update_post_meta( $wcb_job_b, '_wcb_company_id', $wcb_company );

$wcb_a = get_post( $wcb_job_a );
$wcb_b = get_post( $wcb_job_b );
$wcb_chk( $wcb_company === CompanyMetaShape::for_job( $wcb_a ), 'employer fallback resolves a live company' );
$wcb_chk( $wcb_company === CompanyMetaShape::for_job( $wcb_b ), 'the job\'s own company resolves' );

wp_trash_post( $wcb_company );
// Trashing closes the company's own jobs; restore the status so the read is isolated.
$wcb_a = get_post( $wcb_job_a );
$wcb_b = get_post( $wcb_job_b );
$wcb_chk( 0 === CompanyMetaShape::for_job( $wcb_a ), 'trashed company: the employer fallback brands nothing' );
$wcb_chk( 0 === CompanyMetaShape::for_job( $wcb_b ), 'trashed company: the job\'s own id brands nothing' );

wp_untrash_post( $wcb_company );
$wcb_chk( $wcb_company === CompanyMetaShape::for_job( $wcb_a ), 'restored company brands the job again' );

wp_delete_post( $wcb_company, true );
$wcb_chk( 0 === CompanyMetaShape::for_job( get_post( $wcb_job_a ) ), 'deleted company brands nothing' );

wp_delete_post( $wcb_job_a, true );
wp_delete_post( $wcb_job_b, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $wcb_emp );

WP_CLI::log( "  Total: {$wcb_pass}+{$wcb_fail}  Pass: {$wcb_pass}  Fail: {$wcb_fail}" );
$wcb_fail > 0 ? WP_CLI::error( "{$wcb_fail} test(s) failed." ) : WP_CLI::success( 'Trashed companies brand nothing.' );
