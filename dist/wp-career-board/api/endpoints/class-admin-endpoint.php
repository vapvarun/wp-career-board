<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated name matches WP convention for multi-word classes.
/**
 * Admin REST endpoint — handles admin UI actions (e.g. dismissing notices).
 *
 * Routes:
 *   POST /wcb/v1/admin/dismiss-banner  — mark the Pro upgrade banner as dismissed for the current user
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Api\Endpoints;

use WCB\Api\RestController;

if (! defined('ABSPATH') ) {
    exit;
}

/**
 * Handles /wcb/v1/admin/* REST routes.
 *
 * @since 1.0.0
 */
final class AdminEndpoint extends RestController
{

    /**
     * Register admin routes.
     *
     * @since  1.0.0
     * @return void
     */
    public function register_routes(): void
    {
        register_rest_route(
            $this->namespace,
            '/admin/dismiss-banner',
            array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'dismiss_banner' ),
            'permission_callback' => array( $this, 'admin_check' ),
            'args'                => array(
            'banner' => array(
            'required'          => true,
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_key',
                    ),
            ),
            )
        );
    }

    /**
     * Require an authenticated admin-level user.
     *
     * @since 1.0.0
     *
     * @param  \WP_REST_Request $request Full request object.
     * @return bool|\WP_Error
     */
    public function admin_check( \WP_REST_Request $request ): bool|\WP_Error
    {
        return $this->check_ability('wcb_manage_settings') ? true : $this->permission_error();
    }

    /**
     * Mark a banner as dismissed for the current user.
     *
     * @since 1.0.0
     *
     * @param  \WP_REST_Request $request Full REST request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function dismiss_banner( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error
    {
        $banner   = (string) $request->get_param('banner');
        $meta_key = 'wcb_' . $banner . '_dismissed';

        update_user_meta(get_current_user_id(), $meta_key, true);

        return rest_ensure_response(array( 'dismissed' => true ));
    }
}
