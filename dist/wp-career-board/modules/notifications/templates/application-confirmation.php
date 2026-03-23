<?php
/**
 * Email template: candidate receives submission confirmation.
 *
 * Available variables: $job_title (string), $dashboard_url (string).
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<p>
<?php
/* translators: %s: job title */
printf( esc_html__( 'Your application for %s has been submitted successfully.', 'wp-career-board' ), esc_html( $job_title ) );
?>
</p>
<p><?php esc_html_e( 'We will notify you when your application status changes.', 'wp-career-board' ); ?></p>
<p><a href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'View Your Applications', 'wp-career-board' ); ?></a></p>
