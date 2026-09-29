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
		// One meta row per note. A pre-1.8 install stored them as one row
		// holding the whole list: flatten that shape on read.
		$notes = array();
		foreach ( (array) get_post_meta( $app_id, self::NOTES, false ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( isset( $row['id'] ) ) {
				$notes[] = $row;
			} else {
				array_push( $notes, ...array_values( array_filter( $row, 'is_array' ) ) );
			}
		}
		usort( $notes, static fn ( array $a, array $b ): int => strcmp( (string) $a['at'], (string) $b['at'] ) );
		return array_map(
			static function ( array $note ): array {
				$user                = get_userdata( (int) $note['author'] );
				$note['author_name'] = $user instanceof \WP_User ? $user->display_name : __( 'Former member', 'wp-career-board' );
				return $note;
			},
			$notes
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
		$note = array(
			'id'     => wp_generate_password( 8, false, false ),
			'author' => $author,
			'text'   => $text,
			'at'     => gmdate( 'c' ),
		);

		// Its own meta row: a plain INSERT, so concurrent adds can't overwrite
		// each other the way a read-modify-write of one list did
		// (Basecamp 10350213909).
		add_post_meta( $app_id, self::NOTES, $note );

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
		$allowed = static fn ( array $n ): bool => $n['id'] === $note_id && ( $staff || (int) $n['author'] === $user );
		foreach ( (array) get_post_meta( $app_id, self::NOTES, false ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( isset( $row['id'] ) ) {
				if ( $allowed( $row ) ) {
					return delete_post_meta( $app_id, self::NOTES, $row );
				}
				continue;
			}
			// Legacy list row: split it into one row per remaining note.
			$list = array_values( array_filter( $row, 'is_array' ) );
			$kept = array_values( array_filter( $list, static fn ( array $n ): bool => ! $allowed( $n ) ) );
			if ( count( $kept ) !== count( $list ) ) {
				delete_post_meta( $app_id, self::NOTES, $row );
				foreach ( $kept as $n ) {
					add_post_meta( $app_id, self::NOTES, $n );
				}
				return true;
			}
		}
		return false;
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
