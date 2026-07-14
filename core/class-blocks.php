<?php
/**
 * Member blocking — the read side.
 *
 * MembersEndpoint owns the write side (block/unblock, stored as `_wcb_blocked`
 * user-meta). This is the shared read helper the list endpoints use to hide a
 * blocked member's content from the blocker. Blocking is treated as MUTUAL: if
 * either party blocked the other, neither sees the other's listings/resumes —
 * the safer reading of a block.
 *
 * @package WP_Career_Board
 * @since   1.7.0
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the set of author IDs whose content a given viewer must not see.
 *
 * @since 1.7.0
 */
class Blocks {

	/**
	 * Per-request memo, keyed by viewer ID — the reverse lookup is a user query
	 * and a list endpoint calls this once per request.
	 *
	 * @var array<int, array<int>>
	 */
	private static array $cache = array();

	/**
	 * Author IDs to hide from this viewer: everyone they blocked, plus everyone
	 * who blocked them. Empty for a logged-out viewer or one with no blocks —
	 * the common case, so it costs nothing.
	 *
	 * @since 1.7.0
	 *
	 * @param int $viewer_id Current user ID.
	 * @return array<int>
	 */
	public static function hidden_author_ids( int $viewer_id ): array {
		if ( $viewer_id <= 0 ) {
			return array();
		}

		if ( isset( self::$cache[ $viewer_id ] ) ) {
			return self::$cache[ $viewer_id ];
		}

		// Forward: members this viewer blocked (cheap — own user-meta). `_wcb_blocked`
		// is non-unique meta (one row per blocked member), so pass false for all rows.
		$forward = array_map( 'intval', (array) get_user_meta( $viewer_id, '_wcb_blocked', false ) );

		// Reverse: members who blocked this viewer. One bounded user query on the
		// indexed meta_key; blocks are rare, so this returns few rows.
		$reverse = get_users(
			array(
				'meta_key'   => '_wcb_blocked', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- indexed key, bounded result.
				'meta_value' => (string) $viewer_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- exact match, bounded.
				'fields'     => 'ID',
				'number'     => 500,
			)
		);

		$ids = array_values( array_unique( array_filter( array_merge( $forward, array_map( 'intval', $reverse ) ) ) ) );

		self::$cache[ $viewer_id ] = $ids;
		return $ids;
	}

	/**
	 * Whether a viewer must not see content authored by a given member.
	 *
	 * @since 1.7.0
	 *
	 * @param int $viewer_id Current user ID.
	 * @param int $author_id Author of the content.
	 * @return bool
	 */
	public static function is_hidden( int $viewer_id, int $author_id ): bool {
		return $author_id > 0 && in_array( $author_id, self::hidden_author_ids( $viewer_id ), true );
	}
}
