<?php
/**
 * A link that is not a web address is reported by the importer and by custom link fields (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-link-refusal-importer-and-fields.php
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

// Custom link fields: the validator the apply, job, company and resume forms share.
$groups = array( array( 'fields' => array(
	array( 'key' => 'portfolio', 'type' => 'url', 'label' => 'Portfolio' ),
	array( 'key' => 'showreel', 'type' => 'video_url', 'label' => 'Showreel' ),
	array( 'key' => 'nick', 'type' => 'text', 'label' => 'Nickname' ),
) ) );

$err = \WCB\Core\FormCustomFields::required_error( $groups, array( 'portfolio' => 'ana@portfolio.example' ) );
$ok( $err instanceof WP_Error && 400 === ( $err->get_error_data()['status'] ?? 0 ) && str_contains( $err->get_error_message(), 'Portfolio' ) && str_contains( $err->get_error_message(), 'web address' ), 'an email in a link field is refused and names the field' );
$err = \WCB\Core\FormCustomFields::required_error( $groups, array( 'showreel' => 'not a url' ) );
$ok( $err instanceof WP_Error && array( 'showreel' ) === ( $err->get_error_data()['fields'] ?? array() ), 'a video link that is not an address is refused' );
$ok( null === \WCB\Core\FormCustomFields::required_error( $groups, array( 'portfolio' => 'harbor.example/ana', 'showreel' => '', 'nick' => 'a@b' ) ), 'a real address, a blank link and an email in a text field pass' );

// WPJM importer: a refused link is a warning and the job still imports.
$wpjm_ready = post_type_exists( 'job_listing' );
if ( ! $wpjm_ready ) {
	register_post_type( 'job_listing', array( 'public' => false ) );
}
global $wpdb;
$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$src = (int) wp_insert_post( array( 'post_type' => 'job_listing', 'post_status' => 'publish', 'post_title' => 'LRI source job', 'post_content' => 'x' ) );
update_post_meta( $src, '_company_name', 'LRI Co' );
update_post_meta( $src, '_company_website', 'ana@portfolio.example' );
update_post_meta( $src, '_application', 'https://name@company.com' );
$importer = new \WCB\Import\WpjmImporter();
$res      = $importer->migrate_jobs_batch( 0, 5 );
$joined   = implode( ' | ', (array) ( $res['warnings'] ?? array() ) );
$ok( str_contains( $joined, 'company website' ) && str_contains( $joined, 'application link' ), 'the import result lists both refused links as warnings' );
$ok( $res['imported'] >= 1 && array() === $res['errors'], 'the job still imports, with no error' );
$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
wp_cache_flush();

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'All link refusal tests passed.' );
