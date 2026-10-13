<?php
/**
 * The card a dashboard shows instead of itself: signed out, or the wrong role.
 *
 * One design for every such screen (the registration-closed card's), so a
 * visitor never sees three versions of "you can't see this yet".
 *
 * Expects:
 *   $wcb_gate array{icon:string, message:string, actions:array<int, array{label:string, url:string, primary?:bool}>}
 *
 * @package WP_Career_Board
 * @since   1.8.1
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcb_gate = (array) ( $wcb_gate ?? array() );
?>
<div class="wcb-db-gate">
	<?php echo \WCB\Core\Icon::svg( (string) ( $wcb_gate['icon'] ?? 'user' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?>
	<p><?php echo esc_html( (string) ( $wcb_gate['message'] ?? '' ) ); ?></p>
	<?php if ( ! empty( $wcb_gate['actions'] ) ) : ?>
		<div class="wcb-db-gate__actions">
			<?php foreach ( (array) $wcb_gate['actions'] as $wcb_gate_action ) : ?>
				<a href="<?php echo esc_url( (string) $wcb_gate_action['url'] ); ?>" class="wcb-btn <?php echo ! empty( $wcb_gate_action['primary'] ) ? 'wcb-btn--primary' : 'wcb-btn--secondary'; ?>"><?php echo esc_html( (string) $wcb_gate_action['label'] ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
