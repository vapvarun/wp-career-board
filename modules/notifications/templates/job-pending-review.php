<?php
/**
 * Email template: new job pending admin review.
 *
 * Available variables: $job_title (string), $approve_url (string).
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<p>
<?php
/* translators: %s: job title */
printf( esc_html__( 'A new job listing is waiting for your review: %s', 'wp-career-board' ), esc_html( $job_title ) );
?>
</p>
<p><a href="<?php echo esc_url( $approve_url ); ?>"><?php esc_html_e( 'Review in Dashboard', 'wp-career-board' ); ?></a></p>
