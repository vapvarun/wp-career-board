<?php
/**
 * Private notes and a rating on an application.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Applications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hiring-team notes (who, when, what) and a 1-5 rating per application.
 *
 * Only the job's employer and staff read or write them (the REST routes use
 * the status-update permission); they are never in a candidate response.
 * Stored as application meta, so anonymising an application removes them.
 *
 * @since 1.8.0
 */
final class ApplicationNotes {

	/**
	 * Notes meta key: list of {id, author, text, at}.
	 *
	 * @var string
	 */
	public const NOTES = '_wcb_notes';

	/**
	 * Rating meta key: 1-5, absent when unrated.
	 *
	 * @var string
	 */
	public const RATING = '_wcb_rating';

	/**
	 * Notes, oldest first, with the author's name.
	 *
	 * @param int $app_id Application ID.
	 * @return array<int, array{id:string, author:int, author_name:string, text:string, at:string}>
	 */
	public static function notes( int $app_id ): array {
		$notes = get_post_meta( $app_id, self::NOTES, true );
		return array_map(
			static function ( array $note ): array {
				$user                = get_userdata( (int) $note['author'] );
				$note['author_name'] = $user instanceof \WP_User ? $user->display_name : __( 'Former member', 'wp-career-board' );
				return $note;
			},
			is_array( $notes ) ? array_values( $notes ) : array()
		);
	}

	/**
	 * Add a note.
	 *
	 * @param int    $app_id Application ID.
	 * @param int    $author User ID.
	 * @param string $text   Note text.
	 * @return array Note.
	 */
	public static function add( int $app_id, int $author, string $text ): array {
		$notes   = get_post_meta( $app_id, self::NOTES, true );
		$notes   = is_array( $notes ) ? $notes : array();
		$note    = array(
			'id'     => wp_generate_password( 8, false, false ),
			'author' => $author,
			'text'   => $text,
			'at'     => gmdate( 'c' ),
		);
		$notes[] = $note;
		update_post_meta( $app_id, self::NOTES, $notes );
		return $note;
	}

	/**
	 * Delete a note (its author or staff).
	 *
	 * @param int    $app_id  Application ID.
	 * @param string $note_id Note ID.
	 * @param int    $user    Acting user.
	 * @param bool   $staff   Staff may delete any note.
	 * @return bool
	 */
	public static function delete( int $app_id, string $note_id, int $user, bool $staff ): bool {
		$notes = get_post_meta( $app_id, self::NOTES, true );
		$notes = is_array( $notes ) ? $notes : array();
		$kept  = array_values( array_filter( $notes, static fn ( array $n ): bool => $n['id'] !== $note_id || ( ! $staff && (int) $n['author'] !== $user ) ) );
		if ( count( $kept ) === count( $notes ) ) {
			return false;
		}
		update_post_meta( $app_id, self::NOTES, $kept );
		return true;
	}

	/**
	 * Rating 1-5, or 0 when unrated.
	 *
	 * @param int $app_id Application ID.
	 * @return int
	 */
	public static function rating( int $app_id ): int {
		return (int) get_post_meta( $app_id, self::RATING, true );
	}

	/**
	 * Set the rating (0 clears it).
	 *
	 * @param int $app_id Application ID.
	 * @param int $rating 0-5.
	 * @return int
	 */
	public static function set_rating( int $app_id, int $rating ): int {
		$rating = max( 0, min( 5, $rating ) );
		if ( 0 === $rating ) {
			delete_post_meta( $app_id, self::RATING );
		} else {
			update_post_meta( $app_id, self::RATING, $rating );
		}
		return $rating;
	}
}
