<?php
/**
 * Email template: employer notified that their job listing was rejected.
 *
 * Available variables: $job_title (string), $reason (string).
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<h2 style="margin:0 0 16px;font-size:18px;color:#111827;"><?php esc_html_e( 'Your Job Was Not Approved', 'wp-career-board' ); ?></h2>
<p>
<?php
	/* translators: %s: job title */
	printf( esc_html__( 'Unfortunately, your job listing "%s" was not approved.', 'wp-career-board' ), esc_html( $job_title ) );
?>
</p>
<?php if ( ! empty( $reason ) ) : ?>
<p>
	<?php
	/* translators: %s: rejection reason */
	printf( esc_html__( 'Reason: %s', 'wp-career-board' ), esc_html( $reason ) );
	?>
</p>
<?php endif; ?>
