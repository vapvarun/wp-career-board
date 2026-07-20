<?php
/**
 * Release gate: a class deprecated with no remaining callers is dead weight.
 *
 * The "shipped forever, deprecated since inception, zero callers" class —
 * e.g. NotificationsEmail (@deprecated 1.0.0, never instantiated). Static
 * audits catch it, but only as an occasional sweep; this makes it a release
 * blocker so dead deprecated classes get removed instead of accreting.
 *
 * A @deprecated class WITH callers is fine (a live migration). This flags only
 * @deprecated classes that nothing references — the safe-to-delete ones.
 *
 * Usage:  php bin/check-dead-code.php [/abs/plugin/dir]
 * Exit:   0 = no dead deprecated classes; 1 = at least one.
 *
 * @package WP_Career_Board
 */

declare( strict_types=1 );

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : getcwd();

/**
 * PHP files under $root, excluding vendor/node_modules/dist/tests/bin/.git.
 *
 * @param string $root Plugin root.
 * @return array<int,string>
 */
function wcb_dc_php_files( string $root ): array {
	if ( ! is_dir( $root ) ) {
		return array();
	}
	$out = array();
	$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$path = $file->getPathname();
		if ( preg_match( '#/(vendor|node_modules|dist|tests|bin|\.git)/#', $path ) ) {
			continue;
		}
		if ( 'php' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			$out[] = $path;
		}
	}
	return $out;
}

$files = wcb_dc_php_files( $root );

// ── 1. Find @deprecated classes: a docblock containing @deprecated immediately
//        preceding a class/interface/trait declaration. ─────────────────────
$deprecated = array(); // class name => declaring file.

foreach ( $files as $file ) {
	$src = (string) file_get_contents( $file );
	if ( false === strpos( $src, '@deprecated' ) ) {
		continue;
	}
	// /** ... @deprecated ... */ <newline> [final|abstract] class|interface|trait Name
	if ( preg_match_all(
		'#/\*\*(?:(?!\*/).)*?@deprecated(?:(?!\*/).)*?\*/\s*(?:final\s+|abstract\s+)*(?:class|interface|trait)\s+([A-Za-z_][A-Za-z0-9_]*)#s',
		$src,
		$m,
		PREG_SET_ORDER
	) ) {
		foreach ( $m as $hit ) {
			$deprecated[ $hit[1] ] = $file;
		}
	}
}

if ( empty( $deprecated ) ) {
	fwrite( STDOUT, "check-dead-code OK: no @deprecated classes.\n" );
	exit( 0 );
}

// ── 2. For each, is it referenced anywhere but its own file? ─────────────────
$dead = array();

foreach ( $deprecated as $class => $decl_file ) {
	$referenced = false;
	foreach ( $files as $file ) {
		if ( $file === $decl_file ) {
			continue; // Its own declaration doesn't count as a caller.
		}
		$src = (string) file_get_contents( $file );
		// new X | X:: | extends X | implements X | instanceof X | \Ns\X | use ...X;
		if ( preg_match( '/\b(?:new\s+|extends\s+|implements\s+|instanceof\s+)' . preg_quote( $class, '/' ) . '\b/', $src )
			|| preg_match( '/\b' . preg_quote( $class, '/' ) . '::/', $src )
			|| preg_match( '/\\\\' . preg_quote( $class, '/' ) . '\b/', $src ) ) {
			$referenced = true;
			break;
		}
	}
	if ( ! $referenced ) {
		$dead[ $class ] = str_replace( $root . '/', '', $decl_file );
	}
}

// ── 3. Report ────────────────────────────────────────────────────────────────
if ( empty( $dead ) ) {
	$n = count( $deprecated );
	fwrite( STDOUT, "check-dead-code OK: {$n} @deprecated class(es), all still referenced (live migration).\n" );
	exit( 0 );
}

fwrite( STDERR, "check-dead-code FAILED — @deprecated class(es) with zero callers (dead weight, remove them):\n" );
foreach ( $dead as $class => $file ) {
	fwrite( STDERR, "  - {$class}   ({$file})\n" );
}
fwrite( STDERR, "\nDelete the class file. If it must ship for backward-compat despite no internal\n" );
fwrite( STDERR, "caller, add a one-line note here and to the class docblock explaining why.\n" );
exit( 1 );
