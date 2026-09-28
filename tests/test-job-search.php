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

WP_CLI::log( '--- ranking, short words, markup, pay units, page size ---' );
$wcb_t2  = 'Yqw' . wp_generate_password( 5, false, false );
$wcb_ids = static fn ( array $params ): array => array_map( 'intval', JobSearch::run( JobSearch::query_args( $params, array( 'posts_per_page' => 50, 'fields' => 'ids', 'no_found_rows' => true ) ) )->posts );

// Featured leads a keyword search, ahead of a better text match.
$wcb_feat   = wcb_jsx_job( 'Plain listing', "{$wcb_t2} only in the description", array( '_wcb_featured' => '1' ) );
$wcb_better = wcb_jsx_job( "{$wcb_t2} in the title", 'Nothing else' );
wcb_assert( array( $wcb_feat, $wcb_better ) === $wcb_ids( array( 'search' => $wcb_t2 ) ), 'a featured job leads a keyword search (relevance)' );
delete_post_meta( $wcb_feat, '_wcb_featured' );
wcb_assert( array( $wcb_better, $wcb_feat ) === $wcb_ids( array( 'search' => $wcb_t2 ) ), 'control: unfeatured, the better text match leads' );

// Closing soonest: open jobs first by deadline, no deadline next, jobs already past it last.
$wcb_c_soon  = wcb_jsx_job( "{$wcb_t2} soon", 'x', array( '_wcb_deadline' => gmdate( 'Y-m-d', strtotime( '+5 days' ) ) ) );
$wcb_c_later = wcb_jsx_job( "{$wcb_t2} later", 'x', array( '_wcb_deadline' => gmdate( 'Y-m-d', strtotime( '+20 days' ) ) ) );
$wcb_c_none  = wcb_jsx_job( "{$wcb_t2} none", 'x', array( '_wcb_deadline' => '' ) );
$wcb_c_past  = wcb_jsx_job( "{$wcb_t2} past", 'x', array( '_wcb_deadline' => gmdate( 'Y-m-d', strtotime( '-60 days' ) ) ) );
wcb_assert( array( $wcb_c_soon, $wcb_c_later, $wcb_c_none, $wcb_c_past ) === array_values( array_intersect( $wcb_ids( array( 'search' => $wcb_t2, 'sort' => 'closing' ) ), array( $wcb_c_soon, $wcb_c_later, $wcb_c_none, $wcb_c_past ) ) ), 'Closing soonest: open jobs by deadline, then no deadline, jobs already past it last' );

// A keyword with no word of two letters is nothing to search for, not "everything".
wcb_assert( array() === $wcb_ids( array( 'search' => 'Q' ) ) && array() === $wcb_ids( array( 'search' => 'C R' ) ), 'a one-letter keyword returns nothing instead of every job' );
wcb_assert( array() !== $wcb_ids( array( 'search' => $wcb_t2 ) ), 'control: a real keyword still finds jobs' );

// Editor block markup is not job text.
$wcb_marked = wcb_jsx_job( "{$wcb_t2} Marked", "<!-- wp:paragraph -->\n<p>We run kubernetes clusters.</p>\n<!-- /wp:paragraph -->" );
wcb_assert( array() === $wcb_ids( array( 'search' => "{$wcb_t2} paragraph" ) ), 'a word that is only block markup (paragraph) finds nothing' );
wcb_assert( array( $wcb_marked ) === $wcb_ids( array( 'search' => "{$wcb_t2} kubernetes" ) ), 'control: a word in the text is found' );
delete_post_meta( $wcb_marked, JobSearch::TEXT_META );
wcb_assert( array( $wcb_marked ) === $wcb_ids( array( 'search' => "{$wcb_t2} kubernetes" ) ), 'a job not indexed yet is still found (falls back to its content)' );
JobSearch::index_batch();
wcb_assert( '' !== (string) get_post_meta( $wcb_marked, JobSearch::TEXT_META, true ) && array() === $wcb_ids( array( 'search' => "{$wcb_t2} paragraph" ) ), 'the background backfill indexes it, and the markup word stops matching' );
wcb_assert( ! JobSearch::text_matches( $wcb_marked, 'paragraph' ) && JobSearch::text_matches( $wcb_marked, 'kubernetes' ), 'alerts read the same plain text' );

// Highest salary compares pay per year, not raw numbers across units.
$wcb_yearly = wcb_jsx_job( "{$wcb_t2} Yearly", 'x', array( '_wcb_salary_max' => 50000, '_wcb_salary_type' => 'yearly' ) );
$wcb_hourly = wcb_jsx_job( "{$wcb_t2} Hourly", 'x', array( '_wcb_salary_max' => 200, '_wcb_salary_type' => 'hourly' ) );
wcb_assert( array( $wcb_hourly, $wcb_yearly ) === array_values( array_intersect( $wcb_ids( array( 'search' => $wcb_t2, 'sort' => 'salary' ) ), array( $wcb_hourly, $wcb_yearly ) ) ), 'Highest salary: $200 an hour ranks above $50k a year' );

// The REST list uses the owner's Jobs per page when the client sends none.
$wcb_settings = get_option( 'wcb_settings', array() );
update_option( 'wcb_settings', array_merge( (array) $wcb_settings, array( 'jobs_per_page' => 7 ) ) );
\WCB\Admin\Settings::flush_cache();
wp_set_current_user( 0 );
$wcb_page = rest_do_request( new WP_REST_Request( 'GET', '/wcb/v1/jobs' ) )->get_data();
update_option( 'wcb_settings', $wcb_settings );
\WCB\Admin\Settings::flush_cache();
wcb_assert( 7 === count( (array) ( $wcb_page['jobs'] ?? array() ) ), 'REST returns the owner\'s Jobs per page (7), not a fixed 20 (' . count( (array) ( $wcb_page['jobs'] ?? array() ) ) . ')' );

foreach ( array( $wcb_feat, $wcb_better, $wcb_c_soon, $wcb_c_later, $wcb_c_none, $wcb_c_past, $wcb_marked, $wcb_yearly, $wcb_hourly ) as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}

WP_CLI::log( '--- company logo on the job card ---' );
$wcb_png     = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' );
$wcb_up      = wp_upload_bits( 'wcb-logo-' . $wcb_t2 . '.png', null, $wcb_png );
$wcb_logo_co = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => "{$wcb_t2} Logo Co", 'post_author' => 1 ) );
$wcb_logo_at = (int) wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'logo', 'post_status' => 'inherit' ), $wcb_up['file'], $wcb_logo_co );
set_post_thumbnail( $wcb_logo_co, $wcb_logo_at );
$wcb_logo_job   = wcb_jsx_job( "{$wcb_t2} Has logo", 'x', array( '_wcb_company_id' => $wcb_logo_co, '_wcb_company_name' => "{$wcb_t2} Logo Co" ) );
$wcb_nologo_job = wcb_jsx_job( "{$wcb_t2} No logo", 'x', array( '_wcb_company_name' => "{$wcb_t2} Plain Co" ) );
wp_set_current_user( 0 );
$wcb_payload = static function ( int $job_id ): array {
	$request = new WP_REST_Request( 'GET', '/wcb/v1/jobs' );
	$request->set_param( 'search', get_the_title( $job_id ) );
	foreach ( (array) ( rest_do_request( $request )->get_data()['jobs'] ?? array() ) as $row ) {
		if ( (int) $row['id'] === $job_id ) {
			return $row;
		}
	}
	return array();
};
$wcb_row = $wcb_payload( $wcb_logo_job );
wcb_assert( '' !== ( $wcb_row['company_logo'] ?? '' ) && str_contains( (string) $wcb_row['company_logo'], 'wcb-logo-' . $wcb_t2 ), 'the job payload carries the company logo URL' );
wcb_assert( '' === ( $wcb_payload( $wcb_nologo_job )['company_logo'] ?? 'missing' ), 'control: a job with no company logo has an empty company_logo (the card falls back to initials)' );
$_GET     = array( 'wcb_search' => "{$wcb_t2} Has logo" );
$wcb_html = do_blocks( '<!-- wp:wp-career-board/job-listings /-->' );
$_GET     = array();
wcb_assert( str_contains( $wcb_html, 'wcb-logo-' . $wcb_t2 ) && str_contains( $wcb_html, 'wcb-card-avatar__img' ), 'the first-paint card has the logo in its data and an image element' );
foreach ( array( $wcb_logo_job, $wcb_nologo_job, $wcb_logo_co ) as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}
wp_delete_attachment( $wcb_logo_at, true );

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
