<?php
/**
 * Search REST endpoint — unified job search entry point.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Api\Endpoints;

use WCB\Api\RestController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles /wcb/v1/search — delegates to JobsEndpoint with the same query params.
 *
 * Provides a single, discoverable entry point for job search without
 * duplicating filter logic from JobsEndpoint.
 *
 * @since 1.0.0
 */
final class SearchEndpoint extends RestController {

	/**
	 * Register the search route.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/search',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => '__return_true',
				'args'                => ( new JobsEndpoint() )->get_collection_params(),
			)
		);
	}

	/**
	 * Delegate search to the Jobs endpoint using the same request params.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response
	 */
	public function search( \WP_REST_Request $request ): \WP_REST_Response {
		return ( new JobsEndpoint() )->get_items( $request );
	}
}
