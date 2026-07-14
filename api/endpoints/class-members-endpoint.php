<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated endpoint name is intentional.
/**
 * REST endpoint for member-to-member safety: report, block, blocked list.
 *
 * Required for App Store review of any app with user-generated content
 * (Apple 1.2). Reports reuse the moderation flag shape already used for jobs
 * (a per-reporter array + a reason map), stored as user-meta on the reported
 * member; the site owner sees the flag count and can suspend from the
 * Candidates admin screen. Blocks reuse the non-unique-usermeta list pattern
 * the bookmark feature uses.
 *
 * Routes:
 *   POST   /wcb/v1/users/{id}/report  — report a member
 *   POST   /wcb/v1/users/{id}/block   — block a member
 *   DELETE /wcb/v1/users/{id}/block   — unblock
 *   GET    /wcb/v1/me/blocked         — the caller's blocked list
 *
 * @package WP_Career_Board
 * @since   1.7.0
 */

declare( strict_types=1 );

namespace WCB\Api\Endpoints;

use WCB\Api\RestController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Member report + block surface.
 *
 * @since 1.7.0
 */
class MembersEndpoint extends RestController {

	/**
	 * Reasons a member can be reported.
	 *
	 * @since 1.7.0
	 * @return array<string,string>
	 */
	public static function report_reasons(): array {
		return array(
			'spam'         => __( 'Spam or advertisement', 'wp-career-board' ),
			'scam'         => __( 'Scam or fraudulent', 'wp-career-board' ),
			'fake_profile' => __( 'Fake or impersonating profile', 'wp-career-board' ),
			'harassment'   => __( 'Harassment or abuse', 'wp-career-board' ),
			'offensive'    => __( 'Offensive or inappropriate', 'wp-career-board' ),
		);
	}

	/**
	 * The user IDs a member has blocked.
	 *
	 * @since 1.7.0
	 *
	 * @param int $user_id Blocker user ID.
	 * @return array<int>
	 */
	public static function blocked_ids( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}
		return array_values( array_unique( array_map( 'intval', (array) get_user_meta( $user_id, '_wcb_blocked', false ) ) ) );
	}

	/**
	 * Register the routes.
	 *
	 * @since 1.7.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/users/(?P<id>\d+)/report',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'report_member' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
				'args'                => array(
					'reason'  => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array_keys( self::report_reasons() ),
					),
					'details' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/users/(?P<id>\d+)/block',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'block_member' ),
					'permission_callback' => array( $this, 'is_logged_in' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'unblock_member' ),
					'permission_callback' => array( $this, 'is_logged_in' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/me/blocked',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_blocked' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
			)
		);
	}

	/**
	 * POST /users/{id}/report — flag a member, deduped per reporter.
	 *
	 * @since 1.7.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function report_member( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$target = $this->resolve_target( $request );
		if ( is_wp_error( $target ) ) {
			return $target;
		}

		$reporter = $this->current_user_id();
		if ( $target->ID === $reporter ) {
			return new \WP_Error( 'wcb_cannot_report_self', __( 'You cannot report yourself.', 'wp-career-board' ), array( 'status' => 400 ) );
		}

		$reporters_meta = get_user_meta( $target->ID, '_wcb_member_flag_reporters', true );
		$reporters      = is_array( $reporters_meta ) ? array_map( 'intval', $reporters_meta ) : array();

		if ( in_array( $reporter, $reporters, true ) ) {
			return rest_ensure_response( array( 'reported' => true, 'already_reported' => true ) );
		}

		$reason             = (string) $request->get_param( 'reason' );
		$reporters[]        = $reporter;
		$reasons_meta       = get_user_meta( $target->ID, '_wcb_member_flag_reasons', true );
		$reasons            = is_array( $reasons_meta ) ? $reasons_meta : array();
		$reasons[ $reason ] = (int) ( $reasons[ $reason ] ?? 0 ) + 1;

		update_user_meta( $target->ID, '_wcb_member_flag_reporters', $reporters );
		update_user_meta( $target->ID, '_wcb_member_flag_reasons', $reasons );
		update_user_meta( $target->ID, '_wcb_member_flag_count', count( $reporters ) );
		update_user_meta( $target->ID, '_wcb_member_flag_status', 'open' );

		/**
		 * Fires after a member reports another member.
		 *
		 * @since 1.7.0
		 *
		 * @param int    $target_id Reported user ID.
		 * @param string $reason    Reason slug.
		 * @param int    $reporter  Reporting user ID.
		 */
		do_action( 'wcb_member_reported', $target->ID, $reason, $reporter );

		return rest_ensure_response( array( 'reported' => true ) );
	}

	/**
	 * POST /users/{id}/block.
	 *
	 * @since 1.7.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function block_member( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$target = $this->resolve_target( $request );
		if ( is_wp_error( $target ) ) {
			return $target;
		}

		$blocker = $this->current_user_id();
		if ( $target->ID === $blocker ) {
			return new \WP_Error( 'wcb_cannot_block_self', __( 'You cannot block yourself.', 'wp-career-board' ), array( 'status' => 400 ) );
		}

		if ( ! in_array( $target->ID, self::blocked_ids( $blocker ), true ) ) {
			add_user_meta( $blocker, '_wcb_blocked', $target->ID, false );
			do_action( 'wcb_member_blocked', $blocker, $target->ID );
		}

		return rest_ensure_response( array( 'blocked' => true ) );
	}

	/**
	 * DELETE /users/{id}/block.
	 *
	 * @since 1.7.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function unblock_member( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$target = $this->resolve_target( $request );
		if ( is_wp_error( $target ) ) {
			return $target;
		}

		delete_user_meta( $this->current_user_id(), '_wcb_blocked', $target->ID );
		do_action( 'wcb_member_unblocked', $this->current_user_id(), $target->ID );

		return rest_ensure_response( array( 'blocked' => false ) );
	}

	/**
	 * GET /me/blocked — the caller's blocked members.
	 *
	 * @since 1.7.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function list_blocked( \WP_REST_Request $request ): \WP_REST_Response {
		$ids = self::blocked_ids( $this->current_user_id() );
		if ( empty( $ids ) ) {
			return rest_ensure_response( array() );
		}

		// Batch-load the users so the map below is not N+1.
		$users = get_users(
			array(
				'include' => $ids,
				'fields'  => array( 'ID', 'display_name' ),
			)
		);

		$out = array();
		foreach ( $users as $user ) {
			$out[] = array(
				'id'          => (int) $user->ID,
				'name'        => $user->display_name,
				'avatar'      => get_avatar_url( $user->ID ),
				'profile_url' => (string) get_author_posts_url( $user->ID ),
			);
		}

		return rest_ensure_response( $out );
	}

	/**
	 * Resolve and validate the target user from the route.
	 *
	 * @since 1.7.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_User|\WP_Error
	 */
	private function resolve_target( \WP_REST_Request $request ) {
		$user = get_user_by( 'ID', (int) $request['id'] );
		if ( ! $user instanceof \WP_User ) {
			return new \WP_Error( 'wcb_user_not_found', __( 'Member not found.', 'wp-career-board' ), array( 'status' => 404 ) );
		}
		return $user;
	}

	/**
	 * Require a logged-in member.
	 *
	 * @since 1.7.0
	 * @return bool|\WP_Error
	 */
	public function is_logged_in(): bool|\WP_Error {
		return is_user_logged_in() ? true : $this->permission_error();
	}
}
