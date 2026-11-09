<?php
/**
 * Fails on a raw date formatter outside core/class-time.php.
 *
 * wp_date(), date_i18n(), mysql2date() and get_date_from_gmt() each pick their
 * own timezone, so one moment prints differently on two screens. Show a time
 * with \WCB\Core\Time::date_label() (see docs/standards/time.md). A call that is
 * correct on purpose carries a `time-ok:` comment on its line.
 *
 * Run: php bin/check-time-formatter.php [plugin-dir ...]   (default: this plugin)
 * Pro: php ../wp-career-board/bin/check-time-formatter.php .
 *
 * @package WP_Career_Board
 */

$roots   = array_slice( $argv, 1 ) ?: array( dirname( __DIR__ ) );
$skip    = '#/(vendor|node_modules|libs|tests|dist|build|bin|languages|\.git)/#';
$pattern = '/(?<![\w>:$])(wp_date|date_i18n|mysql2date|get_date_from_gmt)\s*\(/';
$bad     = array();

foreach ( $roots as $root ) {
	$root = rtrim( (string) realpath( $root ), '/' );
	$it   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$path = $file->getPathname();
		if ( 'php' !== $file->getExtension() || preg_match( $skip, $path ) || str_ends_with( $path, '/core/class-time.php' ) ) {
			continue;
		}
		foreach ( file( $path ) as $n => $line ) {
			$code = ltrim( $line );
			if ( str_starts_with( $code, '*' ) || str_starts_with( $code, '//' ) || str_contains( $line, 'time-ok:' ) ) {
				continue;
			}
			if ( preg_match( $pattern, $line, $m ) ) {
				$bad[] = sprintf( '%s:%d %s()', substr( $path, strlen( $root ) + 1 ), $n + 1, $m[1] );
			}
		}
	}
}

if ( $bad ) {
	fwrite( STDERR, "Raw date formatter outside \\WCB\\Core\\Time (use Time::date_label()):\n  " . implode( "\n  ", $bad ) . "\n" );
	exit( 1 );
}
echo "Time formatter gate: clean\n";
