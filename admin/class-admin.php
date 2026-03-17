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
		add_action( 'admin_menu', array( $this, 'register_settings_submenu' ), 25 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Boot settings so its admin_init hook fires.
		( new AdminSettings() )->boot();

		// Boot meta boxes for the wcb_job CPT.
		( new AdminMetaBoxes() )->boot();
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
			__( 'Candidates', 'wp-career-board' ),
			__( 'Candidates', 'wp-career-board' ),
			'wcb_manage_settings',
			'wcb-candidates',
			array( new AdminCandidates(), 'render' )
		);

		add_submenu_page(
			'wp-career-board',
			__( 'Companies', 'wp-career-board' ),
			__( 'Companies', 'wp-career-board' ),
			'wcb_manage_settings',
			'wcb-companies',
			array( new AdminCompanies(), 'render' )
		);

		add_submenu_page(
			'wp-career-board',
			__( 'Employers', 'wp-career-board' ),
			__( 'Employers', 'wp-career-board' ),
			'wcb_manage_settings',
			'wcb-employers',
			array( new AdminEmployers(), 'render' )
		);
	}

	/**
	 * Register the Settings submenu at priority 25 so it appears after all Pro items.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings_submenu(): void {
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
	 * Render the admin dashboard — stats, pending queue, recent applications.
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
		$total_emp    = count(
			get_users(
				array(
					'role'   => 'wcb_employer',
					'fields' => 'ID',
					'number' => 9999,
				)
			)
		); // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_number
		$total_cand   = count(
			get_users(
				array(
					'role'   => 'wcb_candidate',
					'fields' => 'ID',
					'number' => 9999,
				)
			)
		); // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_number

		// Pending jobs for inline moderation queue.
		$pending_posts = get_posts(
			array(
				'post_type'      => 'wcb_job',
				'post_status'    => 'pending',
				'posts_per_page' => 10,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		// Five most recent applications.
		$recent_apps = get_posts(
			array(
				'post_type'      => 'wcb_application',
				'post_status'    => 'any',
				'posts_per_page' => 5,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		?>
		<div class="wrap wcb-admin-dashboard">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'WP Career Board', 'wp-career-board' ); ?></h1>
			<hr class="wp-header-end">
			<div class="wcb-dashboard-topbar">
				<p class="wcb-dashboard-tagline">
					<?php esc_html_e( 'Manage your job board, applications, and hiring pipeline.', 'wp-career-board' ); ?>
					<span class="wcb-version-badge">v<?php echo esc_html( WCB_VERSION ); ?></span>
					<?php if ( function_exists( 'wcbp_init' ) ) : ?>
						<span class="wcb-pro-badge"><?php esc_html_e( 'Pro', 'wp-career-board' ); ?></span>
					<?php endif; ?>
				</p>
				<div class="wcb-topbar-links">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer" class="wcb-topbar-link">
						<?php esc_html_e( 'Visit Site', 'wp-career-board' ); ?>
						<span class="dashicons dashicons-external"></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-settings' ) ); ?>" class="wcb-topbar-link">
						<?php esc_html_e( 'Settings', 'wp-career-board' ); ?>
					</a>
				</div>
			</div>

			<?php /* ── Stats row ── */ ?>
			<div class="wcb-stats-grid">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-jobs' ) ); ?>" class="wcb-stat-box">
					<span class="wcb-stat-number"><?php echo (int) $total_jobs; ?></span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Active Jobs', 'wp-career-board' ); ?></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-applications' ) ); ?>" class="wcb-stat-box">
					<span class="wcb-stat-number"><?php echo (int) $total_apps; ?></span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Applications', 'wp-career-board' ); ?></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-employers' ) ); ?>" class="wcb-stat-box">
					<span class="wcb-stat-number"><?php echo (int) $total_emp; ?></span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Employers', 'wp-career-board' ); ?></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-candidates' ) ); ?>" class="wcb-stat-box">
					<span class="wcb-stat-number"><?php echo (int) $total_cand; ?></span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Candidates', 'wp-career-board' ); ?></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-jobs' ) ); ?>" class="wcb-stat-box<?php echo $pending_jobs > 0 ? ' wcb-stat-alert' : ''; ?>">
					<span class="wcb-stat-number"><?php echo (int) $pending_jobs; ?></span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Pending Review', 'wp-career-board' ); ?></span>
				</a>
			</div>

			<div class="wcb-dashboard-columns">

				<?php /* ── Pending moderation queue ── */ ?>
				<div class="wcb-dashboard-col">
					<div class="wcb-dashboard-panel">
						<h2 class="wcb-panel-header" data-panel="pending_review">
							<?php esc_html_e( 'Pending Review', 'wp-career-board' ); ?>
							<span class="wcb-panel-header-right">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-jobs' ) ); ?>" class="wcb-panel-link">
									<?php esc_html_e( 'View all →', 'wp-career-board' ); ?>
								</a>
								<button type="button" class="wcb-panel-toggle" aria-label="<?php esc_attr_e( 'Toggle panel', 'wp-career-board' ); ?>">
									<span class="dashicons dashicons-arrow-up-alt2"></span>
								</button>
							</span>
						</h2>
						<div class="wcb-panel-body">
							<?php if ( empty( $pending_posts ) ) : ?>
								<p class="wcb-empty"><?php esc_html_e( 'No jobs pending review.', 'wp-career-board' ); ?></p>
							<?php else : ?>
								<table class="widefat striped">
									<tbody>
										<?php foreach ( $pending_posts as $wcb_job ) : ?>
											<tr>
												<td>
													<a href="<?php echo esc_url( (string) get_edit_post_link( $wcb_job->ID ) ); ?>" style="font-weight:600">
														<?php echo esc_html( get_the_title( $wcb_job ) ); ?>
													</a>
													<br><small><?php echo esc_html( get_the_author_meta( 'display_name', (int) $wcb_job->post_author ) ); ?> &middot; <?php echo esc_html( get_the_date( 'M j', $wcb_job ) ); ?></small>
												</td>
												<td style="white-space:nowrap">
													<button type="button" class="button button-primary button-small wcb-approve-job" data-job-id="<?php echo (int) $wcb_job->ID; ?>"><?php esc_html_e( 'Approve', 'wp-career-board' ); ?></button>
													<button type="button" class="button button-small wcb-reject-job" data-job-id="<?php echo (int) $wcb_job->ID; ?>"><?php esc_html_e( 'Reject', 'wp-career-board' ); ?></button>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<?php /* ── Recent applications ── */ ?>
				<div class="wcb-dashboard-col">
					<div class="wcb-dashboard-panel">
						<h2 class="wcb-panel-header" data-panel="recent_apps">
							<?php esc_html_e( 'Recent Applications', 'wp-career-board' ); ?>
							<span class="wcb-panel-header-right">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-applications' ) ); ?>" class="wcb-panel-link">
									<?php esc_html_e( 'View all →', 'wp-career-board' ); ?>
								</a>
								<button type="button" class="wcb-panel-toggle" aria-label="<?php esc_attr_e( 'Toggle panel', 'wp-career-board' ); ?>">
									<span class="dashicons dashicons-arrow-up-alt2"></span>
								</button>
							</span>
						</h2>
						<div class="wcb-panel-body">
						<?php if ( empty( $recent_apps ) ) : ?>
							<p class="wcb-empty"><?php esc_html_e( 'No applications yet.', 'wp-career-board' ); ?></p>
						<?php else : ?>
							<table class="widefat striped">
								<tbody>
									<?php foreach ( $recent_apps as $wcb_app ) : ?>
										<?php
										$wcb_job_id   = (int) get_post_meta( $wcb_app->ID, '_wcb_job_id', true );
										$wcb_cand_id  = (int) get_post_meta( $wcb_app->ID, '_wcb_candidate_id', true );
										$wcb_status   = (string) get_post_meta( $wcb_app->ID, '_wcb_status', true );
										$wcb_job_obj  = $wcb_job_id ? get_post( $wcb_job_id ) : null;
										$wcb_cand_obj = $wcb_cand_id ? get_userdata( $wcb_cand_id ) : false;
										$wcb_job_lbl  = $wcb_job_obj instanceof \WP_Post ? $wcb_job_obj->post_title : __( '(deleted)', 'wp-career-board' );
										$wcb_cand_lbl = $wcb_cand_obj instanceof \WP_User ? $wcb_cand_obj->display_name : __( '(deleted)', 'wp-career-board' );
										$wcb_status   = $wcb_status ? $wcb_status : 'submitted';
										?>
										<tr>
											<td>
												<strong><?php echo esc_html( $wcb_cand_lbl ); ?></strong><br>
												<small>
													<?php if ( $wcb_job_obj instanceof \WP_Post ) : ?>
														<a href="<?php echo esc_url( (string) get_edit_post_link( $wcb_job_id ) ); ?>"><?php echo esc_html( $wcb_job_lbl ); ?></a>
													<?php else : ?>
														<?php echo esc_html( $wcb_job_lbl ); ?>
													<?php endif; ?>
													&middot; <?php echo esc_html( get_the_date( 'M j', $wcb_app ) ); ?>
												</small>
											</td>
											<td>
												<span class="wcb-status-badge wcb-status-<?php echo esc_attr( $wcb_status ); ?>">
													<?php echo esc_html( ucfirst( $wcb_status ) ); ?>
												</span>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
						</div>
					</div>
				</div>

			</div><!-- .wcb-dashboard-columns -->

			<?php /* ── Quick actions ── */ ?>
			<div class="wcb-quick-actions">
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcb_job' ) ); ?>" class="button button-primary"><?php esc_html_e( '+ Add Job', 'wp-career-board' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="button"><?php esc_html_e( '+ Add User', 'wp-career-board' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-settings' ) ); ?>" class="button"><?php esc_html_e( 'Settings', 'wp-career-board' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-setup' ) ); ?>" class="button"><?php esc_html_e( 'Run Setup Wizard', 'wp-career-board' ); ?></a>
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
		global $post_type;
		$wcb_cpt_edit = ( 'post.php' === $hook || 'post-new.php' === $hook )
			&& in_array( $post_type, array( 'wcb_job', 'wcb_company' ), true );
		if ( ! $wcb_cpt_edit && false === strpos( $hook, 'wcb' ) && false === strpos( $hook, 'wp-career-board' ) ) {
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
					'approveTitle' => __( 'Approve Job', 'wp-career-board' ),
					'approveMsg'   => __( 'This job will be published and the employer will be notified.', 'wp-career-board' ),
					'approveBtn'   => __( 'Approve', 'wp-career-board' ),
					'rejectTitle'  => __( 'Reject Job', 'wp-career-board' ),
					'rejectMsg'    => __( 'This job will be moved to Draft and the employer will be notified.', 'wp-career-board' ),
					'rejectBtn'    => __( 'Reject', 'wp-career-board' ),
					'reasonLabel'  => __( 'Reason (optional):', 'wp-career-board' ),
					'confirm'      => __( 'Confirm', 'wp-career-board' ),
					'cancel'       => __( 'Cancel', 'wp-career-board' ),
					'saveFailed'   => __( 'Could not update. Please try again.', 'wp-career-board' ),
				),
			)
		);
	}
}
