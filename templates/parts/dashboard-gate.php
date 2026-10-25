<?php
/**
 * The card a dashboard shows instead of itself: signed out, or the wrong role.
 *
 * One design for every such screen (the registration-closed card's), so a
 * visitor never sees three versions of "you can't see this yet".
 *
 * Expects:
 *   $wcb_gate array{icon:string, title?:string, message:string, actions:array<int, array{label:string, url:string, primary?:bool}>, signup?:int}
 *
 * `title` names the screen ("Post a job"). `signup` is a registration page ID:
 * "Create an account" is added only while registration is open, so no gate
 * offers a sign-up the site has closed.
 *
 * @package WP_Career_Board
 * @since   1.8.1
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcb_gate = (array) ( $wcb_gate ?? array() );
if ( ! empty( $wcb_gate['signup'] ) && ( get_option( 'users_can_register' ) || is_multisite() ) ) {
	$wcb_gate['actions'][] = array(
		'label' => __( 'Create an account', 'wp-career-board' ),
		'url'   => (string) get_permalink( (int) $wcb_gate['signup'] ),
	);
}
?>
<div class="wcb-db-gate" data-wcb-gate-scope>
	<?php echo \WCB\Core\Icon::svg( (string) ( $wcb_gate['icon'] ?? 'user' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?>
	<?php if ( ! empty( $wcb_gate['title'] ) ) : ?>
		<?php // The gate is the whole screen, so its title is the page heading (Basecamp 10439833481). ?>
		<h1 class="wcb-db-gate__title"><?php echo esc_html( (string) $wcb_gate['title'] ); ?></h1>
	<?php endif; ?>
	<p><?php echo esc_html( (string) ( $wcb_gate['message'] ?? '' ) ); ?></p>
	<?php if ( ! empty( $wcb_gate['actions'] ) ) : ?>
		<div class="wcb-db-gate__actions">
			<?php foreach ( (array) $wcb_gate['actions'] as $wcb_gate_action ) : ?>
				<a href="<?php echo esc_url( (string) $wcb_gate_action['url'] ); ?>" class="wcb-btn <?php echo ! empty( $wcb_gate_action['primary'] ) ? 'wcb-btn--primary' : 'wcb-btn--secondary'; ?>"><?php echo esc_html( (string) $wcb_gate_action['label'] ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
