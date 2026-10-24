<?php
/**
 * Empty state for a filtered admin list - shared by every Career Board list.
 *
 * @package WP_Career_Board
 * @since   1.8.2
 */

declare( strict_types=1 );

namespace WCB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A search, status or view that matches nothing says so and links back to the
 * whole list, instead of the "nothing here yet" text a new site sees.
 *
 * @since 1.8.2
 */
trait ListEmptyState {

	/**
	 * Whether any of the list's filters is in the request.
	 *
	 * @param string[] $keys Query args that narrow the list.
	 * @return bool
	 */
	protected function list_filtered( array $keys ): bool {
		foreach ( $keys as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view check.
			if ( isset( $_GET[ $key ] ) && '' !== $_GET[ $key ] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Print the "no match" empty state with a Clear filters link.
	 *
	 * @param string $icon  Lucide icon name.
	 * @param string $title Translated "No ... match your filters." text.
	 * @param string $page  Admin page slug of the unfiltered list.
	 * @return void
	 */
	protected function filtered_empty_state( string $icon, string $title, string $page ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view check.
		$trash = isset( $_GET['post_status'] ) && 'trash' === $_GET['post_status'] && empty( $_GET['s'] );
		printf(
			'<div class="wcb-empty-state"><i data-lucide="%1$s" class="wcb-empty-state__icon"></i><p class="wcb-empty-state__title">%2$s</p><a href="%3$s" class="wcb-btn">%4$s</a></div>',
			esc_attr( $trash ? 'trash-2' : $icon ),
			esc_html( $trash ? __( 'The Trash is empty.', 'wp-career-board' ) : $title ),
			esc_url( admin_url( 'admin.php?page=' . $page ) ),
			esc_html( $trash ? __( 'View all', 'wp-career-board' ) : __( 'Clear filters', 'wp-career-board' ) )
		);
	}
}
