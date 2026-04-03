<?php
/**
 * Block render: wp-career-board/job-stats — stat strip.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$wcb_show_jobs       = (bool) ( $attributes['showJobs'] ?? true );
$wcb_show_companies  = (bool) ( $attributes['showCompanies'] ?? true );
$wcb_show_candidates = (bool) ( $attributes['showCandidates'] ?? true );

$wcb_stats = array();

if ( $wcb_show_jobs ) {
	$wcb_stats[] = array(
		'count' => (int) wp_count_posts( 'wcb_job' )->publish,
		'label' => __( 'Jobs', 'wp-career-board' ),
		'icon'  => 'briefcase',
	);
}

if ( $wcb_show_companies ) {
	$wcb_stats[] = array(
		'count' => (int) wp_count_posts( 'wcb_company' )->publish,
		'label' => __( 'Companies', 'wp-career-board' ),
		'icon'  => 'building-2',
	);
}

if ( $wcb_show_candidates ) {
	$wcb_stats[] = array(
		'count' => (int) wp_count_posts( 'wcb_resume' )->publish,
		'label' => __( 'Candidates', 'wp-career-board' ),
		'icon'  => 'users',
	);
}

if ( empty( $wcb_stats ) ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-job-stats' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php foreach ( $wcb_stats as $wcb_stat ) : ?>
		<div class="wcb-stat-item">
			<span class="wcb-stat-icon">
				<i data-lucide="<?php echo esc_attr( $wcb_stat['icon'] ); ?>" aria-hidden="true"></i>
			</span>
			<span class="wcb-stat-count"><?php echo esc_html( number_format_i18n( $wcb_stat['count'] ) ); ?></span>
			<span class="wcb-stat-label"><?php echo esc_html( $wcb_stat['label'] ); ?></span>
		</div>
	<?php endforeach; ?>
</div>
