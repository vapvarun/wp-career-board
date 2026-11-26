<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- filename follows autoloader convention (CompanyCommands → class-company-commands.php).
/**
 * WP-CLI commands for companies.
 *
 * @package WP_Career_Board
 * @since   1.8.6
 */

declare( strict_types=1 );

namespace WCB\Cli;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `wp wcb company` commands.
 *
 * @since 1.8.6
 */
final class CompanyCommands extends AbstractCliCommand {

	/**
	 * List, add or remove a company's co-owners.
	 *
	 * Uses the same rules as the employer dashboard and the REST API: only
	 * Employer accounts, never the company's creator, at most 20 co-owners.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : What to do.
	 * ---
	 * options:
	 *   - list
	 *   - add
	 *   - remove
	 * ---
	 *
	 * <company>
	 * : Company post ID.
	 *
	 * [<user>]
	 * : User ID, email or username (add and remove).
	 *
	 * [--format=<format>]
	 * : Output format for list.
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
	 *   wp wcb company owners list 337
	 *   wp wcb company owners add 337 helen.park@example.com --user=1
	 *   wp wcb company owners remove 337 214 --user=1
	 *
	 * @subcommand owners
	 * @since 1.8.6
	 *
	 * @param array                $args       0 = action, 1 = company ID, 2 = user.
	 * @param array<string,string> $assoc_args Named arguments.
	 * @return void
	 */
	public function owners( array $args, array $assoc_args ): void {
		$this->require_ability( 'wcb/manage-settings' );

		[ $action, $company_id ] = array( (string) ( $args[0] ?? '' ), (int) ( $args[1] ?? 0 ) );
		if ( 'wcb_company' !== get_post_type( $company_id ) ) {
			\WP_CLI::error( "No wcb_company found with ID {$company_id}." );
		}

		if ( 'list' === $action ) {
			\WP_CLI\Utils\format_items(
				\WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' ),
				array_map(
					static fn ( array $owner ): array => array(
						'id'   => $owner['id'],
						'name' => $owner['name'],
						'role' => $owner['creator'] ? 'creator' : 'co-owner',
					),
					\WCB\Modules\Employers\EmployersModule::company_owners( $company_id )
				),
				array( 'id', 'name', 'role' )
			);
			return;
		}

		$who  = (string) ( $args[2] ?? '' );
		$user = ctype_digit( $who ) ? get_user_by( 'id', (int) $who ) : get_user_by( is_email( $who ) ? 'email' : 'login', $who );
		if ( ! $user instanceof \WP_User ) {
			\WP_CLI::error( "No user matches \"{$who}\"." );
		}

		if ( 'add' === $action ) {
			$added = \WCB\Modules\Employers\EmployersModule::add_company_owner( $company_id, $user->ID );
			if ( is_wp_error( $added ) ) {
				\WP_CLI::error( $added->get_error_message() );
			}
			\WP_CLI::success( "User #{$user->ID} is now a co-owner of company #{$company_id}." );
			return;
		}

		if ( (int) get_post_field( 'post_author', $company_id ) === $user->ID ) {
			\WP_CLI::error( "User #{$user->ID} created company #{$company_id} and cannot be removed from it." );
		}
		if ( ! \WCB\Modules\Employers\EmployersModule::remove_company_owner( $company_id, $user->ID ) ) {
			\WP_CLI::error( "User #{$user->ID} is not a co-owner of company #{$company_id}." );
		}
		\WP_CLI::success( "User #{$user->ID} is no longer a co-owner of company #{$company_id}." );
	}
}
