<?php
/**
 * The employer's "View resume" link only exists for a public, published resume (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-public-resume-url.php
 *
 * Pins CandidatesModule::public_resume_url(): public + publish gives the link;
 * public + trash, public + draft, private and a non-resume post give ''.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Modules\Candidates\CandidatesModule;

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

$ok( method_exists( CandidatesModule::class, 'public_resume_url' ), 'CandidatesModule::public_resume_url() exists' );

$made = array();
$make = static function ( string $status, string $public ) use ( &$made ): int {
	$id = (int) wp_insert_post(
		array(
			'post_type'   => 'wcb_resume',
			'post_status' => $status,
			'post_title'  => 'URL probe ' . wp_generate_password( 5, false ),
		)
	);
	update_post_meta( $id, '_wcb_resume_public', $public );
	$made[] = $id;
	return $id;
};

$published = $make( 'publish', '1' );
$ok( '' !== CandidatesModule::public_resume_url( $published ), 'public + publish: a link' );
$ok( get_permalink( $published ) === CandidatesModule::public_resume_url( $published ), 'public + publish: the resume permalink' );

$draft = $make( 'draft', '1' );
$ok( '' === CandidatesModule::public_resume_url( $draft ), 'public + draft: no link' );

$trashed = $make( 'publish', '1' );
wp_trash_post( $trashed );
$ok( '' === CandidatesModule::public_resume_url( $trashed ), 'public + trashed: no link' );

$private = $make( 'publish', '0' );
$ok( '' === CandidatesModule::public_resume_url( $private ), 'private + publish: no link' );

$ok( '' === CandidatesModule::public_resume_url( 0 ), 'id 0: no link' );
$ok( '' === CandidatesModule::public_resume_url( 999999999 ), 'missing post: no link' );

$page = (int) wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'URL probe page',
	)
);
$made[] = $page;
update_post_meta( $page, '_wcb_resume_public', '1' );
$ok( '' === CandidatesModule::public_resume_url( $page ), 'a page flagged public: no link' );

foreach ( $made as $id ) {
	wp_delete_post( $id, true );
}

WP_CLI::log( "Result: {$pass} passed, {$fail} failed." );
if ( $fail > 0 ) {
	WP_CLI::halt( 1 );
}
