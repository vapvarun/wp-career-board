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

// ── Remote is a work mode only (1.8.5). On a translated site the reserved term
// carries the translated name, so the wizard's "Remote" landed on remote-2.
$wiz  = wp_insert_term( 'Remote', 'wcb_location', array( 'slug' => 'remote-rlt-' . wp_rand() ) );
$wjob = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'Customer Happiness Lead', 'post_content' => 'Help coffee lovers by email and chat.', 'post_author' => $user ) );
update_post_meta( $wjob, '_wcb_remote', '1' );
wp_set_object_terms( $wjob, array( (int) $wiz['term_id'], 'rlt-lisbon' ), 'wcb_location' );

$ok( array( 'Lisbon' ) === \WCB\Core\Locations::place_names( get_the_terms( $wjob, 'wcb_location' ) ), 'a "Remote" term on any slug is no place' );
$data = rest_do_request( new WP_REST_Request( 'GET', "/wcb/v1/jobs/{$wjob}" ) )->get_data();
$ok( 'Lisbon' === ( $data['location'] ?? null ) && true === ( $data['remote'] ?? null ), 'REST job (cards after a filter, the app): location Lisbon, remote true' );

$cards = array();
$keep  = static function ( array $d ) use ( &$cards ): array {
	$cards[ (int) $d['id'] ] = $d;
	return $d;
};
add_filter( 'wcb_job_listing_data', $keep, 99 );
$_GET = array( 'wcb_search' => 'Customer Happiness Lead' );
do_blocks( '<!-- wp:wp-career-board/job-listings /-->' );
$_GET = array();
remove_filter( 'wcb_job_listing_data', $keep, 99 );
$ok( 'Lisbon' === ( $cards[ $wjob ]['location'] ?? null ), 'first-paint job card location is Lisbon, Remote stays the chip' );

$html = render_block( array( 'blockName' => 'wp-career-board/job-single', 'attrs' => array( 'jobId' => $wjob ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
preg_match( '/<dt>\s*Location\s*<\/dt>\s*<dd>(.*?)<\/dd>/s', $html, $m );
$ok( 'Lisbon' === trim( wp_strip_all_tags( $m[1] ?? '' ) ), 'job page Location row is Lisbon only' );

add_user_meta( $user, '_wcb_bookmark', $wjob );
$req   = new WP_REST_Request( 'GET', "/wcb/v1/candidates/{$user}/bookmarks" );
$saved = array_column( (array) ( rest_do_request( $req )->get_data()['bookmarks'] ?? rest_do_request( $req )->get_data() ), null, 'id' );
$ok( 'Lisbon' === ( $saved[ $wjob ]['location'] ?? null ), 'candidate saved jobs location is Lisbon' );

update_term_meta( (int) $wiz['term_id'], \WCB\Modules\Seo\SeoModule::COUNTRY_META, 'PT' );
list( $places, $is_remote ) = \WCB\Modules\Seo\SeoModule::job_places( get_post( $wjob ) );
$ok( $is_remote && ! str_contains( (string) wp_json_encode( $places ), 'Remote' ), 'JobPosting: remote (TELECOMMUTE), and Remote is no address' );

$found = get_posts( \WCB\Modules\Jobs\JobSearch::query_args( array( 'location' => 'remote' ), array( 'fields' => 'ids', 'posts_per_page' => -1, 'post__in' => array( $job ) ) ) );
$ok( array( $job ) === $found, 'the location filter Remote still finds a job located Remote' );

$ok( ! in_array( 'Remote', \WCB\Admin\SetupWizard::sample_terms()['wcb_location'], true ), 'the setup wizard seeds no "Remote" location term' );

wp_delete_post( $wjob, true );
wp_delete_term( (int) $wiz['term_id'], 'wcb_location' );
wp_delete_post( $job, true );
wp_delete_user( $user );

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Reserved location terms are never printed as places.' );
