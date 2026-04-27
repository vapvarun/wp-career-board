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
	 * @var   string
	 */
	const OPTION_KEY = 'wcb_settings';

	/**
	 * Allowed currency codes.
	 *
	 * @since 1.0.0
	 * @var   array<string,string>
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
	 * @since  1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_wcb_create_pages', array( $this, 'handle_create_pages' ) );
		add_filter( 'wp_mail_from', array( $this, 'mail_from' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'mail_from_name' ) );
		add_action( 'wcb_settings_tab_emails', array( $this, 'render_emails_tab' ) );
		add_action( 'wcb_settings_tab_import', array( $this, 'render_import_tab' ) );
	}

	/**
	 * Register the WCB settings group with WordPress.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'wcb_settings_group',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
				'autoload'          => false,
			)
		);
	}

	/**
	 * Sanitize submitted settings values.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed $input Raw input from the settings form.
	 * @return array<string,mixed>
	 */
	public function sanitize( mixed $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$existing = (array) get_option( self::OPTION_KEY, array() );

		// Determine which tab was submitted based on which fields are present.
		$tab_fields = array(
			'listings'      => array( 'auto_publish_jobs', 'jobs_per_page', 'jobs_expire_days', 'deadline_auto_close', 'allow_withdraw', 'salary_currency', 'apply_resume_required', 'apply_resume_max_mb' ),
			'pages'         => array( 'jobs_archive_page', 'employer_dashboard_page', 'candidate_dashboard_page', 'company_archive_page' ),
			'notifications' => array( 'notification_email', 'from_name', 'from_email' ),
		);

		// Sanitize every known key; use submitted value when present, otherwise keep existing.
		$sanitized = array(
			'auto_publish_jobs'        => ! empty( $input['auto_publish_jobs'] ),
			'jobs_per_page'            => isset( $input['jobs_per_page'] ) ? max( 1, min( 100, (int) $input['jobs_per_page'] ) ) : 10,
			'jobs_expire_days'         => isset( $input['jobs_expire_days'] ) ? max( 1, (int) $input['jobs_expire_days'] ) : 30,
			'deadline_auto_close'      => ! empty( $input['deadline_auto_close'] ),
			'allow_withdraw'           => ! empty( $input['allow_withdraw'] ),
			'apply_resume_required'    => ! empty( $input['apply_resume_required'] ),
			'apply_resume_max_mb'      => isset( $input['apply_resume_max_mb'] ) ? max( 1, min( 20, (int) $input['apply_resume_max_mb'] ) ) : 5,
			'salary_currency'          => isset( $input['salary_currency'] ) && array_key_exists( $input['salary_currency'], self::CURRENCIES ) ? $input['salary_currency'] : 'USD',
			'jobs_archive_page'        => isset( $input['jobs_archive_page'] ) ? (int) $input['jobs_archive_page'] : 0,
			'employer_dashboard_page'  => isset( $input['employer_dashboard_page'] ) ? (int) $input['employer_dashboard_page'] : 0,
			'candidate_dashboard_page' => isset( $input['candidate_dashboard_page'] ) ? (int) $input['candidate_dashboard_page'] : 0,
			'company_archive_page'     => isset( $input['company_archive_page'] ) ? (int) $input['company_archive_page'] : 0,
			'notification_email'       => isset( $input['notification_email'] ) ? sanitize_email( $input['notification_email'] ) : '',
			'from_name'                => isset( $input['from_name'] ) ? sanitize_text_field( $input['from_name'] ) : '',
			'from_email'               => isset( $input['from_email'] ) ? sanitize_email( $input['from_email'] ) : '',
		);

		// Identify which tab was submitted by checking for its fields in $input.
		$submitted_tab = '';
		foreach ( $tab_fields as $tab => $fields ) {
			foreach ( $fields as $field ) {
				if ( array_key_exists( $field, $input ) ) {
					$submitted_tab = $tab;
					break 2;
				}
			}
		}

		// Start from existing settings, then overlay only the submitted tab's keys.
		$output = $existing;
		if ( $submitted_tab ) {
			foreach ( $tab_fields[ $submitted_tab ] as $field ) {
				$output[ $field ] = $sanitized[ $field ];
			}
		} else {
			// Fallback: unknown tab or full-form submission — apply all sanitized keys.
			$output = array_merge( $existing, $sanitized );
		}

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
	 * @param  string $email Default sender address.
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
	 * @param  string $name Default sender name.
	 * @return string
	 */
	public function mail_from_name( string $name ): string {
		$settings = (array) get_option( self::OPTION_KEY, array() );
		return ! empty( $settings['from_name'] ) ? $settings['from_name'] : $name;
	}

	/**
	 * Handle the "Create Missing Pages" form POST.
	 *
	 * @since  1.0.0
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

		update_option( self::OPTION_KEY, $settings, false );

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
	 * Return the ordered list of settings tabs.
	 *
	 * Pro extensions add tabs via the `wcb_settings_tabs` filter.
	 *
	 * @since  1.0.0
	 * @return array<string,string> Tab slug => label.
	 */
	private function get_tabs(): array {
		$tabs = array(
			'listings'      => __( 'Job Listings', 'wp-career-board' ),
			'pages'         => __( 'Pages', 'wp-career-board' ),
			'notifications' => __( 'Notifications', 'wp-career-board' ),
			'emails'        => __( 'Emails', 'wp-career-board' ),
			'import'        => __( 'Import', 'wp-career-board' ),
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
	 * Lucide icon names for each known sidebar nav item.
	 *
	 * @since  1.0.0
	 * @return array<string,string> Tab slug => Lucide icon name.
	 */
	private function get_tab_icons(): array {
		return array(
			'listings'      => 'list',
			'pages'         => 'file-text',
			'import'        => 'upload',
			'antispam'      => 'shield',
			'notifications' => 'bell',
			'emails'        => 'mail',
			'boards'        => 'layout-grid',
			'field-builder' => 'wrench',
			'ai-settings'   => 'sparkles',
			'job-feed'      => 'rss',
			'credits'       => 'credit-card',
			'pipeline'      => 'kanban',
			'integrations'  => 'puzzle',
			'license'       => 'key-round',
		);
	}

	/**
	 * Return the nav-group assignment for known Free tabs.
	 *
	 * Tabs not listed here are assumed to be "pro" group.
	 *
	 * @since  1.0.0
	 * @return array<string,string> Tab slug => group key.
	 */
	private function get_free_tab_slugs(): array {
		return array(
			'listings'      => 'general',
			'pages'         => 'general',
			'notifications' => 'general',
			'emails'        => 'general',
			'import'        => 'general',
			'antispam'      => 'general',
		);
	}

	/**
	 * Pro feature teaser tabs shown in the sidebar when Pro is not active.
	 *
	 * @since  1.0.0
	 * @return array<string,array{label:string}> Keyed by slug.
	 */
	private function get_pro_teaser_tabs(): array {
		return array(
			'pipeline'      => array( 'label' => __( 'Pipeline', 'wp-career-board' ) ),
			'credits'       => array( 'label' => __( 'Credits', 'wp-career-board' ) ),
			'field-builder' => array( 'label' => __( 'Field Builder', 'wp-career-board' ) ),
			'ai-settings'   => array( 'label' => __( 'AI Settings', 'wp-career-board' ) ),
			'job-feed'      => array( 'label' => __( 'Job Feed', 'wp-career-board' ) ),
			'boards'        => array( 'label' => __( 'Boards', 'wp-career-board' ) ),
		);
	}

	/**
	 * Render the Emails tab — delegates to EmailSettings::render_form().
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_emails_tab(): void {
		( new EmailSettings() )->render_form();
	}

	/**
	 * Render the Import tab — delegates to AdminImport::render().
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_import_tab(): void {
		( new AdminImport() )->render();

		// Sample data removal section.
		if ( get_option( 'wcb_sample_data_installed', false ) ) :
			?>
			<div class="wcb-settings-section__block" style="margin-top: 2rem;">
				<h3><?php esc_html_e( 'Sample Data', 'wp-career-board' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Remove the demo companies and jobs created by the setup wizard.', 'wp-career-board' ); ?></p>
				<button type="button" id="wcb-remove-sample-data" class="button button-secondary" style="margin-top: 0.5rem;">
			<?php esc_html_e( 'Remove Sample Data', 'wp-career-board' ); ?>
				</button>
				<span id="wcb-remove-sample-status" style="margin-left: 0.5rem;"></span>
				<script>
				document.getElementById('wcb-remove-sample-data')?.addEventListener('click', function() {
					var btn = this;
					var status = document.getElementById('wcb-remove-sample-status');
					btn.disabled = true;
					btn.textContent = '<?php echo esc_js( __( 'Removing…', 'wp-career-board' ) ); ?>';
					fetch(wcbAdmin.restUrl + 'wizard/remove-sample-data', {
						method: 'POST',
						headers: { 'X-WP-Nonce': wcbAdmin.restNonce, 'Content-Type': 'application/json' },
					})
					.then(function(r) { return r.json(); })
					.then(function(data) {
						status.textContent = '<?php echo esc_js( __( 'Removed!', 'wp-career-board' ) ); ?>';
						btn.closest('.wcb-settings-section__block').style.display = 'none';
					})
					.catch(function() {
						status.textContent = '<?php echo esc_js( __( 'Error — please try again.', 'wp-career-board' ) ); ?>';
						btn.disabled = false;
						btn.textContent = '<?php echo esc_js( __( 'Remove Sample Data', 'wp-career-board' ) ); ?>';
					});
				});
				</script>
			</div>
			<?php
		endif;
	}

	/**
	 * Render the settings page with sidebar navigation.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render(): void {
		$settings           = (array) get_option( self::OPTION_KEY, array() );
		$salary_currency    = isset( $settings['salary_currency'] ) ? $settings['salary_currency'] : 'USD';
		$notification_email = ! empty( $settings['notification_email'] ) ? $settings['notification_email'] : (string) get_option( 'admin_email', '' );
		$from_name          = ! empty( $settings['from_name'] ) ? $settings['from_name'] : (string) get_option( 'blogname', '' );
		$from_email         = ! empty( $settings['from_email'] ) ? $settings['from_email'] : (string) get_option( 'admin_email', '' );

		$wcb_tabs      = $this->get_tabs();
		$wcb_tab_icons = $this->get_tab_icons();
		$wcb_free_tabs = $this->get_free_tab_slugs();

		$wcb_page_keys = array( 'jobs_archive_page', 'employer_dashboard_page', 'candidate_dashboard_page', 'company_archive_page' );
		$wcb_missing   = array();
		foreach ( $wcb_page_keys as $wcb_k ) {
			if ( empty( $settings[ $wcb_k ] ) || ! get_post( (int) $settings[ $wcb_k ] ) ) {
				$wcb_missing[] = $wcb_k;
			}
		}

		// Group tabs for the sidebar.
		$wcb_groups = array(
			'general' => array(
				'label' => __( 'General', 'wp-career-board' ),
				'items' => array(),
			),
			'pro'     => array(
				'label' => __( 'Pro', 'wp-career-board' ),
				'items' => array(),
			),
		);

		foreach ( $wcb_tabs as $wcb_slug => $wcb_label ) {
			$wcb_group = $wcb_free_tabs[ $wcb_slug ] ?? 'pro';
			if ( ! isset( $wcb_groups[ $wcb_group ] ) ) {
				$wcb_group = 'pro';
			}
			$wcb_groups[ $wcb_group ]['items'][ $wcb_slug ] = $wcb_label;
		}

		// Remove empty groups.
		$wcb_groups = array_filter(
			$wcb_groups,
			static fn( array $g ): bool => ! empty( $g['items'] )
		);

		// Tabs that save via options.php (Settings API).
		$wcb_settings_tabs = array( 'listings', 'pages', 'notifications' );
		?>
		<div class="wrap wcb-admin">

			<h1 class="screen-reader-text"><?php esc_html_e( 'Career Board Settings', 'wp-career-board' ); ?></h1>

		<?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success wcb-notice is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'wp-career-board' ); ?></p>
				</div>
		<?php endif; ?>

		<?php if ( isset( $_GET['wcbp_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success wcb-notice is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'wp-career-board' ); ?></p>
				</div>
		<?php endif; ?>

		<?php if ( isset( $_GET['created'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success wcb-notice is-dismissible">
					<p><?php esc_html_e( 'Missing pages created and assigned successfully.', 'wp-career-board' ); ?></p>
				</div>
		<?php endif; ?>

		<?php if ( isset( $_GET['test_email'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php if ( 'sent' === $_GET['test_email'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<div class="notice notice-success wcb-notice is-dismissible">
						<p><?php esc_html_e( 'Test email sent. Please check your inbox.', 'wp-career-board' ); ?></p>
					</div>
				<?php else : ?>
					<div class="notice notice-error wcb-notice is-dismissible">
						<p><?php esc_html_e( 'Test email failed. Check your server mail configuration.', 'wp-career-board' ); ?></p>
					</div>
				<?php endif; ?>
		<?php endif; ?>

		<?php if ( ! empty( $wcb_missing ) ) : ?>
			<?php
			$wcb_missing_labels = array(
				'jobs_archive_page'        => __( 'Find Jobs', 'wp-career-board' ),
				'employer_dashboard_page'  => __( 'Employer Dashboard', 'wp-career-board' ),
				'candidate_dashboard_page' => __( 'Candidate Dashboard', 'wp-career-board' ),
				'company_archive_page'     => __( 'Companies', 'wp-career-board' ),
			);
			$wcb_missing_names  = array_map( static fn( string $k ): string => $wcb_missing_labels[ $k ] ?? $k, $wcb_missing );
			?>
				<div class="notice notice-warning wcb-notice">
					<p>
						<strong><?php esc_html_e( 'Missing pages:', 'wp-career-board' ); ?></strong>
			<?php echo esc_html( implode( ', ', $wcb_missing_names ) ); ?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 6px;">
						<input type="hidden" name="action" value="wcb_create_pages">
			<?php wp_nonce_field( 'wcb_create_pages' ); ?>
			<?php submit_button( __( 'Create Missing Pages', 'wp-career-board' ), 'primary', 'submit', false ); ?>
					</form>
				</div>
		<?php endif; ?>

			<div class="wcb-page-header">
				<div class="wcb-page-header__left">
					<h2 class="wcb-page-header__title">
						<i data-lucide="settings" class="wcb-icon--lg"></i>
		<?php esc_html_e( 'WP Career Board', 'wp-career-board' ); ?>
						<span class="wcb-version-badge">v<?php echo esc_html( WCB_VERSION ); ?></span>
					</h2>
					<p class="wcb-page-header__desc"><?php esc_html_e( 'Career Board settings and configuration', 'wp-career-board' ); ?></p>
				</div>
				<div class="wcb-page-header__actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-setup' ) ); ?>" class="wcb-btn">
						<i data-lucide="settings" class="wcb-icon--sm"></i>
		<?php esc_html_e( 'Run Setup Wizard', 'wp-career-board' ); ?>
					</a>
				</div>
			</div>

			<!-- ── Sidebar + Content layout ─────────────────────────────────── -->
			<div class="wcb-settings-wrap">

				<!-- Sidebar -->
				<aside class="wcb-settings-sidebar">
					<div class="wcb-settings-sidebar__brand">
						<span class="wcb-settings-sidebar__logo"><i data-lucide="briefcase"></i></span>
						<span class="wcb-settings-sidebar__brand-text">
							<strong><?php esc_html_e( 'Career Board', 'wp-career-board' ); ?></strong>
							<span>v<?php echo esc_html( WCB_VERSION ); ?></span>
						</span>
					</div>
		<?php foreach ( $wcb_groups as $wcb_gkey => $wcb_group ) : ?>
					<nav class="wcb-settings-nav-group" aria-label="<?php echo esc_attr( $wcb_group['label'] ); ?>">
						<span class="wcb-settings-nav-group__label"><?php echo esc_html( $wcb_group['label'] ); ?></span>
			<?php foreach ( $wcb_group['items'] as $wcb_slug => $wcb_label ) : ?>
							<a href="#<?php echo esc_attr( $wcb_slug ); ?>" class="wcb-settings-nav-item" data-section="<?php echo esc_attr( $wcb_slug ); ?>">
								<i data-lucide="<?php echo esc_attr( $wcb_tab_icons[ $wcb_slug ] ?? 'settings' ); ?>"></i>
				<?php echo esc_html( $wcb_label ); ?>
				<?php if ( 'pro' === $wcb_gkey ) : ?>
									<span class="wcb-pro-badge"><?php esc_html_e( 'Pro', 'wp-career-board' ); ?></span>
				<?php endif; ?>
							</a>
			<?php endforeach; ?>
					</nav>
		<?php endforeach; ?>
		<?php if ( ! defined( 'WCBP_VERSION' ) && ! class_exists( 'WCB\Pro\Core\ProPlugin' ) ) : ?>
			<?php $wcb_pro_teasers = $this->get_pro_teaser_tabs(); ?>
					<nav class="wcb-settings-nav-group wcb-settings-nav-group--pro-teasers" aria-label="<?php esc_attr_e( 'Pro', 'wp-career-board' ); ?>">
						<span class="wcb-settings-nav-group__label"><?php esc_html_e( 'Pro', 'wp-career-board' ); ?></span>
			<?php foreach ( $wcb_pro_teasers as $wcb_teaser_slug => $wcb_teaser ) : ?>
							<a href="<?php echo esc_url( 'https://store.wbcomdesigns.com/wp-career-board-pro/' ); ?>"
								target="_blank"
								rel="noopener noreferrer"
								class="wcb-settings-nav-item wcb-settings-nav-item--teaser"
								aria-label="<?php echo esc_attr( sprintf( '%s — %s', $wcb_teaser['label'], __( 'Requires Pro', 'wp-career-board' ) ) ); ?>">
								<i data-lucide="<?php echo esc_attr( $wcb_tab_icons[ $wcb_teaser_slug ] ?? 'lock' ); ?>"></i>
				<?php echo esc_html( $wcb_teaser['label'] ); ?>
								<i data-lucide="lock" class="wcb-icon wcb-nav-lock-icon"></i>
							</a>
			<?php endforeach; ?>
					</nav>
		<?php endif; ?>
				</aside>

				<!-- Content -->
				<div class="wcb-settings-content">

					<!-- ── Listings ─────────────────────────────────────────── -->
					<div class="wcb-settings-section" id="section-listings">
						<form method="post" action="options.php">
		<?php settings_fields( 'wcb_settings_group' ); ?>
							<div class="wcb-card">
								<div class="wcb-card__head">
									<p class="wcb-card__title"><?php esc_html_e( 'Job Listings', 'wp-career-board' ); ?></p>
									<p class="wcb-card__desc"><?php esc_html_e( 'Configure job listing behavior and defaults.', 'wp-career-board' ); ?></p>
								</div>
								<div class="wcb-card__body">
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><?php esc_html_e( 'Auto-Publish Jobs', 'wp-career-board' ); ?></div>
										<div class="wcb-settings-row-control">
											<label class="wcb-toggle-label">
												<span class="wcb-toggle">
													<input type="checkbox" name="wcb_settings[auto_publish_jobs]" value="1" <?php checked( ! empty( $settings['auto_publish_jobs'] ) ); ?>>
													<span class="wcb-toggle-slider"></span>
												</span>
												<?php esc_html_e( 'Publish jobs immediately without admin review', 'wp-career-board' ); ?>
											</label>
											<span class="description"><?php esc_html_e( 'When unchecked, new jobs are held as "Pending" until approved under Career Board > Jobs.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><label for="wcb-jobs-per-page"><?php esc_html_e( 'Jobs Per Page', 'wp-career-board' ); ?></label></div>
										<div class="wcb-settings-row-control">
											<input type="number" id="wcb-jobs-per-page" name="wcb_settings[jobs_per_page]" value="<?php echo isset( $settings['jobs_per_page'] ) ? (int) $settings['jobs_per_page'] : 10; ?>" min="1" max="100" style="width:80px">
											<span class="description"><?php esc_html_e( 'Number of job listings shown per page in the job board block. Maximum 100.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><label for="wcb-jobs-expire-days"><?php esc_html_e( 'Job Expiry (days)', 'wp-career-board' ); ?></label></div>
										<div class="wcb-settings-row-control">
											<input type="number" id="wcb-jobs-expire-days" name="wcb_settings[jobs_expire_days]" value="<?php echo isset( $settings['jobs_expire_days'] ) ? (int) $settings['jobs_expire_days'] : 30; ?>" min="1" max="365" style="width:80px">
											<span class="description"><?php esc_html_e( 'Jobs are automatically closed after this many days.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><?php esc_html_e( 'Deadline Auto-Close', 'wp-career-board' ); ?></div>
										<div class="wcb-settings-row-control">
											<label class="wcb-toggle-label">
												<span class="wcb-toggle">
													<input type="checkbox" name="wcb_settings[deadline_auto_close]" value="1" <?php checked( ! empty( $settings['deadline_auto_close'] ) ); ?>>
													<span class="wcb-toggle-slider"></span>
												</span>
												<?php esc_html_e( 'Automatically close jobs when their application deadline passes', 'wp-career-board' ); ?>
											</label>
											<span class="description"><?php esc_html_e( 'Runs via WP-Cron. Closed jobs remain visible but show "Applications Closed".', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><?php esc_html_e( 'Allow Withdraw', 'wp-career-board' ); ?></div>
										<div class="wcb-settings-row-control">
											<label class="wcb-toggle-label">
												<span class="wcb-toggle">
													<input type="checkbox" name="wcb_settings[allow_withdraw]" value="1" <?php checked( ! empty( $settings['allow_withdraw'] ) ); ?>>
													<span class="wcb-toggle-slider"></span>
												</span>
												<?php esc_html_e( 'Let candidates withdraw their own applications', 'wp-career-board' ); ?>
											</label>
											<span class="description"><?php esc_html_e( 'Withdrawn applications are removed from the employer\'s applicant list.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><label for="wcb-salary-currency"><?php esc_html_e( 'Default Salary Currency', 'wp-career-board' ); ?></label></div>
										<div class="wcb-settings-row-control">
											<select id="wcb-salary-currency" name="wcb_settings[salary_currency]">
												<?php foreach ( self::CURRENCIES as $wcb_code => $wcb_label ) : ?>
													<option value="<?php echo esc_attr( $wcb_code ); ?>" <?php selected( $salary_currency, $wcb_code ); ?>><?php echo esc_html( $wcb_label ); ?></option>
												<?php endforeach; ?>
											</select>
											<span class="description"><?php esc_html_e( 'Site-wide default for new job postings. Employers can override it per job.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><?php esc_html_e( 'Resume Required', 'wp-career-board' ); ?></div>
										<div class="wcb-settings-row-control">
											<label class="wcb-toggle-label">
												<span class="wcb-toggle">
													<input type="checkbox" name="wcb_settings[apply_resume_required]" value="1" <?php checked( ! empty( $settings['apply_resume_required'] ) ); ?>>
													<span class="wcb-toggle-slider"></span>
												</span>
												<?php esc_html_e( 'Require applicants to attach a resume', 'wp-career-board' ); ?>
											</label>
											<span class="description"><?php esc_html_e( 'When off, the resume field is shown but optional.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><label for="wcb-resume-max-mb"><?php esc_html_e( 'Resume Max Size (MB)', 'wp-career-board' ); ?></label></div>
										<div class="wcb-settings-row-control">
											<input
												id="wcb-resume-max-mb"
												type="number"
												name="wcb_settings[apply_resume_max_mb]"
												value="<?php echo esc_attr( (string) ( isset( $settings['apply_resume_max_mb'] ) ? (int) $settings['apply_resume_max_mb'] : 5 ) ); ?>"
												min="1"
												max="20"
												step="1"
											>
											<span class="description"><?php esc_html_e( 'Maximum file size for uploaded resumes (1–20 MB). Accepted formats: PDF, DOC, DOCX.', 'wp-career-board' ); ?></span>
										</div>
									</div>
								</div>
							</div>
							<div class="wcb-settings-section__footer">
								<?php submit_button( __( 'Save Changes', 'wp-career-board' ), 'primary', 'submit', false, array( 'class' => 'wcb-btn wcb-btn--primary' ) ); ?>
							</div>
						</form>
					</div>

					<!-- ── Pages ────────────────────────────────────────────── -->
					<div class="wcb-settings-section" id="section-pages">
						<form method="post" action="options.php">
		<?php settings_fields( 'wcb_settings_group' ); ?>
							<div class="wcb-card">
								<div class="wcb-card__head">
									<p class="wcb-card__title"><?php esc_html_e( 'Pages', 'wp-career-board' ); ?></p>
									<p class="wcb-card__desc"><?php esc_html_e( 'Assign the WordPress pages that contain each WP Career Board block.', 'wp-career-board' ); ?></p>
								</div>
								<div class="wcb-card__body">
									<?php
									$wcb_page_settings = array(
										'jobs_archive_page'    => array(
											'label' => __( 'Jobs Archive Page', 'wp-career-board' ),
											'desc'  => __( 'Contains the wcb/job-listings block. Used as the main job board.', 'wp-career-board' ),
										),
										'employer_dashboard_page' => array(
											'label' => __( 'Employer Dashboard Page', 'wp-career-board' ),
											'desc'  => __( 'Contains the wcb/employer-dashboard block. Employers manage jobs and profiles here.', 'wp-career-board' ),
										),
										'candidate_dashboard_page' => array(
											'label' => __( 'Candidate Dashboard Page', 'wp-career-board' ),
											'desc'  => __( 'Contains the wcb/candidate-dashboard block. Candidates track applications and saved jobs.', 'wp-career-board' ),
										),
										'company_archive_page' => array(
											'label' => __( 'Company Directory Page', 'wp-career-board' ),
											'desc'  => __( 'Contains the wcb/company-archive block. Lists all employer company profiles.', 'wp-career-board' ),
										),
									);

									/**
									 * Filter the page settings configuration.
									 *
									 * @since 1.0.0
									 */
									$wcb_page_settings = (array) apply_filters( 'wcb_page_settings', $wcb_page_settings );
									?>
			<?php foreach ( $wcb_page_settings as $wcb_key => $wcb_info ) : ?>
										<div class="wcb-settings-row">
											<div class="wcb-settings-row-label">
												<label for="wcb-page-<?php echo esc_attr( sanitize_key( $wcb_key ) ); ?>"><?php echo esc_html( $wcb_info['label'] ); ?></label>
											</div>
											<div class="wcb-settings-row-control">
				<?php
				wp_dropdown_pages(
					array(
						'id'               => 'wcb-page-' . sanitize_key( $wcb_key ),
						'name'             => 'wcb_settings[' . sanitize_key( $wcb_key ) . ']',
						'selected'         => isset( $settings[ $wcb_key ] ) ? (int) $settings[ $wcb_key ] : 0,
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
												<span class="description"><?php echo esc_html( $wcb_info['desc'] ); ?></span>
											</div>
										</div>
			<?php endforeach; ?>
								</div>
							</div>
							<div class="wcb-settings-section__footer">
			<?php submit_button( __( 'Save Changes', 'wp-career-board' ), 'primary', 'submit', false, array( 'class' => 'wcb-btn wcb-btn--primary' ) ); ?>
							</div>
						</form>
					</div>

					<!-- ── Notifications ────────────────────────────────────── -->
					<div class="wcb-settings-section" id="section-notifications">
						<form method="post" action="options.php">
			<?php settings_fields( 'wcb_settings_group' ); ?>
							<div class="wcb-card">
								<div class="wcb-card__head">
									<p class="wcb-card__title"><?php esc_html_e( 'Email Configuration', 'wp-career-board' ); ?></p>
									<p class="wcb-card__desc"><?php esc_html_e( 'Configure sender details and admin notification address.', 'wp-career-board' ); ?></p>
								</div>
								<div class="wcb-card__body">
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><label for="wcb-from-name"><?php esc_html_e( 'From Name', 'wp-career-board' ); ?></label></div>
										<div class="wcb-settings-row-control">
											<input type="text" id="wcb-from-name" name="wcb_settings[from_name]" value="<?php echo esc_attr( $from_name ); ?>" class="regular-text">
											<span class="description"><?php esc_html_e( 'Sender name shown on all WCB notification emails. Defaults to your site name.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><label for="wcb-from-email"><?php esc_html_e( 'From Email', 'wp-career-board' ); ?></label></div>
										<div class="wcb-settings-row-control">
											<input type="email" id="wcb-from-email" name="wcb_settings[from_email]" value="<?php echo esc_attr( $from_email ); ?>" class="regular-text">
											<span class="description"><?php esc_html_e( 'Sender address for all WCB emails. Defaults to the site admin email.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><label for="wcb-notification-email"><?php esc_html_e( 'Admin Notification Email', 'wp-career-board' ); ?></label></div>
										<div class="wcb-settings-row-control">
											<input type="email" id="wcb-notification-email" name="wcb_settings[notification_email]" value="<?php echo esc_attr( $notification_email ); ?>" class="regular-text" required>
											<span class="description"><?php esc_html_e( 'Where admin alerts (new jobs, flagged content) are sent. Defaults to the site admin email.', 'wp-career-board' ); ?></span>
										</div>
									</div>
								</div>
							</div>
							<div class="wcb-settings-section__footer">
			<?php submit_button( __( 'Save Changes', 'wp-career-board' ), 'primary', 'submit', false, array( 'class' => 'wcb-btn wcb-btn--primary' ) ); ?>
							</div>
						</form>
					</div>

					<!-- ── Emails ───────────────────────────────────────────── -->
					<div class="wcb-settings-section" id="section-emails">
		<?php
			/**
			 * Render the Emails tab content.
			 *
			 * @since 1.0.0
			 * @param array<string,mixed> $settings Current WCB settings.
			 */
			do_action( 'wcb_settings_tab_emails', $settings );
		?>
					</div>

		<?php
		// Render Pro / extension tab sections.
		$wcb_builtin = array( 'listings', 'pages', 'notifications', 'emails' );
		foreach ( $wcb_tabs as $wcb_slug => $wcb_label ) :
			if ( in_array( $wcb_slug, $wcb_builtin, true ) ) {
				continue;
			}
			?>
						<div class="wcb-settings-section" id="section-<?php echo esc_attr( $wcb_slug ); ?>">
			<?php
			/**
			 * Render content for a Pro or custom settings tab.
			 *
			 * @since 1.0.0
			 * @param array<string,mixed> $settings Current WCB settings.
			 */
			do_action( 'wcb_settings_tab_' . $wcb_slug, $settings );
			?>
						</div>
		<?php endforeach; ?>

				</div><!-- .wcb-settings-content -->
			</div><!-- .wcb-settings-wrap -->

		</div>
		<?php
	}
}
