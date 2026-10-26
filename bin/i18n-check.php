<?php
/**
 * Audit every shipped catalogue of this plugin.
 *
 * Always (blocking, exit 1):
 *   - a translated entry whose placeholders, {tokens}, HTML or protected terms
 *     differ from the English (it breaks at runtime, reviewed or not);
 *   - a catalogue msgfmt cannot compile;
 *   - a .po that is behind the POT (an English string with no entry at all).
 * With --strict (the "100% translation ready" bar, also exit 1):
 *   - any empty or fuzzy entry in any locale.
 *
 * Prints a per-locale table either way. --json prints it as JSON.
 *
 * Usage (from the plugin root; Pro runs ../wp-career-board/bin/i18n-check.php):
 *   php bin/i18n-check.php [--strict] [--json]
 *
 * @package WP_Career_Board
 */

require __DIR__ . '/lib/po.php';

$opt    = getopt( '', array( 'strict', 'json' ) );
$domain = basename( getcwd() );
$pot    = 'languages/' . $domain . '.pot';
if ( ! is_readable( $pot ) ) {
	fwrite( STDERR, "missing {$pot} (run from the plugin root)\n" );
	exit( 2 );
}

$protect  = (array) ( wcb_po_config()['protect'] ?? array() );
$pot_keys = array();
foreach ( wcb_po_read( $pot )['blocks'] as $entry ) {
	if ( wcb_po_is_message( $entry ) ) {
		$pot_keys[ wcb_po_key( $entry ) ] = true;
	}
}

$rows   = array();
$broken = array();
foreach ( glob( 'languages/' . $domain . '-*.po' ) as $po ) {
	$locale = substr( basename( $po, '.po' ), strlen( $domain ) + 1 );
	$parsed = wcb_po_read( $po );
	$count  = array(
		'done'  => 0,
		'fuzzy' => 0,
		'empty' => 0,
	);
	$keys   = array();
	foreach ( $parsed['blocks'] as $entry ) {
		if ( ! wcb_po_is_message( $entry ) ) {
			continue;
		}
		$keys[ wcb_po_key( $entry ) ] = true;
		$state                         = wcb_po_state( $entry );
		++$count[ $state ];
		if ( 'done' !== $state ) {
			continue;
		}
		$forms = is_array( $entry['str'] ) ? array_values( $entry['str'] ) : array( (string) $entry['str'] );
		foreach ( $forms as $n => $form ) {
			$source = ( 0 === $n || null === $entry['plural'] ) ? (string) $entry['id'] : (string) $entry['plural'];
			foreach ( wcb_po_problems( $source, $form, $protect ) as $problem ) {
				$broken[] = sprintf( '%s "%s": %s', $locale, $entry['id'], $problem );
			}
		}
	}
	$missing = count( array_diff_key( $pot_keys, $keys ) );
	exec( 'msgfmt -c -o /dev/null ' . escapeshellarg( $po ) . ' 2>&1', $msgfmt_out, $msgfmt_rc );
	if ( 0 !== $msgfmt_rc ) {
		$broken[] = sprintf( '%s: msgfmt -c fails: %s', $locale, implode( ' / ', array_slice( $msgfmt_out, 0, 3 ) ) );
	}
	$msgfmt_out = array();
	if ( $missing > 0 ) {
		$broken[] = sprintf( '%s: %d POT strings have no entry (run bin/make-translations.sh to merge)', $locale, $missing );
	}
	$total          = array_sum( $count );
	$rows[ $locale ] = $count + array(
		'total'   => $total,
		'percent' => $total ? round( 100 * $count['done'] / $total, 1 ) : 0,
	);
}

ksort( $rows );
if ( isset( $opt['json'] ) ) {
	echo json_encode( array( 'domain' => $domain, 'locales' => $rows, 'broken' => $broken ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "\n";
} else {
	printf( "%s (%d strings)\n%-7s %7s %7s %7s %7s\n", $domain, count( $pot_keys ), 'locale', 'done', 'fuzzy', 'empty', '%' );
	foreach ( $rows as $locale => $r ) {
		printf( "%-7s %7d %7d %7d %6.1f%%\n", $locale, $r['done'], $r['fuzzy'], $r['empty'], $r['percent'] );
	}
	foreach ( $broken as $line ) {
		echo '  BROKEN ' . $line . "\n";
	}
}

$incomplete = array_filter( $rows, static fn( $r ) => $r['fuzzy'] + $r['empty'] > 0 );
if ( $broken ) {
	exit( 1 );
}
if ( isset( $opt['strict'] ) && $incomplete ) {
	fwrite( STDERR, 'Not 100%: ' . implode( ', ', array_keys( $incomplete ) ) . " still have empty or fuzzy entries.\n" );
	exit( 1 );
}
