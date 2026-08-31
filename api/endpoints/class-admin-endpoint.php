<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated name matches WP convention for multi-word classes.
/**
 * Admin REST endpoint — handles admin UI actions (e.g. dismissing notices, email log).
 *
 * Routes:
 *   POST /wcb/v1/admin/dismiss-banner  — mark a banner as dismissed for the current user
 *   POST /wcb/v1/admin/emails/test     — fire a test-send for the named email template
 *   GET  /wcb/v1/admin/emails/log      — paginated log query with filter args
 *   GET  /wcb/v1/admin/industries      — registry + per-slug company counts
 *   POST /wcb/v1/admin/industries      — save the registry, with removal instructions
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

		register_rest_route(
			$this->namespace,
			'/admin/industries',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_industries' ),
					'permission_callback' => array( $this, 'admin_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_industries' ),
					'permission_callback' => array( $this, 'admin_check' ),
					'args'                => array(
						'industries' => array(
							'required'    => true,
							'type'        => 'array',
							'description' => __( 'Ordered slug/label pairs to persist as the registry.', 'wp-career-board' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'slug'  => array( 'type' => 'string' ),
									'label' => array( 'type' => 'string' ),
								),
							),
						),
						'removals'   => array(
							'type'        => 'array',
							'default'     => array(),
							'description' => __( 'What to do with companies still storing a removed slug.', 'wp-career-board' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'slug'   => array( 'type' => 'string' ),
									'action' => array(
										'type' => 'string',
										'enum' => array( 'reassign', 'clear' ),
									),
									'target' => array( 'type' => 'string' ),
								),
							),
						),
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
		return $this->check_ability( 'wcb/manage-settings' ) ? true : $this->permission_error();
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

		$sample_job = '<li style="margin-bottom:10px;padding:12px 16px;background:#f9fafb;border-radius:6px;"><a href="' . esc_url( home_url( '/' ) ) . '" style="font-weight:600;color:#4f46e5;text-decoration:none;">%s</a></li>';
		$job_list   = '<ul style="padding-left:0;list-style:none;margin:0 0 24px;">'
			. sprintf( $sample_job, esc_html__( 'Sample Job One (test send)', 'wp-career-board' ) )
			. sprintf( $sample_job, esc_html__( 'Sample Job Two (test send)', 'wp-career-board' ) )
			. '</ul>';

		$test_vars = array(
			'job_title'      => __( 'Sample Job Title (test send)', 'wp-career-board' ),
			'company_name'   => get_bloginfo( 'name' ),
			'candidate'      => $user->display_name,
			'candidate_name' => $user->display_name,
			'guest_name'     => $user->display_name,
			'employer'       => $user->display_name,
			'status_label'   => __( 'Shortlisted', 'wp-career-board' ),
			'new_status'     => __( 'Shortlisted', 'wp-career-board' ),
			'days_left'      => 3,
			'deadline_iso'   => gmdate( 'Y-m-d', time() + 3 * DAY_IN_SECONDS ),
			'deadline_date'  => date_i18n( (string) get_option( 'date_format', 'F j, Y' ), time() + 3 * DAY_IN_SECONDS ),
			'job_url'        => home_url( '/' ),
			'dashboard_url'  => home_url( '/' ),
			'repost_url'     => home_url( '/' ),
			'approve_url'    => admin_url( 'edit.php?post_type=wcb_job' ),
			'topup_url'      => home_url( '/' ),
			'reason'         => __( 'Listing did not meet the posting guidelines (test send).', 'wp-career-board' ),
			'job_list'       => $job_list,
			'count'          => 2,
			'credits_added'  => 50,
			'new_balance'    => 120,
			'balance'        => 5,
			'is_test'        => true,
		);

		// AbstractEmail::test_send() is the public bridge: it bypasses
		// is_enabled() so disabled templates still render in the admin
		// preview, and always writes a log row so the row-delta check
		// detects the dispatch even when wp_mail() returns false.
		global $wpdb;
		$before = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wcb_notifications_log WHERE event_type = %s",
				$target->get_id()
			)
		);

		$delivered = $target->test_send( $user->user_email, $test_vars, $user_id );

		$after = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wcb_notifications_log WHERE event_type = %s",
				$target->get_id()
			)
		);

		return rest_ensure_response(
			array(
				'sent'   => $delivered,
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

	/**
	 * GET /admin/industries — the registry plus how many companies use each slug.
	 *
	 * `orphans` carries slugs that are stored on companies but absent from the
	 * registry: legacy free-text values, or imports from another job board.
	 * They are surfaced so the owner can clean them up rather than discovering
	 * them as raw slugs on a company profile.
	 *
	 * @since 1.7.1
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_industries( \WP_REST_Request $request ): \WP_REST_Response {
		unset( $request );
		return rest_ensure_response( $this->industries_payload() );
	}

	/**
	 * POST /admin/industries — persist the registry and settle removed slugs.
	 *
	 * A slug dropped from the list while companies still store it is refused
	 * with `wcb_industry_in_use` unless the request says what to do with those
	 * companies. That keeps the "reassign or clear" decision on the server, so
	 * the data can never be orphaned by a client that skipped the prompt.
	 *
	 * @since 1.7.1
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_industries( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$submitted = (array) $request->get_param( 'industries' );
		$map       = array();
		foreach ( $submitted as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug  = sanitize_key( (string) ( $row['slug'] ?? '' ) );
			$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			if ( '' === $slug || '' === $label ) {
				continue;
			}
			$map[ $slug ] = $label;
		}

		if ( array() === $map ) {
			return new \WP_Error(
				'wcb_industries_empty',
				__( 'Keep at least one industry - company forms need something to offer.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		// Removal instructions, keyed by the slug they settle.
		$instructions = array();
		foreach ( (array) $request->get_param( 'removals' ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug = sanitize_key( (string) ( $row['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}
			$instructions[ $slug ] = array(
				'action' => 'reassign' === ( $row['action'] ?? '' ) ? 'reassign' : 'clear',
				'target' => sanitize_key( (string) ( $row['target'] ?? '' ) ),
			);
		}

		$counts = \WCB\Core\Industries::usage_counts();
		$before = \WCB\Core\Industries::registry();

		// Slugs the owner is retiring: in the registry before this save, gone
		// from it now. Orphans are deliberately NOT in this set — they were
		// never offered, so they are not "being removed" and must not block an
		// unrelated save such as a label rename. An orphan is only acted on
		// when the owner sends an explicit instruction for it.
		$leaving = array_values( array_diff( array_keys( $before ), array_keys( $map ) ) );

		// An instruction for a slug the owner kept is stale — ignore it.
		$instructions = array_diff_key( $instructions, $map );

		$settling = array_values( array_unique( array_merge( $leaving, array_keys( $instructions ) ) ) );

		$unsettled = array();
		foreach ( $leaving as $slug ) {
			$in_use = (int) ( $counts[ $slug ] ?? 0 );
			if ( 0 === $in_use ) {
				continue;
			}
			$plan = $instructions[ $slug ] ?? null;
			if ( null === $plan ) {
				$unsettled[] = array(
					'slug'  => $slug,
					'label' => \WCB\Core\Industries::label( $slug ),
					'count' => $in_use,
				);
				continue;
			}
			if ( 'reassign' === $plan['action'] && ! isset( $map[ $plan['target'] ] ) ) {
				return new \WP_Error(
					'wcb_industry_bad_target',
					/* translators: %s: industry slug. */
					sprintf( __( 'Cannot move companies to "%s" - it is not in the saved list.', 'wp-career-board' ), $plan['target'] ),
					array( 'status' => 400 )
				);
			}
		}

		if ( array() !== $unsettled ) {
			return new \WP_Error(
				'wcb_industry_in_use',
				__( 'Some industries are still in use. Choose what happens to those companies before saving.', 'wp-career-board' ),
				array(
					'status' => 400,
					'in_use' => $unsettled,
				)
			);
		}

		// Save first: reassignment writes the replacement slug, and the
		// `_wcb_industry` write guard validates against the saved registry.
		\WCB\Core\Industries::save( $map );

		$moved = 0;
		foreach ( $settling as $slug ) {
			$plan = $instructions[ $slug ] ?? null;
			if ( null === $plan ) {
				continue;
			}
			$moved += \WCB\Core\Industries::reassign(
				$slug,
				'reassign' === $plan['action'] ? $plan['target'] : ''
			);
		}

		$payload          = $this->industries_payload();
		$payload['moved'] = $moved;
		$payload['saved'] = true;

		return rest_ensure_response( $payload );
	}

	/**
	 * Registry + counts + orphaned stored slugs, in one shape.
	 *
	 * @since 1.7.1
	 * @return array<string,mixed>
	 */
	private function industries_payload(): array {
		$counts   = \WCB\Core\Industries::usage_counts();
		$registry = \WCB\Core\Industries::registry();

		$industries = array();
		foreach ( $registry as $slug => $label ) {
			$industries[] = array(
				'slug'  => $slug,
				'label' => $label,
				'count' => (int) ( $counts[ $slug ] ?? 0 ),
			);
		}

		$orphans = array();
		foreach ( $counts as $slug => $total ) {
			if ( isset( $registry[ $slug ] ) ) {
				continue;
			}
			$orphans[] = array(
				'slug'  => (string) $slug,
				'label' => \WCB\Core\Industries::label( (string) $slug ),
				'count' => (int) $total,
			);
		}

		return array(
			'industries' => $industries,
			'orphans'    => $orphans,
		);
	}
}
