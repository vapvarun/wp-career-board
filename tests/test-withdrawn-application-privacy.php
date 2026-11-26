<?php
/**
 * A withdrawn application takes the candidate's data back from the employer (1.8.6).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-withdrawn-application-privacy.php
 *
 * The employer keeps the row (name, Withdrawn status) but loses the CV file,
 * email, cover letter, answers and resume link on every surface: the file
 * download, the single application, both applicant lists and the CSV export.
 * The candidate and admins keep everything; a live application is untouched.
 * On 1.8.5 the employer could still download the CV and read the email.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Core\PrivateFiles;

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

require_once ABSPATH . 'wp-admin/includes/user.php';
$made     = array();
$mk_user  = static function ( string $n, string $role ) use ( &$made ): int {
	$id     = (int) wp_insert_user(
		array(
			'user_login' => 'wd_' . $n . wp_generate_password( 4, false ),
			'user_pass'  => wp_generate_password(),
			'user_email' => 'wd' . $n . wp_generate_password( 4, false ) . '@example.test',
			'role'       => $role,
		)
	);
	$made[] = $id;
	return $id;
};
$employer = $mk_user( 'e', 'wcb_employer' );
$quitter  = $mk_user( 'w', 'wcb_candidate' ); // Withdraws.
$stayer   = $mk_user( 's', 'wcb_candidate' ); // Stays in the process.
$admin    = (int) ( get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
)[0] ?? 0 );

$job       = (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'publish',
		'post_title'  => 'Backend Engineer (PHP, Remote)',
		'post_author' => $employer,
	)
);
$file      = static function ( int $owner ): int {
	$id = (int) wp_insert_post(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'private',
			'post_mime_type' => 'application/pdf',
			'post_title'     => 'cv',
			'post_author'    => $owner,
		)
	);
	update_post_meta( $id, PrivateFiles::META, '1' );
	return $id;
};
$app       = static function ( int $candidate, int $cv, string $status ) use ( $job ): int {
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
	update_post_meta( $id, '_wcb_resume_attachment_id', $cv );
	update_post_meta( $id, '_wcb_cover_letter', 'I have shipped three WordPress plugins used by 40,000 sites.' );
	update_post_meta( $id, '_wcb_status', $status );
	return $id;
};
$cv_gone   = $file( $quitter );
$cv_live   = $file( $stayer );
$withdrawn = $app( $quitter, $cv_gone, 'withdrawn' );
$live      = $app( $stayer, $cv_live, 'reviewing' );

$get    = static function ( string $route ): array {
	$r = rest_do_request( new WP_REST_Request( 'GET', $route ) );
	return array( $r->get_status(), (array) $r->get_data() );
};
$row    = static function ( array $items, int $id ): array {
	foreach ( $items as $item ) {
		if ( (int) ( $item['id'] ?? 0 ) === $id ) {
			return (array) $item;
		}
	}
	return array();
};
$hidden = static fn ( array $item ): bool => '' === (string) ( $item['applicant_email'] ?? '' ) && '' === (string) ( $item['cover_letter'] ?? '' ) && empty( $item['resume_url'] );

// --- Employer ---------------------------------------------------------------
wp_set_current_user( $employer );
$ok( ! PrivateFiles::can_download( $cv_gone ), 'employer cannot download a withdrawn candidate\'s CV' );
$ok( PrivateFiles::can_download( $cv_live ), 'employer can still download a live applicant\'s CV' );

[ $status, $single ] = $get( "/wcb/v1/applications/{$withdrawn}" );
$ok( 200 === $status && 'withdrawn' === ( $single['status'] ?? '' ), 'employer still sees the withdrawn row and its status' );
$ok( $hidden( $single ), 'single application: email, cover letter and CV link are gone' );
$ok( array() === ( $single['custom_fields'] ?? array() ) && empty( $single['resume_permalink'] ), 'single application: answers and resume link are gone' );

[ , $live_single ] = $get( "/wcb/v1/applications/{$live}" );
$ok( ! $hidden( $live_single ), 'single application: a live applicant keeps email, letter and CV link' );

[ , $job_list ] = $get( "/wcb/v1/jobs/{$job}/applications" );
$job_list       = (array) ( $job_list['applications'] ?? array() );
$ok( $hidden( $row( $job_list, $withdrawn ) ) && ! $hidden( $row( $job_list, $live ) ), 'job applicant list hides only the withdrawn candidate\'s data' );

[ , $dash ] = $get( '/wcb/v1/employers/me/applications' );
$dash_items = isset( $dash['applications'] ) ? (array) $dash['applications'] : $dash;
$ok( $hidden( $row( $dash_items, $withdrawn ) ) && ! $hidden( $row( $dash_items, $live ) ), 'employer dashboard list hides only the withdrawn candidate\'s data' );

// --- Candidate and admin ----------------------------------------------------
wp_set_current_user( $quitter );
$ok( PrivateFiles::can_download( $cv_gone ), 'the candidate who withdrew still has their own CV' );

if ( $admin > 0 ) {
	wp_set_current_user( $admin );
	[ , $admin_view ] = $get( "/wcb/v1/applications/{$withdrawn}" );
	$ok( ! $hidden( $admin_view ), 'admin still sees everything on a withdrawn application' );
}

// --- CSV export (last: ApplicationsCsv::stream() ends the request) ---------
wp_set_current_user( $employer );
$mail = static fn ( int $uid ): string => (string) get_userdata( $uid )->user_email;
$csv  = '';
register_shutdown_function(
	static function () use ( &$csv, $ok, $mail, $quitter, $stayer, $cv_gone, $cv_live, $withdrawn, $live, $job, $made, &$pass, &$fail ): void {
		while ( ob_get_level() > 0 && ob_end_flush() ) {
			// Push the export through the collector below.
		}
		$ok( '' !== $csv, 'CSV export produced output' );
		$ok( ! str_contains( $csv, $mail( $quitter ) ) && ! str_contains( $csv, 'wcb_file=' . $cv_gone ), 'CSV export leaves out the withdrawn candidate\'s email and CV' );
		$ok( str_contains( $csv, $mail( $stayer ) ) && str_contains( $csv, 'wcb_file=' . $cv_live ), 'CSV export keeps the live applicant\'s email and CV' );

		wp_set_current_user( 0 );
		foreach ( array( $withdrawn, $live, $cv_gone, $cv_live, $job ) as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( $made as $uid ) {
			wp_delete_user( $uid );
		}
		WP_CLI::log( "{$pass} passed, {$fail} failed" );
		exit( $fail ? 1 : 0 );
	}
);
ob_start(
	static function ( string $chunk ) use ( &$csv ): string {
		$csv .= $chunk;
		return '';
	}
);
\WCB\Core\ApplicationsCsv::stream(
	array(
		'post_type'   => 'wcb_application',
		'post_status' => 'any',
		'post__in'    => array( $withdrawn, $live ),
	),
	'__return_true'
);
