<?php
/**
 * Canonical bookmark button for archive cards.
 *
 * Shared across Find Jobs, Companies, Find Candidates. Emits a single
 * <button> with the bookmark SVG + Interactivity API click handler.
 * Bookmark state is signalled either reactively (data-wp-class binding
 * against a per-card context value, used by Jobs + Companies) or via a
 * pre-rendered class on first paint (used by Find Candidates while its
 * card context migration is pending).
 *
 * Expected in $wcb_bookmark:
 *   aria_label              string  Pre-translated label
 *                                   ("Save job" / "Save company" / "Save resume").
 *                                   REQUIRED.
 *   aria_label_bind         string  Optional data-wp-bind--aria-label value
 *                                   (e.g. 'state.bookmarkLabel' on Jobs so the
 *                                   label flips to "Remove from saved" after
 *                                   click). Empty = no reactive label.
 *   bookmarked_class_bind   string  Optional data-wp-class--wcb-bookmarked
 *                                   value (e.g. 'context.job.bookmarked').
 *                                   Empty = use static SSR class instead.
 *   bookmarked_ssr          bool    Initial bookmarked-state class for first
 *                                   paint. Used when bookmarked_class_bind is
 *                                   empty (Resumes) so the icon flips
 *                                   correctly before JS hydration.
 *   extra_attrs             string  Pre-escaped extra attributes (e.g. Pro
 *                                   Resumes' `data-resume-id="123"`). Echoed
 *                                   verbatim - caller is responsible for
 *                                   escaping.
 *
 * @package WP_Career_Board
 * @since   1.2.0
 *
 * @var array<string,mixed> $wcb_bookmark
 */

defined( 'ABSPATH' ) || exit;

$wcb_bookmark = wp_parse_args(
	$wcb_bookmark ?? array(),
	array(
		'aria_label'            => '',
		'aria_label_bind'       => '',
		'bookmarked_class_bind' => '',
		'bookmarked_ssr'        => false,
		'extra_attrs'           => '',
	)
);

$wcb_bookmark_static_class = '' === (string) $wcb_bookmark['bookmarked_class_bind'] && ! empty( $wcb_bookmark['bookmarked_ssr'] )
	? ' wcb-bookmarked'
	: '';
?>
<button
	type="button"
	class="wcb-bookmark-btn<?php echo esc_attr( $wcb_bookmark_static_class ); ?>"
	data-wp-on--click="actions.toggleBookmark"
	<?php if ( '' !== (string) $wcb_bookmark['bookmarked_class_bind'] ) : ?>
	data-wp-class--wcb-bookmarked="<?php echo esc_attr( (string) $wcb_bookmark['bookmarked_class_bind'] ); ?>"
	<?php endif; ?>
	<?php if ( '' !== (string) $wcb_bookmark['aria_label_bind'] ) : ?>
	data-wp-bind--aria-label="<?php echo esc_attr( (string) $wcb_bookmark['aria_label_bind'] ); ?>"
	<?php endif; ?>
	aria-label="<?php echo esc_attr( (string) $wcb_bookmark['aria_label'] ); ?>"
	<?php echo $wcb_bookmark['extra_attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller-escaped pre-built attribute string. ?>
>
	<?php echo \WCB\Core\Icon::svg( 'bookmark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?>
</button>
