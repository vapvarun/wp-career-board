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
 * The 'Reported' view, the report badge and the Dismiss reports action.
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
	 * The All and Reported view links.
	 *
	 * @param string $page      Admin page slug.
	 * @param int    $all       Count for All.
	 * @param array  $user_args WP_User_Query args selecting this list's members.
	 * @return array<string, string>
	 */
	protected function member_views( string $page, int $all, array $user_args ): array {
		$base     = admin_url( 'admin.php?page=' . $page );
		$reported = ( new \WP_User_Query(
			$user_args + array(
				'meta_query'  => $this->reported_meta_query(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'fields'      => 'ID',
				'number'      => 1,
				'count_total' => true,
			)
		) )->get_total();
		$active   = $this->reported_active();
		$views    = array(
			'all' => sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( $base ),
				$active ? '' : ' class="current"',
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
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => $page,
					'action' => 'resolve_flags',
					'user'   => array( $user_id ),
				),
				admin_url( 'admin.php' )
			),
			$nonce
		);
		return array(
			'resolve_flags' => sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Dismiss reports', 'wp-career-board' ) ),
		);
	}
}
