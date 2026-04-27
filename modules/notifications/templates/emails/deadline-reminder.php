<?php
/**
 * Email template: deadline reminder to a candidate who bookmarked a job.
 *
 * Available variables: $job_title, $job_url, $days_left, $deadline_iso,
 * $company_name.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;
?>
<h2 style="margin:0 0 16px;font-size:18px;color:#111827;">
	<?php
	if ( 1 === (int) $days_left ) {
		esc_html_e( 'Apply by tomorrow', 'wp-career-board' );
	} else {
		printf(
			/* translators: %d: number of days */
			esc_html( _n( 'Apply within %d day', 'Apply within %d days', (int) $days_left, 'wp-career-board' ) ),
			(int) $days_left
		);
	}
	?>
</h2>
<p>
	<?php
	if ( '' !== (string) $company_name ) {
		printf(
			/* translators: 1: job title, 2: company name */
			esc_html__( 'You saved "%1$s" at %2$s — the application window is closing soon.', 'wp-career-board' ),
			esc_html( $job_title ),
			esc_html( $company_name )
		);
	} else {
		printf(
			/* translators: %s: job title */
			esc_html__( 'You saved "%s" — the application window is closing soon.', 'wp-career-board' ),
			esc_html( $job_title )
		);
	}
	?>
</p>
<?php if ( '' !== (string) $deadline_iso ) : ?>
<p style="color:#6b7280;font-size:14px;">
	<?php
	printf(
		/* translators: %s: deadline date */
		esc_html__( 'Closes on %s.', 'wp-career-board' ),
		esc_html( mysql2date( get_option( 'date_format', 'F j, Y' ), $deadline_iso ) )
	);
	?>
</p>
<?php endif; ?>
<p style="margin-top:24px;">
	<a href="<?php echo esc_url( $job_url ); ?>"
		style="display:inline-block;padding:10px 20px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">
		<?php esc_html_e( 'Apply now', 'wp-career-board' ); ?>
	</a>
</p>
