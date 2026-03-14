<?php
/**
 * Admin Applications list page — overview of all job applications.
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
 * Renders the admin applications list page.
 *
 * @since 1.0.0
 */
class AdminApplications {

	/**
	 * Render the applications list page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Applications', 'wp-career-board' ); ?></h1>
			<p><?php esc_html_e( 'Manage all job applications from this screen.', 'wp-career-board' ); ?></p>
		</div>
		<?php
	}
}
