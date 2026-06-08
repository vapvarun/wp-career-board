<?php
/**
 * Server-side SVG icon helper.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side renderer for the small subset of Lucide icons that appear
 * inside Interactivity-bound `data-wp-each` templates.
 *
 * Why this exists: the frontend ships `lucide.min.js`, which swaps every
 * `<i data-lucide="name">` placeholder with an `<svg>` after page load.
 * That works fine for static pages, but inside `data-wp-each` the
 * Interactivity hydrator has already captured the placeholder `<i>` into
 * its vnode tree by the time Lucide swaps. Any subsequent state-driven
 * re-render then logs a hydration mismatch ("Expected DOM node of type
 * 'i' but found 'svg'") for every cloned row.
 *
 * The fix is to emit the final `<svg>` server-side so the vnode tree and
 * the live DOM agree from the first hydration cycle. We do that for the
 * icons that appear inside `data-wp-each` only (this helper carries the
 * path data for that small set). Icons outside Interactivity scopes keep
 * using `<i data-lucide>` + Lucide JS swap because the cost there is
 * one-time DOM mutation, not a hydrator collision.
 *
 * Adding a new icon to this helper:
 *   1. Look up the path data at https://lucide.dev/icons/&lt;name&gt; (MIT licensed).
 *   2. Add a key to `paths()` returning the inner SVG markup.
 *   3. Replace the relevant `<i data-lucide="name" ...>` site with
 *      `Icon::svg( 'name', [ 'class' => 'wcb-...' ] )`.
 *
 * @since 1.1.0
 */
final class Icon {

	/**
	 * Prevent instantiation; everything here is static.
	 *
	 * @since 1.1.0
	 */
	private function __construct() {}

	/**
	 * Inner-SVG path data for each supported icon.
	 *
	 * Source: lucide-static@0.460 (MIT). Values are the markup that goes
	 * INSIDE `<svg>` — the wrapper + width/height/stroke attributes are
	 * added by `svg()`.
	 *
	 * @since 1.1.0
	 * @return array<string,string> Map of icon-name → inner SVG markup.
	 */
	private static function paths(): array {
		return array(
			'award'          => '<path d="m15.477 12.89 1.515 8.526a.5.5 0 0 1-.81.47l-3.58-2.687a1 1 0 0 0-1.197 0l-3.586 2.686a.5.5 0 0 1-.81-.469l1.514-8.526" /> <circle cx="12" cy="8" r="6" />',
			'bell'           => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /> <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />',
			'bookmark'       => '<path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z" />',
			'briefcase'      => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" /> <rect width="20" height="14" x="2" y="6" rx="2" />',
			'building'       => '<rect width="16" height="20" x="4" y="2" rx="2" ry="2" /> <path d="M9 22v-4h6v4" /> <path d="M8 6h.01" /> <path d="M16 6h.01" /> <path d="M12 6h.01" /> <path d="M12 10h.01" /> <path d="M12 14h.01" /> <path d="M16 10h.01" /> <path d="M16 14h.01" /> <path d="M8 10h.01" /> <path d="M8 14h.01" />',
			'check'          => '<path d="M20 6 9 17l-5-5" />',
			'chevron-down'   => '<path d="m6 9 6 6 6-6" />',
			'chevron-right'  => '<path d="m9 18 6-6-6-6" />',
			'download'       => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /> <polyline points="7 10 12 15 17 10" /> <line x1="12" x2="12" y1="15" y2="3" />',
			'external-link'  => '<path d="M15 3h6v6" /> <path d="M10 14 21 3" /> <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />',
			'flag'           => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z" /> <line x1="4" x2="4" y1="22" y2="15" />',
			'globe'          => '<circle cx="12" cy="12" r="10" /> <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" /> <path d="M2 12h20" />',
			'graduation-cap' => '<path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z" /> <path d="M22 10v6" /> <path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5" />',
			'inbox'          => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12" /> <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />',
			'languages'      => '<path d="m5 8 6 6" /> <path d="m4 14 6-6 2-3" /> <path d="M2 5h12" /> <path d="M7 2h1" /> <path d="m22 22-5-10-5 10" /> <path d="M14 18h6" />',
			'layout-grid'    => '<rect width="7" height="7" x="3" y="3" rx="1" /> <rect width="7" height="7" x="14" y="3" rx="1" /> <rect width="7" height="7" x="14" y="14" rx="1" /> <rect width="7" height="7" x="3" y="14" rx="1" />',
			'link'           => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" /> <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />',
			'linkedin'       => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z" /> <rect width="4" height="12" x="2" y="9" /> <circle cx="4" cy="4" r="2" />',
			'list'           => '<path d="M3 12h.01" /> <path d="M3 18h.01" /> <path d="M3 6h.01" /> <path d="M8 12h13" /> <path d="M8 18h13" /> <path d="M8 6h13" />',
			'locate'         => '<line x1="2" x2="5" y1="12" y2="12" /> <line x1="19" x2="22" y1="12" y2="12" /> <line x1="12" x2="12" y1="2" y2="5" /> <line x1="12" x2="12" y1="19" y2="22" /> <circle cx="12" cy="12" r="7" />',
			'map-pin'        => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" /> <circle cx="12" cy="10" r="3" />',
			'pencil'         => '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z" /> <path d="m15 5 4 4" />',
			'plus'           => '<path d="M5 12h14" /> <path d="M12 5v14" />',
			'plus-circle'    => '<circle cx="12" cy="12" r="10" /> <path d="M8 12h8" /> <path d="M12 8v8" />',
			'printer'        => '<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /> <path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6" /> <rect x="6" y="14" width="12" height="8" rx="1" />',
			'search'         => '<circle cx="11" cy="11" r="8" /> <path d="m21 21-4.3-4.3" />',
			'send'           => '<path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z" /> <path d="m21.854 2.147-10.94 10.939" />',
			'star'           => '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z" />',
			'trash-2'        => '<path d="M3 6h18" /> <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" /> <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /> <line x1="10" x2="10" y1="11" y2="17" /> <line x1="14" x2="14" y1="11" y2="17" />',
			'twitter'        => '<path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z" />',
			'user'           => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /> <circle cx="12" cy="7" r="4" />',
			'users'          => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /> <circle cx="9" cy="7" r="4" /> <path d="M22 21v-2a4 4 0 0 0-3-3.87" /> <path d="M16 3.13a4 4 0 0 1 0 7.75" />',
			'zap'            => '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z" />',
		);
	}

	/**
	 * Render an SVG icon as inline markup.
	 *
	 * Returns an escaped, ready-to-echo string. Unknown icon names return
	 * an empty string (silent fail-safe — surfaces as a missing visual,
	 * not a fatal). Use this only for icons inside Interactivity-bound
	 * `data-wp-each` templates; everywhere else, keep the lighter
	 * `<i data-lucide>` placeholder + Lucide JS swap.
	 *
	 * Default attrs match the Lucide JS output: 24x24, currentColor stroke
	 * with rounded caps, `aria-hidden`, `class="wcb-icon"`. Override via
	 * the `$attrs` map; a `class` key concatenates with the default class.
	 *
	 * @since 1.1.0
	 *
	 * @param string               $name  Icon name (e.g. 'bookmark').
	 * @param array<string,string> $attrs Extra attributes (`class`, `width`,
	 *                                    `height`, `aria-label`, etc.).
	 * @return string Inline SVG markup, already escaped for direct echo.
	 */
	public static function svg( string $name, array $attrs = array() ): string {
		$paths = self::paths();
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		$default_attrs = array(
			'xmlns'           => 'http://www.w3.org/2000/svg',
			'width'           => '24',
			'height'          => '24',
			'viewBox'         => '0 0 24 24',
			'fill'            => 'none',
			'stroke'          => 'currentColor',
			'stroke-width'    => '2',
			'stroke-linecap'  => 'round',
			'stroke-linejoin' => 'round',
			'class'           => 'wcb-icon wcb-icon--' . $name,
			'aria-hidden'     => 'true',
		);

		// Merge: caller's `class` is appended to the default; everything
		// else overrides outright.
		if ( isset( $attrs['class'] ) ) {
			$attrs['class'] = $default_attrs['class'] . ' ' . $attrs['class'];
		}
		$merged = array_merge( $default_attrs, $attrs );

		$attr_html = '';
		foreach ( $merged as $key => $value ) {
			$attr_html .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}

		// Path data is plugin-controlled (see paths()) so safe to inline.
		// `wp_kses` would strip valid SVG path attributes; we don't apply
		// it here. Adding a new icon requires touching paths() above.
		return '<svg' . $attr_html . '>' . $paths[ $name ] . '</svg>';
	}
}
