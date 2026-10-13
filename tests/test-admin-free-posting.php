<?php
/**
 * Site admins post, move, republish and feature jobs for free.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-admin-free-posting.php
 *
 * Needs a board with a credit price (Pro credits). Proves an admin with no
 * credits can post on it through REST, publish their own job from the editor,
 * move an employer's job onto it and feature a job, while an employer with no
 * credits is still refused. Creates its own users and jobs and deletes them.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::warning( "  FAIL: {$label}" );
	}
}

/**
 * Dispatch an internal REST request as a user.
 *
 * @param string $method  HTTP method.
 * @param string $route   REST route path.
 * @param array  $params  Request parameters.
 * @param int    $user_id User to authenticate as.
 * @return WP_REST_Response
 */
function wcb_afp_rest( string $method, string $route, array $params, int $user_id ): WP_REST_Response {
	wp_set_current_user( $user_id );
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $k => $v ) {
		$request->set_param( $k, $v );
	}
	return rest_do_request( $request );
}

rest_get_server();

WP_CLI::log( '' );
WP_CLI::log( '  Admin Free Posting Tests' );

$wcb_paid_board = 0;
foreach ( get_posts( array( 'post_type' => 'wcb_board', 'numberposts' => -1, 'fields' => 'ids' ) ) as $wcb_b ) {
	if ( (int) apply_filters( 'wcb_board_credit_cost', 0, (int) $wcb_b ) > 0 ) {
		$wcb_paid_board = (int) $wcb_b;
		break;
	}
}
if ( ! $wcb_paid_board ) {
	WP_CLI::error( 'No board with a credit price: set one in Pro board settings first.' );
}
$wcb_free_board = 0;
foreach ( get_posts( array( 'post_type' => 'wcb_board', 'numberposts' => -1, 'fields' => 'ids' ) ) as $wcb_b ) {
	if ( 0 === (int) apply_filters( 'wcb_board_credit_cost', 0, (int) $wcb_b ) ) {
		$wcb_free_board = (int) $wcb_b;
		break;
	}
}

$wcb_suffix   = wp_generate_password( 6, false );
$wcb_admin    = wp_insert_user( array( 'user_login' => 'afp-admin-' . $wcb_suffix, 'user_pass' => wp_generate_password(), 'user_email' => 'afp-admin-' . $wcb_suffix . '@example.test', 'role' => 'administrator' ) );
$wcb_employer = wp_insert_user( array( 'user_login' => 'afp-emp-' . $wcb_suffix, 'user_pass' => wp_generate_password(), 'user_email' => 'afp-emp-' . $wcb_suffix . '@example.test', 'role' => 'wcb_employer' ) );
$wcb_created  = array();

$wcb_job_params = static fn ( string $title, int $board ): array => array(
	'title'       => $title,
	'description' => 'Lead a small team that keeps our hiring platform fast and reliable for thousands of employers.',
	'board_id'    => $board,
);

// 1. REST create on the paid board.
$wcb_r = wcb_afp_rest( 'POST', '/wcb/v1/jobs', $wcb_job_params( 'Engineering Manager, Platform', $wcb_paid_board ), (int) $wcb_admin );
wcb_assert( in_array( $wcb_r->get_status(), array( 200, 201 ), true ), 'admin with 0 credits posts on a paid board (got ' . $wcb_r->get_status() . ')' );
$wcb_created[] = (int) ( $wcb_r->get_data()['id'] ?? 0 );
wcb_assert( 'publish' === get_post_status( end( $wcb_created ) ), "admin's own post goes live without waiting for review" );

$wcb_r = wcb_afp_rest( 'POST', '/wcb/v1/jobs', $wcb_job_params( 'Customer Success Lead', $wcb_paid_board ), (int) $wcb_employer );
wcb_assert( 402 === $wcb_r->get_status(), 'employer with 0 credits is still refused on a paid board (402)' );

// 2. Admin's own job published from the editor goes live, not "awaiting payment".
wp_set_current_user( (int) $wcb_admin );
$wcb_own = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'draft', 'post_title' => 'Head of People Operations', 'post_author' => (int) $wcb_admin ) );
update_post_meta( $wcb_own, '_wcb_board_id', $wcb_paid_board );
wp_update_post( array( 'ID' => $wcb_own, 'post_status' => 'publish' ) );
wcb_assert( 'publish' === get_post_status( $wcb_own ) && '' === get_post_meta( $wcb_own, '_wcb_awaiting_payment', true ), "admin's own job published in the editor goes live" );
$wcb_created[] = $wcb_own;

// 3. Admin moves an employer's job onto the paid board.
if ( $wcb_free_board ) {
	wp_set_current_user( (int) $wcb_employer );
	$wcb_emp_job = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'Warehouse Shift Supervisor', 'post_author' => (int) $wcb_employer ) );
	update_post_meta( $wcb_emp_job, '_wcb_board_id', $wcb_free_board );
	$wcb_created[] = $wcb_emp_job;
	$wcb_r = wcb_afp_rest( 'PATCH', '/wcb/v1/jobs/' . $wcb_emp_job, array( 'board_id' => $wcb_paid_board ), (int) $wcb_admin );
	wcb_assert( 200 === $wcb_r->get_status() && $wcb_paid_board === (int) get_post_meta( $wcb_emp_job, '_wcb_board_id', true ), "admin moves an employer's job onto a paid board for free" );
	$wcb_r = wcb_afp_rest( 'PATCH', '/wcb/v1/jobs/' . $wcb_emp_job, array( 'board_id' => $wcb_free_board ), (int) $wcb_employer );
	$wcb_r = wcb_afp_rest( 'PATCH', '/wcb/v1/jobs/' . $wcb_emp_job, array( 'board_id' => $wcb_paid_board ), (int) $wcb_employer );
	wcb_assert( 402 === $wcb_r->get_status(), 'the employer moving it onto the paid board still pays (402)' );

	// 4. Feature.
	wp_set_current_user( (int) $wcb_admin );
	wcb_assert( true === \WCB\Modules\Jobs\JobPayment::charge( $wcb_emp_job, 'feature' ), 'admin features a job for free' );
}

// 5. The exemption is filterable.
add_filter( 'wcb_job_payment_exempt', '__return_false' );
wp_set_current_user( (int) $wcb_admin );
wcb_assert( false === \WCB\Modules\Jobs\JobPayment::is_exempt(), 'wcb_job_payment_exempt can turn it off' );
remove_filter( 'wcb_job_payment_exempt', '__return_false' );

// Teardown.
foreach ( array_filter( $wcb_created ) as $wcb_id ) {
	wp_delete_post( (int) $wcb_id, true );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( $wcb_admin, $wcb_employer ) as $wcb_user ) {
	wp_delete_user( (int) $wcb_user );
}

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All admin free posting tests passed.' );
}
