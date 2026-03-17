<?php
/**
 * Block render: wcb/candidate-dashboard — tabbed candidate interface.
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
			'isTabApplications' => true,
			'isTabBookmarks'    => false,
			'isTabResumes'      => false,
			'applications'      => array(),
			'bookmarks'         => array(),
			'resumes'           => array(),
			'loading'           => false,
			'error'             => '',
			'apiBase'           => rest_url( 'wcb/v1' ),
			'nonce'             => wp_create_nonce( 'wp_rest' ),
			'candidateId'       => $wcb_candidate_id,
			'resumeBuilderUrl'  => $wcb_resume_builder_url,
			'resumesEnabled'    => '' !== $wcb_resume_builder_url,
			'customFieldGroups' => apply_filters( 'wcb_candidate_form_fields', array(), $wcb_candidate_id ),
		),
		$wcb_resumes_state
	)
);
?>
<div
	<?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-candidate-dashboard"
	data-wp-init="actions.init"
>
	<!-- Tab nav -->
	<nav class="wcb-dashboard-tabs" aria-label="<?php esc_attr_e( 'Candidate dashboard sections', 'wp-career-board' ); ?>">
		<button
			type="button"
			class="wcb-tab-btn"
			data-wp-class--active="state.isTabApplications"
			data-wp-on--click="actions.switchToApplications"
		><?php esc_html_e( 'My Applications', 'wp-career-board' ); ?></button>
		<button
			type="button"
			class="wcb-tab-btn"
			data-wp-class--active="state.isTabBookmarks"
			data-wp-on--click="actions.switchToBookmarks"
		><?php esc_html_e( 'Saved Jobs', 'wp-career-board' ); ?></button>
		<button
			type="button"
			class="wcb-tab-btn"
			data-wp-bind--hidden="!state.resumesEnabled"
			data-wp-class--active="state.isTabResumes"
			data-wp-on--click="actions.switchToResumes"
		><?php esc_html_e( 'My Resumes', 'wp-career-board' ); ?></button>
	</nav>

	<!-- Tab: My Applications -->
	<div class="wcb-tab-panel" data-wp-bind--hidden="!state.isTabApplications">
		<div class="wcb-loading" data-wp-bind--hidden="!state.loading"><?php esc_html_e( 'Loading…', 'wp-career-board' ); ?></div>
		<p class="wcb-error" data-wp-bind--hidden="!state.error" data-wp-text="state.error"></p>

		<div data-wp-bind--hidden="state.loading">
			<template data-wp-each--application="state.applications" data-wp-each-key="context.application.id">
				<div class="wcb-application-card">
					<h3>
						<a data-wp-bind--href="context.application.jobPermalink" data-wp-text="context.application.jobTitle"></a>
					</h3>
					<span class="wcb-app-status" data-wp-text="context.application.status"></span>
					<span class="wcb-app-date" data-wp-text="context.application.date"></span>
				</div>
			</template>
		</div>
	</div>

	<!-- Tab: Saved Jobs -->
	<div class="wcb-tab-panel" data-wp-bind--hidden="!state.isTabBookmarks">
		<div class="wcb-loading" data-wp-bind--hidden="!state.loading"><?php esc_html_e( 'Loading…', 'wp-career-board' ); ?></div>
		<p class="wcb-error" data-wp-bind--hidden="!state.error" data-wp-text="state.error"></p>

		<div data-wp-bind--hidden="state.loading">
			<template data-wp-each--bookmark="state.bookmarks" data-wp-each-key="context.bookmark.id">
				<div class="wcb-bookmark-card">
					<h3>
						<a data-wp-bind--href="context.bookmark.permalink" data-wp-text="context.bookmark.title"></a>
					</h3>
					<span data-wp-text="context.bookmark.company"></span>
					<button
						type="button"
						class="wcb-unbookmark-btn"
						data-wp-on--click="actions.unbookmark"
					><?php esc_html_e( 'Remove', 'wp-career-board' ); ?></button>
				</div>
			</template>
		</div>
	</div>

	<!-- Tab: My Resumes (Pro) -->
	<div class="wcb-tab-panel" data-wp-bind--hidden="!state.isTabResumes">
		<div class="wcb-loading" data-wp-bind--hidden="!state.loading"><?php esc_html_e( 'Loading…', 'wp-career-board' ); ?></div>
		<p class="wcb-error" data-wp-bind--hidden="!state.error" data-wp-text="state.error"></p>

		<div data-wp-bind--hidden="state.loading">
			<div class="wcb-resumes-header">
				<button
					type="button"
					class="wcb-cbtn wcb-cbtn--primary wcb-new-resume-btn"
					data-wp-on--click="actions.createResume"
					data-wp-bind--disabled="state.isAtResumesCap"
				><?php esc_html_e( '+ New Resume', 'wp-career-board' ); ?></button>
				<span class="wcb-resume-cap-info" data-wp-bind--hidden="!state.maxResumes" data-wp-text="state.resumeCapLabel"></span>
			</div>

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
		</div>
	</div>
</div>
