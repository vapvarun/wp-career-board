<?php
/**
 * Admin settings page — registers, sanitizes, and renders WCB plugin settings.
 *
 * Settings stored as a single serialised array under the 'wcb_settings' option key.
 *
 * Keys and usage:
 *  auto_publish_jobs        — publish employer jobs without admin review (default: OFF)
 *  jobs_per_page            — listings per page in the job-listings block (default: 10)
 *  jobs_expire_days         — default listing lifetime in days (default: 30)
 *  deadline_auto_close      — end jobs at their deadline; on for sites installed on 1.8.0+, switched on from Settings > Jobs for older sites (no UI to turn off)
 *  allow_withdraw           — let candidates withdraw their own applications (default: ON, only an explicit OFF turns it off)
 *  salary_currency          — default currency code for new job postings (default: USD)
 *  apply_resume_required    — require a resume on applications (default: ON, only an explicit OFF turns it off)
 *  jobs_archive_page        — page containing wcb/job-listings block
 *  employer_dashboard_page  — page containing wcb/employer-dashboard block
 *  candidate_dashboard_page — page containing wcb/candidate-dashboard block
 *  post_job_page            — page containing wcb/job-form block
 *  company_archive_page     — page containing wcb/company-archive block
 *  notification_email       — address that receives admin notifications
 *  from_name                — sender name for all WCB notification emails
 *  from_email               — sender address for all WCB notification emails
 *  max_resumes              — maximum resumes a candidate may create (Pro reader; default: 2)
 *  resume_archive_page      — page containing wcb/resume-archive block (Pro reader; default: 0)
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
 * Writer — read raw option to render the form. This class is the sanitize
 * + render pair for the wcb_settings option, so every read here intentionally
 * uses get_option() directly. Reader sites elsewhere route through
 * \WCB\Admin\Settings (the cached accessor); this file is the exception.
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
	 * Canonical currency catalog. Every consumer in Free + Pro reads from
	 * here — admin dropdowns, salary formatters, REST enum, sanitize
	 * allow-lists, Pro board settings, Pro CSV importer. There is no
	 * derivative helper; callers iterate the catalog and pick the
	 * field they need (`['symbol']`, `array_keys(...)`, etc.).
	 *
	 * Pro extends the catalog via the `wcb_currency_catalog` filter and
	 * MUST return the same `array{name, symbol}` shape so every consumer
	 * keeps trusting it.
	 *
	 * @since 1.0.0 (1.1.1: shape became array{name,symbol}; was string label).
	 * @var   array<string,array{name:string,symbol:string}>
	 */
	const CURRENCIES = array(
		'USD' => array(
			'name'   => 'US Dollar',
			'symbol' => '$',
		),
		'EUR' => array(
			'name'   => 'Euro',
			'symbol' => '€',
		),
		'GBP' => array(
			'name'   => 'British Pound',
			'symbol' => '£',
		),
		'CAD' => array(
			'name'   => 'Canadian Dollar',
			'symbol' => 'CA$',
		),
		'AUD' => array(
			'name'   => 'Australian Dollar',
			'symbol' => 'A$',
		),
		'INR' => array(
			'name'   => 'Indian Rupee',
			'symbol' => '₹',
		),
		'SGD' => array(
			'name'   => 'Singapore Dollar',
			'symbol' => 'S$',
		),
	);

	/**
	 * Filtered currency catalog — the single source of truth.
	 *
	 * @since 1.1.1
	 *
	 * @return array<string,array{name:string,symbol:string}>
	 */
	public static function get_currency_catalog(): array {
		/**
		 * Filter the canonical currency catalog. Pro hooks this to add
		 * JPY / BRL / MXN / etc. with their own names and symbols. Each
		 * entry must be `array{name: string, symbol: string}`; malformed
		 * entries are dropped so callers can trust the shape.
		 *
		 * @since 1.1.1
		 *
		 * @param array<string,array{name:string,symbol:string}> $catalog Base catalog.
		 */
		/** @var array<mixed,mixed> $catalog Filtered output may be anything. */
		$catalog = (array) apply_filters( 'wcb_currency_catalog', self::translated_currencies() );
		$out     = array();
		foreach ( $catalog as $code => $entry ) {
			if ( ! is_string( $code ) || ! is_array( $entry ) ) {
				continue;
			}
			$name   = $entry['name'] ?? null;
			$symbol = $entry['symbol'] ?? null;
			if ( ! is_string( $name ) || ! is_string( $symbol ) ) {
				continue;
			}
			$out[ $code ] = array(
				'name'   => $name,
				'symbol' => $symbol,
			);
		}
		return $out;
	}

	/**
	 * The base catalog with its names translated. A class constant cannot hold
	 * a __() call, so the names are mapped here for every consumer at once.
	 *
	 * @since 1.8.0
	 *
	 * @return array<string,array{name:string,symbol:string}>
	 */
	private static function translated_currencies(): array {
		$names   = array(
			'USD' => __( 'US Dollar', 'wp-career-board' ),
			'EUR' => __( 'Euro', 'wp-career-board' ),
			'GBP' => __( 'British Pound', 'wp-career-board' ),
			'CAD' => __( 'Canadian Dollar', 'wp-career-board' ),
			'AUD' => __( 'Australian Dollar', 'wp-career-board' ),
			'INR' => __( 'Indian Rupee', 'wp-career-board' ),
			'SGD' => __( 'Singapore Dollar', 'wp-career-board' ),
		);
		$catalog = self::CURRENCIES;
		foreach ( $names as $code => $name ) {
			$catalog[ $code ]['name'] = $name;
		}
		return $catalog;
	}

	/**
	 * Boot the settings module.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_wcb_create_pages', array( $this, 'handle_create_pages' ) );
		add_action( 'wcb_settings_tab_emails', array( $this, 'render_emails_tab' ) );
		add_action( 'wcb_settings_tab_industries', array( $this, 'render_industries_tab' ) );
		add_action( 'wcb_settings_tab_import', array( $this, 'render_import_tab' ) );
		add_action( 'wcb_settings_tab_mobile-app', array( $this, 'render_mobile_app_tab' ) );
		add_action( 'wcb_settings_tab_privacy', array( $this, 'render_privacy_tab' ) );
		add_action( 'admin_init', array( $this, 'maybe_cancel_account_deletion' ) );
		add_action( 'wcb_settings_tab_integrations', array( $this, 'render_integrations_tab' ) );
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
	 * Sanitize a wcb_settings write.
	 *
	 * Two kinds of write reach here:
	 *  - A settings form (it posts `_wcb_form`): only the schema keys the form
	 *    sent are cleaned and merged over what is stored. Checkboxes post a
	 *    hidden "0" first (SettingsSchema::form_fields()), so an unticked box
	 *    still arrives. Keys outside the schema are ignored.
	 *  - Code writing the whole array (anti-spam, page creation, emails,
	 *    migrations, Pro): the array is authoritative, schema keys are cleaned.
	 *
	 * This replaced guessing the submitted tab from the first key found, which
	 * dropped every key outside the guessed tab: the anti-spam keys and
	 * "Create missing pages" page ids never persisted.
	 *
	 * @since 1.0.0
	 * @since 1.8.0 Schema-driven; no tab guessing.
	 *
	 * @param  mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();

		if ( isset( $input['_wcb_form'] ) ) {
			$output = (array) get_option( self::OPTION_KEY, array() );
			foreach ( SettingsSchema::fields() as $key => $field ) {
				if ( array_key_exists( $key, $input ) ) {
					$output[ $key ] = SettingsSchema::sanitize( $key, $input[ $key ] );
				}
			}
		} else {
			$output = $input;
			foreach ( array_keys( SettingsSchema::fields() ) as $key ) {
				if ( array_key_exists( $key, $output ) ) {
					$output[ $key ] = SettingsSchema::sanitize( $key, $output[ $key ] );
				}
			}
		}

		/**
		 * Filter the sanitized settings output.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,mixed> $output Sanitized settings.
		 * @param array<string,mixed> $input  Raw submitted values.
		 */
		return apply_filters( 'wcb_settings_sanitize', $output, $input );
	}

	/**
	 * Handle the "Create Missing Pages" form POST.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function handle_create_pages(): void {
		check_admin_referer( 'wcb_create_pages' );

		// Defense-in-depth cap check alongside the Abilities API gate. The
		// `wcb/manage-settings` resolves to `manage_options` via the
		// Abilities API. The single gate is sufficient; no need to
		// double-check the capability the ability already wraps.
		if ( ! wp_is_ability_granted( 'wcb/manage-settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
			wp_die( esc_html__( 'You do not have permission to do this.', 'wp-career-board' ) );
		}

		Pages::create_missing();

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
			'listings'     => __( 'Jobs', 'wp-career-board' ),
			'applications' => __( 'Applications', 'wp-career-board' ),
			'industries'   => __( 'Industries', 'wp-career-board' ),
			'import'       => __( 'Import', 'wp-career-board' ),
			'signups'      => __( 'Sign-ups', 'wp-career-board' ),
			'privacy'      => __( 'Privacy', 'wp-career-board' ),
			'brand'        => __( 'Brand', 'wp-career-board' ),
			'emails'       => __( 'Emails', 'wp-career-board' ),
			'mobile-app'   => __( 'Mobile App', 'wp-career-board' ),
			'pages'        => __( 'Pages', 'wp-career-board' ),
			'integrations' => __( 'Integrations', 'wp-career-board' ),
			'advanced'     => __( 'Advanced', 'wp-career-board' ),
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
			'applications'  => 'inbox',
			'signups'       => 'user-plus',
			'advanced'      => 'sliders-horizontal',
			'brand'         => 'palette',
			'resumes'       => 'file-user',
			'analytics'     => 'chart-column',
			'pages'         => 'file-text',
			'industries'    => 'building-2',
			'import'        => 'upload',
			'mobile-app'    => 'smartphone',
			'privacy'       => 'shield-check',
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
	 * Sidebar groups, by the job an owner came to do. Free and Pro tabs sit
	 * in the same groups; a tab not listed here lands in "Site".
	 *
	 * @since  1.8.0
	 * @return array<string, array{label: string, tabs: string[]}>
	 */
	private function get_tab_groups(): array {
		/**
		 * Filter the settings sidebar groups.
		 *
		 * @since 1.8.0
		 * @param array<string, array{label: string, tabs: string[]}> $groups Group key => label and tab slugs, in order.
		 */
		return (array) apply_filters(
			'wcb_settings_tab_groups',
			array(
				'jobs'   => array(
					'label' => __( 'Jobs & Applications', 'wp-career-board' ),
					'tabs'  => array( 'listings', 'applications', 'boards', 'field-builder', 'pipeline', 'job-feed', 'industries', 'import' ),
				),
				'people' => array(
					'label' => __( 'Candidates & Employers', 'wp-career-board' ),
					'tabs'  => array( 'signups', 'resumes', 'credits', 'privacy' ),
				),
				'comms'  => array(
					'label' => __( 'Emails & App', 'wp-career-board' ),
					'tabs'  => array( 'brand', 'emails', 'mobile-app' ),
				),
				'site'   => array(
					'label' => __( 'Site', 'wp-career-board' ),
					'tabs'  => array( 'pages', 'antispam', 'ai-settings', 'analytics', 'integrations', 'advanced', 'license' ),
				),
			)
		);
	}

	/**
	 * Tabs Free itself provides; every other tab carries a Pro badge.
	 *
	 * @since  1.0.0
	 * @return string[]
	 */
	private function get_free_tab_slugs(): array {
		return array( 'listings', 'applications', 'signups', 'advanced', 'pages', 'brand', 'emails', 'industries', 'import', 'mobile-app', 'privacy', 'antispam', 'integrations' );
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
	 * Render the Integrations tab — companion plugin cards.
	 *
	 * @since  1.4.6
	 * @return void
	 */
	public function render_integrations_tab(): void {
		require_once WCB_DIR . 'admin/views/integrations.php';
	}

	/**
	 * Render the Import tab — delegates to AdminImport::render().
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_import_tab(): void {
		( new AdminImport() )->render();

		// Sample data removal section. Defer detection to the wizard so out-of-sync
		// sites — flag is false but `wcb-sample-` jobs / `*.example.com` companies /
		// `_wcb_sample = 1` tagged posts still exist — also see the cleanup button
		// instead of being stranded.
		$wcb_has_sample = SetupWizard::has_sample_data();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only post-redirect flag.
		if ( isset( $_GET['wcb_demo'] ) && 'installed' === sanitize_key( wp_unslash( $_GET['wcb_demo'] ) ) ) {
			echo '<div class="notice notice-success wcb-notice is-dismissible"><p>' . esc_html__( 'Sample data installed.', 'wp-career-board' ) . '</p></div>';
		}

		// Install option - shown when no sample data exists, so demo content can be
		// created straight from Settings (the setup wizard remains available too).
		if ( ! $wcb_has_sample ) :
			?>
			<div class="wcb-settings-card" id="wcb-install-sample-block">
				<div class="wcb-settings-card-header">
					<h2 class="wcb-settings-card-title"><?php esc_html_e( 'Sample data', 'wp-career-board' ); ?></h2>
				</div>
				<div class="wcb-settings-row wcb-settings-row--block">
					<p class="description wcb-settings-intro"><?php esc_html_e( 'Install demo jobs, companies, and candidates so you can explore every feature. You can remove it again any time.', 'wp-career-board' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="wcb_install_demo" />
			<?php wp_nonce_field( 'wcb_install_demo' ); ?>
						<button type="submit" class="button button-secondary"><?php esc_html_e( 'Install Sample Data', 'wp-career-board' ); ?></button>
					</form>
				</div>
			</div>
			<?php
		endif;

		if ( $wcb_has_sample ) :
			?>
			<div class="wcb-settings-card" id="wcb-sample-data-block">
				<div class="wcb-settings-card-header">
					<h2 class="wcb-settings-card-title"><?php esc_html_e( 'Sample data', 'wp-career-board' ); ?></h2>
				</div>
				<div class="wcb-settings-row wcb-settings-row--block">
					<p class="description wcb-settings-intro"><?php esc_html_e( 'Remove demo jobs, companies, candidates, and unused taxonomy terms created by the setup wizard or marked as sample.', 'wp-career-board' ); ?></p>
					<button type="button" id="wcb-remove-sample-data" class="button button-secondary">
				<?php esc_html_e( 'Remove Sample Data', 'wp-career-board' ); ?>
				</button>
				<span id="wcb-remove-sample-status" class="description wcb-settings-status"></span>
				</div>
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

		$wcb_missing = Pages::missing();

		// Group tabs for the sidebar, each group in its own tab order. A tab no
		// group names (a third-party add-on) goes in the last group.
		$wcb_groups = array();
		$wcb_placed = array();
		foreach ( $this->get_tab_groups() as $wcb_gkey => $wcb_group ) {
			$wcb_items = array();
			foreach ( $wcb_group['tabs'] as $wcb_slug ) {
				if ( isset( $wcb_tabs[ $wcb_slug ] ) ) {
					$wcb_items[ $wcb_slug ]  = $wcb_tabs[ $wcb_slug ];
					$wcb_placed[ $wcb_slug ] = true;
				}
			}
			$wcb_groups[ $wcb_gkey ] = array(
				'label' => $wcb_group['label'],
				'items' => $wcb_items,
			);
		}
		$wcb_last_group                          = (string) array_key_last( $wcb_groups );
		$wcb_groups[ $wcb_last_group ]['items'] += array_diff_key( $wcb_tabs, $wcb_placed );
		$wcb_groups                              = array_filter( $wcb_groups, static fn( array $g ): bool => ! empty( $g['items'] ) );

		?>
		<div class="wrap wcb-admin">

			<h1 class="screen-reader-text"><?php esc_html_e( 'Career Board Settings', 'wp-career-board' ); ?></h1>

		<?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success wcb-notice is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'wp-career-board' ); ?></p>
				</div>
		<?php endif; ?>

		<?php
		// Pro returns a notice text when its own settings save fired (e.g. ?wcbp_saved=1).
		// Filter declared in core/class-pro-coordination.php (F-1).
		$wcb_pro_saved_notice = apply_filters( 'wcb_pro_settings_saved_notice', null );
		if ( ! empty( $wcb_pro_saved_notice ) ) :
			?>
				<div class="notice notice-success wcb-notice is-dismissible">
					<p><?php echo esc_html( (string) $wcb_pro_saved_notice ); ?></p>
				</div>
			<?php
		endif;
		?>

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
			$wcb_page_defs     = Pages::definitions();
			$wcb_missing_names = array_map( static fn( string $k ): string => $wcb_page_defs[ $k ]['title'], $wcb_missing );
			?>
				<div class="notice notice-warning wcb-notice">
					<p>
						<strong><?php esc_html_e( 'Missing pages:', 'wp-career-board' ); ?></strong>
			<?php echo esc_html( implode( ', ', $wcb_missing_names ) ); ?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 6px;">
						<input type="hidden" name="action" value="wcb_create_pages">
			<?php wp_nonce_field( 'wcb_create_pages' ); ?>
			<?php submit_button( __( 'Create Missing Pages', 'wp-career-board' ), 'primary wcb-btn wcb-btn--primary', 'submit', false ); ?>
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
				<?php if ( ! in_array( $wcb_slug, $wcb_free_tabs, true ) ) : ?>
									<span class="wcb-pro-badge"><?php esc_html_e( 'Pro', 'wp-career-board' ); ?></span>
				<?php endif; ?>
							</a>
			<?php endforeach; ?>
					</nav>
		<?php endforeach; ?>
		<?php
		// Pro filter (F-1) decides whether to show the Pro teasers nav group.
		$wcb_pro_active_for_teasers = (bool) apply_filters( 'wcb_pro_active', false );
		$wcb_pro_upsell_url         = (string) apply_filters( 'wcb_pro_upsell_url', 'https://store.wbcomdesigns.com/wp-career-board-pro/' );
		if ( ! $wcb_pro_active_for_teasers ) :
			?>
			<?php $wcb_pro_teasers = $this->get_pro_teaser_tabs(); ?>
					<nav class="wcb-settings-nav-group wcb-settings-nav-group--pro-teasers" aria-label="<?php esc_attr_e( 'Pro', 'wp-career-board' ); ?>">
						<span class="wcb-settings-nav-group__label"><?php esc_html_e( 'Pro', 'wp-career-board' ); ?></span>
			<?php foreach ( $wcb_pro_teasers as $wcb_teaser_slug => $wcb_teaser ) : ?>
							<a href="<?php echo esc_url( $wcb_pro_upsell_url ); ?>"
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

					<!-- ── Jobs ─── -->
					<div class="wcb-settings-section" id="section-listings">
						<form method="post" action="options.php">
		<?php settings_fields( 'wcb_settings_group' ); ?>
		<?php SettingsSchema::form_fields( array( 'auto_publish_jobs', 'job_schema_enabled', 'require_job_location', 'social_tags_enabled' ) ); ?>
							<div class="wcb-card">
								<div class="wcb-card__head">
									<p class="wcb-card__title"><?php esc_html_e( 'Jobs', 'wp-career-board' ); ?></p>
									<p class="wcb-card__desc"><?php esc_html_e( 'How jobs are reviewed, how long they run and how they are listed.', 'wp-career-board' ); ?></p>
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
										<div class="wcb-settings-row-label"><label for="wcb-report-auto-hide"><?php esc_html_e( 'Hide a job after this many reports', 'wp-career-board' ); ?></label></div>
										<div class="wcb-settings-row-control">
											<input type="number" id="wcb-report-auto-hide" name="wcb_settings[report_auto_hide_threshold]" value="<?php echo (int) ( $settings['report_auto_hide_threshold'] ?? 3 ); ?>" min="0" max="50" style="width:80px">
											<span class="description"><?php esc_html_e( 'When this many different members report a job, it is taken off the site as "Pending" until you review it under Career Board > Jobs > Flagged. Dismissing the reports puts it back. 0 never hides a job automatically. You are emailed on the first report and when a job is hidden.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><label for="wcb-jobs-expire-days"><?php esc_html_e( 'Default listing length (days)', 'wp-career-board' ); ?></label></div>
										<div class="wcb-settings-row-control">
											<input type="number" id="wcb-jobs-expire-days" name="wcb_settings[jobs_expire_days]" value="<?php echo isset( $settings['jobs_expire_days'] ) ? (int) $settings['jobs_expire_days'] : 30; ?>" min="1" max="365" style="width:80px">
											<span class="description"><?php esc_html_e( 'How long a job stays open when the employer sets no deadline. Default 30. A board can set its own length, which wins over this one.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><?php esc_html_e( 'When a job ends', 'wp-career-board' ); ?></div>
										<div class="wcb-settings-row-control">
											<span class="description"><?php esc_html_e( 'At its deadline a job stops taking applications and leaves the listings, feeds and sitemap within the hour. Its page stays up as an expired page with similar open jobs, so shared links keep working. Employers can reopen it from their dashboard with a new deadline.', 'wp-career-board' ); ?></span>
											<?php if ( empty( $settings['deadline_auto_close'] ) ) : ?>
												<p class="description"><strong><?php esc_html_e( 'This site still lists jobs past their deadline (set before 1.8.0).', 'wp-career-board' ); ?></strong></p>
												<p><a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wcb_enable_expiry', '1' ), 'wcb_enable_expiry' ) ); ?>"><?php esc_html_e( 'End jobs at their deadline', 'wp-career-board' ); ?></a></p>
											<?php endif; ?>
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
										<div class="wcb-settings-row-label"><label for="wcb-jobs-default-sort"><?php esc_html_e( 'Default order', 'wp-career-board' ); ?></label></div>
										<div class="wcb-settings-row-control">
											<?php $wcb_default_sort = \WCB\Admin\Settings::string( 'jobs_default_sort', 'newest' ); ?>
											<select id="wcb-jobs-default-sort" name="wcb_settings[jobs_default_sort]">
												<option value="newest" <?php selected( $wcb_default_sort, 'newest' ); ?>><?php esc_html_e( 'Featured, then newest', 'wp-career-board' ); ?></option>
												<option value="closing" <?php selected( $wcb_default_sort, 'closing' ); ?>><?php esc_html_e( 'Closing soonest', 'wp-career-board' ); ?></option>
												<option value="salary" <?php selected( $wcb_default_sort, 'salary' ); ?>><?php esc_html_e( 'Highest salary', 'wp-career-board' ); ?></option>
												<option value="oldest" <?php selected( $wcb_default_sort, 'oldest' ); ?>><?php esc_html_e( 'Oldest first', 'wp-career-board' ); ?></option>
											</select>
											<span class="description"><?php esc_html_e( 'How job lists are ordered before a visitor picks a sort. A keyword search always shows the best matches first.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><label for="wcb-salary-currency"><?php esc_html_e( 'Default Salary Currency', 'wp-career-board' ); ?></label></div>
										<div class="wcb-settings-row-control">
											<select id="wcb-salary-currency" name="wcb_settings[salary_currency]">
												<?php foreach ( self::get_currency_catalog() as $wcb_code => $wcb_meta ) : ?>
													<option value="<?php echo esc_attr( $wcb_code ); ?>" <?php selected( $salary_currency, $wcb_code ); ?>>
														<?php
														printf(
															/* translators: 1: code (USD), 2: name (US Dollar), 3: symbol ($). */
															esc_html__( '%1$s  -  %2$s (%3$s)', 'wp-career-board' ),
															esc_html( (string) $wcb_code ),
															esc_html( (string) $wcb_meta['name'] ),
															esc_html( (string) $wcb_meta['symbol'] )
														);
														?>
													</option>
												<?php endforeach; ?>
											</select>
											<span class="description"><?php esc_html_e( 'Site-wide default for new job postings. Employers can override it per job.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><label for="wcb-featured-days"><?php esc_html_e( 'Featured Duration (days)', 'wp-career-board' ); ?></label></div>
										<div class="wcb-settings-row-control">
											<input
												id="wcb-featured-days"
												type="number"
												name="wcb_settings[apply_featured_days]"
												value="<?php echo esc_attr( (string) ( isset( $settings['apply_featured_days'] ) ? (int) $settings['apply_featured_days'] : 30 ) ); ?>"
												min="1"
												max="365"
												step="1"
											>
											<span class="description"><?php esc_html_e( 'How many days a job stays in the Featured spotlight before reverting automatically. Daily cron clears expired flags.', 'wp-career-board' ); ?></span>
										</div>
									</div>
								</div>
							</div>
							<div class="wcb-card">
								<div class="wcb-card__head">
									<p class="wcb-card__title"><?php esc_html_e( 'Search engines and sharing', 'wp-career-board' ); ?></p>
									<p class="wcb-card__desc"><?php esc_html_e( 'How jobs and company pages appear in Google for Jobs and when shared on social media.', 'wp-career-board' ); ?></p>
								</div>
								<div class="wcb-card__body">
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><?php esc_html_e( 'Google for Jobs', 'wp-career-board' ); ?></div>
										<div class="wcb-settings-row-control">
											<label class="wcb-toggle-label">
												<span class="wcb-toggle">
													<input type="checkbox" name="wcb_settings[job_schema_enabled]" value="1" <?php checked( \WCB\Admin\Settings::bool( 'job_schema_enabled' ) ); ?>>
													<span class="wcb-toggle-slider"></span>
												</span>
												<?php esc_html_e( 'Add job and company details for search engines', 'wp-career-board' ); ?>
											</label>
											<span class="description"><?php esc_html_e( 'Adds JobPosting markup to open jobs and Organization markup to company pages, so jobs can appear in Google for Jobs. Stays on alongside Yoast SEO and Rank Math, which do not add job markup. Turn off only if another plugin already adds it.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><?php esc_html_e( 'Require a location', 'wp-career-board' ); ?></div>
										<div class="wcb-settings-row-control">
											<label class="wcb-toggle-label">
												<span class="wcb-toggle">
													<input type="checkbox" name="wcb_settings[require_job_location]" value="1" <?php checked( \WCB\Admin\Settings::bool( 'require_job_location' ) ); ?>>
													<span class="wcb-toggle-slider"></span>
												</span>
												<?php esc_html_e( 'Ask for a location on every job that is not remote', 'wp-career-board' ); ?>
											</label>
											<span class="description"><?php esc_html_e( 'Google for Jobs leaves out jobs with no location unless they are remote. When on, a job cannot be posted without a location or the Remote option.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><label for="wcb-default-country"><?php esc_html_e( 'Default country', 'wp-career-board' ); ?></label></div>
										<div class="wcb-settings-row-control">
											<input type="text" id="wcb-default-country" name="wcb_settings[default_country]" value="<?php echo esc_attr( (string) ( $settings['default_country'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( \WCB\Modules\Seo\SeoModule::default_country() ); ?>" style="width:200px">
											<span class="description"><?php esc_html_e( 'Country name or 2-letter code for job addresses, and where remote applicants may live. Empty uses the country of your site language. For boards in several countries, set a country on each location under Career Board > Job Locations.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><?php esc_html_e( 'Social sharing tags', 'wp-career-board' ); ?></div>
										<div class="wcb-settings-row-control">
											<label class="wcb-toggle-label">
												<span class="wcb-toggle">
													<input type="checkbox" name="wcb_settings[social_tags_enabled]" value="1" <?php checked( \WCB\Admin\Settings::bool( 'social_tags_enabled' ) ); ?>>
													<span class="wcb-toggle-slider"></span>
												</span>
												<?php esc_html_e( 'Add title, description and image when a job or company is shared', 'wp-career-board' ); ?>
											</label>
											<span class="description"><?php esc_html_e( 'Uses the job image or the company logo. Skipped automatically when Yoast SEO or Rank Math is active, since they add their own.', 'wp-career-board' ); ?></span>
										</div>
									</div>
								</div>
							</div>
							<div class="wcb-settings-section__footer">
								<?php submit_button( __( 'Save Changes', 'wp-career-board' ), 'primary wcb-btn wcb-btn--primary', 'submit', false ); ?>
							</div>
						</form>
					</div>

					<!-- ── Applications ─── -->
					<div class="wcb-settings-section" id="section-applications">
						<form method="post" action="options.php">
		<?php settings_fields( 'wcb_settings_group' ); ?>
		<?php SettingsSchema::form_fields( array( 'apply_resume_required', 'allow_withdraw' ) ); ?>
							<div class="wcb-card">
								<div class="wcb-card__head">
									<p class="wcb-card__title"><?php esc_html_e( 'Applications', 'wp-career-board' ); ?></p>
									<p class="wcb-card__desc"><?php esc_html_e( 'What candidates need to apply, and what they can do afterwards.', 'wp-career-board' ); ?></p>
								</div>
								<div class="wcb-card__body">
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><?php esc_html_e( 'Resume Required', 'wp-career-board' ); ?></div>
										<div class="wcb-settings-row-control">
											<label class="wcb-toggle-label">
												<span class="wcb-toggle">
													<input type="checkbox" name="wcb_settings[apply_resume_required]" value="1" <?php checked( array_key_exists( 'apply_resume_required', $settings ) ? ! empty( $settings['apply_resume_required'] ) : true ); ?>>
													<span class="wcb-toggle-slider"></span>
												</span>
												<?php esc_html_e( 'Require applicants to attach a resume', 'wp-career-board' ); ?>
											</label>
											<span class="description"><?php esc_html_e( 'On by default. Turn off only for boards where applicants can apply with a cover letter alone (e.g. internal job boards, walk-in roles).', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><label for="wcb-resume-max-mb"><?php esc_html_e( 'Application Resume File Size (MB)', 'wp-career-board' ); ?></label></div>
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
											<span class="description"><?php esc_html_e( 'Maximum size of a resume file a candidate uploads when applying to a job (1-20 MB). Accepted formats: PDF, DOC, DOCX. The resume profile builder lives under Settings > Resumes.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><?php esc_html_e( 'Allow Withdraw', 'wp-career-board' ); ?></div>
										<div class="wcb-settings-row-control">
											<label class="wcb-toggle-label">
												<span class="wcb-toggle">
													<input type="checkbox" name="wcb_settings[allow_withdraw]" value="1" <?php checked( array_key_exists( 'allow_withdraw', $settings ) ? ! empty( $settings['allow_withdraw'] ) : true ); ?>>
													<span class="wcb-toggle-slider"></span>
												</span>
												<?php esc_html_e( 'Let candidates withdraw their own applications', 'wp-career-board' ); ?>
											</label>
											<span class="description"><?php esc_html_e( 'On by default. A withdrawn application stays on the employer\'s applicant list with the status Withdrawn, and the employer can no longer change it. Turn off for boards that want apply-once-final flows (compliance, regulated hiring).', 'wp-career-board' ); ?></span>
										</div>
									</div>
								</div>
							</div>
							<div class="wcb-settings-section__footer">
								<?php submit_button( __( 'Save Changes', 'wp-career-board' ), 'primary wcb-btn wcb-btn--primary', 'submit', false ); ?>
							</div>
						</form>
					</div>

					<!-- ── Sign-ups ─── -->
					<div class="wcb-settings-section" id="section-signups">
						<form method="post" action="options.php">
		<?php settings_fields( 'wcb_settings_group' ); ?>
		<?php SettingsSchema::form_fields( array( 'require_email_verification', 'candidate_requires_role', 'apply_require_login' ) ); ?>
							<div class="wcb-card">
								<div class="wcb-card__head">
									<p class="wcb-card__title"><?php esc_html_e( 'Sign-ups', 'wp-career-board' ); ?></p>
									<p class="wcb-card__desc"><?php esc_html_e( 'Who can create an account, and how new accounts are checked.', 'wp-career-board' ); ?></p>
								</div>
								<div class="wcb-card__body">
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><?php esc_html_e( 'Open Sign-Up', 'wp-career-board' ); ?></div>
										<div class="wcb-settings-row-control">
											<?php if ( get_option( 'users_can_register' ) ) : ?>
												<strong><?php esc_html_e( 'On: candidates and employers can create their own accounts.', 'wp-career-board' ); ?></strong>
											<?php else : ?>
												<strong><?php esc_html_e( 'Off: the sign-up forms show a "registration closed" notice.', 'wp-career-board' ); ?></strong>
											<?php endif; ?>
											<span class="description">
												<?php
												printf(
													/* translators: %s: link to Settings > General. */
													esc_html__( 'This is WordPress\'s "Anyone can register" setting under %s.', 'wp-career-board' ),
													'<a href="' . esc_url( admin_url( 'options-general.php' ) ) . '">' . esc_html__( 'Settings > General', 'wp-career-board' ) . '</a>'
												);
												?>
											</span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><?php esc_html_e( 'Email Verification', 'wp-career-board' ); ?></div>
										<div class="wcb-settings-row-control">
											<label class="wcb-toggle-label">
												<span class="wcb-toggle">
													<input type="checkbox" name="wcb_settings[require_email_verification]" value="1" <?php checked( ! empty( $settings['require_email_verification'] ) ); ?>>
													<span class="wcb-toggle-slider"></span>
												</span>
												<?php esc_html_e( 'New candidates and employers confirm their email before they can sign in', 'wp-career-board' ); ?>
											</label>
											<span class="description"><?php esc_html_e( 'Stops fake sign-ups using addresses the person does not own. Uses the "Confirm Your Email" message under Emails; if that message is turned off, sign-ups are not held.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><?php esc_html_e( 'Require Candidate Role', 'wp-career-board' ); ?></div>
										<div class="wcb-settings-row-control">
											<label class="wcb-toggle-label">
												<span class="wcb-toggle">
													<input type="checkbox" name="wcb_settings[candidate_requires_role]" value="1" <?php checked( ! empty( $settings['candidate_requires_role'] ) ); ?>>
													<span class="wcb-toggle-slider"></span>
												</span>
												<?php esc_html_e( 'Only the Candidate role can apply, bookmark, and use the candidate dashboard', 'wp-career-board' ); ?>
											</label>
											<span class="description"><?php esc_html_e( 'Off by default: any logged-in member can apply and manage a resume (ideal when the job board is part of a community site). Turn on to reserve the candidate experience for users with the Candidate role.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><?php esc_html_e( 'Require login to apply', 'wp-career-board' ); ?></div>
										<div class="wcb-settings-row-control">
											<label class="wcb-toggle-label">
												<span class="wcb-toggle">
													<input type="checkbox" name="wcb_settings[apply_require_login]" value="1" <?php checked( ! empty( $settings['apply_require_login'] ) ); ?>>
													<span class="wcb-toggle-slider"></span>
												</span>
												<?php esc_html_e( 'Only signed-in members can apply', 'wp-career-board' ); ?>
											</label>
											<span class="description"><?php esc_html_e( 'Off by default: visitors can apply with their name and email. Guest applications are limited to 10 an hour from one connection.', 'wp-career-board' ); ?></span>
										</div>
									</div>
								</div>
							</div>
							<div class="wcb-settings-section__footer">
								<?php submit_button( __( 'Save Changes', 'wp-career-board' ), 'primary wcb-btn wcb-btn--primary', 'submit', false ); ?>
							</div>
						</form>
					</div>

					<!-- ── Advanced ─── -->
					<div class="wcb-settings-section" id="section-advanced">
						<form method="post" action="options.php">
		<?php settings_fields( 'wcb_settings_group' ); ?>
		<?php SettingsSchema::form_fields( array( 'remove_data_on_uninstall' ) ); ?>
							<div class="wcb-card">
								<div class="wcb-card__head">
									<p class="wcb-card__title"><?php esc_html_e( 'Advanced', 'wp-career-board' ); ?></p>
									<p class="wcb-card__desc"><?php esc_html_e( 'Layout and data handling. Most sites never need to change these.', 'wp-career-board' ); ?></p>
								</div>
								<div class="wcb-card__body">
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><label for="wcb-container-max-width"><?php esc_html_e( 'Content Width (px)', 'wp-career-board' ); ?></label></div>
										<div class="wcb-settings-row-control">
											<input
												id="wcb-container-max-width"
												type="number"
												name="wcb_settings[container_max_width]"
												value="<?php echo esc_attr( (string) ( isset( $settings['container_max_width'] ) ? (int) $settings['container_max_width'] : 0 ) ); ?>"
												min="0"
												max="1920"
												step="10"
												placeholder="0"
											>
											<span class="description"><?php esc_html_e( 'How wide Career Board pages run. Leave at 0 to follow your theme, which most sites should. Set a value between 720 and 1920 only if the plugin pages need to differ from the rest of the site.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><label for="wcb-log-retention-days"><?php esc_html_e( 'Keep Email History (days)', 'wp-career-board' ); ?></label></div>
										<div class="wcb-settings-row-control">
											<input
												id="wcb-log-retention-days"
												type="number"
												name="wcb_settings[log_retention_days]"
												value="<?php echo esc_attr( (string) ( isset( $settings['log_retention_days'] ) ? (int) $settings['log_retention_days'] : 180 ) ); ?>"
												min="0"
												max="3650"
											>
											<span class="description"><?php esc_html_e( 'How long the email log and notification history are kept before they are deleted automatically. 0 keeps them forever.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><?php esc_html_e( 'Remove Data on Delete', 'wp-career-board' ); ?></div>
										<div class="wcb-settings-row-control">
											<label class="wcb-toggle-label">
												<span class="wcb-toggle">
													<input type="checkbox" name="wcb_settings[remove_data_on_uninstall]" value="1" <?php checked( ! empty( $settings['remove_data_on_uninstall'] ) ); ?>>
													<span class="wcb-toggle-slider"></span>
												</span>
												<?php esc_html_e( 'Delete all Career Board data when the plugin is deleted', 'wp-career-board' ); ?>
											</label>
											<span class="description"><?php esc_html_e( 'Off by default, so deleting and reinstalling the plugin keeps your jobs, applications, companies, resumes and credit balances. Turn on only when you are removing Career Board for good: deleting the plugin then erases all of it, including uploaded resumes, and cannot be undone. Deactivating never deletes anything.', 'wp-career-board' ); ?></span>
										</div>
									</div>
								</div>
							</div>
							<div class="wcb-settings-section__footer">
								<?php submit_button( __( 'Save Changes', 'wp-career-board' ), 'primary wcb-btn wcb-btn--primary', 'submit', false ); ?>
							</div>
						</form>
					</div>

					<!-- ── Pages ────────────────────────────────────────────── -->
					<div class="wcb-settings-section" id="section-pages">
						<form method="post" action="options.php">
		<?php settings_fields( 'wcb_settings_group' ); ?>
		<?php SettingsSchema::form_fields(); ?>
							<div class="wcb-card">
								<div class="wcb-card__head">
									<p class="wcb-card__title"><?php esc_html_e( 'Pages', 'wp-career-board' ); ?></p>
									<p class="wcb-card__desc"><?php esc_html_e( 'Assign the WordPress pages that contain each WP Career Board block.', 'wp-career-board' ); ?></p>
								</div>
								<div class="wcb-card__body">
									<?php
									$wcb_page_settings = Pages::definitions();
									?>
		<?php foreach ( $wcb_page_settings as $wcb_key => $wcb_info ) : ?>
			<?php $wcb_resolved_id = (int) Pages::get_id( (string) $wcb_key ); ?>
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
					'selected'         => (int) $wcb_resolved_id,
					'show_option_none' => esc_html__( ' -  Select a page  - ', 'wp-career-board' ),
				)
			);
			if ( $wcb_resolved_id ) {
				$wcb_page_url = get_permalink( $wcb_resolved_id );
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
		<?php submit_button( __( 'Save Changes', 'wp-career-board' ), 'primary wcb-btn wcb-btn--primary', 'submit', false ); ?>
							</div>
						</form>
					</div>

					<!-- ── Brand ─── -->
					<div class="wcb-settings-section" id="section-brand">
						<form method="post" action="options.php">
		<?php settings_fields( 'wcb_settings_group' ); ?>
		<?php SettingsSchema::form_fields(); ?>
		<?php
		$wcb_brand_logo_id  = (int) ( $settings['logo_id'] ?? 0 );
		$wcb_brand_logo_url = \WCB\Core\Brand::logo_url();
		?>
							<div class="wcb-card">
								<div class="wcb-card__head">
									<p class="wcb-card__title"><?php esc_html_e( 'Brand', 'wp-career-board' ); ?></p>
									<p class="wcb-card__desc"><?php esc_html_e( 'One colour and one logo for your Career Board emails, the mobile app and the installable app. Your website follows your theme.', 'wp-career-board' ); ?></p>
								</div>
								<div class="wcb-card__body">
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><label for="wcb-brand-color"><?php esc_html_e( 'Brand Colour', 'wp-career-board' ); ?></label></div>
										<div class="wcb-settings-row-control">
											<input type="color" id="wcb-brand-color" name="wcb_settings[accent_color]" value="<?php echo esc_attr( \WCB\Core\Brand::color() ); ?>">
											<span class="description"><?php esc_html_e( 'Email header, app buttons and the browser bar of the installable app. Pick a colour white text reads well on.', 'wp-career-board' ); ?></span>
										</div>
									</div>
									<div class="wcb-settings-row">
										<div class="wcb-settings-row-label"><?php esc_html_e( 'Logo', 'wp-career-board' ); ?></div>
										<div class="wcb-settings-row-control wcb-brand-logo" data-wcb-brand-logo>
											<input type="hidden" name="wcb_settings[logo_id]" value="<?php echo (int) $wcb_brand_logo_id; ?>" data-wcb-brand-logo-id>
											<img class="wcb-brand-logo__preview" src="<?php echo esc_url( $wcb_brand_logo_url ); ?>" alt="<?php esc_attr_e( 'Logo preview', 'wp-career-board' ); ?>" data-wcb-brand-logo-preview <?php echo $wcb_brand_logo_url ? '' : 'hidden'; ?>>
											<span class="wcb-brand-logo__actions">
												<button type="button" class="wcb-btn wcb-btn--sm" data-wcb-brand-logo-choose><?php esc_html_e( 'Choose Image', 'wp-career-board' ); ?></button>
												<button type="button" class="wcb-btn wcb-btn--sm wcb-btn--danger" data-wcb-brand-logo-remove <?php echo $wcb_brand_logo_url ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'wp-career-board' ); ?></button>
											</span>
											<span class="description"><?php esc_html_e( 'Shown at the top of every email and in the app. A wide image around 400 x 120 px works best.', 'wp-career-board' ); ?></span>
										</div>
									</div>
								</div>
							</div>
							<div class="wcb-settings-section__footer">
								<?php submit_button( __( 'Save Changes', 'wp-career-board' ), 'primary wcb-btn wcb-btn--primary', 'submit', false ); ?>
							</div>
						</form>
					</div>

					<!-- ── Emails ───────────────────────────────────────────── -->
					<div class="wcb-settings-section" id="section-emails">
											<form method="post" action="options.php">
		<?php settings_fields( 'wcb_settings_group' ); ?>
		<?php SettingsSchema::form_fields(); ?>
							<div class="wcb-card">
								<div class="wcb-card__head">
									<p class="wcb-card__title"><?php esc_html_e( 'Sender', 'wp-career-board' ); ?></p>
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
		<?php submit_button( __( 'Save Changes', 'wp-career-board' ), 'primary wcb-btn wcb-btn--primary', 'submit', false ); ?>
							</div>
						</form>
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
		$wcb_builtin = array( 'listings', 'applications', 'signups', 'advanced', 'pages', 'brand', 'emails' );
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

			<p class="wcb-settings-page-footer">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-setup&wcb_rerun=1' ) ); ?>">
					<?php esc_html_e( 'Re-run Setup Wizard', 'wp-career-board' ); ?>
				</a>
				<span class="separator" aria-hidden="true">·</span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-career-board' ) ); ?>">
					<?php esc_html_e( 'Back to Dashboard', 'wp-career-board' ); ?>
				</a>
			</p>

		</div>
		<?php
	}

	/**
	 * Render the Industries tab — the owner-facing editor for the company
	 * industry registry.
	 *
	 * The list used to be a hardcoded PHP array reachable only through the
	 * `wcb_industries` filter, so a site owner could not add, rename or retire
	 * an industry without writing code (Basecamp 10254034153). State and
	 * persistence both live behind `/wcb/v1/admin/industries`; this method only
	 * paints the shell and lets the script fill it, so counts are always read
	 * live rather than baked into the page.
	 *
	 * @since  1.7.1
	 * @return void
	 */
	public function render_industries_tab(): void {
		?>
		<div class="wcb-settings-card" id="wcb-industries-card">
			<div class="wcb-settings-card-header">
				<h2 class="wcb-settings-card-title"><?php esc_html_e( 'Industries', 'wp-career-board' ); ?></h2>
			</div>
			<div class="wcb-settings-row wcb-settings-row--block">
				<p class="description wcb-settings-intro">
					<?php esc_html_e( 'Industries offered on company profiles, the employer registration form, and the company directory filter. Rename a label any time - renaming never touches stored data. Removing an industry asks what should happen to the companies still using it.', 'wp-career-board' ); ?>
				</p>

				<div id="wcb-industries-list" class="wcb-ind-list" aria-live="polite">
					<p class="description"><?php esc_html_e( 'Loading industries…', 'wp-career-board' ); ?></p>
				</div>

				<div id="wcb-industries-orphans" class="wcb-ind-orphans" hidden>
					<h3 class="wcb-ind-subtitle"><?php esc_html_e( 'Not in your list', 'wp-career-board' ); ?></h3>
					<p class="description" style="margin: 0 0 8px;">
						<?php esc_html_e( 'These values are stored on companies but are not industries you offer - usually left behind by an import. Add one to your list to keep it, or settle it like any other removal.', 'wp-career-board' ); ?>
					</p>
					<div id="wcb-industries-orphan-list"></div>
				</div>

				<div class="wcb-ind-add">
					<label class="wcb-ind-add__field">
						<span class="wcb-ind-add__label"><?php esc_html_e( 'New industry', 'wp-career-board' ); ?></span>
						<input type="text" id="wcb-industry-new-label" class="regular-text" placeholder="<?php esc_attr_e( 'Aerospace & Defence', 'wp-career-board' ); ?>" />
					</label>
					<button type="button" id="wcb-industry-add" class="wcb-btn wcb-btn--secondary">
						<?php esc_html_e( 'Add industry', 'wp-career-board' ); ?>
					</button>
				</div>

				<p class="wcb-ind-actions">
					<button type="button" id="wcb-industries-save" class="wcb-btn wcb-btn--primary">
						<?php esc_html_e( 'Save Industries', 'wp-career-board' ); ?>
					</button>
					<span id="wcb-industries-status" class="description"></span>
				</p>
			</div>
		</div>

		<?php
	}

	/**
	 * Mobile App tab — branding and the per-site legal surface.
	 *
	 * Every key here has been read by GET /settings/app-config since 1.7.0 with
	 * no way for a site owner to set any of them, so a companion app showed the
	 * plugin's default blue, no logo, and fell back to the admin email for abuse
	 * reports. The legal URLs matter beyond cosmetics: the endpoint's own comment
	 * cites Apple guidelines 1.2 and 5.1.1, and an app that cannot link its terms
	 * or community guidelines is a review rejection.
	 *
	 * @since 1.7.1
	 *
	 * @param  array<string,mixed> $settings Current settings.
	 * @return void
	 */
	public function render_mobile_app_tab( array $settings = array() ): void {
		$settings = $settings ? $settings : Settings::all();

		$wcb_login_bg   = (string) ( $settings['login_bg_url'] ?? '' );
		$wcb_dark       = ! empty( $settings['dark_mode_default'] );
		$wcb_terms      = (string) ( $settings['terms_url'] ?? '' );
		$wcb_eula       = (string) ( $settings['eula_url'] ?? '' );
		$wcb_guidelines = (string) ( $settings['guidelines_url'] ?? '' );
		$wcb_abuse      = (string) ( $settings['abuse_contact_email'] ?? '' );
		?>
		<form method="post" action="options.php">
		<?php settings_fields( 'wcb_settings_group' ); ?>
		<?php SettingsSchema::form_fields( array( 'dark_mode_default', 'app_password_login' ) ); ?>
		<h2><?php esc_html_e( 'App branding', 'wp-career-board' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'How your companion app looks. These are served to the app and ignored by the website, which follows your theme.', 'wp-career-board' ); ?>
		</p>

		<div class="wcb-settings-row">
			<div class="wcb-settings-row-label"><?php esc_html_e( 'Colour and Logo', 'wp-career-board' ); ?></div>
			<div class="wcb-settings-row-control">
				<span class="description">
					<?php
					printf(
						/* translators: %s: link to the Brand tab. */
						esc_html__( 'The app uses your Brand colour and logo, shared with your emails. Change them under %s.', 'wp-career-board' ),
						'<a href="#brand" data-wcb-goto-section="brand">' . esc_html__( 'Brand', 'wp-career-board' ) . '</a>'
					);
					?>
				</span>
			</div>
		</div>

		<div class="wcb-settings-row">
			<div class="wcb-settings-row-label"><label for="wcb-login-bg-url"><?php esc_html_e( 'Sign-in background URL', 'wp-career-board' ); ?></label></div>
			<div class="wcb-settings-row-control">
				<input type="url" id="wcb-login-bg-url" class="regular-text" name="wcb_settings[login_bg_url]" value="<?php echo esc_attr( $wcb_login_bg ); ?>" placeholder="https://">
			</div>
		</div>

		<div class="wcb-settings-row">
			<div class="wcb-settings-row-label"><?php esc_html_e( 'Dark mode', 'wp-career-board' ); ?></div>
			<div class="wcb-settings-row-control">
				<label for="wcb-dark-mode-default">
					<input type="checkbox" id="wcb-dark-mode-default" name="wcb_settings[dark_mode_default]" value="1" <?php checked( $wcb_dark ); ?>>
					<?php esc_html_e( 'Open the app in dark mode by default', 'wp-career-board' ); ?>
				</label>
			</div>
		</div>

		<h2><?php esc_html_e( 'Legal links', 'wp-career-board' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'App stores require a published terms link and a way to report abuse. Anything left blank is sent as empty rather than a placeholder link, except the abuse contact, where the app shows your privacy page instead. Your privacy policy comes from Settings > Privacy in WordPress.', 'wp-career-board' ); ?>
		</p>

		<div class="wcb-settings-row">
			<div class="wcb-settings-row-label"><label for="wcb-terms-url"><?php esc_html_e( 'Terms of service URL', 'wp-career-board' ); ?></label></div>
			<div class="wcb-settings-row-control">
				<input type="url" id="wcb-terms-url" class="regular-text" name="wcb_settings[terms_url]" value="<?php echo esc_attr( $wcb_terms ); ?>" placeholder="https://">
			</div>
		</div>

		<div class="wcb-settings-row">
			<div class="wcb-settings-row-label"><label for="wcb-eula-url"><?php esc_html_e( 'EULA URL', 'wp-career-board' ); ?></label></div>
			<div class="wcb-settings-row-control">
				<input type="url" id="wcb-eula-url" class="regular-text" name="wcb_settings[eula_url]" value="<?php echo esc_attr( $wcb_eula ); ?>" placeholder="https://">
			</div>
		</div>

		<div class="wcb-settings-row">
			<div class="wcb-settings-row-label"><label for="wcb-guidelines-url"><?php esc_html_e( 'Community guidelines URL', 'wp-career-board' ); ?></label></div>
			<div class="wcb-settings-row-control">
				<input type="url" id="wcb-guidelines-url" class="regular-text" name="wcb_settings[guidelines_url]" value="<?php echo esc_attr( $wcb_guidelines ); ?>" placeholder="https://">
			</div>
		</div>

		<div class="wcb-settings-row">
			<div class="wcb-settings-row-label"><label for="wcb-abuse-contact"><?php esc_html_e( 'Abuse contact email', 'wp-career-board' ); ?></label></div>
			<div class="wcb-settings-row-control">
				<input type="email" id="wcb-abuse-contact" class="regular-text" name="wcb_settings[abuse_contact_email]" value="<?php echo esc_attr( $wcb_abuse ); ?>">
				<span class="description"><?php esc_html_e( 'Shown in the app so members can email you about abuse. It is public, so use a support address. Leave blank to show your privacy page instead.', 'wp-career-board' ); ?></span>
			</div>
		</div>

		<h2><?php esc_html_e( 'Sign-in', 'wp-career-board' ); ?></h2>
		<div class="wcb-settings-row">
			<div class="wcb-settings-row-label"><?php esc_html_e( 'App Password Sign-In', 'wp-career-board' ); ?></div>
			<div class="wcb-settings-row-control">
				<label class="wcb-toggle-label">
					<span class="wcb-toggle">
						<input type="checkbox" name="wcb_settings[app_password_login]" value="1" <?php checked( ! empty( $settings['app_password_login'] ) ); ?>>
						<span class="wcb-toggle-slider"></span>
					</span>
					<?php esc_html_e( 'Let members sign in to the mobile app by typing their website password', 'wp-career-board' ); ?>
				</label>
				<span class="description"><?php esc_html_e( 'Off by default. The app can already sign members in without this: its "Connect with WordPress" option sends them to your normal login page, where two-factor and your security plugins apply, and no password ever reaches the app. Turn this on only if you want the extra convenience of typing a password directly in the app - it opens a route that accepts real account passwords, so leave it off if you run two-factor authentication or do not use the app at all.', 'wp-career-board' ); ?></span>
			</div>
		</div>

		<div class="wcb-settings-section__footer">
			<?php submit_button( __( 'Save Changes', 'wp-career-board' ), 'primary wcb-btn wcb-btn--primary', 'submit', false ); ?>
		</div>
		</form>
		<?php
	}

	/**
	 * Cancel a member's pending account deletion on their behalf.
	 *
	 * The member can always undo their own deletion by signing back in, but a
	 * support message asking someone else to do it is the common case, and the
	 * owner previously had no way to act on it - nor any way to see the request
	 * existed.
	 *
	 * @since 1.7.1
	 * @return void
	 */
	public function maybe_cancel_account_deletion(): void {
		if ( ! isset( $_GET['action'] ) || 'wcb_cancel_deletion' !== sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			return;
		}

		$user_id = isset( $_GET['user_id'] ) ? absint( wp_unslash( $_GET['user_id'] ) ) : 0;
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( $user_id <= 0 || '' === $nonce || ! wp_verify_nonce( $nonce, 'wcb_cancel_deletion_' . $user_id ) ) {
			return;
		}

		if ( ! wp_is_ability_granted( 'wcb/manage-settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
			return;
		}

		$user = get_userdata( $user_id );
		if ( $user && class_exists( '\WCB\Modules\Account\AccountDeletionService' ) ) {
			( new \WCB\Modules\Account\AccountDeletionService() )->cancel( $user );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wcb-settings&wcb_deletion_cancelled=1#privacy' ) );
		exit;
	}

	/**
	 * Privacy tab — the GDPR request audit trail.
	 *
	 * wcb_gdpr_log has recorded every export and erase request since 1.0.0 and
	 * nothing could read it: no admin screen, no REST route, and the GDPR
	 * exporter deliberately excludes it. A compliance audit trail only reachable
	 * with database access is no evidence at all at the moment an owner needs to
	 * show a request was honoured.
	 *
	 * Read-only on purpose. An audit trail an administrator can edit is not one,
	 * and rows age out with the user they belong to via the existing erase path.
	 *
	 * @since 1.7.1
	 *
	 * @param  array<string,mixed> $settings Current settings (unused; read-only view).
	 * @return void
	 */
	public function render_privacy_tab( array $settings = array() ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- signature fixed by the wcb_settings_tab_{slug} action.
		global $wpdb;

		$table = $wpdb->prefix . 'wcb_gdpr_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin-only audit view on a custom table.
		$exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		$per_page = 50;
		$paged    = isset( $_GET['wcb_log_page'] ) ? max( 1, absint( wp_unslash( $_GET['wcb_log_page'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		$offset   = ( $paged - 1 ) * $per_page;

		$total = 0;
		$rows  = array();

		if ( $exists ) {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- admin-only audit view; the only interpolation is $wpdb->prefix, so there is no user input to prepare.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin-only audit view; LIMIT/OFFSET paginated.
			$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, user_id, action, ip_hash, created_at FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
		}

		$pages = (int) ceil( $total / $per_page );
		?>
			<?php
			// Pending account deletions. Members can schedule their own deletion with
			// a 14-day grace window, and until now the owner could not see that a
			// single one was pending - not who, not when, and not in time to answer a
			// "I changed my mind" support message before the cron ran.
			$wcb_pending = get_users(
				array(
					'meta_key'     => '_wcb_deletion_scheduled_at', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin-only view, bounded by the number of leaving members.
					'meta_compare' => 'EXISTS',
					'number'       => 50,
					'fields'       => array( 'ID', 'user_login', 'user_email' ),
				)
			);
			?>
		<div class="wcb-settings-card">
			<div class="wcb-settings-card-header">
				<h2 class="wcb-settings-card-title"><?php esc_html_e( 'Pending account deletions', 'wp-career-board' ); ?></h2>
			</div>
			<div class="wcb-settings-card-body">
		<p class="description">
			<?php esc_html_e( 'Members who asked to delete their account. They stay signed out during the wait and can stop it themselves by signing back in. Cancel on their behalf if they contact you instead.', 'wp-career-board' ); ?>
		</p>

		<?php if ( empty( $wcb_pending ) ) : ?>
			<p><?php esc_html_e( 'No account deletions are pending.', 'wp-career-board' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Member', 'wp-career-board' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Deletes on', 'wp-career-board' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Time left', 'wp-career-board' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Action', 'wp-career-board' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $wcb_pending as $wcb_member ) : ?>
					<?php
					$wcb_when = (int) get_user_meta( $wcb_member->ID, '_wcb_deletion_scheduled_at', true );
					$wcb_left = $wcb_when - time();
					$wcb_undo = wp_nonce_url(
						add_query_arg(
							array(
								'page'    => 'wcb-settings',
								'action'  => 'wcb_cancel_deletion',
								'user_id' => $wcb_member->ID,
							),
							admin_url( 'admin.php' )
						),
						'wcb_cancel_deletion_' . $wcb_member->ID
					);
					?>
					<tr>
						<td><a href="<?php echo esc_url( get_edit_user_link( $wcb_member->ID ) ); ?>"><?php echo esc_html( $wcb_member->user_login ); ?></a></td>
						<td><?php echo esc_html( $wcb_when ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $wcb_when ) : '—' ); ?></td>
						<td>
							<?php
							echo $wcb_left > 0
								? esc_html( sprintf( /* translators: %s: human-readable duration. */ __( '%s left', 'wp-career-board' ), human_time_diff( time(), $wcb_when ) ) )
								: esc_html__( 'Due - runs on the next scheduled task', 'wp-career-board' );
							?>
						</td>
						<td><a href="<?php echo esc_url( $wcb_undo ); ?>" class="button button-small"><?php esc_html_e( 'Keep account', 'wp-career-board' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

			</div>
		</div>

		<div class="wcb-settings-card">
			<div class="wcb-settings-card-header">
				<h2 class="wcb-settings-card-title"><?php esc_html_e( 'Privacy request log', 'wp-career-board' ); ?></h2>
			</div>
			<div class="wcb-settings-card-body">
			<p class="description">
				<?php esc_html_e( 'Every personal-data export and erase request this plugin has processed. Keep it as evidence that a request was honoured. Visitor IP addresses are stored only as a one-way hash, never in plain text.', 'wp-career-board' ); ?>
			</p>

			<?php if ( ! $exists ) : ?>
				<p><?php esc_html_e( 'The request log table is not present. It is created on activation.', 'wp-career-board' ); ?></p>
			<?php elseif ( empty( $rows ) ) : ?>
				<p><?php esc_html_e( 'No personal-data requests have been processed yet.', 'wp-career-board' ); ?></p>
			<?php else : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: number of logged requests. */
						esc_html( _n( '%s request logged.', '%s requests logged.', $total, 'wp-career-board' ) ),
						esc_html( number_format_i18n( $total ) )
					);
					?>
				</p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Date', 'wp-career-board' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Request', 'wp-career-board' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Member', 'wp-career-board' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Origin (hashed)', 'wp-career-board' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$user  = get_userdata( (int) $row->user_id );
						$label = 'erase' === $row->action
							? __( 'Erase personal data', 'wp-career-board' )
							: __( 'Export personal data', 'wp-career-board' );
						?>
						<tr>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $row->created_at ) ); ?></td>
							<td><?php echo esc_html( $label ); ?></td>
							<td>
								<?php if ( $user ) : ?>
									<a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php echo esc_html( $user->user_login ); ?></a>
								<?php else : ?>
									<?php
									printf(
										/* translators: %d: user ID of a member who no longer exists. */
										esc_html__( 'Deleted member (#%d)', 'wp-career-board' ),
										(int) $row->user_id
									);
									?>
								<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( substr( (string) $row->ip_hash, 0, 12 ) ); ?></code></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $pages > 1 ) : ?>
					<p class="wcb-settings-pagination">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'      => add_query_arg( 'wcb_log_page', '%#%' ),
									'format'    => '',
									'current'   => $paged,
									'total'     => $pages,
									'prev_text' => __( '&laquo; Previous', 'wp-career-board' ),
									'next_text' => __( 'Next &raquo;', 'wp-career-board' ),
								)
							)
						);
						?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
