<?php
/**
 * Block render: wp-career-board/job-search — server-renders the search form and seeds Interactivity API state.
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

// The keyword under any of its URL names (search, wcb_search, s), the same
// one the job listings filter by, so the box shows what the results use.
$wcb_search_query = trim( (string) ( \WCB\Modules\Jobs\JobSearch::from_url()['search'] ?? '' ) );

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
	<?php echo \WCB\Core\ArchiveHeading::render( 'wcb_job', 'jobs_archive_page' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
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
			<?php echo \WCB\Core\Icon::svg( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?>
			<?php esc_html_e( 'Search', 'wp-career-board' ); ?>
		</button>
	</form>
</div>
