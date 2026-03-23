<?php
/**
 * Email template: applicant notified that their application status has changed.
 *
 * Available variables: $job_title (string), $new_status (string), $dashboard_url (string).
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<h2 style="margin:0 0 16px;font-size:18px;color:#111827;"><?php esc_html_e( 'Application Status Updated', 'wp-career-board' ); ?></h2>
<p>
<?php
	/* translators: 1: job title, 2: new status */
	printf( esc_html__( 'The status of your application for "%1$s" has been updated to: %2$s.', 'wp-career-board' ), esc_html( $job_title ), '<strong>' . esc_html( $new_status ) . '</strong>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
?>
</p>
<p style="margin-top:24px;">
	<a href="<?php echo esc_url( $dashboard_url ); ?>"
		style="display:inline-block;padding:10px 20px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">
		<?php esc_html_e( 'View My Applications', 'wp-career-board' ); ?>
	</a>
</p>
