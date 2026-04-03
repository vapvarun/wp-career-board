<?php
/**
 * Block render: wcb/job-search — server-renders the search form and seeds Interactivity API state.
 *
 * WordPress injects:
 *   $attributes  (array)    Block attributes defined in block.json.
 *   $content     (string)   Inner block content (empty for this block).
 *   $block       (WP_Block) Block instance object.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$wcb_search_query = isset( $_GET['wcb_search'] )
	? sanitize_text_field( wp_unslash( $_GET['wcb_search'] ) )
	: '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// Search block owns 'query' only; job-filters block owns 'filters'.
wp_interactivity_state(
	'wcb-search',
	array(
		'query' => $wcb_search_query,
	)
);
?>
<div
	<?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-search"
>
	<form
		class="wcb-search-form"
		role="search"
		data-wp-on--submit="actions.search"
	>
		<label class="screen-reader-text" for="wcb-search-input">
			<?php esc_html_e( 'Search jobs', 'wp-career-board' ); ?>
		</label>
		<input
			id="wcb-search-input"
			type="search"
			class="wcb-search-input"
			name="wcb_search"
			aria-label="<?php esc_attr_e( 'Search jobs', 'wp-career-board' ); ?>"
			placeholder="<?php esc_attr_e( 'Search jobs…', 'wp-career-board' ); ?>"
			value="<?php echo esc_attr( $wcb_search_query ); ?>"
			data-wp-bind--value="state.query"
			data-wp-on--input="actions.updateQuery"
		/>
		<button type="submit" class="wcb-search-button">
			<i data-lucide="search" aria-hidden="true"></i>
			<?php esc_html_e( 'Search', 'wp-career-board' ); ?>
		</button>
	</form>
</div>
