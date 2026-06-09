<?php
/**
 * check-class-paths.php — release-integrity gate.
 *
 * Catches the class of bug that ships a working dev tree but fatals (or
 * silently skips a module) on a packaged release:
 *
 *   A. AUTOLOAD MISMATCH — a class-*.php whose namespace + class name do not
 *      resolve back to its own path under the plugin's PSR-ish autoloader
 *      (the autoloader lowercases namespace dir segments but does NOT
 *      kebab-case them, so a hyphenated dir like modules/theme-integration/
 *      can never be loaded). The class_exists() boot guards then silently
 *      skip the module.
 *
 *   B. STRIPPED CLASS — shipped (non-excluded) code references a WCB class
 *      whose resolved file lives in a directory that .distignore removes from
 *      the release (e.g. /import). Works in dev, "class not found" in release.
 *
 * Pure PHP, no dependencies. Exit 0 = clean, 1 = at least one problem.
 *
 * @package WP_Career_Board
 */

declare( strict_types=1 );

$root = dirname( __DIR__ );

// Detect the autoloader namespace prefix the plugin actually uses (Free: WCB\,
// Pro: WCB\Pro\) by reading the main plugin file's spl_autoload_register guard.
$main_php = '';
foreach ( glob( $root . '/*.php' ) as $f ) {
	if ( false !== strpos( (string) file_get_contents( $f ), 'spl_autoload_register' ) ) {
		$main_php = (string) file_get_contents( $f );
		break;
	}
}
$prefix = ( false !== strpos( $main_php, "'WCB\\\\Pro\\\\'" ) ) ? 'WCB\\Pro\\' : 'WCB\\';

// Replicate the autoloader's file-path computation for a FQCN.
$resolve = static function ( string $fqcn ) use ( $prefix ): ?string {
	if ( 0 !== strpos( $fqcn, $prefix ) ) {
		return null; // not autoloaded by this plugin (third-party, e.g. \Wbcom\Credits).
	}
	$relative   = str_replace( array( $prefix, '\\' ), array( '', '/' ), $fqcn );
	$parts      = explode( '/', $relative );
	$class_name = (string) array_pop( $parts );
	$filename   = 'class-' . strtolower( (string) preg_replace( '/([A-Z])/', '-$1', lcfirst( $class_name ) ) ) . '.php';
	$dir        = implode( '/', array_map( 'strtolower', $parts ) );
	return ( '' !== $dir ? $dir . '/' : '' ) . $filename;
};

// Top-level dirs the release strips (from .distignore, anchored entries).
$excluded = array();
if ( is_file( $root . '/.distignore' ) ) {
	foreach ( file( $root . '/.distignore', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
		$line = trim( $line );
		if ( '' === $line || '#' === $line[0] || '/' !== $line[0] ) {
			continue;
		}
		$excluded[] = trim( $line, '/' );
	}
}
$is_excluded = static function ( string $relpath ) use ( $excluded ): bool {
	$top = explode( '/', $relpath )[0];
	return in_array( $top, $excluded, true );
};

// Walk all PHP files under shipped (non-excluded) dirs.
$skip_walk = array( 'vendor', 'node_modules', 'libs', '.git' );
$php_files = array();
$it        = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file ) {
	if ( 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}
	$rel = ltrim( str_replace( $root, '', $file->getPathname() ), '/\\' );
	$rel = str_replace( '\\', '/', $rel );
	$top = explode( '/', $rel )[0];
	if ( in_array( $top, $skip_walk, true ) || in_array( $top, $excluded, true ) ) {
		continue;
	}
	$php_files[ $rel ] = $file->getPathname();
}

$errors = array();

// ── Check A: every class-*.php resolves to its own path ──────────────────────
foreach ( $php_files as $rel => $abs ) {
	if ( 0 !== strpos( basename( $rel ), 'class-' ) ) {
		continue;
	}
	$src = (string) file_get_contents( $abs );
	if ( ! preg_match( '/^namespace\s+([A-Za-z0-9_\\\\]+)\s*;/m', $src, $nm ) ) {
		continue;
	}
	// Anchored declaration only (skip "class" words inside comments/strings).
	if ( ! preg_match( '/^\s*(?:final\s+|abstract\s+)?(?:class|interface|trait)\s+([A-Za-z0-9_]+)/m', $src, $cm ) ) {
		continue;
	}
	$fqcn = $nm[1] . '\\' . $cm[1];
	$want = $resolve( $fqcn );
	if ( null === $want ) {
		continue;
	}
	if ( $want !== $rel ) {
		$errors[] = "AUTOLOAD MISMATCH: {$fqcn}\n    file:     {$rel}\n    autoload expects: {$want}  (will fail class_exists / fatal)";
	}
}

// ── Check B: no shipped reference resolves into a stripped dir ────────────────
$ref_re = '/(?:use|new|extends|implements)\s+\\\\?(' . preg_quote( $prefix, '/' ) . '[A-Za-z0-9_\\\\]+)|\\\\?(' . preg_quote( $prefix, '/' ) . '[A-Za-z0-9_\\\\]+)::/';
$seen   = array();
foreach ( $php_files as $rel => $abs ) {
	if ( preg_match_all( $ref_re, (string) file_get_contents( $abs ), $ms, PREG_SET_ORDER ) ) {
		foreach ( $ms as $m ) {
			$fqcn = rtrim( $m[1] ?: ( $m[2] ?? '' ), '\\' );
			if ( '' === $fqcn || isset( $seen[ $fqcn ] ) ) {
				continue;
			}
			$seen[ $fqcn ] = true;
			$want          = $resolve( $fqcn );
			if ( null !== $want && $is_excluded( $want ) ) {
				$errors[] = "STRIPPED: {$fqcn}\n    referenced in: {$rel}\n    resolves to:   {$want}  (excluded by .distignore -> 'class not found' in release)";
			}
		}
	}
}

if ( $errors ) {
	fwrite( STDERR, "release-integrity: " . count( $errors ) . " problem(s)\n\n" );
	fwrite( STDERR, implode( "\n\n", $errors ) . "\n" );
	exit( 1 );
}
echo "release-integrity: OK (all WCB classes autoload + ship)\n";
exit( 0 );
