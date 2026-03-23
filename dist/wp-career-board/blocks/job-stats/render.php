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
		'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>',
	);
}

if ( $wcb_show_companies ) {
	$wcb_stats[] = array(
		'count' => (int) wp_count_posts( 'wcb_company' )->publish,
		'label' => __( 'Companies', 'wp-career-board' ),
		'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
	);
}

if ( $wcb_show_candidates ) {
	$wcb_stats[] = array(
		'count' => (int) wp_count_posts( 'wcb_resume' )->publish,
		'label' => __( 'Candidates', 'wp-career-board' ),
		'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
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
				<?php echo $wcb_stat['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
			<span class="wcb-stat-count"><?php echo esc_html( number_format_i18n( $wcb_stat['count'] ) ); ?></span>
			<span class="wcb-stat-label"><?php echo esc_html( $wcb_stat['label'] ); ?></span>
		</div>
	<?php endforeach; ?>
</div>
