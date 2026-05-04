<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated name matches WP convention for multi-word classes.
/**
 * Admin REST endpoint — handles admin UI actions (e.g. dismissing notices, email log).
 *
 * Routes:
 *   POST /wcb/v1/admin/dismiss-banner  — mark a banner as dismissed for the current user
 *   POST /wcb/v1/admin/emails/test     — fire a test-send for the named email template
 *   GET  /wcb/v1/admin/emails/log      — paginated log query with filter args
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
 * Handles /wcb/v1/admin/* REST routes.
 *
 * @since 1.0.0
 */
final class AdminEndpoint extends RestController {



	/**
	 * Register admin routes.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_routes(): void {
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

		register_rest_route(
			$this->namespace,
			'/admin/emails/test',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_send_email' ),
				'permission_callback' => array( $this, 'admin_check' ),
				'args'                => array(
					'email_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/admin/emails/log',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_email_log' ),
				'permission_callback' => array( $this, 'admin_check' ),
				'args'                => array(
					'event_type' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'status'     => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'per_page'   => array(
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'page'       => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
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
	public function admin_check( \WP_REST_Request $request ): bool|\WP_Error {
		return $this->check_ability( 'wcb_manage_settings' ) ? true : $this->permission_error();
	}

	/**
	 * Mark a banner as dismissed for the current user.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function dismiss_banner( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$banner   = (string) $request->get_param( 'banner' );
		$meta_key = 'wcb_' . $banner . '_dismissed';

		update_user_meta( get_current_user_id(), $meta_key, true );

		return rest_ensure_response( array( 'dismissed' => true ) );
	}

	/**
	 * Fire a test-send for one registered email template, addressed to the current admin user.
	 *
	 * Used by the Emails settings page so site admins can verify SMTP / template
	 * rendering without triggering a real candidate or employer flow. Reuses the
	 * email's send() path so the wcb_notifications_log row gets written too.
	 *
	 * @since 1.1.1
	 *
	 * @param  \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function test_send_email( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$email_id = (string) $request->get_param( 'email_id' );
		$emails   = (array) apply_filters( 'wcb_registered_emails', array() );

		$target = null;
		foreach ( $emails as $email ) {
			if ( $email instanceof \WCB\Modules\Notifications\AbstractEmail && $email->get_id() === $email_id ) {
				$target = $email;
				break;
			}
		}

		if ( null === $target ) {
			return new \WP_Error(
				'wcb_email_not_found',
				/* translators: %s: requested email id */
				sprintf( __( 'No registered email matches id "%s".', 'wp-career-board' ), $email_id ),
				array( 'status' => 404 )
			);
		}

		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return $this->permission_error();
		}

		$test_vars = array(
			'job_title'     => __( 'Sample Job Title (test send)', 'wp-career-board' ),
			'company_name'  => get_bloginfo( 'name' ),
			'candidate'     => $user->display_name,
			'employer'      => $user->display_name,
			'status_label'  => __( 'Shortlisted', 'wp-career-board' ),
			'days_left'     => 3,
			'deadline_iso'  => gmdate( 'Y-m-d', time() + 3 * DAY_IN_SECONDS ),
			'job_url'       => home_url( '/' ),
			'dashboard_url' => home_url( '/' ),
			'is_test'       => true,
		);

		// Capture log row count before to confirm the send wrote a row.
		global $wpdb;
		$before = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wcb_notifications_log WHERE event_type = %s",
				$target->get_id()
			)
		);

		// Reuse the email's protected send() via a dedicated public bridge — defined on
		// AbstractEmail::test_send() to avoid exposing send() globally.
		$reflection = new \ReflectionClass( $target );
		if ( ! $reflection->hasMethod( 'send' ) ) {
			return new \WP_Error(
				'wcb_email_not_dispatchable',
				__( 'Internal error: email class is missing send().', 'wp-career-board' ),
				array( 'status' => 500 )
			);
		}
		$send = $reflection->getMethod( 'send' );
		$send->setAccessible( true );
		$send->invoke( $target, $user->user_email, $test_vars, $user_id );

		$after = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wcb_notifications_log WHERE event_type = %s",
				$target->get_id()
			)
		);

		return rest_ensure_response(
			array(
				'sent'   => $after > $before,
				'to'     => $user->user_email,
				'logged' => $after - $before,
			)
		);
	}

	/**
	 * Paginated email-log query for the admin Emails activity tab.
	 *
	 * Returns rows from wp_wcb_notifications_log with optional filters. Pro can
	 * extend the result envelope via the wcb_admin_email_log_response filter.
	 *
	 * @since 1.1.1
	 *
	 * @param  \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response
	 */
	public function get_email_log( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$event_type = (string) ( $request->get_param( 'event_type' ) ?? '' );
		$status     = (string) ( $request->get_param( 'status' ) ?? '' );
		$per_page   = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );
		$page       = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$offset     = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();
		if ( '' !== $event_type ) {
			$where[]  = 'event_type = %s';
			$params[] = $event_type;
		}
		if ( '' !== $status ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}
		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}wcb_notifications_log WHERE {$where_sql}";
		$total     = $params
		? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) // phpcs:ignore WordPress.DB
		: (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB

		$list_sql    = "SELECT id, user_id, event_type, channel, payload, status, sent_at FROM {$wpdb->prefix}wcb_notifications_log WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $per_page, $offset ) );
		$rows        = (array) $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A ); // phpcs:ignore WordPress.DB

		$items = array_map(
			static function ( array $row ): array {
				$payload = json_decode( (string) $row['payload'], true );
				return array(
					'id'         => (int) $row['id'],
					'user_id'    => (int) $row['user_id'],
					'event_type' => (string) $row['event_type'],
					'channel'    => (string) $row['channel'],
					'recipient'  => is_array( $payload ) && isset( $payload['to'] ) ? (string) $payload['to'] : '',
					'subject'    => is_array( $payload ) && isset( $payload['subject'] ) ? (string) $payload['subject'] : '',
					'status'     => (string) $row['status'],
					'sent_at'    => (string) $row['sent_at'],
				);
			},
			$rows
		);

		$response = array(
			'items'    => $items,
			'total'    => $total,
			'pages'    => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
			'page'     => $page,
			'per_page' => $per_page,
		);

		/**
		 * Filter the email-log response — Pro plugins can append their own log rows
		 * (e.g. webhook deliveries, in-app notification log) into the same envelope.
		 *
		 * @since 1.1.1
		 *
		 * @param array            $response   Default response shape.
		 * @param \WP_REST_Request $request    Original request.
		 */
		$response = (array) apply_filters( 'wcb_admin_email_log_response', $response, $request );

		return rest_ensure_response( $response );
	}
}
