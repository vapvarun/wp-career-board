<?php
/**
 * WP-CLI root command group for WP Career Board.
 *
 * Registers `wp wcb` with the `status` subcommand.
 * Operational subgroups are registered separately:
 *   wp wcb job        → WCB\Cli\JobCommands
 *   wp wcb application → WCB\Cli\ApplicationCommands
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
 * Manage WP Career Board content and operations.
 *
 * ## EXAMPLES
 *
 *   wp wcb status
 *   wp wcb job list
 *   wp wcb job approve 42
 *   wp wcb application list --job=42
 *
 * @since 1.0.0
 */
class Cli extends \WP_CLI_Command {

	/**
	 * Show a summary of WP Career Board content counts.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wcb status
	 *
	 * @subcommand status
	 * @since 1.0.0
	 *
	 * @param array                $args       Positional arguments (unused).
	 * @param array<string,string> $assoc_args Named arguments (unused).
	 * @return void
	 */
	public function status( array $args, array $assoc_args ): void {
		$post_types = array(
			'wcb_job'         => 'Jobs',
			'wcb_company'     => 'Companies',
			'wcb_application' => 'Applications',
		);

		if ( post_type_exists( 'wcb_resume' ) ) {
			$post_types['wcb_resume'] = 'Resumes (Pro)';
		}

		$rows = array();

		foreach ( $post_types as $cpt => $label ) {
			$counts = (array) wp_count_posts( $cpt );
			$rows[] = array(
				'Type'      => $label,
				'Published' => (int) ( $counts['publish'] ?? 0 ),
				'Pending'   => (int) ( $counts['pending'] ?? 0 ),
				'Draft'     => (int) ( $counts['draft'] ?? 0 ),
				'Expired'   => (int) ( $counts['wcb_expired'] ?? 0 ),
				'Trash'     => (int) ( $counts['trash'] ?? 0 ),
			);
		}

		$employers  = (int) ( new \WP_User_Query(
			array(
				'role'   => 'wcb_employer',
				'number' => -1,
				'fields' => 'ID',
			)
		) )->get_total();
		$candidates = (int) ( new \WP_User_Query(
			array(
				'role'   => 'wcb_candidate',
				'number' => -1,
				'fields' => 'ID',
			)
		) )->get_total();

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'Type', 'Published', 'Pending', 'Draft', 'Expired', 'Trash' ) );

		\WP_CLI::log( '' );
		\WP_CLI::log( 'Users:' );
		\WP_CLI::log( '  Employers  (wcb_employer):  ' . $employers );
		\WP_CLI::log( '  Candidates (wcb_candidate): ' . $candidates );
	}
}
