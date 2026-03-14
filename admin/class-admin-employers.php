<?php
/**
 * Admin Employers list page — table of all employer user accounts.
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
 * Renders the admin employers list page.
 *
 * @since 1.0.0
 */
class AdminEmployers {

	/**
	 * Render the employers list page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render(): void {
		$wcb_employers = get_users(
			array(
				'role__in' => array( 'wcb_employer' ),
				'orderby'  => 'registered',
				'order'    => 'DESC',
				'number'   => 100,
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Employers', 'wp-career-board' ); ?></h1>

			<?php if ( empty( $wcb_employers ) ) : ?>
				<p class="wcb-no-items"><?php esc_html_e( 'No employer accounts yet.', 'wp-career-board' ); ?></p>
			<?php else : ?>
				<table class="widefat striped wcb-admin-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'User', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Company', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Website', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Active Jobs', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Registered', 'wp-career-board' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $wcb_employers as $wcb_emp ) : ?>
							<?php
							$wcb_company = (string) get_user_meta( $wcb_emp->ID, '_wcb_company_name', true );
							$wcb_site    = (string) get_user_meta( $wcb_emp->ID, '_wcb_company_website', true );
							$wcb_jobs    = count(
								get_posts(
									array(
										'post_type'   => 'wcb_job',
										'post_status' => 'publish',
										'author'      => $wcb_emp->ID,
										'numberposts' => -1,
										'fields'      => 'ids',
									)
								)
							);
							?>
							<tr>
								<td>
									<a href="<?php echo esc_url( get_edit_user_link( $wcb_emp->ID ) ); ?>">
										<?php echo esc_html( $wcb_emp->display_name ); ?>
									</a>
									<br><small><?php echo esc_html( $wcb_emp->user_email ); ?></small>
								</td>
								<td><?php echo esc_html( $wcb_company ? $wcb_company : '—' ); ?></td>
								<td>
									<?php if ( $wcb_site ) : ?>
										<a href="<?php echo esc_url( $wcb_site ); ?>" target="_blank" rel="noopener noreferrer">
											<?php echo esc_html( $wcb_site ); ?>
										</a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td><?php echo (int) $wcb_jobs; ?></td>
								<td><?php echo esc_html( $wcb_emp->user_registered ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
