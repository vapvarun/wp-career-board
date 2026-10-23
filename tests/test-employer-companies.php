<?php
/**
 * Employers manage every company they run from the dashboard (1.8.1).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-employer-companies.php
 *
 * Card 10400521949: add a second company without changing the main one,
 * list, edit, choose the main company and remove one, all through REST.
 * Creates its own users and companies and deletes them.
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
function wcb_rest( string $method, string $route, array $params, int $user_id ): WP_REST_Response {
	wp_set_current_user( $user_id );
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $k => $v ) {
		$request->set_param( $k, $v );
	}
	return rest_do_request( $request );
}

rest_get_server();

WP_CLI::log( '' );
WP_CLI::log( '  Employer Companies Tests' );

$wcb_suffix  = wp_generate_password( 6, false );
$wcb_user    = static fn ( string $login ): int => (int) wp_insert_user( array( 'user_login' => $login . '-' . $wcb_suffix, 'user_pass' => wp_generate_password(), 'user_email' => $login . '-' . $wcb_suffix . '@example.test', 'role' => 'wcb_employer' ) );
$wcb_lead    = $wcb_user( 'ec-lead' );
$wcb_peer    = $wcb_user( 'ec-peer' );
$wcb_other   = $wcb_user( 'ec-other' );
$wcb_main_of = static fn ( int $u ): int => (int) get_user_meta( $u, '_wcb_company_id', true );
$wcb_created = array();

// 1. First company becomes main; a second one does not change it.
$wcb_first     = (int) ( wcb_rest( 'POST', '/wcb/v1/employers', array( 'name' => 'Fernwood Analytics' ), $wcb_lead )->get_data()['id'] ?? 0 );
$wcb_created[] = $wcb_first;
wcb_assert( $wcb_first > 0 && $wcb_first === $wcb_main_of( $wcb_lead ), 'the first company becomes the main company' );
$wcb_r         = wcb_rest( 'POST', '/wcb/v1/employers', array( 'name' => 'Harbor & Pine Studio' ), $wcb_lead );
$wcb_second    = (int) ( $wcb_r->get_data()['id'] ?? 0 );
$wcb_created[] = $wcb_second;
wcb_assert( $wcb_second > 0 && $wcb_first === $wcb_main_of( $wcb_lead ), 'adding a second company keeps the main company (was switched silently before)' );
wcb_assert( 'Harbor & Pine Studio' === get_post_field( 'post_title', $wcb_second ), "an employer's '&' is stored as '&'" );

// 2. The list: both, main first.
$wcb_list = (array) ( wcb_rest( 'GET', '/wcb/v1/employers/me/companies', array(), $wcb_lead )->get_data()['companies'] ?? array() );
wcb_assert( 2 === count( $wcb_list ) && $wcb_first === $wcb_list[0]['id'] && $wcb_list[0]['isMain'] && ! $wcb_list[1]['isMain'], 'GET /employers/me/companies lists both, main first' );
wcb_assert( $wcb_list[1]['canRemove'] && '' !== $wcb_list[1]['stats'] && 'HP' === $wcb_list[1]['initials'], 'each card carries counts, initials and whether it can be removed' );

// 3. Editing: a co-owner may, a stranger may not.
add_post_meta( $wcb_second, '_wcb_company_owner', (string) $wcb_peer );
$wcb_r = wcb_rest( 'PATCH', "/wcb/v1/employers/{$wcb_second}", array( 'tagline' => 'Brand and packaging for independent food makers' ), $wcb_peer );
wcb_assert( 200 === $wcb_r->get_status(), "a co-owner edits the company profile (got {$wcb_r->get_status()})" );
$wcb_r = wcb_rest( 'PATCH', "/wcb/v1/employers/{$wcb_second}", array( 'tagline' => 'x' ), $wcb_other );
wcb_assert( 403 === $wcb_r->get_status(), "a stranger can't (got {$wcb_r->get_status()})" );

// 4. Choosing the main company.
$wcb_r = wcb_rest( 'POST', "/wcb/v1/employers/{$wcb_second}/main", array(), $wcb_lead );
wcb_assert( 200 === $wcb_r->get_status() && $wcb_second === $wcb_main_of( $wcb_lead ) && $wcb_second === ( $wcb_r->get_data()['companies'][0]['id'] ?? 0 ), 'make main switches it and returns the list with it first' );
$wcb_r = wcb_rest( 'POST', "/wcb/v1/employers/{$wcb_first}/main", array(), $wcb_other );
wcb_assert( 403 === $wcb_r->get_status(), "a stranger can't make another employer's company their main (got {$wcb_r->get_status()})" );

// 5. Removing: only the creator; jobs close; main moves on.
$wcb_job       = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'Packaging Designer', 'post_author' => $wcb_lead, 'meta_input' => array( '_wcb_company_id' => $wcb_second ) ) );
$wcb_created[] = $wcb_job;
$wcb_r         = wcb_rest( 'DELETE', "/wcb/v1/employers/{$wcb_second}", array(), $wcb_peer );
wcb_assert( 403 === $wcb_r->get_status() && 'publish' === get_post_status( $wcb_second ), "a co-owner can't remove the creator's company (got {$wcb_r->get_status()})" );
$wcb_r = wcb_rest( 'DELETE', "/wcb/v1/employers/{$wcb_second}", array(), $wcb_lead );
wcb_assert( 200 === $wcb_r->get_status() && 'trash' === get_post_status( $wcb_second ), 'the creator removes it (to the trash)' );
wcb_assert( 'wcb_closed' === get_post_status( $wcb_job ), 'its live job closes' );
wcb_assert( $wcb_first === $wcb_main_of( $wcb_lead ) && 1 === count( (array) ( $wcb_r->get_data()['companies'] ?? array() ) ), 'removing the main company hands main to the one left' );

// 6. Owner bar (13 Oct): remove a company, untrash it in wp-admin (it comes
// back as a draft): not in the picker, and naming it on a job is a 400.
wp_set_current_user( 1 );
wp_untrash_post( $wcb_second );
wcb_assert( 'draft' === get_post_status( $wcb_second ), 'an untrashed company comes back as a draft (no restore feature)' );
wcb_assert( 'wcb_closed' === get_post_status( $wcb_job ), 'untrashing the company does not reopen its jobs' );
wcb_assert( ! isset( \WCB\Modules\Employers\EmployersModule::owned_companies( $wcb_lead, true )[ $wcb_second ] ), 'the job picker leaves the draft company out' );
$wcb_r = wcb_rest( 'PATCH', "/wcb/v1/jobs/{$wcb_job}", array( 'company_id' => $wcb_first ), $wcb_lead );
$wcb_r = wcb_rest( 'PATCH', "/wcb/v1/jobs/{$wcb_job}", array( 'company_id' => $wcb_second ), $wcb_lead );
wcb_assert( 400 === $wcb_r->get_status() && 'wcb_company_offline' === ( $wcb_r->get_data()['code'] ?? '' ), "PATCH company_id to the draft company is a 400 saying why (got {$wcb_r->get_status()})" );
$wcb_r = wcb_rest( 'POST', '/wcb/v1/jobs', array( 'title' => 'Brand Strategist', 'description' => 'Shape the voice of independent food brands from first sketch to shelf.', 'company_id' => $wcb_second ), 1 );
wcb_assert( 400 === $wcb_r->get_status(), "an admin can't name an unpublished company either (got {$wcb_r->get_status()})" );
$wcb_created[] = (int) ( $wcb_r->get_data()['id'] ?? 0 );
$wcb_r = wcb_rest( 'POST', "/wcb/v1/employers/{$wcb_second}/main", array(), $wcb_lead );
wcb_assert( 409 === $wcb_r->get_status(), "a draft company can't become main (got {$wcb_r->get_status()})" );
$wcb_r = wcb_rest( 'PATCH', "/wcb/v1/jobs/{$wcb_job}", array( 'company_id' => $wcb_first ), $wcb_lead );
wcb_assert( 200 === $wcb_r->get_status(), 'a published company still works as before' );

// Card 10401941306: a company handed to someone else drops the old link.
$wcb_owns         = static fn ( int $u, int $c ): bool => isset( \WCB\Modules\Employers\EmployersModule::owned_companies( $u )[ $c ] );
$wcb_admin_co     = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => 'Larkspur Freight', 'post_author' => 1 ) );
$wcb_created[]    = $wcb_admin_co;
update_user_meta( $wcb_other, '_wcb_company_id', $wcb_admin_co );
wp_update_post( array( 'ID' => $wcb_admin_co, 'post_title' => 'Larkspur Freight Co' ) );
wcb_assert( $wcb_owns( $wcb_other, $wcb_admin_co ), 'an employer linked to a company an admin created keeps it across a plain save' );
wp_update_post( array( 'ID' => $wcb_admin_co, 'post_author' => $wcb_peer ) );
wcb_assert( ! $wcb_owns( $wcb_other, $wcb_admin_co ) && 0 === $wcb_main_of( $wcb_other ), 'handing the company to another author drops the old link' );
wcb_assert( $wcb_owns( $wcb_peer, $wcb_admin_co ), 'the new author owns it' );
add_post_meta( $wcb_admin_co, '_wcb_company_owner', $wcb_other );
update_user_meta( $wcb_other, '_wcb_company_id', $wcb_admin_co );
delete_post_meta( $wcb_admin_co, '_wcb_company_owner', $wcb_other );
wcb_assert( ! $wcb_owns( $wcb_other, $wcb_admin_co ), 'a co-owner taken off the company loses it' );

// Teardown.
foreach ( array_filter( $wcb_created ) as $wcb_id ) {
	wp_delete_post( (int) $wcb_id, true );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( $wcb_lead, $wcb_peer, $wcb_other ) as $wcb_u ) {
	wp_delete_user( (int) $wcb_u );
}

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All employer companies tests passed.' );
}
