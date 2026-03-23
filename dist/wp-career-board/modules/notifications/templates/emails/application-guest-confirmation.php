<?php
/**
 * Email template: guest applicant receives confirmation after submitting an application.
 *
 * Available variables: $guest_name (string), $job_title (string), $job_url (string).
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<h2 style="margin:0 0 16px;font-size:18px;color:#111827;"><?php esc_html_e( 'Application Received', 'wp-career-board' ); ?></h2>
<p>
<?php
	/* translators: 1: applicant name, 2: job title */
	printf( esc_html__( 'Hi %1$s, your application for "%2$s" has been received.', 'wp-career-board' ), esc_html( $guest_name ), esc_html( $job_title ) );
?>
</p>
<p style="margin-top:24px;">
	<a href="<?php echo esc_url( $job_url ); ?>"
		style="display:inline-block;padding:10px 20px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">
		<?php esc_html_e( 'View Job', 'wp-career-board' ); ?>
	</a>
</p>
