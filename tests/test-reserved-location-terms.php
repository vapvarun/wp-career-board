<?php
/**
 * Reserved Remote and Other location terms never print as a place (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-reserved-location-terms.php
 *
 * Pins: Locations::place_terms()/place_names(), the job page, the Featured and
 * Recent jobs blocks, the employer Jobs REST list and the RSS enrichment.
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

$user = (int) wp_insert_user( array( 'user_login' => 'rlt_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'wcb_employer' ) );
$job  = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'RLT Job', 'post_author' => $user ) );
update_post_meta( $job, '_wcb_remote', '1' );
update_post_meta( $job, '_wcb_featured', '1' );
foreach ( array( 'remote' => 'Remote', 'other' => 'Other', 'rlt-lisbon' => 'Lisbon' ) as $slug => $name ) {
	if ( ! term_exists( $slug, 'wcb_location' ) ) {
		wp_insert_term( $name, 'wcb_location', array( 'slug' => $slug ) );
	}
}
wp_set_object_terms( $job, array( 'remote', 'other', 'rlt-lisbon' ), 'wcb_location' );

$names = \WCB\Core\Locations::place_names( get_the_terms( $job, 'wcb_location' ) );
$ok( array( 'Lisbon' ) === array_values( $names ), 'place_names keeps only the real place' );
$ok( 1 === count( \WCB\Core\Locations::place_terms( get_the_terms( $job, 'wcb_location' ) ) ), 'place_terms keeps only the real place' );
$ok( array() === \WCB\Core\Locations::place_terms( new WP_Error( 'x' ) ), 'place_terms tolerates a WP_Error' );

$has_reserved = static fn( string $html ): bool => (bool) preg_match( '/>\s*(Other|Remote)\s*<\/(p|span|dd|a)>|Other,\s*Remote|Remote,\s*Other/u', $html );

$GLOBALS['post'] = get_post( $job );
setup_postdata( $GLOBALS['post'] );
foreach ( array( 'featured-jobs', 'recent-jobs' ) as $block ) {
	$html = render_block( array( 'blockName' => 'wp-career-board/' . $block, 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
	$ok( str_contains( $html, 'Lisbon' ) && ! str_contains( $html, 'Other' ), "$block prints the place and not Other" );
}
$html = render_block( array( 'blockName' => 'wp-career-board/job-single', 'attrs' => array( 'jobId' => $job ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
preg_match( '/<dt>\s*Location\s*<\/dt>\s*<dd>(.*?)<\/dd>/s', $html, $m );
$ok( 'Lisbon' === trim( wp_strip_all_tags( $m[1] ?? '' ) ), 'job page Location row is the place only' );
$ok( (bool) preg_match( '/<dt>\s*Work Mode\s*<\/dt>\s*<dd>\s*Remote/s', $html ), 'job page still shows Remote once as Work Mode' );
$ok( ! preg_match( '/wcb-badge--location[^>]*>[^<]*<[^>]*>[^<]*Other/s', $html ), 'job page has no Other location badge' );

wp_set_current_user( $user );
$res  = rest_do_request( new WP_REST_Request( 'GET', '/wcb/v1/employers/me/jobs' ) );
$rows = array_column( (array) ( $res->get_data()['jobs'] ?? array() ), null, 'id' );
$ok( 'Lisbon' === ( $rows[ $job ]['location'] ?? null ), 'employer jobs list location is the place only' );

$rss = \WCB\Modules\Seo\RssFeedEnrichment::class;
if ( method_exists( $rss, 'location_text' ) ) {
	$ok( ! str_contains( (string) $rss::location_text( $job ), 'Other' ), 'rss location has no Other' );
}

wp_delete_post( $job, true );
wp_delete_user( $user );

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Reserved location terms are never printed as places.' );
