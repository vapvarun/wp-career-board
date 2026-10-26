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
	 * The given list filters present in the request, as query args.
	 *
	 * @param string[] $keys Query args that narrow the list.
	 * @return array<string, string>
	 */
	protected function list_args( array $keys ): array {
		$args = array();
		foreach ( $keys as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view args.
			if ( isset( $_GET[ $key ] ) && '' !== $_GET[ $key ] ) {
				$args[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
		return $args;
	}

	/**
	 * Whether any of the list's filters is in the request.
	 *
	 * @param string[] $keys Query args that narrow the list.
	 * @return bool
	 */
	protected function list_filtered( array $keys ): bool {
		return (bool) $this->list_args( $keys );
	}

	/**
	 * Whether the list shows the Trash.
	 *
	 * @return bool
	 */
	protected function is_trash_view(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch.
		return isset( $_GET['post_status'] ) && 'trash' === $_GET['post_status'];
	}

	/**
	 * Hidden inputs that keep the page and the current view across the search form.
	 *
	 * @param string                $page Admin page slug.
	 * @param array<string, string> $args View args to keep.
	 * @return void
	 */
	protected function hidden_inputs( string $page, array $args ): void {
		printf( '<input type="hidden" name="page" value="%s">', esc_attr( $page ) );
		foreach ( $args as $key => $value ) {
			printf( '<input type="hidden" name="%1$s" value="%2$s">', esc_attr( $key ), esc_attr( $value ) );
		}
	}

	/**
	 * One active-filter chip with a remove link.
	 *
	 * @param string   $label  Translated chip text.
	 * @param string[] $remove Query args the remove link drops.
	 * @param string   $aria   Translated label for the remove link.
	 * @return void
	 */
	protected function filter_chip( string $label, array $remove, string $aria ): void {
		printf(
			'<span class="wcb-filter-chip">%1$s <a href="%2$s" aria-label="%3$s">&times;</a></span>',
			esc_html( $label ),
			esc_url( remove_query_arg( array_merge( $remove, array( 'paged' ) ) ) ),
			esc_attr( $aria )
		);
	}

	/**
	 * Print the "no match" empty state with a Clear filters link.
	 *
	 * @param string $icon  Lucide icon name.
	 * @param string $title Translated "No ... match your filters" text.
	 * @param string $page  Admin page slug of the unfiltered list.
	 * @return void
	 */
	protected function filtered_empty_state( string $icon, string $title, string $page ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view check.
		$trash = $this->is_trash_view() && empty( $_GET['s'] );
		printf(
			'<div class="wcb-empty-state"><i data-lucide="%1$s" class="wcb-empty-state__icon"></i><p class="wcb-empty-state__title">%2$s</p><a href="%3$s" class="wcb-btn">%4$s</a></div>',
			esc_attr( $trash ? 'trash-2' : $icon ),
			esc_html( $trash ? __( 'The Trash is empty.', 'wp-career-board' ) : $title ),
			esc_url( admin_url( 'admin.php?page=' . $page ) ),
			esc_html( $trash ? __( 'View all', 'wp-career-board' ) : __( 'Clear filters', 'wp-career-board' ) )
		);
	}
}
