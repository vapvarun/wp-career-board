<?php
/**
 * Email template: employer receives a new application.
 *
 * Available variables: $job_title (string), $candidate_name (string), $dashboard_url (string).
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<h2 style="margin:0 0 16px;font-size:18px;color:#111827;"><?php esc_html_e( 'New Application Received', 'wp-career-board' ); ?></h2>
<p>
<?php
	/* translators: 1: candidate name, 2: job title */
	printf( esc_html__( '%1$s has applied for %2$s.', 'wp-career-board' ), '<strong>' . esc_html( $candidate_name ) . '</strong>', '<strong>' . esc_html( $job_title ) . '</strong>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
?>
</p>
<p style="margin-top:24px;">
	<a href="<?php echo esc_url( $dashboard_url ); ?>"
		style="display:inline-block;padding:10px 20px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">
		<?php esc_html_e( 'View in Dashboard', 'wp-career-board' ); ?>
	</a>
</p>
