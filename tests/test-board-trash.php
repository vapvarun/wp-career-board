<?php
/**
 * Trashed boards take no new jobs (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-board-trash.php
 *
 * Pins: a trashed board ID resolves to the default board on every
 * `_wcb_board_id` write (sanitize callback, REST create, REST update), and a
 * restored board comes back published so it takes jobs again.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Modules\Boards\BoardsModule;
use WCB\Modules\Jobs\JobsMeta;

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

wp_set_current_user( 1 );
$default = BoardsModule::get_default_board_id();
$board   = (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_board',
		'post_status' => 'publish',
		'post_title'  => 'Design Leadership',
	)
);
$ok( $board === JobsMeta::sanitize_board_id( $board ), 'a published board is kept' );

wp_trash_post( $board );
$ok( $default === JobsMeta::sanitize_board_id( $board ), 'a trashed board resolves to the default board' );

$req = new WP_REST_Request( 'POST', '/wcb/v1/jobs' );
$req->set_body_params(
	array(
		'title'       => 'Senior Product Designer',
		'description' => 'Lead the design system behind our checkout.',
		'board_id'    => $board,
		'status'      => 'draft',
	)
);
$res = rest_do_request( $req );
$job = (int) ( $res->get_data()['id'] ?? 0 );
$ok( 201 === $res->get_status() && $job > 0, 'posting to a trashed board still creates the job' );
$ok( $default === (int) get_post_meta( $job, '_wcb_board_id', true ), 'the job lands on the default board, not the trashed one' );

$job2 = (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'draft',
		'post_title'  => 'Design Systems Lead',
	)
);
$upd  = new WP_REST_Request( 'POST', '/wcb/v1/jobs/' . $job2 );
$upd->set_body_params( array( 'board_id' => $board ) );
rest_do_request( $upd );
$ok( $default === (int) get_post_meta( $job2, '_wcb_board_id', true ), 'moving a job to a trashed board keeps it on the default board' );

wp_untrash_post( $board );
$ok( 'publish' === get_post_status( $board ), 'a restored board comes back published' );
$ok( $board === JobsMeta::sanitize_board_id( $board ), 'a restored board takes jobs again' );

wp_delete_post( $job, true );
wp_delete_post( $job2, true );
wp_delete_post( $board, true );
$ok( $default === JobsMeta::sanitize_board_id( $board ), 'a deleted board resolves to the default board' );

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Trashed board tests passed.' );
