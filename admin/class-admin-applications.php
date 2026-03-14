<?php
/**
 * Admin Applications list page — sortable table of all job applications.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the admin applications list page.
 *
 * @since 1.0.0
 */
class AdminApplications {

	/**
	 * Valid application status values.
	 *
	 * @var string[]
	 */
	private const STATUSES = array( 'submitted', 'reviewing', 'shortlisted', 'rejected', 'hired' );

	/**
	 * Render the applications list page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render(): void {
		$applications = get_posts(
			array(
				'post_type'   => 'wcb_application',
				'post_status' => 'publish',
				'numberposts' => 100,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Applications', 'wp-career-board' ); ?></h1>

			<?php if ( empty( $applications ) ) : ?>
				<p class="wcb-no-items"><?php esc_html_e( 'No applications yet.', 'wp-career-board' ); ?></p>
			<?php else : ?>
				<table class="widefat striped wcb-admin-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Job', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Candidate', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Change Status', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Date', 'wp-career-board' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $applications as $wcb_app ) : ?>
							<?php
							$wcb_job_id       = (int) get_post_meta( $wcb_app->ID, '_wcb_job_id', true );
							$wcb_candidate_id = (int) get_post_meta( $wcb_app->ID, '_wcb_candidate_id', true );
							$wcb_status       = (string) get_post_meta( $wcb_app->ID, '_wcb_status', true );
							$wcb_job          = $wcb_job_id ? get_post( $wcb_job_id ) : null;
							$wcb_candidate    = $wcb_candidate_id ? get_userdata( $wcb_candidate_id ) : false;
							$wcb_job_title    = $wcb_job instanceof \WP_Post ? $wcb_job->post_title : __( '(deleted)', 'wp-career-board' );
							$wcb_cand_name    = $wcb_candidate instanceof \WP_User ? $wcb_candidate->display_name : __( '(deleted)', 'wp-career-board' );
							$wcb_status_safe  = in_array( $wcb_status, self::STATUSES, true ) ? $wcb_status : 'submitted';
							?>
							<tr>
								<td>
									<?php if ( $wcb_job instanceof \WP_Post ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( $wcb_job->ID ) ); ?>">
											<?php echo esc_html( $wcb_job_title ); ?>
										</a>
									<?php else : ?>
										<?php echo esc_html( $wcb_job_title ); ?>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $wcb_candidate instanceof \WP_User ) : ?>
										<a href="<?php echo esc_url( get_edit_user_link( $wcb_candidate->ID ) ); ?>">
											<?php echo esc_html( $wcb_cand_name ); ?>
										</a>
									<?php else : ?>
										<?php echo esc_html( $wcb_cand_name ); ?>
									<?php endif; ?>
								</td>
								<td>
									<span class="wcb-status-badge wcb-status-<?php echo esc_attr( $wcb_status_safe ); ?>">
										<?php echo esc_html( ucfirst( $wcb_status_safe ) ); ?>
									</span>
								</td>
								<td>
									<select
										class="wcb-status-select"
										data-app-id="<?php echo (int) $wcb_app->ID; ?>"
									>
										<?php foreach ( self::STATUSES as $wcb_opt ) : ?>
											<option
												value="<?php echo esc_attr( $wcb_opt ); ?>"
												<?php selected( $wcb_status_safe, $wcb_opt ); ?>
											>
												<?php echo esc_html( ucfirst( $wcb_opt ) ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td><?php echo esc_html( $wcb_app->post_date ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
