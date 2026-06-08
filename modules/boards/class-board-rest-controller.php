<?php
/**
 * REST controller for the wcb_board CPT.
 *
 * @package WP_Career_Board
 * @since   1.2.1
 */

declare( strict_types=1 );

namespace WCB\Modules\Boards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gates /wp/v2/wcb_board reads behind the content-editing capability.
 *
 * Core's WP_REST_Posts_Controller::check_read_permission() returns true for any
 * published post regardless of the post type's public=false flag, which exposed
 * board id/title/slug to anonymous visitors. Boards are admin-only config (only
 * administrators create them) and the only REST consumer is the block editor's
 * board picker, so reads are gated on edit_posts: editors keep the picker while
 * anonymous enumeration stops. Writes already require post caps via map_meta_cap.
 *
 * @since 1.2.1
 */
class BoardRestController extends \WP_REST_Posts_Controller {

	/**
	 * Require content-editing capability to list boards.
	 *
	 * @since 1.2.1
	 *
	 * @param \WP_REST_Request $request Full request.
	 * @return bool|\WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		$gate = $this->board_read_gate();
		return is_wp_error( $gate ) ? $gate : parent::get_items_permissions_check( $request );
	}

	/**
	 * Require content-editing capability to read a single board.
	 *
	 * @since 1.2.1
	 *
	 * @param \WP_REST_Request $request Full request.
	 * @return bool|\WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$gate = $this->board_read_gate();
		return is_wp_error( $gate ) ? $gate : parent::get_item_permissions_check( $request );
	}

	/**
	 * Shared read gate — true when the user can edit content, else a 401/403.
	 *
	 * @since 1.2.1
	 *
	 * @return true|\WP_Error
	 */
	private function board_read_gate() {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}
		return new \WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to view job boards.', 'wp-career-board' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
}
