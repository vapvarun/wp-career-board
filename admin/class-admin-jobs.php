<?php
/**
 * Admin Jobs list page — shows pending and published job listings.
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
 * Renders the admin jobs list with moderation controls.
 *
 * @since 1.0.0
 */
class AdminJobs {

	/**
	 * Render the jobs list page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render(): void {
		$pending   = get_posts(
			array(
				'post_type'   => 'wcb_job',
				'post_status' => 'pending',
				'numberposts' => -1,
			)
		);
		$published = get_posts(
			array(
				'post_type'   => 'wcb_job',
				'post_status' => 'publish',
				'numberposts' => 50,
			)
		);

		require_once WCB_DIR . 'admin/views/jobs-list.php';
	}
}
