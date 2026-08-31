<?php
/**
 * Post-title search clause builder.
 *
 * @package WP_Career_Board
 * @since   1.7.1
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the `wp_posts.post_title` match used by every WCB search path.
 *
 * Install migration 1.2.6 adds a FULLTEXT index on `wp_posts(post_title)` so
 * listing searches run at index speed instead of scanning on a leading-wildcard
 * LIKE. The jobs and companies endpoints each grew their own copy of the
 * decision — same option gate, same `ft_min_word_len` floor, same boolean-mode
 * escaping — and the admin Applications list never adopted it at all and still
 * full-scanned (Basecamp 10171649758). One builder, three callers.
 *
 * @since 1.7.1
 */
final class TitleSearch {

	/**
	 * MySQL's default `ft_min_word_len`. Below this MATCH() returns nothing
	 * even where a LIKE would match, so short terms take the LIKE path.
	 *
	 * @since 1.7.1
	 * @var int
	 */
	private const MIN_FULLTEXT_LEN = 3;

	/**
	 * Can this term use the FULLTEXT index on the current install?
	 *
	 * @since 1.7.1
	 * @param string $term Raw search term.
	 * @return bool
	 */
	public static function uses_fulltext( string $term ): bool {
		return (bool) get_option( 'wcb_posts_fulltext_supported', false )
			&& strlen( $term ) >= self::MIN_FULLTEXT_LEN;
	}

	/**
	 * Turn a user-typed term into a safe BOOLEAN MODE term.
	 *
	 * Boolean operators the user may have typed are stripped so they cannot
	 * change the query's meaning, and a trailing `*` opts into prefix matching.
	 *
	 * @since 1.7.1
	 * @param string $term Raw search term.
	 * @return string Empty when nothing searchable survives escaping.
	 */
	public static function boolean_term( string $term ): string {
		$bool_term = preg_replace( '/[+\-><()~*\"@&|]/', ' ', $term );
		$bool_term = trim( (string) $bool_term );
		return '' === $bool_term ? '' : $bool_term . '*';
	}

	/**
	 * A prepared SQL boolean expression matching `wp_posts.post_title`.
	 *
	 * Returns MATCH() AGAINST() where the index can serve the term, and the
	 * equivalent LIKE otherwise (FULLTEXT unsupported — MyISAM `wp_posts`, a
	 * read-only replica — or a term below the word-length floor).
	 *
	 * The return value is already run through `$wpdb->prepare()`, so callers
	 * must interpolate it into a query rather than prepare it a second time.
	 *
	 * @since 1.7.1
	 * @param string $term Raw search term.
	 * @return string Empty when the term yields nothing searchable.
	 */
	public static function title_clause( string $term ): string {
		global $wpdb;

		if ( '' === trim( $term ) ) {
			return '';
		}

		if ( self::uses_fulltext( $term ) ) {
			$bool_term = self::boolean_term( $term );
			if ( '' === $bool_term ) {
				return '';
			}
			return $wpdb->prepare(
				"MATCH ({$wpdb->posts}.post_title) AGAINST (%s IN BOOLEAN MODE)",
				$bool_term
			);
		}

		return $wpdb->prepare(
			"{$wpdb->posts}.post_title LIKE %s",
			'%' . $wpdb->esc_like( $term ) . '%'
		);
	}
}
