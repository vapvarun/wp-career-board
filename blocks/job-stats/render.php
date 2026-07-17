<?php
/**
 * Block render: wp-career-board/job-stats - stat strip.
 *
 * i18n contract for this block (read before "simplifying" anything below):
 *
 *   - Every count is known server-side, so each label is pluralised HERE with
 *     _n() against the REAL count. Never seed a singular/plural PAIR and pick
 *     between them with `count === 1`: _n( …, 2, … ) freezes the n=2 form at
 *     render time, which is only right in 2-form locales. Polish needs distinct
 *     forms for 2/5/22, Russian for 1/2/5, Arabic has six.
 *   - Counts run through number_format_i18n(), never a raw echo and never a
 *     JS toLocaleString() with no locale argument.
 *   - The count and the label are two typographic elements of a stat tile, not
 *     a sentence glued together with `+`. The numeral is always rendered
 *     adjacent to the noun, so _n() picks the form the numeral requires. There
 *     is nothing here for a translator to reorder, so no sprintf() wrapper.
 *   - This block has no viewScript, no wp_interactivity_state() and no client
 *     side strings, so there is no 'i18n' state array to keep in sync.
 *   - The icon ids ("briefcase", "building-2", "users") are machine-facing
 *     identifiers passed to `Icon::svg()` for inline SVG output. Like the pro
 *     plugin's XML job feed, they are deliberately NOT translated.
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
	$wcb_count   = (int) wp_count_posts( 'wcb_job' )->publish;
	$wcb_stats[] = array(
		'count' => $wcb_count,
		'label' => _n( 'Job', 'Jobs', $wcb_count, 'wp-career-board' ),
		'icon'  => 'briefcase',
	);
}

if ( $wcb_show_companies ) {
	$wcb_count   = (int) wp_count_posts( 'wcb_company' )->publish;
	$wcb_stats[] = array(
		'count' => $wcb_count,
		'label' => _n( 'Company', 'Companies', $wcb_count, 'wp-career-board' ),
		'icon'  => 'building-2',
	);
}

if ( $wcb_show_candidates ) {
	$wcb_count   = (int) wp_count_posts( 'wcb_resume' )->publish;
	$wcb_stats[] = array(
		'count' => $wcb_count,
		'label' => _n( 'Candidate', 'Candidates', $wcb_count, 'wp-career-board' ),
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
				<?php echo \WCB\Core\Icon::svg( $wcb_stat['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?>
			</span>
			<span class="wcb-stat-count"><?php echo esc_html( number_format_i18n( $wcb_stat['count'] ) ); ?></span>
			<span class="wcb-stat-label"><?php echo esc_html( $wcb_stat['label'] ); ?></span>
		</div>
	<?php endforeach; ?>
</div>
