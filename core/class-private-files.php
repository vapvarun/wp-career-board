<?php
/**
 * Private storage and gated download for candidate files (resumes, CVs).
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Candidate files used to be ordinary public attachments: core REST
 * `/wp/v2/media` listed every CV to anonymous visitors and each file
 * downloaded from a guessable uploads URL, private resumes included.
 *
 * Now every resume upload and generated resume PDF is written under
 * `uploads/wcb-private/<random>/`, the attachment is given `private` status
 * (so core REST and the media modal skip it for non-admins), and the file is
 * served only through {@see PrivateFiles::send()} after
 * {@see PrivateFiles::can_download()}. The random folder keeps URLs
 * unguessable on nginx, where the directory's .htaccess deny is not read.
 *
 * Existing files are moved by a batched cron job ({@see PrivateFiles::migrate_batch()})
 * scheduled on upgrade, or all at once with `wp wcb files migrate`.
 *
 * @since 1.8.0
 */
final class PrivateFiles {

	/**
	 * Folder under the uploads base directory.
	 *
	 * @since 1.8.0
	 * @var string
	 */
	public const DIR = 'wcb-private';

	/**
	 * Attachment meta flag marking a file as private.
	 *
	 * @since 1.8.0
	 * @var string
	 */
	public const META = '_wcb_private_file';

	/**
	 * Cron hook that moves existing files in batches.
	 *
	 * @since 1.8.0
	 * @var string
	 */
	public const MIGRATE_HOOK = 'wcb_private_files_migrate';

	/**
	 * Files moved per cron pass.
	 *
	 * @since 1.8.0
	 * @var int
	 */
	private const BATCH = 50;

	/**
	 * Post meta: paths of files that could not be moved out of the public
	 * folder, so the next pass can finish them and Site Health can report them.
	 *
	 * @since 1.8.0
	 * @var string
	 */
	public const LEFT_BEHIND = '_wcb_private_left_behind';

	/**
	 * Post meta: unix time before which a failed attachment is not retried.
	 *
	 * @since 1.8.0
	 * @var string
	 */
	private const RETRY_AT = '_wcb_private_retry_at';

	/**
	 * Random sub-folder for the upload in flight.
	 *
	 * @since 1.8.0
	 * @var string
	 */
	private static string $subdir = '';

	/**
	 * Register the download handler and the migration job.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'template_redirect', array( self::class, 'maybe_serve' ), 0 );
		add_action(
			self::MIGRATE_HOOK,
			static function (): void {
				self::migrate_batch();
			}
		);
		add_filter( 'site_status_tests', array( self::class, 'register_health_test' ) );
	}

	/**
	 * Add the "candidate files are not publicly reachable" Site Health test.
	 *
	 * @since 1.8.0
	 * @param array<string,mixed> $tests Site Health tests.
	 * @return array<string,mixed>
	 */
	public static function register_health_test( array $tests ): array {
		$tests['direct']['wcb_private_files'] = array(
			'label' => __( 'Candidate files are private', 'wp-career-board' ),
			'test'  => array( self::class, 'health_test' ),
		);
		return $tests;
	}

	/**
	 * Site Health: fetch a probe file from the private folder over HTTP.
	 *
	 * Apache honours the folder's .htaccess; nginx does not, so there the
	 * random folder names are the only protection until the owner adds a
	 * server rule. This tells them when that is the case.
	 *
	 * @since 1.8.0
	 * @return array<string,mixed>
	 */
	public static function health_test(): array {
		$uploads = wp_get_upload_dir();
		self::guard_folder( (string) $uploads['basedir'] );
		$probe = trailingslashit( $uploads['basedir'] ) . self::DIR . '/probe.txt';
		if ( ! file_exists( $probe ) ) {
			file_put_contents( $probe, 'wcb-private-probe' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- static probe file.
		}
		$response  = wp_remote_get( trailingslashit( $uploads['baseurl'] ) . self::DIR . '/probe.txt', array( 'timeout' => 5 ) );
		$reachable = 200 === wp_remote_retrieve_response_code( $response ) && 'wcb-private-probe' === wp_remote_retrieve_body( $response );

		$result = array(
			'label'       => __( 'Candidate files are private', 'wp-career-board' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'wp-career-board' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'Resumes and CVs are stored in a folder your web server refuses to serve directly. Employers and candidates download them through a permission check.', 'wp-career-board' ) . '</p>',
			'test'        => 'wcb_private_files',
		);

		$left = self::left_behind_count();
		if ( $left > 0 ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'Some candidate files could not be moved out of the public uploads folder', 'wp-career-board' );
			$result['description'] = '<p>' . esc_html(
				sprintf(
					/* translators: %d: number of resumes/CVs with a file still in the public uploads folder. */
					_n( '%d candidate file is still in the public uploads folder, so anyone with its address can open it. It is retried every hour.', '%d candidate files are still in the public uploads folder, so anyone with their address can open them. They are retried every hour.', $left, 'wp-career-board' ),
					$left
				)
			) . '</p><p>' . esc_html__( 'This usually means the web server user cannot write to (or delete from) the uploads folder for those files. Fix the permissions, then run: wp wcb migrate files', 'wp-career-board' ) . '</p>';
		} elseif ( $reachable ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Candidate files can be fetched directly from the uploads folder', 'wp-career-board' );
			$result['description'] = '<p>' . esc_html__( 'Your web server does not read .htaccess files (common on nginx), so the private folder is protected only by its random folder names. Add this rule to your nginx server block, or ask your host to:', 'wp-career-board' ) . '</p><p><code>location ^~ ' . esc_html( untrailingslashit( (string) wp_parse_url( (string) $uploads['baseurl'], PHP_URL_PATH ) ) ) . '/' . self::DIR . '/ { deny all; }</code></p>';
		}

		return $result;
	}

	/**
	 * Run an upload inside the private folder and return its result.
	 *
	 * @since 1.8.0
	 * @param callable $upload Does the actual media_handle_upload() / wp_upload_bits().
	 * @return mixed Whatever $upload returns.
	 */
	public static function in_private_dir( callable $upload ): mixed {
		self::$subdir = '/' . self::DIR . '/' . wp_generate_password( 20, false, false );
		add_filter( 'upload_dir', array( self::class, 'private_upload_dir' ) );
		try {
			return $upload();
		} finally {
			remove_filter( 'upload_dir', array( self::class, 'private_upload_dir' ) );
		}
	}

	/**
	 * `upload_dir` filter: point the upload at the private folder.
	 *
	 * @since 1.8.0
	 * @param array<string,mixed> $dirs Upload dir data.
	 * @return array<string,mixed>
	 */
	public static function private_upload_dir( array $dirs ): array {
		self::guard_folder( (string) $dirs['basedir'] );
		$dirs['subdir'] = self::$subdir;
		$dirs['path']   = $dirs['basedir'] . self::$subdir;
		$dirs['url']    = $dirs['baseurl'] . self::$subdir;
		return $dirs;
	}

	/**
	 * Mark an attachment private.
	 *
	 * @since 1.8.0
	 * @param int $attachment_id Attachment post ID.
	 * @return void
	 */
	public static function protect( int $attachment_id ): void {
		if ( $attachment_id <= 0 ) {
			return;
		}
		wp_update_post(
			array(
				'ID'          => $attachment_id,
				'post_status' => 'private',
			)
		);
		update_post_meta( $attachment_id, self::META, '1' );
	}

	/**
	 * URL to hand out for a candidate file.
	 *
	 * Private files go through the gated handler; anything else keeps its
	 * normal attachment URL.
	 *
	 * @since 1.8.0
	 * @param int $attachment_id Attachment post ID.
	 * @return string '' when there is no file.
	 */
	public static function url( int $attachment_id ): string {
		if ( $attachment_id <= 0 ) {
			return '';
		}
		if ( ! get_post_meta( $attachment_id, self::META, true ) ) {
			return (string) wp_get_attachment_url( $attachment_id );
		}
		return add_query_arg( 'wcb_file', $attachment_id, home_url( '/' ) );
	}

	/**
	 * Whether the current user may download a private file.
	 *
	 * Owner, admins/moderators, and the two sides of any application that
	 * carries the file (the candidate, and the job's author or company).
	 * Pro adds resume-profile access through `wcb_private_file_can_download`.
	 *
	 * @since 1.8.0
	 * @param int $attachment_id Attachment post ID.
	 * @return bool
	 */
	public static function can_download( int $attachment_id ): bool {
		$file = get_post( $attachment_id );
		if ( ! $file instanceof \WP_Post || 'attachment' !== $file->post_type ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( $user_id > 0 && (int) $file->post_author === $user_id ) {
			return true;
		}
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
		if ( wp_is_ability_granted( 'wcb/manage-settings' ) || wp_is_ability_granted( 'wcb/moderate-jobs' ) ) {
			return true;
		}

		if ( $user_id > 0 ) {
			$applications = get_posts(
				array(
					'post_type'      => 'wcb_application',
					'post_status'    => 'any',
					'fields'         => 'ids',
					'posts_per_page' => 100,
					'no_found_rows'  => true,
					'meta_key'       => '_wcb_resume_attachment_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- indexed by wcb_meta_key_value.
					'meta_value'     => (string) $attachment_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- indexed by wcb_meta_key_value.
				)
			);
			$user_company = CompanyMetaShape::resolve_company_id( $user_id );
			foreach ( $applications as $application_id ) {
				if ( (int) get_post_meta( (int) $application_id, '_wcb_candidate_id', true ) === $user_id ) {
					return true;
				}
				$job_id = (int) get_post_meta( (int) $application_id, '_wcb_job_id', true );
				$job    = $job_id > 0 ? get_post( $job_id ) : null;
				if ( $job instanceof \WP_Post && (int) $job->post_author === $user_id ) {
					return true;
				}
				if ( $user_company > 0 && (int) get_post_meta( $job_id, '_wcb_company_id', true ) === $user_company ) {
					return true;
				}
			}
		}

		/**
		 * Filter whether the current user may download a private candidate file.
		 *
		 * @since 1.8.0
		 *
		 * @param bool $allowed       Default false (no rule above matched).
		 * @param int  $attachment_id Attachment post ID.
		 * @param int  $user_id       Current user ID (0 when logged out).
		 */
		return (bool) apply_filters( 'wcb_private_file_can_download', false, $attachment_id, $user_id );
	}

	/**
	 * `template_redirect`: serve `?wcb_file=<id>` requests.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public static function maybe_serve(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only download, gated by can_download().
		if ( ! isset( $_GET['wcb_file'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only download, gated by can_download().
		self::send( absint( wp_unslash( $_GET['wcb_file'] ) ) );
	}

	/**
	 * Stream a private file, or a 404 when the user may not have it.
	 *
	 * A denied request gets the same 404 as a missing file so IDs can't be
	 * probed.
	 *
	 * @since 1.8.0
	 * @param int $attachment_id Attachment post ID.
	 * @return never
	 */
	public static function send( int $attachment_id ): never {
		$path = self::can_download( $attachment_id ) ? (string) get_attached_file( $attachment_id ) : '';
		if ( '' === $path || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'File not found.', 'wp-career-board' ), '', array( 'response' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: ' . ( get_post_mime_type( $attachment_id ) ? get_post_mime_type( $attachment_id ) : 'application/octet-stream' ) );
		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( basename( $path ) ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a file the user is allowed to download.
		exit;
	}

	/**
	 * Move one batch of existing candidate files into private storage.
	 *
	 * Candidate files are PDF/DOC/DOCX attachments referenced by an
	 * application or resume (`_wcb_resume_attachment_id`) or attached to one.
	 * Reschedules itself until nothing is left.
	 *
	 * @since 1.8.0
	 * @return int Files processed in this pass (moved, or protected in place when missing on disk).
	 */
	public static function migrate_batch(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time bounded migration.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT a.ID FROM {$wpdb->posts} a
				LEFT JOIN {$wpdb->postmeta} ref ON ref.meta_key = '_wcb_resume_attachment_id' AND ref.meta_value = CAST( a.ID AS CHAR )
				LEFT JOIN {$wpdb->posts} parent ON parent.ID = a.post_parent
				LEFT JOIN {$wpdb->postmeta} done ON done.post_id = a.ID AND done.meta_key = %s
				LEFT JOIN {$wpdb->postmeta} retry ON retry.post_id = a.ID AND retry.meta_key = %s
				WHERE a.post_type = 'attachment'
				AND a.post_mime_type IN ( 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' )
				AND ( ref.meta_id IS NOT NULL OR parent.post_type IN ( 'wcb_application', 'wcb_resume' ) )
				AND ( done.meta_id IS NULL OR ( retry.meta_id IS NOT NULL AND CAST( retry.meta_value AS UNSIGNED ) <= %d ) )
				ORDER BY a.ID
				LIMIT %d",
				self::META,
				self::RETRY_AT,
				time(),
				self::BATCH
			)
		);

		$failed = 0;
		foreach ( array_map( 'intval', $ids ) as $attachment_id ) {
			self::move_to_private( $attachment_id );
			if ( get_post_meta( $attachment_id, self::RETRY_AT, true ) ) {
				++$failed;
			}
		}

		if ( ! wp_next_scheduled( self::MIGRATE_HOOK ) ) {
			if ( count( $ids ) === self::BATCH ) {
				wp_schedule_single_event( time() + 60, self::MIGRATE_HOOK );
			} elseif ( $failed > 0 ) {
				// A file that would not move is tried again later, not forgotten.
				wp_schedule_single_event( time() + HOUR_IN_SECONDS, self::MIGRATE_HOOK );
			}
		}

		return count( $ids );
	}

	/**
	 * Move one attachment's file into a new private folder and protect it.
	 *
	 * A file that is missing on disk is still protected (status + meta) so it
	 * leaves core REST and is not retried forever. A file that exists but
	 * cannot be moved (permissions, a locked file, another mount) is also
	 * protected, but stays on the retry list with the paths left behind, so it
	 * is finished on a later pass and shows in Site Health instead of sitting
	 * in the public folder unnoticed.
	 *
	 * @since 1.8.0
	 * @param int $attachment_id Attachment post ID.
	 * @return bool True when the file was moved.
	 */
	public static function move_to_private( int $attachment_id ): bool {
		$old   = (string) get_attached_file( $attachment_id );
		$moved = false;
		$left  = array();

		if ( '' !== $old && is_readable( $old ) && false === strpos( $old, '/' . self::DIR . '/' ) ) {
			$uploads = wp_get_upload_dir();
			self::guard_folder( (string) $uploads['basedir'] );
			$folder = trailingslashit( $uploads['basedir'] ) . self::DIR . '/' . wp_generate_password( 20, false, false );
			$new    = $folder . '/' . basename( $old );
			if ( wp_mkdir_p( $folder ) && self::relocate( $old, $new ) ) {
				update_attached_file( $attachment_id, $new );
				$moved = true;
				// A PDF's page-1 preview and its sizes sit beside it and show the CV
				// (name, email); WordPress resolves them from the file's folder, so
				// they move with it and the metadata needs no rewrite.
				$meta = wp_get_attachment_metadata( $attachment_id );
				foreach ( is_array( $meta ) && is_array( $meta['sizes'] ?? null ) ? $meta['sizes'] : array() as $size ) {
					$preview = dirname( $old ) . '/' . basename( (string) ( $size['file'] ?? '' ) );
					if ( is_file( $preview ) && ! self::relocate( $preview, $folder . '/' . basename( $preview ) ) ) {
						$left[] = $preview;
					}
				}
			} else {
				$left[] = $old;
			}
		} elseif ( '' !== $old ) {
			// Already in the private folder: finish any previews an earlier pass left behind.
			$pending = get_post_meta( $attachment_id, self::LEFT_BEHIND, true );
			foreach ( is_array( $pending ) ? $pending : array() as $path ) {
				if ( is_file( (string) $path ) && ! self::relocate( (string) $path, dirname( $old ) . '/' . basename( (string) $path ) ) ) {
					$left[] = (string) $path;
				}
			}
		}

		self::protect( $attachment_id );

		if ( $left ) {
			update_post_meta( $attachment_id, self::LEFT_BEHIND, $left );
			update_post_meta( $attachment_id, self::RETRY_AT, (string) ( time() + HOUR_IN_SECONDS ) );
			error_log( sprintf( 'WP Career Board: could not move %d file(s) of attachment %d into private storage; still in the public uploads folder: %s', count( $left ), $attachment_id, implode( ', ', array_map( 'basename', $left ) ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- a background job has no other channel; Site Health reports it too.
		} else {
			delete_post_meta( $attachment_id, self::LEFT_BEHIND );
			delete_post_meta( $attachment_id, self::RETRY_AT );
		}

		return $moved;
	}

	/**
	 * Move a file, falling back to copy-and-delete for another mount.
	 *
	 * Never leaves two copies: if the original cannot be removed after the copy,
	 * the copy is dropped and the move counts as failed.
	 *
	 * @since 1.8.0
	 * @param string $from Current path.
	 * @param string $to   Destination path.
	 * @return bool True when the file now exists only at $to.
	 */
	private static function relocate( string $from, string $to ): bool {
		if ( @rename( $from, $to ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- failure handled below.
			return true;
		}
		if ( @copy( $from, $to ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- failure handled below.
			if ( @unlink( $from ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- failure handled below.
				return true;
			}
			@unlink( $to ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- drop the copy so only the original remains.
		}
		return false;
	}

	/**
	 * How many attachments still have a file in the public uploads folder.
	 *
	 * @since 1.8.0
	 * @return int
	 */
	public static function left_behind_count(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- small indexed count for Site Health.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::LEFT_BEHIND ) );
	}

	/**
	 * Create the private root with a deny-all .htaccess and a blank index.
	 *
	 * @since 1.8.0
	 * @param string $basedir Uploads base directory.
	 * @return void
	 */
	private static function guard_folder( string $basedir ): void {
		$root = trailingslashit( $basedir ) . self::DIR;
		if ( file_exists( $root . '/.htaccess' ) ) {
			return;
		}
		wp_mkdir_p( $root );
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- two static guard files, written once.
		file_put_contents( $root . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
		file_put_contents( $root . '/index.php', "<?php\n// Silence is golden.\n" );
		// phpcs:enable
	}
}
