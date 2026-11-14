<?php
/**
 * Web address rule tests (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-web-url.php
 *
 * Pins: one rule for a typed web address (http/https with a real host, else
 * empty), used by custom URL answers, the company links and the candidate
 * profile links on save, and by every display of them, so "my site" is never
 * stored as http://my%20site or shown as a dead link.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Core\FormCustomFields;

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_wu_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

rest_get_server();

WP_CLI::log( '' );
WP_CLI::log( '  Web Address Rule Tests' );

$wcb_has_helper = method_exists( '\WCB\Core\Text', 'web_url' );
wcb_wu_assert( $wcb_has_helper, 'Text::web_url() exists' );
if ( $wcb_has_helper ) {
	$wcb_cases = array(
		'https://acme.example/careers' => 'https://acme.example/careers',
		'acme.example'                 => 'https://acme.example',
		'http://legacy.acme.example'   => 'http://legacy.acme.example',
		'my site'                      => '',
		'portfolio'                    => '',
		'javascript:alert(1)'          => '',
		'ftp://files.acme.example'     => '',
		'tomas@harborline.example'     => '',
		'https://user:pw@acme.example' => '',
		'https://acme.example/a@b'     => 'https://acme.example/a@b',
		''                             => '',
	);
	foreach ( $wcb_cases as $wcb_in => $wcb_out ) {
		wcb_wu_assert( $wcb_out === \WCB\Core\Text::web_url( (string) $wcb_in ), "web_url( '{$wcb_in}' ) is '{$wcb_out}'" );
	}
}

// Custom URL answers: save, display, and the inline error.
$wcb_groups = array(
	array(
		'title'  => 'Links',
		'fields' => array(
			array(
				'key'   => 'portfolio',
				'label' => 'Portfolio',
				'type'  => 'url',
			),
			array(
				'key'   => 'showreel',
				'label' => 'Showreel',
				'type'  => 'video_url',
			),
		),
	),
);
$wcb_post = (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'draft',
		'post_title'  => 'Motion Designer',
	)
);
FormCustomFields::save_values(
	$wcb_groups,
	$wcb_post,
	array(
		'portfolio' => 'my site',
		'showreel'  => 'https://video.acme.example/reel',
	)
);
wcb_wu_assert( '' === get_post_meta( $wcb_post, 'portfolio', true ), 'a URL answer that is not a web address is stored empty (got "' . get_post_meta( $wcb_post, 'portfolio', true ) . '")' );
wcb_wu_assert( 'https://video.acme.example/reel' === get_post_meta( $wcb_post, 'showreel', true ), 'a real web address is stored as typed' );

update_post_meta( $wcb_post, 'portfolio', 'http://my%20site' ); // A row saved before 1.8.4.
$wcb_keys = array_column( FormCustomFields::labelled_values( $wcb_groups, $wcb_post ), 'value', 'key' );
wcb_wu_assert( ! isset( $wcb_keys['portfolio'] ), 'an old stored dead link is left out of every display' );
wcb_wu_assert( 'https://video.acme.example/reel' === ( $wcb_keys['showreel'] ?? '' ), 'a stored real link is still displayed' );
wp_delete_post( $wcb_post, true );

ob_start();
FormCustomFields::render_groups( $wcb_groups );
$wcb_html = (string) ob_get_clean();
wcb_wu_assert( str_contains( $wcb_html, 'wcb-field-error--url' ), 'a URL input renders its inline error' );

// Company links on save (REST) and at render.
$wcb_suffix   = wp_generate_password( 6, false );
$wcb_employer = (int) wp_insert_user(
	array(
		'user_login' => 'wu-emp-' . $wcb_suffix,
		'user_pass'  => wp_generate_password(),
		'user_email' => 'wu-emp-' . $wcb_suffix . '@example.test',
		'role'       => 'wcb_employer',
	)
);
$wcb_company  = (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_company',
		'post_status' => 'publish',
		'post_title'  => 'Northwind Studios',
		'post_author' => $wcb_employer,
	)
);
update_user_meta( $wcb_employer, '_wcb_company_id', $wcb_company );
wp_set_current_user( $wcb_employer );
$wcb_req = new WP_REST_Request( 'POST', '/wcb/v1/employers/' . $wcb_company );
$wcb_req->set_param( 'website', 'my site' );
$wcb_req->set_param( 'linkedin', 'https://www.linkedin.com/company/northwind' );
$wcb_res = rest_do_request( $wcb_req );
wcb_wu_assert( 200 === $wcb_res->get_status(), 'company save with a bad website still succeeds (got ' . $wcb_res->get_status() . ')' );
wcb_wu_assert( '' === get_post_meta( $wcb_company, '_wcb_website', true ), 'company website that is not a web address is stored empty (got "' . get_post_meta( $wcb_company, '_wcb_website', true ) . '")' );
wcb_wu_assert( 'https://www.linkedin.com/company/northwind' === get_post_meta( $wcb_company, '_wcb_linkedin', true ), 'company LinkedIn link is stored as typed' );
update_post_meta( $wcb_company, '_wcb_website', 'my site' ); // A row saved before 1.8.4.
$wcb_get = (array) rest_do_request( new WP_REST_Request( 'GET', '/wcb/v1/employers/' . $wcb_company ) )->get_data();
wcb_wu_assert( '' === ( $wcb_get['website'] ?? 'missing' ), 'REST answers an old dead company website as empty' );

$wcb_blocks = dirname( __DIR__ ) . '/blocks/';
foreach ( array( 'company-profile', 'job-single' ) as $wcb_block ) {
	$wcb_src = (string) file_get_contents( $wcb_blocks . $wcb_block . '/render.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
	wcb_wu_assert( (bool) preg_match( "/Text::web_url\\( \\(string\\) get_post_meta\\( [^)]*'_wcb_website'/", $wcb_src ), "{$wcb_block}: the company website goes through the rule" );
}

// Company profile form: the link inputs show the inline error, and the form shows what was stored.
$wcb_dash      = (string) file_get_contents( $wcb_blocks . 'employer-dashboard/render.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
$wcb_dash_view = (string) file_get_contents( $wcb_blocks . 'employer-dashboard/view.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
foreach ( array( 'site', 'linkedin', 'twitter' ) as $wcb_link ) {
	wcb_wu_assert( (bool) preg_match( '/id="wcb-company-' . $wcb_link . '"[^>]*\/>\s*<p class="wcb-field-error wcb-field-error--url" id="wcb-company-' . $wcb_link . '-error"/', $wcb_dash ), "company form: {$wcb_link} input has the inline web-address error" );
}
wcb_wu_assert( (bool) preg_match( '/state\.companySite\s*=\s*data\.website/', $wcb_dash_view ), 'company form shows the stored website after a save' );

// Browser feedback uses the server rule: the inputs are text (no native url check) and the JS mirror agrees with Text::web_url().
$wcb_fields_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/modules/wcb-fields.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
wcb_wu_assert( ! str_contains( $wcb_html, 'type="url"' ) && str_contains( $wcb_html, 'inputmode="url"' ), 'custom URL inputs skip the browser url check (a bare domain is fine)' );
foreach ( array( 'site', 'linkedin', 'twitter' ) as $wcb_link ) {
	wcb_wu_assert( (bool) preg_match( '/id="wcb-company-' . $wcb_link . '" type="text" inputmode="url"/', $wcb_dash ), "company form: {$wcb_link} input skips the browser url check" );
}
wcb_wu_assert( str_contains( $wcb_fields_js, "'aria-invalid'" ) && str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/wcb-ui.css' ), '[aria-invalid="true"] ~ .wcb-field-error--url' ), 'the inline error and aria-invalid come from the same check' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
wcb_wu_assert( str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/core/class-form-custom-fields.php' ), "wp_enqueue_script_module( '@wcb/fields' )" ), 'a custom URL input loads the module that runs the check' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.

$wcb_js_cases = array( 'example.org', 'northwind.example/careers', 'https://x.y', 'my site', 'javascript:alert(1)', 'lenahoffmann.design', 'http://legacy.acme.example', 'ftp://files.acme.example', 'acme.example:8080/jobs', 'https://acme.example:8443/jobs', 'https://acme.example/a b', 'portfolio', 'tomas@harborline.example', 'https://user:pw@acme.example', 'https://acme.example/a@b', '' );
$wcb_node_src  = 'globalThis.document={addEventListener(){}};const m=await import(' . wp_json_encode( 'file://' . dirname( __DIR__ ) . '/assets/js/modules/wcb-fields.js' ) . ');console.log(JSON.stringify(' . wp_json_encode( $wcb_js_cases ) . '.map((v)=>m.isWebAddress(v))));';
$wcb_node_out  = shell_exec( 'node --input-type=module -e ' . escapeshellarg( $wcb_node_src ) . ' 2>/dev/null' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test runner calls node on a repo file.
$wcb_js_valid  = json_decode( (string) $wcb_node_out, true );
wcb_wu_assert( is_array( $wcb_js_valid ), 'the browser check runs (node)' );
foreach ( $wcb_js_cases as $wcb_i => $wcb_case ) {
	$wcb_server = '' === $wcb_case || '' !== \WCB\Core\Text::web_url( $wcb_case );
	wcb_wu_assert( is_array( $wcb_js_valid ) && $wcb_server === ( $wcb_js_valid[ $wcb_i ] ?? null ), "browser and server agree on '{$wcb_case}' (" . ( $wcb_server ? 'accept' : 'reject' ) . ')' );
}

// Apply URL on both job forms: same inline error and the same browser check as the company links.
foreach ( array(
	'job-form'        => array( 'wcb-apply-url', 'job-form/render.php' ),
	'job-form-simple' => array( 'wcb-simple-apply-url', 'job-form-simple/render.php' ),
) as $wcb_form => $wcb_apply ) {
	$wcb_form_src = (string) file_get_contents( $wcb_blocks . $wcb_apply[1] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
	wcb_wu_assert( (bool) preg_match( '/id="' . $wcb_apply[0] . '"\s+type="text"\s+inputmode="url"/', $wcb_form_src ), "{$wcb_form}: Apply URL input skips the browser url check" );
	wcb_wu_assert( str_contains( $wcb_form_src, 'id="' . $wcb_apply[0] . '-error"' ), "{$wcb_form}: Apply URL has the inline web-address error" );
	wcb_wu_assert( str_contains( (string) file_get_contents( $wcb_blocks . $wcb_form . '/view.js' ), 'isWebAddress( state.applyUrl )' ), "{$wcb_form}: a bad Apply URL stops the form" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
}

// Custom URL answers on the job page read as links.
$wcb_single_css = (string) file_get_contents( $wcb_blocks . 'job-single/style.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
wcb_wu_assert( (bool) preg_match( '/\.wcb-job-details__row dd a\s*\{[^}]*color:\s*var\(--wcb-primary-text\)[^}]*text-decoration:\s*underline/', $wcb_single_css ), 'job page detail links use the link colour and underline' );

// Candidate profile links on save.
$wcb_cand = (int) wp_insert_user(
	array(
		'user_login' => 'wu-cand-' . $wcb_suffix,
		'user_pass'  => wp_generate_password(),
		'user_email' => 'wu-cand-' . $wcb_suffix . '@example.test',
		'role'       => 'wcb_candidate',
	)
);
wp_set_current_user( $wcb_cand );
$wcb_req = new WP_REST_Request( 'POST', '/wcb/v1/candidates/' . $wcb_cand );
$wcb_req->set_param(
	'resume_data',
	array(
		'website'  => 'my site',
		'github'   => 'github.com/ada',
		'location' => 'Lisbon, Portugal',
	)
);
$wcb_res  = rest_do_request( $wcb_req );
$wcb_data = (array) get_user_meta( $wcb_cand, '_wcb_resume_data', true );
wcb_wu_assert( 200 === $wcb_res->get_status(), 'candidate save with a bad link still succeeds (got ' . $wcb_res->get_status() . ')' );
wcb_wu_assert( '' === ( $wcb_data['website'] ?? 'missing' ), 'candidate website that is not a web address is stored empty' );
wcb_wu_assert( 'https://github.com/ada' === ( $wcb_data['github'] ?? '' ), 'candidate bare domain gets https' );
wcb_wu_assert( 'Lisbon, Portugal' === ( $wcb_data['location'] ?? '' ), 'candidate non-link fields are kept as text' );

wp_delete_post( $wcb_company, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $wcb_employer );
wp_delete_user( $wcb_cand );

WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
}
WP_CLI::success( 'All web address tests passed.' );
