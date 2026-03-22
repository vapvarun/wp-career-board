<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated name matches WP convention for multi-word classes.
/**
 * WP-CLI migration commands — import from WP Job Manager.
 *
 * Usage:
 *   wp wcb migrate wpjm --dry-run
 *   wp wcb migrate wpjm
 *   wp wcb migrate wpjm --offset=100 --limit=50
 *   wp wcb migrate wpjm-resumes --dry-run
 *   wp wcb migrate wpjm-resumes
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Cli;

use WCB\Import\WpjmImporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrate job data from other job board plugins into WP Career Board.
 *
 * @since 1.0.0
 */
class MigrateCommands extends AbstractCliCommand {

	/**
	 * Migrate jobs from WP Job Manager (job_listing CPT) into WP Career Board.
	 *
	 * Reads every published `job_listing` post and creates a matching `wcb_job`.
	 * Company meta is copied as inline meta (no wcb_company CPT post created).
	 * Taxonomies are mapped: job_listing_category → wcb_category,
	 * job_listing_type → wcb_job_type.
	 *
	 * Already-migrated posts are skipped (tracked via _wcb_migrated_from meta).
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Preview what would be imported without making any changes.
	 *
	 * [--limit=<n>]
	 * : Maximum number of WPJM jobs to process. Default: all.
	 *
	 * [--offset=<n>]
	 * : Skip the first N jobs. Useful for resuming a partial migration.
	 *
	 * [--status=<status>]
	 * : Which WPJM post status to migrate.
	 * ---
	 * default: publish
	 * options:
	 *   - publish
	 *   - pending
	 *   - any
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *   wp wcb migrate wpjm --dry-run
	 *   wp wcb migrate wpjm
	 *   wp wcb migrate wpjm --offset=100 --limit=50
	 *
	 * @subcommand wpjm
	 * @since 1.0.0
	 *
	 * @param array                $args       Positional arguments (unused).
	 * @param array<string,string> $assoc_args Named arguments.
	 * @return void
	 */
	public function wpjm( array $args, array $assoc_args ): void {
		if ( ! post_type_exists( 'job_listing' ) ) {
			\WP_CLI::error( 'WP Job Manager is not active. Install and activate it before running this command.' );
		}

		$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$limit   = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', -1 );
		$offset  = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'offset', 0 );
		$status  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'status', 'publish' );

		if ( $dry_run ) {
			\WP_CLI::log( \WP_CLI::colorize( '%YDRY RUN — no data will be written.%n' ) );
		}

		$importer = new WpjmImporter();
		$total    = $importer->wpjm_jobs_total( 'any' === $status ? 'publish' : $status );

		if ( 0 === $total ) {
			\WP_CLI::success( 'No WP Job Manager jobs found to migrate.' );
			return;
		}

		\WP_CLI::log( sprintf( 'Found %d job_listing post(s) to process.', $total ) );

		if ( $dry_run ) {
			// In dry-run mode query IDs manually so we can log each one.
			$ids = get_posts(
				array(
					'post_type'      => 'job_listing',
					'post_status'    => $status,
					'posts_per_page' => $limit > 0 ? $limit : -1,
					'offset'         => $offset,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'fields'         => 'ids',
				)
			);
			foreach ( $ids as $id ) {
				$post = get_post( (int) $id );
				\WP_CLI::log( sprintf( '  [dry-run] Would import: "%s" (ID %d)', $post ? $post->post_title : '?', $id ) );
			}
			\WP_CLI::success( sprintf( 'Dry run complete. %d job(s) would be processed.', count( $ids ) ) );
			return;
		}

		$batch_size = $limit > 0 ? min( $limit, 20 ) : 20;
		$progress   = \WP_CLI\Utils\make_progress_bar( 'Migrating jobs', $total );
		$imported   = 0;
		$skipped    = 0;
		$errors     = 0;
		$current    = $offset;

		while ( true ) {
			$this_limit = $limit > 0 ? min( $batch_size, $limit - ( $current - $offset ) ) : $batch_size;
			if ( $this_limit <= 0 ) {
				break;
			}

			$result    = $importer->migrate_jobs_batch( $current, $this_limit, $status );
			$processed = $result['imported'] + $result['skipped'] + count( $result['errors'] );

			if ( 0 === $processed ) {
				break;
			}

			$imported += $result['imported'];
			$skipped  += $result['skipped'];
			$errors   += count( $result['errors'] );

			foreach ( $result['errors'] as $err ) {
				\WP_CLI::warning( $err );
			}

			for ( $i = 0; $i < $processed; $i++ ) {
				$progress->tick();
			}

			$current += $processed;

			if ( $current >= $total || ( $limit > 0 && ( $current - $offset ) >= $limit ) ) {
				break;
			}
		}

		$progress->finish();

		\WP_CLI::log( '' );
		\WP_CLI::success(
			sprintf(
				'Done. Imported: %d | Skipped (already migrated): %d | Errors: %d',
				$imported,
				$skipped,
				$errors
			)
		);
	}

	/**
	 * Migrate resumes from WP Job Manager Resumes (resume CPT) into WP Career Board.
	 *
	 * Reads every published `resume` post and creates a matching `wcb_resume`.
	 * All candidate data is preserved: professional title, photo, location,
	 * video, resume file, education history, work experience, and social links.
	 *
	 * Already-migrated posts are skipped (tracked via _wcb_migrated_from meta).
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Preview what would be imported without making any changes.
	 *
	 * [--limit=<n>]
	 * : Maximum number of resumes to process. Default: all.
	 *
	 * [--offset=<n>]
	 * : Skip the first N resumes. Useful for resuming a partial migration.
	 *
	 * [--status=<status>]
	 * : Which WPJM post status to migrate.
	 * ---
	 * default: publish
	 * options:
	 *   - publish
	 *   - pending
	 *   - any
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *   wp wcb migrate wpjm-resumes --dry-run
	 *   wp wcb migrate wpjm-resumes
	 *   wp wcb migrate wpjm-resumes --offset=50 --limit=25
	 *
	 * @subcommand wpjm-resumes
	 * @since 1.0.0
	 *
	 * @param array                $args       Positional arguments (unused).
	 * @param array<string,string> $assoc_args Named arguments.
	 * @return void
	 */
	public function wpjm_resumes( array $args, array $assoc_args ): void {
		if ( ! post_type_exists( 'resume' ) ) {
			\WP_CLI::error( 'WP Job Manager Resumes is not active. Install and activate it before running this command.' );
		}

		$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$limit   = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', -1 );
		$offset  = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'offset', 0 );
		$status  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'status', 'publish' );

		if ( $dry_run ) {
			\WP_CLI::log( \WP_CLI::colorize( '%YDRY RUN — no data will be written.%n' ) );
		}

		$importer = new WpjmImporter();
		$total    = $importer->wpjm_resumes_total( 'any' === $status ? 'publish' : $status );

		if ( 0 === $total ) {
			\WP_CLI::success( 'No WP Job Manager Resumes found to migrate.' );
			return;
		}

		\WP_CLI::log( sprintf( 'Found %d resume post(s) to process.', $total ) );

		if ( $dry_run ) {
			$ids = get_posts(
				array(
					'post_type'      => 'resume',
					'post_status'    => $status,
					'posts_per_page' => $limit > 0 ? $limit : -1,
					'offset'         => $offset,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'fields'         => 'ids',
				)
			);
			foreach ( $ids as $id ) {
				$post = get_post( (int) $id );
				\WP_CLI::log( sprintf( '  [dry-run] Would import: "%s" (ID %d)', $post ? $post->post_title : '?', $id ) );
			}
			\WP_CLI::success( sprintf( 'Dry run complete. %d resume(s) would be processed.', count( $ids ) ) );
			return;
		}

		$batch_size = $limit > 0 ? min( $limit, 20 ) : 20;
		$progress   = \WP_CLI\Utils\make_progress_bar( 'Migrating resumes', $total );
		$imported   = 0;
		$skipped    = 0;
		$errors     = 0;
		$current    = $offset;

		while ( true ) {
			$this_limit = $limit > 0 ? min( $batch_size, $limit - ( $current - $offset ) ) : $batch_size;
			if ( $this_limit <= 0 ) {
				break;
			}

			$result    = $importer->migrate_resumes_batch( $current, $this_limit, $status );
			$processed = $result['imported'] + $result['skipped'] + count( $result['errors'] );

			if ( 0 === $processed ) {
				break;
			}

			$imported += $result['imported'];
			$skipped  += $result['skipped'];
			$errors   += count( $result['errors'] );

			foreach ( $result['errors'] as $err ) {
				\WP_CLI::warning( $err );
			}

			for ( $i = 0; $i < $processed; $i++ ) {
				$progress->tick();
			}

			$current += $processed;

			if ( $current >= $total || ( $limit > 0 && ( $current - $offset ) >= $limit ) ) {
				break;
			}
		}

		$progress->finish();

		\WP_CLI::log( '' );
		\WP_CLI::success(
			sprintf(
				'Done. Imported: %d | Skipped (already migrated): %d | Errors: %d',
				$imported,
				$skipped,
				$errors
			)
		);
	}
}
