<?php
/**
 * Block render: wp-career-board/recent-jobs — static sidebar widget.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$wcb_count        = max( 1, (int) ( $attributes['count'] ?? 5 ) );
$wcb_title        = trim( (string) ( $attributes['title'] ?? '' ) );
$wcb_show_all     = (bool) ( $attributes['showViewAll'] ?? true );
$wcb_view_all_url = trim( (string) ( $attributes['viewAllUrl'] ?? '' ) );

if ( ! $wcb_view_all_url ) {
	$wcb_settings     = (array) get_option( 'wcb_settings', array() );
	$wcb_view_all_url = ! empty( $wcb_settings['jobs_archive_page'] )
		? (string) get_permalink( (int) $wcb_settings['jobs_archive_page'] )
		: '';
}

$wcb_jobs = get_posts(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'publish',
		'numberposts' => $wcb_count,
		'orderby'     => 'date',
		'order'       => 'DESC',
	)
);

if ( empty( $wcb_jobs ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p class="wcb-admin-empty-state" style="padding:1rem;color:#6b7280;font-style:italic;text-align:center;">' . esc_html__( 'No recent jobs to display.', 'wp-career-board' ) . '</p>';
	}
	return;
}

// Pre-fetch company thumbnails to avoid N+1 per card.
$wcb_author_ids  = array_unique( array_map( fn( $p ) => (int) $p->post_author, $wcb_jobs ) );
$wcb_company_map = array(); // Maps author ID to company thumbnail URL (empty string when absent).
foreach ( $wcb_author_ids as $wcb_aid ) {
	$wcb_cid                     = (int) get_user_meta( $wcb_aid, '_wcb_company_id', true );
	$wcb_thumb                   = $wcb_cid ? (string) get_the_post_thumbnail_url( $wcb_cid, 'thumbnail' ) : '';
	$wcb_company_map[ $wcb_aid ] = $wcb_thumb;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-recent-jobs' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<div class="wcb-widget-header">
		<h2 class="wcb-widget-title">
			<?php echo esc_html( $wcb_title ? $wcb_title : __( 'Recent Jobs', 'wp-career-board' ) ); ?>
		</h2>
		<?php if ( $wcb_show_all && $wcb_view_all_url ) : ?>
			<a class="wcb-widget-view-all" href="<?php echo esc_url( $wcb_view_all_url ); ?>">
				<?php esc_html_e( 'View all →', 'wp-career-board' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<ul class="wcb-job-widget-list">
		<?php foreach ( $wcb_jobs as $wcb_job ) : ?>
			<?php
			$wcb_company_name = (string) get_post_meta( $wcb_job->ID, '_wcb_company_name', true );
			$wcb_thumb_url    = $wcb_company_map[ (int) $wcb_job->post_author ] ?? '';
			$wcb_initial      = $wcb_company_name ? strtoupper( mb_substr( $wcb_company_name, 0, 1 ) ) : '?';
			$wcb_loc_terms    = wp_get_object_terms( $wcb_job->ID, 'wcb_location', array( 'fields' => 'names' ) );
			$wcb_location     = is_wp_error( $wcb_loc_terms ) ? '' : implode( ', ', $wcb_loc_terms );
			$wcb_type_terms   = wp_get_object_terms( $wcb_job->ID, 'wcb_job_type', array( 'fields' => 'names' ) );
			$wcb_job_type     = is_wp_error( $wcb_type_terms ) ? '' : ( $wcb_type_terms[0] ?? '' );
			$wcb_posted_ago   = human_time_diff( (int) get_post_time( 'U', false, $wcb_job ), time() );
			?>
			<li class="wcb-job-widget-item">
				<a class="wcb-job-widget-link" href="<?php echo esc_url( get_permalink( $wcb_job->ID ) ); ?>">
					<span class="wcb-job-widget-logo" aria-hidden="true">
						<?php if ( $wcb_thumb_url ) : ?>
							<img src="<?php echo esc_url( $wcb_thumb_url ); ?>" alt="" width="16" height="16" loading="lazy" />
						<?php else : ?>
							<span class="wcb-job-widget-initial"><?php echo esc_html( $wcb_initial ); ?></span>
						<?php endif; ?>
					</span>
					<span class="wcb-job-widget-body">
						<span class="wcb-job-widget-name"><?php echo esc_html( $wcb_job->post_title ); ?></span>
						<?php if ( $wcb_company_name ) : ?>
							<span class="wcb-job-widget-company"><?php echo esc_html( $wcb_company_name ); ?></span>
						<?php endif; ?>
						<span class="wcb-job-widget-meta">
							<?php if ( $wcb_location ) : ?>
								<span class="wcb-badge wcb-badge--location"><?php echo esc_html( $wcb_location ); ?></span>
							<?php endif; ?>
							<?php if ( $wcb_job_type ) : ?>
								<span class="wcb-badge wcb-badge--type"><?php echo esc_html( $wcb_job_type ); ?></span>
							<?php endif; ?>
							<span class="wcb-job-widget-age">
								<?php
								printf(
									/* translators: %s: human-readable time difference e.g. "3 days" */
									esc_html__( '%s ago', 'wp-career-board' ),
									esc_html( $wcb_posted_ago )
								);
								?>
							</span>
						</span>
					</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

</div>
