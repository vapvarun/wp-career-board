<?php
/**
 * A remote job needs a country for Google for Jobs; without one it is left out.
 *
 * Run: wp eval-file tests/test-job-posting-remote.php
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

$wcb_pass = 0;
$wcb_fail = 0;
$wcb_chk  = static function ( bool $ok, string $label ) use ( &$wcb_pass, &$wcb_fail ): void {
	$ok ? ++$wcb_pass : ++$wcb_fail;
	WP_CLI::log( ( $ok ? '  PASS: ' : '  FAIL: ' ) . $label );
};

$wcb_settings = get_option( 'wcb_settings' );
$wcb_job      = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'Remote chef probe', 'post_content' => 'Cook remotely.' ) );
update_post_meta( $wcb_job, '_wcb_remote', '1' );
update_post_meta( $wcb_job, '_wcb_deadline', gmdate( 'Y-m-d H:i:s', time() + 20 * DAY_IN_SECONDS ) );
$wcb_post = get_post( $wcb_job );

$wcb_set = static function ( string $country ): void {
	$s                    = (array) get_option( 'wcb_settings', array() );
	$s['default_country'] = $country;
	update_option( 'wcb_settings', $s );
	\WCB\Admin\Settings::flush_cache();
};

// A locale with no region (de) and no default country: nothing to say about where remote workers may live.
$wcb_locale = static fn(): string => 'de';
add_filter( 'locale', $wcb_locale );
$wcb_set( '' );
$wcb_chk( '' === \WCB\Modules\Seo\SeoModule::default_country(), 'precondition: no default country on a region-less locale' );
$wcb_chk( array() === \WCB\Modules\Seo\SeoModule::job_posting( $wcb_post ), 'a remote job with no country is left out of JobPosting' );
$wcb_chk( false === \WCB\Modules\Seo\SeoModule::has_location( $wcb_post ), 'the Jobs list marks it (has_location is false)' );

$wcb_set( 'Germany' );
$wcb_posting = \WCB\Modules\Seo\SeoModule::job_posting( $wcb_post );
$wcb_chk( 'TELECOMMUTE' === ( $wcb_posting['jobLocationType'] ?? '' ) && 'Germany' === ( $wcb_posting['applicantLocationRequirements']['name'] ?? '' ), 'with a default country it is TELECOMMUTE with applicantLocationRequirements' );
$wcb_chk( true === \WCB\Modules\Seo\SeoModule::has_location( $wcb_post ), 'and the Jobs list does not mark it' );

remove_filter( 'locale', $wcb_locale );
update_option( 'wcb_settings', $wcb_settings );
\WCB\Admin\Settings::flush_cache();
wp_delete_post( $wcb_job, true );

WP_CLI::log( "  Total: {$wcb_pass}+{$wcb_fail}  Pass: {$wcb_pass}  Fail: {$wcb_fail}" );
$wcb_fail > 0 ? WP_CLI::error( "{$wcb_fail} test(s) failed." ) : WP_CLI::success( 'Remote jobs need a country.' );
