<?php
/**
 * Email template: guest receives submission confirmation.
 *
 * Available variables: $guest_name (string), $job_title (string),
 *                      $app_id (int), $job_url (string).
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<p>
<?php
/* translators: %s: guest first name */
printf( esc_html__( 'Hi %s,', 'wp-career-board' ), esc_html( $guest_name ) );
?>
</p>
<p>
<?php
/* translators: %s: job title */
printf( esc_html__( 'Your application for %s has been submitted successfully.', 'wp-career-board' ), '<strong>' . esc_html( $job_title ) . '</strong>' );
?>
</p>
<p>
<?php
/* translators: %d: application reference number */
printf( esc_html__( 'Your application reference is #%d.', 'wp-career-board' ), absint( $app_id ) );
?>
</p>
<p><?php esc_html_e( 'The employer will be in touch if your application moves forward.', 'wp-career-board' ); ?></p>
<p><a href="<?php echo esc_url( $job_url ); ?>"><?php esc_html_e( 'View Job Listing', 'wp-career-board' ); ?></a></p>
