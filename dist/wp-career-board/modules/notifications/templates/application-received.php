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
<p>
<?php
/* translators: %s: job title */
printf( esc_html__( 'A new application has been received for %s.', 'wp-career-board' ), esc_html( $job_title ) );
?>
</p>
<p>
<?php
/* translators: %s: candidate display name */
printf( esc_html__( 'Applicant: %s', 'wp-career-board' ), esc_html( $candidate_name ) );
?>
</p>
<p><a href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'View in Dashboard', 'wp-career-board' ); ?></a></p>
