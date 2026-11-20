<?php
/**
 * An empty board gets default job types and experience levels (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-default-terms.php
 *
 * Install::seed_default_terms() fills only a taxonomy that has no terms, never
 * touches a list the owner already has, and is safe to run twice.
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
$count = static fn( string $t ): int => (int) wp_count_terms( array( 'taxonomy' => $t, 'hide_empty' => false ) );
$names = static fn( string $t ): array => array_map( 'strval', (array) get_terms( array( 'taxonomy' => $t, 'hide_empty' => false, 'fields' => 'names' ) ) );

$ok( method_exists( \WCB\Core\Install::class, 'seed_default_terms' ), 'Install::seed_default_terms() exists' );

$had_type = $count( 'wcb_job_type' );
$had_exp  = $count( 'wcb_experience' );

\WCB\Core\Install::seed_default_terms();
if ( 0 === $had_type ) {
	$ok( 6 === $count( 'wcb_job_type' ) && in_array( 'Full-time', $names( 'wcb_job_type' ), true ), 'empty job types get the six defaults' );
} else {
	$ok( $had_type === $count( 'wcb_job_type' ), 'an owner list of job types is left alone' );
}
if ( 0 === $had_exp ) {
	$ok( 4 === $count( 'wcb_experience' ), 'empty experience levels get the four defaults' );
} else {
	$ok( $had_exp === $count( 'wcb_experience' ), 'an owner list of experience levels is left alone' );
}

$after = $count( 'wcb_job_type' );
\WCB\Core\Install::seed_default_terms();
$ok( $after === $count( 'wcb_job_type' ), 'running it twice adds nothing' );

// A single owner-made level must stop the seed for that taxonomy.
if ( 0 === $had_exp ) {
	foreach ( (array) get_terms( array( 'taxonomy' => 'wcb_experience', 'hide_empty' => false, 'fields' => 'ids' ) ) as $id ) {
		wp_delete_term( (int) $id, 'wcb_experience' );
	}
	wp_insert_term( 'Principal', 'wcb_experience' );
	\WCB\Core\Install::seed_default_terms();
	$ok( 1 === $count( 'wcb_experience' ), 'one owner-made level is not padded with defaults' );
	wp_delete_term( (int) get_term_by( 'name', 'Principal', 'wcb_experience' )->term_id, 'wcb_experience' );
	\WCB\Core\Install::seed_default_terms();
}

WP_CLI::log( "{$pass} passed, {$fail} failed" );
exit( $fail ? 1 : 0 );
