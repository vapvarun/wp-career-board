<?php
/**
 * Admin bootstrap — registers the top-level menu, sub-menus, and admin assets.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the admin interface: menus, settings, and asset enqueuing.
 *
 * @since 1.0.0
 */
class Admin {

	/**
	 * Boot the admin module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Boot settings so its admin_init hook fires.
		( new AdminSettings() )->boot();
	}

	/**
	 * Register the top-level Career Board menu and its sub-menus.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_menus(): void {
		add_menu_page(
			__( 'WP Career Board', 'wp-career-board' ),
			__( 'Career Board', 'wp-career-board' ),
			'wcb_manage_settings',
			'wp-career-board',
			array( $this, 'render_dashboard' ),
			'dashicons-portfolio',
			25
		);

		add_submenu_page(
			'wp-career-board',
			__( 'Jobs', 'wp-career-board' ),
			__( 'Jobs', 'wp-career-board' ),
			'wcb_manage_settings',
			'wcb-jobs',
			array( new AdminJobs(), 'render' )
		);

		add_submenu_page(
			'wp-career-board',
			__( 'Applications', 'wp-career-board' ),
			__( 'Applications', 'wp-career-board' ),
			'wcb_manage_settings',
			'wcb-applications',
			array( new AdminApplications(), 'render' )
		);

		add_submenu_page(
			'wp-career-board',
			__( 'Employers', 'wp-career-board' ),
			__( 'Employers', 'wp-career-board' ),
			'wcb_manage_settings',
			'wcb-employers',
			array( new AdminEmployers(), 'render' )
		);

		add_submenu_page(
			'wp-career-board',
			__( 'Settings', 'wp-career-board' ),
			__( 'Settings', 'wp-career-board' ),
			'wcb_manage_settings',
			'wcb-settings',
			array( new AdminSettings(), 'render' )
		);
	}

	/**
	 * Render the admin dashboard with summary stats.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_dashboard(): void {
		$jobs_count   = wp_count_posts( 'wcb_job' );
		$apps_count   = wp_count_posts( 'wcb_application' );
		$total_jobs   = isset( $jobs_count->publish ) ? (int) $jobs_count->publish : 0;
		$total_apps   = isset( $apps_count->publish ) ? (int) $apps_count->publish : 0;
		$pending_jobs = isset( $jobs_count->pending ) ? (int) $jobs_count->pending : 0;
		?>
		<div class="wrap wcb-admin-dashboard">
			<h1><?php esc_html_e( 'WP Career Board', 'wp-career-board' ); ?></h1>
			<div class="wcb-stats-grid">
				<div class="wcb-stat-box">
					<span class="wcb-stat-number"><?php echo (int) $total_jobs; ?></span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Active Jobs', 'wp-career-board' ); ?></span>
				</div>
				<div class="wcb-stat-box">
					<span class="wcb-stat-number"><?php echo (int) $total_apps; ?></span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Applications', 'wp-career-board' ); ?></span>
				</div>
				<div class="wcb-stat-box wcb-stat-alert">
					<span class="wcb-stat-number"><?php echo (int) $pending_jobs; ?></span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Pending Review', 'wp-career-board' ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin CSS and JS on WCB admin pages.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'wcb' ) && false === strpos( $hook, 'wp-career-board' ) ) {
			return;
		}

		wp_enqueue_style( 'wcb-admin', WCB_URL . 'assets/css/admin.css', array(), WCB_VERSION );
		wp_enqueue_script( 'wcb-admin', WCB_URL . 'assets/js/admin.js', array( 'wp-api-fetch' ), WCB_VERSION, true );

		wp_localize_script(
			'wcb-admin',
			'wcbAdmin',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'wcb/v1/' ) ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'      => array(
					'confirmApprove' => __( 'Approve this job?', 'wp-career-board' ),
					'confirmReject'  => __( 'Reject this job? Enter reason:', 'wp-career-board' ),
				),
			)
		);
	}
}
