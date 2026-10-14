<?php
/**
 * Minimal gettext .po reader/writer shared by the i18n scripts (Free and Pro).
 *
 * Reads every entry with its context, plural, flags and comments, and writes a
 * catalogue back byte-for-byte except for the entries a script changed. No
 * WordPress needed: the scripts run from a plain shell.
 *
 * Usage: require __DIR__ . '/lib/po.php';
 *
 * @package WP_Career_Board
 */

/**
 * Parse a .po file into header text and entries.
 *
 * Each entry: raw (original block), ctx, id, plural, str (string|string[]),
 * flags (string[]), comment (translator #. lines), refs (#: lines).
 *
 * @param string $path File.
 * @return array{blocks: array<int,array<string,mixed>>, nplurals: int}
 */
function wcb_po_read( string $path ): array {
	$text   = str_replace( "\r\n", "\n", (string) file_get_contents( $path ) );
	$blocks = array();
	foreach ( preg_split( "/\n{2,}/", trim( $text, "\n" ) ) as $raw ) {
		$entry = array(
			'raw'     => $raw,
			'ctx'     => null,
			'id'      => null,
			'plural'  => null,
			'str'     => null,
			'flags'   => array(),
			'comment' => array(),
			'refs'    => array(),
			'obsolete' => 0 === strpos( ltrim( $raw ), '#~' ),
		);
		$field = null;
		$index = null;
		foreach ( explode( "\n", $raw ) as $line ) {
			if ( preg_match( '/^#,\s*(.*)$/', $line, $m ) ) {
				$entry['flags'] = array_map( 'trim', explode( ',', $m[1] ) );
			} elseif ( preg_match( '/^#\.\s?(.*)$/', $line, $m ) ) {
				$entry['comment'][] = $m[1];
			} elseif ( preg_match( '/^#:\s*(.*)$/', $line, $m ) ) {
				$entry['refs'][] = $m[1];
			} elseif ( preg_match( '/^(msgctxt|msgid|msgid_plural) "(.*)"$/', $line, $m ) ) {
				$field = array( 'msgctxt' => 'ctx', 'msgid' => 'id', 'msgid_plural' => 'plural' )[ $m[1] ];
				$index = null;
				$entry[ $field ] = wcb_po_unescape( $m[2] );
			} elseif ( preg_match( '/^msgstr "(.*)"$/', $line, $m ) ) {
				$field        = 'str';
				$index        = null;
				$entry['str'] = wcb_po_unescape( $m[1] );
			} elseif ( preg_match( '/^msgstr\[(\d+)\] "(.*)"$/', $line, $m ) ) {
				$field = 'str';
				$index = (int) $m[1];
				if ( ! is_array( $entry['str'] ) ) {
					$entry['str'] = array();
				}
				$entry['str'][ $index ] = wcb_po_unescape( $m[2] );
			} elseif ( preg_match( '/^"(.*)"$/', $line, $m ) && null !== $field ) {
				$more = wcb_po_unescape( $m[1] );
				if ( 'str' === $field && null !== $index ) {
					$entry['str'][ $index ] .= $more;
				} else {
					$entry[ $field ] .= $more;
				}
			}
		}
		$blocks[] = $entry;
	}

	$nplurals = 2;
	if ( isset( $blocks[0] ) && '' === $blocks[0]['id'] && preg_match( '/nplurals\s*=\s*(\d+)/', (string) $blocks[0]['str'], $m ) ) {
		$nplurals = (int) $m[1];
	}
	return array(
		'blocks'   => $blocks,
		'nplurals' => $nplurals,
	);
}

/**
 * The key a translation batch uses for an entry: context + EOT + msgid, the
 * same convention gettext uses inside a .mo.
 *
 * @param array $entry Parsed entry.
 * @return string
 */
function wcb_po_key( array $entry ): string {
	return ( null !== $entry['ctx'] ? $entry['ctx'] . "\4" : '' ) . (string) $entry['id'];
}

/**
 * Whether an entry is a real, translatable message (not the header, not obsolete).
 *
 * @param array $entry Parsed entry.
 * @return bool
 */
function wcb_po_is_message( array $entry ): bool {
	return ! $entry['obsolete'] && null !== $entry['id'] && '' !== $entry['id'];
}

/**
 * Translation state: 'done', 'fuzzy' or 'empty'.
 *
 * @param array $entry Parsed entry.
 * @return string
 */
function wcb_po_state( array $entry ): string {
	$strings = is_array( $entry['str'] ) ? $entry['str'] : array( (string) $entry['str'] );
	if ( in_array( '', $strings, true ) || array() === $strings ) {
		return 'empty';
	}
	return in_array( 'fuzzy', $entry['flags'], true ) ? 'fuzzy' : 'done';
}

/**
 * Rewrite one block with new translation(s): replaces the msgstr lines, drops
 * the fuzzy flag and the #| previous-msgid lines, keeps everything else.
 *
 * @param array           $entry Parsed entry.
 * @param string|string[] $str   New msgstr, or one string per plural form.
 * @return string New raw block.
 */
function wcb_po_rewrite( array $entry, $str ): string {
	$out    = array();
	$in_str = false; // Inside a msgstr and its "..." continuation lines.
	foreach ( explode( "\n", $entry['raw'] ) as $line ) {
		$continuation = 0 === strpos( $line, '"' );
		if ( preg_match( '/^msgstr(\[\d+\])? "/', $line ) ) {
			$in_str = true;
			continue;
		}
		if ( $continuation && $in_str ) {
			continue;
		}
		if ( ! $continuation ) {
			$in_str = false;
		}
		if ( preg_match( '/^#\|/', $line ) ) {
			continue;
		}
		if ( preg_match( '/^#,\s*(.*)$/', $line, $m ) ) {
			$flags = array_filter( array_map( 'trim', explode( ',', $m[1] ) ), static fn( $f ) => '' !== $f && 'fuzzy' !== $f );
			if ( $flags ) {
				$out[] = '#, ' . implode( ', ', $flags );
			}
			continue;
		}
		$out[] = $line;
	}
	if ( is_array( $str ) ) {
		foreach ( array_values( $str ) as $i => $form ) {
			$out[] = 'msgstr[' . $i . '] "' . wcb_po_escape( $form ) . '"';
		}
	} else {
		$out[] = 'msgstr "' . wcb_po_escape( $str ) . '"';
	}
	return implode( "\n", $out );
}

/**
 * Write blocks back to a file.
 *
 * @param string $path   File.
 * @param array  $blocks Parsed entries (their raw text is what gets written).
 * @return void
 */
function wcb_po_write( string $path, array $blocks ): void {
	file_put_contents( $path, implode( "\n\n", array_column( $blocks, 'raw' ) ) . "\n" );
}

/**
 * Unescape .po text using ONLY the escapes every gettext compiler agrees on
 * (\n \t \r \" \\). stripcslashes() also decoded \x3c and octal, which a
 * compiler may not: the checker would judge different text from what ships.
 * Any other backslash sequence is left as is and reported by wcb_po_problems().
 *
 * @param string $text Escaped .po text.
 */
function wcb_po_unescape( string $text ): string {
	return preg_replace_callback(
		'/\\\\(.)/s',
		static function ( array $m ): string {
			$map = array(
				'n'  => "\n",
				't'  => "\t",
				'r'  => "\r",
				'"'  => '"',
				'\\' => '\\',
			);
			return $map[ $m[1] ] ?? '\\' . $m[1];
		},
		$text
	);
}

/** @param string $text Plain text. */
function wcb_po_escape( string $text ): string {
	return strtr(
		$text,
		array(
			'\\' => '\\\\',
			'"'  => '\\"',
			"\n" => '\\n',
			"\t" => '\\t',
			"\r" => '\\r',
		)
	);
}

/**
 * What a translation must carry over unchanged from the English: printf
 * placeholders, {tokens}, HTML tags and protected terms. Returns a list of
 * problems (empty = safe to ship).
 *
 * @param string   $source    English (msgid or msgid_plural).
 * @param string   $target    Translation.
 * @param string[] $protected Terms that must appear unchanged when the source has them.
 * @return string[]
 */
function wcb_po_problems( string $source, string $target, array $protected = array() ): array {
	$problems = array();
	if ( '' === trim( $target ) ) {
		return array( 'empty translation' );
	}
	// %NAME% tokens are filled by JS .replace(), not printf: compare them as
	// tokens and keep them out of the printf scan ("%JOBS% sample" is not "% s").
	$named  = static function ( string $s ): array {
		preg_match_all( '/%[A-Z][A-Z0-9_]*%/', $s, $m );
		sort( $m[0] );
		return $m[0];
	};
	if ( $named( $source ) !== $named( $target ) ) {
		$problems[] = '%TOKENS% differ (' . implode( ' ', $named( $source ) ) . ' vs ' . implode( ' ', $named( $target ) ) . ')';
	}
	$source_printf = preg_replace( '/%[A-Z][A-Z0-9_]*%/', '', $source );
	$target_printf = preg_replace( '/%[A-Z][A-Z0-9_]*%/', '', $target );
	$printf = static function ( string $s ): array {
		preg_match_all( '/%(?:\d+\$)?[-+ 0#]*\d*(?:\.\d+)?[bcdeEfFgGosuxX%]/', $s, $m );
		$m = array_values( array_filter( $m[0], static fn( $p ) => '%%' !== $p ) );
		// Numbered and plain forms are equivalent when the order is explicit.
		$m = array_map( static fn( $p ) => preg_replace( '/^%\d+\$/', '%', $p ), $m );
		sort( $m );
		return $m;
	};
	$numbered = static function ( string $s ): array {
		preg_match_all( '/%(\d+)\$/', $s, $m );
		$m = array_unique( $m[1] );
		sort( $m );
		return $m;
	};
	if ( $printf( $source_printf ) !== $printf( $target_printf ) ) {
		$problems[] = 'printf placeholders differ (' . implode( ' ', $printf( $source_printf ) ) . ' vs ' . implode( ' ', $printf( $target_printf ) ) . ')';
	} elseif ( $numbered( $source ) && $numbered( $source ) !== $numbered( $target ) ) {
		$problems[] = 'numbered placeholders differ: keep %1$s, %2$s ... exactly';
	}
	$tokens = static function ( string $s ): array {
		preg_match_all( '/\{[a-z0-9_]+\}/', $s, $m );
		sort( $m[0] );
		return $m[0];
	};
	if ( $tokens( $source ) !== $tokens( $target ) ) {
		$problems[] = '{tokens} differ (' . implode( ' ', $tokens( $source ) ) . ' vs ' . implode( ' ', $tokens( $target ) ) . ')';
	}
	$tags = static function ( string $s ): array {
		preg_match_all( '#</?([a-z][a-z0-9]*)\b[^>]*>#i', $s, $m );
		$m = array_map( 'strtolower', $m[0] );
		$m = array_map( static fn( $t ) => preg_replace( '/\s+/', ' ', $t ), $m );
		sort( $m );
		return $m;
	};
	if ( $tags( $source ) !== $tags( $target ) ) {
		$problems[] = 'HTML tags differ: keep every tag and attribute exactly';
	}
	// A malformed or unclosed tag ("<img src=x onerror=...") is not a tag to
	// the pattern above, so angle brackets and script-ish attributes must also
	// match the English one for one.
	foreach ( array( '<', '>' ) as $bracket ) {
		if ( substr_count( $source, $bracket ) !== substr_count( $target, $bracket ) ) {
			$problems[] = 'angle brackets differ: a translation cannot add or remove "' . $bracket . '"';
			break;
		}
	}
	$risky = static function ( string $s ): int {
		return preg_match_all( '/\bon[a-z]+\s*=|javascript:|data:text\/html|<\s*(script|iframe|object|embed|style)\b/i', $s );
	};
	if ( $risky( $target ) > $risky( $source ) ) {
		$problems[] = 'script, event-handler or javascript: content that the English does not have';
	}
	// Escapes outside \n \t \r \" \\ survive unescaping; refuse them so the
	// checked text is exactly the shipped text.
	if ( preg_match( '/\\\\[^nrt"\\\\]/', $target ) && ! preg_match( '/\\\\[^nrt"\\\\]/', $source ) ) {
		$problems[] = 'unsupported backslash escape (use the character itself)';
	}
	foreach ( $protected as $term ) {
		// Whole words only: "Pro" must not match inside "Protocol".
		$word = '/(?<![\\p{L}\\p{N}])' . preg_quote( $term, '/' ) . '(?![\\p{L}\\p{N}])/u';
		if ( preg_match( $word, $source ) && ! preg_match( $word, $target ) ) {
			$problems[] = 'protected term "' . $term . '" must stay unchanged';
		}
	}
	return $problems;
}

/**
 * The project i18n config (protected terms, glossary, locales).
 *
 * @return array
 */
function wcb_po_config(): array {
	// The plugin being worked on first (Pro runs these scripts from Free), then Free's.
	foreach ( array( getcwd() . '/languages/i18n-config.json', dirname( __DIR__, 2 ) . '/languages/i18n-config.json' ) as $file ) {
		$data = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
		if ( is_array( $data ) ) {
			return $data;
		}
	}
	return array( 'protect' => array() );
}
