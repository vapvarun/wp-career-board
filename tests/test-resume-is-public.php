<?php
/**
 * One rule for "did the candidate list this resume as Public" (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-resume-is-public.php
 *
 * Pins: CandidatesModule::resume_is_public() answers the owner's toggle only
 * (Public, Private, empty, missing, trashed), the Pro REST read reports the
 * same answer, and no PHP file in Free or Pro reads `_wcb_resume_public` with
 * get_post_meta() outside that helper (SQL meta_query lists stay).
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Modules\Candidates\CandidatesModule;

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

$has_helper = method_exists( CandidatesModule::class, 'resume_is_public' );
$ok( $has_helper, 'CandidatesModule::resume_is_public() exists' );

$prev_user = get_current_user_id();
$candidate = (int) wp_insert_user(
	array(
		'user_login' => 'wcb_rip_' . wp_generate_password( 6, false ),
		'user_pass'  => wp_generate_password( 20 ),
		'user_email' => 'wcb-rip-' . wp_generate_password( 6, false ) . '@example.test',
		'role'       => 'wcb_candidate',
		'first_name' => 'Maya',
		'last_name'  => 'Okafor',
	)
);
$resume    = (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_resume',
		'post_status' => 'publish',
		'post_title'  => 'Maya Okafor - Senior UX Researcher',
		'post_author' => $candidate,
	)
);

// Stored value => expected answer. Matches the `'value' => '1'` meta_query lists.
$cases = array(
	'Public (1)'      => array( '1', true ),
	'Private (0)'     => array( '0', false ),
	'Private (empty)' => array( '', false ),
	'missing meta'    => array( null, false ),
);
$pro_rest = rest_get_server()->get_routes()['/wcb/v1/resumes/(?P<id>\d+)'] ?? null;
wp_set_current_user( $candidate );
foreach ( $cases as $label => $case ) {
	delete_post_meta( $resume, '_wcb_resume_public' );
	if ( null !== $case[0] ) {
		update_post_meta( $resume, '_wcb_resume_public', $case[0] );
	}
	if ( $has_helper ) {
		$ok( $case[1] === CandidatesModule::resume_is_public( $resume ), "helper: {$label}" );
	}
	if ( $pro_rest ) {
		$data = rest_do_request( new WP_REST_Request( 'GET', '/wcb/v1/resumes/' . $resume ) )->get_data();
		$ok( isset( $data['is_public'] ) && $case[1] === $data['is_public'], "Pro REST is_public: {$label}" );
	}
}

update_post_meta( $resume, '_wcb_resume_public', '1' );
wp_trash_post( $resume );
if ( $has_helper ) {
	$ok( CandidatesModule::resume_is_public( $resume ), 'helper: a trashed listed resume keeps its toggle (status is not this rule)' );
}
wp_set_current_user( 0 );
$ok( ! CandidatesModule::resume_is_readable( $resume ), 'readable gate still hides a trashed listed resume from guests' );

// Class check: every PHP read of the toggle goes through the helper.
$roots   = array( WP_PLUGIN_DIR . '/wp-career-board', WP_PLUGIN_DIR . '/wp-career-board-pro' );
$copies  = array();
$pattern = '/get_post_meta\(\s*[^;]*?[\'"]_wcb_resume_public[\'"]/';
foreach ( $roots as $root ) {
	if ( ! is_dir( $root ) ) {
		continue;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$path = $file->getPathname();
		if ( 'php' !== $file->getExtension() || preg_match( '#/(libs|vendor|node_modules|dist|build|tests|bin)/#', $path ) ) {
			continue;
		}
		$src = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$n   = preg_match_all( $pattern, $src );
		// The helper itself is the one allowed read.
		if ( str_ends_with( $path, 'modules/candidates/class-candidates-module.php' ) ) {
			--$n;
		}
		if ( $n > 0 ) {
			$copies[] = str_replace( WP_PLUGIN_DIR . '/', '', $path ) . " ({$n})";
		}
	}
}
$ok( array() === $copies, 'no copy of the toggle check outside the helper' . ( $copies ? ': ' . implode( ', ', $copies ) : '' ) );

wp_set_current_user( 1 );
wp_delete_post( $resume, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $candidate );
wp_set_current_user( $prev_user );

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Resume is-public tests passed.' );
