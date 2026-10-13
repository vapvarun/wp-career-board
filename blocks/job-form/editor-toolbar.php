<?php
/**
 * Always-visible formatting toolbar for a job description editor.
 *
 * Included inside a `.wcb-editor` by the job-form and job-form-simple
 * render.php; assets/js/wcb-editor.js wires the buttons to the editor.
 *
 * @package WP_Career_Board
 * @since   1.8.1
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wcb-editor-toolbar" role="group" aria-label="<?php esc_attr_e( 'Text formatting', 'wp-career-board' ); ?>">
	<button type="button" class="wcb-editor-toolbar__btn" data-wcb-format="bold" aria-pressed="false" aria-label="<?php esc_attr_e( 'Bold', 'wp-career-board' ); ?>" title="<?php esc_attr_e( 'Bold', 'wp-career-board' ); ?>"><b aria-hidden="true"><?php echo esc_html_x( 'B', 'bold button glyph', 'wp-career-board' ); ?></b></button>
	<button type="button" class="wcb-editor-toolbar__btn" data-wcb-format="italic" aria-pressed="false" aria-label="<?php esc_attr_e( 'Italic', 'wp-career-board' ); ?>" title="<?php esc_attr_e( 'Italic', 'wp-career-board' ); ?>"><i aria-hidden="true"><?php echo esc_html_x( 'I', 'italic button glyph', 'wp-career-board' ); ?></i></button>
	<button type="button" class="wcb-editor-toolbar__btn" data-wcb-format="list" aria-pressed="false" aria-label="<?php esc_attr_e( 'Bulleted list', 'wp-career-board' ); ?>" title="<?php esc_attr_e( 'Bulleted list', 'wp-career-board' ); ?>"><?php echo \WCB\Core\Icon::svg( 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?></button>
	<button type="button" class="wcb-editor-toolbar__btn" data-wcb-format="link" aria-expanded="false" aria-label="<?php esc_attr_e( 'Link', 'wp-career-board' ); ?>" title="<?php esc_attr_e( 'Link', 'wp-career-board' ); ?>"><?php echo \WCB\Core\Icon::svg( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?></button>
</div>
<div class="wcb-editor-link" hidden>
	<label class="wcb-editor-link__field">
		<span class="wcb-sr-only"><?php esc_html_e( 'Link address', 'wp-career-board' ); ?></span>
		<input type="url" class="wcb-editor-link__input" placeholder="https://" autocomplete="url" />
	</label>
	<button type="button" class="wcb-btn wcb-btn--primary wcb-btn--sm" data-wcb-link="apply"><?php esc_html_e( 'Add link', 'wp-career-board' ); ?></button>
	<button type="button" class="wcb-btn wcb-btn--ghost wcb-btn--sm" data-wcb-link="cancel"><?php esc_html_e( 'Cancel', 'wp-career-board' ); ?></button>
</div>
