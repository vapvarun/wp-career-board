<?php
/**
 * Block render: wcb/employer-dashboard — sidebar layout employer interface.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$wcb_can_manage = function_exists( 'wp_is_ability_granted' )
	? wp_is_ability_granted( 'wcb_manage_company' )
	: current_user_can( 'wcb_manage_company' );

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
	echo '<p>' . esc_html__( 'You do not have permission to view this dashboard.', 'wp-career-board' ) . '</p>';
	return;
}

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
$wcb_company_logo    = $wcb_company_id ? (string) get_the_post_thumbnail_url( $wcb_company_id, 'medium' ) : '';

$wcb_settings        = (array) get_option( 'wcb_settings', array() );
$wcb_post_job_url    = ! empty( $wcb_settings['post_job_page'] )
	? (string) get_permalink( (int) $wcb_settings['post_job_page'] )
	: '#';
$wcb_company_dir_url = ! empty( $wcb_settings['company_archive_page'] )
	? (string) get_permalink( (int) $wcb_settings['company_archive_page'] )
	: '#';
$wcb_company_url     = $wcb_company_id ? (string) get_permalink( $wcb_company_id ) : $wcb_company_dir_url;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only param, no state mutation.
$wcb_apps_job_id   = absint( wp_unslash( $_GET['job_apps'] ?? '0' ) );
$wcb_dashboard_url = (string) get_permalink();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only param, no state mutation.
$wcb_edit_job_id = absint( wp_unslash( $_GET['edit'] ?? '0' ) );

// Pre-compute lightweight stats for instant render (avoids zero-flash before JS hydrates).
$wcb_job_counts = (array) wp_count_posts( 'wcb_job' );
if ( $wcb_company_id ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lightweight count for initial render only.
	$wcb_total_employer_jobs = (int) $GLOBALS['wpdb']->get_var(
		$GLOBALS['wpdb']->prepare(
			"SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts} p
			 INNER JOIN {$GLOBALS['wpdb']->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wcb_company_id'
			 WHERE p.post_type = 'wcb_job' AND p.post_status IN ('publish','pending','draft') AND pm.meta_value = %s",
			(string) $wcb_company_id
		)
	);
	$wcb_live_employer_jobs = (int) $GLOBALS['wpdb']->get_var(
		$GLOBALS['wpdb']->prepare(
			"SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts} p
			 INNER JOIN {$GLOBALS['wpdb']->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wcb_company_id'
			 WHERE p.post_type = 'wcb_job' AND p.post_status = 'publish' AND pm.meta_value = %s",
			(string) $wcb_company_id
		)
	);
	$wcb_total_apps = (int) $GLOBALS['wpdb']->get_var(
		$GLOBALS['wpdb']->prepare(
			"SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts} a
			 INNER JOIN {$GLOBALS['wpdb']->postmeta} aj ON a.ID = aj.post_id AND aj.meta_key = '_wcb_job_id'
			 INNER JOIN {$GLOBALS['wpdb']->postmeta} jc ON aj.meta_value = jc.post_id AND jc.meta_key = '_wcb_company_id'
			 WHERE a.post_type = 'wcb_application' AND a.post_status = 'publish' AND jc.meta_value = %s",
			(string) $wcb_company_id
		)
	);
	$wcb_week_ago   = gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS );
	$wcb_new_apps   = (int) $GLOBALS['wpdb']->get_var(
		$GLOBALS['wpdb']->prepare(
			"SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts} a
			 INNER JOIN {$GLOBALS['wpdb']->postmeta} aj ON a.ID = aj.post_id AND aj.meta_key = '_wcb_job_id'
			 INNER JOIN {$GLOBALS['wpdb']->postmeta} jc ON aj.meta_value = jc.post_id AND jc.meta_key = '_wcb_company_id'
			 WHERE a.post_type = 'wcb_application' AND a.post_status = 'publish' AND jc.meta_value = %s AND a.post_date >= %s",
			(string) $wcb_company_id,
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
		'currentView'       => $wcb_apps_job_id > 0 ? 'applications' : ( $wcb_edit_job_id > 0 ? 'post-job' : 'overview' ),
		'jobFilter'         => 'all',
		'jobSearch'         => '',
		'appsFilter'        => 'all',
		'selectedAppId'     => null,
		'allApplications'   => array(),
		'jobs'              => array(),
		'ssrTotalJobs'      => $wcb_total_employer_jobs,
		'ssrPublishedJobs'  => $wcb_live_employer_jobs,
		'ssrTotalApps'      => $wcb_total_apps,
		'ssrNewThisWeek'    => $wcb_new_apps,
		'loading'           => true,
		'error'             => '',
		'noCompany'         => false,
		'apiBase'           => rest_url( 'wcb/v1' ),
		'nonce'             => wp_create_nonce( 'wp_rest' ),
		'companyId'         => $wcb_company_id,
		'companyName'       => $wcb_company_name,
		'companyDesc'       => $wcb_company_desc,
		'companyTagline'    => $wcb_company_tagline,
		'companySite'       => $wcb_company_site,
		'companyIndustry'   => $wcb_company_ind,
		'companySize'       => $wcb_company_size,
		'companyHq'         => $wcb_company_hq,
		'companyLogoUrl'    => $wcb_company_logo,
		'logoUploading'     => false,
		'saving'            => false,
		'saved'             => false,
		'companyDirUrl'     => $wcb_company_dir_url,
		'dashboardUrl'      => $wcb_dashboard_url,
		'customFieldGroups' => apply_filters( 'wcb_company_form_fields', array(), $wcb_company_id ),
		'customFields'      => (object) array(),
		'appsJobId'         => $wcb_apps_job_id,
		'appsJobTitle'      => '',
		'appsJobSearch'     => '',
		'applications'      => array(),
		'appsLoading'       => false,
		'appsError'         => '',
		'bellNotifications' => array(),
		'bellUnreadCount'   => 0,
		'bellOpen'          => false,
		'bellLoading'       => false,
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
			data-wp-on--click="actions.toggleNav"
			data-wp-bind--aria-expanded="state.navOpen">
			<span data-wp-text="state.activeTabLabel"><?php esc_html_e( 'Dashboard', 'wp-career-board' ); ?></span>
			<span class="wcb-nav-toggle-icon" aria-hidden="true"></span>
		</button>
		<button type="button" class="wcb-sidebar-logo"
			data-wp-on--click="actions.switchToOverview"
			data-wp-class--wcb-nav-active="state.isViewOverview">
			<?php esc_html_e( 'Dashboard', 'wp-career-board' ); ?>
		</button>

		<nav class="wcb-sidebar-nav" aria-label="<?php esc_attr_e( 'Dashboard navigation', 'wp-career-board' ); ?>">
			<span class="wcb-nav-section-label"><?php esc_html_e( 'JOBS', 'wp-career-board' ); ?></span>
			<button type="button" class="wcb-nav-item" data-wp-class--wcb-nav-active="state.isViewJobs" data-wp-on--click="actions.switchToJobs">
				<?php esc_html_e( 'My Jobs', 'wp-career-board' ); ?>
				<span class="wcb-nav-badge" data-wp-text="state.totalJobs">0</span>
			</button>
			<button type="button" class="wcb-nav-item" data-wp-class--wcb-nav-active="state.isViewPostJob" data-wp-on--click="actions.switchToPostJob">
				<?php esc_html_e( 'Post a Job', 'wp-career-board' ); ?>
			</button>

			<span class="wcb-nav-section-label"><?php esc_html_e( 'HIRING', 'wp-career-board' ); ?></span>
			<button type="button" class="wcb-nav-item" data-wp-class--wcb-nav-active="state.isViewApplications" data-wp-on--click="actions.switchToApplications">
				<?php esc_html_e( 'Applications', 'wp-career-board' ); ?>
				<span class="wcb-nav-badge" data-wp-text="state.totalApps">0</span>
			</button>

			<span class="wcb-nav-section-label"><?php esc_html_e( 'COMPANY', 'wp-career-board' ); ?></span>
			<button type="button" class="wcb-nav-item" data-wp-class--wcb-nav-active="state.isViewCompany" data-wp-on--click="actions.switchToCompany">
				<?php esc_html_e( 'Profile', 'wp-career-board' ); ?>
			</button>
			<a class="wcb-nav-item wcb-nav-item--link" href="<?php echo esc_url( $wcb_company_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Public Page', 'wp-career-board' ); ?> &#8599;
			</a>
		</nav>

		<button type="button" class="wcb-sidebar-cta" data-wp-on--click="actions.switchToPostJob">
			+ <?php esc_html_e( 'Post a Job', 'wp-career-board' ); ?>
		</button>

		<div class="wcb-sidebar-user">
			<div class="wcb-sidebar-avatar" data-wp-text="state.companyInitials" aria-hidden="true"></div>
			<span class="wcb-sidebar-company" data-wp-text="state.companyName"></span>
		</div>
	</aside>

	<!-- MAIN CONTENT -->
	<main class="wcb-main">

		<?php if ( class_exists( 'WCB\Pro\Modules\NotificationsBell\NotificationsBellModule' ) ) : ?>
		<div class="wcb-bell-wrapper" data-wp-class--wcb-bell-open="state.bellOpen">
			<button type="button" class="wcb-bell-btn"
				data-wp-on--click="actions.toggleBell"
				aria-label="<?php esc_attr_e( 'Notifications', 'wp-career-board' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
					<path d="M13.73 21a2 2 0 0 1-3.46 0"/>
				</svg>
				<span class="wcb-bell-badge" data-wp-class--wcb-hidden="!state.bellUnreadCount" data-wp-text="state.bellUnreadCount"></span>
			</button>
			<div class="wcb-bell-dropdown" data-wp-class--wcb-hidden="!state.bellOpen">
				<div class="wcb-bell-header">
					<span><?php esc_html_e( 'Notifications', 'wp-career-board' ); ?></span>
					<button type="button" class="wcb-bell-read-all" data-wp-on--click="actions.markAllRead" data-wp-class--wcb-hidden="!state.bellUnreadCount">
						<?php esc_html_e( 'Mark all read', 'wp-career-board' ); ?>
					</button>
				</div>
				<div class="wcb-bell-list">
					<template data-wp-each--notif="state.bellNotifications" data-wp-each-key="context.notif.id">
						<a class="wcb-bell-item"
							data-wp-bind--href="context.notif.link"
							data-wp-class--wcb-bell-unread="!context.notif.is_read"
							data-wp-on--click="actions.markBellRead">
							<span class="wcb-bell-msg" data-wp-text="context.notif.message"></span>
							<span class="wcb-bell-time" data-wp-text="context.notif.created_at"></span>
						</a>
					</template>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- VIEW: Overview -->
		<div class="wcb-view-panel" data-wp-class--wcb-view-active="state.isViewOverview">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'Overview', 'wp-career-board' ); ?></h1>
			</div>

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
					<span class="wcb-stat-label"><?php esc_html_e( 'Total Applicants', 'wp-career-board' ); ?></span>
				</div>
				<div class="wcb-stat-card wcb-stat-card--amber">
					<span class="wcb-stat-value" data-wp-text="state.newThisWeek">0</span>
					<span class="wcb-stat-label"><?php esc_html_e( 'New This Week', 'wp-career-board' ); ?></span>
				</div>
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
								<span class="wcb-status-badge" data-wp-text="context.app.status" data-wp-bind--data-status="context.app.status"></span>
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
		<div class="wcb-view-panel" data-wp-class--wcb-view-active="state.isViewJobs">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'My Jobs', 'wp-career-board' ); ?></h1>
				<input type="search" class="wcb-job-search" placeholder="<?php esc_attr_e( 'Search jobs…', 'wp-career-board' ); ?>" data-wp-on--input="actions.setJobSearch" />
			</div>

			<div class="wcb-filter-bar">
				<button type="button" class="wcb-filter-pill" data-wcb-filter="all" data-wp-class--wcb-filter-active="state.isFilterAll" data-wp-on--click="actions.setJobFilter"><?php esc_html_e( 'All', 'wp-career-board' ); ?></button>
				<button type="button" class="wcb-filter-pill" data-wcb-filter="live" data-wp-class--wcb-filter-active="state.isFilterLive" data-wp-on--click="actions.setJobFilter"><?php esc_html_e( 'Live', 'wp-career-board' ); ?></button>
				<button type="button" class="wcb-filter-pill" data-wcb-filter="draft" data-wp-class--wcb-filter-active="state.isFilterDraft" data-wp-on--click="actions.setJobFilter"><?php esc_html_e( 'Draft', 'wp-career-board' ); ?></button>
				<button type="button" class="wcb-filter-pill" data-wcb-filter="pending" data-wp-class--wcb-filter-active="state.isFilterPending" data-wp-on--click="actions.setJobFilter"><?php esc_html_e( 'Pending', 'wp-career-board' ); ?></button>
				<button type="button" class="wcb-filter-pill" data-wcb-filter="closed" data-wp-class--wcb-filter-active="state.isFilterClosed" data-wp-on--click="actions.setJobFilter"><?php esc_html_e( 'Closed', 'wp-career-board' ); ?></button>
			</div>

			<div class="wcb-db-loading" data-wp-class--wcb-shown="state.loading">
				<div class="wcb-skeleton-row"></div>
				<div class="wcb-skeleton-row"></div>
				<div class="wcb-skeleton-row"></div>
			</div>

			<div class="wcb-db-empty" data-wp-class--wcb-shown="state.noCompany">
				<p class="wcb-db-empty-msg"><?php esc_html_e( 'Set up your company profile first before posting jobs.', 'wp-career-board' ); ?></p>
				<button type="button" class="wcb-db-btn wcb-db-btn--secondary" data-wp-on--click="actions.switchToCompany"><?php esc_html_e( 'Set Up Company Profile', 'wp-career-board' ); ?></button>
			</div>

			<p class="wcb-db-error" data-wp-class--wcb-shown="state.error" data-wp-text="state.error"></p>

			<div class="wcb-db-empty" data-wp-class--wcb-shown="state.noJobs">
				<p class="wcb-db-empty-msg"><?php esc_html_e( 'No jobs posted yet.', 'wp-career-board' ); ?></p>
				<a href="<?php echo esc_url( $wcb_post_job_url ); ?>" class="wcb-db-btn wcb-db-btn--secondary"><?php esc_html_e( 'Post Your First Job', 'wp-career-board' ); ?></a>
			</div>

			<div class="wcb-jobs-list" data-wp-class--wcb-shown="state.hasJobs">
				<template data-wp-each--job="state.filteredJobs" data-wp-each-key="context.job.id">
					<article class="wcb-job-row" data-wp-class--wcb-job-closed="context.job.isClosed">
						<div class="wcb-status-dot" data-wp-bind--data-status="context.job.status"></div>
						<div class="wcb-job-info">
							<span class="wcb-job-title" data-wp-text="context.job.title"></span>
							<span class="wcb-job-meta" data-wp-text="context.job.location"></span>
						</div>
						<span class="wcb-status-badge" data-wp-text="context.job.statusLabel" data-wp-bind--data-status="context.job.status"></span>
						<button type="button" class="wcb-apps-chip" data-wp-class--wcb-hidden="!context.job.appCount" data-wp-text="context.job.appLabel" data-wp-bind--data-wcb-job-id="context.job.id" data-wp-on--click="actions.switchAppsJob"></button>
						<span class="wcb-apps-chip wcb-apps-chip--empty" data-wp-class--wcb-hidden="context.job.appCount" data-wp-text="context.job.appLabel"></span>
						<div class="wcb-job-actions">
							<a class="wcb-db-link-btn" data-wp-bind--href="context.job.permalink" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View ↗', 'wp-career-board' ); ?></a>
							<a class="wcb-db-link-btn wcb-db-link-btn--edit" data-wp-bind--href="context.job.editUrl"><?php esc_html_e( 'Edit', 'wp-career-board' ); ?></a>
							<button type="button" class="wcb-db-link-btn wcb-db-link-btn--close" data-wp-class--wcb-hidden="context.job.isClosed" data-wp-bind--data-wcb-job-id="context.job.id" data-wp-on--click="actions.closeJob"><?php esc_html_e( 'Close', 'wp-career-board' ); ?></button>
							<button type="button" class="wcb-db-link-btn wcb-db-link-btn--publish" data-wp-class--wcb-hidden="!context.job.isDraft" data-wp-bind--data-wcb-job-id="context.job.id" data-wp-on--click="actions.reopenJob"><?php esc_html_e( 'Publish', 'wp-career-board' ); ?></button>
							<button type="button" class="wcb-db-link-btn wcb-db-link-btn--reopen" data-wp-class--wcb-hidden="!context.job.isClosed" data-wp-bind--data-wcb-job-id="context.job.id" data-wp-on--click="actions.reopenJob"><?php esc_html_e( 'Reopen', 'wp-career-board' ); ?></button>
						</div>
					</article>
				</template>
			</div>
		</div>

		<!-- VIEW: Applications -->
		<div class="wcb-view-panel" data-wp-class--wcb-view-active="state.isViewApplications">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'Applications', 'wp-career-board' ); ?></h1>
			</div>

			<div class="wcb-apps-selector" data-wp-class--wcb-shown="state.hasJobsWithApps">
				<div class="wcb-apps-selector-header">
					<input type="search" class="wcb-apps-job-search" placeholder="<?php esc_attr_e( 'Search jobs...', 'wp-career-board' ); ?>" data-wp-on--input="actions.setAppsJobSearch" data-wp-on--search="actions.setAppsJobSearch" />
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

			<p class="wcb-db-error" data-wp-class--wcb-shown="state.appsError" data-wp-text="state.appsError"></p>

			<div class="wcb-db-loading" data-wp-class--wcb-shown="state.appsLoading">
				<div class="wcb-skeleton-row"></div>
				<div class="wcb-skeleton-row"></div>
			</div>

			<div class="wcb-db-empty" data-wp-class--wcb-shown="state.noApplications">
				<p class="wcb-db-empty-msg"><?php esc_html_e( 'No applications yet for this job.', 'wp-career-board' ); ?></p>
			</div>

			<div class="wcb-split-panel" data-wp-class--wcb-shown="state.hasApplications">
				<div class="wcb-applicant-list">
					<template data-wp-each--app="state.filteredApps" data-wp-each-key="context.app.id">
						<div class="wcb-applicant-row" role="button" tabindex="0" data-wp-class--wcb-selected="state.isSelectedApp" data-wp-bind--data-wcb-app-id="context.app.id" data-wp-bind--aria-label="state.applicantRowLabel" data-wp-on--click="actions.selectApplicant" data-wp-on--keydown="actions.handleRowKeydown">
							<div class="wcb-app-avatar" data-wp-text="context.app.initials" aria-hidden="true"></div>
							<div class="wcb-app-info">
								<span class="wcb-app-name" data-wp-text="context.app.applicant_name"></span>
								<span class="wcb-app-date" data-wp-text="context.app.submitted_at"></span>
							</div>
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
						</div>
						<div class="wcb-detail-section">
							<h4 class="wcb-detail-section-label"><?php esc_html_e( 'Cover Letter', 'wp-career-board' ); ?></h4>
							<div class="wcb-cover-letter" data-wp-text="state.selectedAppCoverLetter"></div>
						</div>
						<div class="wcb-detail-section" data-wp-class--wcb-shown="state.selectedAppResumeUrl">
							<h4 class="wcb-detail-section-label"><?php esc_html_e( 'Resume', 'wp-career-board' ); ?></h4>
							<a class="wcb-resume-chip" data-wp-bind--href="state.selectedAppResumeUrl" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Download Resume', 'wp-career-board' ); ?></a>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- VIEW: Company Profile -->
		<div class="wcb-view-panel" data-wp-class--wcb-view-active="state.isViewCompany">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'Company Profile', 'wp-career-board' ); ?></h1>
			</div>

			<div class="wcb-profile-grid">
				<div class="wcb-profile-form">
					<div class="wcb-field-group">
						<label class="wcb-field-label"><?php esc_html_e( 'Company Logo', 'wp-career-board' ); ?></label>
						<div class="wcb-logo-field">
							<img class="wcb-logo-current" data-wp-class--wcb-shown="state.companyLogoUrl" data-wp-bind--src="state.companyLogoUrl" alt="" width="64" height="64" />
							<label class="wcb-logo-upload-label" for="wcb-company-logo">
								<span data-wp-text="state.logoUploadLabel"></span>
							</label>
							<input id="wcb-company-logo" type="file" class="wcb-logo-input" accept="image/jpeg,image/png,image/gif,image/webp" data-wp-on--change="actions.uploadLogo" />
						</div>
					</div>
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
						<textarea id="wcb-company-desc" class="wcb-field-input wcb-field-textarea" rows="4" data-wcb-field="companyDesc" data-wp-bind--value="state.companyDesc" data-wp-on--input="actions.updateField"></textarea>
					</div>
					<div class="wcb-field-row">
						<div class="wcb-field-group">
							<label class="wcb-field-label" for="wcb-company-ind"><?php esc_html_e( 'Industry', 'wp-career-board' ); ?></label>
							<input id="wcb-company-ind" type="text" class="wcb-field-input" placeholder="<?php esc_attr_e( 'e.g. Technology', 'wp-career-board' ); ?>" data-wcb-field="companyIndustry" data-wp-bind--value="state.companyIndustry" data-wp-on--input="actions.updateField" />
						</div>
						<div class="wcb-field-group">
							<label class="wcb-field-label" for="wcb-company-size"><?php esc_html_e( 'Company Size', 'wp-career-board' ); ?></label>
							<select id="wcb-company-size" class="wcb-field-input wcb-field-select" data-wcb-field="companySize" data-wp-on--change="actions.updateField">
								<option value=""><?php esc_html_e( '— Select size —', 'wp-career-board' ); ?></option>
								<?php
								$wcb_size_options = array(
									'1-10'      => __( '1–10 employees', 'wp-career-board' ),
									'11-50'     => __( '11–50 employees', 'wp-career-board' ),
									'51-200'    => __( '51–200 employees', 'wp-career-board' ),
									'201-500'   => __( '201–500 employees', 'wp-career-board' ),
									'501-1000'  => __( '501–1,000 employees', 'wp-career-board' ),
									'1001-5000' => __( '1,001–5,000 employees', 'wp-career-board' ),
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

					<div class="wcb-profile-actions">
						<p class="wcb-db-save-success" data-wp-class--wcb-shown="state.saved"><?php esc_html_e( '✓ Profile saved successfully.', 'wp-career-board' ); ?></p>
						<p class="wcb-db-error" data-wp-class--wcb-shown="state.error" data-wp-text="state.error"></p>
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
							<span class="wcb-preview-chip" data-wp-class--wcb-hidden="!state.companyIndustry" data-wp-text="state.companyIndustry"></span>
							<span class="wcb-preview-chip" data-wp-class--wcb-hidden="!state.companySize" data-wp-text="state.companySize"></span>
							<span class="wcb-preview-chip" data-wp-class--wcb-hidden="!state.companyHq" data-wp-text="state.companyHq"></span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- VIEW: Post a Job -->
		<div class="wcb-view-panel" data-wp-class--wcb-view-active="state.isViewPostJob">
			<?php
			if ( is_user_logged_in() ) {
				echo do_blocks( '<!-- wp:wp-career-board/job-form /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
		</div>

	</main>
</div>

</div>
