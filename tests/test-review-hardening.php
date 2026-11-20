<?php
/**
 * Second-look review fixes (1.8.6): access, Featured by an admin, IP buckets.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-review-hardening.php
 *
 * Pins, each as a case that fails on 1.8.5:
 *  - a company co-owner can list the company's applications, a stranger cannot;
 *  - a trashed application is gone over REST for its employer and candidate, not for an admin;
 *  - GET /candidates/{id} follows wcb_can_view_candidate_profile (a refusal reads as not found);
 *  - an admin's Featured request sets the flag;
 *  - the app-password limiter names its counters with a keyed hash, never md5 of the IP;
 *  - an IPv6 visitor is one bucket per /64, an IPv4-mapped one is its IPv4 address.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Auth\AppCredentials;
use WCB\Modules\Jobs\JobPayment;

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

$call = static function ( string $route, int $user_id ): WP_REST_Response {
	wp_set_current_user( $user_id );
	return rest_do_request( new WP_REST_Request( 'GET', $route ) );
};

$suffix = wp_generate_password( 5, false, false );
$mk     = static fn ( string $tag, string $role ): int => (int) wp_insert_user(
	array(
		'user_login' => $tag . '_' . $suffix,
		'user_pass'  => wp_generate_password(),
		'user_email' => $tag . '_' . $suffix . '@example.test',
		'role'       => $role,
	)
);
$owner    = $mk( 'rh_owner', 'wcb_employer' );
$coowner  = $mk( 'rh_co', 'wcb_employer' );
$stranger = $mk( 'rh_str', 'wcb_employer' );
$cand     = $mk( 'rh_cand', 'wcb_candidate' );
$admin    = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );

$company = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => 'Review Probe Co', 'post_author' => $owner ) );
update_post_meta( $company, '_wcb_company_owner', (string) $coowner );
$job = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'Review probe job', 'post_author' => $owner ) );
update_post_meta( $job, '_wcb_company_id', $company );
$app = (int) wp_insert_post( array( 'post_type' => 'wcb_application', 'post_status' => 'publish', 'post_title' => 'Review probe application', 'post_author' => $cand ) );
update_post_meta( $app, '_wcb_candidate_id', $cand );
update_post_meta( $app, '_wcb_job_id', $job );

// --- 1. company applications: a co-owner is in, a stranger is out -------------------------------
$route = '/wcb/v1/employers/' . $company . '/applications';
$ok( 200 === $call( $route, $owner )->get_status(), 'applications of a company: its author is allowed' );
$ok( 200 === $call( $route, $coowner )->get_status(), 'applications of a company: a co-owner is allowed (was 403)' );
$ok( 403 === $call( $route, $stranger )->get_status(), 'applications of a company: another employer is refused' );

// --- 2. a trashed application is gone for its parties, not for an admin --------------------------
$one = '/wcb/v1/applications/' . $app;
$ok( 200 === $call( $one, $owner )->get_status() && 200 === $call( $one, $cand )->get_status(), 'application: employer and candidate can read it while it is live' );
wp_trash_post( $app );
$ok( $call( $one, $owner )->get_status() >= 400, 'trashed application: the employer no longer gets it (was 200)' );
$ok( $call( $one, $cand )->get_status() >= 400, 'trashed application: the candidate no longer gets it (was 200)' );
$ok( 200 === $call( $one, $admin )->get_status(), 'trashed application: an admin still can' );

// --- 3. candidate profile follows the viewer filter ---------------------------------------------
$prof = '/wcb/v1/candidates/' . $cand;
$ok( 200 === $call( $prof, 0 )->get_status(), 'candidate profile: open by default (Free alone changes nothing)' );
add_filter( 'wcb_can_view_candidate_profile', '__return_false' );
$ok( 404 === $call( $prof, 0 )->get_status(), 'candidate profile: a refusal answers not found (was 200)' );
$ok( 404 === $call( $prof, $stranger )->get_status(), 'candidate profile: a refusal applies to a signed-in stranger too' );
$ok( 200 === $call( $prof, $cand )->get_status(), 'candidate profile: the candidate always sees their own' );
$ok( 200 === $call( $prof, $admin )->get_status(), 'candidate profile: an admin always sees it' );
// An employer the candidate applied to still opens it; one they did not apply to does not.
$app2 = (int) wp_insert_post( array( 'post_type' => 'wcb_application', 'post_status' => 'publish', 'post_title' => 'Review probe application 2', 'post_author' => $cand ) );
update_post_meta( $app2, '_wcb_candidate_id', $cand );
update_post_meta( $app2, '_wcb_job_id', $job );
$ok( 200 === $call( $prof, $owner )->get_status(), 'candidate profile: the employer whose job received the application still opens it (was 404)' );
$ok( 200 === $call( $prof, $coowner )->get_status(), 'candidate profile: a co-owner of that company opens it too' );
$ok( 404 === $call( $prof, $stranger )->get_status(), 'candidate profile: an employer the candidate did not apply to is refused' );
wp_trash_post( $app2 );
$ok( 404 === $call( $prof, $owner )->get_status(), 'candidate profile: a withdrawn (trashed) application no longer grants access' );
wp_delete_post( $app2, true );
remove_filter( 'wcb_can_view_candidate_profile', '__return_false' );

// --- 4. an admin asking for Featured gets it -----------------------------------------------------
update_post_meta( $job, '_wcb_featured', '0' );
wp_set_current_user( $admin );
$paid = JobPayment::charge( $job, 'feature' );
$ok( true === $paid && '1' === (string) get_post_meta( $job, '_wcb_featured', true ), 'admin Feature: the flag is set (was a success that did nothing)' );
$ok( '' !== (string) get_post_meta( $job, '_wcb_featured_since', true ), 'admin Feature: the since-date is recorded so the expiry sweep applies' );

// --- 5. limiter counters are keyed, never md5 of the IP ----------------------------------------------
$ip = '203.0.113.' . wp_rand( 20, 250 );
AppCredentials::record_attempt( $ip );
$ok( (int) get_transient( 'wcb_apw_try_' . md5( wp_salt() . $ip ) ) >= 1, 'limiter: the attempt is counted under a keyed name' );
$ok( false === get_transient( 'wcb_apw_try_' . md5( $ip ) ), 'limiter: no counter is named by a bare md5 of the IP (reversible over IPv4)' );
delete_transient( 'wcb_apw_try_' . md5( wp_salt() . $ip ) );

// --- 6. IPv6 is one visitor per /64, IPv4-mapped is IPv4 ------------------------------------------------
$remote_was = $_SERVER['REMOTE_ADDR'] ?? null;
$ip_of      = static function ( string $addr ): string {
	$_SERVER['REMOTE_ADDR'] = $addr;
	return AppCredentials::client_ip();
};
$a = $ip_of( '2001:db8:1:2:aaaa:bbbb:cccc:dddd' );
$b = $ip_of( '2001:db8:1:2:1111:2222:3333:4444' );
$ok( $a === $b, 'IPv6: two addresses in one /64 are one visitor (was one bucket each)' );
$ok( $a !== $ip_of( '2001:db8:1:3:aaaa:bbbb:cccc:dddd' ), 'IPv6: a different /64 is a different visitor' );
$ok( '198.51.100.7' === $ip_of( '::ffff:198.51.100.7' ), 'IPv6: an IPv4-mapped address counts as its IPv4 address' );
$ok( '203.0.113.9' === $ip_of( '203.0.113.9' ), 'IPv4: unchanged' );
null === $remote_was ? ( function () {
	unset( $_SERVER['REMOTE_ADDR'] );
} )() : ( $_SERVER['REMOTE_ADDR'] = $remote_was );

// --- cleanup, by the ids this run created -----------------------------------------------------------------------
wp_set_current_user( 0 );
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( $app, $job, $company ) as $pid ) {
	wp_delete_post( $pid, true );
}
foreach ( array( $owner, $coowner, $stranger, $cand ) as $uid ) {
	wp_delete_user( $uid );
}

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Review hardening tests passed.' );
