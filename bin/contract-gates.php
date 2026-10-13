<?php
/**
 * Contract gates: classes of bug that WPCS, PHPStan and grep rules miss.
 *
 * Each gate exists because a real 1.8.0 bug shipped through every other check:
 *
 *   A. `this.` inside an Interactivity store action (actions are not bound,
 *      so the call throws): "Request data export" did nothing.
 *   B. A redirect or an action handler reached from an admin list screen's
 *      render(): headers were already sent ("Headers already sent" on
 *      flag dismiss and companies bulk trash).
 *   C. An empty catch after a fetch in a view module: Save Job and Copy Link
 *      failed with no message. Mark a deliberate one `wcb-allow-silent: why`.
 *   D. A wcb-* class in a template that no stylesheet styles (the Delete
 *      Account form had none). Known gaps live in a baseline; new ones fail.
 *   F. A bundled EDD SL SDK that is incomplete or carries a bug fixed in the
 *      reference copy (BuddyNext's libs/edd-sl-sdk): unguarded $requires
 *      reads warned on Updates, a DOCUMENT_ROOT asset URL 404ed on live hosts.
 *   E. A job meta key registered in JobsMeta with no admin editor field
 *      (Apply URL/Email could only be set from the frontend). Keys owned by
 *      another screen are listed in $e_owned_elsewhere with the reason.
 *
 * Usage: php bin/contract-gates.php [--root=<plugin dir>] [--update-baseline]
 *        (Pro runs Free's copy with --root=. ; gates whose files a plugin
 *        does not have are skipped.)
 * Exit:  0 pass, 1 at least one gate failed.
 *
 * @package WP_Career_Board
 * @since   1.8.1
 */

declare( strict_types=1 );

$root = dirname( __DIR__ );
foreach ( $argv as $arg ) {
	if ( str_starts_with( $arg, '--root=' ) ) {
		$root = (string) realpath( substr( $arg, 7 ) );
	}
}
$update   = in_array( '--update-baseline', $argv, true );
$failures = array();
$skipped  = array();

/**
 * Files under $dir matching $pattern (no vendor/node_modules/dist/build/tests).
 *
 * @param string $dir     Directory relative to the plugin root.
 * @param string $pattern Regex on the relative path.
 * @return string[] Relative paths.
 */
$files = static function ( string $dir, string $pattern ) use ( $root ): array {
	$out = array();
	if ( ! is_dir( $root . '/' . $dir ) ) {
		return $out;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$rel = substr( $file->getPathname(), strlen( $root ) + 1 );
		if ( preg_match( '#(^|/)(vendor|node_modules|dist|build|tests)/#', $rel ) || ! preg_match( $pattern, $rel ) ) {
			continue;
		}
		$out[] = $rel;
	}
	sort( $out );
	return $out;
};

$line_of = static fn ( string $src, int $offset ): int => substr_count( substr( $src, 0, $offset ), "\n" ) + 1;

// A. `this.` in Interactivity view modules.
foreach ( $files( 'blocks', '#/view\.js$#' ) as $rel ) {
	$src = (string) file_get_contents( $root . '/' . $rel );
	if ( preg_match_all( '#^(?![\s]*(?://|\*)).*\bthis\.[A-Za-z_]+#m', $src, $m, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $m[0] as $hit ) {
			$failures['A'][] = $rel . ':' . $line_of( $src, $hit[1] ) . '  ' . trim( $hit[0] );
		}
	}
}

// B. Redirects / handlers reached from an admin list render().
foreach ( $files( 'admin', '#^admin/class-admin-[a-z-]+\.php$#' ) as $rel ) {
	$src = (string) file_get_contents( $root . '/' . $rel );
	if ( ! preg_match( '#public function render\(\)[^{]*\{#', $src, $m, PREG_OFFSET_CAPTURE ) ) {
		continue;
	}
	$start = $m[0][1];
	$next  = preg_match( '#\n\t(?:public|protected|private)(?: static)? function #', $src, $n, PREG_OFFSET_CAPTURE, $start + 10 ) ? $n[0][1] : strlen( $src );
	$body  = substr( $src, $start, $next - $start );
	if ( preg_match_all( '#wp_safe_redirect|wp_redirect|\$this->process_[a-z_]+\(#', $body, $hits, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $hits[0] as $hit ) {
			$failures['B'][] = $rel . ':' . $line_of( $src, $start + $hit[1] ) . '  ' . $hit[0] . ' inside render() - hook it on load-{page}';
		}
	}
}

// C. Empty catch after a fetch.
$js = array_merge( $files( 'blocks', '#/view\.js$#' ), $files( 'assets/js', '#\.js$#' ) );
foreach ( $js as $rel ) {
	if ( str_ends_with( $rel, '.min.js' ) ) {
		continue;
	}
	$src = (string) file_get_contents( $root . '/' . $rel );
	if ( preg_match_all( '#\bcatch\s*(?:\([^)]*\))?\s*\{(\s*(?://[^\n]*\n\s*)*)\}#', $src, $m, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $m[0] as $i => $hit ) {
			if ( str_contains( $m[1][ $i ][0], 'wcb-allow-silent' ) ) {
				continue;
			}
			// The try this catch closes: look back to the nearest `try {`.
			$try = strrpos( substr( $src, 0, $hit[1] ), 'try' );
			if ( false !== $try && preg_match( '#\b(?:wcbFetch|fetch|apiFetch)\s*\(#', substr( $src, $try, $hit[1] - $try ) ) ) {
				$failures['C'][] = $rel . ':' . $line_of( $src, $hit[1] ) . '  empty catch after a request - tell the user, or mark `// wcb-allow-silent: why`';
			}
		}
	}
}

// D. Unstyled wcb-* classes in templates.
$css = '';
foreach ( array_merge( $files( 'assets/css', '#\.css$#' ), $files( 'blocks', '#\.css$#' ) ) as $rel ) {
	if ( ! preg_match( '#(-rtl|\.min)\.css$#', $rel ) ) {
		$css .= file_get_contents( $root . '/' . $rel );
	}
}
// Pro renders Free's components, so Free's stylesheets count as styled too.
$free = dirname( $root ) . '/wp-career-board';
if ( realpath( $free ) !== $root && is_dir( $free . '/assets/css' ) ) {
	foreach ( (array) glob( $free . '/{assets/css,blocks/*}/*.css', GLOB_BRACE ) as $path ) {
		if ( ! preg_match( '#(-rtl|\.min)\.css$#', (string) $path ) ) {
			$css .= file_get_contents( (string) $path );
		}
	}
}
preg_match_all( '#\.(wcb-[a-z0-9_-]+)#', $css, $m );
$styled = array_flip( $m[1] );
$used   = array();
foreach ( array_merge( $files( 'blocks', '#/render\.php$#' ), $files( 'templates', '#\.php$#' ) ) as $rel ) {
	$src = (string) file_get_contents( $root . '/' . $rel );
	if ( preg_match_all( '#class="([^"<]*)"#', $src, $cm ) ) {
		foreach ( $cm[1] as $attr ) {
			foreach ( preg_split( '#\s+#', (string) preg_replace( '#<\?php.*?\?>#s', ' ', $attr ) ) as $cls ) {
				if ( preg_match( '#^wcb-[a-z0-9_-]+$#', $cls ) && ! isset( $styled[ $cls ] ) ) {
					$used[ $cls ] = $rel;
				}
			}
		}
	}
}
ksort( $used );
$baseline_file = $root . '/audit/unstyled-classes-baseline.json';
if ( $update ) {
	file_put_contents( $baseline_file, json_encode( array_keys( $used ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
	echo 'Baseline written: ' . count( $used ) . " classes (hooks for JS, or gaps to style later).\n";
}
$baseline = is_file( $baseline_file ) ? (array) json_decode( (string) file_get_contents( $baseline_file ), true ) : array();
foreach ( array_diff( array_keys( $used ), $baseline ) as $cls ) {
	$failures['D'][] = $used[ $cls ] . '  .' . $cls . ' has no CSS rule - style it, or (JS hook only) add it with --update-baseline';
}

// E. Every registered job meta key is editable in the admin job editor.
$e_owned_elsewhere = array(
	'_wcb_board_id' => 'board is chosen on the Boards meta box (Pro) / job form',
);
$meta_src  = is_file( $root . '/modules/jobs/class-jobs-meta.php' ) ? (string) file_get_contents( $root . '/modules/jobs/class-jobs-meta.php' ) : '';
$admin_src = is_file( $root . '/admin/class-admin-meta-boxes.php' ) ? (string) file_get_contents( $root . '/admin/class-admin-meta-boxes.php' ) : '';
if ( '' === $meta_src ) {
	$skipped[] = 'E';
} elseif ( preg_match( '#\$meta_fields\s*=\s*array\((.*?)\n\t\t\);#s', $meta_src, $mm ) && preg_match_all( "#'(_wcb_[a-z_]+)'\s*=>#", $mm[1], $keys ) ) {
	foreach ( $keys[1] as $key ) {
		$field = 'wcb_' . substr( $key, 5 );
		if ( isset( $e_owned_elsewhere[ $key ] ) || str_contains( $admin_src, "'" . $key . "'" ) || str_contains( $admin_src, 'name="' . $field . '"' ) || str_contains( $admin_src, "'" . substr( $key, 5 ) . "'" ) ) {
			continue;
		}
		$failures['E'][] = 'modules/jobs/class-jobs-meta.php  ' . $key . ' is registered but the admin job editor has no field for it';
	}
} else {
	$failures['E'][] = 'could not read the $meta_fields map in modules/jobs/class-jobs-meta.php';
}

// F. The bundled EDD SL SDK is complete and free of known-fixed bugs.
$sdk = $root . '/libs/edd-sl-sdk';
if ( ! is_dir( $sdk ) ) {
	$skipped[] = 'F';
} else {
	foreach ( array( 'edd-sl-sdk.php', 'src/Registry.php', 'src/Versions.php', 'src/Updaters/Plugin.php', 'src/Utilities/Path.php' ) as $need ) {
		if ( ! is_file( $sdk . '/' . $need ) ) {
			$failures['F'][] = 'libs/edd-sl-sdk/' . $need . ' is missing - re-bundle the SDK from the reference copy';
		}
	}
	$plugin_src = is_file( $sdk . '/src/Updaters/Plugin.php' ) ? (string) file_get_contents( $sdk . '/src/Updaters/Plugin.php' ) : '';
	if ( preg_match( '#\$version_info->(requires|requires_php|icons|banners)\s*\)?\s*;#', $plugin_src ) && preg_match( '#->requires(_php)?\s*=\s*\$version_info->requires(_php)?\s*;#', $plugin_src ) ) {
		$failures['F'][] = 'libs/edd-sl-sdk/src/Updaters/Plugin.php reads $version_info->requires without ?? - re-bundle from the reference copy';
	}
	$path_src = is_file( $sdk . '/src/Utilities/Path.php' ) ? (string) file_get_contents( $sdk . '/src/Utilities/Path.php' ) : '';
	if ( str_contains( $path_src, 'DOCUMENT_ROOT' ) && ! str_contains( $path_src, 'plugins_url(' ) ) {
		$failures['F'][] = 'libs/edd-sl-sdk/src/Utilities/Path.php builds asset URLs from DOCUMENT_ROOT - re-bundle from the reference copy';
	}
}

$names = array(
	'A' => 'no `this.` in Interactivity store actions',
	'B' => 'no redirect or action handler inside an admin render()',
	'C' => 'no silent catch after a request',
	'D' => 'every wcb-* template class is styled (or baselined)',
	'E' => 'every registered job meta key has an admin editor field',
	'F' => 'bundled EDD SL SDK is complete and has no known-fixed bug',
);
foreach ( $names as $gate => $label ) {
	if ( in_array( $gate, $skipped, true ) ) {
		echo "  --  Gate {$gate}: {$label} (not in this plugin)\n";
		continue;
	}
	if ( empty( $failures[ $gate ] ) ) {
		echo "  ok  Gate {$gate}: {$label}\n";
		continue;
	}
	echo "  FAIL Gate {$gate}: {$label}\n";
	foreach ( $failures[ $gate ] as $line ) {
		echo "       {$line}\n";
	}
}
exit( $failures ? 1 : 0 );
