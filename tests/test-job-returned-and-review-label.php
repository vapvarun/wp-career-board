<?php
/**
 * My Jobs says what Publish does, and that a returned job was returned (1.8.6).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-job-returned-and-review-label.php
 *
 * On a board that needs approval a draft's action reads "Submit for review",
 * not "Publish". A submitted job the site moved back to Draft reads "Returned
 * by the site" instead of "Not submitted yet", until it is submitted again.
 * The employer saving their own job as a draft is not a return. On 1.8.5 every
 * draft said "Publish" and "Not submitted yet".
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

require_once ABSPATH . 'wp-admin/includes/user.php';
$employer = (int) wp_insert_user(
	array(
		'user_login' => 'tr_e' . wp_generate_password( 4, false ),
		'user_pass'  => wp_generate_password(),
		'user_email' => 'tre' . wp_generate_password( 4, false ) . '@example.test',
		'role'       => 'wcb_employer',
	)
);
$admin    = (int) ( get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
)[0] ?? 0 );

$job      = static function ( string $title, string $status ) use ( $employer ): int {
	return (int) wp_insert_post(
		array(
			'post_type'   => 'wcb_job',
			'post_status' => $status,
			'post_title'  => $title,
			'post_author' => $employer,
		)
	);
};
$draft    = $job( 'Warehouse Shift Lead', 'draft' );
$returned = $job( 'Forklift Operator', 'pending' );
$own      = $job( 'Inventory Analyst', 'pending' );

$row = static function ( int $id ): array {
	$r = new WP_REST_Request( 'GET', '/wcb/v1/employers/me/jobs' );
	$r->set_param( 'per_page', 50 );
	foreach ( (array) ( rest_do_request( $r )->get_data()['jobs'] ?? array() ) as $item ) {
		if ( (int) $item['id'] === $id ) {
			return $item;
		}
	}
	return array();
};

$mode = 'pending';
add_filter(
	'wcb_job_default_status',
	static function () use ( &$mode ): string {
		return $mode; // Stands in for a board's moderation setting (Pro hooks the same filter).
	},
	99
);

wp_set_current_user( $employer );
$ok( 'Submit for review' === ( $row( $draft )['publishLabel'] ?? '' ), 'a draft on an approval board offers "Submit for review"' );
$mode = 'publish';
$ok( 'Publish' === ( $row( $draft )['publishLabel'] ?? '' ), 'a draft on an auto-publish board offers "Publish"' );
$mode = 'pending';

// The site sends a submitted job back to Draft (wp-admin status change).
wp_set_current_user( $admin );
wp_update_post(
	array(
		'ID'          => $returned,
		'post_status' => 'draft',
	)
);
// The employer moves their own pending job back to Draft.
wp_set_current_user( $employer );
wp_update_post(
	array(
		'ID'          => $own,
		'post_status' => 'draft',
	)
);

$ok( str_starts_with( (string) ( $row( $returned )['statusNote'] ?? '' ), 'Returned by the site' ), 'a job the site sent back says it was returned' );
$ok( 'Not submitted yet' === ( $row( $own )['statusNote'] ?? '' ), 'a job the employer saved as a draft still says "Not submitted yet"' );
$ok( 'Not submitted yet' === ( $row( $draft )['statusNote'] ?? '' ), 'a never-submitted draft says "Not submitted yet"' );
$single = rest_do_request( new WP_REST_Request( 'GET', "/wcb/v1/jobs/{$returned}" ) )->get_data();
$ok( true === ( $single['returned'] ?? null ), 'the job endpoint flags it as returned for the app' );

// Submitting it again clears the marker.
$r = new WP_REST_Request( 'PATCH', "/wcb/v1/jobs/{$returned}" );
$r->set_body_params( array( 'status' => 'publish' ) );
$status = rest_do_request( $r )->get_status();
$ok( 200 === $status && 'pending' === get_post_status( $returned ), 'resubmitting sends it to review' );
$ok( ! metadata_exists( 'post', $returned, \WCB\Modules\Moderation\ModerationModule::RETURNED ), 'and clears the returned marker' );

wp_set_current_user( 0 );
foreach ( array( $draft, $returned, $own ) as $id ) {
	wp_delete_post( $id, true );
}
wp_delete_user( $employer );

WP_CLI::log( "{$pass} passed, {$fail} failed" );
exit( $fail ? 1 : 0 );
