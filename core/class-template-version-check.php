<?php
/**
 * Warn when a theme's copied template has fallen behind the plugin's own.
 *
 * A theme may legitimately copy one of our overridable templates (see
 * TemplateOverride::is_theme_template()) into its own directory to
 * customise it — that copy then wins over ours. If we later change the
 * markup our template relies on and the theme's copy is never updated,
 * the site silently keeps rendering the stale version with no signal to
 * the owner. WooCommerce solves this with a version comment in each
 * template plus a status check; this is the same idea, scoped to our
 * three CPT single templates and surfaced as a Site Health test — it
 * re-evaluates live every time, so there's no dismissal state to go
 * stale after a plugin update.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compares the plugin's overridable templates against any theme copies.
 *
 * @since 1.8.0
 */
class TemplateVersionCheck {

	/**
	 * Boot the check.
	 *
	 * @since  1.8.0
	 * @return void
	 */
	public function boot(): void {
		add_filter( 'site_status_tests', array( $this, 'register_test' ) );
	}

	/**
	 * Register this class's direct test with Site Health.
	 *
	 * @since  1.8.0
	 * @param  array<string,array<string,array<string,mixed>>> $tests Existing tests.
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	public function register_test( array $tests ): array {
		$tests['direct']['wcb_template_versions'] = array(
			'label' => __( 'WP Career Board template overrides', 'wp-career-board' ),
			'test'  => array( $this, 'run_test' ),
		);
		return $tests;
	}

	/**
	 * The plugin's own overridable templates: basename (the theme file WP's
	 * hierarchy would have to match exactly) => plugin file path.
	 *
	 * Filtered so Pro (and any future module) can register its own without
	 * this class knowing about them ahead of time.
	 *
	 * @since 1.8.0
	 * @return array<string,string> Basename => absolute plugin template path.
	 */
	public static function templates(): array {
		$defs = array(
			'single-wcb_job.php'     => WCB_DIR . 'modules/jobs/templates/single-wcb_job.php',
			'single-wcb_company.php' => WCB_DIR . 'modules/employers/templates/single-wcb_company.php',
		);

		/**
		 * Filter the set of theme-overridable templates this check watches.
		 *
		 * @since 1.8.0
		 *
		 * @param array<string,string> $defs Basename => absolute plugin template path.
		 */
		return (array) apply_filters( 'wcb_overridable_templates', $defs );
	}

	/**
	 * Read the "Template version" header from a file, WordPress-style.
	 *
	 * @since  1.8.0
	 * @param  string $file Absolute path.
	 * @return string Version string, '' if the file has none (or doesn't exist).
	 */
	private static function file_version( string $file ): string {
		if ( ! file_exists( $file ) ) {
			return '';
		}
		$data = get_file_data( $file, array( 'version' => 'Template version' ) );
		return (string) $data['version'];
	}

	/**
	 * Find every theme copy that is missing a version, or behind the plugin's.
	 *
	 * @since  1.8.0
	 * @return array<int,array{basename:string,theme_file:string,theme_version:string,plugin_version:string}> Outdated copies.
	 */
	public static function outdated(): array {
		$outdated = array();

		foreach ( self::templates() as $basename => $plugin_file ) {
			$theme_file = locate_template( $basename );
			if ( '' === $theme_file ) {
				continue; // No theme copy — nothing to compare.
			}

			$theme_version  = self::file_version( $theme_file );
			$plugin_version = self::file_version( $plugin_file );

			if ( '' === $plugin_version ) {
				continue; // Our own file has no version yet — nothing to check against.
			}

			if ( '' === $theme_version || version_compare( $theme_version, $plugin_version, '<' ) ) {
				$outdated[] = array(
					'basename'       => $basename,
					'theme_file'     => $theme_file,
					'theme_version'  => $theme_version,
					'plugin_version' => $plugin_version,
				);
			}
		}

		return $outdated;
	}

	/**
	 * Run the Site Health test.
	 *
	 * @since  1.8.0
	 * @return array<string,mixed> Site Health test result.
	 */
	public function run_test(): array {
		$result = array(
			'test'        => 'wcb_template_versions',
			'label'       => __( 'Your theme\'s WP Career Board template copies are current', 'wp-career-board' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'WP Career Board', 'wp-career-board' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				esc_html__( 'When your theme copies one of WP Career Board\'s overridable templates, this compares it against the version the plugin ships so a plugin update does not silently go unused.', 'wp-career-board' )
			),
			'actions'     => '',
		);

		$outdated = self::outdated();
		if ( ! $outdated ) {
			return $result;
		}

		$items = '';
		foreach ( $outdated as $row ) {
			$items .= sprintf(
				'<li><code>%1$s</code> — %2$s</li>',
				esc_html( $row['basename'] ),
				esc_html(
					sprintf(
						/* translators: 1: theme's template version (or "none"), 2: plugin's current template version. */
						__( 'yours: %1$s, current: %2$s', 'wp-career-board' ),
						'' !== $row['theme_version'] ? $row['theme_version'] : __( 'none', 'wp-career-board' ),
						$row['plugin_version']
					)
				)
			);
		}

		$result['status'] = 'recommended';
		$result['label']  = __( 'Your theme has outdated WP Career Board template copies', 'wp-career-board' );

		$result['description'] = sprintf(
			'<p>%s</p><ul>%s</ul>',
			esc_html__( 'Your theme has its own copy of the following WP Career Board templates, and they look older than the version the plugin now ships. Compare them against the plugin\'s files and update your copies to keep those pages working as intended.', 'wp-career-board' ),
			$items
		);

		return $result;
	}
}
