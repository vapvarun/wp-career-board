<?php
/**
 * Block render: wcb/employer-dashboard — sidebar layout employer interface.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$wcb_can_manage = wp_is_ability_granted( 'wcb/manage-company' );

if ( ! is_user_logged_in() ) {
	?>
	<div class="wcb-db-gate">
		<p><?php esc_html_e( 'Please sign in to access your dashboard.', 'wp-career-board' ); ?></p>
		<a href="<?php echo esc_url( wp_login_url( (string) get_permalink() ) ); ?>" class="wcb-db-btn wcb-db-btn--primary">
	<?php esc_html_e( 'Sign In', 'wp-career-board' ); ?>
		</a>
	</div>
	<?php
	return;
}

if ( ! $wcb_can_manage ) {
	$wcb_settings_opt   = (array) get_option( 'wcb_settings', array() );
	$wcb_emp_reg_page   = (int) ( $wcb_settings_opt['employer_registration_page'] ?? 0 );
	$wcb_cand_dash_page = (int) ( $wcb_settings_opt['candidate_dashboard_page'] ?? 0 );
	?>
	<div class="wcb-db-gate">
		<p><?php esc_html_e( 'The employer dashboard is for employers. If you are hiring, register as an employer. Otherwise, manage your applications and resumes from your candidate dashboard.', 'wp-career-board' ); ?></p>
	<?php if ( $wcb_emp_reg_page > 0 ) : ?>
		<a href="<?php echo esc_url( (string) get_permalink( $wcb_emp_reg_page ) ); ?>" class="wcb-db-btn wcb-db-btn--primary">
		<?php esc_html_e( 'Register as an employer', 'wp-career-board' ); ?>
		</a>
	<?php endif; ?>
	<?php if ( $wcb_cand_dash_page > 0 ) : ?>
		<a href="<?php echo esc_url( (string) get_permalink( $wcb_cand_dash_page ) ); ?>" class="wcb-db-btn">
		<?php esc_html_e( 'Go to Candidate Dashboard', 'wp-career-board' ); ?>
		</a>
	<?php endif; ?>
	</div>
	<?php
	return;
}

// Shared confirm-modal — used by closeJob() in view.js.
wp_enqueue_style( 'wcb-confirm-modal' );
wp_enqueue_script( 'wcb-confirm-modal' );

$wcb_employer_id = get_current_user_id();
$wcb_company_id  = (int) get_user_meta( $wcb_employer_id, '_wcb_company_id', true );
$wcb_company     = $wcb_company_id ? get_post( $wcb_company_id ) : null;

$wcb_company_name    = $wcb_company instanceof \WP_Post ? $wcb_company->post_title : '';
$wcb_company_desc    = $wcb_company instanceof \WP_Post ? $wcb_company->post_content : '';
$wcb_company_tagline = $wcb_company_id ? (string) get_post_meta( $wcb_company_id, '_wcb_tagline', true ) : '';
$wcb_company_site    = $wcb_company_id ? (string) get_post_meta( $wcb_company_id, '_wcb_website', true ) : '';
$wcb_company_ind     = $wcb_company_id ? (string) get_post_meta( $wcb_company_id, '_wcb_industry', true ) : '';
$wcb_company_size    = $wcb_company_id ? (string) get_post_meta( $wcb_company_id, '_wcb_company_size', true ) : '';
$wcb_company_hq      = $wcb_company_id ? (string) get_post_meta( $wcb_company_id, '_wcb_hq_location', true ) : '';
$wcb_company_type    = $wcb_company_id ? (string) get_post_meta( $wcb_company_id, '_wcb_company_type', true ) : '';

// Pro signals its notifications module through the wcb_module_renders slot; when
// present, the dashboard shows a Notifications item in the ACCOUNT nav whose panel
// renders that markup (trusted plugin Interactivity HTML — emitted as-is below,
// since wp_kses_post would strip the <template>/data-wp-each loop).
$wcb_module_renders  = (array) apply_filters( 'wcb_module_renders', array() );
$wcb_bell_enabled    = ! empty( $wcb_module_renders['notifications_bell'] );
$wcb_company_founded = $wcb_company_id ? (string) get_post_meta( $wcb_company_id, '_wcb_founded', true ) : '';
$wcb_company_li      = $wcb_company_id ? (string) get_post_meta( $wcb_company_id, '_wcb_linkedin', true ) : '';
$wcb_company_tw      = $wcb_company_id ? (string) get_post_meta( $wcb_company_id, '_wcb_twitter', true ) : '';
$wcb_company_logo    = $wcb_company_id ? (string) get_the_post_thumbnail_url( $wcb_company_id, 'medium' ) : '';

$wcb_company_archive_id = \WCB\Admin\Settings::int( 'company_archive_page', 0 );
$wcb_company_dir_url    = $wcb_company_archive_id > 0
	? (string) get_permalink( $wcb_company_archive_id )
	: '#';
$wcb_company_url        = $wcb_company_id ? (string) get_permalink( $wcb_company_id ) : $wcb_company_dir_url;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only param, no state mutation.
$wcb_apps_job_id   = absint( wp_unslash( $_GET['job_apps'] ?? '0' ) );
$wcb_dashboard_url = (string) get_permalink();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only param, no state mutation.
$wcb_edit_job_id = absint( wp_unslash( $_GET['edit'] ?? '0' ) );

// Pre-compute lightweight stats for instant render (avoids zero-flash before JS hydrates).
$wcb_job_counts = (array) wp_count_posts( 'wcb_job' );
if ( $wcb_employer_id ) {
	// Stats count the employer's OWN jobs (by author) so the cards match the
	// jobs/applications lists below, which also load by author.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lightweight count for initial render only.
	$wcb_total_employer_jobs = (int) $GLOBALS['wpdb']->get_var(
		$GLOBALS['wpdb']->prepare(
			"SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts}
			 WHERE post_type = 'wcb_job' AND post_status IN ('publish','pending','draft') AND post_author = %d",
			$wcb_employer_id
		)
	);
	$wcb_live_employer_jobs  = (int) $GLOBALS['wpdb']->get_var(
		$GLOBALS['wpdb']->prepare(
			"SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts}
			 WHERE post_type = 'wcb_job' AND post_status = 'publish' AND post_author = %d",
			$wcb_employer_id
		)
	);
	$wcb_total_apps          = (int) $GLOBALS['wpdb']->get_var(
		$GLOBALS['wpdb']->prepare(
			"SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts} a
			 INNER JOIN {$GLOBALS['wpdb']->postmeta} aj ON a.ID = aj.post_id AND aj.meta_key = '_wcb_job_id'
			 INNER JOIN {$GLOBALS['wpdb']->posts} j ON aj.meta_value = j.ID
			 WHERE a.post_type = 'wcb_application' AND a.post_status = 'publish' AND j.post_author = %d",
			$wcb_employer_id
		)
	);
	$wcb_week_ago            = gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS );
	$wcb_new_apps            = (int) $GLOBALS['wpdb']->get_var(
		$GLOBALS['wpdb']->prepare(
			"SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts} a
			 INNER JOIN {$GLOBALS['wpdb']->postmeta} aj ON a.ID = aj.post_id AND aj.meta_key = '_wcb_job_id'
			 INNER JOIN {$GLOBALS['wpdb']->posts} j ON aj.meta_value = j.ID
			 WHERE a.post_type = 'wcb_application' AND a.post_status = 'publish' AND j.post_author = %d AND a.post_date >= %s",
			$wcb_employer_id,
			$wcb_week_ago
		)
	);
} else {
	$wcb_total_employer_jobs = 0;
	$wcb_live_employer_jobs  = 0;
	$wcb_total_apps          = 0;
	$wcb_new_apps            = 0;
}

wp_interactivity_state(
	'wcb-employer-dashboard',
	array(
		'currentView'           => $wcb_apps_job_id > 0 ? 'applications' : ( $wcb_edit_job_id > 0 ? 'post-job' : 'overview' ),
		'jobFilter'             => 'all',
		'jobSearch'             => '',
		'appsFilter'            => 'all',
		'selectedAppId'         => null,
		'allApplications'       => array(),
		'jobs'                  => array(),
		'ssrTotalJobs'          => $wcb_total_employer_jobs,
		'ssrPublishedJobs'      => $wcb_live_employer_jobs,
		'ssrTotalApps'          => $wcb_total_apps,
		'ssrNewThisWeek'        => $wcb_new_apps,
		'loading'               => true,
		'error'                 => '',
		'noCompany'             => 0 === (int) $wcb_company_id,
		'apiBase'               => untrailingslashit( rest_url( 'wcb/v1' ) ),
		'nonce'                 => wp_create_nonce( 'wp_rest' ),
		'employerId'            => get_current_user_id(),
		// My Saves state - any logged-in user can bookmark jobs / companies /
		// resumes; employer dashboard surfaces those three lists in the
		// MY SAVES sidebar group (mirrors the candidate-dashboard pattern).
		'savedJobs'             => array(),
		'savedJobsLoading'      => false,
		'savedJobsError'        => '',
		'savedJobsCount'        => (int) count( (array) get_user_meta( get_current_user_id(), '_wcb_bookmark', false ) ),
		'savedCompanies'        => array(),
		'savedCompaniesLoading' => false,
		'savedCompaniesError'   => '',
		'savedCompaniesCount'   => (int) count( (array) get_user_meta( get_current_user_id(), '_wcb_company_bookmark', false ) ),
		'savedResumes'          => array(),
		'savedResumesLoading'   => false,
		'savedResumesError'     => '',
		'savedResumesCount'     => post_type_exists( 'wcb_resume' )
			? (int) count( (array) get_user_meta( get_current_user_id(), '_wcb_resume_bookmark', false ) )
			: 0,
		'companyId'             => $wcb_company_id,
		'companyName'           => $wcb_company_name,
		'companyDesc'           => $wcb_company_desc,
		'companyTagline'        => $wcb_company_tagline,
		'companySite'           => $wcb_company_site,
		'companyIndustry'       => $wcb_company_ind,
		'industryLabels'        => \WCB\Core\Industries::all(),
		'companySize'           => $wcb_company_size,
		'companyHq'             => $wcb_company_hq,
		'companyType'           => $wcb_company_type,
		'companyFounded'        => $wcb_company_founded,
		'companyLinkedin'       => $wcb_company_li,
		'companyTwitter'        => $wcb_company_tw,
		'companyLogoUrl'        => $wcb_company_logo,
		'logoUploading'         => false,
		'saving'                => false,
		'saved'                 => false,
		'companyDirUrl'         => $wcb_company_dir_url,
		'dashboardUrl'          => $wcb_dashboard_url,
		'customFieldGroups'     => apply_filters( 'wcb_company_form_fields', array(), $wcb_company_id ),
		'customFields'          => (object) (
			$wcb_company_id > 0
				? \WCB\Core\FormCustomFields::load_values(
					(array) apply_filters( 'wcb_company_form_fields', array(), $wcb_company_id ),
					$wcb_company_id
				)
				: array()
		),
		'appsJobId'             => $wcb_apps_job_id,
		'appsJobTitle'          => '',
		'appsJobSearch'         => '',
		'applications'          => array(),
		'appsLoading'           => false,
		'appsError'             => '',
		'employerEmail'         => wp_get_current_user()->user_email,
		'displayName'           => wp_get_current_user()->display_name,
		// Account Settings panel — editable display name + email + password.
		'accountName'           => wp_get_current_user()->display_name,
		'accountEmail'          => wp_get_current_user()->user_email,
		'curPassword'           => '',
		'newPassword'           => '',
		'confPassword'          => '',
		'accountMsg'            => '',
		'accountMsgType'        => '',
		'accountSaving'         => false,
		// Inline confirmation shown after changing an applicant's status.
		'statusMsg'             => '',
		'i18nStatusSaved'       => __( 'Status updated. The candidate has been notified.', 'wp-career-board' ),
		'i18nStatusError'       => __( 'Could not update the status. Please try again.', 'wp-career-board' ),
		'pwMsg'                 => '',
		'pwMsgType'             => '',
		'pwSaving'              => false,
		// Set true by the embedded Post-a-Job form (wcb-job-form) after a
		// successful submit, so switchToJobs() refreshes the stale My Jobs list.
		'_needsJobsRefresh'     => false,
		'passwordResetUrl'      => wp_lostpassword_url( $wcb_dashboard_url ),
		'creditBalance'         => (int) apply_filters( 'wcb_employer_credit_balance', 0, $wcb_employer_id ),
		'creditPurchaseUrl'     => (string) apply_filters( 'wcb_credit_purchase_url', '' ),
		'creditsEnabled'        => (bool) apply_filters( 'wcb_credits_enabled', false ),
		// Low-balance threshold — Pro returns the admin-configured value, Free
		// defaults to 0 (no warning). When balance dips below the threshold
		// dashboard renders a subtle banner pointing at the Buy Credits page.
		'creditLowThreshold'    => (int) apply_filters( 'wcb_credit_low_threshold', 0 ),
		// Post-purchase success — set when the checkout redirect lands the
		// employer back on the dashboard with ?wcb_credits_added=N. Banner
		// auto-dismisses on first interaction so the message doesn't linger.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
		'creditsJustAdded'      => isset( $_GET['wcb_credits_added'] ) ? max( 0, (int) $_GET['wcb_credits_added'] ) : 0,
		'bellNotifications'     => array(),
		'bellUnreadCount'       => 0,
		'bellLoading'           => false,
		'bellEnabled'           => $wcb_bell_enabled,
		// AI applicant ranking — Pro answers wcb_ai_ranking_available and serves
		// the /ai/ranked-applications/{id} route; Free only surfaces the button.
		'aiRanking'             => (bool) apply_filters( 'wcb_ai_ranking_available', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		'aiRanked'              => false,
		'aiRankLoading'         => false,
		'strings'               => array(
			'errorLoadJobs'            => __( 'Could not load your jobs.', 'wp-career-board' ),
			'errorLoadApps'            => __( 'Could not load applications.', 'wp-career-board' ),
			'errorConnectionApps'      => __( 'Connection error loading applications.', 'wp-career-board' ),
			'errorCompanyNameRequired' => __( 'Company name is required.', 'wp-career-board' ),
			'errorSaveProfile'         => __( 'Could not save profile. Please try again.', 'wp-career-board' ),
			'errorSaveLogo'            => __( 'Please save your company profile before uploading a logo.', 'wp-career-board' ),
			'errorConnection'          => __( 'Connection error. Please check your network and try again.', 'wp-career-board' ),
			'confirmClearAllTitle'     => __( 'Clear all notifications?', 'wp-career-board' ),
			'confirmClearAllMsg'       => __( 'This permanently removes all of your notifications. This cannot be undone.', 'wp-career-board' ),
			'clearAll'                 => __( 'Clear all', 'wp-career-board' ),
			'overview'                 => __( 'Overview', 'wp-career-board' ),
			'myJobs'                   => __( 'My Jobs', 'wp-career-board' ),
			'applications'             => __( 'Applications', 'wp-career-board' ),
			'profile'                  => __( 'Profile', 'wp-career-board' ),
			'postAJob'                 => __( 'Post a Job', 'wp-career-board' ),
			'dashboard'                => __( 'Dashboard', 'wp-career-board' ),
			'logoUploading'            => __( 'Uploading\u2026', 'wp-career-board' ),
			'logoChange'               => __( 'Change Logo', 'wp-career-board' ),
			'logoUpload'               => __( 'Upload Logo', 'wp-career-board' ),
			'viewAppFrom'              => __( 'View application from ', 'wp-career-board' ),
			'jobsWithApps'             => __( ' jobs with applications', 'wp-career-board' ),
			'jobSingular'              => __( ' job', 'wp-career-board' ),
			'jobsOf'                   => __( ' of ', 'wp-career-board' ),
			'jobsPlural'               => __( ' jobs', 'wp-career-board' ),
			'appsHeadingPrefix'        => __( 'Applications: ', 'wp-career-board' ),
			'appsHeadingDefault'       => __( 'Applications', 'wp-career-board' ),
			'confirmCloseJob'          => __( 'Are you sure you want to close this job? It will no longer be visible to candidates.', 'wp-career-board' ),
			/* translators: %d is the credit count just added to the employer's balance. */
			'creditsAdded'             => __( '%d credits added to your balance.', 'wp-career-board' ),
			'creditsAddedSingular'     => __( '1 credit added to your balance.', 'wp-career-board' ),
			/* translators: %d is the current low-credit balance. */
			'lowBalance'               => __( 'Low balance: %d credits left.', 'wp-career-board' ),
			'lowBalanceSingular'       => __( 'Low balance: 1 credit left.', 'wp-career-board' ),
			'aiRankButton'             => __( 'Rank by AI fit', 'wp-career-board' ),
			'aiRankingLabel'           => __( 'Ranking…', 'wp-career-board' ),
		),
	)
);
?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-employer-dashboard"
	data-wp-init="actions.init"
>

<div class="wcb-dashboard-shell">

	<!-- SIDEBAR -->
	<aside class="wcb-sidebar" data-wp-class--wcb-nav-open="state.navOpen">
		<button type="button" class="wcb-nav-toggle"
			aria-label="<?php esc_attr_e( 'Toggle navigation', 'wp-career-board' ); ?>"
			data-wp-on--click="actions.toggleNav"
			data-wp-bind--aria-expanded="state.navOpen">
			<span data-wp-text="state.activeTabLabel"><?php esc_html_e( 'Dashboard', 'wp-career-board' ); ?></span>
			<span class="wcb-nav-toggle-icon" aria-hidden="true"></span>
		</button>
		<button type="button" class="wcb-sidebar-logo" id="wcb-tab-overview"
			role="tab" aria-controls="wcb-panel-overview"
			data-wp-bind--aria-selected="state.isViewOverview"
			data-wp-on--click="actions.switchToOverview"
			data-wp-class--wcb-nav-active="state.isViewOverview">
			<?php esc_html_e( 'Dashboard', 'wp-career-board' ); ?>
		</button>

		<nav class="wcb-sidebar-nav" role="tablist" aria-label="<?php esc_attr_e( 'Dashboard navigation', 'wp-career-board' ); ?>" aria-orientation="vertical">
			<span class="wcb-nav-section-label"><?php esc_html_e( 'JOBS', 'wp-career-board' ); ?></span>
			<button type="button" role="tab" class="wcb-nav-item" id="wcb-tab-jobs" aria-controls="wcb-panel-jobs" data-wp-bind--aria-selected="state.isViewJobs" data-wp-class--wcb-nav-active="state.isViewJobs" data-wp-on--click="actions.switchToJobs">
				<?php esc_html_e( 'My Jobs', 'wp-career-board' ); ?>
				<span class="wcb-nav-badge" data-wp-text="state.totalJobs">0</span>
			</button>
			<button type="button" role="tab" class="wcb-nav-item" id="wcb-tab-postjob" aria-controls="wcb-panel-postjob" data-wp-bind--aria-selected="state.isViewPostJob" data-wp-class--wcb-nav-active="state.isViewPostJob" data-wp-on--click="actions.switchToPostJob">
				<?php esc_html_e( 'Post a Job', 'wp-career-board' ); ?>
			</button>

			<span class="wcb-nav-section-label"><?php esc_html_e( 'HIRING', 'wp-career-board' ); ?></span>
			<button type="button" role="tab" class="wcb-nav-item" id="wcb-tab-apps" aria-controls="wcb-panel-apps" data-wp-bind--aria-selected="state.isViewApplications" data-wp-class--wcb-nav-active="state.isViewApplications" data-wp-on--click="actions.switchToApplications">
				<?php esc_html_e( 'Applications', 'wp-career-board' ); ?>
				<span class="wcb-nav-badge" data-wp-text="state.totalApps">0</span>
			</button>

			<span class="wcb-nav-section-label"><?php esc_html_e( 'COMPANY', 'wp-career-board' ); ?></span>
			<button type="button" role="tab" class="wcb-nav-item" id="wcb-tab-company" aria-controls="wcb-panel-company" data-wp-bind--aria-selected="state.isViewCompany" data-wp-class--wcb-nav-active="state.isViewCompany" data-wp-on--click="actions.switchToCompany">
				<?php esc_html_e( 'Profile', 'wp-career-board' ); ?>
			</button>
			<a class="wcb-nav-item wcb-nav-item--link" href="<?php echo esc_url( $wcb_company_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Public Page', 'wp-career-board' ); ?> &#8599;
			</a>

			<?php if ( apply_filters( 'wcb_credits_enabled', false ) ) : ?>
			<span class="wcb-nav-section-label"><?php esc_html_e( 'CREDITS', 'wp-career-board' ); ?></span>
			<span class="wcb-nav-item wcb-nav-item--static">
				<?php esc_html_e( 'Balance', 'wp-career-board' ); ?>
				<span class="wcb-nav-badge" data-wp-text="state.creditBalance">0</span>
			</span>
				<?php
				$wcb_purchase_url = (string) apply_filters( 'wcb_credit_purchase_url', '' );
				if ( $wcb_purchase_url ) :
					?>
			<a class="wcb-nav-item wcb-nav-item--link" href="<?php echo esc_url( $wcb_purchase_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Buy Credits', 'wp-career-board' ); ?> &#8599;
			</a>
				<?php endif; ?>
			<?php endif; ?>

			<?php
			/* My Saves - any logged-in user can bookmark jobs, companies,
					or resumes. Employers are most likely to save candidate
					resumes (Saved Resumes) but jobs + companies are kept here
					too so the surface is consistent with the candidate
					dashboard. Saved Resumes hides itself when wcb_resume
					isn't registered (Free-only sites). */
			?>
			<span class="wcb-nav-section-label"><?php esc_html_e( 'MY SAVES', 'wp-career-board' ); ?></span>
			<button type="button" role="tab" class="wcb-nav-item" id="wcb-tab-saved-jobs" data-wp-bind--aria-selected="state.isViewSavedJobs" data-wp-class--wcb-nav-active="state.isViewSavedJobs" data-wp-on--click="actions.switchToSavedJobs">
				<?php esc_html_e( 'Saved Jobs', 'wp-career-board' ); ?>
				<span class="wcb-nav-badge" data-wp-text="state.savedJobsCount">0</span>
			</button>
			<button type="button" role="tab" class="wcb-nav-item" id="wcb-tab-saved-companies" data-wp-bind--aria-selected="state.isViewSavedCompanies" data-wp-class--wcb-nav-active="state.isViewSavedCompanies" data-wp-on--click="actions.switchToSavedCompanies">
				<?php esc_html_e( 'Saved Companies', 'wp-career-board' ); ?>
				<span class="wcb-nav-badge" data-wp-text="state.savedCompaniesCount">0</span>
			</button>
			<?php if ( post_type_exists( 'wcb_resume' ) ) : ?>
			<button type="button" role="tab" class="wcb-nav-item" id="wcb-tab-saved-resumes" data-wp-bind--aria-selected="state.isViewSavedResumes" data-wp-class--wcb-nav-active="state.isViewSavedResumes" data-wp-on--click="actions.switchToSavedResumes">
				<?php esc_html_e( 'Saved Resumes', 'wp-career-board' ); ?>
				<span class="wcb-nav-badge" data-wp-text="state.savedResumesCount">0</span>
			</button>
			<?php endif; ?>

			<span class="wcb-nav-section-label"><?php esc_html_e( 'ACCOUNT', 'wp-career-board' ); ?></span>
			<button type="button" role="tab" class="wcb-nav-item" id="wcb-tab-settings"
				data-wp-bind--aria-selected="state.isViewSettings"
				data-wp-class--wcb-nav-active="state.isViewSettings"
				data-wp-on--click="actions.switchToSettings">
				<?php esc_html_e( 'Settings', 'wp-career-board' ); ?>
			</button>
			<?php if ( $wcb_bell_enabled ) : ?>
			<button type="button" role="tab" class="wcb-nav-item" id="wcb-tab-notifications"
				data-wp-bind--aria-selected="state.isViewNotifications"
				data-wp-class--wcb-nav-active="state.isViewNotifications"
				data-wp-on--click="actions.switchToNotifications">
				<?php esc_html_e( 'Notifications', 'wp-career-board' ); ?>
				<span class="wcb-nav-badge" data-wp-class--wcb-hidden="!state.bellUnreadCount" data-wp-text="state.bellUnreadCount"></span>
			</button>
			<?php endif; ?>
		</nav>

		<button type="button" class="wcb-sidebar-cta" data-wp-on--click="actions.switchToPostJob">
			+ <?php esc_html_e( 'Post a Job', 'wp-career-board' ); ?>
		</button>

		<div class="wcb-sidebar-user">
			<div class="wcb-sidebar-avatar" data-wp-text="state.companyInitials" aria-hidden="true"></div>
			<span class="wcb-sidebar-company" data-wp-text="state.sidebarName"></span>
		</div>
	</aside>

	<!-- MAIN CONTENT -->
	<main class="wcb-main">

		<!-- VIEW: Overview -->
		<div class="wcb-view-panel" id="wcb-panel-overview" role="tabpanel" aria-labelledby="wcb-tab-overview" data-wp-class--wcb-view-active="state.isViewOverview">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'Overview', 'wp-career-board' ); ?></h1>
			</div>

			<div class="wcb-db-onboard" data-wp-class--wcb-shown="state.noCompany">
				<div class="wcb-db-onboard__text">
					<p class="wcb-db-onboard__title"><?php esc_html_e( 'Welcome! Let\'s get you set up.', 'wp-career-board' ); ?></p>
					<p class="wcb-db-onboard__msg"><?php esc_html_e( 'Create your company profile to start posting jobs and receiving applications.', 'wp-career-board' ); ?></p>
				</div>
				<button type="button" class="wcb-db-btn wcb-db-btn--primary" data-wp-on--click="actions.switchToCompany"><?php esc_html_e( 'Set Up Company Profile', 'wp-career-board' ); ?></button>
			</div>

			<div class="wcb-db-onboard" data-wp-class--wcb-shown="state.noJobs">
				<div class="wcb-db-onboard__text">
					<p class="wcb-db-onboard__title"><?php esc_html_e( 'Your company is ready.', 'wp-career-board' ); ?></p>
					<p class="wcb-db-onboard__msg"><?php esc_html_e( 'Post your first job to start receiving applications.', 'wp-career-board' ); ?></p>
				</div>
				<button type="button" class="wcb-db-btn wcb-db-btn--primary" data-wp-on--click="actions.switchToPostJob"><?php esc_html_e( 'Post Your First Job', 'wp-career-board' ); ?></button>
			</div>

			<?php if ( apply_filters( 'wcb_credits_enabled', false ) ) : ?>
			<!--
				Credits banners — only render when credits are enabled. The success
				banner appears once after a successful WooCommerce checkout
				redirected back here with ?wcb_credits_added=N. The low-balance
				banner shows when state.creditBalance dips below the threshold.
			-->
			<div class="wcb-credit-success-banner" data-wp-class--wcb-shown="state.justAddedCredits" role="status">
				<span class="wcb-credit-banner__icon" aria-hidden="true"><?php echo \WCB\Core\Icon::svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?></span>
				<span class="wcb-credit-banner__text" data-wp-text="state.justAddedCreditsMessage"></span>
				<button type="button" class="wcb-credit-banner__dismiss" data-wp-on--click="actions.dismissCreditSuccess" aria-label="<?php esc_attr_e( 'Dismiss', 'wp-career-board' ); ?>">&times;</button>
			</div>
			<div class="wcb-credit-low-banner" data-wp-class--wcb-shown="state.isCreditBalanceLow" role="status">
				<span class="wcb-credit-banner__icon" aria-hidden="true">&#9888;</span>
				<span class="wcb-credit-banner__text" data-wp-text="state.lowBalanceMessage"></span>
				<a class="wcb-credit-banner__link" data-wp-bind--href="state.creditPurchaseUrl" data-wp-class--wcb-hidden="!state.creditPurchaseUrl"><?php esc_html_e( 'Buy more', 'wp-career-board' ); ?></a>
			</div>
			<?php endif; ?>

			<div class="wcb-stats-row">
				<div class="wcb-stat-card">
					<span class="wcb-stat-value" data-wp-text="state.totalJobs">0</span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Total Jobs', 'wp-career-board' ); ?></span>
				</div>
				<div class="wcb-stat-card wcb-stat-card--green">
					<span class="wcb-stat-value" data-wp-text="state.publishedJobs">0</span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Live', 'wp-career-board' ); ?></span>
				</div>
				<div class="wcb-stat-card wcb-stat-card--blue">
					<span class="wcb-stat-value" data-wp-text="state.totalApps">0</span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Total Applications', 'wp-career-board' ); ?></span>
				</div>
				<div class="wcb-stat-card wcb-stat-card--amber">
					<span class="wcb-stat-value" data-wp-text="state.newThisWeek">0</span>
					<span class="wcb-stat-label"><?php esc_html_e( 'New This Week', 'wp-career-board' ); ?></span>
				</div>
				<?php if ( apply_filters( 'wcb_credits_enabled', false ) ) : ?>
				<div class="wcb-stat-card wcb-stat-card--purple" data-wp-bind--hidden="!state.creditsEnabled">
					<span class="wcb-stat-value" data-wp-text="state.creditBalance">0</span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Credits', 'wp-career-board' ); ?></span>
				</div>
				<?php endif; ?>
			</div>

			<div class="wcb-two-col">
				<div class="wcb-panel">
					<div class="wcb-panel-header">
						<span class="wcb-panel-title"><?php esc_html_e( 'Recent Applications', 'wp-career-board' ); ?></span>
						<button type="button" class="wcb-panel-link" data-wp-on--click="actions.switchToApplications"><?php esc_html_e( 'View all →', 'wp-career-board' ); ?></button>
					</div>
					<div data-wp-class--wcb-shown="state.hasRecentApps">
						<template data-wp-each--app="state.overviewRecentApps" data-wp-each-key="context.app.id">
							<div class="wcb-overview-app-row">
								<div class="wcb-app-avatar" data-wp-text="context.app.initials" aria-hidden="true"></div>
								<div class="wcb-app-info">
									<span class="wcb-app-name" data-wp-text="context.app.applicant_name"></span>
									<span class="wcb-app-job" data-wp-text="context.app.job_title"></span>
								</div>
								<span class="wcb-status-badge" role="status" data-wp-text="context.app.status" data-wp-bind--data-status="context.app.status"></span>
							</div>
						</template>
					</div>
					<p class="wcb-panel-empty" data-wp-class--wcb-shown="state.noRecentApps"><?php esc_html_e( 'No applications yet.', 'wp-career-board' ); ?></p>
				</div>

				<div class="wcb-panel">
					<div class="wcb-panel-header">
						<span class="wcb-panel-title"><?php esc_html_e( 'Active Jobs', 'wp-career-board' ); ?></span>
						<button type="button" class="wcb-panel-link" data-wp-on--click="actions.switchToJobs"><?php esc_html_e( 'Manage all →', 'wp-career-board' ); ?></button>
					</div>
					<div data-wp-class--wcb-shown="state.hasActiveJobs">
						<template data-wp-each--job="state.overviewActiveJobs" data-wp-each-key="context.job.id">
							<div class="wcb-overview-job-row">
								<div class="wcb-status-dot wcb-status-dot--green"></div>
								<div class="wcb-job-info">
									<span class="wcb-job-title" data-wp-text="context.job.title"></span>
									<span class="wcb-job-meta" data-wp-text="context.job.location"></span>
								</div>
								<span class="wcb-status-badge" data-wp-text="context.job.appLabel"></span>
							</div>
						</template>
					</div>
					<p class="wcb-panel-empty" data-wp-class--wcb-shown="state.noActiveJobs"><?php esc_html_e( 'No active jobs.', 'wp-career-board' ); ?></p>
				</div>
			</div>
		</div>

		<!-- VIEW: My Jobs -->
		<div class="wcb-view-panel" id="wcb-panel-jobs" role="tabpanel" aria-labelledby="wcb-tab-jobs" data-wp-class--wcb-view-active="state.isViewJobs">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'My Jobs', 'wp-career-board' ); ?></h1>
				<input type="search" class="wcb-job-search" aria-label="<?php esc_attr_e( 'Search your jobs', 'wp-career-board' ); ?>" placeholder="<?php esc_attr_e( 'Search jobs…', 'wp-career-board' ); ?>" data-wp-on--input="actions.setJobSearch" />
			</div>

			<div class="wcb-filter-bar">
				<button type="button" class="wcb-filter-pill" data-wcb-filter="all" data-wp-class--wcb-filter-active="state.isFilterAll" data-wp-on--click="actions.setJobFilter"><?php esc_html_e( 'All', 'wp-career-board' ); ?></button>
				<button type="button" class="wcb-filter-pill" data-wcb-filter="live" data-wp-class--wcb-filter-active="state.isFilterLive" data-wp-on--click="actions.setJobFilter"><?php esc_html_e( 'Live', 'wp-career-board' ); ?></button>
				<button type="button" class="wcb-filter-pill" data-wcb-filter="draft" data-wp-class--wcb-filter-active="state.isFilterDraft" data-wp-on--click="actions.setJobFilter"><?php esc_html_e( 'Draft', 'wp-career-board' ); ?></button>
				<button type="button" class="wcb-filter-pill" data-wcb-filter="pending" data-wp-class--wcb-filter-active="state.isFilterPending" data-wp-on--click="actions.setJobFilter"><?php esc_html_e( 'Pending', 'wp-career-board' ); ?></button>
				<button type="button" class="wcb-filter-pill" data-wcb-filter="closed" data-wp-class--wcb-filter-active="state.isFilterClosed" data-wp-on--click="actions.setJobFilter"><?php esc_html_e( 'Closed', 'wp-career-board' ); ?></button>
				<button type="button" class="wcb-filter-pill" data-wcb-filter="rejected" data-wp-class--wcb-filter-active="state.isFilterRejected" data-wp-on--click="actions.setJobFilter"><?php esc_html_e( 'Rejected', 'wp-career-board' ); ?></button>
			</div>

			<div class="wcb-db-loading" role="status" aria-label="<?php esc_attr_e( 'Loading', 'wp-career-board' ); ?>" data-wp-class--wcb-shown="state.loading">
				<div class="wcb-skeleton-row"></div>
				<div class="wcb-skeleton-row"></div>
				<div class="wcb-skeleton-row"></div>
			</div>

			<div class="wcb-db-empty" data-wp-class--wcb-shown="state.noCompany">
				<p class="wcb-db-empty-msg"><?php esc_html_e( 'Set up your company profile first before posting jobs.', 'wp-career-board' ); ?></p>
				<button type="button" class="wcb-db-btn wcb-db-btn--secondary" data-wp-on--click="actions.switchToCompany"><?php esc_html_e( 'Set Up Company Profile', 'wp-career-board' ); ?></button>
			</div>

			<p class="wcb-db-error" role="alert" data-wp-class--wcb-shown="state.error" data-wp-text="state.error"></p>

			<div class="wcb-db-empty" data-wp-class--wcb-shown="state.noJobs">
				<p class="wcb-db-empty-msg"><?php esc_html_e( 'No jobs posted yet.', 'wp-career-board' ); ?></p>
				<button type="button" class="wcb-db-btn wcb-db-btn--secondary" data-wp-on--click="actions.switchToPostJob"><?php esc_html_e( 'Post Your First Job', 'wp-career-board' ); ?></button>
			</div>

			<div class="wcb-jobs-list" aria-live="polite" data-wp-class--wcb-shown="state.hasJobs">
				<template data-wp-each--job="state.filteredJobs" data-wp-each-key="context.job.id">
					<article class="wcb-job-row" data-wp-class--wcb-job-closed="context.job.isClosed" data-wp-class--wcb-job-expired="context.job.isExpired">
						<div class="wcb-status-dot" data-wp-bind--data-status="context.job.status"></div>
						<div class="wcb-job-info">
							<span class="wcb-job-title" data-wp-text="context.job.title"></span>
							<span class="wcb-job-meta" data-wp-text="context.job.location"></span>
						</div>
						<span class="wcb-status-badge" role="status" data-wp-text="context.job.statusLabel" data-wp-bind--data-status="context.job.status"></span>
						<button type="button" class="wcb-apps-chip" data-wp-class--wcb-hidden="!context.job.appCount" data-wp-text="context.job.appLabel" data-wp-bind--data-wcb-job-id="context.job.id" data-wp-on--click="actions.switchAppsJob"></button>
						<span class="wcb-apps-chip wcb-apps-chip--empty" data-wp-class--wcb-hidden="context.job.appCount" data-wp-text="context.job.appLabel"></span>
						<div class="wcb-job-actions">
							<a class="wcb-db-link-btn" data-wp-bind--href="context.job.permalink" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View ↗', 'wp-career-board' ); ?></a>
							<a class="wcb-db-link-btn wcb-db-link-btn--edit" data-wp-bind--href="context.job.editUrl"><?php esc_html_e( 'Edit', 'wp-career-board' ); ?></a>
							<button type="button" class="wcb-db-link-btn wcb-db-link-btn--close" data-wp-class--wcb-hidden="state.isJobInactive" data-wp-bind--data-wcb-job-id="context.job.id" data-wp-on--click="actions.closeJob"><?php esc_html_e( 'Close', 'wp-career-board' ); ?></button>
							<button type="button" class="wcb-db-link-btn wcb-db-link-btn--publish" data-wp-class--wcb-hidden="!context.job.isDraft" data-wp-bind--data-wcb-job-id="context.job.id" data-wp-on--click="actions.reopenJob"><?php esc_html_e( 'Publish', 'wp-career-board' ); ?></button>
							<button type="button" class="wcb-db-link-btn wcb-db-link-btn--publish" data-wp-class--wcb-hidden="!context.job.isRejected" data-wp-bind--data-wcb-job-id="context.job.id" data-wp-on--click="actions.reopenJob"><?php esc_html_e( 'Resubmit', 'wp-career-board' ); ?></button>
							<button type="button" class="wcb-db-link-btn wcb-db-link-btn--reopen" data-wp-class--wcb-hidden="!state.isJobInactive" data-wp-bind--data-wcb-job-id="context.job.id" data-wp-on--click="actions.reopenJob"><?php esc_html_e( 'Reopen', 'wp-career-board' ); ?></button>
						</div>
					</article>
				</template>
			</div>
		</div>

		<!-- VIEW: Applications -->
		<div class="wcb-view-panel" id="wcb-panel-apps" role="tabpanel" aria-labelledby="wcb-tab-apps" data-wp-class--wcb-view-active="state.isViewApplications">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'Applications', 'wp-career-board' ); ?></h1>
			</div>

			<div class="wcb-apps-selector" data-wp-class--wcb-shown="state.hasJobsWithApps">
				<div class="wcb-apps-selector-header">
					<input type="search" class="wcb-apps-job-search" aria-label="<?php esc_attr_e( 'Search applications by job', 'wp-career-board' ); ?>" placeholder="<?php esc_attr_e( 'Search jobs...', 'wp-career-board' ); ?>" data-wp-on--input="actions.setAppsJobSearch" data-wp-on--search="actions.setAppsJobSearch" />
					<span class="wcb-apps-selector-hint" data-wp-text="state.appsJobSelectorHint"></span>
				</div>
				<div class="wcb-apps-job-list">
					<template data-wp-each--job="state.filteredJobsWithApps" data-wp-each-key="context.job.id">
						<button type="button" class="wcb-apps-job-item" data-wp-class--wcb-active="state.isSelectedAppsJob" data-wp-bind--data-wcb-job-id="context.job.id" data-wp-on--click="actions.switchAppsJob">
							<span class="wcb-apps-job-item-title" data-wp-text="context.job.title"></span>
							<span class="wcb-apps-job-item-count" data-wp-text="context.job.appCount"></span>
						</button>
					</template>
					<p class="wcb-apps-no-match" data-wp-class--wcb-shown="state.appsJobNoMatch"><?php esc_html_e( 'No jobs match your search.', 'wp-career-board' ); ?></p>
				</div>
			</div>

			<div class="wcb-db-empty" data-wp-class--wcb-shown="state.noJobSelected">
				<p class="wcb-db-empty-msg"><?php esc_html_e( 'Select a job above to view its applications.', 'wp-career-board' ); ?></p>
				<button type="button" class="wcb-db-btn wcb-db-btn--secondary" data-wp-on--click="actions.switchToJobs"><?php esc_html_e( 'Go to My Jobs', 'wp-career-board' ); ?></button>
			</div>

			<div class="wcb-apps-filter-bar wcb-filter-bar" data-wp-class--wcb-shown="state.hasApplications">
				<button type="button" class="wcb-filter-pill" data-wcb-filter="all" data-wp-class--wcb-filter-active="state.isAppsFilterAll" data-wp-on--click="actions.setAppsFilter">
					<?php esc_html_e( 'All', 'wp-career-board' ); ?>
					<span class="wcb-pill-count" data-wp-text="state.appsCountAll"></span>
				</button>
				<button type="button" class="wcb-filter-pill" data-wcb-filter="submitted" data-wp-class--wcb-filter-active="state.isAppsFilterSubmitted" data-wp-on--click="actions.setAppsFilter">
					<?php esc_html_e( 'New', 'wp-career-board' ); ?>
					<span class="wcb-pill-count" data-wp-text="state.appsCountSubmitted"></span>
				</button>
				<button type="button" class="wcb-filter-pill" data-wcb-filter="reviewing" data-wp-class--wcb-filter-active="state.isAppsFilterReviewing" data-wp-on--click="actions.setAppsFilter">
					<?php esc_html_e( 'Reviewing', 'wp-career-board' ); ?>
					<span class="wcb-pill-count" data-wp-text="state.appsCountReviewing"></span>
				</button>
				<button type="button" class="wcb-filter-pill" data-wcb-filter="shortlisted" data-wp-class--wcb-filter-active="state.isAppsFilterShortlisted" data-wp-on--click="actions.setAppsFilter">
					<?php esc_html_e( 'Shortlisted', 'wp-career-board' ); ?>
					<span class="wcb-pill-count" data-wp-text="state.appsCountShortlisted"></span>
				</button>
				<button type="button" class="wcb-filter-pill" data-wcb-filter="rejected" data-wp-class--wcb-filter-active="state.isAppsFilterRejected" data-wp-on--click="actions.setAppsFilter">
					<?php esc_html_e( 'Rejected', 'wp-career-board' ); ?>
					<span class="wcb-pill-count" data-wp-text="state.appsCountRejected"></span>
				</button>
				<button type="button" class="wcb-filter-pill" data-wcb-filter="hired" data-wp-class--wcb-filter-active="state.isAppsFilterHired" data-wp-on--click="actions.setAppsFilter">
					<?php esc_html_e( 'Hired', 'wp-career-board' ); ?>
					<span class="wcb-pill-count" data-wp-text="state.appsCountHired"></span>
				</button>
			</div>

			<p class="wcb-db-error" role="alert" data-wp-class--wcb-shown="state.appsError" data-wp-text="state.appsError"></p>

			<div class="wcb-db-loading" role="status" aria-label="<?php esc_attr_e( 'Loading', 'wp-career-board' ); ?>" data-wp-class--wcb-shown="state.appsLoading">
				<div class="wcb-skeleton-row"></div>
				<div class="wcb-skeleton-row"></div>
			</div>

			<div class="wcb-db-empty" data-wp-class--wcb-shown="state.noApplications">
				<p class="wcb-db-empty-msg"><?php esc_html_e( 'No applications yet for this job.', 'wp-career-board' ); ?></p>
			</div>

			<div class="wcb-split-panel" data-wp-class--wcb-shown="state.hasApplications">
				<div class="wcb-applicant-list" aria-live="polite">
					<button type="button" class="wcb-btn wcb-btn--ghost wcb-ai-rank-btn" data-wp-class--wcb-hidden="!state.showAiRankButton" data-wp-bind--disabled="state.aiRankLoading" data-wp-on--click="actions.rankByAi" data-wp-text="state.aiRankBtnLabel"></button>
					<template data-wp-each--app="state.filteredApps" data-wp-each-key="context.app.id">
						<div class="wcb-applicant-row" role="button" tabindex="0" data-wp-class--wcb-selected="state.isSelectedApp" data-wp-bind--data-wcb-app-id="context.app.id" data-wp-bind--aria-label="state.applicantRowLabel" data-wp-on--click="actions.selectApplicant" data-wp-on--keydown="actions.handleRowKeydown">
							<div class="wcb-app-avatar" data-wp-text="context.app.initials" aria-hidden="true"></div>
							<div class="wcb-app-info">
								<span class="wcb-app-name" data-wp-text="context.app.applicant_name"></span>
								<span class="wcb-ai-summary" data-wp-class--wcb-hidden="!context.app.aiSummary" data-wp-text="context.app.aiSummary"></span>
								<span class="wcb-app-date" data-wp-text="context.app.submitted_at"></span>
							</div>
							<span class="wcb-ai-score" data-wp-class--wcb-hidden="!context.app.aiScoreLabel" data-wp-text="context.app.aiScoreLabel"></span>
							<span class="wcb-unread-dot" data-wp-class--wcb-shown="state.isUnread"></span>
						</div>
					</template>
				</div>

				<div class="wcb-applicant-detail">
					<div class="wcb-no-selection" data-wp-class--wcb-shown="state.noAppSelected">
						<p><?php esc_html_e( 'Select an applicant from the list.', 'wp-career-board' ); ?></p>
					</div>
					<div data-wp-class--wcb-hidden="state.noAppSelected">
						<div class="wcb-detail-header">
							<div class="wcb-detail-avatar" data-wp-text="state.selectedAppInitials" aria-hidden="true"></div>
							<div>
								<h3 class="wcb-detail-name" data-wp-text="state.selectedAppName"></h3>
								<p class="wcb-detail-email" data-wp-text="state.selectedAppEmail"></p>
								<p class="wcb-detail-date" data-wp-text="state.selectedAppDate"></p>
							</div>
							<select class="wcb-status-select" aria-label="<?php esc_attr_e( 'Change application status', 'wp-career-board' ); ?>" data-wp-bind--value="state.selectedAppStatus" data-wp-bind--data-wcb-app-id="state.selectedAppId" data-wp-on--change="actions.updateAppStatus" data-wp-bind--data-status="state.selectedAppStatus">
								<option value="submitted"><?php esc_html_e( 'Submitted', 'wp-career-board' ); ?></option>
								<option value="reviewing"><?php esc_html_e( 'Reviewing', 'wp-career-board' ); ?></option>
								<option value="shortlisted"><?php esc_html_e( 'Shortlisted', 'wp-career-board' ); ?></option>
								<option value="rejected"><?php esc_html_e( 'Rejected', 'wp-career-board' ); ?></option>
								<option value="hired"><?php esc_html_e( 'Hired', 'wp-career-board' ); ?></option>
							</select>
							<p class="wcb-status-msg" role="status" data-wp-bind--hidden="!state.statusMsg" data-wp-text="state.statusMsg"></p>
						</div>
						<div class="wcb-detail-section wcb-ai-fit" data-wp-class--wcb-shown="state.selectedAppHasAiScore">
							<h4 class="wcb-detail-section-label"><?php esc_html_e( 'AI fit', 'wp-career-board' ); ?> <span class="wcb-ai-score" data-wp-text="state.selectedAppAiScoreLabel"></span></h4>
							<p class="wcb-ai-summary-detail" data-wp-class--wcb-hidden="!state.selectedAppAiSummary" data-wp-text="state.selectedAppAiSummary"></p>
							<p class="wcb-ai-reason" data-wp-text="state.selectedAppAiReason"></p>
						</div>
						<div class="wcb-detail-section">
							<h4 class="wcb-detail-section-label"><?php esc_html_e( 'Cover Letter', 'wp-career-board' ); ?></h4>
							<div class="wcb-cover-letter" data-wp-text="state.selectedAppCoverLetter"></div>
						</div>
						<div class="wcb-detail-section" data-wp-class--wcb-shown="state.selectedAppHasResume">
							<h4 class="wcb-detail-section-label"><?php esc_html_e( 'Resume', 'wp-career-board' ); ?></h4>
							<div class="wcb-resume-actions">
								<a class="wcb-resume-chip" data-wp-bind--href="state.selectedAppResumePermalink" data-wp-class--wcb-hidden="!state.selectedAppResumePermalink" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Resume', 'wp-career-board' ); ?></a>
								<a class="wcb-resume-chip wcb-resume-chip--download" data-wp-bind--href="state.selectedAppResumeUrl" data-wp-class--wcb-hidden="!state.selectedAppResumeUrl" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Download Resume', 'wp-career-board' ); ?></a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- VIEW: Company Profile -->
		<div class="wcb-view-panel" id="wcb-panel-company" role="tabpanel" aria-labelledby="wcb-tab-company" data-wp-class--wcb-view-active="state.isViewCompany">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'Company Profile', 'wp-career-board' ); ?></h1>
			</div>

			<div class="wcb-profile-grid">
				<div class="wcb-profile-form">
					<div class="wcb-field-group" data-wp-class--wcb-shown="state.companyId">
						<label class="wcb-field-label"><?php esc_html_e( 'Company Logo', 'wp-career-board' ); ?></label>
						<div class="wcb-logo-field">
							<img class="wcb-logo-current" data-wp-class--wcb-shown="state.companyLogoUrl" data-wp-bind--src="state.companyLogoUrl" alt="" width="64" height="64" />
							<label class="wcb-logo-upload-label" for="wcb-company-logo">
								<span data-wp-text="state.logoUploadLabel"></span>
							</label>
							<input id="wcb-company-logo" type="file" class="wcb-logo-input" accept="image/jpeg,image/png,image/gif,image/webp" data-wp-on--change="actions.uploadLogo" />
						</div>
					</div>
					<p class="wcb-field-hint" data-wp-class--wcb-shown="state.noCompany"><?php esc_html_e( 'Save your company profile first to enable logo upload.', 'wp-career-board' ); ?></p>
					<div class="wcb-field-group">
						<label class="wcb-field-label" for="wcb-company-name"><?php esc_html_e( 'Company Name', 'wp-career-board' ); ?></label>
						<input id="wcb-company-name" type="text" class="wcb-field-input" data-wcb-field="companyName" data-wp-bind--value="state.companyName" data-wp-on--input="actions.updateField" />
					</div>
					<div class="wcb-field-group">
						<label class="wcb-field-label" for="wcb-company-tagline">
							<?php esc_html_e( 'Tagline', 'wp-career-board' ); ?>
							<span class="wcb-field-hint"><?php esc_html_e( 'One-line description shown on listings', 'wp-career-board' ); ?></span>
						</label>
						<input id="wcb-company-tagline" type="text" class="wcb-field-input" data-wcb-field="companyTagline" data-wp-bind--value="state.companyTagline" data-wp-on--input="actions.updateField" />
					</div>
					<div class="wcb-field-group">
						<label class="wcb-field-label" for="wcb-company-desc"><?php esc_html_e( 'About the Company', 'wp-career-board' ); ?></label>
						<div class="wcb-editor" data-placeholder="<?php esc_attr_e( 'What does your company do? Mission, products, culture…', 'wp-career-board' ); ?>">
							<div class="wcb-editor-holder" id="wcb-editor-company-desc"></div>
							<textarea
								id="wcb-company-desc"
								class="wcb-editor-source"
								rows="1"
								tabindex="-1"
								aria-label="<?php esc_attr_e( 'About the company', 'wp-career-board' ); ?>"
								data-wcb-field="companyDesc"
								data-wp-bind--value="state.companyDesc"
								data-wp-on--input="actions.updateField"
							></textarea>
						</div>
					</div>
					<div class="wcb-field-row">
						<div class="wcb-field-group">
							<label class="wcb-field-label" for="wcb-company-ind"><?php esc_html_e( 'Industry', 'wp-career-board' ); ?></label>
							<select id="wcb-company-ind" class="wcb-field-input wcb-field-select" data-wcb-field="companyIndustry" data-wp-bind--value="state.companyIndustry" data-wp-on--change="actions.updateField">
								<?php foreach ( \WCB\Core\Industries::all() as $wcb_ind_val => $wcb_ind_label ) : ?>
									<option value="<?php echo esc_attr( $wcb_ind_val ); ?>"<?php selected( (string) $wcb_company_ind, (string) $wcb_ind_val ); ?>>
									<?php echo esc_html( $wcb_ind_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="wcb-field-group">
							<label class="wcb-field-label" for="wcb-company-size"><?php esc_html_e( 'Company Size', 'wp-career-board' ); ?></label>
							<select id="wcb-company-size" class="wcb-field-input wcb-field-select" data-wcb-field="companySize" data-wp-on--change="actions.updateField">
								<option value=""><?php esc_html_e( ' -  Select size  - ', 'wp-career-board' ); ?></option>
								<?php
								$wcb_size_options = array(
									'1-10'      => __( '1-10 employees', 'wp-career-board' ),
									'11-50'     => __( '11-50 employees', 'wp-career-board' ),
									'51-200'    => __( '51-200 employees', 'wp-career-board' ),
									'201-500'   => __( '201-500 employees', 'wp-career-board' ),
									'501-1000'  => __( '501-1,000 employees', 'wp-career-board' ),
									'1001-5000' => __( '1,001-5,000 employees', 'wp-career-board' ),
									'5000+'     => __( '5,000+ employees', 'wp-career-board' ),
								);
								foreach ( $wcb_size_options as $wcb_val => $wcb_label ) {
									printf(
										'<option value="%s"%s>%s</option>',
										esc_attr( $wcb_val ),
										selected( $wcb_company_size, $wcb_val, false ),
										esc_html( $wcb_label )
									);
								}
								?>
							</select>
						</div>
					</div>
					<div class="wcb-field-row">
						<div class="wcb-field-group">
							<label class="wcb-field-label" for="wcb-company-hq"><?php esc_html_e( 'HQ Location', 'wp-career-board' ); ?></label>
							<input id="wcb-company-hq" type="text" class="wcb-field-input" placeholder="<?php esc_attr_e( 'e.g. San Francisco, CA', 'wp-career-board' ); ?>" data-wcb-field="companyHq" data-wp-bind--value="state.companyHq" data-wp-on--input="actions.updateField" />
						</div>
						<div class="wcb-field-group">
							<label class="wcb-field-label" for="wcb-company-site"><?php esc_html_e( 'Website', 'wp-career-board' ); ?></label>
							<input id="wcb-company-site" type="url" class="wcb-field-input" placeholder="https://" data-wcb-field="companySite" data-wp-bind--value="state.companySite" data-wp-on--input="actions.updateField" />
						</div>
					</div>
					<div class="wcb-field-row">
						<div class="wcb-field-group">
							<label class="wcb-field-label" for="wcb-company-type"><?php esc_html_e( 'Company Type', 'wp-career-board' ); ?></label>
							<select id="wcb-company-type" class="wcb-field-input wcb-field-select" data-wcb-field="companyType" data-wp-on--change="actions.updateField">
								<option value=""><?php esc_html_e( ' -  Select type  - ', 'wp-career-board' ); ?></option>
								<?php
								$wcb_type_options = array(
									'public'        => __( 'Public Company', 'wp-career-board' ),
									'private'       => __( 'Privately Held', 'wp-career-board' ),
									'self-employed' => __( 'Self-Employed / Freelance', 'wp-career-board' ),
									'nonprofit'     => __( 'Non-profit', 'wp-career-board' ),
									'government'    => __( 'Government Agency', 'wp-career-board' ),
									'educational'   => __( 'Educational Institution', 'wp-career-board' ),
									'partnership'   => __( 'Partnership', 'wp-career-board' ),
								);
								foreach ( $wcb_type_options as $wcb_val => $wcb_label ) {
									printf(
										'<option value="%s"%s>%s</option>',
										esc_attr( $wcb_val ),
										selected( $wcb_company_type, $wcb_val, false ),
										esc_html( $wcb_label )
									);
								}
								?>
							</select>
						</div>
						<div class="wcb-field-group">
							<label class="wcb-field-label" for="wcb-company-founded"><?php esc_html_e( 'Founded Year', 'wp-career-board' ); ?></label>
							<input id="wcb-company-founded" type="number" class="wcb-field-input" min="1800" max="<?php echo esc_attr( (string) gmdate( 'Y' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. 2012', 'wp-career-board' ); ?>" data-wcb-field="companyFounded" data-wp-bind--value="state.companyFounded" data-wp-on--input="actions.updateField" />
						</div>
					</div>
					<div class="wcb-field-row">
						<div class="wcb-field-group">
							<label class="wcb-field-label" for="wcb-company-linkedin"><?php esc_html_e( 'LinkedIn', 'wp-career-board' ); ?></label>
							<input id="wcb-company-linkedin" type="url" class="wcb-field-input" placeholder="https://linkedin.com/company/…" data-wcb-field="companyLinkedin" data-wp-bind--value="state.companyLinkedin" data-wp-on--input="actions.updateField" />
						</div>
						<div class="wcb-field-group">
							<label class="wcb-field-label" for="wcb-company-twitter"><?php esc_html_e( 'X (Twitter)', 'wp-career-board' ); ?></label>
							<input id="wcb-company-twitter" type="url" class="wcb-field-input" placeholder="https://x.com/…" data-wcb-field="companyTwitter" data-wp-bind--value="state.companyTwitter" data-wp-on--input="actions.updateField" />
						</div>
					</div>

					<?php
					// Custom-field groups from wcb_company_form_fields (Pro Field
					// Builder + add-ons). Rendered above the save button so they
					// participate in the same profile-save flow.
					$wcb_ed_company_custom = (array) apply_filters( 'wcb_company_form_fields', array(), $wcb_company_id );
					if ( ! empty( $wcb_ed_company_custom ) ) {
						\WCB\Core\FormCustomFields::render_groups( $wcb_ed_company_custom, 'updateCustomField', 'wcb-company-custom', (int) $wcb_company_id );
					}
					?>

					<div class="wcb-profile-actions">
						<p class="wcb-db-save-success wcb-icon-label" data-wp-class--wcb-shown="state.saved"><?php echo \WCB\Core\Icon::svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?><?php esc_html_e( 'Profile saved successfully.', 'wp-career-board' ); ?></p>
						<p class="wcb-db-error" role="alert" data-wp-class--wcb-shown="state.error" data-wp-text="state.error"></p>
						<button type="button" class="wcb-db-btn wcb-db-btn--primary" data-wp-on--click="actions.saveProfile" data-wp-bind--disabled="state.saving">
							<span data-wp-class--wcb-hidden="state.saving"><?php esc_html_e( 'Save Profile', 'wp-career-board' ); ?></span>
							<span class="wcb-saving-label" data-wp-class--wcb-shown="state.saving"><?php esc_html_e( 'Saving…', 'wp-career-board' ); ?></span>
						</button>
					</div>
				</div>

				<div class="wcb-preview-card">
					<h3 class="wcb-preview-title"><?php esc_html_e( 'Live Preview', 'wp-career-board' ); ?></h3>
					<div class="wcb-preview-body">
						<img class="wcb-preview-logo-img" data-wp-class--wcb-shown="state.companyLogoUrl" data-wp-bind--src="state.companyLogoUrl" alt="" />
						<p class="wcb-preview-name" data-wp-text="state.companyName"></p>
						<p class="wcb-preview-tagline" data-wp-text="state.companyTagline"></p>
						<p class="wcb-preview-desc" data-wp-text="state.companyDescExcerpt"></p>
						<div class="wcb-preview-chips">
							<span class="wcb-preview-chip" data-wp-class--wcb-hidden="!state.companyIndustry" data-wp-text="state.companyIndustryLabel"></span>
							<span class="wcb-preview-chip" data-wp-class--wcb-hidden="!state.companySize" data-wp-text="state.companySize"></span>
							<span class="wcb-preview-chip" data-wp-class--wcb-hidden="!state.companyHq" data-wp-text="state.companyHq"></span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- VIEW: Post a Job -->
		<div class="wcb-view-panel" id="wcb-panel-postjob" role="tabpanel" aria-labelledby="wcb-tab-postjob" data-wp-class--wcb-view-active="state.isViewPostJob">
			<?php
			if ( is_user_logged_in() ) {
				echo do_blocks( '<!-- wp:wp-career-board/job-form /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
		</div>

	<!-- ── Saved Jobs panel ────────────────────────────────────────── -->
	<div class="wcb-view-panel" id="wcb-panel-saved-jobs" role="tabpanel" aria-labelledby="wcb-tab-saved-jobs" data-wp-class--wcb-view-active="state.isViewSavedJobs">
		<div class="wcb-page-header">
			<h1 class="wcb-page-title"><?php esc_html_e( 'Saved Jobs', 'wp-career-board' ); ?></h1>
		</div>
		<div class="wcb-cd-loading" role="status" data-wp-class--wcb-shown="state.savedJobsLoading">
			<span class="wcb-cd-spinner" aria-hidden="true"></span>
			<?php esc_html_e( 'Loading…', 'wp-career-board' ); ?>
		</div>
		<p class="wcb-cd-error" role="alert" data-wp-bind--hidden="!state.savedJobsError" data-wp-text="state.savedJobsError"></p>

		<div class="wcb-panel" aria-live="polite" data-wp-class--wcb-shown="state.hasSavedJobs">
			<template data-wp-each--job="state.savedJobs" data-wp-each-key="context.job.id">
				<div class="wcb-cd-bookmark-row">
					<div class="wcb-cd-bookmark-main">
						<h3 class="wcb-cd-bookmark-title">
							<a aria-label="<?php esc_attr_e( 'Bookmarked job', 'wp-career-board' ); ?>" data-wp-bind--aria-label="context.job.title" data-wp-bind--href="context.job.permalink" data-wp-text="context.job.title" target="_blank" rel="noopener noreferrer"></a>
						</h3>
						<div class="wcb-cd-bookmark-meta">
							<span data-wp-text="context.job.company"></span>
							<span class="wcb-cd-bookmark-meta-sep" data-wp-class--wcb-hidden="!context.job.location" aria-hidden="true">·</span>
							<span data-wp-class--wcb-hidden="!context.job.location" data-wp-text="context.job.location"></span>
							<span class="wcb-cd-bookmark-meta-sep" data-wp-class--wcb-hidden="!context.job.type" aria-hidden="true">·</span>
							<span data-wp-class--wcb-hidden="!context.job.type" data-wp-text="context.job.type"></span>
						</div>
					</div>
					<div class="wcb-cd-bookmark-actions">
						<a class="wcb-cbtn wcb-cbtn--ghost wcb-cbtn--sm" data-wp-bind--href="context.job.permalink" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Job', 'wp-career-board' ); ?></a>
						<button type="button" class="wcb-cbtn wcb-cbtn--danger wcb-cbtn--sm" data-wp-on--click="actions.unbookmarkJob"><?php esc_html_e( 'Remove', 'wp-career-board' ); ?></button>
					</div>
				</div>
			</template>
		</div>

		<div class="wcb-cd-empty" data-wp-class--wcb-shown="state.noSavedJobs">
			<p class="wcb-cd-empty-msg"><?php esc_html_e( 'No saved jobs yet. Bookmark a job to find it here.', 'wp-career-board' ); ?></p>
			<a href="<?php echo esc_url( home_url( '/find-jobs/' ) ); ?>" class="wcb-cbtn wcb-cbtn--primary"><?php esc_html_e( 'Browse Jobs', 'wp-career-board' ); ?></a>
		</div>
	</div>

	<!-- ── Saved Companies panel ──────────────────────────────────── -->
	<div class="wcb-view-panel" id="wcb-panel-saved-companies" role="tabpanel" aria-labelledby="wcb-tab-saved-companies" data-wp-class--wcb-view-active="state.isViewSavedCompanies">
		<div class="wcb-page-header">
			<h1 class="wcb-page-title"><?php esc_html_e( 'Saved Companies', 'wp-career-board' ); ?></h1>
		</div>
		<div class="wcb-cd-loading" role="status" data-wp-class--wcb-shown="state.savedCompaniesLoading">
			<span class="wcb-cd-spinner" aria-hidden="true"></span>
			<?php esc_html_e( 'Loading…', 'wp-career-board' ); ?>
		</div>
		<p class="wcb-cd-error" role="alert" data-wp-bind--hidden="!state.savedCompaniesError" data-wp-text="state.savedCompaniesError"></p>

		<div class="wcb-panel" aria-live="polite" data-wp-class--wcb-shown="state.hasSavedCompanies">
			<template data-wp-each--company="state.savedCompanies" data-wp-each-key="context.company.id">
				<div class="wcb-cd-bookmark-row">
					<div class="wcb-cd-bookmark-main">
						<h3 class="wcb-cd-bookmark-title">
							<a aria-label="<?php esc_attr_e( 'Bookmarked company', 'wp-career-board' ); ?>" data-wp-bind--aria-label="context.company.title" data-wp-bind--href="context.company.permalink" data-wp-text="context.company.title" target="_blank" rel="noopener noreferrer"></a>
						</h3>
						<div class="wcb-cd-bookmark-meta">
							<span data-wp-class--wcb-hidden="!context.company.industry" data-wp-text="context.company.industry"></span>
							<span class="wcb-cd-bookmark-meta-sep" data-wp-class--wcb-hidden="!context.company.hq" aria-hidden="true">·</span>
							<span data-wp-class--wcb-hidden="!context.company.hq" data-wp-text="context.company.hq"></span>
						</div>
					</div>
					<div class="wcb-cd-bookmark-actions">
						<a class="wcb-cbtn wcb-cbtn--ghost wcb-cbtn--sm" data-wp-bind--href="context.company.permalink" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Profile', 'wp-career-board' ); ?></a>
						<button type="button" class="wcb-cbtn wcb-cbtn--danger wcb-cbtn--sm" data-wp-on--click="actions.unbookmarkCompany"><?php esc_html_e( 'Remove', 'wp-career-board' ); ?></button>
					</div>
				</div>
			</template>
		</div>

		<div class="wcb-cd-empty" data-wp-class--wcb-shown="state.noSavedCompanies">
			<p class="wcb-cd-empty-msg"><?php esc_html_e( 'No saved companies yet. Bookmark a company to find it here.', 'wp-career-board' ); ?></p>
			<a href="<?php echo esc_url( home_url( '/companies/' ) ); ?>" class="wcb-cbtn wcb-cbtn--primary"><?php esc_html_e( 'Browse Companies', 'wp-career-board' ); ?></a>
		</div>
	</div>

	<?php if ( post_type_exists( 'wcb_resume' ) ) : ?>
	<!-- ── Saved Resumes panel ────────────────────────────────────── -->
	<div class="wcb-view-panel" id="wcb-panel-saved-resumes" role="tabpanel" aria-labelledby="wcb-tab-saved-resumes" data-wp-class--wcb-view-active="state.isViewSavedResumes">
		<div class="wcb-page-header">
			<h1 class="wcb-page-title"><?php esc_html_e( 'Saved Resumes', 'wp-career-board' ); ?></h1>
		</div>
		<div class="wcb-cd-loading" role="status" data-wp-class--wcb-shown="state.savedResumesLoading">
			<span class="wcb-cd-spinner" aria-hidden="true"></span>
			<?php esc_html_e( 'Loading…', 'wp-career-board' ); ?>
		</div>
		<p class="wcb-cd-error" role="alert" data-wp-bind--hidden="!state.savedResumesError" data-wp-text="state.savedResumesError"></p>

		<div class="wcb-panel" aria-live="polite" data-wp-class--wcb-shown="state.hasSavedResumes">
			<template data-wp-each--resume="state.savedResumes" data-wp-each-key="context.resume.id">
				<div class="wcb-cd-bookmark-row">
					<div class="wcb-cd-bookmark-main">
						<h3 class="wcb-cd-bookmark-title">
							<a aria-label="<?php esc_attr_e( 'Bookmarked resume', 'wp-career-board' ); ?>" data-wp-bind--aria-label="context.resume.title" data-wp-bind--href="context.resume.permalink" data-wp-text="context.resume.title" target="_blank" rel="noopener noreferrer"></a>
						</h3>
						<div class="wcb-cd-bookmark-meta">
							<span data-wp-class--wcb-hidden="!context.resume.role" data-wp-text="context.resume.role"></span>
							<span class="wcb-cd-bookmark-meta-sep" data-wp-class--wcb-hidden="!context.resume.location" aria-hidden="true">·</span>
							<span data-wp-class--wcb-hidden="!context.resume.location" data-wp-text="context.resume.location"></span>
						</div>
					</div>
					<div class="wcb-cd-bookmark-actions">
						<a class="wcb-cbtn wcb-cbtn--ghost wcb-cbtn--sm" data-wp-bind--href="context.resume.permalink" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Resume', 'wp-career-board' ); ?></a>
						<button type="button" class="wcb-cbtn wcb-cbtn--danger wcb-cbtn--sm" data-wp-on--click="actions.unbookmarkResume"><?php esc_html_e( 'Remove', 'wp-career-board' ); ?></button>
					</div>
				</div>
			</template>
		</div>

		<div class="wcb-cd-empty" data-wp-class--wcb-shown="state.noSavedResumes">
			<p class="wcb-cd-empty-msg"><?php esc_html_e( 'No saved resumes yet. Bookmark a candidate to find it here.', 'wp-career-board' ); ?></p>
			<a href="<?php echo esc_url( home_url( '/find-candidates/' ) ); ?>" class="wcb-cbtn wcb-cbtn--primary"><?php esc_html_e( 'Browse Candidates', 'wp-career-board' ); ?></a>
		</div>
	</div>
	<?php endif; ?>

	<!-- ── Settings panel ─────────────────────────────────────────── -->
	<div class="wcb-view-panel" id="wcb-panel-settings" role="tabpanel" aria-labelledby="wcb-tab-settings" data-wp-class--wcb-view-active="state.isViewSettings">
		<div class="wcb-page-header">
			<h1 class="wcb-page-title"><?php esc_html_e( 'Account Settings', 'wp-career-board' ); ?></h1>
		</div>
		<div class="wcb-panel wcb-panel--form wcb-shown">
			<p class="wcb-account-msg" role="status" data-wp-bind--hidden="!state.accountMsg" data-wp-bind--data-type="state.accountMsgType" data-wp-text="state.accountMsg"></p>
			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-emp-account-name"><?php esc_html_e( 'Display Name', 'wp-career-board' ); ?></label>
				<input type="text" id="wcb-emp-account-name" class="wcb-input" autocomplete="name" data-wp-bind--value="state.accountName" data-wp-on--input="actions.updateField" data-wcb-field="accountName" />
			</div>
			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-emp-account-email"><?php esc_html_e( 'Email', 'wp-career-board' ); ?></label>
				<input type="email" id="wcb-emp-account-email" class="wcb-input" autocomplete="email" data-wp-bind--value="state.accountEmail" data-wp-on--input="actions.updateField" data-wcb-field="accountEmail" />
			</div>
			<div class="wcb-form-field">
				<button type="button" class="wcb-cbtn wcb-cbtn--primary" data-wp-on--click="actions.saveAccount" data-wp-bind--disabled="state.accountSaving"><?php esc_html_e( 'Save changes', 'wp-career-board' ); ?></button>
			</div>
		</div>

		<div class="wcb-page-header" style="margin-top: var(--wcb-space-xl);">
			<h2 class="wcb-page-title"><?php esc_html_e( 'Change Password', 'wp-career-board' ); ?></h2>
		</div>
		<div class="wcb-panel wcb-panel--form wcb-shown">
			<p class="wcb-account-msg" role="status" data-wp-bind--hidden="!state.pwMsg" data-wp-bind--data-type="state.pwMsgType" data-wp-text="state.pwMsg"></p>
			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-emp-account-curpw"><?php esc_html_e( 'Current Password', 'wp-career-board' ); ?></label>
				<input type="password" id="wcb-emp-account-curpw" class="wcb-input" autocomplete="current-password" data-wp-bind--value="state.curPassword" data-wp-on--input="actions.updateField" data-wcb-field="curPassword" />
			</div>
			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-emp-account-newpw"><?php esc_html_e( 'New Password', 'wp-career-board' ); ?></label>
				<input type="password" id="wcb-emp-account-newpw" class="wcb-input" autocomplete="new-password" data-wp-bind--value="state.newPassword" data-wp-on--input="actions.updateField" data-wcb-field="newPassword" />
			</div>
			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-emp-account-confpw"><?php esc_html_e( 'Confirm New Password', 'wp-career-board' ); ?></label>
				<input type="password" id="wcb-emp-account-confpw" class="wcb-input" autocomplete="new-password" data-wp-bind--value="state.confPassword" data-wp-on--input="actions.updateField" data-wcb-field="confPassword" />
			</div>
			<div class="wcb-form-field">
				<button type="button" class="wcb-cbtn wcb-cbtn--primary" data-wp-on--click="actions.changePassword" data-wp-bind--disabled="state.pwSaving"><?php esc_html_e( 'Update password', 'wp-career-board' ); ?></button>
			</div>
		</div>
	</div>

	<?php if ( $wcb_bell_enabled ) : ?>
	<!-- VIEW: Notifications (Pro) -->
	<div class="wcb-view-panel" id="wcb-panel-notifications" role="tabpanel" aria-labelledby="wcb-tab-notifications" data-wp-class--wcb-view-active="state.isViewNotifications">
		<div class="wcb-page-header">
			<h1 class="wcb-page-title"><?php esc_html_e( 'Notifications', 'wp-career-board' ); ?></h1>
		</div>
		<?php
		// Pro's notifications-list markup (trusted Interactivity HTML; see note at top).
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted plugin Interactivity markup.
		echo $wcb_module_renders['notifications_bell'];
		?>
	</div>
	<?php endif; ?>

	</main>
</div>

</div>
