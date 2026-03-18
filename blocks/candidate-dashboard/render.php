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

$wcb_settings = (array) get_option( 'wcb_settings', array() );
$wcb_jobs_url = ! empty( $wcb_settings['jobs_archive_page'] )
	? (string) get_permalink( (int) $wcb_settings['jobs_archive_page'] )
	: '#';

/**
 * Pro populates this with the URL of the resume-builder page (?resume_id=N appended per resume).
 * Free passes an empty string — the My Resumes tab is hidden when empty.
 *
 * @since 1.0.0
 * @param string $url         Resume builder page URL (empty in Free).
 * @param int    $candidate_id Current user ID.
 */
$wcb_resume_builder_url = (string) apply_filters( 'wcb_resume_builder_url', '', $wcb_candidate_id );

/**
 * Filter extra state keys for the candidate dashboard resumes tab.
 *
 * Pro uses this to inject maxResumes and resumeCount for cap enforcement.
 *
 * @since 1.0.0
 * @param array<string,mixed> $state   Default state with maxResumes=0, resumeCount=0.
 * @param int                 $user_id Current candidate user ID.
 */
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
			'tab'               => 'applications',
			'applications'      => array(),
			'bookmarks'         => array(),
			'resumes'           => array(),
			'loading'           => false,
			'error'             => '',
			'apiBase'           => rest_url( 'wcb/v1' ),
			'nonce'             => wp_create_nonce( 'wp_rest' ),
			'candidateId'       => $wcb_candidate_id,
			'candidateName'     => $wcb_display_name,
			'resumeBuilderUrl'  => $wcb_resume_builder_url,
			'resumesEnabled'    => '' !== $wcb_resume_builder_url,
			'customFieldGroups' => apply_filters( 'wcb_candidate_form_fields', array(), $wcb_candidate_id ),
			'bellNotifications' => array(),
			'bellUnreadCount'   => 0,
			'bellOpen'          => false,
			'bellLoading'       => false,
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
	<aside class="wcb-sidebar">
		<div class="wcb-sidebar-logo"><?php esc_html_e( 'Dashboard', 'wp-career-board' ); ?></div>

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

		<!-- VIEW: My Applications -->
		<div class="wcb-view-panel" data-wp-class--wcb-view-active="state.isTabApplications">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'My Applications', 'wp-career-board' ); ?></h1>
			</div>

			<div class="wcb-cd-loading" data-wp-class--wcb-shown="state.loading">
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

			<div class="wcb-cd-loading" data-wp-class--wcb-shown="state.loading">
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
							<span class="wcb-cd-bookmark-company" data-wp-text="context.bookmark.company"></span>
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
					<button
						type="button"
						class="wcb-cbtn wcb-cbtn--primary"
						data-wp-on--click="actions.createResume"
						data-wp-bind--disabled="state.isAtResumesCap"
					><?php esc_html_e( '+ New Resume', 'wp-career-board' ); ?></button>
					<span class="wcb-resume-cap-info" data-wp-bind--hidden="!state.maxResumes" data-wp-text="state.resumeCapLabel"></span>
				</div>
			</div>

			<div class="wcb-cd-loading" data-wp-class--wcb-shown="state.loading">
				<span class="wcb-cd-spinner" aria-hidden="true"></span>
				<?php esc_html_e( 'Loading…', 'wp-career-board' ); ?>
			</div>
			<p class="wcb-cd-error" data-wp-bind--hidden="!state.error" data-wp-text="state.error"></p>

			<div class="wcb-panel wcb-shown">
			<template data-wp-each--resume="state.resumes" data-wp-each-key="context.resume.id">
				<div class="wcb-resume-card">
					<div class="wcb-resume-card-info">
						<span class="wcb-resume-card-title" data-wp-text="context.resume.title"></span>
						<span class="wcb-resume-card-date" data-wp-text="context.resume.date"></span>
					</div>
					<div class="wcb-resume-card-actions">
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
							data-wp-on--click="actions.deleteResume"
						><?php esc_html_e( 'Delete', 'wp-career-board' ); ?></button>
					</div>
				</div>
			</template>
		</div><!-- .wcb-panel -->

	</main>

</div><!-- .wcb-dashboard-shell -->

</div>
