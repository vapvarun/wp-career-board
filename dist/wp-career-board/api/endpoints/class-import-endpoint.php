<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated name matches WP convention for multi-word classes.
/**
 * Import REST endpoint — powers the admin Import page batch migration.
 *
 * Routes:
 *   GET  /wcb/v1/import/status  — counts of available and already-migrated records
 *   POST /wcb/v1/import/run     — run one batch; body: {type, offset, limit}
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Api\Endpoints;

use WCB\Api\RestController;
use WCB\Import\WpjmImporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles /wcb/v1/import/* REST routes.
 *
 * @since 1.0.0
 */
final class ImportEndpoint extends RestController {

	/**
	 * Register import routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/import/status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'admin_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/import/run',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_batch' ),
				'permission_callback' => array( $this, 'admin_check' ),
				'args'                => array(
					'type'   => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'wpjm-jobs', 'wpjm-resumes' ),
						'validate_callback' => 'rest_validate_request_arg',
					),
					'offset' => array(
						'type'              => 'integer',
						'default'           => 0,
						'minimum'           => 0,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'absint',
					),
					'limit'  => array(
						'type'              => 'integer',
						'default'           => 10,
						'minimum'           => 1,
						'maximum'           => 50,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Return counts of available and already-migrated records for each source.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response
	 */
	public function get_status( \WP_REST_Request $request ): \WP_REST_Response {
		$importer = new WpjmImporter();

		return rest_ensure_response(
			array(
				'jobs'    => array(
					'source_active' => post_type_exists( 'job_listing' ),
					'total'         => $importer->wpjm_jobs_total(),
					'migrated'      => $importer->wcb_jobs_migrated(),
				),
				'resumes' => array(
					'source_active' => post_type_exists( 'resume' ),
					'total'         => $importer->wpjm_resumes_total(),
					'migrated'      => $importer->wcb_resumes_migrated(),
				),
			)
		);
	}

	/**
	 * Run one migration batch and return progress data.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function run_batch( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$type   = (string) $request->get_param( 'type' );
		$offset = (int) $request->get_param( 'offset' );
		$limit  = (int) $request->get_param( 'limit' );

		$importer = new WpjmImporter();

		if ( 'wpjm-jobs' === $type ) {
			if ( ! post_type_exists( 'job_listing' ) ) {
				return new \WP_Error(
					'wcb_source_inactive',
					__( 'WP Job Manager is not active.', 'wp-career-board' ),
					array( 'status' => 400 )
				);
			}
			$result = $importer->migrate_jobs_batch( $offset, $limit );
			$total  = $importer->wpjm_jobs_total();
		} else {
			if ( ! post_type_exists( 'resume' ) ) {
				return new \WP_Error(
					'wcb_source_inactive',
					__( 'WP Job Manager Resumes is not active.', 'wp-career-board' ),
					array( 'status' => 400 )
				);
			}
			$result = $importer->migrate_resumes_batch( $offset, $limit );
			$total  = $importer->wpjm_resumes_total();
		}

		$processed        = $result['imported'] + $result['skipped'] + count( $result['errors'] );
		$result['total']  = $total;
		$result['offset'] = $offset;
		$result['next']   = $offset + $processed;
		$result['done']   = $result['next'] >= $total || 0 === $processed;

		return rest_ensure_response( $result );
	}

	/**
	 * Only admins (wcb_manage_settings ability) may access import routes.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function admin_check( \WP_REST_Request $request ): bool|\WP_Error {
		return $this->check_ability( 'wcb_manage_settings' ) ? true : $this->permission_error();
	}
}
