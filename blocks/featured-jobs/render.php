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

$wcb_per_page = (int) ( $attributes['perPage'] ?? 3 );

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
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-featured-jobs' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<h2 class="wcb-featured-title"><?php esc_html_e( 'Featured Jobs', 'wp-career-board' ); ?></h2>

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
				$wcb_feat_location  = is_wp_error( $wcb_feat_loc_terms ) ? '' : implode( ', ', $wcb_feat_loc_terms );
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
</div>
