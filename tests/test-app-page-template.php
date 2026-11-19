<?php
/**
 * Only app pages take the plugin's full-width page template (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-app-page-template.php
 *
 * Pins: a page whose only Career Board content is a display block (Recent
 * Jobs, Featured Jobs, Job Search, Job Search Hero, or Pro Featured
 * Candidates / Companies), as a block or a shortcode, keeps the theme's
 * template and gets no wcb-page body class. Dashboards, forms, registration,
 * job listings and the company / resume archives still get the plugin
 * template, also when a display block sits beside them; both filters still
 * steer the decision.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

$plugin = \WCB\Core\Plugin::instance();
$ids    = array();

// Does the page get the plugin template and the wcb-page body class?
$is_app = static function ( string $content ) use ( $plugin, &$ids ): bool {
	$id    = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Careers at Northwind Logistics',
			'post_content' => $content,
		)
	);
	$ids[] = $id;
	query_posts( array( 'page_id' => $id ) );
	the_post();
	$body = in_array( 'wcb-page', $plugin->add_page_class( array() ), true );
	$tpl  = str_ends_with( (string) $plugin->use_wcb_archive_template( '/theme/page.php' ), 'page-wcb-fullwidth.php' );
	wp_reset_query();
	return $body && $tpl;
};

WP_CLI::log( 'Display blocks keep the theme template' );
foreach ( array( 'recent-jobs', 'featured-jobs', 'job-search', 'job-search-hero' ) as $b ) {
	$ok( ! $is_app( '<!-- wp:wp-career-board/' . $b . ' /-->' ), "block $b alone" );
}
foreach ( array( 'wcb_recent_jobs', 'wcb_featured_jobs', 'wcb_job_search_hero' ) as $sc ) {
	$ok( ! $is_app( '<p>Open roles this week</p>[' . $sc . ']' ), "shortcode [$sc] alone" );
}
if ( defined( 'WCBP_VERSION' ) ) {
	foreach ( array( 'featured-candidates', 'featured-companies' ) as $b ) {
		$ok( ! $is_app( '<!-- wp:wcb/' . $b . ' /-->' ), "Pro block $b alone" );
	}
	$ok( ! $is_app( '[wcbp_featured_companies]' ), 'Pro shortcode [wcbp_featured_companies] alone' );
}

WP_CLI::log( 'App blocks take the plugin template' );
foreach ( array( 'job-listings', 'employer-dashboard', 'candidate-dashboard', 'job-form', 'employer-registration', 'company-archive' ) as $b ) {
	$ok( $is_app( '<!-- wp:wp-career-board/' . $b . ' /-->' ), "block $b" );
}
$ok( $is_app( '<!-- wp:wp-career-board/recent-jobs /--><!-- wp:wp-career-board/job-listings /-->' ), 'display block beside job listings' );
$ok( $is_app( '[wcb_job_listings]' ), 'shortcode [wcb_job_listings]' );
$ok( $is_app( '[wcb_registration]' ), 'back-compat shortcode [wcb_registration]' );
if ( defined( 'WCBP_VERSION' ) ) {
	$ok( $is_app( '<!-- wp:wcb/resume-archive /-->' ), 'Pro block resume-archive' );
	$ok( $is_app( '[wcbp_resume_archive]' ), 'Pro shortcode [wcbp_resume_archive]' );
}

WP_CLI::log( 'Filters still steer it' );
$add = static fn( array $b ): array => array_merge( $b, array( 'wp-career-board/recent-jobs' ) );
add_filter( 'wcb_fullwidth_block_names', $add );
$ok( $is_app( '<!-- wp:wp-career-board/recent-jobs /-->' ), 'wcb_fullwidth_block_names opts a display block in' );
remove_filter( 'wcb_fullwidth_block_names', $add );
add_filter( 'wcb_apply_page_class', '__return_false' );
$ok( ! $is_app( '<!-- wp:wp-career-board/job-listings /-->' ), 'wcb_apply_page_class opts an app page out' );
remove_filter( 'wcb_apply_page_class', '__return_false' );

foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}

WP_CLI::log( sprintf( '%d passed, %d failed', $pass, $fail ) );
if ( $fail > 0 ) {
	WP_CLI::error( 'App page template test failed.' );
}
WP_CLI::success( 'App page template test passed.' );
