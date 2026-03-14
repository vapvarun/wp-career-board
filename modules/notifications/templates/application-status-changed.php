<?php
/**
 * Email template: candidate is notified of an application status change.
 *
 * Available variables: $job_title (string), $new_status (string), $dashboard_url (string).
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<p>
<?php
/* translators: %s: job title */
printf( esc_html__( 'The status of your application for %s has been updated.', 'wp-career-board' ), esc_html( $job_title ) );
?>
</p>
<p>
<?php
/* translators: %s: new application status (e.g. Reviewed) */
printf( esc_html__( 'New status: %s', 'wp-career-board' ), esc_html( $new_status ) );
?>
</p>
<p><a href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'View Your Applications', 'wp-career-board' ); ?></a></p>
