<?php
/**
 * Tests for WCB\Core\TemplateOverride::keep().
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-template-override.php
 *
 * Pins Basecamp 10350370251: keep() must compare a resolved template path
 * against the actual plugin directories, not a substring of the plugin slug
 * anywhere in the path. An install whose path happens to contain the slug
 * (a demo folder, a site named after the plugin) must not have the theme's
 * own generic single.php mistaken for one of ours.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	echo "This file must be run via wp eval-file.\n";
	exit( 1 );
}

use WCB\Core\TemplateOverride;

$GLOBALS['wcb_tplo_test_pass'] = 0;
$GLOBALS['wcb_tplo_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_tplo_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		WP_CLI::log( "  PASS: {$label}" );
		++$GLOBALS['wcb_tplo_test_pass'];
	} else {
		WP_CLI::warning( "  FAIL: {$label}" );
		++$GLOBALS['wcb_tplo_test_fail'];
	}
}

WP_CLI::log( '=== WCB\\Core\\TemplateOverride::keep() tests ===' );

wcb_tplo_assert( class_exists( TemplateOverride::class ), 'WCB\\Core\\TemplateOverride class is loaded' );

// A theme's own generic single.php, resolved under a fake site path that
// happens to contain the plugin slug as a path component (the exact shape
// of Basecamp 10350370251: "/Local Sites/wp-career-board/.../themes/reign/single.php").
// Old buggy behaviour: str_contains() on the slug matched this and kept the
// theme's generic template. Fixed behaviour: false, since the resolved path
// is not inside WCB_DIR or WCBP_DIR.
$wcb_slug_in_path = '/Local Sites/wp-career-board/app/public/wp-content/themes/reign/single.php';
wcb_tplo_assert(
	false === TemplateOverride::keep( $wcb_slug_in_path, 'single-wcb_company.php' ),
	'a theme generic template is not kept just because the install path contains the plugin slug'
);

// A theme template matching the exact slot name, resolved under the site's
// REAL active theme directory — is_theme_template() already handles this
// branch, unaffected by this fix. (The fake slug-in-path above can't be used
// here: is_theme_template() checks against the real get_template_directory(),
// not an arbitrary path.)
$wcb_theme_specific = trailingslashit( get_template_directory() ) . 'single-wcb_company.php';
wcb_tplo_assert(
	true === TemplateOverride::keep( $wcb_theme_specific, 'single-wcb_company.php' ),
	'a theme template matching the exact slot name is still kept'
);

// A genuine bundled-integration template living inside the plugin's own
// directory must still be kept, regardless of the site's install path.
$wcb_own_template = WCB_DIR . 'integrations/reign/templates/single-wcb_job.php';
wcb_tplo_assert(
	true === TemplateOverride::keep( $wcb_own_template, 'single-wcb_job.php' ),
	'a template inside the plugin\'s own directory is kept'
);

// A Pro-owned template (e.g. Pro's resume fallback) is kept via WCBP_DIR when Pro is active.
if ( defined( 'WCBP_DIR' ) ) {
	$wcb_pro_template = WCBP_DIR . 'modules/resume/templates/single-wcb_resume.php';
	wcb_tplo_assert(
		true === TemplateOverride::keep( $wcb_pro_template, 'single-wcb_resume.php' ),
		'a template inside the Pro plugin directory is kept'
	);
} else {
	WP_CLI::log( '  SKIP: Pro directory case (WCBP_DIR not defined, Pro inactive)' );
}

// Empty template path is never kept.
wcb_tplo_assert( false === TemplateOverride::keep( '' ), 'an empty template path is never kept' );

WP_CLI::log( '' );
WP_CLI::log(
	sprintf(
		'=== Results: %d passed, %d failed ===',
		(int) $GLOBALS['wcb_tplo_test_pass'],
		(int) $GLOBALS['wcb_tplo_test_fail']
	)
);

if ( (int) $GLOBALS['wcb_tplo_test_fail'] > 0 ) {
	WP_CLI::error( 'TemplateOverride tests failed.' );
}
