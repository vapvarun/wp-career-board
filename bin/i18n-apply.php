<?php
/**
 * Apply a filled translation batch to its .po, refusing anything unsafe.
 *
 * A msgstr whose placeholders, {tokens}, HTML or brand names differ from the
 * English is not cosmetic: sprintf() drops or misplaces values, an email loses
 * the attendee's name, a link breaks. Every such entry is refused and reported;
 * nothing in a batch is written unless the whole batch is clean.
 *
 * Reviewed work is never overwritten: an entry that is already translated (and
 * not fuzzy) is left alone. Fuzzy entries are replaced and lose the flag.
 *
 * Accepts a batch from bin/i18n-todo.php ({"entries": [...]}) or a flat
 * {"msgid": "msgstr"} map (context entries keyed "context\u0004msgid", plurals
 * as an array of forms).
 *
 * Usage (from the plugin root; Pro runs ../wp-career-board/bin/i18n-apply.php):
 *   php bin/i18n-apply.php --json=<batch.json> [--po=languages/<file>.po] [--apply]
 *
 * @package WP_Career_Board
 */

require __DIR__ . '/lib/po.php';

$opt  = getopt( '', array( 'json:', 'po:', 'apply' ) );
$json = (string) ( $opt['json'] ?? '' );
$data = is_readable( $json ) ? json_decode( (string) file_get_contents( $json ), true ) : null;
if ( ! is_array( $data ) ) {
	fwrite( STDERR, "usage: php bin/i18n-apply.php --json=<batch.json> [--po=<file.po>] [--apply]  (unreadable or invalid JSON)\n" );
	exit( 2 );
}

$map = array();
if ( isset( $data['entries'] ) ) {
	foreach ( (array) $data['entries'] as $item ) {
		if ( isset( $item['key'], $item['msgstr'] ) ) {
			$map[ (string) $item['key'] ] = $item['msgstr'];
		}
	}
} else {
	$map = $data;
}

$po = (string) ( $opt['po'] ?? '' );
if ( '' === $po && isset( $data['domain'], $data['locale'] ) ) {
	$po = 'languages/' . $data['domain'] . '-' . $data['locale'] . '.po';
}
if ( ! is_writable( $po ) ) {
	fwrite( STDERR, "cannot write catalogue '{$po}' (pass --po or run from the plugin root)\n" );
	exit( 2 );
}

$protect = (array) ( wcb_po_config()['protect'] ?? array() );
$parsed  = wcb_po_read( $po );
$blocks  = $parsed['blocks'];
$applied = 0;
$kept    = 0;
$blank   = 0;
$refused = array();
$seen    = array();

foreach ( $blocks as $i => $entry ) {
	if ( ! wcb_po_is_message( $entry ) ) {
		continue;
	}
	$key = wcb_po_key( $entry );
	if ( ! array_key_exists( $key, $map ) ) {
		continue;
	}
	$seen[ $key ] = true;
	$new          = $map[ $key ];

	// Left blank in the batch: nothing to apply, not an error.
	if ( ( is_array( $new ) && '' === implode( '', $new ) ) || '' === $new ) {
		++$blank;
		continue;
	}
	if ( 'done' === wcb_po_state( $entry ) ) {
		++$kept;
		continue;
	}

	$label = ( null !== $entry['ctx'] ? '[' . $entry['ctx'] . '] ' : '' ) . $entry['id'];
	if ( null !== $entry['plural'] ) {
		if ( ! is_array( $new ) || count( $new ) !== $parsed['nplurals'] ) {
			$refused[] = sprintf( '"%s": plural entry needs %d forms as an array', $label, $parsed['nplurals'] );
			continue;
		}
		$problems = array();
		foreach ( array_values( $new ) as $n => $form ) {
			// Every form keeps every placeholder: in fr_FR and pt_BR form 0 also
			// covers n = 0, so a singular that dropped %d would read "one" for none.
			$source   = 0 === $n ? $entry['id'] : $entry['plural'];
			$problems = array_merge( $problems, wcb_po_problems( $source, (string) $form, $protect ) );
		}
	} else {
		if ( is_array( $new ) ) {
			$refused[] = sprintf( '"%s": not a plural entry, give one string', $label );
			continue;
		}
		$problems = wcb_po_problems( (string) $entry['id'], (string) $new, $protect );
	}
	if ( $problems ) {
		$refused[] = sprintf( '"%s": %s', $label, implode( '; ', array_unique( $problems ) ) );
		continue;
	}

	$blocks[ $i ]['raw'] = wcb_po_rewrite( $entry, $new );
	++$applied;
}

$unknown = array_diff_key( $map, $seen );
printf(
	"%s: %d to apply, %d already reviewed (left alone), %d left blank, %d refused, %d not in the catalogue%s\n",
	basename( $po ),
	$applied,
	$kept,
	$blank,
	count( $refused ),
	count( $unknown ),
	isset( $opt['apply'] ) ? '' : ' [dry run: add --apply to write]'
);
foreach ( $refused as $reason ) {
	echo '  REFUSED ' . $reason . "\n";
}
foreach ( array_slice( array_keys( $unknown ), 0, 5 ) as $key ) {
	echo '  not in catalogue (POT changed? re-export): "' . str_replace( "\4", ' | ', $key ) . "\"\n";
}

if ( $refused ) {
	echo "Nothing written: fix the refused entries and run again.\n";
	exit( 1 );
}
if ( isset( $opt['apply'] ) && $applied > 0 ) {
	wcb_po_write( $po, $blocks );
	echo "Written. Next: bash bin/make-translations.sh (compiles .mo, .l10n.php and JSON).\n";
}
