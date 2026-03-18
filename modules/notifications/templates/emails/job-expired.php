<?php
/**
 * Email template: employer notified that their job listing has expired.
 *
 * Available variables: $job_title (string), $repost_url (string).
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<h2 style="margin:0 0 16px;font-size:18px;color:#111827;"><?php esc_html_e( 'Your Job Listing Has Expired', 'wp-career-board' ); ?></h2>
<p>
<?php
	/* translators: %s: job title */
	printf( esc_html__( 'Your job listing "%s" has expired.', 'wp-career-board' ), esc_html( $job_title ) );
?>
</p>
<p style="margin-top:24px;">
	<a href="<?php echo esc_url( $repost_url ); ?>"
		style="display:inline-block;padding:10px 20px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">
		<?php esc_html_e( 'Re-post Job', 'wp-career-board' ); ?>
	</a>
</p>
