<?php
/**
 * Personal data: one registry for export, erase and account deletion.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Gdpr;

use WCB\Modules\Applications\ApplicationStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every piece of Career Board personal data has one owner here.
 *
 * Modules declare what they store through the `wcb_personal_data_providers`
 * filter; each provider exports and erases its own data for a person, found
 * by user ID and, for guest applicants, by email. The WordPress privacy tools
 * (Tools > Export / Erase Personal Data), a member deleting their account and
 * an admin deleting a user all run the same providers, so they can no longer
 * drift apart (card 10344034685).
 *
 * Applications are anonymised, not deleted (owner decision D3): the job,
 * status and dates stay so the employer's history and counts stay right;
 * name, email, cover letter, answers and files go.
 *
 * @since 1.0.0
 * @since 1.8.0 Provider registry; guests by email; applications anonymised.
 */
class GdprModule {

	/**
	 * Daily cron that deletes old email log rows (and Pro bell rows).
	 *
	 * @since 1.8.0
	 */
	public const PRUNE_HOOK = 'wcb_prune_logs';

	/**
	 * Application meta that survives anonymisation (not personal).
	 *
	 * @since 1.8.0
	 */
	private const KEEP_APPLICATION_META = array( '_wcb_job_id', '_wcb_status', '_wcb_status_log', '_wcb_stage_id', '_wcb_job_title_snapshot', '_wcb_company_name_snapshot' );

	/**
	 * User meta kept on erase: safety records, not profile data.
	 *
	 * @since 1.8.0
	 */
	private const KEEP_USER_META = array( '_wcb_employer_banned', '_wcb_deletion_scheduled_at', '_wcb_deletion_locked' );

	/**
	 * Account state, not personal data: a Tools > Erase request for an account that
	 * stays open must not unlink the company, mark an unverified email verified, or
	 * drop moderation reports (`_wcb_member_flag_*` holds other members' reports).
	 */
	private const KEEP_ACTIVE_META = array( '_wcb_company_id', '_wcb_email_unverified' );

	/**
	 * Boot the module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_filter( 'wcb_personal_data_providers', array( $this, 'free_providers' ), 5 );

		// Account deletion and admin user deletion: runs before WordPress
		// removes the user, while the email is still known.
		add_action( 'delete_user', array( self::class, 'erase_user' ), 5 );

		add_action( self::PRUNE_HOOK, array( self::class, 'prune_logs' ) );
		if ( ! wp_next_scheduled( self::PRUNE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PRUNE_HOOK );
		}
	}

	/**
	 * Register the one Career Board exporter.
	 *
	 * @since 1.0.0
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['wp-career-board'] = array(
			'exporter_friendly_name' => __( 'WP Career Board', 'wp-career-board' ),
			'callback'               => array( $this, 'export_user_data' ),
		);
		return $exporters;
	}

	/**
	 * Register the one Career Board eraser.
	 *
	 * @since 1.0.0
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['wp-career-board'] = array(
			'eraser_friendly_name' => __( 'WP Career Board', 'wp-career-board' ),
			'callback'             => array( $this, 'erase_user_data' ),
		);
		return $erasers;
	}

	/**
	 * Every registered provider, in a stable order.
	 *
	 * @since 1.8.0
	 * @return array<string, array{label:string, export:callable, erase:callable}>
	 */
	public static function providers(): array {
		/**
		 * Filter the personal-data providers.
		 *
		 * Each entry: `label`, `export( array $subject ): array` (privacy
		 * exporter items) and `erase( array $subject ): array{removed:int,
		 * retained:int, messages:string[]}`. `$subject` is
		 * `array{user_id:int, email:string, closing?:bool}`; user_id is 0 for a guest, `closing` is true only when the account itself is being deleted.
		 *
		 * @since 1.8.0
		 *
		 * @param array $providers Providers keyed by ID.
		 */
		$providers = (array) apply_filters( 'wcb_personal_data_providers', array() );
		ksort( $providers );
		return $providers;
	}

	/**
	 * Privacy exporter: page N exports provider N.
	 *
	 * One person's data per provider is small (a candidate has tens of
	 * applications, not thousands), so each provider exports in one call.
	 *
	 * @since 1.0.0
	 * @param string $email_address Requester's email.
	 * @param int    $page          1-based page.
	 * @return array{data:array, done:bool}
	 */
	public function export_user_data( string $email_address, int $page = 1 ): array {
		$providers = array_values( self::providers() );
		$provider  = $providers[ max( 1, $page ) - 1 ] ?? null;
		$data      = $provider ? (array) call_user_func( $provider['export'], self::subject( $email_address ) ) : array();
		$done      = $page >= count( $providers );
		if ( $done ) {
			self::log_action( self::subject( $email_address )['user_id'], 'export' );
		}
		return array(
			'data' => $data,
			'done' => $done,
		);
	}

	/**
	 * Privacy eraser: page N erases provider N.
	 *
	 * @since 1.0.0
	 * @param string $email_address Requester's email.
	 * @param int    $page          1-based page.
	 * @return array{items_removed:bool, items_retained:bool, messages:array, done:bool}
	 */
	public function erase_user_data( string $email_address, int $page = 1 ): array {
		$providers = array_values( self::providers() );
		$provider  = $providers[ max( 1, $page ) - 1 ] ?? null;
		$result    = $provider ? self::erase_one( $provider, self::subject( $email_address ) ) : array(
			'removed'  => 0,
			'retained' => 0,
			'messages' => array(),
		);
		$done      = $page >= count( $providers );
		if ( $done ) {
			self::log_action( self::subject( $email_address )['user_id'], 'erase' );
		}
		return array(
			'items_removed'  => $result['removed'] > 0,
			'items_retained' => $result['retained'] > 0,
			'messages'       => $result['messages'],
			'done'           => $done,
		);
	}

	/**
	 * Erase everything a user has, through every provider.
	 *
	 * Hooked to `delete_user`, so a member's own account deletion and an
	 * admin deleting a user both leave nothing personal behind.
	 *
	 * @since 1.8.0
	 * @param int $user_id User being deleted.
	 * @return void
	 */
	public static function erase_user( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return;
		}
		$subject = array(
			'user_id' => $user_id,
			'email'   => (string) $user->user_email,
			'closing' => true,
		);
		foreach ( self::providers() as $provider ) {
			self::erase_one( $provider, $subject );
		}
	}

	/**
	 * Run one provider's eraser with a normalised result.
	 *
	 * @param array                             $provider Provider entry.
	 * @param array{user_id:int, email:string} $subject  Person.
	 * @return array{removed:int, retained:int, messages:array<int,string>}
	 */
	private static function erase_one( array $provider, array $subject ): array {
		$result = (array) call_user_func( $provider['erase'], $subject );
		return array(
			'removed'  => (int) ( $result['removed'] ?? 0 ),
			'retained' => (int) ( $result['retained'] ?? 0 ),
			'messages' => array_values( (array) ( $result['messages'] ?? array() ) ),
		);
	}

	/**
	 * The person an email belongs to: a member, or a guest (user_id 0).
	 *
	 * @param string $email Email address.
	 * @return array{user_id:int, email:string}
	 */
	private static function subject( string $email ): array {
		$user = get_user_by( 'email', $email );
		return array(
			'user_id' => $user instanceof \WP_User ? (int) $user->ID : 0,
			'email'   => $email,
		);
	}

	/**
	 * Free's providers: applications, profile and files, email log.
	 *
	 * @since 1.8.0
	 * @param array $providers Providers so far.
	 * @return array
	 */
	public function free_providers( array $providers ): array {
		$providers['applications'] = array(
			'label'  => __( 'Job applications', 'wp-career-board' ),
			'export' => array( self::class, 'export_applications' ),
			'erase'  => array( self::class, 'anonymise_applications' ),
		);
		$providers['profile']      = array(
			'label'  => __( 'Profile, saved items and files', 'wp-career-board' ),
			'export' => array( self::class, 'export_profile' ),
			'erase'  => array( self::class, 'erase_profile' ),
		);
		$providers['email-log']    = array(
			'label'  => __( 'Email history', 'wp-career-board' ),
			'export' => array( self::class, 'export_email_log' ),
			'erase'  => array( self::class, 'erase_email_log' ),
		);
		return $providers;
	}

	/**
	 * Applications a person made: as a member and as a guest with this email.
	 *
	 * @param array{user_id:int, email:string} $subject Person.
	 * @return array<int>
	 */
	private static function application_ids( array $subject ): array {
		$clauses = array( 'relation' => 'OR' );
		if ( $subject['user_id'] > 0 ) {
			$clauses[] = array(
				'key'   => '_wcb_candidate_id',
				'value' => (string) $subject['user_id'],
			);
		}
		if ( '' !== $subject['email'] ) {
			$clauses[] = array(
				'key'   => '_wcb_guest_email',
				'value' => $subject['email'],
			);
		}
		if ( count( $clauses ) < 2 ) {
			return array();
		}
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => 'wcb_application',
					'post_status'    => 'any',
					// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- one person's applications.
					'posts_per_page' => 1000,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_query'     => $clauses, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				)
			)
		);
	}

	/**
	 * Export a person's applications with what they submitted.
	 *
	 * @param array{user_id:int, email:string} $subject Person.
	 * @return array
	 */
	public static function export_applications( array $subject ): array {
		$ids = self::application_ids( $subject );
		if ( $ids ) {
			update_postmeta_cache( $ids );
		}
		$items = array();
		foreach ( $ids as $id ) {
			$job_id = (int) get_post_meta( $id, '_wcb_job_id', true );
			$data   = array(
				array(
					'name'  => __( 'Job', 'wp-career-board' ),
					'value' => \WCB\Modules\Applications\ApplicationLifecycle::job_title( $id ),
				),
				array(
					'name'  => __( 'Status', 'wp-career-board' ),
					'value' => ApplicationStatus::label( (string) get_post_meta( $id, '_wcb_status', true ), ApplicationStatus::AUDIENCE_CANDIDATE ),
				),
				array(
					'name'  => __( 'Submitted', 'wp-career-board' ),
					'value' => (string) get_post_field( 'post_date', $id ),
				),
				array(
					'name'  => __( 'Cover letter', 'wp-career-board' ),
					'value' => wp_strip_all_tags( (string) get_post_meta( $id, '_wcb_cover_letter', true ) ),
				),
			);
			foreach ( array( '_wcb_guest_name', '_wcb_guest_email' ) as $key ) {
				$value = (string) get_post_meta( $id, $key, true );
				if ( '' !== $value ) {
					$data[] = array(
						'name'  => '_wcb_guest_name' === $key ? __( 'Name', 'wp-career-board' ) : __( 'Email', 'wp-career-board' ),
						'value' => $value,
					);
				}
			}
			$answers = \WCB\Core\FormCustomFields::labelled_values(
				(array) apply_filters( 'wcb_application_form_fields_groups', array(), $job_id ),
				$id,
				'post_meta',
				\WCB\Api\Endpoints\ApplicationsEndpoint::FIELD_META_PREFIX
			);
			foreach ( $answers as $answer ) {
				$data[] = array(
					'name'  => (string) ( $answer['label'] ?? '' ),
					'value' => is_array( $answer['value'] ?? '' ) ? implode( ', ', $answer['value'] ) : (string) ( $answer['value'] ?? '' ),
				);
			}
			$file = (int) get_post_meta( $id, '_wcb_resume_attachment_id', true );
			if ( $file > 0 ) {
				$data[] = array(
					'name'  => __( 'Resume file', 'wp-career-board' ),
					'value' => wp_basename( (string) get_attached_file( $file ) ),
				);
			}
			$items[] = array(
				'group_id'    => 'wcb-applications',
				'group_label' => __( 'Job applications', 'wp-career-board' ),
				'item_id'     => 'application-' . $id,
				'data'        => $data,
			);
		}
		return $items;
	}

	/**
	 * Anonymise a person's applications (owner decision D3).
	 *
	 * Keeps the job, status, status history and dates so the employer's
	 * pipeline and counts stay true; removes name, email, cover letter,
	 * answers and every file. The employer sees "Deleted candidate".
	 *
	 * @param array{user_id:int, email:string} $subject Person.
	 * @return array{removed:int, retained:int, messages:array<int,string>}
	 */
	public static function anonymise_applications( array $subject ): array {
		$ids = self::application_ids( $subject );
		foreach ( $ids as $id ) {
			foreach ( get_children(
				array(
					'post_parent' => $id,
					'post_type'   => 'attachment',
					'fields'      => 'ids',
				)
			) as $child ) {
				wp_delete_attachment( (int) $child, true );
			}
			foreach ( array_keys( (array) get_post_meta( $id ) ) as $key ) {
				if ( in_array( $key, self::KEEP_APPLICATION_META, true ) ) {
					continue;
				}
				if ( '_wcb_resume_attachment_id' === $key ) {
					wp_delete_attachment( (int) get_post_meta( $id, $key, true ), true );
				}
				delete_post_meta( $id, $key );
			}
			update_post_meta( $id, '_wcb_guest_name', __( 'Deleted candidate', 'wp-career-board' ) );
			update_post_meta( $id, '_wcb_candidate_id', 0 );
			update_post_meta( $id, '_wcb_anonymised_at', time() );
			wp_update_post(
				array(
					'ID'          => $id,
					'post_author' => 0,
					'post_title'  => __( 'Application: Deleted candidate', 'wp-career-board' ),
				)
			);
		}
		return array(
			'removed'  => count( $ids ),
			'retained' => count( $ids ),
			'messages' => $ids ? array( __( 'Applications are kept without personal details (job, status and dates only) so employers\' hiring records stay accurate.', 'wp-career-board' ) ) : array(),
		);
	}

	/**
	 * Export profile fields and saved items.
	 *
	 * @param array{user_id:int, email:string} $subject Person.
	 * @return array
	 */
	public static function export_profile( array $subject ): array {
		if ( $subject['user_id'] <= 0 ) {
			return array();
		}
		$labels = array(
			'_wcb_job_title'          => __( 'Headline', 'wp-career-board' ),
			'_wcb_location'           => __( 'Location', 'wp-career-board' ),
			'_wcb_open_to_work'       => __( 'Open to work', 'wp-career-board' ),
			'_wcb_profile_visibility' => __( 'Profile visibility', 'wp-career-board' ),
			'_wcb_resume_data'        => __( 'Profile resume', 'wp-career-board' ),
			'_wcb_bookmark'           => __( 'Saved jobs', 'wp-career-board' ),
			'_wcb_company_bookmark'   => __( 'Saved companies', 'wp-career-board' ),
			'_wcb_resume_bookmark'    => __( 'Saved resumes', 'wp-career-board' ),
		);
		$data   = array();
		foreach ( $labels as $key => $label ) {
			$values = get_user_meta( $subject['user_id'], $key, false );
			if ( ! $values ) {
				continue;
			}
			if ( in_array( $key, array( '_wcb_bookmark', '_wcb_company_bookmark', '_wcb_resume_bookmark' ), true ) ) {
				$values = array_filter( array_map( static fn( $post_id ) => get_the_title( (int) $post_id ), $values ) );
			}
			$data[] = array(
				'name'  => $label,
				'value' => implode(
					', ',
					array_map(
						static fn( $value ) => is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value ),
						$values
					)
				),
			);
		}
		return $data ? array(
			array(
				'group_id'    => 'wcb-profile',
				'group_label' => __( 'Career Board profile', 'wp-career-board' ),
				'item_id'     => 'profile-' . $subject['user_id'],
				'data'        => $data,
			),
		) : array();
	}

	/**
	 * Erase profile fields, saved items and the person's private files.
	 *
	 * Ban and pending-deletion flags are kept: they are safety records, and
	 * an erase must never lift a ban.
	 *
	 * @param array{user_id:int, email:string, closing?:bool} $subject Person.
	 * @return array{removed:int, retained:int, messages:array<int,string>}
	 */
	public static function erase_profile( array $subject ): array {
		if ( $subject['user_id'] <= 0 ) {
			return array(
				'removed'  => 0,
				'retained' => 0,
				'messages' => array(),
			);
		}
		$removed = 0;
		foreach ( array_keys( (array) get_user_meta( $subject['user_id'] ) ) as $key ) {
			$keep = in_array( $key, self::KEEP_USER_META, true )
				|| ( empty( $subject['closing'] ) && ( in_array( $key, self::KEEP_ACTIVE_META, true ) || str_starts_with( (string) $key, '_wcb_member_flag_' ) ) );
			if ( str_starts_with( (string) $key, '_wcb_' ) && ! $keep ) {
				delete_user_meta( $subject['user_id'], (string) $key );
				++$removed;
			}
		}
		// Uploaded CVs and generated PDFs live in private storage as the
		// member's attachments.
		foreach ( get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'author'         => $subject['user_id'],
				'fields'         => 'ids',
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- one person's files.
				'posts_per_page' => 500,
				'meta_key'       => '_wcb_private_file', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		) as $file ) {
			wp_delete_attachment( (int) $file, true );
			++$removed;
		}
		$retained = (bool) get_user_meta( $subject['user_id'], '_wcb_employer_banned', true );
		return array(
			'removed'  => $removed,
			'retained' => $retained ? 1 : 0,
			'messages' => $retained ? array( __( 'A ban on this account is kept as a safety record.', 'wp-career-board' ) ) : array(),
		);
	}

	/**
	 * Where a person's email-log rows are: by user ID, or a guest's address.
	 *
	 * @param array{user_id:int, email:string} $subject Person.
	 * @return array{0:string, 1:array<int, int|string>} SQL condition and values.
	 */
	private static function email_log_where( array $subject ): array {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( '"to":' . (string) wp_json_encode( $subject['email'] ) ) . '%';
		// A guest is user_id 0, which is every guest's rows: only the address tells them apart.
		return $subject['user_id'] > 0
			? array( '( user_id = %d OR ( user_id = 0 AND payload LIKE %s ) )', array( $subject['user_id'], $like ) )
			: array( '( user_id = 0 AND payload LIKE %s )', array( $like ) );
	}

	/**
	 * Export the person's email history.
	 *
	 * @param array{user_id:int, email:string} $subject Person.
	 * @return array
	 */
	public static function export_email_log( array $subject ): array {
		global $wpdb;
		list( $where, $values ) = self::email_log_where( $subject );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $where is a fixed fragment; values are prepared.
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, event_type, status, sent_at FROM {$wpdb->prefix}wcb_notifications_log WHERE {$where} ORDER BY id ASC LIMIT 1000", ...$values ), ARRAY_A );
		$items = array();
		foreach ( $rows as $row ) {
			$items[] = array(
				'group_id'    => 'wcb-email-log',
				'group_label' => __( 'Email history', 'wp-career-board' ),
				'item_id'     => 'email-' . $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'Email', 'wp-career-board' ),
						'value' => (string) $row['event_type'],
					),
					array(
						'name'  => __( 'Status', 'wp-career-board' ),
						'value' => (string) $row['status'],
					),
					array(
						'name'  => __( 'Sent', 'wp-career-board' ),
						'value' => (string) $row['sent_at'],
					),
				),
			);
		}
		return $items;
	}

	/**
	 * Delete the person's email history (members and guests).
	 *
	 * @param array{user_id:int, email:string} $subject Person.
	 * @return array{removed:int, retained:int, messages:array<int,string>}
	 */
	public static function erase_email_log( array $subject ): array {
		global $wpdb;
		list( $where, $values ) = self::email_log_where( $subject );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $where is a fixed fragment; values are prepared.
		$removed = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wcb_notifications_log WHERE {$where}", ...$values ) );
		return array(
			'removed'  => $removed,
			'retained' => 1,
			'messages' => array( __( 'The record that this privacy request was handled is kept for compliance.', 'wp-career-board' ) ),
		);
	}

	/**
	 * Delete email log rows older than the retention period, in batches.
	 *
	 * Settings > Advanced "Keep email and notification history for (days)",
	 * default 180; 0 keeps them. Fires `wcb_logs_pruned` so Pro prunes the
	 * notification bell with the same cutoff.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public static function prune_logs(): void {
		$days = \WCB\Admin\Settings::int( 'log_retention_days', 180 );
		if ( $days <= 0 ) {
			return;
		}
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded retention delete.
		$deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wcb_notifications_log WHERE sent_at < %s LIMIT 5000", $cutoff ) );

		/**
		 * Fires when Career Board prunes old history.
		 *
		 * @since 1.8.0
		 *
		 * @param string $cutoff Rows older than this (UTC, Y-m-d H:i:s) are removed.
		 */
		do_action( 'wcb_logs_pruned', $cutoff );

		if ( 5000 === $deleted ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::PRUNE_HOOK );
		}
	}

	/**
	 * Record a handled privacy request (retained as evidence).
	 *
	 * The client IP is stored only as a SHA-256 hash.
	 *
	 * @since 1.0.0
	 * @param int    $user_id User the request was for (0 for a guest).
	 * @param string $action  'export' or 'erase'.
	 * @return void
	 */
	private static function log_action( int $user_id, string $action ): void {
		global $wpdb;

		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insert into custom wcb_gdpr_log table; no caching needed for write-only audit log.
		$wpdb->insert(
			$wpdb->prefix . 'wcb_gdpr_log',
			array(
				'user_id'    => $user_id,
				'action'     => $action,
				'ip_hash'    => hash( 'sha256', $ip ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}
}
