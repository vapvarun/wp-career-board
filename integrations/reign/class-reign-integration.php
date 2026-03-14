<?php
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
		add_action( 'customize_register', array( $this, 'customizer_section' ) );
		add_filter( 'reign_nav_items', array( $this, 'add_nav_items' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Return Reign-compatible single job template when viewing a wcb_job post.
	 *
	 * @param string $template Default template path.
	 * @return string
	 */
	public function single_template( string $template ): string {
		if ( is_singular( 'wcb_job' ) ) {
			$reign_tpl = WCB_DIR . 'integrations/reign/templates/single-wcb_job.php';
			if ( file_exists( $reign_tpl ) ) {
				return $reign_tpl;
			}
		}
		return $template;
	}

	/**
	 * Return Reign-compatible archive template for wcb_job post-type archives.
	 *
	 * @param string $template Default template path.
	 * @return string
	 */
	public function archive_template( string $template ): string {
		if ( is_post_type_archive( 'wcb_job' ) ) {
			$reign_tpl = WCB_DIR . 'integrations/reign/templates/archive-wcb_job.php';
			if ( file_exists( $reign_tpl ) ) {
				return $reign_tpl;
			}
		}
		return $template;
	}

	/**
	 * Register WP Career Board Customizer section and colour control.
	 *
	 * @param \WP_Customize_Manager $wp_customize WordPress Customizer instance.
	 */
	public function customizer_section( \WP_Customize_Manager $wp_customize ): void {
		$wp_customize->add_section(
			'wcb_reign',
			array(
				'title'    => __( 'WP Career Board', 'wp-career-board' ),
				'priority' => 200,
			)
		);

		$wp_customize->add_setting(
			'wcb_reign_primary_color',
			array(
				'default'           => '#4f46e5',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);

		$wp_customize->add_control(
			new \WP_Customize_Color_Control(
				$wp_customize,
				'wcb_reign_primary_color',
				array(
					'label'   => __( 'Primary Color', 'wp-career-board' ),
					'section' => 'wcb_reign',
				)
			)
		);
	}

	/**
	 * Append WP Career Board links to Reign's left navigation panel.
	 *
	 * @param array<int,array<string,string>> $items Existing nav items.
	 * @return array<int,array<string,string>>
	 */
	public function add_nav_items( array $items ): array {
		$settings = (array) get_option( 'wcb_settings', array() );

		$jobs_url = ! empty( $settings['jobs_archive_page'] )
			? (string) get_permalink( (int) $settings['jobs_archive_page'] )
			: home_url( '/jobs/' );

		$items[] = array(
			'label' => __( 'Browse Jobs', 'wp-career-board' ),
			'url'   => $jobs_url,
			'icon'  => 'dashicons-portfolio',
		);

		$wcb_can_post = function_exists( 'wp_is_ability_granted' )
			? wp_is_ability_granted( 'wcb_post_jobs' )
			: current_user_can( 'wcb_post_jobs' );

		if ( $wcb_can_post ) {
			$employer_url = ! empty( $settings['employer_dashboard_page'] )
				? (string) get_permalink( (int) $settings['employer_dashboard_page'] )
				: '#';

			$items[] = array(
				'label' => __( 'Employer Dashboard', 'wp-career-board' ),
				'url'   => $employer_url,
				'icon'  => 'dashicons-building',
			);
		}

		$wcb_can_apply = function_exists( 'wp_is_ability_granted' )
			? wp_is_ability_granted( 'wcb_apply_jobs' )
			: current_user_can( 'wcb_apply_jobs' );

		if ( $wcb_can_apply ) {
			$candidate_url = ! empty( $settings['candidate_dashboard_page'] )
				? (string) get_permalink( (int) $settings['candidate_dashboard_page'] )
				: '#';

			$items[] = array(
				'label' => __( 'My Applications', 'wp-career-board' ),
				'url'   => $candidate_url,
				'icon'  => 'dashicons-id-alt',
			);
		}

		return $items;
	}

	/**
	 * Enqueue Reign-compatible stylesheet on WCB job pages.
	 */
	public function enqueue_styles(): void {
		if ( ! is_singular( 'wcb_job' ) && ! is_post_type_archive( 'wcb_job' ) ) {
			return;
		}
		wp_enqueue_style(
			'wcb-reign-compat',
			WCB_URL . 'integrations/reign/assets/reign-compat.css',
			array( 'reign-style' ),
			WCB_VERSION
		);
	}
}
