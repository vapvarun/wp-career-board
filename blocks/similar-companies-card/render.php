<?php
/**
 * Block render: wp-career-board/similar-companies-card - sidebar card listing
 * companies in the same industry / location.
 *
 * Resolves the current company via:
 *   1. The `companyId` block attribute (standalone use on any page).
 *   2. Otherwise the queried post on `is_singular( 'wcb_company' )` pages
 *      (the company-profile sidebar context).
 *
 * @package WP_Career_Board
 * @since   1.1.1
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$wcb_title      = trim( (string) ( $attributes['title'] ?? '' ) );
$wcb_count      = max( 1, min( 10, (int) ( $attributes['count'] ?? 5 ) ) );
$wcb_current_id = (int) ( $attributes['companyId'] ?? 0 );

if ( '' === $wcb_title ) {
	$wcb_title = __( 'Similar Companies', 'wp-career-board' );
}

// Auto-resolve current company id from query context when not set explicitly.
if ( ! $wcb_current_id && is_singular( 'wcb_company' ) ) {
	$wcb_queried = get_queried_object();
	if ( $wcb_queried instanceof \WP_Post ) {
		$wcb_current_id = (int) $wcb_queried->ID;
	}
}

$wcb_industry = $wcb_current_id
	? (string) get_post_meta( $wcb_current_id, '_wcb_industry', true )
	: '';

$wcb_query_args = array(
	'post_type'      => 'wcb_company',
	'post_status'    => 'publish',
	'posts_per_page' => $wcb_count,
	'orderby'        => 'rand',
	'no_found_rows'  => true,
);

if ( $wcb_current_id ) {
	$wcb_query_args['post__not_in'] = array( $wcb_current_id );
}

if ( '' !== $wcb_industry ) {
	$wcb_query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		array(
			'key'   => '_wcb_industry',
			'value' => $wcb_industry,
		),
	);
}

$wcb_companies = get_posts( $wcb_query_args );

// Empty state: keep the sidebar slot visible for admins (so they know the
// block rendered and can fix the lack of matching companies). Front-end
// visitors see nothing, same pattern as wcb/recent-jobs.
if ( empty( $wcb_companies ) ) {
	if ( current_user_can( 'edit_posts' ) ) { // phpcs:ignore -- admin-UI empty-state hint, not a security gate.
		?>
		<aside <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-cp-side-card wcb-similar-companies-card wcb-similar-companies-card--empty' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<h3 class="wcb-cp-side-card__title"><?php echo esc_html( $wcb_title ); ?></h3>
			<div class="wcb-similar-companies-card__empty">
				<i data-lucide="building" aria-hidden="true"></i>
				<p><?php esc_html_e( 'No similar companies found yet.', 'wp-career-board' ); ?></p>
			</div>
		</aside>
		<?php
	}
	return;
}

?>
<aside <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-cp-side-card wcb-similar-companies-card' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<h3 class="wcb-cp-side-card__title">
		<i data-lucide="building" aria-hidden="true"></i>
		<?php echo esc_html( $wcb_title ); ?>
	</h3>
	<ul class="wcb-similar-companies-card__list">
	<?php
	foreach ( $wcb_companies as $wcb_company ) :
		$wcb_logo    = (string) get_the_post_thumbnail_url( $wcb_company->ID, 'thumbnail' );
		$wcb_loc     = (string) get_post_meta( $wcb_company->ID, '_wcb_hq_location', true );
		$wcb_perma   = (string) get_permalink( $wcb_company->ID );
		// Prefer mb_strtoupper over byte-based strtoupper so non-ASCII initials
		// uppercase correctly ("ärzte" -> "Ä", not "ä"). mb_substr is always
		// available (WordPress polyfills it in wp-includes/compat.php), but
		// mb_strtoupper is NOT polyfilled, so guard it and fall back to
		// strtoupper on PHP builds without ext-mbstring.
		$wcb_first   = mb_substr( $wcb_company->post_title, 0, 1 );
		$wcb_initial = function_exists( 'mb_strtoupper' )
			? mb_strtoupper( $wcb_first )
			: strtoupper( $wcb_first );
		?>
		<li class="wcb-similar-companies-card__item">
			<a class="wcb-similar-companies-card__link" href="<?php echo esc_url( $wcb_perma ); ?>">
				<?php if ( $wcb_logo ) : ?>
					<img class="wcb-similar-companies-card__logo" src="<?php echo esc_url( $wcb_logo ); ?>" alt="" loading="lazy" />
				<?php else : ?>
					<span class="wcb-similar-companies-card__initial" aria-hidden="true"><?php echo esc_html( $wcb_initial ); ?></span>
				<?php endif; ?>
				<span class="wcb-similar-companies-card__body">
					<span class="wcb-similar-companies-card__name"><?php echo esc_html( $wcb_company->post_title ); ?></span>
					<?php if ( $wcb_loc ) : ?>
						<span class="wcb-similar-companies-card__meta">
							<i data-lucide="map-pin" aria-hidden="true"></i>
							<?php echo esc_html( $wcb_loc ); ?>
						</span>
					<?php endif; ?>
				</span>
			</a>
		</li>
	<?php endforeach; ?>
	</ul>
</aside>
