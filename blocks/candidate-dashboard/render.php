<?php
/**
 * Block render: wcb/candidate-dashboard — sidebar layout candidate interface.
 *
 * WordPress injects:
 *   $attributes  (array)    Block attributes defined in block.json.
 *   $content     (string)   Inner block content (empty for this block).
 *   $block       (WP_Block) Block instance object.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	echo '<p>' . esc_html__( 'Please log in to view your candidate dashboard.', 'wp-career-board' ) . '</p>';
	return;
}

$wcb_candidate_id = get_current_user_id();
$wcb_current_user = wp_get_current_user();
$wcb_display_name = $wcb_current_user->display_name;

$wcb_settings       = (array) get_option( 'wcb_settings', array() );
$wcb_jobs_page_id   = (int) ( $wcb_settings['jobs_archive_page'] ?? 0 );
$wcb_jobs_permalink = $wcb_jobs_page_id > 0 ? get_permalink( $wcb_jobs_page_id ) : false;
$wcb_jobs_url       = ( false !== $wcb_jobs_permalink && '' !== $wcb_jobs_permalink )
	? (string) $wcb_jobs_permalink
	: home_url( '/' );

/**
 * Pro populates this with the URL of the resume-builder page (?resume_id=N appended per resume).
 * Free passes an empty string — the My Resumes tab is hidden when empty.
 *
 * @since 1.0.0
 * @param string $url         Resume builder page URL (empty in Free).
 * @param int    $candidate_id Current user ID.
 */
$wcb_resume_builder_url = (string) apply_filters( 'wcb_resume_builder_url', '', $wcb_candidate_id );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only param, no state mutation.
$wcb_resume_embed_id         = absint( wp_unslash( $_GET['resume_id'] ?? '0' ) );
$wcb_resume_builder_embedded = WP_Block_Type_Registry::get_instance()->is_registered( 'wcb/resume-builder' );
$wcb_dashboard_url           = (string) get_permalink();

/**
 * Filter extra state keys for the candidate dashboard resumes tab.
 *
 * Pro uses this to inject maxResumes and resumeCount for cap enforcement.
 *
 * @since 1.0.0
 * @param array<string,mixed> $state   Default state with maxResumes=0, resumeCount=0.
 * @param int                 $user_id Current candidate user ID.
 */
$wcb_saved_jobs_count = (int) count( (array) get_user_meta( $wcb_candidate_id, '_wcb_bookmark', false ) );

$wcb_resumes_state = (array) apply_filters(
	'wcb_candidate_resumes_state',
	array(
		'maxResumes'  => 0,
		'resumeCount' => 0,
	),
	$wcb_candidate_id
);

wp_interactivity_state(
	'wcb-candidate-dashboard',
	array_merge(
		array(
			'tab'                   => $wcb_resume_embed_id > 0 && $wcb_resume_builder_embedded ? 'resume-builder' : 'overview',
			'savedJobsCount'        => $wcb_saved_jobs_count,
			'applications'          => array(),
			'bookmarks'             => array(),
			'resumes'               => array(),
			'loading'               => false,
			'error'                 => '',
			'apiBase'               => rest_url( 'wcb/v1' ),
			'nonce'                 => wp_create_nonce( 'wp_rest' ),
			'candidateId'           => $wcb_candidate_id,
			'candidateName'         => $wcb_display_name,
			'resumeBuilderUrl'      => $wcb_resume_builder_url,
			'resumesEnabled'        => '' !== $wcb_resume_builder_url,
			'dashboardUrl'          => $wcb_dashboard_url,
			'resumeBuilderEmbedded' => $wcb_resume_builder_embedded,
			'resumeEmbedId'         => $wcb_resume_embed_id,
			'showNewResumeForm'     => false,
			'newResumeTitle'        => '',
			'customFieldGroups'     => apply_filters( 'wcb_candidate_form_fields', array(), $wcb_candidate_id ),
			'bellNotifications'     => array(),
			'bellUnreadCount'       => 0,
			'bellOpen'              => false,
			'bellLoading'           => false,
			'alerts'                => array(),
			'alertsLoading'         => false,
		),
		$wcb_resumes_state
	)
);
?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-candidate-dashboard"
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
		<button type="button" class="wcb-sidebar-logo"
			data-wp-on--click="actions.switchToOverview"
			data-wp-class--wcb-nav-active="state.isTabOverview">
			<?php esc_html_e( 'Dashboard', 'wp-career-board' ); ?>
		</button>

		<nav class="wcb-sidebar-nav" aria-label="<?php esc_attr_e( 'Candidate dashboard navigation', 'wp-career-board' ); ?>">
			<span class="wcb-nav-section-label"><?php esc_html_e( 'MY ACTIVITY', 'wp-career-board' ); ?></span>
			<button type="button" class="wcb-nav-item" data-wp-class--wcb-nav-active="state.isTabApplications" data-wp-on--click="actions.switchToApplications">
				<?php esc_html_e( 'My Applications', 'wp-career-board' ); ?>
				<span class="wcb-nav-badge wcb-nav-badge--blue" data-wp-text="state.appsCount">0</span>
			</button>
			<button type="button" class="wcb-nav-item" data-wp-class--wcb-nav-active="state.isTabBookmarks" data-wp-on--click="actions.switchToBookmarks">
				<?php esc_html_e( 'Saved Jobs', 'wp-career-board' ); ?>
				<span class="wcb-nav-badge" data-wp-text="state.bookmarksCount">0</span>
			</button>
			<button
				type="button"
				class="wcb-nav-item"
				data-wp-class--wcb-nav-active="state.isTabResumes"
				data-wp-on--click="actions.switchToResumes"
				data-wp-bind--hidden="!state.resumesEnabled"
				<?php
				if ( ! $wcb_resume_builder_url ) :
					?>
					hidden<?php endif; ?>
			><?php esc_html_e( 'My Resumes', 'wp-career-board' ); ?></button>
			<?php if ( $wcb_resume_builder_embedded ) : ?>
			<button
				type="button"
				class="wcb-nav-item"
				data-wp-class--wcb-nav-active="state.isTabResumeBuilder"
				data-wp-on--click="actions.switchToResumeBuilder"
				<?php echo $wcb_resume_embed_id > 0 ? '' : 'hidden'; ?>
				data-wp-bind--hidden="!state.resumeEmbedId"
			><?php esc_html_e( 'Edit Resume', 'wp-career-board' ); ?></button>
			<?php endif; ?>
			<?php if ( class_exists( 'WCB\\Pro\\Modules\\Alerts\\AlertsModule' ) ) : ?>
			<button type="button" class="wcb-nav-item"
				data-wp-class--wcb-nav-active="state.isTabAlerts"
				data-wp-on--click="actions.switchToAlerts">
				<?php esc_html_e( 'Job Alerts', 'wp-career-board' ); ?>
				<span class="wcb-nav-badge wcb-nav-badge--green" data-wp-text="state.alertsCount">0</span>
			</button>
			<?php endif; ?>
		</nav>

		<a href="<?php echo esc_url( $wcb_jobs_url ); ?>" class="wcb-sidebar-cta">
			<?php esc_html_e( 'Browse Jobs', 'wp-career-board' ); ?> &#8599;
		</a>

		<div class="wcb-sidebar-user">
			<div class="wcb-sidebar-avatar" data-wp-text="state.candidateInitials" aria-hidden="true"></div>
			<span class="wcb-sidebar-company" data-wp-text="state.candidateName"></span>
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
		<div class="wcb-view-panel" data-wp-class--wcb-view-active="state.isTabOverview">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'Overview', 'wp-career-board' ); ?></h1>
			</div>

			<div class="wcb-stats-row">
				<div class="wcb-stat-card">
					<span class="wcb-stat-value" data-wp-text="state.appsCount">0</span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Applications', 'wp-career-board' ); ?></span>
				</div>
				<div class="wcb-stat-card wcb-stat-card--green">
					<span class="wcb-stat-value" data-wp-text="state.overviewShortlistedCount">0</span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Shortlisted', 'wp-career-board' ); ?></span>
				</div>
				<div class="wcb-stat-card wcb-stat-card--blue">
					<span class="wcb-stat-value" data-wp-text="state.savedJobsCount"><?php echo esc_html( (string) $wcb_saved_jobs_count ); ?></span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Saved Jobs', 'wp-career-board' ); ?></span>
				</div>
				<div class="wcb-stat-card wcb-stat-card--amber">
					<span class="wcb-stat-value" data-wp-text="state.resumeCount"><?php echo esc_html( (string) ( $wcb_resumes_state['resumeCount'] ?? 0 ) ); ?></span>
					<span class="wcb-stat-label"><?php esc_html_e( 'My Resumes', 'wp-career-board' ); ?></span>
				</div>
				<?php if ( class_exists( 'WCB\\Pro\\Modules\\Alerts\\AlertsModule' ) ) : ?>
				<div class="wcb-stat-card wcb-stat-card--green" style="cursor:pointer" data-wp-on--click="actions.switchToAlerts">
					<span class="wcb-stat-value" data-wp-text="state.alertsCount">0</span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Job Alerts', 'wp-career-board' ); ?></span>
				</div>
				<?php endif; ?>
			</div>

			<div class="wcb-two-col">
				<div class="wcb-panel wcb-shown">
					<div class="wcb-panel-header">
						<span class="wcb-panel-title"><?php esc_html_e( 'Recent Applications', 'wp-career-board' ); ?></span>
						<button type="button" class="wcb-panel-link" data-wp-on--click="actions.switchToApplications"><?php esc_html_e( 'View all →', 'wp-career-board' ); ?></button>
					</div>
					<div data-wp-class--wcb-shown="state.hasRecentApps">
						<template data-wp-each--app="state.overviewRecentApps" data-wp-each-key="context.app.id">
							<div class="wcb-overview-app-row">
								<div class="wcb-app-info">
									<span class="wcb-app-name" data-wp-text="context.app.jobTitle"></span>
									<span class="wcb-app-job" data-wp-text="context.app.company"></span>
								</div>
								<span class="wcb-status-badge" data-wp-text="context.app.status" data-wp-bind--data-status="context.app.status"></span>
							</div>
						</template>
					</div>
					<p class="wcb-panel-empty" data-wp-class--wcb-shown="state.noRecentApps"><?php esc_html_e( 'No applications yet.', 'wp-career-board' ); ?></p>
				</div>

				<div class="wcb-panel wcb-shown">
					<div class="wcb-panel-header">
						<span class="wcb-panel-title"><?php esc_html_e( 'Saved Jobs', 'wp-career-board' ); ?></span>
						<button type="button" class="wcb-panel-link" data-wp-on--click="actions.switchToBookmarks"><?php esc_html_e( 'View all →', 'wp-career-board' ); ?></button>
					</div>
					<div data-wp-class--wcb-shown="state.hasRecentSavedJobs">
						<template data-wp-each--saved="state.overviewRecentSavedJobs" data-wp-each-key="context.saved.id">
							<div class="wcb-overview-app-row">
								<div class="wcb-app-info">
									<a class="wcb-app-name" data-wp-bind--href="context.saved.permalink" data-wp-text="context.saved.title" target="_blank" rel="noopener noreferrer"></a>
									<span class="wcb-app-job" data-wp-text="context.saved.company"></span>
								</div>
								<span class="wcb-status-badge" data-wp-text="context.saved.type"></span>
							</div>
						</template>
					</div>
					<p class="wcb-panel-empty" data-wp-class--wcb-shown="state.noRecentSavedJobs"><?php esc_html_e( 'No saved jobs yet.', 'wp-career-board' ); ?></p>
				</div>
			</div>
		</div>

		<!-- VIEW: My Applications -->
		<div class="wcb-view-panel" data-wp-class--wcb-view-active="state.isTabApplications">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'My Applications', 'wp-career-board' ); ?></h1>
			</div>

			<div class="wcb-cd-loading" role="status" data-wp-class--wcb-shown="state.loading">
				<span class="wcb-cd-spinner" aria-hidden="true"></span>
				<?php esc_html_e( 'Loading…', 'wp-career-board' ); ?>
			</div>
			<p class="wcb-cd-error" data-wp-bind--hidden="!state.error" data-wp-text="state.error"></p>

			<div class="wcb-panel" data-wp-class--wcb-shown="state.hasApplications">
				<template data-wp-each--application="state.applications" data-wp-each-key="context.application.id">
					<div class="wcb-cd-app-row">
						<div class="wcb-cd-app-main">
							<h3 class="wcb-cd-app-title">
								<a
									data-wp-bind--href="context.application.jobPermalink"
									data-wp-text="context.application.jobTitle"
									target="_blank"
									rel="noopener noreferrer"
								></a>
							</h3>
							<span class="wcb-cd-app-company" data-wp-text="context.application.company"></span>
							<span class="wcb-cd-app-date" data-wp-text="context.application.date"></span>
						</div>
						<span
							class="wcb-cd-status-badge"
							data-wp-text="context.application.status"
							data-wp-bind--data-status="context.application.status"
						></span>
					</div>
				</template>
			</div>

			<div class="wcb-cd-empty" data-wp-class--wcb-shown="state.noApplications">
				<p class="wcb-cd-empty-msg"><?php esc_html_e( 'You haven\'t applied to any jobs yet.', 'wp-career-board' ); ?></p>
				<a href="<?php echo esc_url( $wcb_jobs_url ); ?>" class="wcb-cbtn wcb-cbtn--primary">
					<?php esc_html_e( 'Browse Jobs', 'wp-career-board' ); ?>
				</a>
			</div>
		</div>

		<!-- VIEW: Saved Jobs -->
		<div class="wcb-view-panel" data-wp-class--wcb-view-active="state.isTabBookmarks">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'Saved Jobs', 'wp-career-board' ); ?></h1>
			</div>

			<div class="wcb-cd-loading" role="status" data-wp-class--wcb-shown="state.loading">
				<span class="wcb-cd-spinner" aria-hidden="true"></span>
				<?php esc_html_e( 'Loading…', 'wp-career-board' ); ?>
			</div>
			<p class="wcb-cd-error" data-wp-bind--hidden="!state.error" data-wp-text="state.error"></p>

			<div class="wcb-panel" data-wp-class--wcb-shown="state.hasBookmarks">
				<template data-wp-each--bookmark="state.bookmarks" data-wp-each-key="context.bookmark.id">
					<div class="wcb-cd-bookmark-row">
						<div class="wcb-cd-bookmark-main">
							<h3 class="wcb-cd-bookmark-title">
								<a
									data-wp-bind--href="context.bookmark.permalink"
									data-wp-text="context.bookmark.title"
									target="_blank"
									rel="noopener noreferrer"
								></a>
							</h3>
							<div class="wcb-cd-bookmark-meta">
							<span data-wp-text="context.bookmark.company"></span>
							<span class="wcb-cd-bookmark-meta-sep" data-wp-class--wcb-hidden="!context.bookmark.location" aria-hidden="true">·</span>
							<span data-wp-class--wcb-hidden="!context.bookmark.location" data-wp-text="context.bookmark.location"></span>
							<span class="wcb-cd-bookmark-meta-sep" data-wp-class--wcb-hidden="!context.bookmark.type" aria-hidden="true">·</span>
							<span data-wp-class--wcb-hidden="!context.bookmark.type" data-wp-text="context.bookmark.type"></span>
						</div>
					</div>
					<div class="wcb-cd-bookmark-actions">
							<a
								class="wcb-cbtn wcb-cbtn--ghost wcb-cbtn--sm"
								data-wp-bind--href="context.bookmark.permalink"
								target="_blank"
								rel="noopener noreferrer"
							><?php esc_html_e( 'View Job', 'wp-career-board' ); ?></a>
							<button
								type="button"
								class="wcb-cbtn wcb-cbtn--danger wcb-cbtn--sm"
								data-wp-on--click="actions.unbookmark"
							><?php esc_html_e( 'Remove', 'wp-career-board' ); ?></button>
						</div>
					</div>
				</template>
			</div>

			<div class="wcb-cd-empty" data-wp-class--wcb-shown="state.noBookmarks">
				<p class="wcb-cd-empty-msg"><?php esc_html_e( 'No saved jobs yet. Bookmark a job to find it here.', 'wp-career-board' ); ?></p>
				<a href="<?php echo esc_url( $wcb_jobs_url ); ?>" class="wcb-cbtn wcb-cbtn--primary">
					<?php esc_html_e( 'Browse Jobs', 'wp-career-board' ); ?>
				</a>
			</div>
		</div>

		<!-- VIEW: My Resumes (Pro) -->
		<div class="wcb-view-panel" data-wp-class--wcb-view-active="state.isTabResumes">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'My Resumes', 'wp-career-board' ); ?></h1>
				<div class="wcb-resumes-header">
					<div data-wp-class--wcb-hidden="state.showNewResumeForm">
						<button
							type="button"
							class="wcb-cbtn wcb-cbtn--primary"
							data-wp-on--click="actions.toggleNewResumeForm"
							data-wp-bind--disabled="state.isAtResumesCap"
						><?php esc_html_e( '+ New Resume', 'wp-career-board' ); ?></button>
						<span class="wcb-resume-cap-info" data-wp-bind--hidden="!state.maxResumes" data-wp-text="state.resumeCapLabel"></span>
					</div>
					<div class="wcb-new-resume-form" data-wp-class--wcb-hidden="!state.showNewResumeForm">
						<label class="screen-reader-text" for="wcb-new-resume-title"><?php esc_html_e( 'Resume title', 'wp-career-board' ); ?></label>
						<input
							type="text"
							id="wcb-new-resume-title"
							class="wcb-input"
							placeholder="<?php esc_attr_e( 'e.g. Software Developer', 'wp-career-board' ); ?>"
							data-wp-bind--value="state.newResumeTitle"
							data-wp-on--input="actions.setNewResumeTitle"
						>
						<button type="button" class="wcb-cbtn wcb-cbtn--primary" data-wp-on--click="actions.createResume"><?php esc_html_e( 'Create', 'wp-career-board' ); ?></button>
						<button type="button" class="wcb-cbtn wcb-cbtn--ghost" data-wp-on--click="actions.toggleNewResumeForm"><?php esc_html_e( 'Cancel', 'wp-career-board' ); ?></button>
					</div>
				</div>
			</div>

			<div class="wcb-cd-loading" role="status" data-wp-class--wcb-shown="state.loading">
				<span class="wcb-cd-spinner" aria-hidden="true"></span>
				<?php esc_html_e( 'Loading…', 'wp-career-board' ); ?>
			</div>
			<p class="wcb-cd-error" data-wp-bind--hidden="!state.error" data-wp-text="state.error"></p>

			<div class="wcb-panel wcb-shown">
			<template data-wp-each--resume="state.resumes" data-wp-each-key="context.resume.id">
				<div class="wcb-resume-card" data-wp-context='{"confirmingDelete": false}'>
					<div class="wcb-resume-card-info">
						<span class="wcb-resume-card-title" data-wp-text="context.resume.title"></span>
						<span class="wcb-resume-card-date" data-wp-text="context.resume.date"></span>
					</div>
					<div class="wcb-resume-card-actions" data-wp-class--wcb-hidden="context.confirmingDelete">
						<a
							class="wcb-cbtn wcb-cbtn--ghost wcb-cbtn--sm"
							data-wp-bind--href="context.resume.permalink"
							target="_blank"
							rel="noopener"
						><?php esc_html_e( 'View', 'wp-career-board' ); ?></a>
						<button
							type="button"
							class="wcb-cbtn wcb-cbtn--ghost wcb-cbtn--sm"
							data-wp-on--click="actions.openResumeEditor"
						><?php esc_html_e( 'Edit', 'wp-career-board' ); ?></button>
						<button
							type="button"
							class="wcb-cbtn wcb-cbtn--danger wcb-cbtn--sm"
							data-wp-on--click="actions.requestDeleteConfirm"
						><?php esc_html_e( 'Delete', 'wp-career-board' ); ?></button>
					</div>
					<div class="wcb-resume-card-confirm" data-wp-class--wcb-hidden="!context.confirmingDelete">
						<span class="wcb-resume-confirm-msg"><?php esc_html_e( 'Delete this resume?', 'wp-career-board' ); ?></span>
						<button
							type="button"
							class="wcb-cbtn wcb-cbtn--danger wcb-cbtn--sm"
							data-wp-on--click="actions.deleteResume"
						><?php esc_html_e( 'Confirm', 'wp-career-board' ); ?></button>
						<button
							type="button"
							class="wcb-cbtn wcb-cbtn--ghost wcb-cbtn--sm"
							data-wp-on--click="actions.cancelDelete"
						><?php esc_html_e( 'Cancel', 'wp-career-board' ); ?></button>
					</div>
				</div>
			</template>
		</div><!-- .wcb-panel -->
		</div><!-- .wcb-view-panel: My Resumes -->

		<?php if ( $wcb_resume_builder_embedded ) : ?>
		<!-- VIEW: Resume Builder (Pro embedded) -->
		<div class="wcb-view-panel" data-wp-class--wcb-view-active="state.isTabResumeBuilder">
			<?php
			if ( $wcb_resume_embed_id > 0 ) {
				echo do_blocks( '<!-- wp:wcb/resume-builder /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
		</div>
		<?php endif; ?>

	<?php if ( class_exists( 'WCB\\Pro\\Modules\\Alerts\\AlertsModule' ) ) : ?>
		<!-- VIEW: Job Alerts (Pro) -->
		<div class="wcb-view-panel" data-wp-class--wcb-view-active="state.isTabAlerts">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'Job Alerts', 'wp-career-board' ); ?></h1>
			</div>

			<div class="wcb-cd-loading" role="status" data-wp-class--wcb-shown="state.alertsLoading">
				<span class="wcb-cd-spinner" aria-hidden="true"></span>
			</div>

			<div data-wp-class--wcb-shown="state.hasAlerts">
				<template data-wp-each--alert="state.alerts" data-wp-each-key="context.alert.id">
					<div class="wcb-alert-row">
						<div class="wcb-alert-main">
							<h3 class="wcb-alert-title" data-wp-text="context.alert.label"></h3>
							<div class="wcb-alert-meta">
								<template data-wp-each--pill="context.alert.filterPills" data-wp-each-key="context.pill">
									<span class="wcb-alert-pill" data-wp-text="context.pill"></span>
								</template>
							</div>
						</div>
						<div class="wcb-alert-actions">
							<select class="wcb-alert-freq" data-wp-bind--value="context.alert.frequency" data-wp-on--change="actions.changeAlertFrequency">
								<option value="instant"><?php esc_html_e( 'Instant', 'wp-career-board' ); ?></option>
								<option value="daily"><?php esc_html_e( 'Daily', 'wp-career-board' ); ?></option>
								<option value="weekly"><?php esc_html_e( 'Weekly', 'wp-career-board' ); ?></option>
							</select>
							<button type="button" class="wcb-cbtn wcb-cbtn--danger wcb-cbtn--sm" data-wp-on--click="actions.deleteAlert">
								<?php esc_html_e( 'Delete', 'wp-career-board' ); ?>
							</button>
						</div>
					</div>
				</template>
			</div>

			<div class="wcb-cd-empty" data-wp-class--wcb-shown="state.noAlerts">
				<p class="wcb-cd-empty-msg"><?php esc_html_e( 'No job alerts yet. Search for jobs and click "Alert me" to get notified when matching jobs are posted.', 'wp-career-board' ); ?></p>
				<a href="<?php echo esc_url( $wcb_jobs_url ); ?>" class="wcb-cbtn wcb-cbtn--primary">
					<?php esc_html_e( 'Browse Jobs', 'wp-career-board' ); ?>
				</a>
			</div>
		</div>
		<?php endif; ?>

	</main>

</div><!-- .wcb-dashboard-shell -->

</div>
