<?php
/**
 * A trashed application is admin-only for writes as well as reads (1.8.6).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-trashed-application-writes.php
 *
 * The job's employer may read and work a live application, but once it is in
 * the trash every write is refused: status, notes, rating, the candidate's
 * withdrawal and (with Pro) a pipeline stage move. No email goes out, and
 * ApplicationLifecycle::transition() refuses it for every other writer. On
 * 1.8.5 the status change returned 200 and emailed the candidate.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Modules\Applications\ApplicationLifecycle;

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

require_once ABSPATH . 'wp-admin/includes/user.php';
$made      = array();
$mk_user   = static function ( string $n, string $role ) use ( &$made ): int {
	$id     = (int) wp_insert_user(
		array(
			'user_login' => 'tw_' . $n . wp_generate_password( 4, false ),
			'user_pass'  => wp_generate_password(),
			'user_email' => 'tw' . $n . wp_generate_password( 4, false ) . '@example.test',
			'role'       => $role,
		)
	);
	$made[] = $id;
	return $id;
};
$employer  = $mk_user( 'e', 'wcb_employer' );
$candidate = $mk_user( 'c', 'wcb_candidate' );
$admin     = (int) ( get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
)[0] ?? 0 );

$job     = (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'publish',
		'post_title'  => 'Senior Product Designer',
		'post_author' => $employer,
	)
);
$app     = static function () use ( $job, $candidate ): int {
	$id = (int) wp_insert_post(
		array(
			'post_type'   => 'wcb_application',
			'post_status' => 'publish',
			'post_title'  => 'Application',
			'post_author' => $candidate,
		)
	);
	update_post_meta( $id, '_wcb_job_id', $job );
	update_post_meta( $id, '_wcb_candidate_id', $candidate );
	update_post_meta( $id, '_wcb_status', 'submitted' );
	return $id;
};
$live    = $app();
$trashed = $app();
wp_trash_post( $trashed );

$mails = 0;
add_filter(
	'pre_wp_mail',
	static function () use ( &$mails ): bool {
		++$mails;
		return true;
	}
);
$call = static function ( string $method, string $route, array $body = array() ): int {
	$r = new WP_REST_Request( $method, $route );
	if ( $body ) {
		$r->set_body_params( $body );
	}
	return rest_do_request( $r )->get_status();
};

wp_set_current_user( $employer );
$ok( 200 === $call( 'PATCH', "/wcb/v1/applications/{$live}/status", array( 'status' => 'reviewing' ) ), 'employer can still change a live application' );

$mails = 0;
$ok( 403 === $call( 'PATCH', "/wcb/v1/applications/{$trashed}/status", array( 'status' => 'shortlisted' ) ), 'employer cannot change the status of a trashed application' );
$ok( 'submitted' === get_post_meta( $trashed, '_wcb_status', true ) && 0 === $mails, 'its status is unchanged and no email was sent' );
$ok( 403 === $call( 'POST', "/wcb/v1/applications/{$trashed}/notes", array( 'text' => 'Strong portfolio' ) ), 'employer cannot add a note' );
$ok( 403 === $call( 'GET', "/wcb/v1/applications/{$trashed}/notes" ), 'employer cannot read its notes' );
$ok( 403 === $call( 'POST', "/wcb/v1/applications/{$trashed}/rating", array( 'rating' => 4 ) ), 'employer cannot rate it' );
$ok( 403 === $call( 'GET', "/wcb/v1/applications/{$trashed}" ), 'employer cannot read it' );

if ( rest_get_server()->get_routes()['/wcb/v1/applications/(?P<id>\d+)/stage'] ?? false ) {
	$ok( 403 === $call( 'PATCH', "/wcb/v1/applications/{$trashed}/stage", array( 'stage_id' => 1 ) ), 'Pro: employer cannot move it to a pipeline stage' );
	$ok( '' === (string) get_post_meta( $trashed, '_wcb_stage_id', true ), 'Pro: its stage is unchanged' );
}

wp_set_current_user( $candidate );
$ok( 403 === $call( 'DELETE', "/wcb/v1/applications/{$trashed}" ), 'candidate cannot withdraw a trashed application' );

// A fresh one: the requests above must not have moved it to a closed status first.
$untouched = $app();
wp_trash_post( $untouched );
$mails = 0;
$ok( false === ApplicationLifecycle::transition( $untouched, 'hired', 'pipeline_stage', $employer ) && 0 === $mails, 'transition() refuses a trashed application and sends nothing' );
wp_delete_post( $untouched, true );

if ( $admin > 0 ) {
	wp_set_current_user( $admin );
	$ok( 200 === $call( 'GET', "/wcb/v1/applications/{$trashed}" ), 'admin can still read it' );
}

wp_set_current_user( 0 );
foreach ( array( $live, $trashed, $job ) as $id ) {
	wp_delete_post( $id, true );
}
foreach ( $made as $uid ) {
	wp_delete_user( $uid );
}

WP_CLI::log( "{$pass} passed, {$fail} failed" );
exit( $fail ? 1 : 0 );
