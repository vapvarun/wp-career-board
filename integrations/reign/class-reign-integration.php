<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Reign theme integration for WP Career Board.
 *
 * Activated automatically when the active theme is 'reign-theme'.
 * Provides:
 *  - Template overrides for single and archive wcb_job pages
 *  - Customizer colour control under a dedicated "WP Career Board" panel
 *  - Reign left-nav items (Browse Jobs, Employer Dashboard, My Applications)
 *  - A lightweight compatibility stylesheet for WCB blocks inside Reign
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Integrations\Reign;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReignIntegration
 */
class ReignIntegration {

	/**
	 * Boot hooks.
	 */
	public function boot(): void {
		add_filter( 'single_template', array( $this, 'single_template' ) );
		add_filter( 'archive_template', array( $this, 'archive_template' ) );
		add_filter( 'reign_nav_items', array( $this, 'add_nav_items' ) );
		\WCB\Core\ThemeCompat::register(
			'wcb-reign-compat',
			WCB_URL . 'integrations/reign/assets/reign-compat.css',
			array( 'reign_main_style' )
		);
	}

	/**
	 * Return Reign-compatible single job template when viewing a wcb_job post.
	 *
	 * @param string $template Default template path.
	 * @return string
	 */
	public function single_template( string $template ): string {
		if ( ! is_singular( 'wcb_job' ) ) {
			return $template;
		}

		// A theme shipping its OWN single-wcb_job.php outranks the bundled
		// integration. This handler runs on single_template, which receives
		// whatever WordPress's hierarchy already resolved - a theme's generic
		// single.php does not count, only a file matching this exact slot.
		if ( \WCB\Core\TemplateOverride::is_theme_template( $template, 'single-wcb_job.php' ) ) {
			return $template;
		}

		$reign_tpl = WCB_DIR . 'integrations/reign/templates/single-wcb_job.php';
		return file_exists( $reign_tpl ) ? $reign_tpl : $template;
	}

	/**
	 * Return Reign-compatible archive template for wcb_job post-type archives.
	 *
	 * @param string $template Default template path.
	 * @return string
	 */
	public function archive_template( string $template ): string {
		if ( ! is_post_type_archive( 'wcb_job' ) ) {
			return $template;
		}

		// A theme shipping its OWN archive-wcb_job.php outranks the bundled
		// integration; its generic archive.php does not count. See single_template().
		if ( \WCB\Core\TemplateOverride::is_theme_template( $template, 'archive-wcb_job.php' ) ) {
			return $template;
		}

		$reign_tpl = WCB_DIR . 'integrations/reign/templates/archive-wcb_job.php';
		return file_exists( $reign_tpl ) ? $reign_tpl : $template;
	}

	/**
	 * Append WP Career Board links to Reign's left navigation panel.
	 *
	 * @param array<int,array<string,string>> $items Existing nav items.
	 * @return array<int,array<string,string>>
	 */
	public function add_nav_items( array $items ): array {
		$jobs_url = \WCB\Admin\Pages::url( 'jobs_archive_page' );
		$items[]  = array(
			'label' => __( 'Browse Jobs', 'wp-career-board' ),
			'url'   => '' !== $jobs_url ? $jobs_url : (string) get_post_type_archive_link( 'wcb_job' ),
			'icon'  => 'dashicons-portfolio',
		);

		$employer_url = \WCB\Admin\Pages::url( 'employer_dashboard_page' );
		if ( '' !== $employer_url && wp_is_ability_granted( 'wcb/post-jobs' ) ) {
			$items[] = array(
				'label' => __( 'Employer Dashboard', 'wp-career-board' ),
				'url'   => $employer_url,
				'icon'  => 'dashicons-building',
			);
		}

		$candidate_url = \WCB\Admin\Pages::url( 'candidate_dashboard_page' );
		if ( '' !== $candidate_url && wp_is_ability_granted( 'wcb/apply-jobs' ) ) {
			$items[] = array(
				'label' => __( 'My Applications', 'wp-career-board' ),
				'url'   => $candidate_url,
				'icon'  => 'dashicons-id-alt',
			);
		}

		return $items;
	}
}
