<?php
/**
 * A listing block's query count must not grow with the number of rows (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-block-query-budget.php
 *
 * Renders each listing block with a cold object cache at a small and a large page size and
 * fails when the larger page costs more than a small allowance of extra queries. 1.8.4 shipped
 * 46 queries for 20 company cards (one per logo) and 87 for the Pro resume map (two per pin)
 * before this guard existed. Needs 12+ published rows of each type; fewer rows prove nothing,
 * so the check says so instead of passing.
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
$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

wp_set_current_user( 0 );
$count = static function ( string $block, array $attrs ) use ( $wpdb ): int {
	wp_cache_flush();
	wp_load_alloptions();
	$before = $wpdb->num_queries;
	render_block( array( 'blockName' => $block, 'attrs' => $attrs, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
	return $wpdb->num_queries - $before;
};

// Block, post type, attribute that sets the page size (null = fixed size, compared by row count).
$cases = array(
	array( 'wp-career-board/job-listings', 'wcb_job', 'perPage' ),
	array( 'wp-career-board/company-archive', 'wcb_company', 'perPage' ),
);
foreach ( $cases as list( $block, $type, $attr ) ) {
	$rows = (int) ( wp_count_posts( $type )->publish ?? 0 );
	if ( $rows < 12 ) {
		WP_CLI::warning( "{$block}: only {$rows} published {$type} rows; seed 12+ (bin/seed-demo-data.php) to prove the budget." );
		continue;
	}
	$small = $count( $block, array( $attr => 3 ) );
	$large = $count( $block, array( $attr => 12 ) );
	$ok( $large <= $small + 3, "{$block}: 12 rows cost {$large} queries, 3 rows cost {$small} (allowance +3)" );
}

// A job whose company was deleted must not cost a lookup per row: WordPress does not cache a missing post.
$gone_job = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'draft', 'post_title' => 'Budget probe' ) );
update_post_meta( $gone_job, '_wcb_company_id', 999999999 );
$gone_post = get_post( $gone_job );
wp_cache_flush();
$before = $wpdb->num_queries;
for ( $i = 0; $i < 10; $i++ ) {
	\WCB\Core\CompanyMetaShape::for_job( $gone_post );
}
$spent = $wpdb->num_queries - $before;
wp_delete_post( $gone_job, true );
$ok( $spent <= 2, "ten lookups of a deleted company cost {$spent} queries (budget 2: one for the post, meta once)" );

// Pro block, when Pro is active: pins grow with candidates, not with a page size.
if ( class_exists( '\WCB\Pro\Modules\Resume\ResumeModule' ) && post_type_exists( 'wcb_resume' ) ) {
	$pins = (int) ( new WP_Query( array( 'post_type' => 'wcb_resume', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 500, 'no_found_rows' => true ) ) )->post_count;
	if ( $pins >= 12 ) {
		$used = $count( 'wcb/resume-map', array() );
		$ok( $used <= 25, "wcb/resume-map: {$used} queries for {$pins} resumes (budget 25, no per-pin queries)" );
	} else {
		WP_CLI::warning( "wcb/resume-map: only {$pins} resumes; seed 12+ to prove the budget." );
	}
}

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Block query budgets hold.' );
