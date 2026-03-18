<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- filename follows autoloader convention (JobCommands → class-job-commands.php).
/**
 * `wp wcb job` subcommands — operational job management via WP-CLI.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Cli;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage job listings.
 *
 * ## EXAMPLES
 *
 *   wp wcb job list
 *   wp wcb job list --status=pending
 *   wp wcb job approve 42
 *   wp wcb job reject 42 --reason="Duplicate listing"
 *   wp wcb job expire 42
 *   wp wcb job run-expiry
 *
 * @since 1.0.0
 */
class JobCommands extends \WP_CLI_Command {

	/**
	 * List job listings.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Filter by post status. Accepts publish, pending, draft, wcb_expired, or any.
	 * ---
	 * default: any
	 * ---
	 *
	 * [--company=<slug>]
	 * : Filter by company post slug.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - ids
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *   wp wcb job list
	 *   wp wcb job list --status=pending
	 *   wp wcb job list --company=stripe --format=json
	 *
	 * @subcommand list
	 * @since 1.0.0
	 *
	 * @param array                $args       Positional arguments (unused).
	 * @param array<string,string> $assoc_args Named arguments.
	 * @return void
	 */
	public function list( array $args, array $assoc_args ): void {
		$status       = \WP_CLI\Utils\get_flag_value( $assoc_args, 'status', 'any' );
		$company_slug = \WP_CLI\Utils\get_flag_value( $assoc_args, 'company', '' );
		$format       = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$query_args = array(
			'post_type'      => 'wcb_job',
			'post_status'    => $status,
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $company_slug ) {
			$company = get_page_by_path( $company_slug, OBJECT, 'wcb_company' );
			if ( ! $company instanceof \WP_Post ) {
				\WP_CLI::error( "No company found with slug '{$company_slug}'." );
			}
			$query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_wcb_company_id',
					'value' => $company->ID,
					'type'  => 'NUMERIC',
				),
			);
		}

		$jobs = get_posts( $query_args );

		if ( 'ids' === $format ) {
			\WP_CLI::log( implode( ' ', wp_list_pluck( $jobs, 'ID' ) ) );
			return;
		}

		$rows = array();
		foreach ( $jobs as $job ) {
			$company_id   = (int) get_post_meta( $job->ID, '_wcb_company_id', true );
			$company_name = $company_id ? get_the_title( $company_id ) : '—';
			$deadline_raw = (string) get_post_meta( $job->ID, '_wcb_deadline', true );

			$rows[] = array(
				'ID'       => $job->ID,
				'Title'    => $job->post_title,
				'Status'   => $job->post_status,
				'Company'  => $company_name,
				'Deadline' => '' !== $deadline_raw ? $deadline_raw : '—',
				'Date'     => substr( $job->post_date, 0, 10 ),
			);
		}

		if ( empty( $rows ) ) {
			\WP_CLI::log( 'No jobs found.' );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'ID', 'Title', 'Status', 'Company', 'Deadline', 'Date' ) );
	}

	/**
	 * Approve a pending job listing.
	 *
	 * Publishes the job and fires the wcb_job_approved action (triggers email notifications).
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The job post ID to approve.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wcb job approve 42
	 *
	 * @subcommand approve
	 * @since 1.0.0
	 *
	 * @param array                $args       Positional arguments: 0 = job ID.
	 * @param array<string,string> $assoc_args Named arguments (unused).
	 * @return void
	 */
	public function approve( array $args, array $assoc_args ): void {
		$job_id = (int) ( $args[0] ?? 0 );
		if ( ! $job_id ) {
			\WP_CLI::error( 'Usage: wp wcb job approve <id>' );
		}

		$job = get_post( $job_id );
		if ( ! $job instanceof \WP_Post || 'wcb_job' !== $job->post_type ) {
			\WP_CLI::error( "No wcb_job found with ID {$job_id}." );
		}

		$result = wp_update_post(
			array(
				'ID'          => $job_id,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}

		/**
		 * Fires after a job listing is approved.
		 *
		 * @since 1.0.0
		 * @param int $job_id The approved job post ID.
		 */
		do_action( 'wcb_job_approved', $job_id );

		\WP_CLI::success( "Job #{$job_id} \"{$job->post_title}\" approved and published." );
	}

	/**
	 * Reject a pending job listing.
	 *
	 * Sets the job to draft, stores the rejection reason, and fires the
	 * wcb_job_rejected action (triggers email notifications).
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The job post ID to reject.
	 *
	 * [--reason=<reason>]
	 * : Rejection reason sent to the employer via email.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wcb job reject 42
	 *   wp wcb job reject 42 --reason="Duplicate listing"
	 *
	 * @subcommand reject
	 * @since 1.0.0
	 *
	 * @param array                $args       Positional arguments: 0 = job ID.
	 * @param array<string,string> $assoc_args Named arguments.
	 * @return void
	 */
	public function reject( array $args, array $assoc_args ): void {
		$job_id = (int) ( $args[0] ?? 0 );
		if ( ! $job_id ) {
			\WP_CLI::error( 'Usage: wp wcb job reject <id> [--reason=<reason>]' );
		}

		$job = get_post( $job_id );
		if ( ! $job instanceof \WP_Post || 'wcb_job' !== $job->post_type ) {
			\WP_CLI::error( "No wcb_job found with ID {$job_id}." );
		}

		$reason = sanitize_textarea_field( \WP_CLI\Utils\get_flag_value( $assoc_args, 'reason', '' ) );

		$result = wp_update_post(
			array(
				'ID'          => $job_id,
				'post_status' => 'draft',
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}

		update_post_meta( $job_id, '_wcb_rejection_reason', $reason );

		/**
		 * Fires after a job listing is rejected.
		 *
		 * @since 1.0.0
		 * @param int    $job_id The rejected job post ID.
		 * @param string $reason The rejection reason.
		 */
		do_action( 'wcb_job_rejected', $job_id, $reason );

		\WP_CLI::success( "Job #{$job_id} \"{$job->post_title}\" rejected (set to draft)." );
	}

	/**
	 * Force-expire a single job listing.
	 *
	 * Sets the job to the wcb_expired status and fires the wcb_job_expired action
	 * regardless of its current deadline value.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The job post ID to expire.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wcb job expire 42
	 *
	 * @subcommand expire
	 * @since 1.0.0
	 *
	 * @param array                $args       Positional arguments: 0 = job ID.
	 * @param array<string,string> $assoc_args Named arguments (unused).
	 * @return void
	 */
	public function expire( array $args, array $assoc_args ): void {
		$job_id = (int) ( $args[0] ?? 0 );
		if ( ! $job_id ) {
			\WP_CLI::error( 'Usage: wp wcb job expire <id>' );
		}

		$job = get_post( $job_id );
		if ( ! $job instanceof \WP_Post || 'wcb_job' !== $job->post_type ) {
			\WP_CLI::error( "No wcb_job found with ID {$job_id}." );
		}

		if ( 'wcb_expired' === $job->post_status ) {
			\WP_CLI::warning( "Job #{$job_id} is already expired." );
			return;
		}

		$result = wp_update_post(
			array(
				'ID'          => $job_id,
				'post_status' => 'wcb_expired',
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}

		/**
		 * Fires after a job listing is expired.
		 *
		 * @since 1.0.0
		 * @param int $job_id The expired job post ID.
		 */
		do_action( 'wcb_job_expired', $job_id );

		\WP_CLI::success( "Job #{$job_id} \"{$job->post_title}\" expired." );
	}

	/**
	 * Run the job expiry check (same as the daily cron).
	 *
	 * Expires all published jobs whose deadline has passed, identical to the
	 * automatic daily WP-Cron run. Respects the deadline_auto_close setting.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wcb job run-expiry
	 *
	 * @subcommand run-expiry
	 * @since 1.0.0
	 *
	 * @param array                $args       Positional arguments (unused).
	 * @param array<string,string> $assoc_args Named arguments (unused).
	 * @return void
	 */
	public function run_expiry( array $args, array $assoc_args ): void {
		\WP_CLI::log( 'Running job expiry check…' );

		/**
		 * Fires the wcb_check_job_expiry cron hook manually.
		 *
		 * @since 1.0.0
		 */
		do_action( 'wcb_check_job_expiry' );

		\WP_CLI::success( 'Job expiry check complete.' );
	}
}
