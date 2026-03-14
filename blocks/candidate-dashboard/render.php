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

wp_interactivity_state(
	'wcb-candidate-dashboard',
	array(
		'tab'          => 'applications',
		'applications' => array(),
		'bookmarks'    => array(),
		'loading'      => false,
		'error'        => '',
		'apiBase'      => rest_url( 'wcb/v1' ),
		'nonce'        => wp_create_nonce( 'wp_rest' ),
		'candidateId'  => $wcb_candidate_id,
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
	</nav>

	<!-- Tab: My Applications -->
	<div class="wcb-tab-panel" data-wp-show="state.isTabApplications">
		<div class="wcb-loading" data-wp-show="state.loading"><?php esc_html_e( 'Loading…', 'wp-career-board' ); ?></div>
		<p class="wcb-error" data-wp-show="state.error" data-wp-text="state.error"></p>

		<div data-wp-show="!state.loading">
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
	<div class="wcb-tab-panel" data-wp-show="state.isTabBookmarks">
		<div class="wcb-loading" data-wp-show="state.loading"><?php esc_html_e( 'Loading…', 'wp-career-board' ); ?></div>
		<p class="wcb-error" data-wp-show="state.error" data-wp-text="state.error"></p>

		<div data-wp-show="!state.loading">
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
</div>
