<?php
/**
 * Fail when the POT carries near-duplicate strings: the same words differing
 * only in letter case, ASCII "..." versus "…", spacing, or a trailing period.
 * Each twin is one more entry for every translator in every locale, and the
 * two copies drift apart once translated.
 *
 * Variants that do a different job are allowed, because a locale may need to
 * render them differently:
 *   - a trailing ":" (a field label; French puts a space before it),
 *   - a trailing "?" (a confirmation question, not the button),
 *   - a trailing "…" (a placeholder or "in progress" state, not the label),
 *   - a single lowercase word (an entity name used inside a sentence).
 * Anything else in a group fails. Context (msgctxt) entries are separate on
 * purpose and are skipped. ASCII "..." always fails: use "…".
 *
 * Usage (from the plugin root; Pro runs ../wp-career-board/bin/i18n-dupes.php):
 *   php bin/i18n-dupes.php
 *
 * @package WP_Career_Board
 */

require __DIR__ . '/lib/po.php';

$domain = basename( getcwd() );
$pot    = 'languages/' . $domain . '.pot';
if ( ! is_readable( $pot ) ) {
	fwrite( STDERR, "missing {$pot} (run from the plugin root)\n" );
	exit( 2 );
}

$norm = static function ( string $s ): string {
	$s = str_replace( '...', '…', $s );
	$s = preg_replace( '/\s+/u', ' ', trim( $s ) );
	$s = mb_strtolower( (string) $s );
	return (string) preg_replace( '/[.]+$/u', '', $s );
};
// The job a string does, read from its ending: a different job is a different string.
$role = static function ( string $s ): string {
	$s = rtrim( str_replace( '...', '…', $s ) );
	if ( preg_match( '/[:?…]$/u', $s, $m ) ) {
		return $m[0];
	}
	if ( ! str_contains( $s, ' ' ) && mb_strtolower( $s ) === $s ) {
		return 'word';
	}
	return '';
};

$groups = array();
$ascii  = array();
foreach ( wcb_po_read( $pot )['blocks'] as $entry ) {
	if ( ! wcb_po_is_message( $entry ) || null !== $entry['ctx'] ) {
		continue;
	}
	$id = (string) $entry['id'];
	if ( str_contains( $id, '...' ) ) {
		$ascii[] = $id;
	}
	$key                                    = rtrim( $norm( $id ), ':?…' ) . '|' . $role( $id );
	$groups[ $key ][ $id ]                  = true;
}

$fail = array();
foreach ( $groups as $ids ) {
	if ( count( $ids ) > 1 ) {
		$fail[] = implode( ' | ', array_keys( $ids ) );
	}
}

foreach ( $ascii as $id ) {
	echo "  ASCII ... (use …): {$id}\n";
}
foreach ( $fail as $line ) {
	echo "  NEAR-DUPLICATE: {$line}\n";
}
if ( $ascii || $fail ) {
	printf( "%s: %d near-duplicate group(s), %d ASCII ellipsis string(s). Keep one wording (sentence case) and reuse it.\n", $domain, count( $fail ), count( $ascii ) );
	exit( 1 );
}
echo "{$domain}: no near-duplicate strings.\n";
