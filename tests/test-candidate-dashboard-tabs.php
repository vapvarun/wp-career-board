<?php
/**
 * Candidate dashboard: one Saved tab, tiles that open their tab, the new
 * alert form and the status history toggle (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-candidate-dashboard-tabs.php
 *
 * Renders the block as a candidate and reads its markup and interactivity
 * config; the hash aliases are checked in view.js, where they run.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$GLOBALS['wcb_cdt_pass'] = 0;
$GLOBALS['wcb_cdt_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_cdt_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_cdt_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_cdt_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

WP_CLI::log( '=== Candidate dashboard tabs ===' );

$wcb_cdt_user = wp_insert_user(
	array(
		'user_login' => 'wcb_cdt_' . wp_generate_password( 6, false ),
		'user_pass'  => wp_generate_password(),
		'user_email' => 'wcb-cdt-' . wp_generate_password( 6, false ) . '@example.com',
		'role'       => 'wcb_candidate',
	)
);
wp_set_current_user( $wcb_cdt_user );

$wcb_cdt_html   = render_block(
	array(
		'blockName'    => 'wp-career-board/candidate-dashboard',
		'attrs'        => array(),
		'innerBlocks'  => array(),
		'innerHTML'    => '',
		'innerContent' => array(),
	)
);
$wcb_cdt_config = (array) wp_interactivity_config( 'wcb-candidate-dashboard' );
$wcb_cdt_alerts = (bool) apply_filters( 'wcb_pro_alerts_enabled', false );

// D4: one Saved tab, with a Jobs / Companies switch; no Saved resumes for candidates.
wcb_cdt_assert( 1 === substr_count( $wcb_cdt_html, 'id="wcb-tab-bookmarks"' ), 'one Saved item in the sidebar' );
wcb_cdt_assert( false === strpos( $wcb_cdt_html, 'id="wcb-tab-saved-companies"' ), 'no separate Saved companies item' );
wcb_cdt_assert( false === strpos( $wcb_cdt_html, 'isTabSavedResumes' ) && false === strpos( $wcb_cdt_html, 'unbookmarkResume' ), 'no Saved resumes on the candidate dashboard' );
wcb_cdt_assert( 2 === substr_count( $wcb_cdt_html, 'class="wcb-cd-switch__btn"' ), 'Saved tab has a Jobs / Companies switch' );
wcb_cdt_assert( ! in_array( 'saved-resumes', (array) ( $wcb_cdt_config['tabs'] ?? array() ), true ), 'saved-resumes is no longer a tab' );
$wcb_cdt_aliases = (array) ( $wcb_cdt_config['aliases'] ?? array() );
wcb_cdt_assert( 'bookmarks' === ( $wcb_cdt_aliases['saved-resumes'] ?? '' ) && 'bookmarks' === ( $wcb_cdt_aliases['saved-jobs'] ?? '' ), 'old #saved-resumes and #saved-jobs links open the Saved tab' );
wcb_cdt_assert( in_array( 'bookmarks', (array) $wcb_cdt_config['tabs'], true ) && in_array( 'saved-companies', (array) $wcb_cdt_config['tabs'], true ), '#bookmarks and #saved-companies still route' );

$wcb_cdt_view = (string) file_get_contents( dirname( __DIR__ ) . '/blocks/candidate-dashboard/view.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
wcb_cdt_assert( (bool) preg_match( '/\*init\(\) \{\s*resolveTabAlias\(\);\s*state\.tab = tabs\.initial/', $wcb_cdt_view ), 'an alias hash is rewritten before the router reads it' );
wcb_cdt_assert( (bool) preg_match( "/'hashchange', withScope\( \(\) => \{\s*resolveTabAlias\(\);/", $wcb_cdt_view ), 'an alias typed into the hash later also resolves' );

// D3: tiles are buttons that open their tab.
wcb_cdt_assert( false === strpos( $wcb_cdt_html, '<div class="wcb-stat-card' ), 'no tile is a dead div' );
wcb_cdt_assert( substr_count( $wcb_cdt_html, '<button type="button" class="wcb-stat-card wcb-stat-card--link"' ) >= 3, 'each overview tile is a button' );
wcb_cdt_assert( false !== strpos( $wcb_cdt_html, 'class="wcb-cd-next-step"' ), 'overview has the next-step line' );

// D1: a row expands to its status history.
wcb_cdt_assert( (bool) preg_match( '/<button type="button"[^>]*wcb-cd-app-history-toggle[^>]*data-wp-bind--aria-expanded=/', $wcb_cdt_html ), 'status history is a button with aria-expanded' );
wcb_cdt_assert( false !== strpos( $wcb_cdt_html, 'data-wp-each--step="context.application.timeline"' ), 'history lists the timeline steps' );

// D2: new alert form, labelled fields, only where alerts are on.
if ( $wcb_cdt_alerts ) {
	foreach ( array( 'wcb-alert-keywords', 'wcb-alert-location', 'wcb-alert-frequency' ) as $wcb_cdt_id ) {
		wcb_cdt_assert( false !== strpos( $wcb_cdt_html, 'for="' . $wcb_cdt_id . '"' ) && false !== strpos( $wcb_cdt_html, 'id="' . $wcb_cdt_id . '"' ), "alert form field {$wcb_cdt_id} has a label" );
	}
	wcb_cdt_assert( false !== strpos( $wcb_cdt_html, 'data-wp-on--submit="actions.createAlert"' ), 'alert form submits to createAlert' );
} else {
	wcb_cdt_assert( false === strpos( $wcb_cdt_html, 'wcb-cd-alert-form' ), 'no alert form when alerts are off' );
}

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $wcb_cdt_user );

WP_CLI::log( sprintf( 'Passed: %d  Failed: %d', $GLOBALS['wcb_cdt_pass'], $GLOBALS['wcb_cdt_fail'] ) );
if ( $GLOBALS['wcb_cdt_fail'] > 0 ) {
	WP_CLI::halt( 1 );
}
