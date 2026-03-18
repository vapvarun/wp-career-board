<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- filename follows autoloader convention (ApplicationCommands → class-application-commands.php).
/**
 * `wp wcb application` subcommands — operational application management via WP-CLI.
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
 * Manage job applications.
 *
 * ## EXAMPLES
 *
 *   wp wcb application list
 *   wp wcb application list --job=42
 *   wp wcb application list --status=shortlisted
 *   wp wcb application update 7 --status=hired
 *
 * @since 1.0.0
 */
class ApplicationCommands extends \WP_CLI_Command {

	/**
	 * Valid application status values.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private const VALID_STATUSES = array( 'submitted', 'reviewing', 'shortlisted', 'hired', 'rejected' );

	/**
	 * List job applications.
	 *
	 * ## OPTIONS
	 *
	 * [--job=<id>]
	 * : Filter by job post ID.
	 *
	 * [--status=<status>]
	 * : Filter by application status (submitted, reviewing, shortlisted, hired, rejected).
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
	 *   wp wcb application list
	 *   wp wcb application list --job=42
	 *   wp wcb application list --status=shortlisted --format=json
	 *
	 * @subcommand list
	 * @since 1.0.0
	 *
	 * @param array                $args       Positional arguments (unused).
	 * @param array<string,string> $assoc_args Named arguments.
	 * @return void
	 */
	public function list( array $args, array $assoc_args ): void {
		$job_id = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'job', 0 );
		$status = \WP_CLI\Utils\get_flag_value( $assoc_args, 'status', '' );
		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$query_args = array(
			'post_type'      => 'wcb_application',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$meta_query = array();

		if ( $job_id ) {
			$meta_query[] = array(
				'key'   => '_wcb_job_id',
				'value' => $job_id,
				'type'  => 'NUMERIC',
			);
		}

		if ( $status ) {
			if ( ! in_array( $status, self::VALID_STATUSES, true ) ) {
				\WP_CLI::error( 'Invalid status. Valid values: ' . implode( ', ', self::VALID_STATUSES ) );
			}
			$meta_query[] = array(
				'key'   => '_wcb_status',
				'value' => $status,
			);
		}

		if ( $meta_query ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$applications = get_posts( $query_args );

		if ( 'ids' === $format ) {
			\WP_CLI::log( implode( ' ', wp_list_pluck( $applications, 'ID' ) ) );
			return;
		}

		$rows = array();
		foreach ( $applications as $app ) {
			$app_job_id   = (int) get_post_meta( $app->ID, '_wcb_job_id', true );
			$candidate_id = (int) get_post_meta( $app->ID, '_wcb_candidate_id', true );
			$status_raw   = (string) get_post_meta( $app->ID, '_wcb_status', true );
			$app_status   = '' !== $status_raw ? $status_raw : 'submitted';
			$job_title    = $app_job_id ? get_the_title( $app_job_id ) : '—';

			if ( $candidate_id ) {
				$user      = get_user_by( 'ID', $candidate_id );
				$applicant = $user ? $user->display_name : "User #{$candidate_id}";
				$email     = $user ? $user->user_email : '—';
			} else {
				$guest_name = (string) get_post_meta( $app->ID, '_wcb_guest_name', true );
				$guest_mail = (string) get_post_meta( $app->ID, '_wcb_guest_email', true );
				$applicant  = '' !== $guest_name ? $guest_name : '—';
				$email      = '' !== $guest_mail ? $guest_mail : '—';
			}

			$rows[] = array(
				'ID'        => $app->ID,
				'Job'       => $job_title,
				'Applicant' => $applicant,
				'Email'     => $email,
				'Status'    => $app_status,
				'Date'      => substr( $app->post_date, 0, 10 ),
			);
		}

		if ( empty( $rows ) ) {
			\WP_CLI::log( 'No applications found.' );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'ID', 'Job', 'Applicant', 'Email', 'Status', 'Date' ) );
	}

	/**
	 * Update the status of a job application.
	 *
	 * Fires the wcb_application_status_changed action after the update
	 * (triggers email notifications to the applicant).
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The application post ID to update.
	 *
	 * --status=<status>
	 * : New status value.
	 * ---
	 * options:
	 *   - submitted
	 *   - reviewing
	 *   - shortlisted
	 *   - hired
	 *   - rejected
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *   wp wcb application update 7 --status=shortlisted
	 *   wp wcb application update 7 --status=hired
	 *
	 * @subcommand update
	 * @since 1.0.0
	 *
	 * @param array                $args       Positional arguments: 0 = application ID.
	 * @param array<string,string> $assoc_args Named arguments.
	 * @return void
	 */
	public function update( array $args, array $assoc_args ): void {
		$app_id = (int) ( $args[0] ?? 0 );
		if ( ! $app_id ) {
			\WP_CLI::error( 'Usage: wp wcb application update <id> --status=<status>' );
		}

		$new_status = \WP_CLI\Utils\get_flag_value( $assoc_args, 'status', '' );
		if ( ! $new_status ) {
			\WP_CLI::error( '--status is required. Valid values: ' . implode( ', ', self::VALID_STATUSES ) );
		}

		if ( ! in_array( $new_status, self::VALID_STATUSES, true ) ) {
			\WP_CLI::error( 'Invalid status "' . $new_status . '". Valid values: ' . implode( ', ', self::VALID_STATUSES ) );
		}

		$app = get_post( $app_id );
		if ( ! $app instanceof \WP_Post || 'wcb_application' !== $app->post_type ) {
			\WP_CLI::error( "No wcb_application found with ID {$app_id}." );
		}

		$old_status_raw = (string) get_post_meta( $app_id, '_wcb_status', true );
		$old_status     = '' !== $old_status_raw ? $old_status_raw : 'submitted';

		update_post_meta( $app_id, '_wcb_status', $new_status );

		/**
		 * Fires after an application status is changed.
		 *
		 * @since 1.0.0
		 * @param int    $app_id     The application post ID.
		 * @param string $old_status Previous status.
		 * @param string $new_status New status.
		 */
		do_action( 'wcb_application_status_changed', $app_id, $old_status, $new_status );

		\WP_CLI::success( "Application #{$app_id} status updated: {$old_status} → {$new_status}." );
	}
}
