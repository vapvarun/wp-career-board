<?php
/**
 * Reported members - shared by the Candidates and Employers lists.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

declare( strict_types=1 );

namespace WCB\Admin;

use WCB\Modules\Moderation\ModerationModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The 'Reported' and 'Suspended' / 'Banned' views, the report badge and the
 * Dismiss reports action.
 *
 * @since 1.8.0
 */
trait ReportedMembers {

	/**
	 * Whether the Reported view is active.
	 *
	 * @return bool
	 */
	protected function reported_active(): bool {
		return isset( $_GET['wcb_reported'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- view filter.
	}

	/**
	 * Whether the list is narrowed to suspended (candidates) or banned
	 * (employers) members; both carry `_wcb_employer_banned`.
	 *
	 * @return bool
	 */
	protected function banned_active(): bool {
		return isset( $_GET['wcb_banned'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- view filter.
	}

	/**
	 * Meta query for the active view, or none for All.
	 *
	 * @return array
	 */
	protected function view_meta_query(): array {
		if ( $this->reported_active() ) {
			return $this->reported_meta_query();
		}
		return $this->banned_active() ? $this->banned_meta_query() : array();
	}

	/**
	 * Meta query matching suspended / banned members.
	 *
	 * @return array
	 */
	protected function banned_meta_query(): array {
		return array(
			array(
				'key'   => '_wcb_employer_banned',
				'value' => '1',
			),
		);
	}

	/**
	 * Query args of the active view, kept across bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function view_args(): array {
		if ( $this->reported_active() ) {
			return array( 'wcb_reported' => '1' );
		}
		return $this->banned_active() ? array( 'wcb_banned' => '1' ) : array();
	}

	/**
	 * View and search to come back to after a row or bulk action, so the admin
	 * stays where they acted (Basecamp 10417317114, 10417326173).
	 *
	 * @return array<string, string>
	 */
	protected function return_args(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list state.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		return $this->view_args() + ( '' !== $search ? array( 's' => $search ) : array() );
	}

	/**
	 * Nonced URL of a row action that returns to the current view and search.
	 *
	 * @param string $page    Admin page slug.
	 * @param string $action  Action name.
	 * @param int    $user_id Member.
	 * @param string $nonce   Bulk nonce action of the list.
	 * @return string
	 */
	protected function row_action_url( string $page, string $action, int $user_id, string $nonce ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'   => $page,
					'action' => $action,
					'user'   => array( $user_id ),
				) + $this->return_args(),
				admin_url( 'admin.php' )
			),
			$nonce
		);
	}

	/**
	 * Meta query matching members with open reports.
	 *
	 * @return array
	 */
	protected function reported_meta_query(): array {
		return array(
			array(
				'key'   => '_wcb_member_flag_status',
				'value' => 'open',
			),
		);
	}

	/**
	 * The All, Reported and Suspended / Banned view links. A view shows once it
	 * has members, or while it is open.
	 *
	 * @param string $page         Admin page slug.
	 * @param int    $all          Count for All.
	 * @param array  $user_args    WP_User_Query args selecting this list's members.
	 * @param string $banned_label Translated label of the banned view.
	 * @return array<string, string>
	 */
	protected function member_views( string $page, int $all, array $user_args, string $banned_label ): array {
		$base     = admin_url( 'admin.php?page=' . $page );
		$count    = static fn ( array $meta_query ): int => ( new \WP_User_Query(
			$user_args + array(
				'meta_query'  => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'fields'      => 'ID',
				'number'      => 1,
				'count_total' => true,
			)
		) )->get_total();
		$reported = $count( $this->reported_meta_query() );
		$banned   = $count( $this->banned_meta_query() );
		$active   = $this->reported_active();
		$views    = array(
			'all' => sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( $base ),
				$active || $this->banned_active() ? '' : ' class="current"',
				esc_html__( 'All', 'wp-career-board' ),
				$all
			),
		);
		if ( $reported > 0 || $active ) {
			$views['reported'] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'wcb_reported', '1', $base ) ),
				$active ? ' class="current"' : '',
				esc_html__( 'Reported', 'wp-career-board' ),
				$reported
			);
		}
		if ( $banned > 0 || $this->banned_active() ) {
			$views['banned'] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'wcb_banned', '1', $base ) ),
				$this->banned_active() ? ' class="current"' : '',
				esc_html( $banned_label ),
				$banned
			);
		}
		return $views;
	}

	/**
	 * Badge with the open report count, or ''.
	 *
	 * @param int $user_id Member.
	 * @return string
	 */
	protected function reports_badge( int $user_id ): string {
		$count = ModerationModule::open_member_reports( $user_id );
		if ( $count <= 0 ) {
			return '';
		}
		return sprintf(
			' <span class="wcb-badge wcb-badge--warn" title="%s">%s</span>',
			esc_attr__( 'Open reports', 'wp-career-board' ),
			esc_html(
				sprintf(
					/* translators: %d: number of reports. */
					_n( '%d report', '%d reports', $count, 'wp-career-board' ),
					$count
				)
			)
		);
	}

	/**
	 * The Dismiss reports row action, when the member has open reports.
	 *
	 * @param int    $user_id Member.
	 * @param string $page    Admin page slug.
	 * @param string $nonce   Bulk nonce action of the list.
	 * @return array<string, string>
	 */
	protected function dismiss_reports_action( int $user_id, string $page, string $nonce ): array {
		if ( ModerationModule::open_member_reports( $user_id ) <= 0 ) {
			return array();
		}
		$url = $this->row_action_url( $page, 'resolve_flags', $user_id, $nonce );
		return array(
			'resolve_flags' => sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Dismiss reports', 'wp-career-board' ) ),
		);
	}
}
