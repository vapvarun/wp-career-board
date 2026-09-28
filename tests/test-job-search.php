<?php
/**
 * Job search tests (1.8.0, W17).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-job-search.php
 *
 * Pins: a keyword matches title, description or company, every word must
 * match, title matches rank first; two values of one filter return the
 * union; salary and closing sorts; URL filters reach the listing's first
 * paint; alerts use the same keyword rule; Pro radius narrows in the query
 * (right totals) and never leaks into the plain listing's cache.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Modules\Jobs\JobSearch;

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
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

/**
 * Create a published job.
 *
 * @param string $title   Title.
 * @param string $content Content.
 * @param array  $meta    Meta.
 * @param array  $types   Job type slugs.
 * @return int
 */
function wcb_jsx_job( string $title, string $content, array $meta = array(), array $types = array() ): int {
	$id = (int) wp_insert_post(
		array(
			'post_type'    => 'wcb_job',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $content,
			'post_author'  => 1,
			'meta_input'   => $meta + array( '_wcb_deadline' => gmdate( 'Y-m-d', strtotime( '+30 days' ) ) ),
		)
	);
	if ( $types ) {
		wp_set_object_terms( $id, $types, 'wcb_job_type' );
	}
	return $id;
}

/**
 * GET /wcb/v1/jobs IDs for params.
 *
 * @param array $params Params.
 * @return array{ids: int[], total: int}
 */
function wcb_jsx_rest( array $params ): array {
	wp_set_current_user( 0 );
	$request = new WP_REST_Request( 'GET', '/wcb/v1/jobs' );
	foreach ( $params + array( 'per_page' => 100 ) as $k => $v ) {
		$request->set_param( $k, $v );
	}
	$data = rest_do_request( $request )->get_data();
	return array(
		'ids'   => array_map( 'intval', wp_list_pluck( (array) ( $data['jobs'] ?? array() ), 'id' ) ),
		'total' => (int) ( $data['total'] ?? 0 ),
	);
}

$wcb_tag   = 'Zqx' . wp_generate_password( 5, false, false );
$wcb_title = wcb_jsx_job( "{$wcb_tag} Senior Designer", 'Figma and research.', array( '_wcb_salary_max' => 90000, '_wcb_deadline' => gmdate( 'Y-m-d', strtotime( '+40 days' ) ) ), array( 'full-time' ) );
$wcb_body  = wcb_jsx_job( "{$wcb_tag} Platform Engineer", 'Senior role running Kubernetes clusters.', array( '_wcb_salary_max' => 150000, '_wcb_deadline' => gmdate( 'Y-m-d', strtotime( '+5 days' ) ) ), array( 'part-time' ) );
$wcb_comp  = wcb_jsx_job( "{$wcb_tag} Analyst", 'Numbers.', array( '_wcb_company_name' => "{$wcb_tag}Corp Designer Studio" ), array( 'contract' ) );

WP_CLI::log( '--- keyword ---' );
$r = wcb_jsx_rest( array( 'search' => "{$wcb_tag} Kubernetes" ) );
wcb_assert( array( $wcb_body ) === $r['ids'], 'a word in the description is found' );
$r = wcb_jsx_rest( array( 'search' => "{$wcb_tag} Senior Designer" ) );
wcb_assert( array( $wcb_title ) === $r['ids'], 'every word must match (Senior Designer is not every Senior job)' );
$r = wcb_jsx_rest( array( 'search' => "{$wcb_tag} Senior" ) );
wcb_assert( array( $wcb_title, $wcb_body ) === $r['ids'], 'title matches rank above description matches' );
$r = wcb_jsx_rest( array( 'search' => "{$wcb_tag}Corp" ) );
wcb_assert( array( $wcb_comp ) === $r['ids'], 'the company name is searched' );

WP_CLI::log( '--- filters and sort ---' );
$r = wcb_jsx_rest( array( 'search' => $wcb_tag, 'type' => 'full-time,part-time' ) );
wcb_assert( 2 === $r['total'] && ! in_array( $wcb_comp, $r['ids'], true ), 'two values of one filter return either (union)' );
$r = wcb_jsx_rest( array( 'search' => $wcb_tag, 'sort' => 'salary' ) );
wcb_assert( array( $wcb_body, $wcb_title, $wcb_comp ) === $r['ids'], 'salary sort: highest first, jobs without a salary last' );
$r = wcb_jsx_rest( array( 'search' => $wcb_tag, 'sort' => 'closing' ) );
wcb_assert( $wcb_body === ( $r['ids'][0] ?? 0 ), 'closing sort: soonest deadline first' );
$r = wcb_jsx_rest( array( 'search' => $wcb_tag, 'per_page' => 1 ) );
wcb_assert( 3 === $r['total'] && 1 === count( $r['ids'] ), 'total counts every match, not the page' );
wcb_assert( 'oldest' === JobSearch::sort( JobSearch::normalise( array( 'orderby' => 'date', 'order' => 'ASC' ) ) ) && 'relevance' === JobSearch::sort( array( 'search' => 'x', 'order' => 'DESC' ) ), 'pre-1.8.0 orderby/order still work and keep best-match for keywords' );

$wcb_only = static function ( array $args ) use ( $wcb_comp ): array {
	$args['post__in'] = array( $wcb_comp );
	return $args;
};
add_filter( 'wcb_job_search_args', $wcb_only );
$r = wcb_jsx_rest( array( 'search' => $wcb_tag ) );
remove_filter( 'wcb_job_search_args', $wcb_only );
wcb_assert( array( $wcb_comp ) === $r['ids'], 'wcb_job_search_args changes the search (and its cache key)' );

WP_CLI::log( '--- first paint from the URL ---' );
$_GET = array(
	'wcb_search'   => $wcb_tag,
	'wcb_job_type' => 'part-time',
);
$wcb_html = do_blocks( '<!-- wp:wp-career-board/job-listings /-->' );
$_GET     = array();
wcb_assert( str_contains( $wcb_html, "{$wcb_tag} Platform Engineer" ) && ! str_contains( $wcb_html, "{$wcb_tag} Senior Designer" ) && str_contains( $wcb_html, '1 job found' ), 'hero keyword + type filter apply before any JavaScript' );

$wcb_fired = 0;
$wcb_hook  = static function ( int $job_id ) use ( &$wcb_fired, $wcb_body ): void {
	$wcb_fired = $job_id === $wcb_body ? 1 : 0;
	echo '<p class="qa-after-desc">extra</p>';
};
add_action( 'wcb_job_single_after_description', $wcb_hook );
$wcb_html = do_blocks( '<!-- wp:wp-career-board/job-single {"jobId":' . $wcb_body . '} /-->' );
remove_action( 'wcb_job_single_after_description', $wcb_hook );
wcb_assert( 1 === $wcb_fired && str_contains( $wcb_html, 'qa-after-desc' ), 'the job page offers wcb_job_single_after_description (custom field Details)' );

WP_CLI::log( '--- alerts ---' );
wcb_assert( JobSearch::text_matches( $wcb_body, 'kubernetes senior' ) && ! JobSearch::text_matches( $wcb_body, 'kubernetes designer' ), 'alerts match with the same every-word rule' );

if ( has_filter( 'wcb_job_search_args' ) ) {
	WP_CLI::log( '--- Pro radius ---' );
	update_post_meta( $wcb_title, '_wcb_lat', '51.5072' );
	update_post_meta( $wcb_title, '_wcb_lng', '-0.1276' );
	update_post_meta( $wcb_body, '_wcb_lat', '40.7128' );
	update_post_meta( $wcb_body, '_wcb_lng', '-74.0060' );
	$r = wcb_jsx_rest(
		array(
			'search' => $wcb_tag,
			'lat'    => 51.5,
			'lng'    => -0.12,
			'radius' => 25,
		)
	);
	wcb_assert( array( $wcb_title ) === $r['ids'] && 1 === $r['total'], 'radius keeps only the nearby job, with the right total' );
	$r = wcb_jsx_rest( array( 'search' => $wcb_tag ) );
	wcb_assert( 3 === $r['total'], 'the plain listing is not affected by a radius search' );
}

// Teardown.
foreach ( array( $wcb_title, $wcb_body, $wcb_comp ) as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All job search tests passed.' );
}
