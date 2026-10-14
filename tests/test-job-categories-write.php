<?php
/**
 * How many categories a job may have, on create and update.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-job-categories-write.php
 *
 * The limit is `wcb_job_category_limit` (Free: 1, Pro raises it). Over the
 * limit is a 400, on POST and on a PATCH that sends only categories. A job
 * already over the limit (set in wp-admin) saves with the same categories or
 * fewer, so editing it in the front-end form never drops categories the
 * employer did not touch, and the form seeds every one of them.
 *
 * Self-contained: creates its own jobs and deletes them at the end.
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
 * Dispatch an internal REST write.
 *
 * @param string $method HTTP method.
 * @param string $route  REST route path.
 * @param array  $params Body parameters.
 * @return WP_REST_Response
 */
function wcb_cat_send( string $method, string $route, array $params ): WP_REST_Response {
	// New posts go on a free board, so credits never decide the answer.
	if ( 'POST' === $method && ! empty( $GLOBALS['wcb_cat_free_board'] ) ) {
		$params += array( 'board_id' => $GLOBALS['wcb_cat_free_board'] );
	}
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $k => $v ) {
		$request->set_param( $k, $v );
	}
	return rest_do_request( $request );
}

/**
 * The job's category slugs, sorted.
 *
 * @param int $job_id Job ID.
 * @return string[]
 */
function wcb_cat_slugs( int $job_id ): array {
	clean_object_term_cache( $job_id, 'wcb_job' );
	$slugs = wp_get_object_terms( $job_id, 'wcb_category', array( 'fields' => 'slugs' ) );
	sort( $slugs );
	return $slugs;
}

rest_get_server();

WP_CLI::log( '' );
WP_CLI::log( '  Job Category Limit Tests' );

// Real categories; any this site lacks are created and removed afterwards.
$wcb_made_terms = array();
$wcb_cats       = array();
foreach ( array( 'Design', 'Engineering', 'Marketing', 'Sales' ) as $wcb_name ) {
	$wcb_term = get_term_by( 'slug', sanitize_title( $wcb_name ), 'wcb_category' );
	if ( ! $wcb_term ) {
		$wcb_new          = wp_insert_term( $wcb_name, 'wcb_category' );
		$wcb_made_terms[] = (int) $wcb_new['term_id'];
		$wcb_term         = get_term( (int) $wcb_new['term_id'], 'wcb_category' );
	}
	$wcb_cats[] = $wcb_term->slug;
}
$wcb_three = array_slice( $wcb_cats, 0, 3 );
sort( $wcb_three );

// An employer: site admins are not limited (checked at the end).
$wcb_employer = (int) wp_insert_user( array( 'user_login' => 'cat-emp-' . wp_generate_password( 6, false ), 'user_pass' => wp_generate_password(), 'user_email' => 'cat-emp-' . wp_generate_password( 6, false ) . '@example.test', 'role' => 'wcb_employer' ) );
$GLOBALS['wcb_cat_free_board'] = 0;
foreach ( get_posts( array( 'post_type' => 'wcb_board', 'numberposts' => -1, 'fields' => 'ids' ) ) as $wcb_b ) {
	if ( 0 === (int) apply_filters( 'wcb_board_credit_cost', 0, (int) $wcb_b ) ) {
		$GLOBALS['wcb_cat_free_board'] = (int) $wcb_b;
		break;
	}
}
wp_set_current_user( $wcb_employer );
$wcb_jobs   = array();
$wcb_status = static fn ( WP_REST_Response $r ): int => $r->get_status();
$wcb_job    = static function ( string $title, array $cats ) use ( &$wcb_jobs ): int {
	$id = (int) wp_insert_post(
		array(
			'post_type'    => 'wcb_job',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => 'Shape the product experience for a growing hiring platform.',
			'post_author'  => get_current_user_id(),
		)
	);
	wp_set_object_terms( $id, $cats, 'wcb_category' );
	$wcb_jobs[] = $id;
	return $id;
};

$wcb_limit = 1;
$wcb_force = static function () use ( &$wcb_limit ): int {
	return $wcb_limit;
};
add_filter( 'wcb_job_category_limit', $wcb_force, PHP_INT_MAX );

// ── Limit 1 (Free) ─────────────────────────────────────────────────────────
$wcb_r = wcb_cat_send( 'POST', '/wcb/v1/jobs', array( 'title' => 'Senior Product Designer', 'description' => 'Lead design for our hiring tools.', 'remote' => true, 'categories' => array_slice( $wcb_cats, 0, 2 ) ) );
wcb_assert( 400 === $wcb_status( $wcb_r ) && 'wcb_too_many_categories' === ( $wcb_r->get_data()['code'] ?? '' ), 'limit 1: posting with two categories is refused (400 wcb_too_many_categories)' );
wcb_assert( str_contains( (string) ( $wcb_r->get_data()['message'] ?? '' ), '1 category' ), 'limit 1: the message names the limit' );
if ( 200 <= $wcb_status( $wcb_r ) && $wcb_status( $wcb_r ) < 300 ) {
	$wcb_jobs[] = (int) $wcb_r->get_data()['id'];
}

$wcb_r = wcb_cat_send( 'POST', '/wcb/v1/jobs', array( 'title' => 'Account Executive, Mid-Market', 'description' => 'Own a book of mid-market accounts.', 'remote' => true, 'categories' => array( $wcb_cats[3] ) ) );
wcb_assert( in_array( $wcb_status( $wcb_r ), array( 200, 201 ), true ), 'limit 1: posting with one category is accepted' );
if ( 200 <= $wcb_status( $wcb_r ) && $wcb_status( $wcb_r ) < 300 ) {
	$wcb_jobs[] = (int) $wcb_r->get_data()['id'];
}

$wcb_over = $wcb_job( 'Staff Frontend Engineer', $wcb_three );
$wcb_r    = wcb_cat_send( 'PATCH', '/wcb/v1/jobs/' . $wcb_over, array( 'title' => 'Staff Frontend Engineer (Design Systems)', 'categories' => $wcb_three ) );
wcb_assert( 200 === $wcb_status( $wcb_r ), 'limit 1: editing a job that already has 3 categories with the same 3 saves (200)' );
wcb_assert( wcb_cat_slugs( $wcb_over ) === $wcb_three, 'limit 1: all 3 categories are kept' );

$wcb_r = wcb_cat_send( 'PATCH', '/wcb/v1/jobs/' . $wcb_over, array( 'title' => 'Staff Frontend Engineer' ) );
wcb_assert( 200 === $wcb_status( $wcb_r ) && wcb_cat_slugs( $wcb_over ) === $wcb_three, 'limit 1: an edit that does not send categories keeps all 3' );

$wcb_r = wcb_cat_send( 'PATCH', '/wcb/v1/jobs/' . $wcb_over, array( 'categories' => array( $wcb_three[0], $wcb_cats[3] ) ) );
wcb_assert( 400 === $wcb_status( $wcb_r ), 'limit 1: a categories-only PATCH that adds a new category to an over-limit job is refused' );
wcb_assert( wcb_cat_slugs( $wcb_over ) === $wcb_three, 'limit 1: the refused PATCH changed nothing' );

$wcb_r = wcb_cat_send( 'PATCH', '/wcb/v1/jobs/' . $wcb_over, array( 'categories' => array_slice( $wcb_three, 0, 2 ) ) );
wcb_assert( 200 === $wcb_status( $wcb_r ) && 2 === count( wcb_cat_slugs( $wcb_over ) ), 'limit 1: dropping one of the 3 saves' );

$wcb_one = $wcb_job( 'Growth Marketing Manager', array( $wcb_cats[2] ) );
$wcb_r   = wcb_cat_send( 'PATCH', '/wcb/v1/jobs/' . $wcb_one, array( 'categories' => array( $wcb_cats[2], $wcb_cats[3] ) ) );
wcb_assert( 400 === $wcb_status( $wcb_r ), 'limit 1: a categories-only PATCH adding a second category is refused' );

// The edit form seeds every category the job has, not only the first.
$wcb_over3 = $wcb_job( 'Lead Product Designer', $wcb_three );
$_GET['edit'] = (string) $wcb_over3;
do_blocks( '<!-- wp:wp-career-board/job-form /-->' );
unset( $_GET['edit'] );
$wcb_seeded = (array) ( wp_interactivity_state( 'wcb-job-form' )['categorySlugs'] ?? array() );
sort( $wcb_seeded );
wcb_assert( $wcb_seeded === $wcb_three, 'edit form seeds all 3 categories of the job' );

// ── Limit 3 (Pro default) ──────────────────────────────────────────────────
$wcb_limit = 3;
$wcb_r     = wcb_cat_send( 'POST', '/wcb/v1/jobs', array( 'title' => 'Product Marketing Lead', 'description' => 'Launch and position new features.', 'remote' => true, 'categories' => $wcb_three ) );
wcb_assert( in_array( $wcb_status( $wcb_r ), array( 200, 201 ), true ), 'limit 3: posting with three categories is accepted' );
if ( 200 <= $wcb_status( $wcb_r ) && $wcb_status( $wcb_r ) < 300 ) {
	$wcb_jobs[] = (int) $wcb_r->get_data()['id'];
	wcb_assert( wcb_cat_slugs( (int) $wcb_r->get_data()['id'] ) === $wcb_three, 'limit 3: all three are saved' );
}

$wcb_r = wcb_cat_send( 'POST', '/wcb/v1/jobs', array( 'title' => 'Solutions Engineer', 'description' => 'Help customers succeed.', 'remote' => true, 'categories' => $wcb_cats ) );
wcb_assert( 400 === $wcb_status( $wcb_r ) && str_contains( (string) ( $wcb_r->get_data()['message'] ?? '' ), '3 categories' ), 'limit 3: posting with four categories is refused, naming the limit' );
if ( 200 <= $wcb_status( $wcb_r ) && $wcb_status( $wcb_r ) < 300 ) {
	$wcb_jobs[] = (int) $wcb_r->get_data()['id'];
}

$wcb_r = wcb_cat_send( 'PATCH', '/wcb/v1/jobs/' . $wcb_one, array( 'categories' => $wcb_cats ) );
wcb_assert( 400 === $wcb_status( $wcb_r ) && array( $wcb_cats[2] ) === wcb_cat_slugs( $wcb_one ), 'limit 3: a categories-only PATCH with four is refused and changes nothing' );

$wcb_r = wcb_cat_send( 'PATCH', '/wcb/v1/jobs/' . $wcb_one, array( 'categories' => $wcb_three ) );
wcb_assert( 200 === $wcb_status( $wcb_r ) && wcb_cat_slugs( $wcb_one ) === $wcb_three, 'limit 3: a categories-only PATCH with three saves' );

// Site admins are not limited.
$wcb_limit = 1;
wp_set_current_user( (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 1 ) );
$wcb_r = wcb_cat_send( 'PATCH', '/wcb/v1/jobs/' . $wcb_one, array( 'categories' => $wcb_cats ) );
wcb_assert( 200 === $wcb_status( $wcb_r ) && count( wcb_cat_slugs( $wcb_one ) ) === count( $wcb_cats ), 'a site admin may give a job any number of categories' );

remove_filter( 'wcb_job_category_limit', $wcb_force, PHP_INT_MAX );

// Teardown.
foreach ( $wcb_jobs as $wcb_id ) {
	wp_delete_post( (int) $wcb_id, true );
}
foreach ( $wcb_made_terms as $wcb_id ) {
	wp_delete_term( $wcb_id, 'wcb_category' );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $wcb_employer );

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All job category limit tests passed.' );
}
