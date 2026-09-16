<?php
/**
 * Text helpers for turning authored HTML into plain-text summaries.
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
 * Plain-text derivations of user-authored HTML.
 *
 * @since 1.7.1
 */
final class Text {

	/**
	 * Trim authored HTML to a word-count summary, without fusing blocks.
	 *
	 * `wp_trim_words()` strips tags with `strip_tags()`, which removes the
	 * markup and joins whatever sat either side of it with no separator. Two
	 * paragraphs — `<p>…the Nordics.</p><p>Our teams…</p>` — come out as
	 * "the Nordics.Our teams". The same applies to `</li><li>`, `<br>` and
	 * every other block boundary, so any excerpt of a real job description ran
	 * words together at every paragraph and bullet.
	 *
	 * Seeding a space before every `<` guarantees a word break at each tag;
	 * `wp_trim_words()` then collapses the resulting whitespace run.
	 *
	 * This existed inline at four call sites — the job card excerpt, the
	 * single-job company blurb, the og:description meta and the REST excerpt
	 * the mobile app reads — three of which were missing the guard entirely.
	 *
	 * @since 1.7.1
	 *
	 * @param string $html  Authored HTML.
	 * @param int    $words Maximum words to keep.
	 * @param string $more  Trailing string when truncated.
	 * @return string Plain text, safe to escape at the point of output.
	 */
	public static function excerpt( string $html, int $words, string $more = '&hellip;' ): string {
		return wp_trim_words( str_replace( '<', ' <', $html ), $words, $more );
	}
}
