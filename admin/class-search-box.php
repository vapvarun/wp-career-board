<?php
/**
 * Search box for the Career Board admin list screens.
 *
 * @package WP_Career_Board
 * @since   1.8.1
 */

declare( strict_types=1 );

namespace WCB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One search box for every list screen, with a placeholder saying what it
 * searches (core's WP_List_Table::search_box() cannot take one).
 *
 * @since 1.8.1
 */
trait SearchBox {

	/**
	 * Print the search box. Call inside the list form, before display().
	 *
	 * @param string $id          Input ID prefix, e.g. "wcb-job".
	 * @param string $button      Button and screen-reader label, e.g. "Search Jobs".
	 * @param string $placeholder What the box searches, e.g. "Job title…".
	 * @return void
	 */
	protected function render_search_box( string $id, string $button, string $placeholder ): void {
		$value = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search term.
		?>
		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-search-input"><?php echo esc_html( $button ); ?></label>
			<input type="search" id="<?php echo esc_attr( $id ); ?>-search-input" name="s" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>">
			<?php submit_button( $button, '', '', false, array( 'id' => 'search-submit' ) ); ?>
		</p>
		<?php
	}
}
