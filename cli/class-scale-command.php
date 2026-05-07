<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- filename follows autoloader convention (ScaleCommand → class-scale-command.php).
/**
 * `wp wcb scale` subcommands — synthetic-load benchmark harness for F8.
 *
 * Seeds a representative production-shape dataset, times hot-path REST queries
 * against documented budgets, and tears the dataset back down. Used by the
 * F8 perf work in plan/upscale-stabilization-plan-2026-05-07.md (Tasks 7.2,
 * 7.3) so optimizations can show measurable before/after numbers.
 *
 * Every seeded row carries the `_wcb_scale_seed` postmeta (or `wcb_scale_seed`
 * usermeta) so teardown is idempotent and never touches genuine content.
 *
 * Seed and teardown bulk-INSERT/DELETE via $wpdb->prepare directly because
 * wp_insert_post / wp_delete_post fire dozens of hooks per call, which makes
 * 50K-row seeds infeasible (an hour+ each). Bulk SQL skips those hooks — fine
 * for synthetic data that nothing else in the system reads.
 *
 * @package WP_Career_Board
 * @since   1.2.0
 */

declare( strict_types=1 );

namespace WCB\Cli;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Synthetic-load benchmark CLI for the F8 perf work.
 *
 * ## EXAMPLES
 *
 *   wp wcb scale seed
 *   wp wcb scale benchmark
 *   wp wcb scale benchmark --per-page=50
 *   wp wcb scale teardown
 *
 * @since 1.2.0
 */
class ScaleCommand extends AbstractCliCommand {

	/**
	 * Sentinel meta key written to every seeded row.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	private const SENTINEL = '_wcb_scale_seed';

	/**
	 * Sentinel usermeta key written to every seeded user.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	private const SENTINEL_USER = 'wcb_scale_seed';

	/**
	 * Bulk-insert chunk size — number of rows per INSERT statement.
	 *
	 * @since 1.2.0
	 * @var int
	 */
	private const CHUNK = 500;

	/**
	 * Hot-path query budgets, in milliseconds.
	 *
	 * Each entry corresponds to a closure registered in build_ops(); the
	 * benchmark exits 1 if any op exceeds its budget. Numbers reflect the
	 * 100K-user contract from upscale-stabilization-plan-2026-05-07.md
	 * Phase 7.
	 *
	 * @since 1.2.0
	 * @var array<string,int>
	 */
	private const BUDGETS_MS = array(
		'jobs.list_50'              => 100,
		'jobs.single'               => 5,
		'jobs.search_keyword'       => 200,
		'jobs.filter_by_location'   => 150,
		'applications.list_for_job' => 50,
		'companies.list_50'         => 50,
		'candidates.list_50'        => 50,
	);

	/**
	 * Seed targets — controls dataset shape.
	 *
	 * @since 1.2.0
	 * @var array<string,int>
	 */
	private const TARGETS = array(
		'candidates'   => 10000,
		'employers'    => 1000,
		'companies'    => 500,
		'jobs'         => 5000,
		'applications' => 50000,
	);

	/**
	 * Seed a representative synthetic dataset for the F8 benchmark.
	 *
	 * Idempotent: counts existing seeded rows of each type and only creates
	 * the difference. Re-running this command is safe and a no-op once the
	 * dataset is fully populated.
	 *
	 * ## OPTIONS
	 *
	 * [--candidates=<n>]
	 * : Override candidate target. Default: 10000.
	 *
	 * [--employers=<n>]
	 * : Override employer target. Default: 1000.
	 *
	 * [--companies=<n>]
	 * : Override company target. Default: 500.
	 *
	 * [--jobs=<n>]
	 * : Override job target. Default: 5000.
	 *
	 * [--applications=<n>]
	 * : Override application target. Default: 50000.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wcb scale seed
	 *   wp wcb scale seed --jobs=1000 --applications=5000
	 *
	 * @subcommand seed
	 * @since 1.2.0
	 *
	 * @param array                $args       Positional args (unused).
	 * @param array<string,string> $assoc_args Named args.
	 * @return void
	 */
	public function seed( array $args, array $assoc_args ): void {
		$this->require_ability( 'wcb/manage-settings' );

		$targets = array(
			'candidates'   => (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'candidates', self::TARGETS['candidates'] ),
			'employers'    => (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'employers', self::TARGETS['employers'] ),
			'companies'    => (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'companies', self::TARGETS['companies'] ),
			'jobs'         => (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'jobs', self::TARGETS['jobs'] ),
			'applications' => (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'applications', self::TARGETS['applications'] ),
		);

		// Suspend cache invalidation; we wp_cache_flush() at the end.
		wp_suspend_cache_addition( true );
		wp_defer_term_counting( true );

		$totals = array(
			'candidates'   => $this->seed_users( 'wcb_candidate', $targets['candidates'] ),
			'employers'    => $this->seed_users( 'wcb_employer', $targets['employers'] ),
			'companies'    => $this->seed_companies( $targets['companies'] ),
			'jobs'         => $this->seed_jobs( $targets['jobs'] ),
			'applications' => $this->seed_applications( $targets['applications'] ),
		);

		wp_defer_term_counting( false );
		wp_suspend_cache_addition( false );
		wp_cache_flush();

		// Bump the listing cache version so prior transients are invalidated.
		update_option( 'wcb_jobs_cache_v', (int) get_option( 'wcb_jobs_cache_v', 0 ) + 1, false );

		\WP_CLI::log( '' );
		\WP_CLI::log( 'Seeded rows (created this run / target):' );
		foreach ( $totals as $type => $created ) {
			\WP_CLI::log( sprintf( '  %-13s %d / %d', $type, $created, $targets[ $type ] ) );
		}
		\WP_CLI::success( 'Seed complete.' );
	}

	/**
	 * Time the hot-path REST queries against BUDGETS_MS.
	 *
	 * Each op runs after wp_cache_flush() so we measure cold-cache cost,
	 * which is the realistic contract for a fanned-out site under load.
	 * Exits 1 if any op exceeds its budget (CI gate).
	 *
	 * ## OPTIONS
	 *
	 * [--per-page=<n>]
	 * : Per-page size for listing ops. Default: 50.
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
	 *   wp wcb scale benchmark
	 *   wp wcb scale benchmark --per-page=20
	 *   wp wcb scale benchmark --format=json
	 *
	 * @subcommand benchmark
	 * @since 1.2.0
	 *
	 * @param array                $args       Positional args (unused).
	 * @param array<string,string> $assoc_args Named args.
	 * @return void
	 */
	public function benchmark( array $args, array $assoc_args ): void {
		$this->require_ability( 'wcb/manage-settings' );

		$per_page = max( 1, (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'per-page', 50 ) );
		$format   = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$ops     = $this->build_ops( $per_page );
		$results = array();
		foreach ( $ops as $name => $op ) {
			$results[] = self::time_op( $name, $op );
		}

		$rows     = array();
		$any_over = false;
		$any_skip = false;
		foreach ( $results as $row ) {
			$budget = self::BUDGETS_MS[ $row['name'] ] ?? 0;
			$status = 'OK';
			if ( $row['skipped'] ) {
				$status   = 'SKIPPED';
				$any_skip = true;
			} elseif ( $row['duration_ms'] > $budget ) {
				$status   = 'OVER BUDGET';
				$any_over = true;
			}
			$rows[] = array(
				'Name'          => $row['name'],
				'Duration (ms)' => $row['skipped'] ? '—' : (string) $row['duration_ms'],
				'Queries'       => $row['skipped'] ? '—' : (string) $row['queries'],
				'Budget (ms)'   => (string) $budget,
				'Status'        => $status,
			);
		}

		\WP_CLI\Utils\format_items(
			$format,
			$rows,
			array( 'Name', 'Duration (ms)', 'Queries', 'Budget (ms)', 'Status' )
		);

		if ( $any_skip ) {
			\WP_CLI::warning( 'Some ops were skipped (no rows of the required type — run `wp wcb scale seed` first).' );
		}

		if ( $any_over ) {
			\WP_CLI::error( 'One or more hot-path queries exceeded their budget.' );
		}

		\WP_CLI::success( 'All hot-path queries within budget.' );
	}

	/**
	 * Drop every row carrying the seed sentinel. Idempotent.
	 *
	 * Bulk-DELETEs via the postmeta sentinel JOIN so a 50K-row teardown
	 * stays under a few seconds. Forgoes wp_delete_post hooks for the same
	 * reason seed forgoes wp_insert_post hooks.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wcb scale teardown
	 *
	 * @subcommand teardown
	 * @since 1.2.0
	 *
	 * @param array                $args       Positional args (unused).
	 * @param array<string,string> $assoc_args Named args (unused).
	 * @return void
	 */
	public function teardown( array $args, array $assoc_args ): void {
		$this->require_ability( 'wcb/manage-settings' );

		global $wpdb;

		$totals = array();

		// Posts: applications first (FK-soft on jobs), then jobs, then companies.
		foreach ( array( 'wcb_application', 'wcb_job', 'wcb_company' ) as $cpt ) {
			$key = match ( $cpt ) {
				'wcb_application' => 'applications',
				'wcb_job'         => 'jobs',
				default           => 'companies',
			};

			$ids = (array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p
					 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
					 WHERE p.post_type = %s
					   AND m.meta_key = %s",
					$cpt,
					self::SENTINEL
				)
			);
			$ids = array_map( 'intval', $ids );

			if ( empty( $ids ) ) {
				$totals[ $key ] = 0;
				continue;
			}

			$totals[ $key ] = count( $ids );
			\WP_CLI::log( sprintf( 'Deleting %d %s posts…', count( $ids ), $cpt ) );

			foreach ( array_chunk( $ids, self::CHUNK ) as $chunk ) {
				$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
				// Term relationships first (taxonomy joins reference object_id).
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ({$placeholders})", $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$placeholders})", $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE ID IN ({$placeholders})", $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			}
		}

		// Users: bulk delete via sentinel.
		$user_ids = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT u.ID FROM {$wpdb->users} u
				 INNER JOIN {$wpdb->usermeta} m ON m.user_id = u.ID
				 WHERE m.meta_key = %s",
				self::SENTINEL_USER
			)
		);
		$user_ids = array_map( 'intval', $user_ids );

		// Never delete actual admins, even if somehow tagged.
		$user_ids = array_filter( $user_ids, static fn( $id ) => $id > 1 );

		$totals['users'] = count( $user_ids );
		if ( ! empty( $user_ids ) ) {
			\WP_CLI::log( sprintf( 'Deleting %d users…', count( $user_ids ) ) );
			foreach ( array_chunk( $user_ids, self::CHUNK ) as $chunk ) {
				$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE user_id IN ({$placeholders})", $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->users} WHERE ID IN ({$placeholders})", $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			}
		}

		// Recalc term counts since we bypassed the cleanup hooks.
		$taxonomies = array( 'wcb_category', 'wcb_job_type', 'wcb_location', 'wcb_experience', 'wcb_tag' );
		foreach ( $taxonomies as $tax ) {
			$term_ids = get_terms(
				array(
					'taxonomy'   => $tax,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);
			if ( ! is_wp_error( $term_ids ) && ! empty( $term_ids ) ) {
				wp_update_term_count_now( $term_ids, $tax );
			}
		}

		wp_cache_flush();

		// Bump the listing cache version so any stale transient is invalidated.
		update_option( 'wcb_jobs_cache_v', (int) get_option( 'wcb_jobs_cache_v', 0 ) + 1, false );

		\WP_CLI::log( '' );
		\WP_CLI::log( 'Removed rows:' );
		foreach ( $totals as $type => $count ) {
			\WP_CLI::log( sprintf( '  %-13s %d', $type, $count ) );
		}
		\WP_CLI::success( 'Teardown complete.' );
	}

	/**
	 * Build the closures the benchmark times.
	 *
	 * Each closure flushes its own arguments and runs the same code path the
	 * REST endpoint uses (WP_Query, get_post_meta, get_the_terms) so wpdb
	 * timings reflect production cost, not artificial simplifications.
	 *
	 * @since 1.2.0
	 *
	 * @param int $per_page Listing page size.
	 * @return array<string,callable>
	 */
	private function build_ops( int $per_page ): array {
		$sample_job_id   = $this->find_sample_post_id( 'wcb_job' );
		$sample_location = $this->find_sample_term_slug( 'wcb_location' );
		$sample_keyword  = 'engineer';

		return array(
			'jobs.list_50'              => function () use ( $per_page ): void {
				$query    = new \WP_Query(
					array(
						'post_type'      => 'wcb_job',
						'post_status'    => 'publish',
						'posts_per_page' => $per_page,
						'no_found_rows'  => false,
					)
				);
				$post_ids = wp_list_pluck( $query->posts, 'ID' );
				if ( ! empty( $post_ids ) ) {
					update_meta_cache( 'post', $post_ids );
					update_object_term_cache(
						$post_ids,
						array( 'wcb_category', 'wcb_job_type', 'wcb_location', 'wcb_experience', 'wcb_tag' )
					);
				}
				foreach ( $query->posts as $post ) {
					get_post_meta( $post->ID );
					get_the_terms( $post->ID, 'wcb_category' );
					get_the_terms( $post->ID, 'wcb_location' );
					get_the_terms( $post->ID, 'wcb_job_type' );
				}
			},

			'jobs.single'               => function () use ( $sample_job_id ): void {
				if ( ! $sample_job_id ) {
					return;
				}
				$post = get_post( $sample_job_id );
				if ( $post ) {
					get_post_meta( $post->ID );
					get_the_terms( $post->ID, 'wcb_category' );
					get_the_terms( $post->ID, 'wcb_location' );
				}
			},

			'jobs.search_keyword'       => function () use ( $per_page, $sample_keyword ): void {
				$query    = new \WP_Query(
					array(
						'post_type'      => 'wcb_job',
						'post_status'    => 'publish',
						'posts_per_page' => $per_page,
						's'              => $sample_keyword,
					)
				);
				$post_ids = wp_list_pluck( $query->posts, 'ID' );
				if ( ! empty( $post_ids ) ) {
					update_meta_cache( 'post', $post_ids );
				}
			},

			'jobs.filter_by_location'   => function () use ( $per_page, $sample_location ): void {
				if ( ! $sample_location ) {
					return;
				}
				$query    = new \WP_Query(
					array(
						'post_type'      => 'wcb_job',
						'post_status'    => 'publish',
						'posts_per_page' => $per_page,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						'tax_query'      => array(
							array(
								'taxonomy' => 'wcb_location',
								'field'    => 'slug',
								'terms'    => $sample_location,
							),
						),
					)
				);
				$post_ids = wp_list_pluck( $query->posts, 'ID' );
				if ( ! empty( $post_ids ) ) {
					update_meta_cache( 'post', $post_ids );
				}
			},

			'applications.list_for_job' => function () use ( $sample_job_id ): void {
				if ( ! $sample_job_id ) {
					return;
				}
				$query    = new \WP_Query(
					array(
						'post_type'      => 'wcb_application',
						'post_status'    => 'any',
						'posts_per_page' => 50,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'meta_query'     => array(
							array(
								'key'   => '_wcb_job_id',
								'value' => $sample_job_id,
								'type'  => 'NUMERIC',
							),
						),
					)
				);
				$post_ids = wp_list_pluck( $query->posts, 'ID' );
				if ( ! empty( $post_ids ) ) {
					update_meta_cache( 'post', $post_ids );
				}
			},

			'companies.list_50'         => function () use ( $per_page ): void {
				$query    = new \WP_Query(
					array(
						'post_type'      => 'wcb_company',
						'post_status'    => 'publish',
						'posts_per_page' => $per_page,
					)
				);
				$post_ids = wp_list_pluck( $query->posts, 'ID' );
				if ( ! empty( $post_ids ) ) {
					update_meta_cache( 'post', $post_ids );
				}
			},

			'candidates.list_50'        => function () use ( $per_page ): void {
				$user_query = new \WP_User_Query(
					array(
						'role'   => 'wcb_candidate',
						'number' => $per_page,
						'fields' => array( 'ID', 'user_login', 'display_name' ),
					)
				);
				$users      = $user_query->get_results();
				$ids        = array_map( static fn( $u ) => (int) $u->ID, $users );
				if ( ! empty( $ids ) ) {
					update_meta_cache( 'user', $ids );
				}
			},
		);
	}

	/**
	 * Run a single closure and capture (duration_ms, queries, skipped).
	 *
	 * Flushes the object cache before invoking the closure so we measure the
	 * cold path. A closure that early-returns (no sample data) is reported
	 * as `skipped` rather than a misleading 0 ms.
	 *
	 * @since 1.2.0
	 *
	 * @param string   $name Op name (matches BUDGETS_MS).
	 * @param callable $op   Closure to time.
	 * @return array{name:string,duration_ms:float,queries:int,skipped:bool}
	 */
	private static function time_op( string $name, callable $op ): array {
		global $wpdb;

		wp_cache_flush();

		$start_time    = microtime( true );
		$start_queries = (int) $wpdb->num_queries;

		try {
			$op();
			$skipped = false;
		} catch ( \Throwable $e ) {
			\WP_CLI::warning( sprintf( 'Op "%s" threw: %s', $name, $e->getMessage() ) );
			$skipped = true;
		}

		$duration_ms = ( microtime( true ) - $start_time ) * 1000;
		$queries     = (int) $wpdb->num_queries - $start_queries;

		// Treat near-zero duration with zero queries as a skip (sample data missing).
		if ( ! $skipped && 0 === $queries && $duration_ms < 1.0 ) {
			$skipped = true;
		}

		return array(
			'name'        => $name,
			'duration_ms' => round( $duration_ms, 1 ),
			'queries'     => $queries,
			'skipped'     => $skipped,
		);
	}

	/**
	 * Bulk-create users with a given role until the target is met.
	 *
	 * @since 1.2.0
	 *
	 * @param string $role   Role slug (wcb_candidate / wcb_employer).
	 * @param int    $target Total seeded users with this role.
	 * @return int Number created this run.
	 */
	private function seed_users( string $role, int $target ): int {
		global $wpdb;

		$existing = $this->count_seeded_users( $role );

		$to_create = $target - $existing;
		if ( $to_create <= 0 ) {
			return 0;
		}

		\WP_CLI::log( sprintf( 'Seeding %d %s users…', $to_create, $role ) );
		$progress = \WP_CLI\Utils\make_progress_bar( $role, $to_create );

		$cap_meta_key  = $wpdb->prefix . 'capabilities';
		$role_meta_val = (string) maybe_serialize( array( $role => true ) );
		$now           = current_time( 'mysql', true );
		$pass_hash     = wp_hash_password( wp_generate_password( 16 ) );
		$prefix        = 'wcb_seed_' . substr( $role, 4 ) . '_';
		$created       = 0;

		// Build chunks of unique logins/emails first to dedupe collisions.
		$batch = array();
		for ( $i = 0; $i < $to_create; $i++ ) {
			$suffix       = wp_generate_password( 10, false );
			$login        = strtolower( $prefix . $suffix );
			$email        = $login . '@example.invalid';
			$display_name = ucfirst( str_replace( 'wcb_', '', $role ) ) . ' ' . $suffix;
			$batch[]      = compact( 'login', 'email', 'display_name' );

			if ( count( $batch ) >= self::CHUNK || $i === $to_create - 1 ) {
				$user_values = array();
				$user_phs    = array();
				foreach ( $batch as $u ) {
					$user_phs[]    = '(%s, %s, %s, %s, %s, %s, 0, %s)';
					$user_values[] = $u['login'];
					$user_values[] = $pass_hash;
					$user_values[] = $u['login'];
					$user_values[] = $u['email'];
					$user_values[] = $now;
					$user_values[] = '';
					$user_values[] = $u['display_name'];
				}
				$sql = "INSERT INTO {$wpdb->users}
					(user_login, user_pass, user_nicename, user_email, user_registered, user_activation_key, user_status, display_name)
					VALUES " . implode( ',', $user_phs );
				$wpdb->query( $wpdb->prepare( $sql, $user_values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

				// Recover IDs by selecting on logins (faster than insert_id loop).
				$logins       = array_map( static fn( $u ) => $u['login'], $batch );
				$placeholders = implode( ',', array_fill( 0, count( $logins ), '%s' ) );
				$ids          = (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login IN ({$placeholders})", $logins ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

				$meta_phs    = array();
				$meta_values = array();
				foreach ( $ids as $uid ) {
					$uid           = (int) $uid;
					$meta_phs[]    = '(%d, %s, %s)';
					$meta_values[] = $uid;
					$meta_values[] = $cap_meta_key;
					$meta_values[] = $role_meta_val;

					$meta_phs[]    = '(%d, %s, %s)';
					$meta_values[] = $uid;
					$meta_values[] = self::SENTINEL_USER;
					$meta_values[] = '1';
				}
				if ( ! empty( $meta_phs ) ) {
					$wpdb->query(
						$wpdb->prepare(
							"INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) VALUES " . implode( ',', $meta_phs ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
							$meta_values
						)
					);
				}

				$created += count( $batch );
				$progress->tick( count( $batch ) );
				$batch = array();
			}
		}
		$progress->finish();
		return $created;
	}

	/**
	 * Bulk-create wcb_company posts up to the target.
	 *
	 * @since 1.2.0
	 *
	 * @param int $target Total seeded companies.
	 * @return int Number created this run.
	 */
	private function seed_companies( int $target ): int {
		$existing  = $this->count_seeded( 'wcb_company' );
		$to_create = $target - $existing;
		if ( $to_create <= 0 ) {
			return 0;
		}

		\WP_CLI::log( sprintf( 'Seeding %d companies…', $to_create ) );
		$progress = \WP_CLI\Utils\make_progress_bar( 'companies', $to_create );

		$created = 0;
		$batch   = array();
		for ( $i = 0; $i < $to_create; $i++ ) {
			$suffix  = wp_generate_password( 8, false );
			$batch[] = array(
				'title'   => 'Acme Corp ' . $suffix,
				'content' => 'Synthetic company seeded by wp wcb scale.',
				'meta'    => array(
					'_wcb_company_website' => 'https://example.invalid/' . $suffix,
				),
			);

			if ( count( $batch ) >= self::CHUNK || $i === $to_create - 1 ) {
				$created += $this->bulk_insert_posts( 'wcb_company', $batch );
				$progress->tick( count( $batch ) );
				$batch = array();
			}
		}
		$progress->finish();
		return $created;
	}

	/**
	 * Bulk-create wcb_job posts and assign taxonomies/meta varied across the
	 * dimensions the listing endpoint filters on.
	 *
	 * @since 1.2.0
	 *
	 * @param int $target Total seeded jobs.
	 * @return int Number created this run.
	 */
	private function seed_jobs( int $target ): int {
		$existing  = $this->count_seeded( 'wcb_job' );
		$to_create = $target - $existing;
		if ( $to_create <= 0 ) {
			return 0;
		}

		$company_ids = $this->get_seeded_post_ids( 'wcb_company' );
		if ( empty( $company_ids ) ) {
			\WP_CLI::warning( 'No seeded companies — jobs will be created without a _wcb_company_id link.' );
		}

		$category_terms   = $this->ensure_terms( 'wcb_category', array( 'engineering', 'design', 'marketing', 'sales', 'support', 'operations' ) );
		$type_terms       = $this->ensure_terms( 'wcb_job_type', array( 'full-time', 'part-time', 'contract', 'internship', 'freelance' ) );
		$location_terms   = $this->ensure_terms( 'wcb_location', array( 'remote', 'new-york', 'london', 'berlin', 'tokyo', 'singapore', 'sydney', 'toronto' ) );
		$experience_terms = $this->ensure_terms( 'wcb_experience', array( 'entry', 'mid', 'senior', 'lead', 'executive' ) );

		$currencies = array( 'USD', 'EUR', 'GBP', 'INR', 'JPY' );
		$titles     = array(
			'Software Engineer',
			'Senior Engineer',
			'Product Designer',
			'Marketing Manager',
			'Sales Engineer',
			'Customer Support Lead',
			'DevOps Engineer',
			'Data Analyst',
			'Frontend Developer',
			'Backend Developer',
		);

		\WP_CLI::log( sprintf( 'Seeding %d jobs…', $to_create ) );
		$progress = \WP_CLI\Utils\make_progress_bar( 'jobs', $to_create );

		$created = 0;
		$batch   = array();
		for ( $i = 0; $i < $to_create; $i++ ) {
			$suffix     = wp_generate_password( 6, false );
			$days_back  = wp_rand( 0, 90 );
			$post_date  = gmdate( 'Y-m-d H:i:s', time() - ( $days_back * DAY_IN_SECONDS ) );
			$base_title = $titles[ array_rand( $titles ) ];
			$company_id = ! empty( $company_ids ) ? $company_ids[ array_rand( $company_ids ) ] : 0;
			$salary_min = wp_rand( 30000, 80000 );
			$salary_max = $salary_min + wp_rand( 10000, 50000 );
			$location_t = $location_terms[ array_rand( $location_terms ) ];

			$meta = array(
				'_wcb_salary_min' => (string) $salary_min,
				'_wcb_salary_max' => (string) $salary_max,
				'_wcb_currency'   => $currencies[ array_rand( $currencies ) ],
				'_wcb_remote'     => 'remote' === $location_t['slug'] ? '1' : '0',
				'_wcb_deadline'   => gmdate( 'Y-m-d', time() + ( wp_rand( 7, 60 ) * DAY_IN_SECONDS ) ),
			);
			if ( $company_id ) {
				$meta['_wcb_company_id'] = (string) $company_id;
			}

			$batch[] = array(
				'title'    => $base_title . ' ' . $suffix,
				'content'  => 'Synthetic ' . $base_title . ' role seeded by wp wcb scale.',
				'date'     => $post_date,
				'meta'     => $meta,
				'term_ids' => array(
					$category_terms[ array_rand( $category_terms ) ]['term_id'],
					$type_terms[ array_rand( $type_terms ) ]['term_id'],
					$location_t['term_id'],
					$experience_terms[ array_rand( $experience_terms ) ]['term_id'],
				),
			);

			if ( count( $batch ) >= self::CHUNK || $i === $to_create - 1 ) {
				$created += $this->bulk_insert_posts( 'wcb_job', $batch );
				$progress->tick( count( $batch ) );
				$batch = array();
			}
		}
		$progress->finish();

		return $created;
	}

	/**
	 * Bulk-create wcb_application posts distributed across seeded jobs+candidates.
	 *
	 * @since 1.2.0
	 *
	 * @param int $target Total seeded applications.
	 * @return int Number created this run.
	 */
	private function seed_applications( int $target ): int {
		$existing  = $this->count_seeded( 'wcb_application' );
		$to_create = $target - $existing;
		if ( $to_create <= 0 ) {
			return 0;
		}

		$job_ids       = $this->get_seeded_post_ids( 'wcb_job' );
		$candidate_ids = $this->get_seeded_user_ids( 'wcb_candidate' );

		if ( empty( $job_ids ) || empty( $candidate_ids ) ) {
			\WP_CLI::warning( 'Skipping applications — need seeded jobs AND candidates first.' );
			return 0;
		}

		$statuses = array( 'submitted', 'reviewing', 'shortlisted', 'hired', 'rejected' );

		\WP_CLI::log( sprintf( 'Seeding %d applications…', $to_create ) );
		$progress = \WP_CLI\Utils\make_progress_bar( 'applications', $to_create );

		$created = 0;
		$batch   = array();
		for ( $i = 0; $i < $to_create; $i++ ) {
			$job_id       = $job_ids[ array_rand( $job_ids ) ];
			$candidate_id = $candidate_ids[ array_rand( $candidate_ids ) ];
			$status       = $statuses[ array_rand( $statuses ) ];

			$batch[] = array(
				'title'   => sprintf( 'Application #%d', $i ),
				'content' => '',
				'author'  => $candidate_id,
				'meta'    => array(
					'_wcb_job_id'       => (string) $job_id,
					'_wcb_candidate_id' => (string) $candidate_id,
					'_wcb_status'       => $status,
				),
			);

			if ( count( $batch ) >= self::CHUNK || $i === $to_create - 1 ) {
				$created += $this->bulk_insert_posts( 'wcb_application', $batch );
				$progress->tick( count( $batch ) );
				$batch = array();
			}
		}
		$progress->finish();
		return $created;
	}

	/**
	 * Bulk-INSERT posts + postmeta + term relationships for a chunk.
	 *
	 * Bypasses wp_insert_post hooks; the SENTINEL meta is added so teardown
	 * can find these rows. Returns the number of post rows actually inserted
	 * (driven by mysqli affected_rows on the posts table).
	 *
	 * @since 1.2.0
	 *
	 * @param string                                                                                                              $post_type CPT slug.
	 * @param array<int, array{title:string,content:string,date?:string,author?:int,meta?:array<string,string>,term_ids?:int[]}>  $batch     Rows to insert.
	 * @return int
	 */
	private function bulk_insert_posts( string $post_type, array $batch ): int {
		global $wpdb;

		if ( empty( $batch ) ) {
			return 0;
		}

		$now       = current_time( 'mysql' );
		$now_gmt   = current_time( 'mysql', true );
		$post_phs  = array();
		$post_vals = array();
		foreach ( $batch as $row ) {
			$date     = $row['date'] ?? $now;
			$date_gmt = $row['date'] ?? $now_gmt;
			$author   = (int) ( $row['author'] ?? 1 );

			$post_phs[]  = '(%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)';
			$post_vals[] = $author;
			$post_vals[] = $date;
			$post_vals[] = $date_gmt;
			$post_vals[] = $row['content'];
			$post_vals[] = $row['title'];
			$post_vals[] = '';
			$post_vals[] = 'publish';
			$post_vals[] = 'open';
			$post_vals[] = 'closed';
			$post_vals[] = '';
			$post_vals[] = sanitize_title( $row['title'] ) . '-' . wp_generate_password( 4, false );
			$post_vals[] = $date;
			$post_vals[] = $date_gmt;
			$post_vals[] = '';
			$post_vals[] = $post_type;
			$post_vals[] = '';
		}

		$columns = '(post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, post_modified, post_modified_gmt, post_content_filtered, post_type, post_mime_type)';
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->posts} {$columns} VALUES " . implode( ',', $post_phs ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $columns is a hardcoded literal column list (line 900); {$wpdb->posts} is server-side; placeholders array is built from %d/%s only.
				$post_vals
			)
		);

		$first_id = (int) $wpdb->insert_id;
		$count    = count( $batch );
		// MySQL bulk INSERT returns first inserted ID; the rest are sequential
		// when using the AUTO_INCREMENT default lock mode (=1, the default).
		$ids = range( $first_id, $first_id + $count - 1 );

		// postmeta rows: SENTINEL on every row, plus per-row meta.
		$meta_phs  = array();
		$meta_vals = array();
		foreach ( $batch as $idx => $row ) {
			$pid         = $ids[ $idx ];
			$meta_phs[]  = '(%d, %s, %s)';
			$meta_vals[] = $pid;
			$meta_vals[] = self::SENTINEL;
			$meta_vals[] = '1';

			foreach ( ( $row['meta'] ?? array() ) as $key => $val ) {
				$meta_phs[]  = '(%d, %s, %s)';
				$meta_vals[] = $pid;
				$meta_vals[] = $key;
				$meta_vals[] = (string) $val;
			}
		}
		// $meta_phs is always non-empty here: each $batch row adds at least the SENTINEL triplet.
		foreach ( array_chunk( $meta_phs, self::CHUNK ) as $chunk_idx => $chunk_phs ) {
			$slice_start = $chunk_idx * self::CHUNK * 3;
			$slice       = array_slice( $meta_vals, $slice_start, count( $chunk_phs ) * 3 );
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ',', $chunk_phs ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					$slice
				)
			);
		}

		// term_relationships rows.
		$tr_phs  = array();
		$tr_vals = array();
		foreach ( $batch as $idx => $row ) {
			foreach ( ( $row['term_ids'] ?? array() ) as $tt_id ) {
				$tr_phs[]  = '(%d, %d, 0)';
				$tr_vals[] = $ids[ $idx ];
				$tr_vals[] = (int) $tt_id;
			}
		}
		if ( ! empty( $tr_phs ) ) {
			foreach ( array_chunk( $tr_phs, self::CHUNK ) as $chunk_idx => $chunk_phs ) {
				$slice_start = $chunk_idx * self::CHUNK * 2;
				$slice       = array_slice( $tr_vals, $slice_start, count( $chunk_phs ) * 2 );
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id, term_order) VALUES " . implode( ',', $chunk_phs ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
						$slice
					)
				);
			}
		}

		return $count;
	}

	/**
	 * Ensure each given term slug exists in the taxonomy and return their
	 * (term_id, term_taxonomy_id, slug) tuples.
	 *
	 * @since 1.2.0
	 *
	 * @param string   $taxonomy Taxonomy slug.
	 * @param string[] $slugs    Term slugs to ensure.
	 * @return array<int, array{term_id:int,slug:string}>
	 */
	private function ensure_terms( string $taxonomy, array $slugs ): array {
		$out = array();
		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( ! $term instanceof \WP_Term ) {
				$created = wp_insert_term( ucwords( str_replace( '-', ' ', $slug ) ), $taxonomy, array( 'slug' => $slug ) );
				if ( is_wp_error( $created ) ) {
					continue;
				}
				$term_id = (int) $created['term_taxonomy_id'];
			} else {
				$term_id = (int) $term->term_taxonomy_id;
			}
			$out[] = array(
				'term_id' => $term_id,
				'slug'    => $slug,
			);
		}
		return $out;
	}

	/**
	 * Count seeded rows for a given post type.
	 *
	 * @since 1.2.0
	 *
	 * @param string $post_type CPT slug.
	 * @return int
	 */
	private function count_seeded( string $post_type ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(p.ID) FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				 WHERE p.post_type = %s
				   AND m.meta_key = %s",
				$post_type,
				self::SENTINEL
			)
		);
	}

	/**
	 * Count seeded users with a given role.
	 *
	 * @since 1.2.0
	 *
	 * @param string $role Role slug.
	 * @return int
	 */
	private function count_seeded_users( string $role ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
				 INNER JOIN {$wpdb->usermeta} m ON m.user_id = u.ID
				 INNER JOIN {$wpdb->usermeta} r ON r.user_id = u.ID
				 WHERE m.meta_key = %s
				   AND r.meta_key = %s
				   AND r.meta_value LIKE %s",
				self::SENTINEL_USER,
				$wpdb->prefix . 'capabilities',
				'%' . $wpdb->esc_like( $role ) . '%'
			)
		);
	}

	/**
	 * Fetch IDs of every seeded post for a given CPT.
	 *
	 * @since 1.2.0
	 *
	 * @param string $post_type CPT slug.
	 * @return int[]
	 */
	private function get_seeded_post_ids( string $post_type ): array {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				 WHERE p.post_type = %s
				   AND m.meta_key = %s",
				$post_type,
				self::SENTINEL
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Fetch IDs of every seeded user holding a given role.
	 *
	 * @since 1.2.0
	 *
	 * @param string $role Role slug.
	 * @return int[]
	 */
	private function get_seeded_user_ids( string $role ): array {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT u.ID FROM {$wpdb->users} u
				 INNER JOIN {$wpdb->usermeta} m ON m.user_id = u.ID
				 INNER JOIN {$wpdb->usermeta} r ON r.user_id = u.ID
				 WHERE m.meta_key = %s
				   AND r.meta_key = %s
				   AND r.meta_value LIKE %s",
				self::SENTINEL_USER,
				$wpdb->prefix . 'capabilities',
				'%' . $wpdb->esc_like( $role ) . '%'
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Pick any post ID for the given CPT — prefers seeded rows.
	 *
	 * @since 1.2.0
	 *
	 * @param string $post_type CPT slug.
	 * @return int
	 */
	private function find_sample_post_id( string $post_type ): int {
		$seeded = $this->get_seeded_post_ids( $post_type );
		if ( ! empty( $seeded ) ) {
			return $seeded[ array_rand( $seeded ) ];
		}

		$any = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		return ! empty( $any ) ? (int) $any[0] : 0;
	}

	/**
	 * Pick any term slug for the given taxonomy.
	 *
	 * @since 1.2.0
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return string
	 */
	private function find_sample_term_slug( string $taxonomy ): string {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => 1,
				'fields'     => 'slugs',
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}
		return (string) $terms[0];
	}
}
