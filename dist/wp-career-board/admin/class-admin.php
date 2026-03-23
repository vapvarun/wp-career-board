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
		add_action( 'admin_menu', array( $this, 'register_import_submenu' ), 30 );
		add_action( 'admin_menu', array( $this, 'register_emails_submenu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		( new EmailSettings() )->boot();

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

		$admin_jobs = new AdminJobs();
		$jobs_hook  = add_submenu_page(
			'wp-career-board',
			__( 'Jobs', 'wp-career-board' ),
			__( 'Jobs', 'wp-career-board' ),
			'wcb_manage_settings',
			'wcb-jobs',
			array( $admin_jobs, 'render' )
		);
		add_action( 'load-' . $jobs_hook, array( $admin_jobs, 'process_bulk_action' ) );

		$admin_apps = new AdminApplications();
		$apps_hook  = add_submenu_page(
			'wp-career-board',
			__( 'Applications', 'wp-career-board' ),
			__( 'Applications', 'wp-career-board' ),
			'wcb_manage_settings',
			'wcb-applications',
			array( $admin_apps, 'render' )
		);
		add_action( 'load-' . $apps_hook, array( $admin_apps, 'process_bulk_action' ) );

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
	 * Register the Import submenu at priority 30 (after Settings).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_import_submenu(): void {
		add_submenu_page(
			'wp-career-board',
			__( 'Import', 'wp-career-board' ),
			__( 'Import', 'wp-career-board' ),
			'wcb_manage_settings',
			'wcb-import',
			array( new AdminImport(), 'render' )
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

		// Getting Started card: hide once setup complete AND at least one job is published.
		$wcb_setup_done = (bool) get_option( 'wcb_setup_complete', false );
		$wcb_show_gs    = ! ( $wcb_setup_done && $total_jobs > 0 );

		if ( $wcb_show_gs ) {
			$wcb_settings      = (array) get_option( 'wcb_settings', array() );
			$wcb_page_keys     = array(
				'employer_registration_page',
				'employer_dashboard_page',
				'candidate_dashboard_page',
				'jobs_archive_page',
				'post_job_page',
				'company_archive_page',
			);
			$wcb_pages_created = count( array_filter( array_map( static fn( string $k ): int => (int) ( $wcb_settings[ $k ] ?? 0 ), $wcb_page_keys ) ) );
			$wcb_total_pages   = count( $wcb_page_keys );
		}
		$total_emp  = count(
			get_users(
				array(
					'role'   => 'wcb_employer',
					'fields' => 'ID',
					'number' => 9999,
				)
			)
		); // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_number
		$total_cand = count(
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
			<h1 class="screen-reader-text"><?php esc_html_e( 'WP Career Board', 'wp-career-board' ); ?></h1>

			<div class="wcb-settings-header">
				<div class="wcb-settings-header-identity">
					<div class="wcb-settings-header-icon">
						<span class="dashicons dashicons-portfolio"></span>
					</div>
					<div class="wcb-settings-header-text">
						<div class="wcb-settings-header-title"><?php esc_html_e( 'WP Career Board', 'wp-career-board' ); ?></div>
						<p class="wcb-settings-header-desc">
							<?php esc_html_e( 'Manage your job board, applications, and hiring pipeline.', 'wp-career-board' ); ?>
							<span class="wcb-version-badge">v<?php echo esc_html( WCB_VERSION ); ?></span>
							<?php if ( function_exists( 'wcbp_init' ) ) : ?>
								<span class="wcb-pro-badge"><?php esc_html_e( 'Pro', 'wp-career-board' ); ?></span>
							<?php endif; ?>
						</p>
					</div>
				</div>
				<div class="wcb-settings-header-actions">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer" class="button">
						<?php esc_html_e( 'Visit Site', 'wp-career-board' ); ?>
						<span class="dashicons dashicons-external"></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-settings' ) ); ?>" class="button">
						<?php esc_html_e( 'Settings', 'wp-career-board' ); ?>
					</a>
				</div>
			</div>

			<?php /* ── Getting Started checklist — hidden once setup complete + 1+ job published ── */ ?>
			<?php if ( $wcb_show_gs ) : ?>
			<div class="wcb-settings-card wcb-getting-started-card">
				<div class="wcb-settings-card-header">
					<h2 class="wcb-settings-card-title"><?php esc_html_e( 'Getting Started', 'wp-career-board' ); ?></h2>
				</div>
				<ul class="wcb-getting-started-list">
					<li class="wcb-gs-item wcb-gs-done">
						<span class="wcb-gs-icon dashicons dashicons-yes-alt"></span>
						<span class="wcb-gs-label"><?php esc_html_e( 'Plugin activated', 'wp-career-board' ); ?></span>
					</li>
					<li class="wcb-gs-item <?php echo $wcb_pages_created >= $wcb_total_pages ? 'wcb-gs-done' : ''; ?>">
						<span class="wcb-gs-icon dashicons <?php echo $wcb_pages_created >= $wcb_total_pages ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>"></span>
						<span class="wcb-gs-label">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: pages created count, 2: total pages count */
									__( 'Pages created (%1$d/%2$d)', 'wp-career-board' ),
									$wcb_pages_created,
									$wcb_total_pages
								)
							);
							?>
							<?php if ( $wcb_pages_created < $wcb_total_pages ) : ?>
								&mdash; <a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-setup' ) ); ?>"><?php esc_html_e( 'Run Wizard', 'wp-career-board' ); ?></a>
							<?php endif; ?>
						</span>
					</li>
					<li class="wcb-gs-item <?php echo $total_jobs > 0 ? 'wcb-gs-done' : ''; ?>">
						<span class="wcb-gs-icon dashicons <?php echo $total_jobs > 0 ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>"></span>
						<span class="wcb-gs-label">
							<?php esc_html_e( 'Add your first job', 'wp-career-board' ); ?>
							<?php if ( 0 === $total_jobs ) : ?>
								&mdash; <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcb_job' ) ); ?>"><?php esc_html_e( '+ Add Job', 'wp-career-board' ); ?></a>
							<?php endif; ?>
						</span>
					</li>
					<li class="wcb-gs-item">
						<span class="wcb-gs-icon dashicons dashicons-marker"></span>
						<span class="wcb-gs-label">
							<?php esc_html_e( 'Invite an employer', 'wp-career-board' ); ?>
							&mdash; <a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>"><?php esc_html_e( '+ Add User', 'wp-career-board' ); ?></a>
						</span>
					</li>
				</ul>
			</div>
			<?php endif; ?>

			<?php /* ── Stats row ── */ ?>
			<div class="wcb-stats-grid">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-jobs' ) ); ?>" class="wcb-stat-box">
					<span class="wcb-stat-icon"><span class="dashicons dashicons-portfolio"></span></span>
					<span class="wcb-stat-number"><?php echo (int) $total_jobs; ?></span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Active Jobs', 'wp-career-board' ); ?></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-applications' ) ); ?>" class="wcb-stat-box">
					<span class="wcb-stat-icon"><span class="dashicons dashicons-clipboard"></span></span>
					<span class="wcb-stat-number"><?php echo (int) $total_apps; ?></span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Applications', 'wp-career-board' ); ?></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-employers' ) ); ?>" class="wcb-stat-box">
					<span class="wcb-stat-icon"><span class="dashicons dashicons-building"></span></span>
					<span class="wcb-stat-number"><?php echo (int) $total_emp; ?></span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Employers', 'wp-career-board' ); ?></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-candidates' ) ); ?>" class="wcb-stat-box">
					<span class="wcb-stat-icon"><span class="dashicons dashicons-groups"></span></span>
					<span class="wcb-stat-number"><?php echo (int) $total_cand; ?></span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Candidates', 'wp-career-board' ); ?></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-jobs' ) ); ?>" class="wcb-stat-box<?php echo $pending_jobs > 0 ? ' wcb-stat-alert' : ''; ?>">
					<span class="wcb-stat-icon"><span class="dashicons dashicons-flag"></span></span>
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
			<div class="wcb-settings-card wcb-dashboard-actions-card">
				<div class="wcb-settings-card-header">
					<h2 class="wcb-settings-card-title"><?php esc_html_e( 'Quick Actions', 'wp-career-board' ); ?></h2>
				</div>
				<div class="wcb-dashboard-actions">
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcb_job' ) ); ?>" class="wcb-action-item">
						<span class="dashicons dashicons-plus-alt2"></span>
						<span><?php esc_html_e( 'Post a Job', 'wp-career-board' ); ?></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="wcb-action-item">
						<span class="dashicons dashicons-admin-users"></span>
						<span><?php esc_html_e( 'Add User', 'wp-career-board' ); ?></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-settings' ) ); ?>" class="wcb-action-item">
						<span class="dashicons dashicons-admin-settings"></span>
						<span><?php esc_html_e( 'Settings', 'wp-career-board' ); ?></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-setup' ) ); ?>" class="wcb-action-item">
						<span class="dashicons dashicons-admin-tools"></span>
						<span><?php esc_html_e( 'Setup Wizard', 'wp-career-board' ); ?></span>
					</a>
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * Register the Emails submenu page (redirects to the Settings emails tab).
	 *
	 * Keeps old URL working while consolidating Emails into Settings > Emails tab.
	 *
	 * @since 1.0.0
	 */
	public function register_emails_submenu(): void {
		add_submenu_page(
			'wp-career-board',
			__( 'Emails', 'wp-career-board' ),
			null,
			'wcb_manage_settings',
			'wcb-emails',
			static function (): void {
				wp_safe_redirect( admin_url( 'admin.php?page=wcb-settings&tab=emails' ) );
				exit;
			}
		);

		// Remove from visible menu — page remains accessible for backward-compat redirect.
		global $submenu;
		if ( isset( $submenu['wp-career-board'] ) ) {
			foreach ( $submenu['wp-career-board'] as $wcb_key => $wcb_item ) {
				if ( 'wcb-emails' === ( $wcb_item[2] ?? '' ) ) {
					unset( $submenu['wp-career-board'][ $wcb_key ] );
					break;
				}
			}
		}
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
		wp_style_add_data( 'wcb-admin', 'rtl', 'replace' );
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
