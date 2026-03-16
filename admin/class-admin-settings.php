<?php
/**
 * Admin settings page — registers, sanitizes, and renders WCB plugin settings.
 *
 * Settings stored as a single serialised array under the 'wcb_settings' option key.
 *
 * Keys and usage:
 *  auto_publish_jobs        — publish employer jobs without admin review
 *  jobs_per_page            — listings per page in the job-listings block
 *  jobs_expire_days         — default listing lifetime (modules/jobs/class-jobs-expiry.php)
 *  deadline_auto_close      — auto-close jobs when application deadline passes
 *  allow_withdraw           — let candidates withdraw their own applications
 *  salary_currency          — default currency code for new job postings
 *  jobs_archive_page        — page containing wcb/job-listings block
 *  employer_dashboard_page  — page containing wcb/employer-dashboard block
 *  candidate_dashboard_page — page containing wcb/candidate-dashboard block
 *  post_job_page            — page containing wcb/job-form block
 *  company_archive_page     — page containing wcb/company-archive block
 *  notification_email       — address that receives admin notifications
 *  from_name                — sender name for all WCB notification emails
 *  from_email               — sender address for all WCB notification emails
 *
 * Developer hooks (for Pro extensions):
 *  Filter: wcb_settings_tabs( array $tabs )                      — add or reorder settings tabs
 *  Filter: wcb_settings_sanitize( array $output, array $input )  — extend sanitization for Pro keys
 *  Action: wcb_settings_tab_{slug}( array $settings )            — render a Pro tab's content
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
	 * Allowed currency codes.
	 *
	 * @since 1.0.0
	 * @var array<string,string>
	 */
	const CURRENCIES = array(
		'USD' => 'USD — US Dollar ($)',
		'EUR' => 'EUR — Euro (€)',
		'GBP' => 'GBP — British Pound (£)',
		'CAD' => 'CAD — Canadian Dollar (CA$)',
		'AUD' => 'AUD — Australian Dollar (A$)',
		'INR' => 'INR — Indian Rupee (₹)',
		'SGD' => 'SGD — Singapore Dollar (S$)',
	);

	/**
	 * Boot the settings module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_wcb_create_pages', array( $this, 'handle_create_pages' ) );
		add_action( 'admin_post_wcb_send_test_email', array( $this, 'handle_send_test_email' ) );
		add_filter( 'wp_mail_from', array( $this, 'mail_from' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'mail_from_name' ) );
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

		$notification_email = isset( $input['notification_email'] ) ? sanitize_email( $input['notification_email'] ) : '';
		$from_email         = isset( $input['from_email'] ) ? sanitize_email( $input['from_email'] ) : '';

		$output = array(
			'auto_publish_jobs'        => ! empty( $input['auto_publish_jobs'] ),
			'jobs_per_page'            => isset( $input['jobs_per_page'] ) ? max( 1, min( 100, (int) $input['jobs_per_page'] ) ) : 10,
			'jobs_expire_days'         => isset( $input['jobs_expire_days'] ) ? max( 1, (int) $input['jobs_expire_days'] ) : 30,
			'deadline_auto_close'      => ! empty( $input['deadline_auto_close'] ),
			'allow_withdraw'           => ! empty( $input['allow_withdraw'] ),
			'salary_currency'          => isset( $input['salary_currency'] ) && array_key_exists( $input['salary_currency'], self::CURRENCIES ) ? $input['salary_currency'] : 'USD',
			'jobs_archive_page'        => isset( $input['jobs_archive_page'] ) ? (int) $input['jobs_archive_page'] : 0,
			'employer_dashboard_page'  => isset( $input['employer_dashboard_page'] ) ? (int) $input['employer_dashboard_page'] : 0,
			'candidate_dashboard_page' => isset( $input['candidate_dashboard_page'] ) ? (int) $input['candidate_dashboard_page'] : 0,
			'post_job_page'            => isset( $input['post_job_page'] ) ? (int) $input['post_job_page'] : 0,
			'company_archive_page'     => isset( $input['company_archive_page'] ) ? (int) $input['company_archive_page'] : 0,
			'notification_email'       => $notification_email ? $notification_email : '',
			'from_name'                => isset( $input['from_name'] ) ? sanitize_text_field( $input['from_name'] ) : '',
			'from_email'               => $from_email ? $from_email : '',
		);

		/**
		 * Filter the sanitized settings output.
		 *
		 * Pro extensions can validate and persist their own keys here.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,mixed> $output Sanitized settings.
		 * @param array<string,mixed> $input  Raw submitted values.
		 */
		return apply_filters( 'wcb_settings_sanitize', $output, $input );
	}

	/**
	 * Override wp_mail_from with the configured From Email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Default sender address.
	 * @return string
	 */
	public function mail_from( string $email ): string {
		$settings = (array) get_option( self::OPTION_KEY, array() );
		return ! empty( $settings['from_email'] ) ? $settings['from_email'] : $email;
	}

	/**
	 * Override wp_mail_from_name with the configured From Name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Default sender name.
	 * @return string
	 */
	public function mail_from_name( string $name ): string {
		$settings = (array) get_option( self::OPTION_KEY, array() );
		return ! empty( $settings['from_name'] ) ? $settings['from_name'] : $name;
	}

	/**
	 * Handle the "Create Missing Pages" form POST.
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
			'company_archive_page'     => array(
				'title'   => __( 'Companies', 'wp-career-board' ),
				'content' => '<!-- wp:wp-career-board/company-archive /-->',
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
					'tab'     => 'pages',
					'created' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle the "Send Test Email" form POST.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_send_test_email(): void {
		check_admin_referer( 'wcb_send_test_email' );

		if ( ! current_user_can( 'wcb_manage_settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to do this.', 'wp-career-board' ) );
		}

		$settings = (array) get_option( self::OPTION_KEY, array() );
		$to       = ! empty( $settings['notification_email'] ) ? $settings['notification_email'] : (string) get_option( 'admin_email' );
		$subject  = __( 'WP Career Board — Test Email', 'wp-career-board' );
		$message  = __( 'This is a test email from WP Career Board. Your notification email is configured correctly.', 'wp-career-board' );

		$sent = wp_mail( $to, $subject, $message );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'wcb-settings',
					'tab'        => 'notifications',
					'test_email' => $sent ? 'sent' : 'failed',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Return the ordered list of settings tabs.
	 *
	 * Pro extensions add tabs via the `wcb_settings_tabs` filter.
	 *
	 * @since 1.0.0
	 * @return array<string,string> Tab slug => label.
	 */
	private function get_tabs(): array {
		$tabs = array(
			'listings'      => __( 'Job Listings', 'wp-career-board' ),
			'pages'         => __( 'Pages', 'wp-career-board' ),
			'notifications' => __( 'Notifications', 'wp-career-board' ),
			'status'        => __( 'System Status', 'wp-career-board' ),
		);

		/**
		 * Filter the settings tab list.
		 *
		 * Add Pro tabs:
		 *   add_filter( 'wcb_settings_tabs', function( $tabs ) {
		 *       $tabs['credits'] = __( 'Credits', 'wp-career-board-pro' );
		 *       return $tabs;
		 *   } );
		 *
		 * @since 1.0.0
		 * @param array<string,string> $tabs Tab slug => label.
		 */
		return apply_filters( 'wcb_settings_tabs', $tabs );
	}

	/**
	 * Render the System Status tab (read-only health check panel).
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $settings Current settings.
	 * @return void
	 */
	private function render_status_tab( array $settings ): void {
		$wcb_page_keys   = array( 'jobs_archive_page', 'employer_dashboard_page', 'candidate_dashboard_page', 'post_job_page', 'company_archive_page' );
		$wcb_total_pages = count( $wcb_page_keys );
		$wcb_mapped      = 0;
		foreach ( $wcb_page_keys as $wcb_k ) {
			if ( ! empty( $settings[ $wcb_k ] ) && get_post( (int) $settings[ $wcb_k ] ) ) {
				++$wcb_mapped;
			}
		}

		$wcb_job_counts = wp_count_posts( 'wcb_job' );
		$wcb_pending    = isset( $wcb_job_counts->pending ) ? (int) $wcb_job_counts->pending : 0;
		$wcb_published  = isset( $wcb_job_counts->publish ) ? (int) $wcb_job_counts->publish : 0;
		$wcb_pretty     = ! empty( get_option( 'permalink_structure' ) );

		$wcb_checks = array(
			array(
				'label' => __( 'PHP Version', 'wp-career-board' ),
				'value' => PHP_VERSION,
				'ok'    => version_compare( PHP_VERSION, '8.1', '>=' ),
				'note'  => __( 'Requires PHP 8.1+', 'wp-career-board' ),
			),
			array(
				'label' => __( 'WordPress Version', 'wp-career-board' ),
				'value' => get_bloginfo( 'version' ),
				'ok'    => version_compare( (string) get_bloginfo( 'version' ), '6.5', '>=' ),
				'note'  => __( 'Requires WordPress 6.5+', 'wp-career-board' ),
			),
			array(
				'label' => __( 'Pretty Permalinks', 'wp-career-board' ),
				'value' => $wcb_pretty ? __( 'Enabled', 'wp-career-board' ) : __( 'Disabled (plain)', 'wp-career-board' ),
				'ok'    => $wcb_pretty,
				'note'  => __( 'Required for REST API and block routing', 'wp-career-board' ),
			),
			array(
				'label' => __( 'REST API', 'wp-career-board' ),
				'value' => $wcb_pretty ? __( 'Available', 'wp-career-board' ) : __( 'Unavailable', 'wp-career-board' ),
				'ok'    => $wcb_pretty,
				'note'  => __( 'Required for all Interactivity API blocks', 'wp-career-board' ),
			),
			array(
				'label' => __( 'Pages Mapped', 'wp-career-board' ),
				'value' => $wcb_mapped . ' / ' . $wcb_total_pages,
				'ok'    => $wcb_mapped === $wcb_total_pages,
				'note'  => __( 'Go to the Pages tab to assign missing pages', 'wp-career-board' ),
			),
			array(
				'label' => __( 'Published Jobs', 'wp-career-board' ),
				'value' => (string) $wcb_published,
				'ok'    => true,
				'note'  => '',
			),
			array(
				'label' => __( 'Pending Review', 'wp-career-board' ),
				'value' => (string) $wcb_pending,
				'ok'    => 0 === $wcb_pending,
				'note'  => $wcb_pending > 0 ? __( 'Jobs awaiting approval under Career Board → Jobs', 'wp-career-board' ) : '',
			),
		);
		?>
		<h2><?php esc_html_e( 'System Status', 'wp-career-board' ); ?></h2>
		<p class="description" style="margin-bottom:16px">
			<?php esc_html_e( 'A snapshot of your WP Career Board setup. All checks should be green before going live.', 'wp-career-board' ); ?>
		</p>
		<table class="widefat striped" style="max-width:760px">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Check', 'wp-career-board' ); ?></th>
					<th><?php esc_html_e( 'Value', 'wp-career-board' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-career-board' ); ?></th>
					<th><?php esc_html_e( 'Note', 'wp-career-board' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $wcb_checks as $wcb_check ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $wcb_check['label'] ); ?></strong></td>
						<td><code><?php echo esc_html( $wcb_check['value'] ); ?></code></td>
						<td>
							<?php if ( $wcb_check['ok'] ) : ?>
								<span style="color:#46b450;font-weight:600">&#10003; <?php esc_html_e( 'OK', 'wp-career-board' ); ?></span>
							<?php else : ?>
								<span style="color:#dc3232;font-weight:600">&#10007; <?php esc_html_e( 'Attention', 'wp-career-board' ); ?></span>
							<?php endif; ?>
						</td>
						<td><small><?php echo esc_html( $wcb_check['note'] ); ?></small></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p style="margin-top:16px">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-settings&tab=pages' ) ); ?>" class="button">
				<?php esc_html_e( '→ Go to Pages tab', 'wp-career-board' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>" class="button" style="margin-left:8px">
				<?php esc_html_e( '→ Permalink Settings', 'wp-career-board' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render(): void {
		$settings           = (array) get_option( self::OPTION_KEY, array() );
		$salary_currency    = isset( $settings['salary_currency'] ) ? $settings['salary_currency'] : 'USD';
		$notification_email = ! empty( $settings['notification_email'] ) ? $settings['notification_email'] : (string) get_option( 'admin_email', '' );
		$from_name          = ! empty( $settings['from_name'] ) ? $settings['from_name'] : (string) get_option( 'blogname', '' );
		$from_email         = ! empty( $settings['from_email'] ) ? $settings['from_email'] : (string) get_option( 'admin_email', '' );

		$wcb_tabs       = $this->get_tabs();
		$wcb_active_tab = ( isset( $_GET['tab'] ) && array_key_exists( sanitize_key( $_GET['tab'] ), $wcb_tabs ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_key( $_GET['tab'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: 'listings';

		$wcb_page_keys = array( 'jobs_archive_page', 'employer_dashboard_page', 'candidate_dashboard_page', 'post_job_page', 'company_archive_page' );
		$wcb_missing   = array();
		foreach ( $wcb_page_keys as $wcb_k ) {
			if ( empty( $settings[ $wcb_k ] ) || ! get_post( (int) $settings[ $wcb_k ] ) ) {
				$wcb_missing[] = $wcb_k;
			}
		}

		// Tabs that save via options.php.
		$wcb_settings_tabs = array( 'listings', 'pages', 'notifications' );
		$wcb_is_form_tab   = in_array( $wcb_active_tab, $wcb_settings_tabs, true );
		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'WP Career Board — Settings', 'wp-career-board' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-setup' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Run Setup Wizard', 'wp-career-board' ); ?></a>
			</h1>

			<?php if ( isset( $_GET['settings-updated'] ) && $wcb_is_form_tab ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'wp-career-board' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['created'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Missing pages created and assigned successfully.', 'wp-career-board' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['test_email'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<?php if ( 'sent' === $_GET['test_email'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<div class="notice notice-success is-dismissible">
						<p><?php esc_html_e( 'Test email sent. Please check your inbox.', 'wp-career-board' ); ?></p>
					</div>
				<?php else : ?>
					<div class="notice notice-error is-dismissible">
						<p><?php esc_html_e( 'Test email failed. Check your server mail configuration.', 'wp-career-board' ); ?></p>
					</div>
				<?php endif; ?>
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

			<!-- ── Tab navigation ──────────────────────────────────────────── -->
			<nav class="nav-tab-wrapper" style="margin-bottom:0">
				<?php foreach ( $wcb_tabs as $wcb_slug => $wcb_label ) : ?>
					<a
						href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									'page' => 'wcb-settings',
									'tab'  => $wcb_slug,
								),
								admin_url( 'admin.php' )
							)
						);
						?>
								"
						class="nav-tab<?php echo $wcb_active_tab === $wcb_slug ? ' nav-tab-active' : ''; ?>"
					><?php echo esc_html( $wcb_label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<!-- ── Settings form (listings / pages / notifications tabs only) ─ -->
			<?php if ( $wcb_is_form_tab ) : ?>
			<form method="post" action="options.php" style="margin-top:1rem">
				<?php settings_fields( 'wcb_settings_group' ); ?>

				<?php if ( 'listings' === $wcb_active_tab ) : ?>

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
							<th scope="row"><label for="wcb-jobs-per-page"><?php esc_html_e( 'Jobs Per Page', 'wp-career-board' ); ?></label></th>
							<td>
								<input
									type="number"
									id="wcb-jobs-per-page"
									name="wcb_settings[jobs_per_page]"
									value="<?php echo isset( $settings['jobs_per_page'] ) ? (int) $settings['jobs_per_page'] : 10; ?>"
									min="1"
									max="100"
									style="width:80px"
								>
								<p class="description"><?php esc_html_e( 'Number of job listings shown per page in the job board block. Maximum 100.', 'wp-career-board' ); ?></p>
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
								<p class="description"><?php esc_html_e( 'Jobs are automatically closed after this many days.', 'wp-career-board' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Deadline Auto-Close', 'wp-career-board' ); ?></th>
							<td>
								<label>
									<input
										type="checkbox"
										name="wcb_settings[deadline_auto_close]"
										value="1"
										<?php checked( ! empty( $settings['deadline_auto_close'] ) ); ?>
									>
									<?php esc_html_e( 'Automatically close jobs when their application deadline passes', 'wp-career-board' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Runs via WP-Cron. Closed jobs remain visible but show "Applications Closed".', 'wp-career-board' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Allow Withdraw', 'wp-career-board' ); ?></th>
							<td>
								<label>
									<input
										type="checkbox"
										name="wcb_settings[allow_withdraw]"
										value="1"
										<?php checked( ! empty( $settings['allow_withdraw'] ) ); ?>
									>
									<?php esc_html_e( 'Let candidates withdraw their own applications', 'wp-career-board' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Withdrawn applications are removed from the employer\'s applicant list.', 'wp-career-board' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wcb-salary-currency"><?php esc_html_e( 'Default Salary Currency', 'wp-career-board' ); ?></label></th>
							<td>
								<select id="wcb-salary-currency" name="wcb_settings[salary_currency]">
									<?php foreach ( self::CURRENCIES as $wcb_code => $wcb_label ) : ?>
										<option value="<?php echo esc_attr( $wcb_code ); ?>" <?php selected( $salary_currency, $wcb_code ); ?>>
											<?php echo esc_html( $wcb_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Site-wide default for new job postings. Employers can override it per job.', 'wp-career-board' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>

				<?php elseif ( 'pages' === $wcb_active_tab ) : ?>

					<h2><?php esc_html_e( 'Pages', 'wp-career-board' ); ?></h2>
					<p class="description" style="margin-bottom:12px">
						<?php esc_html_e( 'Assign the WordPress pages that contain each WP Career Board block. Use the "Create Missing Pages" button above to auto-create unassigned pages.', 'wp-career-board' ); ?>
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
							'company_archive_page'     => array(
								'label' => __( 'Company Directory Page', 'wp-career-board' ),
								'desc'  => __( 'Contains the wcb/company-archive block. Lists all employer company profiles.', 'wp-career-board' ),
							),
						);

						/**
						 * Filter the page settings rows displayed on the Pages tab.
						 *
						 * Pro uses this to inject the Resume Builder page setting.
						 *
						 * @since 1.0.0
						 * @param array<string, array{label:string, desc:string}> $wcb_page_settings
						 */
						$wcb_page_settings = (array) apply_filters( 'wcb_page_settings', $wcb_page_settings );

						foreach ( $wcb_page_settings as $wcb_key => $wcb_info ) :
							?>
							<tr>
								<th scope="row">
									<label for="wcb-page-<?php echo esc_attr( sanitize_key( $wcb_key ) ); ?>">
										<?php echo esc_html( $wcb_info['label'] ); ?>
									</label>
								</th>
								<td>
									<?php
									wp_dropdown_pages(
										array(
											'id'       => 'wcb-page-' . sanitize_key( $wcb_key ),
											'name'     => 'wcb_settings[' . sanitize_key( $wcb_key ) . ']',
											'selected' => isset( $settings[ $wcb_key ] ) ? (int) $settings[ $wcb_key ] : 0,
											'show_option_none' => esc_html__( '— Select a page —', 'wp-career-board' ),
										)
									);
									$wcb_mapped_id = ! empty( $settings[ $wcb_key ] ) ? (int) $settings[ $wcb_key ] : 0;
									if ( $wcb_mapped_id && get_post( $wcb_mapped_id ) ) {
										$wcb_page_url = get_permalink( $wcb_mapped_id );
										if ( $wcb_page_url ) {
											echo ' <a href="' . esc_url( $wcb_page_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View Page →', 'wp-career-board' ) . '</a>';
										}
									}
									?>
									<p class="description"><?php echo esc_html( $wcb_info['desc'] ); ?></p>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
					<?php submit_button(); ?>

				<?php elseif ( 'notifications' === $wcb_active_tab ) : ?>

					<h2><?php esc_html_e( 'Notifications', 'wp-career-board' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="wcb-from-name"><?php esc_html_e( 'From Name', 'wp-career-board' ); ?></label></th>
							<td>
								<input
									type="text"
									id="wcb-from-name"
									name="wcb_settings[from_name]"
									value="<?php echo esc_attr( $from_name ); ?>"
									class="regular-text"
								>
								<p class="description"><?php esc_html_e( 'Sender name shown on all WCB notification emails. Defaults to your site name.', 'wp-career-board' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wcb-from-email"><?php esc_html_e( 'From Email', 'wp-career-board' ); ?></label></th>
							<td>
								<input
									type="email"
									id="wcb-from-email"
									name="wcb_settings[from_email]"
									value="<?php echo esc_attr( $from_email ); ?>"
									class="regular-text"
								>
								<p class="description"><?php esc_html_e( 'Sender address for all WCB emails. Defaults to the site admin email.', 'wp-career-board' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wcb-notification-email"><?php esc_html_e( 'Admin Notification Email', 'wp-career-board' ); ?></label></th>
							<td>
								<input
									type="email"
									id="wcb-notification-email"
									name="wcb_settings[notification_email]"
									value="<?php echo esc_attr( $notification_email ); ?>"
									class="regular-text"
									required
								>
								<p class="description"><?php esc_html_e( 'Where admin alerts (new jobs, flagged content) are sent. Defaults to the site admin email.', 'wp-career-board' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>

				<?php endif; ?>
			</form>
			<?php endif; ?>

			<!-- ── Send Test Email (Notifications tab, separate form) ───────── -->
			<?php if ( 'notifications' === $wcb_active_tab ) : ?>
				<hr style="margin:2rem 0 1.5rem">
				<h3><?php esc_html_e( 'Send Test Email', 'wp-career-board' ); ?></h3>
				<p><?php esc_html_e( 'Send a test email to the admin notification address to verify your mail configuration.', 'wp-career-board' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wcb_send_test_email">
					<?php wp_nonce_field( 'wcb_send_test_email' ); ?>
					<?php submit_button( __( 'Send Test Email', 'wp-career-board' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>

			<!-- ── System Status tab (read-only, no form) ───────────────────── -->
			<?php if ( 'status' === $wcb_active_tab ) : ?>
				<div style="margin-top:1.5rem">
					<?php $this->render_status_tab( $settings ); ?>
				</div>
			<?php endif; ?>

			<!-- ── Pro / custom tabs ────────────────────────────────────────── -->
			<?php if ( ! $wcb_is_form_tab && 'status' !== $wcb_active_tab ) : ?>
				<div style="margin-top:1.5rem">
					<?php
					/**
					 * Action: render content for a Pro or custom settings tab.
					 *
					 * Hook example:
					 *   add_action( 'wcb_settings_tab_credits', function( $settings ) {
					 *       // render credits settings table + submit_button()
					 *   } );
					 *
					 * @since 1.0.0
					 * @param array<string,mixed> $settings Current WCB settings.
					 */
					do_action( 'wcb_settings_tab_' . $wcb_active_tab, $settings );
					?>
				</div>
			<?php endif; ?>

		</div>
		<?php
	}
}
