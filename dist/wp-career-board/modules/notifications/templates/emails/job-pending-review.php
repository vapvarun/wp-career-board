<?php
/**
 * Email template: admin notified of a job pending approval.
 *
 * Available variables: $job_title (string), $approve_url (string).
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<h2 style="margin:0 0 16px;font-size:18px;color:#111827;"><?php esc_html_e( 'New Job Pending Approval', 'wp-career-board' ); ?></h2>
<p>
<?php
	/* translators: %s: job title */
	printf( esc_html__( 'A new job "%s" has been submitted and is awaiting your review.', 'wp-career-board' ), esc_html( $job_title ) );
?>
</p>
<p style="margin-top:24px;">
	<a href="<?php echo esc_url( $approve_url ); ?>"
		style="display:inline-block;padding:10px 20px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">
		<?php esc_html_e( 'Review Job', 'wp-career-board' ); ?>
	</a>
</p>
