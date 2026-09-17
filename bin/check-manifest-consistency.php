<?php
/**
 * Gate: audit/manifest.json must agree with itself and with the code.
 *
 * WHY THIS EXISTS
 *
 * CLAUDE.md tells every session to read the manifest FIRST and trust it over
 * grepping - so a manifest that disagrees with itself sends every reader wrong,
 * quietly. On 2026-09-17 a release pass found three such drifts, all hand-fixed:
 *
 *   free  counts.services 41, while services[] held 39 and the manifest's own
 *         refresh note said "services 40->39"
 *   free  generated.db_version 1.3.1, while Install::DB_VERSION was '1.3.2'
 *   pro   counts.tables 10, while class-pro-install.php stopped creating one of
 *         them and said so at four separate sites
 *
 * None needed judgement to catch. Each was a number that should have equalled
 * another number and did not. So check that mechanically.
 *
 * WHAT IS AND IS NOT COMPARED
 *
 * Only categories where counts.X and X[] measure the SAME thing. Three do not,
 * and the manifest documents why, so they are deliberately skipped rather than
 * forced into a false failure people learn to ignore:
 *
 *   rest          counts = registered route PATHS; rest.endpoints[] = one entry
 *                 per route+method pair (a GET+POST path is two entries)
 *   shortcodes    counts = add_shortcode() call SITES; shortcodes[] = unique tags
 *                 (one loop registers many)
 *   capabilities  counts = caps synced by class-roles.php; capabilities[] = only
 *                 the ability-backed ones
 *
 * If one of those is ever reconciled to the same unit, move it into $exact.
 *
 * Usage:
 *   php bin/check-manifest-consistency.php
 *
 * Exit 0 consistent, 1 on any mismatch.
 *
 * @package WP_Career_Board
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput

$root     = dirname( __DIR__ );
$manifest = $root . '/audit/manifest.json';

if ( ! is_readable( $manifest ) ) {
	fwrite( STDERR, "FAIL: no audit/manifest.json\n" );
	exit( 1 );
}

$data = json_decode( (string) file_get_contents( $manifest ), true );
if ( ! is_array( $data ) ) {
	fwrite( STDERR, "FAIL: audit/manifest.json is not valid JSON\n" );
	exit( 1 );
}

$exact = array( 'ajax', 'admin_pages', 'blocks', 'post_types', 'taxonomies', 'tables', 'services', 'hooks_fired', 'cron', 'wp_cli' );

$problems = array();
$counts   = is_array( $data['counts'] ?? null ) ? $data['counts'] : array();

foreach ( $exact as $key ) {
	if ( ! array_key_exists( $key, $counts ) ) {
		continue;
	}
	$array = $data[ $key ] ?? null;
	if ( ! is_array( $array ) ) {
		continue;
	}
	$declared = (int) $counts[ $key ];
	$actual   = count( $array );
	if ( $declared !== $actual ) {
		$problems[] = sprintf( 'counts.%s is %d but %s[] holds %d entries', $key, $declared, $key, $actual );
	} else {
		printf( "  %-12s %4d  ok\n", $key, $actual );
	}
}

// db_version against the constant the installer actually migrates to. The
// installer is found rather than named, so free and pro share this file.
$recorded = (string) ( $data['generated']['db_version'] ?? '' );
if ( '' !== $recorded ) {
	$constant = '';
	foreach ( glob( $root . '/core/*install*.php' ) ?: array() as $file ) {
		if ( preg_match( "/const\\s+DB_VERSION\\s*=\\s*'([^']+)'/", (string) file_get_contents( $file ), $m ) ) {
			$constant = $m[1];
			$source   = str_replace( $root . '/', '', $file );
			break;
		}
	}
	if ( '' === $constant ) {
		$problems[] = 'generated.db_version is set, but no core/*install*.php declares const DB_VERSION to check it against';
	} elseif ( $recorded !== $constant ) {
		$problems[] = sprintf( 'generated.db_version is %s but %s declares DB_VERSION = %s', $recorded, $source, $constant );
	} else {
		printf( "  %-12s %4s  ok (matches %s)\n", 'db_version', $recorded, $source );
	}
}

if ( $problems ) {
	echo "\n";
	foreach ( $problems as $p ) {
		fwrite( STDERR, "FAIL: {$p}\n" );
	}
	fwrite(
		STDERR,
		"\nThe manifest is what every session is told to read first and trust over the\n"
		. "code. A number that contradicts its own array misleads every one of them.\n"
		. "Correct the manifest to match the code - do not change the code to match it.\n"
	);
	exit( 1 );
}

echo "check-manifest-consistency OK\n";
exit( 0 );
