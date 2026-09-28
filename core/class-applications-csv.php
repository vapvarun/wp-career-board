<?php
/**
 * CSV export of applications (admin screen and employer dashboard).
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
 * Streams applications as a formula-safe CSV.
 *
 * @since 1.8.0
 */
final class ApplicationsCsv {

	/**
	 * Stream a CSV of the applications a query selects.
	 *
	 * Walks the query 500 IDs at a time so "export all" on a large board never
	 * loads every row at once. Every cell is guarded against spreadsheet
	 * formula injection (a cover letter starting with "=" opens as text).
	 *
	 * Columns: ID, Job ID, Job Title, Applicant Name, Applicant Email,
	 * Status, Submitted, Cover Letter, Resume URL.
	 *
	 * @since 1.1.0
	 * @since 1.8.0 Takes query args; batched; formula-safe; status label.
	 *
	 * @param array<string,mixed> $query_args WP_Query args selecting the rows.
	 * @param callable|null       $can_export Per-row check; default edit_post (admin screen).
	 * @return void Exits after streaming.
	 */
	public static function stream( array $query_args, ?callable $can_export = null ): void {
		$can_export = $can_export ?? static fn ( int $app_id ): bool => current_user_can( 'edit_post', $app_id ); // phpcs:ignore WordPress.WP.Capabilities -- admin screen default, as before.
		$filename = 'wcb-applications-' . gmdate( 'Y-m-d-His' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		// UTF-8 BOM so Excel respects the encoding.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- streaming to php://output, not a real file.
		fwrite( $out, "\xEF\xBB\xBF" );
		self::csv_row(
			$out,
			array(
				__( 'ID', 'wp-career-board' ),
				__( 'Job ID', 'wp-career-board' ),
				__( 'Job Title', 'wp-career-board' ),
				__( 'Applicant Name', 'wp-career-board' ),
				__( 'Applicant Email', 'wp-career-board' ),
				__( 'Status', 'wp-career-board' ),
				__( 'Submitted', 'wp-career-board' ),
				__( 'Cover Letter', 'wp-career-board' ),
				__( 'Resume URL', 'wp-career-board' ),
			)
		);

		$page = 1;
		do {
			$batch_args = array_merge(
				$query_args,
				array(
					'fields'         => 'ids',
					// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded batch.
					'posts_per_page' => 500,
					'paged'          => $page,
					'no_found_rows'  => true,
				)
			);
			$ids        = wp_parse_id_list( get_posts( $batch_args ) );
			if ( $ids ) {
				update_postmeta_cache( $ids );
				cache_users( array_filter( array_map( static fn( $id ) => (int) get_post_meta( $id, '_wcb_candidate_id', true ), $ids ) ) );
			}

			foreach ( $ids as $app_id ) {
				if ( ! $can_export( (int) $app_id ) ) {
					continue;
				}
				$job_id        = (int) get_post_meta( $app_id, '_wcb_job_id', true );
				$candidate_id  = (int) get_post_meta( $app_id, '_wcb_candidate_id', true );
				$user          = $candidate_id > 0 ? get_userdata( $candidate_id ) : false;
				$attachment_id = (int) get_post_meta( $app_id, '_wcb_resume_attachment_id', true );

				self::csv_row(
					$out,
					array(
						(string) $app_id,
						(string) $job_id,
						$job_id > 0 ? (string) get_post_field( 'post_title', $job_id ) : '',
						$user instanceof \WP_User ? $user->display_name : (string) get_post_meta( $app_id, '_wcb_guest_name', true ),
						$user instanceof \WP_User ? $user->user_email : (string) get_post_meta( $app_id, '_wcb_guest_email', true ),
						\WCB\Modules\Applications\ApplicationStatus::label( (string) get_post_meta( $app_id, '_wcb_status', true ), \WCB\Modules\Applications\ApplicationStatus::AUDIENCE_ADMIN ),
						(string) get_post_field( 'post_date', $app_id ),
						(string) get_post_meta( $app_id, '_wcb_cover_letter', true ),
						$attachment_id > 0 ? \WCB\Core\PrivateFiles::url( $attachment_id ) : '',
					)
				);
			}

			$wcb_batch = count( $ids );
			++$page;
		} while ( 500 === $wcb_batch );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streaming to php://output, not a real file.
		fclose( $out );
		exit;
	}

	/**
	 * Write one CSV row with every cell safe to open in a spreadsheet.
	 *
	 * A cell starting with = + - @ tab or CR is run as a formula by Excel,
	 * LibreOffice and Sheets; a leading apostrophe makes it plain text.
	 *
	 * @since 1.8.0
	 *
	 * @param resource           $out   Output stream.
	 * @param array<int, string> $cells Row cells.
	 * @return void
	 */
	private static function csv_row( $out, array $cells ): void {
		$cells = array_map(
			static fn( string $cell ): string => '' !== $cell && str_contains( "=+-@\t\r", $cell[0] ) ? "'" . $cell : $cell,
			$cells
		);
		fputcsv( $out, $cells, ',', '"', '' );
	}
}
