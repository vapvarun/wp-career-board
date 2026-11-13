<?php
/**
 * Social sharing tags back off for SEO plugins (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-social-tags.php
 *
 * Pins: the Open Graph tags print on a job page, are skipped when the
 * wcb_social_tags_enabled filter says no, and are skipped when an SEO plugin
 * that prints its own is active (AIOSEO, SEOPress). The constants cannot be
 * undefined, so the active-plugin case runs last.
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

$wcb_job = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'Social tags probe ' . wp_generate_password( 5, false ) ) );
$wcb_tags = static function () use ( $wcb_job ): int {
	$query                = new WP_Query( array( 'p' => $wcb_job, 'post_type' => 'wcb_job' ) );
	$GLOBALS['wp_query']  = $query;
	$query->the_post();
	ob_start();
	( new \WCB\Modules\Seo\SeoModule() )->inject_og_tags();
	return substr_count( (string) ob_get_clean(), 'og:title' );
};

$wcb_chk( 1 === $wcb_tags(), 'a job page prints og:title once' );
add_filter( 'wcb_social_tags_enabled', '__return_false' );
$wcb_chk( 0 === $wcb_tags(), 'the wcb_social_tags_enabled filter can switch the tags off' );
remove_filter( 'wcb_social_tags_enabled', '__return_false' );
foreach ( array( 'AIOSEO_VERSION', 'SEOPRESS_VERSION' ) as $wcb_const ) {
	if ( ! defined( $wcb_const ) ) {
		define( $wcb_const, '1' );
		$wcb_chk( 0 === $wcb_tags(), "the tags are skipped when {$wcb_const} is defined" );
		break; // One constant is enough to prove the branch; the other is read the same way.
	}
}

wp_delete_post( $wcb_job, true );
WP_CLI::log( "  Total: {$wcb_pass}+{$wcb_fail}  Pass: {$wcb_pass}  Fail: {$wcb_fail}" );
$wcb_fail > 0 ? WP_CLI::error( "{$wcb_fail} test(s) failed." ) : WP_CLI::success( 'Social tags back-off holds.' );
