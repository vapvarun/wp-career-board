<?php
/**
 * Gate: the number of rules silenced by `phpcs:ignore` may shrink, never grow.
 *
 * WHY THIS EXISTS
 *
 * bin/coding-rules-check.sh enforces the architecture rules in CLAUDE.md, and
 * every one of its greps drops any line containing `phpcs:ignore`. That escape
 * hatch is necessary - some call sites genuinely cannot satisfy the rule, and a
 * rule with no exit turns into a rule people delete. But it had no budget, so
 * the exemptions were invisible and unbounded: on 2026-09-17 a QA sweep found
 * Rule 2 ("Abilities API only, never bare current_user_can") reporting OK in
 * --full mode with 43 silenced call sites across the free/pro pair, 19 of them
 * in one Pro admin file. Nothing was wrong with any individual ignore. The
 * problem was that adding the next one cost nothing and nobody could see the
 * total.
 *
 * So: count them, commit the count, and fail when it goes up. Existing
 * exemptions are grandfathered, new ones have to be deliberate - you either
 * solve the call site or you run --update and the growth shows up in the diff
 * for review. Same discipline as audit/qa-coverage.json, which this repo
 * already applies to test coverage, and as a PHPStan baseline.
 *
 * Usage:
 *   php bin/check-ignore-budget.php            # gate: fail if any budget grew
 *   php bin/check-ignore-budget.php --update   # re-baseline deliberately
 *
 * Exit 0 clean (or shrunk, baseline rewritten down), 1 when a budget grew.
 *
 * @package WP_Career_Board
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput

$root     = dirname( __DIR__ );
$baseline = $root . '/audit/ignore-budget.json';
$update   = in_array( '--update', array_slice( $argv, 1 ), true );

/**
 * The rules whose ignores we budget.
 *
 * `pattern` is the call site the matching coding rule looks for. Keep these in
 * step with bin/coding-rules-check.sh - if a rule's grep changes, this one has
 * to change with it or the budget silently measures the wrong thing.
 */
$rules = array(
	'abilities-api' => array(
		'label'   => 'Rule 2 - bare current_user_can() instead of wp_is_ability_granted()',
		'pattern' => '/(?<!bp_)current_user_can\s*\(/',
	),
	'prepared-sql'  => array(
		'label'   => 'Rule 6 - $wpdb call without ->prepare()',
		'pattern' => '/\$wpdb->(query|get_var|get_col|get_row|get_results)\s*\(/',
	),
);

/** Our own code only: the vendored SDKs, builds and tests are not ours to fix. */
$own_files = static function ( string $root ): array {
	$out = array();
	$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$path = $file->getPathname();
		if ( substr( $path, -4 ) !== '.php' ) {
			continue;
		}
		foreach ( array( '/libs/', '/vendor/', '/dist/', '/tests/', '/node_modules/', '/.git/' ) as $skip ) {
			if ( strpos( $path, $skip ) !== false ) {
				continue 2;
			}
		}
		$out[] = $path;
	}
	sort( $out );
	return $out;
};

$files   = $own_files( $root );
$current = array();
$sites   = array();

foreach ( $rules as $key => $rule ) {
	$current[ $key ] = 0;
	$sites[ $key ]   = array();
}

foreach ( $files as $path ) {
	$lines = file( $path, FILE_IGNORE_NEW_LINES );
	if ( ! is_array( $lines ) ) {
		continue;
	}
	foreach ( $lines as $n => $line ) {
		// Only lines the coding rule would have caught but for the ignore.
		if ( strpos( $line, 'phpcs:ignore' ) === false ) {
			continue;
		}
		foreach ( $rules as $key => $rule ) {
			if ( preg_match( $rule['pattern'], $line ) ) {
				++$current[ $key ];
				$sites[ $key ][] = str_replace( $root . '/', '', $path ) . ':' . ( $n + 1 );
			}
		}
	}
}

$previous = array();
if ( is_readable( $baseline ) ) {
	$decoded  = json_decode( (string) file_get_contents( $baseline ), true );
	$previous = is_array( $decoded ) && isset( $decoded['budgets'] ) ? $decoded['budgets'] : array();
}

$grew     = array();
$shrank   = array();
$reported = false;

foreach ( $rules as $key => $rule ) {
	$now    = $current[ $key ];
	$before = $previous[ $key ] ?? null;

	if ( null === $before ) {
		printf( "  %-14s %3d  (new budget)\n", $key, $now );
		continue;
	}

	if ( $now > $before ) {
		$grew[ $key ] = array( $before, $now );
		printf( "  %-14s %3d  GREW from %d\n", $key, $now, $before );
		$reported = true;
		continue;
	}

	if ( $now < $before ) {
		$shrank[ $key ] = array( $before, $now );
		printf( "  %-14s %3d  shrank from %d\n", $key, $now, $before );
		continue;
	}

	printf( "  %-14s %3d  unchanged\n", $key, $now );
}

// Write when asked, when there is no baseline yet, or when a budget shrank -
// a ratchet only holds if tightening is recorded.
if ( $update || ! is_readable( $baseline ) || $shrank ) {
	if ( ! $grew || $update ) {
		$payload = array(
			'generated' => gmdate( 'c' ),
			'note'      => 'Counts of coding-rule call sites silenced by phpcs:ignore. May shrink, never grow. See bin/check-ignore-budget.php.',
			'budgets'   => $current,
			'sites'     => $sites,
		);
		if ( ! is_dir( dirname( $baseline ) ) ) {
			mkdir( dirname( $baseline ), 0755, true );
		}
		file_put_contents( $baseline, wp_career_board_encode( $payload ) . "\n" );
		echo "  baseline written: " . str_replace( $root . '/', '', $baseline ) . "\n";
	}
}

if ( $grew ) {
	echo "\n";
	foreach ( $grew as $key => $pair ) {
		fwrite( STDERR, sprintf( "FAIL: %s\n      silenced call sites went %d -> %d\n", $rules[ $key ]['label'], $pair[0], $pair[1] ) );
	}
	fwrite( STDERR, "\nA new phpcs:ignore now hides a rule this plugin says it follows.\n" );
	fwrite( STDERR, "Fix the call site, or run `php bin/check-ignore-budget.php --update`\n" );
	fwrite( STDERR, "so the increase lands in the diff and gets reviewed like any other change.\n" );
	exit( 1 );
}

echo "check-ignore-budget OK\n";
exit( 0 );

/**
 * Pretty-print JSON without depending on WP.
 *
 * @param array<string, mixed> $data Payload.
 * @return string
 */
function wp_career_board_encode( array $data ): string {
	return (string) json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
}
