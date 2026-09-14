<?php
/**
 * Block render: wp-career-board/job-alert-card - sidebar CTA promoting the
 * job-alerts feature. Defaults to copy that fits the company-profile sidebar
 * context.
 *
 * @package WP_Career_Board
 * @since   1.1.1
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// Job alerts are a Pro feature. This card ships as one of three DEFAULT cards in
// the company-profile sidebar, so on a Free-only site every company profile
// rendered a "Get Job Alerts" call to action pointing at
// candidate-dashboard/?tab=alerts - a tab that is gated off without Pro. The
// member arrived at a dashboard with no such tab.
//
// The sibling surfaces already gate on this filter; this one did not. Rendering
// nothing is right rather than showing a disabled card: the sidebar is a stack,
// so the remaining cards simply close up.
if ( ! apply_filters( 'wcb_pro_alerts_enabled', false ) ) {
	return '';
}

$wcb_title = trim( (string) ( $attributes['title'] ?? '' ) );
$wcb_body  = trim( (string) ( $attributes['body'] ?? '' ) );
$wcb_cta   = trim( (string) ( $attributes['cta'] ?? '' ) );
$wcb_url   = trim( (string) ( $attributes['url'] ?? '' ) );

if ( '' === $wcb_title ) {
	$wcb_title = __( 'Get Job Alerts', 'wp-career-board' );
}
if ( '' === $wcb_body ) {
	$wcb_body = __( "Don't miss new openings. Subscribe to email alerts for roles that match what you're looking for.", 'wp-career-board' );
}
if ( '' === $wcb_cta ) {
	$wcb_cta = __( 'Set Up Alerts', 'wp-career-board' );
}

if ( '' === $wcb_url ) {
	$wcb_dashboard_id = (int) \WCB\Admin\Settings::int( 'candidate_dashboard_page', 0 );
	if ( $wcb_dashboard_id > 0 ) {
		$wcb_dashboard_url = (string) get_permalink( $wcb_dashboard_id );
		if ( $wcb_dashboard_url ) {
			$wcb_url = add_query_arg( 'tab', 'alerts', $wcb_dashboard_url );
		}
	}
	if ( '' === $wcb_url ) {
		$wcb_url = home_url( '/candidate-dashboard/?tab=alerts' );
	}
}

?>
<aside <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-cp-side-card wcb-job-alert-card' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<h3 class="wcb-cp-side-card__title"><?php echo esc_html( $wcb_title ); ?></h3>
	<p class="wcb-job-alert-card__body"><?php echo esc_html( $wcb_body ); ?></p>
	<a class="wcb-job-alert-card__btn" href="<?php echo esc_url( $wcb_url ); ?>">
		<?php echo esc_html( $wcb_cta ); ?>
	</a>
</aside>
