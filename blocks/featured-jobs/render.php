<?php
/**
 * Block render: wcb/featured-jobs — static server-rendered featured job grid.
 *
 * WordPress injects:
 *   $attributes  (array)    Block attributes defined in block.json.
 *   $content     (string)   Inner block content (empty for this block).
 *   $block       (WP_Block) Block instance object.
 *
 * No Interactivity API — content is fully static.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$wcb_per_page     = (int) ( $attributes['perPage'] ?? 3 );
$wcb_title        = trim( (string) ( $attributes['title'] ?? '' ) );
$wcb_show_all     = (bool) ( $attributes['showViewAll'] ?? true );
$wcb_view_all_url = trim( (string) ( $attributes['viewAllUrl'] ?? '' ) );

if ( ! $wcb_view_all_url ) {
	$wcb_archive_page_id = \WCB\Admin\Settings::int( 'jobs_archive_page', 0 );
	$wcb_view_all_url    = $wcb_archive_page_id > 0
		? (string) get_permalink( $wcb_archive_page_id )
		: '';
}

$wcb_featured_posts = get_posts(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'publish',
		'numberposts' => $wcb_per_page,
		'meta_key'    => '_wcb_featured', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'  => '1',            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	)
);

if ( empty( $wcb_featured_posts ) ) {
	if ( current_user_can( 'edit_posts' ) ) { // phpcs:ignore -- admin-UI empty-state hint, not a security gate; no Abilities API equivalent for "can edit posts in general".
		?>
		<div <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-featured-jobs' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="wcb-featured-empty">
				<i data-lucide="inbox" aria-hidden="true"></i>
				<p><?php esc_html_e( 'No featured jobs to display. Mark jobs as featured in the editor.', 'wp-career-board' ); ?></p>
			</div>
		</div>
		<?php
	}
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-featured-jobs' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<h2 class="wcb-featured-title">
		<?php echo esc_html( $wcb_title ? $wcb_title : __( 'Featured Jobs', 'wp-career-board' ) ); ?>
	</h2>

	<div class="wcb-featured-grid">
		<?php foreach ( $wcb_featured_posts as $wcb_post ) : ?>
			<article class="wcb-featured-card">
				<h3 class="wcb-featured-card-title">
					<a href="<?php echo esc_url( get_permalink( $wcb_post->ID ) ); ?>">
						<?php echo esc_html( $wcb_post->post_title ); ?>
					</a>
				</h3>

				<?php
				$wcb_feat_company = (string) get_post_meta( $wcb_post->ID, '_wcb_company_name', true );
				if ( $wcb_feat_company ) :
					?>
					<p class="wcb-featured-company"><?php echo esc_html( $wcb_feat_company ); ?></p>
				<?php endif; ?>

				<?php
				$wcb_feat_loc_terms = wp_get_object_terms( $wcb_post->ID, 'wcb_location', array( 'fields' => 'names' ) );
				/*
				 * The separator between list items is user-facing punctuation, not
				 * markup: ja/zh want the ideographic comma, ar wants the Arabic
				 * comma, and several locales drop the trailing space. Reuse WP core's
				 * locale-aware separator (wp_get_list_item_separator(), WP 6.0+) so it
				 * resolves against core's own catalog instead of an untranslated
				 * plugin-domain key.
				 */
				$wcb_feat_location = is_wp_error( $wcb_feat_loc_terms )
					? ''
					: implode( wp_get_list_item_separator(), $wcb_feat_loc_terms );
				if ( $wcb_feat_location ) :
					?>
					<p class="wcb-featured-location"><?php echo esc_html( $wcb_feat_location ); ?></p>
				<?php endif; ?>

				<a class="wcb-featured-link" href="<?php echo esc_url( get_permalink( $wcb_post->ID ) ); ?>">
					<?php esc_html_e( 'View Job', 'wp-career-board' ); ?>
				</a>
			</article>
		<?php endforeach; ?>
	</div>

	<?php if ( $wcb_show_all && $wcb_view_all_url ) : ?>
		<a class="wcb-widget-view-all" href="<?php echo esc_url( $wcb_view_all_url ); ?>">
			<?php esc_html_e( 'View all jobs', 'wp-career-board' ); ?>
			<span class="wcb-widget-view-all-arrow" aria-hidden="true"><?php echo is_rtl() ? '&larr;' : '&rarr;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML entity, direction-aware. ?></span>
		</a>
	<?php endif; ?>
</div>
