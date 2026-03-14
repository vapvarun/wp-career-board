<?php
/**
 * Email template: employer is notified that their job has expired.
 *
 * Available variables: $job_title (string), $repost_url (string).
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<p>
<?php
/* translators: %s: job title */
printf( esc_html__( 'Your job listing has expired: %s', 'wp-career-board' ), esc_html( $job_title ) );
?>
</p>
<p><?php esc_html_e( 'Repost from your dashboard to continue accepting applications.', 'wp-career-board' ); ?></p>
<p><a href="<?php echo esc_url( $repost_url ); ?>"><?php esc_html_e( 'Go to Dashboard', 'wp-career-board' ); ?></a></p>
