<?php
/**
 * Admin Employers list page — overview of all employer profiles.
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
 * Renders the admin employers list page.
 *
 * @since 1.0.0
 */
class AdminEmployers {

	/**
	 * Render the employers list page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Employers', 'wp-career-board' ); ?></h1>
			<p><?php esc_html_e( 'Manage all employer profiles from this screen.', 'wp-career-board' ); ?></p>
		</div>
		<?php
	}
}
