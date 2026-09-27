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
class ApplicationCommands extends AbstractCliCommand {

	/**
	 * List job applications, newest first, one page at a time.
	 *
	 * ## OPTIONS
	 *
	 * [--job=<id>]
	 * : Filter by job post ID.
	 *
	 * [--status=<status>]
	 * : Filter by application status (submitted, reviewing, shortlisted, rejected, hired, withdrawn, job_removed).
	 *
	 * [--per-page=<n>]
	 * : Rows per page, 1-500.
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--page=<n>]
	 * : Page number.
	 * ---
	 * default: 1
	 * ---
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
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *   wp wcb application list
	 *   wp wcb application list --job=42
	 *   wp wcb application list --status=shortlisted --format=json
	 *   wp wcb application list --page=2 --per-page=500
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

		$per_page = max( 1, min( 500, (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'per-page', 100 ) ) );
		$page     = max( 1, (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'page', 1 ) );

		$query_args = array(
			'post_type'      => 'wcb_application',
			'post_status'    => 'any',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$meta_query = array();

		if ( $job_id ) {
			// String compare (no NUMERIC) so the wcb_meta_key_value index is used;
			// _wcb_job_id is stored as a string, so equality is exact.
			$meta_query[] = array(
				'key'   => '_wcb_job_id',
				'value' => $job_id,
			);
		}

		if ( $status ) {
			if ( ! \WCB\Modules\Applications\ApplicationStatus::is_valid( $status ) ) {
				\WP_CLI::error( 'Invalid status. Valid values: ' . implode( ', ', \WCB\Modules\Applications\ApplicationStatus::all() ) );
			}
			$meta_query[] = array(
				'key'   => '_wcb_status',
				'value' => $status,
			);
		}

		if ( $meta_query ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$query        = new \WP_Query( $query_args );
		$applications = $query->posts;

		if ( 'count' === $format ) {
			\WP_CLI::log( (string) $query->found_posts );
			return;
		}

		if ( 'ids' === $format ) {
			\WP_CLI::log( implode( ' ', wp_list_pluck( $applications, 'ID' ) ) );
			return;
		}

		if ( $applications ) {
			update_postmeta_cache( wp_list_pluck( $applications, 'ID' ) );
		}

		$rows = array();
		foreach ( $applications as $app ) {
			$app_job_id   = (int) get_post_meta( $app->ID, '_wcb_job_id', true );
			$candidate_id = (int) get_post_meta( $app->ID, '_wcb_candidate_id', true );
			$status_raw   = (string) get_post_meta( $app->ID, '_wcb_status', true );
			$app_status   = '' !== $status_raw ? $status_raw : 'submitted';
			$job_title    = $app_job_id ? (string) get_post_field( 'post_title', $app_job_id ) : '—';

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
				'Status'    => \WCB\Modules\Applications\ApplicationStatus::label( $app_status, \WCB\Modules\Applications\ApplicationStatus::AUDIENCE_ADMIN ),
				'Date'      => substr( $app->post_date, 0, 10 ),
			);
		}

		if ( empty( $rows ) ) {
			\WP_CLI::log( 'No applications found.' );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'ID', 'Job', 'Applicant', 'Email', 'Status', 'Date' ) );

		if ( 'table' === $format && (int) $query->max_num_pages > $page ) {
			\WP_CLI::log( sprintf( 'Page %1$d of %2$d (%3$d applications). Next: --page=%4$d', $page, (int) $query->max_num_pages, (int) $query->found_posts, $page + 1 ) );
		}
	}

	/**
	 * Update the status of a job application.
	 *
	 * Goes through ApplicationLifecycle::transition(), so the change is logged
	 * and the candidate is notified once. Setting the current status again
	 * changes nothing and sends nothing.
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
		$this->require_ability( 'wcb/view-applications' );

		$app_id = (int) ( $args[0] ?? 0 );
		if ( ! $app_id ) {
			\WP_CLI::error( 'Usage: wp wcb application update <id> --status=<status>' );
		}

		$new_status = \WP_CLI\Utils\get_flag_value( $assoc_args, 'status', '' );
		$allowed    = \WCB\Modules\Applications\ApplicationStatus::employer_actionable();
		if ( ! $new_status || ! in_array( $new_status, $allowed, true ) ) {
			\WP_CLI::error( 'Invalid or missing --status. Valid values: ' . implode( ', ', $allowed ) );
		}

		$app = get_post( $app_id );
		if ( ! $app instanceof \WP_Post || 'wcb_application' !== $app->post_type ) {
			\WP_CLI::error( "No wcb_application found with ID {$app_id}." );
		}

		$old_status_raw = (string) get_post_meta( $app_id, '_wcb_status', true );
		$old_status     = '' !== $old_status_raw ? $old_status_raw : 'submitted';

		if ( ! \WCB\Modules\Applications\ApplicationLifecycle::transition( $app_id, $new_status, 'cli' ) ) {
			\WP_CLI::warning( "Application #{$app_id} is already {$new_status}. Nothing changed, nothing sent." );
			return;
		}

		\WP_CLI::success( "Application #{$app_id} status updated: {$old_status} → {$new_status}." );
	}
}
