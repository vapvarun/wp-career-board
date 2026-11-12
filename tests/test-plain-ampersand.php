<?php
/**
 * Ampersand in titles stays an ampersand (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-plain-ampersand.php
 *
 * Pins: a job, company, resume or board title saved by a member without
 * unfiltered_html is stored with "&", not "&amp;"; a script tag is still stripped;
 * the 1.4.6 upgrade step repairs titles already stored with the entity.
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

$wcb_s    = wp_generate_password( 6, false );
$wcb_emp  = (int) wp_insert_user( array( 'user_login' => 'amp_' . $wcb_s, 'user_pass' => wp_generate_password(), 'user_email' => "amp_{$wcb_s}@example.test", 'role' => 'wcb_employer' ) );
wp_set_current_user( $wcb_emp );
kses_init(); // This member has no unfiltered_html, so core's title filter runs.

$wcb_ids = array();
foreach ( array( 'wcb_job', 'wcb_company', 'wcb_resume', 'wcb_board' ) as $wcb_type ) {
	if ( ! post_type_exists( $wcb_type ) ) {
		continue;
	}
	$wcb_id    = (int) wp_insert_post( array( 'post_type' => $wcb_type, 'post_status' => 'draft', 'post_author' => $wcb_emp, 'post_title' => 'Smith & Sons <script>alert(1)</script>Roasters ' . $wcb_s ) );
	$wcb_ids[] = $wcb_id;
	$wcb_title = (string) get_post_field( 'post_title', $wcb_id );
	$wcb_chk( 0 === strpos( $wcb_title, 'Smith & Sons ' ) && false === strpos( $wcb_title, '&amp;' ), "{$wcb_type}: title keeps a plain &" );
	$wcb_chk( false === strpos( $wcb_title, '<script>' ), "{$wcb_type}: a script tag is still stripped" );
}

// Upgrade step repairs a stored entity.
global $wpdb;
$wcb_old = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'draft', 'post_author' => $wcb_emp, 'post_title' => 'Plain ' . $wcb_s ) );
$wpdb->update( $wpdb->posts, array( 'post_title' => 'Bread &amp; Butter ' . $wcb_s ), array( 'ID' => $wcb_old ) );
clean_post_cache( $wcb_old );
$wcb_m = new ReflectionMethod( '\WCB\Core\Install', 'migrate_plain_ampersand_titles' );
$wcb_m->setAccessible( true );
$wcb_m->invoke( null );
clean_post_cache( $wcb_old );
$wcb_chk( 'Bread & Butter ' . $wcb_s === get_post_field( 'post_title', $wcb_old ), 'upgrade step repairs an existing &amp; title' );

foreach ( array_merge( $wcb_ids, array( $wcb_old ) ) as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $wcb_emp );

WP_CLI::log( "  Total: {$wcb_pass}+{$wcb_fail}  Pass: {$wcb_pass}  Fail: {$wcb_fail}" );
$wcb_fail > 0 ? WP_CLI::error( "{$wcb_fail} test(s) failed." ) : WP_CLI::success( 'Ampersand titles are plain.' );
