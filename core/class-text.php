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
		// Decode after trimming: authored text keeps entities (&#039;) that would
		// otherwise show literally once the caller escapes it for output.
		return html_entity_decode( wp_trim_words( str_replace( '<', ' <', strip_shortcodes( $html ) ), $words, $more ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Show dash lists typed in the rich editor as bullet lists.
	 *
	 * The job and company editors start a new paragraph on every Enter, so a
	 * list typed as "- item" lines is stored as <p>- item</p><p>- item</p>, and
	 * imported text often arrives as one paragraph: "You bring: - a - b - c".
	 * Both rendered as dash-strewn prose. Display only: stored content is not
	 * rewritten, and prose with a single dash is left alone.
	 *
	 * @since 1.8.1
	 *
	 * @param string $html Authored HTML.
	 * @return string HTML with those runs as <ul> lists.
	 */
	public static function dash_lists( string $html ): string {
		$item = '/^\s*[-*\x{2022}]\s+/u';
		// 1. Consecutive "<p>- item</p>" paragraphs become one list.
		$html = (string) preg_replace_callback(
			'#(?:<p>\s*[-*\x{2022}]\s+.*?</p>\s*)+#su',
			static function ( array $m ) use ( $item ): string {
				preg_match_all( '#<p>(.*?)</p>#s', $m[0], $paras );
				return '<ul><li>' . implode( '</li><li>', array_map( static fn ( $p ) => trim( (string) preg_replace( $item, '', $p ) ), $paras[1] ) ) . '</li></ul>';
			},
			$html
		);
		// 2. "Intro: - a - b" in one paragraph becomes the intro plus a list
		// (two or more items, after a colon, so ordinary dashes stay prose).
		return (string) preg_replace_callback(
			'#<p>((?:(?!</p>).)*?:\s*(?:</(?:b|strong|em)>)?)\s+-\s+((?:(?!</p>).)+?\s-\s(?:(?!</p>).)+?)</p>#su',
			static fn ( array $m ): string => '<p>' . trim( $m[1] ) . '</p><ul><li>' . implode( '</li><li>', array_map( 'trim', preg_split( '/\s+-\s+/', $m[2] ) ) ) . '</li></ul>',
			$html
		);
	}
}
