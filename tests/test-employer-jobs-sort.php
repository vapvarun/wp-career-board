<?php
/**
 * My Jobs: server-side sort (newest, most applicants, closing soon) and the
 * status note a job that is not live yet carries instead of view counts.
 *
 * Run: wp eval-file tests/test-employer-jobs-sort.php
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

$wcb_user = wp_insert_user( array( 'user_login' => 'qa_sort_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'wcb_employer' ) );
$wcb_job  = static function ( string $title, string $status, string $date, string $deadline ) use ( $wcb_user ): int {
	$id = (int) wp_insert_post(
		array(
			'post_type'   => 'wcb_job',
			'post_status' => $status,
			'post_author' => $wcb_user,
			'post_title'  => $title,
			'post_date'   => $date,
		)
	);
	if ( '' !== $deadline ) {
		update_post_meta( $id, '_wcb_deadline', $deadline );
	}
	return $id;
};
$wcb_soon   = gmdate( 'Y-m-d 23:59:59', time() + 3 * DAY_IN_SECONDS );
$wcb_later  = gmdate( 'Y-m-d 23:59:59', time() + 20 * DAY_IN_SECONDS );
$wcb_a      = $wcb_job( 'Senior Product Designer', 'publish', '2026-01-01 09:00:00', $wcb_later );
$wcb_b      = $wcb_job( 'Customer Success Manager', 'pending', '2026-03-01 09:00:00', '' );
$wcb_c      = $wcb_job( 'Data Analyst', 'publish', '2026-02-01 09:00:00', $wcb_soon );
$wcb_d      = $wcb_job( 'Office Coordinator', 'draft', '2026-02-15 09:00:00', '' );
$wcb_apps   = array();
foreach ( array( $wcb_a, $wcb_a, $wcb_a, $wcb_c ) as $wcb_for ) {
	$wcb_app = (int) wp_insert_post( array( 'post_type' => 'wcb_application', 'post_status' => 'publish', 'post_title' => 'Applicant' ) );
	update_post_meta( $wcb_app, '_wcb_job_id', $wcb_for );
	$wcb_apps[] = $wcb_app;
}
wp_set_current_user( $wcb_user );

$wcb_list = static function ( string $orderby ): array {
	$req = new WP_REST_Request( 'GET', '/wcb/v1/employers/me/jobs' );
	$req->set_param( 'per_page', 10 );
	if ( '' !== $orderby ) {
		$req->set_param( 'orderby', $orderby );
	}
	$res = rest_do_request( $req );
	return array( $res->get_status(), $res->get_data()['jobs'] ?? array() );
};

list( , $wcb_rows ) = $wcb_list( '' );
$wcb_chk( array( $wcb_b, $wcb_d, $wcb_c, $wcb_a ) === array_map( 'intval', wp_list_pluck( $wcb_rows, 'id' ) ), 'default order is newest first' );

list( , $wcb_rows ) = $wcb_list( 'applicants' );
$wcb_ids            = array_map( 'intval', wp_list_pluck( $wcb_rows, 'id' ) );
$wcb_chk( array( $wcb_a, $wcb_c ) === array_slice( $wcb_ids, 0, 2 ), 'most applicants first (3, then 1, then none)' );

list( , $wcb_rows ) = $wcb_list( 'deadline' );
$wcb_ids            = array_map( 'intval', wp_list_pluck( $wcb_rows, 'id' ) );
$wcb_chk( array( $wcb_c, $wcb_a ) === array_slice( $wcb_ids, 0, 2 ), 'closing soon: nearest deadline first, no deadline last' );

list( $wcb_status ) = $wcb_list( 'title' );
$wcb_chk( 400 === $wcb_status, 'an unknown sort is refused' );

$wcb_by_id = array_column( $wcb_rows, null, 'id' );
$wcb_chk( str_starts_with( (string) ( $wcb_by_id[ $wcb_b ]['statusNote'] ?? '' ), 'Waiting for review' ), 'a pending job says it waits for review' );
$wcb_chk( ! str_contains( (string) ( $wcb_by_id[ $wcb_b ]['statusNote'] ?? '' ), '—' ), 'a pending job shows its submitted date, not a dash' );
$wcb_chk( '' !== (string) ( $wcb_by_id[ $wcb_d ]['statusNote'] ?? '' ), 'a draft says it is not submitted' );
$wcb_chk( '' === (string) ( $wcb_by_id[ $wcb_a ]['statusNote'] ?? 'x' ), 'a live job carries no status note' );

foreach ( array_merge( $wcb_apps, array( $wcb_a, $wcb_b, $wcb_c, $wcb_d ) ) as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}
wp_delete_user( $wcb_user );

WP_CLI::log( "  Total: {$wcb_pass}+{$wcb_fail}  Pass: {$wcb_pass}  Fail: {$wcb_fail}" );
$wcb_fail > 0 ? WP_CLI::error( "{$wcb_fail} test(s) failed." ) : WP_CLI::success( 'My Jobs sort and status notes hold.' );
