<?php
/**
 * Site Brand tests (1.8.0).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-brand.php
 *
 * Pins: the upgrade moves the email header colour and logo into the Brand
 * (so existing emails look the same), keeps the footer text, is safe to run
 * twice, and emails and app-config read the same Brand.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Core\Brand;
use WCB\Core\Install;

$GLOBALS['wcb_brand_pass'] = 0;
$GLOBALS['wcb_brand_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_brand_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_brand_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_brand_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

WP_CLI::log( '=== Site Brand ===' );

$wcb_brand_snapshot = get_option( 'wcb_settings', array() );
$wcb_brand_logo     = (int) wp_insert_attachment(
	array(
		'post_title'     => 'wcb brand test logo',
		'post_mime_type' => 'image/png',
		'post_status'    => 'inherit',
	),
	'wcb-brand-test.png'
);

// A pre-1.8.0 site: colour and logo live in the email settings only.
update_option(
	'wcb_settings',
	array(
		'max_resumes' => 3,
		'emails'      => array(
			'brand' => array(
				'header_color' => '#ff5500',
				'logo_id'      => $wcb_brand_logo,
				'footer_text'  => 'Footer stays',
			),
		),
	)
);
\WCB\Admin\Settings::flush_cache();

Install::migrate_email_brand();
\WCB\Admin\Settings::flush_cache();
$wcb_brand_after = get_option( 'wcb_settings' );

wcb_brand_assert( '#FF5500' === $wcb_brand_after['accent_color'], 'email header colour becomes the Brand colour' );
wcb_brand_assert( $wcb_brand_logo === (int) $wcb_brand_after['logo_id'], 'email logo becomes the Brand logo' );
wcb_brand_assert( array( 'footer_text' => 'Footer stays' ) === $wcb_brand_after['emails']['brand'], 'footer text stays; old colour and logo keys removed' );
wcb_brand_assert( 3 === (int) $wcb_brand_after['max_resumes'], 'other settings untouched' );

Install::migrate_email_brand();
wcb_brand_assert( get_option( 'wcb_settings' ) === $wcb_brand_after, 'second run changes nothing' );

wcb_brand_assert( '#FF5500' === Brand::color(), 'Brand::color() reads the Brand' );

$wcb_brand_config = rest_do_request( new WP_REST_Request( 'GET', '/wcb/v1/settings/app-config' ) )->get_data();
wcb_brand_assert( '#FF5500' === $wcb_brand_config['accent_color'], 'app-config accent_color is the Brand colour' );

ob_start();
require WCB_DIR . 'modules/notifications/templates/emails/email-header.php';
wcb_brand_assert( str_contains( (string) ob_get_clean(), '#FF5500' ), 'email header paints the Brand colour' );

// A site that never set a colour gets the default.
update_option( 'wcb_settings', array() );
\WCB\Admin\Settings::flush_cache();
wcb_brand_assert( '#4F46E5' === Brand::color(), 'default Brand colour when none is set' );
wcb_brand_assert( '' === Brand::logo_url(), 'no logo when none is set' );

wp_delete_attachment( $wcb_brand_logo, true );
update_option( 'wcb_settings', $wcb_brand_snapshot );
\WCB\Admin\Settings::flush_cache();

WP_CLI::log( '' );
WP_CLI::log( sprintf( '=== Results: %d passed, %d failed ===', (int) $GLOBALS['wcb_brand_pass'], (int) $GLOBALS['wcb_brand_fail'] ) );
if ( (int) $GLOBALS['wcb_brand_fail'] > 0 ) {
	WP_CLI::error( 'Brand tests failed.' );
}
