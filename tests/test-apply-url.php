<?php
/**
 * Apply URL rule (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-apply-url.php
 *
 * Pins: a job's Apply URL follows the one web-address rule (Text::web_url):
 * a bare domain gets https, words and other schemes are refused by the REST
 * rule with a message, and the meta sanitizer stores nothing for them.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$wcb_pass = 0;
$wcb_fail = 0;
$wcb_chk  = static function ( bool $ok, string $label ) use ( &$wcb_pass, &$wcb_fail ): void {
	$ok ? ++$wcb_pass : ++$wcb_fail;
	WP_CLI::log( ( $ok ? '  PASS: ' : '  FAIL: ' ) . $label );
};

$wcb_cb = ( new \WCB\Api\Endpoints\JobsEndpoint() )->get_write_params()['apply_url']['validate_callback'];
$wcb_chk( true === $wcb_cb( 'https://careers.example.com/roaster' ), 'a full https address passes' );
$wcb_chk( true === $wcb_cb( 'careers.example.com/roaster' ), 'a bare domain passes' );
$wcb_chk( true === $wcb_cb( '' ), 'empty passes (the field is optional)' );
$wcb_res = $wcb_cb( 'apply on our site' );
$wcb_chk( is_wp_error( $wcb_res ) && false !== strpos( $wcb_res->get_error_message(), 'full web address' ), 'words are refused with the web-address message' );
$wcb_chk( is_wp_error( $wcb_cb( 'ftp://files.example.com/job' ) ), 'ftp is refused' );

$wcb_job = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'draft', 'post_title' => 'Apply URL probe ' . wp_generate_password( 5, false ) ) );
update_post_meta( $wcb_job, '_wcb_apply_url', 'careers.example.com/roaster' );
$wcb_chk( 'https://careers.example.com/roaster' === get_post_meta( $wcb_job, '_wcb_apply_url', true ), 'a bare domain is stored with https, not http' );
update_post_meta( $wcb_job, '_wcb_apply_url', 'apply on our site' );
$wcb_chk( '' === get_post_meta( $wcb_job, '_wcb_apply_url', true ), 'words store nothing' );
wp_delete_post( $wcb_job, true );

WP_CLI::log( "  Total: {$wcb_pass}+{$wcb_fail}  Pass: {$wcb_pass}  Fail: {$wcb_fail}" );
$wcb_fail > 0 ? WP_CLI::error( "{$wcb_fail} test(s) failed." ) : WP_CLI::success( 'Apply URL rule holds.' );
