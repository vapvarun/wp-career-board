<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated name matches WP convention for multi-word classes.
/**
 * WP Job Manager → WP Career Board migration engine.
 *
 * Shared by both WP-CLI commands and the admin Import page REST endpoint.
 * No data is ever deleted from WPJM — migration is always additive.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Import;

use WCB\Modules\Applications\ApplicationLifecycle;
use WCB\Modules\Applications\ApplicationStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrates WP Job Manager jobs and resumes into WP Career Board CPTs.
 *
 * @since 1.0.0
 */
class WpjmImporter {

	/**
	 * WPJM pay unit => WP Career Board salary type.
	 *
	 * @var array<string, string>
	 */
	private const SALARY_UNITS = array(
		'HOUR'  => 'hourly',
		'MONTH' => 'monthly',
		'YEAR'  => 'yearly',
	);

	/**
	 * WPJM application status => WP Career Board application status.
	 *
	 * @var array<string, string>
	 */
	private const APPLICATION_STATUSES = array(
		'new'         => 'submitted',
		'interviewed' => 'shortlisted',
		'offer'       => 'shortlisted',
		'hired'       => 'hired',
		'rejected'    => 'rejected',
		'archived'    => 'rejected',
	);

	/**
	 * WPJM job statuses the import brings across by default. Expired is listed
	 * on purpose: WPJM registers it excluded from search, so neither 'any' nor a
	 * default query would ever return it.
	 *
	 * @var string[]
	 */
	public const JOB_STATUSES = array( 'publish', 'expired' );

	/**
	 * Every post status. 'any' skips statuses excluded from search, and our
	 * Closed and Expired jobs are: an already-imported closed job then read
	 * as not imported (re-imported on every run) and its applications as
	 * orphans.
	 *
	 * @return string[]
	 */
	private static function all_statuses(): array {
		return array_keys( get_post_stati() );
	}

	/**
	 * WPJM statuses a job import reads: none named = the default list, 'any' =
	 * the default list plus jobs awaiting approval, else just the one named.
	 *
	 * @param string $status Status, 'any' or '' for the default.
	 * @return string[]
	 */
	public static function job_statuses( string $status = '' ): array {
		return match ( $status ) {
			''      => self::JOB_STATUSES,
			'any'   => array( 'publish', 'pending', 'expired' ),
			default => array( $status ),
		};
	}

	/**
	 * Status for an imported job: a filled job is closed, an expired one is
	 * expired, a live one stays live, anything else waits for review.
	 *
	 * @param \WP_Post $source WPJM job.
	 * @return string
	 */
	private static function job_status( \WP_Post $source ): string {
		if ( 'expired' === $source->post_status ) {
			return 'wcb_expired';
		}
		if ( 'publish' !== $source->post_status ) {
			return 'pending';
		}
		return get_post_meta( $source->ID, '_filled', true ) ? 'wcb_closed' : 'publish';
	}

	/**
	 * The WP Career Board company for a WPJM job: an existing one with the
	 * same website or name, else a new company page with WPJM's details.
	 *
	 * @param \WP_Post $source WPJM job.
	 * @return int Company post ID, or 0 when the job names no company.
	 */
	private function company_for( \WP_Post $source ): int {
		$name = trim( (string) get_post_meta( $source->ID, '_company_name', true ) );
		if ( '' === $name ) {
			return 0;
		}
		$website  = esc_url_raw( (string) get_post_meta( $source->ID, '_company_website', true ) );
		$existing = '' !== $website ? get_posts(
			array(
				'post_type'      => 'wcb_company',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'meta_key'       => '_wcb_website', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $website, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		) : array();
		if ( ! $existing ) {
			$existing = get_posts(
				array(
					'post_type'      => 'wcb_company',
					'post_status'    => 'any',
					'fields'         => 'ids',
					'posts_per_page' => 1,
					'title'          => $name,
				)
			);
		}
		$company_id = (int) ( $existing[0] ?? 0 );

		// Matched by name alone: adopt the website WPJM knows (structured data uses it).
		if ( $company_id && '' !== $website && '' === (string) get_post_meta( $company_id, '_wcb_website', true ) ) {
			update_post_meta( $company_id, '_wcb_website', $website );
		}

		if ( ! $company_id ) {
			$company_id = (int) wp_insert_post(
				array(
					'post_type'   => 'wcb_company',
					'post_status' => 'publish',
					'post_title'  => $name,
					'post_author' => $this->resolve_author( $source ),
					'meta_input'  => array_filter(
						array(
							'_wcb_website'           => $website,
							'_wcb_tagline'           => sanitize_text_field( (string) get_post_meta( $source->ID, '_company_tagline', true ) ),
							'_wcb_twitter'           => sanitize_text_field( (string) get_post_meta( $source->ID, '_company_twitter', true ) ),
							'_wcb_migrated_source'   => 'wp-job-manager',
						)
					),
				)
			);
			$logo = get_post_meta( $source->ID, '_company_logo', true );
			$logo = is_numeric( $logo ) ? (int) $logo : attachment_url_to_postid( (string) $logo );
			if ( $company_id && $logo > 0 ) {
				set_post_thumbnail( $company_id, $logo );
			}
		}

		// An employer without a company adopts the one their jobs name.
		$author = (int) $source->post_author;
		if ( $company_id && $author > 0 && ! get_user_meta( $author, '_wcb_company_id', true ) ) {
			update_user_meta( $author, '_wcb_company_id', $company_id );
		}
		return $company_id;
	}

	/**
	 * What an import would do, without writing anything (the admin preview).
	 *
	 * @return array{jobs:int, filled:int, companies_new:int, applications:int}
	 */
	public function preview(): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- admin preview, read only.
		$filled = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_filled' AND m.meta_value = '1' WHERE p.post_type = 'job_listing' AND p.post_status = 'publish'" );
		$in     = implode( ', ', array_fill( 0, count( self::JOB_STATUSES ), '%s' ) );
		$names  = (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT TRIM(m.meta_value) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_company_name' WHERE p.post_type = 'job_listing' AND p.post_status IN ( {$in} ) AND m.meta_value <> '' LIMIT 5000", self::JOB_STATUSES ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders built from the status constant.
		$have   = (array) $wpdb->get_col( "SELECT post_title FROM {$wpdb->posts} WHERE post_type = 'wcb_company' AND post_status NOT IN ( 'trash', 'auto-draft' )" );
		// phpcs:enable
		return array(
			'jobs'          => max( 0, $this->wpjm_jobs_total() - $this->wcb_jobs_migrated() ),
			'filled'        => $filled,
			'companies_new' => count( array_diff( array_map( 'strtolower', $names ), array_map( 'strtolower', $have ) ) ),
			'applications'  => max( 0, $this->applications_total() - $this->wcb_applications_migrated() ),
		);
	}

	// ── Jobs ─────────────────────────────────────────────────────────────────

	/**
	 * Total WPJM job_listing posts with the given status.
	 *
	 * @since 1.0.0
	 * @since 1.8.0 The default is publish plus expired; see job_statuses().
	 *
	 * @param string $status Post status, 'any', or '' for the default list.
	 * @return int
	 */
	public function wpjm_jobs_total( string $status = '' ): int {
		if ( ! post_type_exists( 'job_listing' ) ) {
			return 0;
		}
		$q = new \WP_Query(
			array(
				'post_type'              => 'job_listing',
				'post_status'            => self::job_statuses( $status ),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return (int) $q->found_posts;
	}

	/**
	 * Number of wcb_job posts that already carry a _wcb_migrated_from meta.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function wcb_jobs_migrated(): int {
		$q = new \WP_Query(
			array(
				'post_type'              => 'wcb_job',
				'post_status'            => self::all_statuses(),
				'meta_key'               => '_wcb_migrated_source',
				'meta_value'             => 'wp-job-manager',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return (int) $q->found_posts;
	}

	/**
	 * Migrate one batch of WPJM jobs.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $offset Number of jobs to skip.
	 * @param int    $limit  Maximum jobs to process in this batch.
	 * @param string $status WPJM post status, 'any', or '' for the default list.
	 * @return array{imported:int, skipped:int, errors:string[]}
	 */
	public function migrate_jobs_batch( int $offset, int $limit, string $status = '' ): array {
		$ids = get_posts(
			array(
				'post_type'      => 'job_listing',
				'post_status'    => self::job_statuses( $status ),
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		$result = array(
			'imported' => 0,
			'skipped'  => 0,
			'errors'   => array(),
		);

		foreach ( $ids as $source_id ) {
			$r = $this->migrate_single_job( (int) $source_id );
			if ( 'imported' === $r ) {
				++$result['imported'];
			} elseif ( 'skipped' === $r ) {
				++$result['skipped'];
			} else {
				$result['errors'][] = sprintf( 'ID %d: %s', $source_id, $r );
			}
		}

		return $result;
	}

	/**
	 * Migrate one WPJM job_listing post to wcb_job.
	 *
	 * @since 1.0.0
	 *
	 * @param int $source_id WPJM post ID.
	 * @return string 'imported' | 'skipped' | error message.
	 */
	private function migrate_single_job( int $source_id ): string {
		// Skip already-migrated.
		$existing = get_posts(
			array(
				'post_type'      => 'wcb_job',
				'meta_key'       => '_wcb_migrated_from',
				'meta_value'     => (string) $source_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'post_status'    => self::all_statuses(),
			)
		);
		if ( ! empty( $existing ) ) {
			return 'skipped';
		}

		$source = get_post( $source_id );
		if ( ! $source ) {
			return 'source post not found';
		}

		$new_id = wp_insert_post(
			array(
				'post_type'     => 'wcb_job',
				'post_title'    => $source->post_title,
				'post_content'  => $source->post_content,
				'post_excerpt'  => $source->post_excerpt,
				'post_status'   => self::job_status( $source ),
				'post_author'   => (int) $source->post_author,
				'post_date'     => $source->post_date,
				'post_date_gmt' => $source->post_date_gmt,
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			return $new_id->get_error_message();
		}

		// ── Meta mapping ─────────────────────────────────────────────────────
		$meta_map = array(
			'_job_salary'          => '_wcb_salary_min',
			'_job_salary_currency' => '_wcb_salary_currency',
			'_job_expires'         => '_wcb_deadline',
			'_featured'            => '_wcb_featured',
			'_remote_position'     => '_wcb_remote',
			'_company_name'        => '_wcb_company_name',
		);

		foreach ( $meta_map as $wpjm_key => $wcb_key ) {
			$value = get_post_meta( $source_id, $wpjm_key, true );
			if ( '' !== $value && false !== $value ) {
				update_post_meta( $new_id, $wcb_key, $value );
			}
		}

		// WPJM pay units are HOUR / DAY / WEEK / MONTH / YEAR; ours are hourly,
		// monthly, yearly. Units we have no match for are left unset (yearly
		// display) rather than guessed.
		$unit = self::SALARY_UNITS[ strtoupper( (string) get_post_meta( $source_id, '_job_salary_unit', true ) ) ] ?? '';
		if ( '' !== $unit ) {
			update_post_meta( $new_id, '_wcb_salary_type', $unit );
		}

		// Set location as taxonomy term (not postmeta).
		$wpjm_location = (string) get_post_meta( $source_id, '_job_location', true );
		if ( $wpjm_location ) {
			wp_set_object_terms( $new_id, $wpjm_location, 'wcb_location' );
		}

		// salary_max: WPJM stores one salary value — copy to both min and max.
		$salary = get_post_meta( $source_id, '_job_salary', true );
		if ( '' !== $salary ) {
			update_post_meta( $new_id, '_wcb_salary_max', $salary );
		}

		// Deadline fallback: derive from _job_duration (days) when _job_expires is absent.
		if ( '' === get_post_meta( $source_id, '_job_expires', true ) ) {
			$duration = (int) get_post_meta( $source_id, '_job_duration', true );
			if ( $duration > 0 ) {
				$deadline = gmdate( 'Y-m-d', strtotime( $source->post_date ) + ( $duration * DAY_IN_SECONDS ) );
				update_post_meta( $new_id, '_wcb_deadline', $deadline );
			}
		}

		// Application destination: email or URL.
		$application = get_post_meta( $source_id, '_application', true );
		if ( '' !== $application ) {
			if ( is_email( $application ) ) {
				update_post_meta( $new_id, '_wcb_apply_email', $application );
			} else {
				update_post_meta( $new_id, '_wcb_apply_url', $application );
			}
		}

		// Company page: find or create, and link the job to it.
		$company_id = $this->company_for( $source );
		if ( $company_id > 0 ) {
			update_post_meta( $new_id, '_wcb_company_id', $company_id );
		}

		// Provenance tracking.
		update_post_meta( $new_id, '_wcb_migrated_from', $source_id );
		update_post_meta( $new_id, '_wcb_migrated_source', 'wp-job-manager' );

		// ── Taxonomies ────────────────────────────────────────────────────────
		$this->migrate_taxonomy( $source_id, $new_id, 'job_listing_category', 'wcb_category' );
		$this->migrate_taxonomy( $source_id, $new_id, 'job_listing_type', 'wcb_job_type' );
		$this->migrate_taxonomy( $source_id, $new_id, 'job_listing_tag', 'wcb_tag' );

		/**
		 * Fires after a job has been imported from another job board.
		 *
		 * Fired last, once meta and taxonomies are written, so consumers see a
		 * complete job. Pro binds this to geocode the location (Maps) and to
		 * match the job against saved alerts (Alerts) — both stayed silent for
		 * WPJM migrations until this fired here.
		 *
		 * @since 1.7.1
		 *
		 * @param int $new_id Imported wcb_job post ID.
		 */
		do_action( 'wcb_job_imported', $new_id );

		return 'imported';
	}

	// ── Resumes ───────────────────────────────────────────────────────────────

	/**
	 * Total WPJM resume posts with the given status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Post status (default 'publish').
	 * @return int
	 */
	public function wpjm_resumes_total( string $status = 'publish' ): int {
		if ( ! post_type_exists( 'resume' ) ) {
			return 0;
		}
		$q = new \WP_Query(
			array(
				'post_type'              => 'resume',
				'post_status'            => $status,
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return (int) $q->found_posts;
	}

	/**
	 * Number of wcb_resume posts already migrated from WPJM Resumes.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function wcb_resumes_migrated(): int {
		$q = new \WP_Query(
			array(
				'post_type'              => 'wcb_resume',
				'post_status'            => 'any',
				'meta_key'               => '_wcb_migrated_source',
				'meta_value'             => 'wp-job-manager-resumes',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return (int) $q->found_posts;
	}

	/**
	 * Migrate one batch of WPJM resumes.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $offset Number of resumes to skip.
	 * @param int    $limit  Maximum resumes to process in this batch.
	 * @param string $status WPJM post status to query.
	 * @return array{imported:int, skipped:int, errors:string[]}
	 */
	public function migrate_resumes_batch( int $offset, int $limit, string $status = 'publish' ): array {
		$ids = get_posts(
			array(
				'post_type'      => 'resume',
				'post_status'    => $status,
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		$result = array(
			'imported' => 0,
			'skipped'  => 0,
			'errors'   => array(),
		);

		foreach ( $ids as $source_id ) {
			$r = $this->migrate_single_resume( (int) $source_id );
			if ( 'imported' === $r ) {
				++$result['imported'];
			} elseif ( 'skipped' === $r ) {
				++$result['skipped'];
			} else {
				$result['errors'][] = sprintf( 'ID %d: %s', $source_id, $r );
			}
		}

		return $result;
	}

	/**
	 * Migrate one WPJM resume post to wcb_resume.
	 *
	 * All fields are preserved with zero data loss:
	 * - Scalar meta → prefixed _wcb_* meta keys on wcb_resume
	 * - Serialized arrays (education, experience, links) → preserved as-is
	 * - Photo URL → stored as _wcb_photo_url (attachment ID not available)
	 * - Resume file → _wcb_resume_file (preserves array when multiple files)
	 * - Categories → _wcb_resume_categories (term names, for future taxonomy)
	 *
	 * @since 1.0.0
	 *
	 * @param int $source_id WPJM resume post ID.
	 * @return string 'imported' | 'skipped' | error message.
	 */
	private function migrate_single_resume( int $source_id ): string {
		// Skip already-migrated.
		$existing = get_posts(
			array(
				'post_type'      => 'wcb_resume',
				'meta_key'       => '_wcb_migrated_from',
				'meta_value'     => (string) $source_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'post_status'    => 'any',
			)
		);
		if ( ! empty( $existing ) ) {
			return 'skipped';
		}

		$source = get_post( $source_id );
		if ( ! $source ) {
			return 'source post not found';
		}

		$new_id = wp_insert_post(
			array(
				'post_type'     => 'wcb_resume',
				'post_title'    => $source->post_title,
				'post_content'  => $source->post_content,
				'post_excerpt'  => $source->post_excerpt,
				'post_status'   => 'publish' === $source->post_status ? 'publish' : 'pending',
				'post_author'   => $this->resolve_author( $source ),
				'post_date'     => $source->post_date,
				'post_date_gmt' => $source->post_date_gmt,
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			return $new_id->get_error_message();
		}

		// ── Scalar meta mapping ───────────────────────────────────────────────
		$meta_map = array(
			'_candidate_title'    => '_wcb_candidate_title',
			'_candidate_email'    => '_wcb_contact_email',
			'_candidate_location' => '_wcb_location',
			'_candidate_video'    => '_wcb_video',
			'_featured'           => '_wcb_featured',
			'_resume_expires'     => '_wcb_expires',
		);

		foreach ( $meta_map as $wpjm_key => $wcb_key ) {
			$value = get_post_meta( $source_id, $wpjm_key, true );
			if ( '' !== $value && false !== $value ) {
				update_post_meta( $new_id, $wcb_key, $value );
			}
		}

		// Photo: stored as URL in WPJM (not an attachment ID), preserve as-is.
		$photo = get_post_meta( $source_id, '_candidate_photo', true );
		if ( '' !== $photo ) {
			update_post_meta( $new_id, '_wcb_photo_url', $photo );
		}

		// Resume file: can be a string URL or array of URLs.
		$resume_file = get_post_meta( $source_id, '_resume_file', true );
		if ( ! empty( $resume_file ) ) {
			update_post_meta( $new_id, '_wcb_resume_file', $resume_file );
		}

		// ── Serialized arrays (education, experience, social links) ───────────
		foreach ( array( '_candidate_education', '_candidate_experience', '_links' ) as $array_key ) {
			$data = get_post_meta( $source_id, $array_key, true );
			if ( ! empty( $data ) && is_array( $data ) ) {
				$wcb_key_map = array(
					'_candidate_education'  => '_wcb_education',
					'_candidate_experience' => '_wcb_experience',
					'_links'                => '_wcb_links',
				);
				update_post_meta( $new_id, $wcb_key_map[ $array_key ], $data );
			}
		}

		// ── Resume categories → stored as term name array for future use ──────
		if ( taxonomy_exists( 'resume_category' ) ) {
			$terms = get_the_terms( $source_id, 'resume_category' );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$term_names = wp_list_pluck( $terms, 'name' );
				update_post_meta( $new_id, '_wcb_resume_categories', $term_names );
			}
		}

		// ── Provenance tracking ───────────────────────────────────────────────
		update_post_meta( $new_id, '_wcb_migrated_from', $source_id );
		update_post_meta( $new_id, '_wcb_migrated_source', 'wp-job-manager-resumes' );

		return 'imported';
	}

	// ── Shared helpers ────────────────────────────────────────────────────────

	// ── Applications (WP Job Manager Applications add-on) ─────────────────────

	/**
	 * WPJM applications waiting to be imported (all statuses).
	 *
	 * @since 1.8.0
	 * @return int
	 */
	public function applications_total(): int {
		if ( ! post_type_exists( 'job_application' ) ) {
			return 0;
		}
		$counts = wp_count_posts( 'job_application' );
		return (int) array_sum( array_map( 'intval', (array) $counts ) ) - (int) ( $counts->trash ?? 0 ) - (int) ( $counts->{'auto-draft'} ?? 0 );
	}

	/**
	 * Applications already imported from WPJM.
	 *
	 * @since 1.8.0
	 * @return int
	 */
	public function wcb_applications_migrated(): int {
		$query = new \WP_Query(
			array(
				'post_type'      => 'wcb_application',
				'post_status'    => self::all_statuses(),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_wcb_migrated_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'wp-job-manager-applications', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Import one batch of WPJM applications (import jobs first).
	 *
	 * @since 1.8.0
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Batch size.
	 * @return array{imported:int, skipped:int, errors:array<int,string>}
	 */
	public function migrate_applications_batch( int $offset, int $limit ): array {
		$result = array(
			'imported' => 0,
			'skipped'  => 0,
			'errors'   => array(),
		);
		$ids    = get_posts(
			array(
				'post_type'      => 'job_application',
				'post_status'    => 'any',
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			$outcome = $this->migrate_single_application( (int) $id );
			if ( 'imported' === $outcome || 'skipped' === $outcome ) {
				++$result[ $outcome ];
			} else {
				$result['errors'][] = sprintf( '#%d: %s', (int) $id, $outcome );
			}
		}
		return $result;
	}

	/**
	 * Import one WPJM application onto its imported job, without emails.
	 *
	 * @since 1.8.0
	 *
	 * @param int $source_id WPJM job_application post ID.
	 * @return string 'imported' | 'skipped' | error message.
	 */
	private function migrate_single_application( int $source_id ): string {
		$already = get_posts(
			array(
				'post_type'      => 'wcb_application',
				'post_status'    => self::all_statuses(),
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'meta_key'       => '_wcb_migrated_from', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => (string) $source_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( $already ) {
			return 'skipped';
		}
		$source = get_post( $source_id );
		if ( ! $source instanceof \WP_Post ) {
			return 'source post not found';
		}
		$job = get_posts(
			array(
				'post_type'      => 'wcb_job',
				'post_status'    => self::all_statuses(),
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'meta_key'       => '_wcb_migrated_from', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => (string) $source->post_parent, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$job_id = (int) ( $job[0] ?? 0 );
		if ( ! $job_id ) {
			return __( 'its job has not been imported yet; import the jobs first', 'wp-career-board' );
		}

		$email   = sanitize_email( (string) get_post_meta( $source_id, '_candidate_email', true ) );
		$user_id = (int) get_post_meta( $source_id, '_candidate_user_id', true );
		if ( ! $user_id && '' !== $email ) {
			$user    = get_user_by( 'email', $email );
			$user_id = $user instanceof \WP_User ? (int) $user->ID : 0;
		}
		$name   = sanitize_text_field( $source->post_title );
		$letter = (string) $source->post_content;
		// Files stay where WPJM stored them; list them in the application.
		foreach ( array_filter( (array) get_post_meta( $source_id, '_attachment', true ) ) as $file ) {
			$letter .= "\n\n" . esc_url_raw( (string) $file );
		}

		$app_id = wp_insert_post(
			array(
				'post_type'   => 'wcb_application',
				'post_status' => 'publish',
				'post_title'  => sprintf(
					/* translators: %s: candidate name. */
					__( 'Application: %s', 'wp-career-board' ),
					$name
				),
				'post_author' => $user_id,
				'post_date'   => $source->post_date,
			),
			true
		);
		if ( is_wp_error( $app_id ) ) {
			return $app_id->get_error_message();
		}
		update_post_meta( $app_id, '_wcb_job_id', $job_id );
		update_post_meta( $app_id, '_wcb_candidate_id', $user_id );
		update_post_meta( $app_id, '_wcb_status', self::APPLICATION_STATUSES[ $source->post_status ] ?? 'submitted' );
		update_post_meta( $app_id, '_wcb_cover_letter', sanitize_textarea_field( $letter ) );
		update_post_meta( $app_id, '_wcb_job_title_snapshot', get_the_title( $job_id ) );
		update_post_meta( $app_id, '_wcb_company_name_snapshot', (string) get_post_meta( $job_id, '_wcb_company_name', true ) );
		if ( ! $user_id ) {
			update_post_meta( $app_id, '_wcb_guest_name', $name );
			update_post_meta( $app_id, '_wcb_guest_email', $email );
		}
		update_post_meta( $app_id, '_wcb_migrated_from', $source_id );
		update_post_meta( $app_id, '_wcb_migrated_source', 'wp-job-manager-applications' );
		// The close rule (undecided applicants on a closed job become Position
		// closed) queues on the job's import, so it may run before or after this
		// application exists. Apply it here, silently, so the outcome does not
		// depend on cron timing and nobody is emailed about an old application.
		if ( 'wcb_closed' === get_post_status( $job_id ) && ! in_array( (string) get_post_meta( $app_id, '_wcb_status', true ), ApplicationStatus::terminal(), true ) ) {
			ApplicationLifecycle::transition( (int) $app_id, ApplicationStatus::POSITION_CLOSED, 'job_closed', 0, '', false );
		}
		return 'imported';
	}

	/**
	 * Copy terms from a WPJM taxonomy to the matching WCB taxonomy.
	 * Terms that don't exist in the target taxonomy are created automatically.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $source_id  WPJM post ID.
	 * @param int    $new_id     New WCB post ID.
	 * @param string $source_tax WPJM taxonomy slug.
	 * @param string $target_tax WCB taxonomy slug.
	 * @return void
	 */
	public function migrate_taxonomy( int $source_id, int $new_id, string $source_tax, string $target_tax ): void {
		$terms = get_the_terms( $source_id, $source_tax );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		$target_term_ids = array();

		foreach ( $terms as $term ) {
			$existing = get_term_by( 'slug', $term->slug, $target_tax );

			if ( $existing ) {
				$target_term_ids[] = $existing->term_id;
			} else {
				$new_term = wp_insert_term( $term->name, $target_tax, array( 'slug' => $term->slug ) );
				if ( ! is_wp_error( $new_term ) ) {
					$target_term_ids[] = $new_term['term_id'];
				}
			}
		}

		if ( ! empty( $target_term_ids ) ) {
			wp_set_object_terms( $new_id, $target_term_ids, $target_tax );
		}
	}

	/**
	 * Resolve a valid post_author for a migrated post.
	 *
	 * If the source post_author is 0 (guest submission), try to match by
	 * _candidate_email meta to find an existing WP user. Falls back to the
	 * current logged-in user (the admin running the migration).
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $source Source WPJM post.
	 * @return int Valid user ID (never 0).
	 */
	private function resolve_author( \WP_Post $source ): int {
		if ( $source->post_author > 0 ) {
			return (int) $source->post_author;
		}

		// Try to match by candidate email.
		$email = (string) get_post_meta( $source->ID, '_candidate_email', true );
		if ( $email ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				return $user->ID;
			}
		}

		// Fall back to the admin running the import.
		$current = get_current_user_id();
		return $current > 0 ? $current : 1;
	}
}
