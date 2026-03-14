<?php
/**
 * Admin view: jobs list with moderation queue and edit actions.
 *
 * Available variables (set by AdminJobs::render()):
 *   $pending   WP_Post[] — jobs with 'pending' status.
 *   $published WP_Post[] — most recent published jobs.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wcb-jobs-list">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Jobs', 'wp-career-board' ); ?></h1>
	<a
		href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcb_job' ) ); ?>"
		class="page-title-action"
	><?php esc_html_e( 'Add New', 'wp-career-board' ); ?></a>
	<hr class="wp-header-end">

	<?php if ( ! empty( $pending ) ) : ?>
		<h2><?php esc_html_e( 'Pending Review', 'wp-career-board' ); ?></h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Title', 'wp-career-board' ); ?></th>
					<th><?php esc_html_e( 'Author', 'wp-career-board' ); ?></th>
					<th><?php esc_html_e( 'Date', 'wp-career-board' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-career-board' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pending as $wcb_job ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( (string) get_edit_post_link( $wcb_job->ID ) ); ?>" style="font-weight:600">
								<?php echo esc_html( get_the_title( $wcb_job ) ); ?>
							</a>
							<div class="row-actions">
								<span><a href="<?php echo esc_url( (string) get_edit_post_link( $wcb_job->ID ) ); ?>"><?php esc_html_e( 'Edit', 'wp-career-board' ); ?></a></span>
								&nbsp;|&nbsp;
								<span><a href="<?php echo esc_url( (string) get_permalink( $wcb_job->ID ) ); ?>" target="_blank"><?php esc_html_e( 'View', 'wp-career-board' ); ?></a></span>
							</div>
						</td>
						<td><?php echo esc_html( get_the_author_meta( 'display_name', (int) $wcb_job->post_author ) ); ?></td>
						<td><?php echo esc_html( $wcb_job->post_date ); ?></td>
						<td>
							<button
								type="button"
								class="button button-primary wcb-approve-job"
								data-job-id="<?php echo (int) $wcb_job->ID; ?>"
							><?php esc_html_e( 'Approve', 'wp-career-board' ); ?></button>
							<button
								type="button"
								class="button wcb-reject-job"
								data-job-id="<?php echo (int) $wcb_job->ID; ?>"
							><?php esc_html_e( 'Reject', 'wp-career-board' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p><?php esc_html_e( 'No jobs pending review.', 'wp-career-board' ); ?></p>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Published Jobs', 'wp-career-board' ); ?></h2>
	<?php if ( ! empty( $published ) ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Title', 'wp-career-board' ); ?></th>
					<th><?php esc_html_e( 'Author', 'wp-career-board' ); ?></th>
					<th><?php esc_html_e( 'Date', 'wp-career-board' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $published as $wcb_job ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( (string) get_edit_post_link( $wcb_job->ID ) ); ?>" style="font-weight:600">
								<?php echo esc_html( get_the_title( $wcb_job ) ); ?>
							</a>
							<div class="row-actions">
								<span><a href="<?php echo esc_url( (string) get_edit_post_link( $wcb_job->ID ) ); ?>"><?php esc_html_e( 'Edit', 'wp-career-board' ); ?></a></span>
								&nbsp;|&nbsp;
								<span><a href="<?php echo esc_url( (string) get_permalink( $wcb_job->ID ) ); ?>" target="_blank"><?php esc_html_e( 'View', 'wp-career-board' ); ?></a></span>
							</div>
						</td>
						<td><?php echo esc_html( get_the_author_meta( 'display_name', (int) $wcb_job->post_author ) ); ?></td>
						<td><?php echo esc_html( $wcb_job->post_date ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p>
			<?php esc_html_e( 'No published jobs yet.', 'wp-career-board' ); ?>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcb_job' ) ); ?>"><?php esc_html_e( 'Add your first job.', 'wp-career-board' ); ?></a>
		</p>
	<?php endif; ?>
</div>
