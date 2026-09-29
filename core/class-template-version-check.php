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
 * template plus an admin notice comparing it to the theme's copy; this is
 * the same idea, scoped to our three CPT single templates.
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
	 * Option storing basenames the owner has dismissed the notice for.
	 *
	 * @since 1.8.0
	 * @var   string
	 */
	private const DISMISSED_OPTION = 'wcb_template_version_dismissed';

	/**
	 * Boot the check.
	 *
	 * @since  1.8.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'admin_init', array( $this, 'handle_dismiss' ) );
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
	 * @return array<int,array{basename:string,theme_file:string}> Outdated copies.
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
					'basename'   => $basename,
					'theme_file' => $theme_file,
				);
			}
		}

		return $outdated;
	}

	/**
	 * Show one dismissible notice naming every outdated theme copy.
	 *
	 * @since  1.8.0
	 * @return void
	 */
	public function notice(): void {
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
		if ( ! wp_is_ability_granted( 'wcb/manage-settings' ) ) {
			return;
		}

		$outdated  = self::outdated();
		$dismissed = (array) get_option( self::DISMISSED_OPTION, array() );
		$outdated  = array_values(
			array_filter(
				$outdated,
				static fn( array $row ): bool => ! in_array( $row['basename'], $dismissed, true )
			)
		);

		if ( ! $outdated ) {
			return;
		}

		$names = wp_list_pluck( $outdated, 'basename' );
		$url   = wp_nonce_url( add_query_arg( 'wcb_dismiss_template_notice', implode( ',', $names ) ), 'wcb_dismiss_template_notice' );
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php
				printf(
					esc_html(
						/* translators: %s: comma-separated list of template filenames. */
						_n(
							'Your theme has its own copy of %s, and it looks older than the version WP Career Board now ships. Compare it against the plugin\'s file and update your copy to keep the page working as intended.',
							'Your theme has its own copy of %s, and they look older than the versions WP Career Board now ships. Compare them against the plugin\'s files and update your copies to keep those pages working as intended.',
							count( $outdated ),
							'wp-career-board'
						)
					),
					'<code>' . esc_html( implode( '</code>, <code>', $names ) ) . '</code>'
				);
				?>
			</p>
			<p><a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Dismiss', 'wp-career-board' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Persist a dismissal so the notice doesn't reappear for the same files.
	 *
	 * @since  1.8.0
	 * @return void
	 */
	public function handle_dismiss(): void {
		if ( ! isset( $_GET['wcb_dismiss_template_notice'], $_GET['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wcb_dismiss_template_notice' )
		) {
			return;
		}
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
		if ( ! wp_is_ability_granted( 'wcb/manage-settings' ) ) {
			return;
		}

		$names     = array_filter( array_map( 'sanitize_file_name', explode( ',', sanitize_text_field( wp_unslash( $_GET['wcb_dismiss_template_notice'] ) ) ) ) );
		$dismissed = array_unique( array_merge( (array) get_option( self::DISMISSED_OPTION, array() ), $names ) );
		update_option( self::DISMISSED_OPTION, array_values( $dismissed ), false );
	}
}
