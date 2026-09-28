<?php
/**
 * Google for Jobs markup tests (1.8.0, W16).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-job-schema.php
 *
 * Pins: JobPosting comes from the job's own data (company from the job, not
 * the author), with jobLocation and country, TELECOMMUTE plus applicant
 * country for remote jobs, the salary unit from the salary type, no
 * markup for an ended job; company pages carry Organization; each output
 * follows its setting; "Require a location" is enforced on create and on
 * updates that touch the location.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Modules\Seo\SeoModule;

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
 * @param int   $author Author.
 * @param array $meta   Meta.
 * @return int
 */
function wcb_js_job( int $author, array $meta ): int {
	return (int) wp_insert_post(
		array(
			'post_type'    => 'wcb_job',
			'post_status'  => 'publish',
			'post_title'   => 'JS Job',
			'post_content' => 'Build <strong>things</strong>.',
			'post_author'  => $author,
			'meta_input'   => $meta + array( '_wcb_deadline' => gmdate( 'Y-m-d', strtotime( '+20 days' ) ) ),
		)
	);
}

/**
 * Save settings for the test.
 *
 * @param array $overrides Settings.
 * @return void
 */
function wcb_js_settings( array $overrides ): void {
	update_option( 'wcb_settings', array_merge( (array) $GLOBALS['wcb_js_backup'], $overrides ) );
	\WCB\Admin\Settings::flush_cache();
}

$GLOBALS['wcb_js_backup'] = get_option( 'wcb_settings', array() );
// Posting is free here whatever the site's credits are set to: this test is about the markup and the location rule.
add_filter( 'wcb_board_credit_cost', '__return_zero', 99 );
add_filter( 'wcb_job_payment', '__return_true', 99 );
wcb_js_settings( array( 'default_country' => 'US', 'job_schema_enabled' => true ) );

$wcb_admin   = 1;
$wcb_company = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => 'JS Company', 'post_author' => $wcb_admin, 'meta_input' => array( '_wcb_website' => 'https://js-company.example', '_wcb_tagline' => 'We test', '_wcb_hq_location' => 'Berlin' ) ) );
$wcb_term    = wp_insert_term( 'JS Town, Bavaria ' . wp_generate_password( 4, false ), 'wcb_location' );
$wcb_term_id = (int) $wcb_term['term_id'];
update_term_meta( $wcb_term_id, SeoModule::COUNTRY_META, 'DE' );

WP_CLI::log( '--- on-site, hourly ---' );
$wcb_onsite = wcb_js_job( $wcb_admin, array( '_wcb_company_id' => $wcb_company, '_wcb_salary_min' => 25, '_wcb_salary_max' => 40, '_wcb_salary_type' => 'hourly', '_wcb_salary_currency' => 'EUR' ) );
wp_set_object_terms( $wcb_onsite, array( $wcb_term_id ), 'wcb_location' );
wp_set_object_terms( $wcb_onsite, array( 'full-time' ), 'wcb_job_type' );
$s = SeoModule::job_posting( get_post( $wcb_onsite ) );
wcb_assert( 'JobPosting' === ( $s['@type'] ?? '' ) && 'JS Company' === ( $s['hiringOrganization']['name'] ?? '' ), 'company comes from the job, not the admin author' );
wcb_assert( 'https://js-company.example' === ( $s['hiringOrganization']['sameAs'] ?? '' ), 'company website is sameAs' );
wcb_assert( 'DE' === ( $s['jobLocation'][0]['address']['addressCountry'] ?? '' ) && str_starts_with( (string) ( $s['jobLocation'][0]['address']['addressLocality'] ?? '' ), 'JS Town' ), 'jobLocation uses the location and its own country' );
wcb_assert( 'HOUR' === ( $s['baseSalary']['value']['unitText'] ?? '' ) && 25.0 === ( $s['baseSalary']['value']['minValue'] ?? 0 ) && 'EUR' === ( $s['baseSalary']['currency'] ?? '' ), 'hourly salary is published per hour' );
wcb_assert( array( 'FULL_TIME' ) === ( $s['employmentType'] ?? array() ) && true === ( $s['directApply'] ?? null ) && (string) $wcb_onsite === ( $s['identifier']['value'] ?? '' ), 'employmentType, directApply and identifier' );
wcb_assert( str_contains( (string) ( $s['validThrough'] ?? '' ), 'T23:59:59' ) && ! isset( $s['jobLocationType'] ), 'validThrough is the end of the closing day; not remote' );
wcb_assert( str_contains( (string) $s['description'], '<strong>things</strong>' ), 'description keeps its formatting' );

add_filter( 'wcb_job_posting_schema', '__return_empty_array' );
wcb_assert( array() === SeoModule::job_posting( get_post( $wcb_onsite ) ), 'wcb_job_posting_schema can leave a job out' );
remove_filter( 'wcb_job_posting_schema', '__return_empty_array' );

WP_CLI::log( '--- remote, no salary ---' );
$wcb_nocomp = (int) wp_insert_user( array( 'user_login' => 'js_nocomp_' . wp_generate_password( 4, false ), 'user_email' => 'js-nocomp-' . wp_generate_password( 4, false ) . '@example.test', 'user_pass' => wp_generate_password(), 'role' => 'wcb_employer' ) );
$wcb_remote = wcb_js_job( $wcb_nocomp, array( '_wcb_remote' => '1', '_wcb_apply_url' => 'https://apply.example/x' ) );
$s          = SeoModule::job_posting( get_post( $wcb_remote ) );
wcb_assert( 'TELECOMMUTE' === ( $s['jobLocationType'] ?? '' ) && 'US' === ( $s['applicantLocationRequirements']['name'] ?? '' ), 'remote job: TELECOMMUTE with the default country for applicants' );
wcb_assert( ! isset( $s['baseSalary'] ) && ! isset( $s['jobLocation'] ) && false === ( $s['directApply'] ?? null ), 'no salary, no address, external apply is not directApply' );
wcb_assert( get_bloginfo( 'name' ) === ( $s['hiringOrganization']['name'] ?? '' ), 'a job with no company falls back to the site name' );

WP_CLI::log( '--- custom location, default country ---' );
$wcb_custom = wcb_js_job( $wcb_admin, array( '_wcb_location_custom' => 'Springfield' ) );
$s          = SeoModule::job_posting( get_post( $wcb_custom ) );
wcb_assert( 'Springfield' === ( $s['jobLocation'][0]['address']['addressLocality'] ?? '' ) && 'US' === ( $s['jobLocation'][0]['address']['addressCountry'] ?? '' ), 'custom location gets the default country' );

WP_CLI::log( '--- ended job, company page, settings ---' );
update_post_meta( $wcb_custom, '_wcb_deadline', gmdate( 'Y-m-d', strtotime( '-2 days' ) ) );
wcb_assert( array() === SeoModule::job_posting( get_post( $wcb_custom ) ), 'an ended job has no JobPosting' );
$o = SeoModule::organization( $wcb_company );
wcb_assert( 'Organization' === ( $o['@type'] ?? '' ) && 'We test' === ( $o['description'] ?? '' ) && 'Berlin' === ( $o['address']['addressLocality'] ?? '' ), 'company page has Organization with tagline and HQ' );
$wcb_page = wp_remote_get( (string) get_permalink( $wcb_onsite ), array( 'timeout' => 20, 'sslverify' => false ) );
wcb_assert( str_contains( (string) wp_remote_retrieve_body( $wcb_page ), '"@type":"JobPosting"' ), 'job page prints the JobPosting (setting on)' );
wcb_js_settings( array( 'job_schema_enabled' => false ) );
$wcb_page = wp_remote_get( (string) get_permalink( $wcb_onsite ), array( 'timeout' => 20, 'sslverify' => false ) );
wcb_assert( ! str_contains( (string) wp_remote_retrieve_body( $wcb_page ), '"@type":"JobPosting"' ), 'turning the setting off removes it' );

WP_CLI::log( '--- require a location ---' );
wcb_js_settings( array( 'require_job_location' => true, 'auto_publish_jobs' => true ) );
wp_set_current_user( $wcb_admin );
$wcb_req = static function ( string $method, string $route, array $params ): WP_REST_Response {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $k => $v ) {
		$request->set_param( $k, $v );
	}
	return rest_do_request( $request );
};
$r = $wcb_req( 'POST', '/wcb/v1/jobs', array( 'title' => 'JS no location', 'description' => 'x' ) );
wcb_assert( 'wcb_location_required' === ( $r->get_data()['code'] ?? '' ) && 400 === $r->get_status(), 'create without a location or Remote is refused' );
$r = $wcb_req( 'POST', '/wcb/v1/jobs', array( 'title' => 'JS remote', 'description' => 'x', 'remote' => true ) );
$wcb_made = (int) ( $r->get_data()['id'] ?? 0 );
wcb_assert( $wcb_made > 0, 'a remote job needs no location' );
$r = $wcb_req( 'PUT', '/wcb/v1/jobs/' . $wcb_remote, array( 'title' => 'JS renamed' ) );
wcb_assert( 200 === $r->get_status(), 'editing an older job without touching its location still works' );
$r = $wcb_req( 'PUT', '/wcb/v1/jobs/' . $wcb_remote, array( 'remote' => false ) );
wcb_assert( 'wcb_location_required' === ( $r->get_data()['code'] ?? '' ), 'unticking Remote with no location is refused' );
$r = $wcb_req( 'POST', '/wcb/v1/jobs', array( 'title' => 'JS other', 'description' => 'x', 'locations' => array( 'other' ) ) );
wcb_assert( 'wcb_location_required' === ( $r->get_data()['code'] ?? '' ), 'Require a location: the Other placeholder with no typed location is refused' );
$r = $wcb_req( 'POST', '/wcb/v1/jobs', array( 'title' => 'JS bogus', 'description' => 'x', 'locations' => array( 'no-such-place-' . wp_generate_password( 6, false ) ) ) );
wcb_assert( 'wcb_location_required' === ( $r->get_data()['code'] ?? '' ), 'Require a location: a slug that does not exist is refused' );
$r = $wcb_req( 'POST', '/wcb/v1/jobs', array( 'title' => 'JS blank custom', 'description' => 'x', 'locations' => array( 'other' ), 'location_custom' => '   ' ) );
wcb_assert( 'wcb_location_required' === ( $r->get_data()['code'] ?? '' ), 'Require a location: Other with a blank typed location is refused' );
$wcb_slug = (string) get_term( $wcb_term_id )->slug;
$r        = $wcb_req( 'POST', '/wcb/v1/jobs', array( 'title' => 'JS real place', 'description' => 'x', 'locations' => array( $wcb_slug ) ) );
$wcb_made3 = (int) ( $r->get_data()['id'] ?? 0 );
wcb_assert( $wcb_made3 > 0, 'control: an existing location term satisfies the rule' );
wcb_js_settings( array( 'require_job_location' => false ) );
$r = $wcb_req( 'POST', '/wcb/v1/jobs', array( 'title' => 'JS optional', 'description' => 'x' ) );
$wcb_made2 = (int) ( $r->get_data()['id'] ?? 0 );
wcb_assert( $wcb_made2 > 0, 'with the setting off a location stays optional' );

WP_CLI::log( '--- a place Google can use ---' );
$wcb_reserved = static function ( string $slug, string $name ): int {
	$t = term_exists( $slug, 'wcb_location' ) ?: wp_insert_term( $name, 'wcb_location', array( 'slug' => $slug ) );
	return (int) ( is_array( $t ) ? $t['term_id'] : 0 );
};
$wcb_rem_term   = $wcb_reserved( 'remote', 'Remote' );
$wcb_oth_term   = $wcb_reserved( 'other', 'Other' );
$wcb_only_rem   = wcb_js_job( $wcb_admin, array() );
wp_set_object_terms( $wcb_only_rem, array( $wcb_rem_term ), 'wcb_location' );
$s = SeoModule::job_posting( get_post( $wcb_only_rem ) );
wcb_assert( 'TELECOMMUTE' === ( $s['jobLocationType'] ?? '' ) && ! isset( $s['jobLocation'] ), 'picking Remote in the location list is a remote job, not a place called Remote' );
$wcb_only_oth = wcb_js_job( $wcb_admin, array() );
wp_set_object_terms( $wcb_only_oth, array( $wcb_oth_term ), 'wcb_location' );
wcb_assert( array() === SeoModule::job_posting( get_post( $wcb_only_oth ) ) && ! SeoModule::has_location( get_post( $wcb_only_oth ) ), 'Other with no typed location has no place: no JobPosting' );
$wcb_berlin = wcb_js_job( $wcb_admin, array( '_wcb_location_custom' => 'Berlin' ) );
wp_set_object_terms( $wcb_berlin, array( $wcb_oth_term ), 'wcb_location' );
$wcb_bterm = wp_insert_term( 'Berlin', 'wcb_location' );
if ( ! is_wp_error( $wcb_bterm ) ) {
	wp_set_object_terms( $wcb_berlin, array( $wcb_oth_term, (int) $wcb_bterm['term_id'] ), 'wcb_location' );
}
$s = SeoModule::job_posting( get_post( $wcb_berlin ) );
wcb_assert( 1 === count( (array) ( $s['jobLocation'] ?? array() ) ) && 'Berlin' === ( $s['jobLocation'][0]['address']['addressLocality'] ?? '' ), 'a typed location that is also a term is published once, and Other never is' );
$wcb_none = wcb_js_job( $wcb_admin, array() );
wcb_assert( array() === SeoModule::job_posting( get_post( $wcb_none ) ) && ! SeoModule::has_location( get_post( $wcb_none ) ), 'a job with no location and not remote is left out of Google for Jobs' );
update_post_meta( $wcb_none, '_wcb_remote', '1' );
wcb_assert( 'JobPosting' === ( SeoModule::job_posting( get_post( $wcb_none ) )['@type'] ?? '' ) && SeoModule::has_location( get_post( $wcb_none ) ), 'control: the same job marked remote is published' );
$wcb_amp_co  = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => 'AT&amp;T Labs', 'post_author' => $wcb_admin ) );
$wcb_amp_job = wcb_js_job( $wcb_admin, array( '_wcb_company_id' => $wcb_amp_co, '_wcb_remote' => '1' ) );
$s           = SeoModule::job_posting( get_post( $wcb_amp_job ) );
wcb_assert( 'AT&T Labs' === ( $s['hiringOrganization']['name'] ?? '' ) && 'AT&T Labs' === ( $s['identifier']['name'] ?? '' ) && 'AT&T Labs' === ( SeoModule::organization( $wcb_amp_co )['name'] ?? '' ), 'company names are not published HTML-encoded (AT&T, not AT&amp;T)' );

WP_CLI::log( '--- taxonomy screens name their own terms ---' );
$wcb_expect = array(
	'wcb_category'   => array( 'Edit Job Category', 'Add New Job Category' ),
	'wcb_job_type'   => array( 'Edit Job Type', 'Add New Job Type' ),
	'wcb_tag'        => array( 'Edit Job Tag', 'Add New Job Tag' ),
	'wcb_location'   => array( 'Edit Location', 'Add New Location' ),
	'wcb_experience' => array( 'Edit Experience Level', 'Add New Experience Level' ),
);
foreach ( $wcb_expect as $wcb_tax => $wcb_want ) {
	$wcb_labels = get_taxonomy( $wcb_tax )->labels;
	wcb_assert( $wcb_want === array( $wcb_labels->edit_item, $wcb_labels->add_new_item ), $wcb_tax . ' says "' . $wcb_want[0] . '", not the generic Category / Tag (' . $wcb_labels->edit_item . ')' );
}
wcb_assert( 'Parent Location' === get_taxonomy( 'wcb_location' )->labels->parent_item && 'Add or remove job tags' === get_taxonomy( 'wcb_tag' )->labels->add_or_remove_items, 'hierarchical and flat taxonomies get their own extra labels' );

// Teardown.
update_option( 'wcb_settings', $GLOBALS['wcb_js_backup'] );
\WCB\Admin\Settings::flush_cache();
foreach ( array( $wcb_onsite, $wcb_remote, $wcb_custom, $wcb_made, $wcb_made2, $wcb_made3, $wcb_only_rem, $wcb_only_oth, $wcb_berlin, $wcb_none, $wcb_amp_job, $wcb_amp_co, $wcb_company ) as $wcb_id ) {
	if ( $wcb_id ) {
		wp_delete_post( $wcb_id, true );
	}
}
wp_delete_term( $wcb_term_id, 'wcb_location' );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $wcb_nocomp );

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All job schema tests passed.' );
}
