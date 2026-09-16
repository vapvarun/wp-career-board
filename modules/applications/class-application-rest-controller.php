<?php
/**
 * REST controller for the wcb_application CPT.
 *
 * @package WP_Career_Board
 * @since   1.7.1
 */

declare( strict_types=1 );

namespace WCB\Modules\Applications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gates /wp/v2/wcb_application reads behind the application-viewing ability.
 *
 * Core's WP_REST_Posts_Controller::check_read_permission() returns true for any
 * published post regardless of the post type's public=false flag, and
 * applications are created with post_status 'publish'. The registered post meta
 * carries an auth_callback, but core consults that on WRITE only - never on
 * read - so an anonymous GET returned the whole application record: which
 * candidate applied to which job, their cover letter, their status, and for
 * guest applications their name and email address.
 *
 * This is the same defect BoardRestController fixed for wcb_board in 1.2.1. The
 * plugin's own /wcb/v1 endpoints are the supported way to read applications and
 * they scope by ownership; /wp/v2/wcb_application has no first-party consumer,
 * so gating it costs nothing.
 *
 * The gate is the site-administration ability rather than wcb/view-applications.
 * That ability is granted to every employer, so using it would still have let
 * one employer list another employer's applicants here. Employers already have
 * a correctly scoped route - GET /wcb/v1/employers/me/applications - and nothing
 * first-party reads /wp/v2/wcb_application, so there is no reason for this
 * surface to answer anyone below an administrator.
 *
 * @since 1.7.1
 */
class ApplicationRestController extends \WP_REST_Posts_Controller {

	/**
	 * Require the application-viewing ability to list applications.
	 *
	 * @since 1.7.1
	 *
	 * @param \WP_REST_Request $request Full request.
	 * @return bool|\WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		$gate = $this->application_read_gate();
		return is_wp_error( $gate ) ? $gate : parent::get_items_permissions_check( $request );
	}

	/**
	 * Require the application-viewing ability to read one application.
	 *
	 * @since 1.7.1
	 *
	 * @param \WP_REST_Request $request Full request.
	 * @return bool|\WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$gate = $this->application_read_gate();
		return is_wp_error( $gate ) ? $gate : parent::get_item_permissions_check( $request );
	}

	/**
	 * Shared read gate.
	 *
	 * @since 1.7.1
	 *
	 * @return true|\WP_Error
	 */
	private function application_read_gate() {
		if ( wp_is_ability_granted( 'wcb/manage-settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
			return true;
		}

		return new \WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to view applications.', 'wp-career-board' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
}
