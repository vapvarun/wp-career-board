<?php
/**
 * Admin Candidates list page — table of all candidate user accounts.
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
 * Renders the admin candidates list page.
 *
 * @since 1.0.0
 */
class AdminCandidates {

	/**
	 * Render the candidates list page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render(): void {
		$wcb_candidates = get_users(
			array(
				'role__in' => array( 'wcb_candidate' ),
				'orderby'  => 'registered',
				'order'    => 'DESC',
				'number'   => 100,
			)
		);
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Candidates', 'wp-career-board' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'wp-career-board' ); ?></a>
			<hr class="wp-header-end">

			<?php if ( empty( $wcb_candidates ) ) : ?>
				<p class="wcb-no-items">
					<?php esc_html_e( 'No candidate accounts yet.', 'wp-career-board' ); ?>
					<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>"><?php esc_html_e( 'Add your first candidate.', 'wp-career-board' ); ?></a>
				</p>
			<?php else : ?>
				<table class="widefat striped wcb-admin-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Candidate', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Profile Visibility', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Applications', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Bookmarks', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Registered', 'wp-career-board' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $wcb_candidates as $wcb_cand ) : ?>
							<?php
							$wcb_visibility     = (string) get_user_meta( $wcb_cand->ID, '_wcb_profile_visibility', true );
							$wcb_visibility     = $wcb_visibility ? $wcb_visibility : 'public';
							$wcb_app_count      = (int) ( new \WP_Query(
								array(
									'post_type'      => 'wcb_application',
									'post_status'    => 'any',
									'posts_per_page' => 1,
									'fields'         => 'ids',
									'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
										array(
											'key'   => '_wcb_candidate_id',
											'value' => $wcb_cand->ID,
											'type'  => 'NUMERIC',
										),
									),
								)
							) )->found_posts;
							$wcb_bookmark_count = count( (array) get_user_meta( $wcb_cand->ID, '_wcb_bookmark', false ) );
							?>
							<tr>
								<td>
									<a href="<?php echo esc_url( get_edit_user_link( $wcb_cand->ID ) ); ?>">
										<?php echo esc_html( $wcb_cand->display_name ); ?>
									</a>
									<br><small><?php echo esc_html( $wcb_cand->user_email ); ?></small>
								</td>
								<td>
									<span class="wcb-badge wcb-badge--<?php echo esc_attr( $wcb_visibility ); ?>">
										<?php echo esc_html( ucfirst( $wcb_visibility ) ); ?>
									</span>
								</td>
								<td><?php echo (int) $wcb_app_count; ?></td>
								<td><?php echo (int) $wcb_bookmark_count; ?></td>
								<td><?php echo esc_html( $wcb_cand->user_registered ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
