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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrates WP Job Manager jobs and resumes into WP Career Board CPTs.
 *
 * @since 1.0.0
 */
class WpjmImporter {

	// ── Jobs ─────────────────────────────────────────────────────────────────

	/**
	 * Total WPJM job_listing posts with the given status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Post status (default 'publish').
	 * @return int
	 */
	public function wpjm_jobs_total( string $status = 'publish' ): int {
		if ( ! post_type_exists( 'job_listing' ) ) {
			return 0;
		}
		$q = new \WP_Query(
			array(
				'post_type'              => 'job_listing',
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
	 * Number of wcb_job posts that already carry a _wcb_migrated_from meta.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function wcb_jobs_migrated(): int {
		$q = new \WP_Query(
			array(
				'post_type'              => 'wcb_job',
				'post_status'            => 'any',
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
	 * @param string $status WPJM post status to query.
	 * @return array{imported:int, skipped:int, errors:string[]}
	 */
	public function migrate_jobs_batch( int $offset, int $limit, string $status = 'publish' ): array {
		$ids = get_posts(
			array(
				'post_type'      => 'job_listing',
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
				'meta_value'     => $source_id,
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
				'post_type'     => 'wcb_job',
				'post_title'    => $source->post_title,
				'post_content'  => $source->post_content,
				'post_excerpt'  => $source->post_excerpt,
				'post_status'   => 'publish' === $source->post_status ? 'publish' : 'pending',
				'post_author'   => $source->post_author,
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
			'_job_location'        => '_wcb_location',
			'_job_salary'          => '_wcb_salary_min',
			'_job_salary_currency' => '_wcb_salary_currency',
			'_job_salary_unit'     => '_wcb_salary_type',
			'_job_expires'         => '_wcb_deadline',
			'_featured'            => '_wcb_featured',
			'_remote_position'     => '_wcb_remote',
			'_company_name'        => '_wcb_company_name',
			'_company_website'     => '_wcb_website',
			'_company_tagline'     => '_wcb_tagline',
			'_company_twitter'     => '_wcb_twitter',
			'_company_video'       => '_wcb_company_video',
		);

		foreach ( $meta_map as $wpjm_key => $wcb_key ) {
			$value = get_post_meta( $source_id, $wpjm_key, true );
			if ( '' !== $value && false !== $value ) {
				update_post_meta( $new_id, $wcb_key, $value );
			}
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

		// Filled → close the job.
		if ( get_post_meta( $source_id, '_filled', true ) ) {
			update_post_meta( $new_id, '_wcb_status', 'closed' );
		}

		// Company logo (attachment ID).
		$logo_id = (int) get_post_meta( $source_id, '_company_logo', true );
		if ( $logo_id > 0 ) {
			set_post_thumbnail( $new_id, $logo_id );
		}

		// Provenance tracking.
		update_post_meta( $new_id, '_wcb_migrated_from', $source_id );
		update_post_meta( $new_id, '_wcb_migrated_source', 'wp-job-manager' );

		// ── Taxonomies ────────────────────────────────────────────────────────
		$this->migrate_taxonomy( $source_id, $new_id, 'job_listing_category', 'wcb_category' );
		$this->migrate_taxonomy( $source_id, $new_id, 'job_listing_type', 'wcb_job_type' );

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
				'meta_value'     => $source_id,
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
