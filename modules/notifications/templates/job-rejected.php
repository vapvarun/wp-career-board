<?php
/**
 * Email template: employer is notified that their job was not approved.
 *
 * Available variables: $job_title (string), $reason (string).
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<p>
<?php
/* translators: %s: job title */
printf( esc_html__( 'Unfortunately, your job listing was not approved: %s', 'wp-career-board' ), esc_html( $job_title ) );
?>
</p>
<?php if ( ! empty( $reason ) ) : ?>
<p>
	<?php
	/* translators: %s: rejection reason provided by admin */
	printf( esc_html__( 'Reason: %s', 'wp-career-board' ), esc_html( $reason ) );
	?>
</p>
<?php endif; ?>
<p><?php esc_html_e( 'Please review our posting guidelines and resubmit.', 'wp-career-board' ); ?></p>
