<?php
/**
 * Admin settings page — registers, sanitizes, and renders WCB plugin settings.
 *
 * Settings stored as a single serialised array under the 'wcb_settings' option key.
 *
 * Keys and usage:
 *  auto_publish_jobs        — publish employer jobs without admin review
 *  jobs_expire_days         — default listing lifetime (modules/jobs/class-jobs-expiry.php)
 *  jobs_archive_page        — page containing wcb/job-listings block (used in Reign nav)
 *  employer_dashboard_page  — page containing wcb/employer-dashboard block
 *  candidate_dashboard_page — page containing wcb/candidate-dashboard block
 *  post_job_page            — page containing wcb/job-form block
 *  salary_currency          — currency symbol prepended to salary ranges on job detail pages
 *  notification_email       — address that receives admin notifications (defaults to admin_email)
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
 * Manages the WCB settings page (Career Board > Settings).
 *
 * @since 1.0.0
 */
class AdminSettings {

	/**
	 * WordPress option key for all WCB settings.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_KEY = 'wcb_settings';

	/**
	 * Boot the settings module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_wcb_create_pages', array( $this, 'handle_create_pages' ) );
	}

	/**
	 * Register the WCB settings group with WordPress.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'wcb_settings_group',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * Sanitize submitted settings values.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $input Raw input from the settings form.
	 * @return array<string,mixed>
	 */
	public function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();

		$notification_email = isset( $input['notification_email'] )
			? sanitize_email( $input['notification_email'] )
			: '';

		return array(
			'auto_publish_jobs'        => ! empty( $input['auto_publish_jobs'] ),
			'jobs_expire_days'         => isset( $input['jobs_expire_days'] ) ? max( 1, (int) $input['jobs_expire_days'] ) : 30,
			'jobs_archive_page'        => isset( $input['jobs_archive_page'] ) ? (int) $input['jobs_archive_page'] : 0,
			'employer_dashboard_page'  => isset( $input['employer_dashboard_page'] ) ? (int) $input['employer_dashboard_page'] : 0,
			'candidate_dashboard_page' => isset( $input['candidate_dashboard_page'] ) ? (int) $input['candidate_dashboard_page'] : 0,
			'post_job_page'            => isset( $input['post_job_page'] ) ? (int) $input['post_job_page'] : 0,
			'salary_currency'          => isset( $input['salary_currency'] ) && in_array( $input['salary_currency'], array( 'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'INR', 'SGD' ), true ) ? $input['salary_currency'] : 'USD',
			'notification_email'       => $notification_email ? $notification_email : '',
		);
	}

	/**
	 * Handle the "Create Missing Pages" form POST.
	 *
	 * Creates any WCB page that has not yet been assigned in settings,
	 * then redirects back to the settings screen.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_create_pages(): void {
		check_admin_referer( 'wcb_create_pages' );

		if ( ! current_user_can( 'wcb_manage_settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to do this.', 'wp-career-board' ) );
		}

		$settings = (array) get_option( self::OPTION_KEY, array() );

		$pages = array(
			'jobs_archive_page'        => array(
				'title'   => __( 'Find Jobs', 'wp-career-board' ),
				'content' => '<!-- wp:wp-career-board/job-search /--><!-- wp:wp-career-board/job-filters /--><!-- wp:wp-career-board/job-listings /-->',
			),
			'employer_dashboard_page'  => array(
				'title'   => __( 'Employer Dashboard', 'wp-career-board' ),
				'content' => '<!-- wp:wp-career-board/employer-dashboard /-->',
			),
			'candidate_dashboard_page' => array(
				'title'   => __( 'Candidate Dashboard', 'wp-career-board' ),
				'content' => '<!-- wp:wp-career-board/candidate-dashboard /-->',
			),
			'post_job_page'            => array(
				'title'   => __( 'Post a Job', 'wp-career-board' ),
				'content' => '<!-- wp:wp-career-board/job-form /-->',
			),
		);

		foreach ( $pages as $key => $page ) {
			if ( ! empty( $settings[ $key ] ) && get_post( (int) $settings[ $key ] ) ) {
				continue;
			}
			$page_id = wp_insert_post(
				array(
					'post_title'   => $page['title'],
					'post_content' => $page['content'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$settings[ $key ] = $page_id;
			}
		}

		update_option( self::OPTION_KEY, $settings );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'wcb-settings',
					'created' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render(): void {
		$settings           = (array) get_option( self::OPTION_KEY, array() );
		$notification_email = ! empty( $settings['notification_email'] ) ? $settings['notification_email'] : (string) get_option( 'admin_email', '' );
		$salary_currency    = isset( $settings['salary_currency'] ) ? $settings['salary_currency'] : '$';

		$wcb_page_keys = array( 'jobs_archive_page', 'employer_dashboard_page', 'candidate_dashboard_page', 'post_job_page' );
		$wcb_missing   = array();
		foreach ( $wcb_page_keys as $wcb_k ) {
			if ( empty( $settings[ $wcb_k ] ) || ! get_post( (int) $settings[ $wcb_k ] ) ) {
				$wcb_missing[] = $wcb_k;
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP Career Board — Settings', 'wp-career-board' ); ?></h1>

			<?php if ( isset( $_GET['created'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Missing pages created and assigned successfully.', 'wp-career-board' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $wcb_missing ) ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Page Setup Required', 'wp-career-board' ); ?></strong> —
						<?php esc_html_e( 'Some required WP Career Board pages have not been created yet.', 'wp-career-board' ); ?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="wcb_create_pages">
						<?php wp_nonce_field( 'wcb_create_pages' ); ?>
						<?php submit_button( __( 'Create Missing Pages', 'wp-career-board' ), 'primary', 'submit', false ); ?>
					</form>
				</div>
			<?php else : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'All required WP Career Board pages are set up.', 'wp-career-board' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'wcb_settings_group' ); ?>

				<h2><?php esc_html_e( 'Job Listings', 'wp-career-board' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto-Publish Jobs', 'wp-career-board' ); ?></th>
						<td>
							<label>
								<input
									type="checkbox"
									name="wcb_settings[auto_publish_jobs]"
									value="1"
									<?php checked( ! empty( $settings['auto_publish_jobs'] ) ); ?>
								>
								<?php esc_html_e( 'Publish jobs immediately without admin review', 'wp-career-board' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'When unchecked, new jobs are held as "Pending" until approved under Career Board → Jobs.', 'wp-career-board' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcb-jobs-expire-days"><?php esc_html_e( 'Job Expiry (days)', 'wp-career-board' ); ?></label></th>
						<td>
							<input
								type="number"
								id="wcb-jobs-expire-days"
						name="wcb_settings[jobs_expire_days]"
								value="<?php echo isset( $settings['jobs_expire_days'] ) ? (int) $settings['jobs_expire_days'] : 30; ?>"
								min="1"
								max="365"
								style="width:80px"
							>
							<p class="description"><?php esc_html_e( 'Jobs are automatically closed after this many days. Set 0 to disable auto-expiry.', 'wp-career-board' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcb-salary-currency"><?php esc_html_e( 'Default Salary Currency', 'wp-career-board' ); ?></label></th>
						<td>
							<?php
							$wcb_currencies_list = array(
								'USD' => 'USD — US Dollar ($)',
								'EUR' => 'EUR — Euro (€)',
								'GBP' => 'GBP — British Pound (£)',
								'CAD' => 'CAD — Canadian Dollar (CA$)',
								'AUD' => 'AUD — Australian Dollar (A$)',
								'INR' => 'INR — Indian Rupee (₹)',
								'SGD' => 'SGD — Singapore Dollar (S$)',
							);
							?>
							<select id="wcb-salary-currency" name="wcb_settings[salary_currency]">
								<?php foreach ( $wcb_currencies_list as $wcb_code => $wcb_label ) : ?>
									<option value="<?php echo esc_attr( $wcb_code ); ?>" <?php selected( $salary_currency, $wcb_code ); ?>>
										<?php echo esc_html( $wcb_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Site-wide default currency for new job postings. Employers can override it per job.', 'wp-career-board' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Pages', 'wp-career-board' ); ?></h2>
				<p class="description" style="margin-bottom:12px">
					<?php esc_html_e( 'Assign the WordPress pages that contain each WP Career Board block. Run the Setup Wizard to create these pages automatically.', 'wp-career-board' ); ?>
				</p>
				<table class="form-table">
					<?php
					$wcb_page_settings = array(
						'jobs_archive_page'        => array(
							'label' => __( 'Jobs Archive Page', 'wp-career-board' ),
							'desc'  => __( 'Contains the wcb/job-listings block. Used as the main job board.', 'wp-career-board' ),
						),
						'employer_dashboard_page'  => array(
							'label' => __( 'Employer Dashboard Page', 'wp-career-board' ),
							'desc'  => __( 'Contains the wcb/employer-dashboard block. Employers manage jobs and profiles here.', 'wp-career-board' ),
						),
						'candidate_dashboard_page' => array(
							'label' => __( 'Candidate Dashboard Page', 'wp-career-board' ),
							'desc'  => __( 'Contains the wcb/candidate-dashboard block. Candidates track applications and saved jobs.', 'wp-career-board' ),
						),
						'post_job_page'            => array(
							'label' => __( 'Post a Job Page', 'wp-career-board' ),
							'desc'  => __( 'Contains the wcb/job-form block. Employers post new job listings here.', 'wp-career-board' ),
						),
					);
					foreach ( $wcb_page_settings as $wcb_key => $wcb_info ) :
						?>
						<tr>
							<th scope="row"><label for="wcb-page-<?php echo esc_attr( sanitize_key( $wcb_key ) ); ?>"><?php echo esc_html( $wcb_info['label'] ); ?></label></th>
							<td>
								<?php
								wp_dropdown_pages(
									array(
										'id'               => 'wcb-page-' . sanitize_key( $wcb_key ),
										'name'             => 'wcb_settings[' . sanitize_key( $wcb_key ) . ']',
										'selected'         => isset( $settings[ $wcb_key ] ) ? (int) $settings[ $wcb_key ] : 0,
										'show_option_none' => esc_html__( '— Select a page —', 'wp-career-board' ),
									)
								);
								?>
								<p class="description"><?php echo esc_html( $wcb_info['desc'] ); ?></p>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'Notifications', 'wp-career-board' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="wcb-notification-email"><?php esc_html_e( 'Notification Email', 'wp-career-board' ); ?></label></th>
						<td>
							<input
								type="email"
								id="wcb-notification-email"
						name="wcb_settings[notification_email]"
								value="<?php echo esc_attr( $notification_email ); ?>"
								class="regular-text"
								required
							>
							<p class="description"><?php esc_html_e( 'Admin notification emails (new jobs, flagged content) are sent here. Defaults to the site admin email.', 'wp-career-board' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
