<?php
/**
 * Email template: employer is notified that their job has been approved.
 *
 * Available variables: $job_title (string), $job_url (string).
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<p>
<?php
/* translators: %s: job title */
printf( esc_html__( 'Your job listing has been approved: %s', 'wp-career-board' ), esc_html( $job_title ) );
?>
</p>
<p><?php esc_html_e( 'It is now live and accepting applications.', 'wp-career-board' ); ?></p>
<p><a href="<?php echo esc_url( $job_url ); ?>"><?php esc_html_e( 'View Job Listing', 'wp-career-board' ); ?></a></p>
