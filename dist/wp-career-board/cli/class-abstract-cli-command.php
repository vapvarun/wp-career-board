<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- filename follows autoloader convention (AbstractCliCommand → class-abstract-cli-command.php).
/**
 * Abstract WP-CLI command base for all WCB command groups.
 *
 * Provides ability-check helpers that mirror RestController::check_ability(),
 * so the same Abilities API gates apply whether an action is invoked via
 * REST or WP-CLI (e.g. by an AI agent via `--user`).
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
 * Base class for all `wp wcb` command groups.
 *
 * @since 1.0.0
 */
abstract class AbstractCliCommand extends \WP_CLI_Command {

	/**
	 * Check an ability via the WordPress Abilities API with cap fallback.
	 *
	 * Mirrors WCB\Api\RestController::check_ability() so the same
	 * permission model applies over REST and WP-CLI.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ability Ability slug (e.g. wcb_moderate_jobs).
	 * @param array  $args    Optional context args (e.g. ['board_id' => 3]).
	 * @return bool
	 */
	protected function check_ability( string $ability, array $args = array() ): bool {
		if ( function_exists( 'wp_is_ability_granted' ) ) {
			return wp_is_ability_granted( $ability, wp_get_current_user(), $args );
		}

		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- ability slug used as fallback cap.
		return current_user_can( $ability );
	}

	/**
	 * Require an ability or abort with WP_CLI::error().
	 *
	 * @since 1.0.0
	 *
	 * @param string $ability Ability slug.
	 * @param array  $args    Optional context args.
	 * @return void
	 */
	protected function require_ability( string $ability, array $args = array() ): void {
		if ( ! $this->check_ability( $ability, $args ) ) {
			\WP_CLI::error(
				sprintf(
					'Permission denied. The current user does not have the "%s" ability.',
					$ability
				)
			);
		}
	}
}
