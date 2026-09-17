<?php
/**
 * Gate: the smoke report must be NEWER than the shipped code it vouches for.
 *
 * WHY THIS EXISTS
 *
 * build-release.sh already refuses a smoke report whose `release_version`
 * differs from the version being packaged. That catches a report from a
 * previous release. It does not catch a report from THIS release that was
 * written before the last few commits landed - same version string, so the
 * string comparison is happy, and the walk never saw the code being shipped.
 *
 * That is not hypothetical. 1.7.1 shipped exactly that way: the browser walk
 * ran at 2026-09-16T06:52Z, and 5b3e926 (the _wp_http_referer settings fix, a
 * customer-visible admin bug) was committed after it. The gate passed because
 * both said "1.7.1". The fix was verified by hand, so nothing bad shipped - but
 * the gate that was supposed to prove it had not actually looked.
 *
 * So compare timestamps, not version strings. Any commit touching shipped code
 * after the walk means the walk is stale: re-run it, or say out loud that you
 * are shipping unwalked code.
 *
 * Docs, audit records, plans, tests and bin/ tooling are excluded - changing a
 * runbook does not invalidate a browser walk, and treating it as if it did
 * would train people to pass --allow-stale-smoke by reflex, which is how a gate
 * stops meaning anything.
 *
 * Usage:
 *   php bin/check-smoke-freshness.php
 *   php bin/check-smoke-freshness.php --report=docs/qa/.last-smoke-pass-pro.json
 *
 * Exit 0 when the walk postdates the code, 1 when it does not.
 *
 * @package WP_Career_Board
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput

$root   = dirname( __DIR__ );
$report = $root . '/docs/qa/.last-smoke-pass.json';

foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( 0 === strpos( $arg, '--report=' ) ) {
		$candidate = substr( $arg, 9 );
		$report    = ( '' !== $candidate && '/' === $candidate[0] ) ? $candidate : $root . '/' . $candidate;
	}
}

if ( ! is_readable( $report ) ) {
	fwrite( STDERR, "FAIL: no smoke report at {$report}\n" );
	exit( 1 );
}

$data   = json_decode( (string) file_get_contents( $report ), true );
$ran_at = is_array( $data ) ? ( $data['ran_at'] ?? '' ) : '';

if ( '' === $ran_at ) {
	fwrite( STDERR, "FAIL: smoke report has no ran_at timestamp; cannot prove it is current.\n" );
	exit( 1 );
}

$walked = strtotime( (string) $ran_at );
if ( false === $walked ) {
	fwrite( STDERR, "FAIL: smoke report ran_at is not a parseable timestamp: {$ran_at}\n" );
	exit( 1 );
}

/**
 * Commits touching shipped code since the walk.
 *
 * Pathspecs are exclusions rather than an allowlist: a new shipped directory
 * should count automatically, where an allowlist would silently ignore it.
 */
$cmd = sprintf(
	'cd %s && git log --since=%s --pretty=format:%%h%%x09%%cI%%x09%%s -- . %s 2>/dev/null',
	escapeshellarg( $root ),
	escapeshellarg( gmdate( 'c', $walked ) ),
	implode(
		' ',
		array_map(
			'escapeshellarg',
			array(
				':(exclude)docs/**',
				':(exclude)audit/**',
				':(exclude)plan/**',
				':(exclude)tests/**',
				':(exclude)bin/**',
				':(exclude).githooks/**',
				':(exclude)dist/**',
				':(exclude)*.md',
				':(exclude)readme.txt',
				// Tooling config. composer.json and package.json are safe to drop:
				// .distignore keeps both OUT of the zip, and a change to a shipped
				// dependency always rewrites the matching lockfile, which stays in.
				':(exclude)composer.json',
				':(exclude)package.json',
				':(exclude)phpcs.xml*',
				':(exclude)phpstan*.neon*',
				':(exclude).gitignore',
				':(exclude).editorconfig',
			)
		)
	)
);

$out     = (string) shell_exec( $cmd );
$commits = array_values( array_filter( array_map( 'trim', explode( "\n", $out ) ) ) );

printf( "  smoke walked at : %s\n", gmdate( 'c', $walked ) );
printf( "  report          : %s\n", str_replace( $root . '/', '', $report ) );

if ( ! $commits ) {
	echo "check-smoke-freshness OK: no shipped-code commits since the walk.\n";
	exit( 0 );
}

fwrite( STDERR, sprintf( "\nFAIL: %d shipped-code commit(s) landed AFTER the smoke walk:\n\n", count( $commits ) ) );
foreach ( $commits as $line ) {
	fwrite( STDERR, '  ' . $line . "\n" );
}
fwrite(
	STDERR,
	"\nThe report vouches for code it never exercised. Re-run /wp-plugin-smoke\n"
	. "against HEAD, or pass --skip-browser-smoke to build-release.sh and own the\n"
	. "decision explicitly. A matching version string is not evidence.\n"
);
exit( 1 );
