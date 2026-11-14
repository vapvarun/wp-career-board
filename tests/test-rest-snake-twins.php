<?php
/**
 * REST rows carry a snake_case twin for every camelCase key (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-rest-snake-twins.php
 *
 * Pins: the helper, the employer jobs list, the candidate applications list and
 * (when Pro is active) the resumes list. The camelCase keys must stay.
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

$row = \WCB\Api\RestController::with_snake_keys( array( 'jobTitle' => 'A', 'canWithdraw' => true, 'status_label' => 'keep', 'statusLabel' => 'new', 'id' => 1 ) );
$ok( 'A' === $row['job_title'] && true === $row['can_withdraw'], 'helper adds snake twins' );
$ok( 'A' === $row['jobTitle'], 'helper keeps the camelCase key' );
$ok( 'keep' === $row['status_label'], 'helper never overwrites an existing snake key' );

$check = static function ( array $item, array $pairs ) use ( $ok ): void {
	foreach ( $pairs as $camel => $snake ) {
		$ok( array_key_exists( $camel, $item ) && array_key_exists( $snake, $item ) && $item[ $camel ] === $item[ $snake ], "$camel and $snake agree" );
	}
};

$jobs = get_posts( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'author__not_in' => array( 0 ), 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true ) );
if ( $jobs ) {
	$author = (int) get_post_field( 'post_author', $jobs[0] );
	wp_set_current_user( $author );
	$res  = rest_do_request( new WP_REST_Request( 'GET', '/wcb/v1/employers/me/jobs' ) );
	$data = $res->get_data();
	$item = $data['jobs'][0] ?? ( $data[0] ?? array() );
	$ok( 200 === $res->get_status() && ! empty( $item ), 'employer jobs list returns rows' );
	if ( $item ) {
		$check( $item, array( 'statusLabel' => 'status_label', 'closedNote' => 'closed_note', 'editUrl' => 'edit_url', 'pipelineUrl' => 'pipeline_url', 'appCount' => 'app_count', 'appLabel' => 'app_label' ) );
	}
}

$apps = get_posts( array( 'post_type' => 'wcb_application', 'post_status' => 'any', 'author__not_in' => array( 0 ), 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true ) );
if ( $apps ) {
	$cand = (int) get_post_field( 'post_author', $apps[0] );
	wp_set_current_user( $cand );
	$res  = rest_do_request( new WP_REST_Request( 'GET', '/wcb/v1/candidates/' . $cand . '/applications' ) );
	$data = $res->get_data();
	$item = $data['applications'][0] ?? ( $data[0] ?? array() );
	$ok( ! empty( $item ), 'candidate applications list returns rows' );
	if ( $item ) {
		$check( $item, array( 'jobTitle' => 'job_title', 'jobPermalink' => 'job_permalink', 'jobRemoved' => 'job_removed', 'canWithdraw' => 'can_withdraw', 'statusLabel' => 'status_label' ) );
	}
}

if ( class_exists( '\WCB\Pro\Modules\Resume\ResumeModule' ) ) {
	wp_set_current_user( 1 );
	$res  = rest_do_request( new WP_REST_Request( 'GET', '/wcb/v1/resumes' ) );
	$data = $res->get_data();
	$item = $data['resumes'][0] ?? ( $data[0] ?? array() );
	$ok( ! empty( $item ), 'resumes list returns rows' );
	if ( $item ) {
		$check( $item, array( 'candidateId' => 'candidate_id', 'candidateName' => 'candidate_name', 'jobTitle' => 'job_title', 'yearsExp' => 'years_exp', 'experienceBand' => 'experience_band' ) );
	}
}


// The single application, as its employer, has the fields the list rows have.
$apps = get_posts( array( 'post_type' => 'wcb_application', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true, 'meta_key' => '_wcb_job_id', 'meta_compare' => 'EXISTS' ) ); // phpcs:ignore
if ( $apps ) {
	$job = (int) get_post_meta( $apps[0], '_wcb_job_id', true );
	wp_set_current_user( (int) get_post_field( 'post_author', $job ) ?: 1 );
	$one = rest_do_request( new WP_REST_Request( 'GET', '/wcb/v1/applications/' . $apps[0] ) )->get_data();
	foreach ( array( 'applicant_name', 'applicant_email', 'job_title', 'rating', 'submitted_at_label', 'ai_score', 'status_history', 'resume_url' ) as $k ) {
		$ok( array_key_exists( $k, $one ), "single application has {$k}" );
	}
}

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'All snake-twin tests passed.' );
