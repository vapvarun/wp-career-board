<?php
/**
 * Export what is left to translate as batch files, one per assignment.
 *
 * Writes <out>/<domain>-<locale>-NN.json for every locale (or one), each with up
 * to --batch entries that are empty or fuzzy. A translator (person or agent)
 * fills "msgstr" and hands the file back to bin/i18n-apply.php, which checks it
 * before anything reaches the catalogue. "same_as" carries the reviewed
 * translation of the identical English from Free (for Pro), so it is reused.
 *
 * Usage (from the plugin root; Pro runs ../wp-career-board/bin/i18n-todo.php):
 *   php bin/i18n-todo.php --out=<dir> [--locale=de_DE] [--batch=200]
 *
 * @package WP_Career_Board
 */

require __DIR__ . '/lib/po.php';

$opt    = getopt( '', array( 'out:', 'locale:', 'batch:' ) );
$out    = rtrim( (string) ( $opt['out'] ?? '' ), '/' );
$batch  = max( 20, (int) ( $opt['batch'] ?? 200 ) );
$domain = basename( getcwd() );
if ( '' === $out ) {
	fwrite( STDERR, "usage: php bin/i18n-todo.php --out=<dir> [--locale=de_DE] [--batch=200]\n" );
	exit( 2 );
}
if ( ! is_dir( $out ) && ! mkdir( $out, 0777, true ) ) {
	fwrite( STDERR, "cannot create {$out}\n" );
	exit( 2 );
}

$config  = wcb_po_config();
$pattern = 'languages/' . $domain . '-' . ( $opt['locale'] ?? '*' ) . '.po';
$files   = glob( $pattern );
if ( ! $files ) {
	fwrite( STDERR, "no catalogue matches {$pattern} (run from the plugin root)\n" );
	exit( 2 );
}

// Reviewed translations already in use, by key: this catalogue first, then the
// Free plugin's when exporting Pro. The same English gets the same wording, so a
// translator copies a match instead of translating it twice.
$known = static function ( string $file ): array {
	$out = array();
	if ( is_readable( $file ) ) {
		foreach ( wcb_po_read( $file )['blocks'] as $entry ) {
			if ( wcb_po_is_message( $entry ) && 'done' === wcb_po_state( $entry ) ) {
				$out[ wcb_po_key( $entry ) ] = $entry['str'];
			}
		}
	}
	return $out;
};

foreach ( $files as $po ) {
	$locale = substr( basename( $po, '.po' ), strlen( $domain ) + 1 );
	$parsed = wcb_po_read( $po );
	$items  = array();
	$reuse  = $known( $po );
	if ( 'wp-career-board' !== $domain ) {
		$reuse += $known( dirname( __DIR__ ) . '/languages/wp-career-board-' . $locale . '.po' );
	}
	foreach ( $parsed['blocks'] as $entry ) {
		if ( ! wcb_po_is_message( $entry ) || 'done' === wcb_po_state( $entry ) ) {
			continue;
		}
		$item = array(
			'key'   => wcb_po_key( $entry ),
			'msgid' => $entry['id'],
		);
		if ( null !== $entry['ctx'] ) {
			$item['context'] = $entry['ctx'];
		}
		if ( null !== $entry['plural'] ) {
			$item['msgid_plural'] = $entry['plural'];
		}
		if ( $entry['comment'] ) {
			$item['note'] = implode( ' ', $entry['comment'] );
		}
		if ( $entry['refs'] ) {
			$item['where'] = strtok( $entry['refs'][0], ' ' );
		}
		if ( isset( $reuse[ $item['key'] ] ) ) {
			// Already translated elsewhere in the product: reuse it unless the note says otherwise.
			$item['same_as'] = $reuse[ $item['key'] ];
		}
		if ( 'fuzzy' === wcb_po_state( $entry ) ) {
			// gettext's guess from a similar old string: a starting point, never shipped.
			$item['fuzzy_guess'] = $entry['str'];
		}
		$item['msgstr'] = null !== $entry['plural'] ? array_fill( 0, $parsed['nplurals'], '' ) : '';
		$items[]        = $item;
	}

	$chunks = array_chunk( $items, $batch );
	foreach ( $chunks as $n => $chunk ) {
		$file = sprintf( '%s/%s-%s-%02d.json', $out, $domain, $locale, $n + 1 );
		file_put_contents(
			$file,
			json_encode(
				array(
					'domain'   => $domain,
					'locale'   => $locale,
					'language' => $config['locales'][ $locale ]['name'] ?? $locale,
					'register' => $config['locales'][ $locale ]['register'] ?? '',
					'nplurals' => $parsed['nplurals'],
					'batch'    => $n + 1,
					'of'       => count( $chunks ),
					'entries'  => $chunk,
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			) . "\n"
		);
	}
	printf( "%s %s: %d entries to do in %d batch file(s)\n", $domain, $locale, count( $items ), count( $chunks ) );
}
