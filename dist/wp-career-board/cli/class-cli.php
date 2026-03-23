<?php
/**
 * WP-CLI root command group for WP Career Board.
 *
 * Registers `wp wcb` with the `status` and `abilities` subcommands.
 * Operational subgroups are registered separately:
 *   wp wcb job         → WCB\Cli\JobCommands
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
 *   wp wcb abilities
 *   wp wcb abilities --user-id=5
 *   wp wcb job list
 *   wp wcb job approve 42
 *   wp wcb application list --job=42
 *
 * @since 1.0.0
 */
class Cli extends AbstractCliCommand {

	/**
	 * All WCB ability slugs registered by the Free plugin.
	 *
	 * Pro adds its own abilities at runtime; those are not listed here.
	 *
	 * @since 1.0.0
	 * @var array<string,string>
	 */
	private const ABILITIES = array(
		'wcb_post_jobs'                 => 'Post Jobs',
		'wcb_manage_company'            => 'Manage Company Profile',
		'wcb_view_applications'         => 'View Applications',
		'wcb_access_employer_dashboard' => 'Access Employer Dashboard',
		'wcb_apply_jobs'                => 'Apply to Jobs',
		'wcb_manage_resume'             => 'Manage Resume',
		'wcb_bookmark_jobs'             => 'Bookmark Jobs',
		'wcb_moderate_jobs'             => 'Moderate Jobs',
		'wcb_manage_settings'           => 'Manage Settings',
		'wcb_view_analytics'            => 'View Analytics',
	);

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

	/**
	 * List all WCB abilities and whether the specified user holds them.
	 *
	 * Useful for AI agents to discover what operations are available
	 * for a given user before attempting write actions.
	 *
	 * ## OPTIONS
	 *
	 * [--user-id=<id>]
	 * : Check abilities for this user ID instead of the current WP-CLI user.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *   wp wcb abilities
	 *   wp wcb abilities --user-id=5
	 *   wp wcb abilities --user-id=5 --format=json
	 *
	 * @subcommand abilities
	 * @since 1.0.0
	 *
	 * @param array                $args       Positional arguments (unused).
	 * @param array<string,string> $assoc_args Named arguments.
	 * @return void
	 */
	public function abilities( array $args, array $assoc_args ): void {
		$user_id = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'user-id', 0 );
		$format  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$user = $user_id ? get_user_by( 'ID', $user_id ) : wp_get_current_user();

		if ( ! $user || ! $user->ID ) {
			\WP_CLI::error( 'User not found.' );
		}

		$rows = array();

		$abilities = (array) apply_filters( 'wcb_cli_abilities', self::ABILITIES );

		foreach ( $abilities as $slug => $label ) {
			$granted = function_exists( 'wp_is_ability_granted' )
				? wp_is_ability_granted( $slug, $user )
				// phpcs:ignore WordPress.WP.Capabilities.Unknown -- ability slug used as fallback cap.
				: user_can( $user, $slug );

			$rows[] = array(
				'Ability' => $slug,
				'Label'   => $label,
				'Granted' => $granted ? 'yes' : 'no',
			);
		}

		\WP_CLI::log( 'User: ' . $user->display_name . ' (ID ' . $user->ID . ', role: ' . implode( ', ', $user->roles ) . ')' );
		\WP_CLI::log( '' );

		\WP_CLI\Utils\format_items( $format, $rows, array( 'Ability', 'Label', 'Granted' ) );
	}
}
