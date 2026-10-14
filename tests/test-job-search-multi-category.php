<?php
/**
 * Job search across several categories (1.8.1).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-job-search-multi-category.php
 *
 * Pins: a comma list of categories returns jobs in any of them; a parent
 * category includes the jobs of its children; `category` and the legacy
 * `wcb_category` merge instead of the first one winning; old single-value
 * links keep working; on a category archive the archive's term is the
 * starting choice and a choice in the URL replaces it.
 *
 * Self-contained: creates its own categories and jobs and deletes them.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
		WP_CLI::warning( "  FAIL: {$label}" );
	}
}

WP_CLI::log( '' );
WP_CLI::log( '  Job Search Multi-Category Tests' );

$wcb_term = static function ( string $name, int $parent = 0 ): int {
	$term = wp_insert_term( $name, 'wcb_category', array( 'parent' => $parent ) );
	return is_wp_error( $term ) ? 0 : (int) $term['term_id'];
};
$wcb_cloud    = $wcb_term( 'Cloud Infrastructure' );
$wcb_sre      = $wcb_term( 'Site Reliability Engineering', $wcb_cloud );
$wcb_platform = $wcb_term( 'Platform Engineering', $wcb_cloud );
$wcb_clinical = $wcb_term( 'Clinical Research' );
$wcb_legal    = $wcb_term( 'Legal and Compliance' );
wcb_assert( $wcb_cloud && $wcb_sre && $wcb_platform && $wcb_clinical && $wcb_legal, 'categories created (none of the names existed already)' );

$wcb_slug = static fn ( int $id ): string => (string) get_term_field( 'slug', $id, 'wcb_category' );
$wcb_job  = static function ( string $title, int $term ): int {
	$id = (int) wp_insert_post(
		array(
			'post_type'    => 'wcb_job',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => 'Full-time role with a hybrid schedule and a yearly learning budget.',
		)
	);
	wp_set_object_terms( $id, array( $term ), 'wcb_category' );
	return $id;
};
$wcb_job_sre      = $wcb_job( 'Senior Site Reliability Engineer, Northwind Health', $wcb_sre );
$wcb_job_platform = $wcb_job( 'Platform Engineer, Kubernetes, Brightline Logistics', $wcb_platform );
$wcb_job_clinical = $wcb_job( 'Clinical Research Coordinator, Meridian Labs', $wcb_clinical );
$wcb_job_legal    = $wcb_job( 'Compliance Analyst, Harbor Credit Union', $wcb_legal );
$wcb_all          = array( $wcb_job_sre, $wcb_job_platform, $wcb_job_clinical, $wcb_job_legal );

$wcb_found = static function ( array $raw ) use ( $wcb_all ): array {
	$query = JobSearch::run(
		JobSearch::query_args(
			$raw,
			array(
				'post__in'       => $wcb_all,
				'posts_per_page' => 10,
				'fields'         => 'ids',
			)
		)
	);
	$ids = array_map( 'intval', $query->posts );
	sort( $ids );
	return $ids;
};
$wcb_sorted = static function ( int ...$ids ): array {
	sort( $ids );
	return $ids;
};

wcb_assert(
	$wcb_sorted( $wcb_job_clinical, $wcb_job_legal ) === $wcb_found( array( 'category' => $wcb_slug( $wcb_clinical ) . ',' . $wcb_slug( $wcb_legal ) ) ),
	'category=a,b returns the jobs of either category'
);
wcb_assert(
	$wcb_sorted( $wcb_job_sre, $wcb_job_platform ) === $wcb_found( array( 'category' => $wcb_slug( $wcb_cloud ) ) ),
	'a parent category includes the jobs of its children'
);
wcb_assert(
	$wcb_sorted( $wcb_job_sre, $wcb_job_platform, $wcb_job_legal ) === $wcb_found( array( 'category' => $wcb_slug( $wcb_cloud ) . ',' . $wcb_slug( $wcb_legal ) ) ),
	'a parent plus another category returns both sets'
);
wcb_assert(
	$wcb_sorted( $wcb_job_sre ) === $wcb_found( array( 'category' => $wcb_slug( $wcb_sre ) ) ),
	'a child alone stays narrow (no sibling or parent jobs)'
);
wcb_assert(
	$wcb_sorted( $wcb_job_clinical ) === $wcb_found( array( 'wcb_category' => $wcb_slug( $wcb_clinical ) ) ),
	'legacy ?wcb_category=x still filters'
);
wcb_assert(
	$wcb_sorted( $wcb_job_clinical, $wcb_job_legal ) === $wcb_found(
		array(
			'category'     => $wcb_slug( $wcb_clinical ),
			'wcb_category' => $wcb_slug( $wcb_legal ),
		)
	),
	'category and wcb_category merge (both values count)'
);
wcb_assert(
	$wcb_slug( $wcb_legal ) === JobSearch::normalise(
		array(
			'category'     => $wcb_slug( $wcb_legal ),
			'wcb_category' => $wcb_slug( $wcb_legal ),
		)
	)['category'],
	'the same slug under both names is kept once'
);
wcb_assert(
	'full-time' === JobSearch::normalise(
		array(
			'search'     => 'nurse',
			'wcb_search' => 'driver',
			'type'       => 'full-time',
		)
	)['type'] && 'nurse' === JobSearch::normalise(
		array(
			'search'     => 'nurse',
			'wcb_search' => 'driver',
		)
	)['search'],
	'non-taxonomy params keep first-wins (the keyword is not merged)'
);

// Category archive: the archive's term is the starting choice.
$wcb_saved_query = $GLOBALS['wp_query'];
$wcb_saved_get   = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$_GET            = array();
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- simulate the archive request.
$GLOBALS['wp_query'] = new WP_Query( array( 'wcb_category' => $wcb_slug( $wcb_cloud ) ) );
wcb_assert( $wcb_slug( $wcb_cloud ) === ( JobSearch::from_url()['category'] ?? '' ), "on a category archive the archive's term is the starting choice" );
$_GET['category'] = $wcb_slug( $wcb_clinical ) . ',' . $wcb_slug( $wcb_legal );
wcb_assert( $_GET['category'] === ( JobSearch::from_url()['category'] ?? '' ), 'a choice in the URL replaces the archive term' );
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restore.
$GLOBALS['wp_query'] = $wcb_saved_query;
$_GET                = $wcb_saved_get;

// Teardown.
foreach ( $wcb_all as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}
foreach ( array( $wcb_sre, $wcb_platform, $wcb_cloud, $wcb_clinical, $wcb_legal ) as $wcb_id ) {
	wp_delete_term( $wcb_id, 'wcb_category' );
}

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All job search multi-category tests passed.' );
}
