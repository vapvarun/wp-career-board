<?php
/**
 * Applicant Card widget — avatar, name, contact, applied date.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Applications\Widgets;

use WCB\Core\Widgets\AbstractWidget;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Identity card showing who applied and when.
 *
 * @since 1.1.0
 */
final class ApplicantCard extends AbstractWidget {

	/**
	 * Widget id.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function id(): string {
		return 'application/applicant-card';
	}

	/**
	 * Widget title.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function title(): string {
		return __( 'Applicant Card', 'wp-career-board' );
	}

	/**
	 * Default args.
	 *
	 * @since 1.1.0
	 * @return array<string, mixed>
	 */
	public function default_args(): array {
		return array( 'application_id' => 0 );
	}

	/**
	 * Render the applicant card.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $args Caller args.
	 * @return string
	 */
	public function render( array $args ): string {
		$application_id = (int) ( $args['application_id'] ?? 0 );
		$post           = $application_id ? get_post( $application_id ) : null;

		if ( ! $post instanceof \WP_Post || 'wcb_application' !== $post->post_type ) {
			return '';
		}

		$candidate_id = (int) get_post_meta( $post->ID, '_wcb_candidate_id', true );
		$guest_name   = (string) get_post_meta( $post->ID, '_wcb_guest_name', true );
		$guest_email  = (string) get_post_meta( $post->ID, '_wcb_guest_email', true );
		$job_id       = (int) get_post_meta( $post->ID, '_wcb_job_id', true );

		if ( $candidate_id ) {
			$user        = get_userdata( $candidate_id );
			$name        = $user instanceof \WP_User ? $user->display_name : __( 'Unknown candidate', 'wp-career-board' );
			$email       = $user instanceof \WP_User ? $user->user_email : '';
			$avatar_html = get_avatar( $candidate_id, 64 );
			$profile_url = $user instanceof \WP_User ? get_edit_user_link( $user->ID ) : '';
		} else {
			$name        = $guest_name !== '' ? $guest_name : __( 'Guest applicant', 'wp-career-board' );
			$email       = $guest_email;
			$avatar_html = get_avatar( $email !== '' ? $email : 0, 64 );
			$profile_url = '';
		}

		$applied_at = mysql2date( get_option( 'date_format', 'F j, Y' ) . ' ' . get_option( 'time_format', 'g:i a' ), $post->post_date );
		$job_title  = $job_id ? get_the_title( $job_id ) : '';
		$job_link   = $job_id ? get_edit_post_link( $job_id ) : '';

		ob_start();
		?>
		<div class="wcb-app-card">
			<div class="wcb-app-card__avatar"><?php echo $avatar_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_avatar() returns escaped HTML. ?></div>
			<div class="wcb-app-card__body">
				<h2 class="wcb-app-card__name"><?php echo esc_html( $name ); ?></h2>
				<?php if ( '' !== $email ) : ?>
					<p class="wcb-app-card__row">
						<span class="wcb-app-card__label"><?php esc_html_e( 'Email', 'wp-career-board' ); ?></span>
						<a href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo esc_html( $email ); ?></a>
					</p>
				<?php endif; ?>
				<p class="wcb-app-card__row">
					<span class="wcb-app-card__label"><?php esc_html_e( 'Applied', 'wp-career-board' ); ?></span>
					<span><?php echo esc_html( $applied_at ); ?></span>
				</p>
				<?php if ( '' !== $job_title ) : ?>
					<p class="wcb-app-card__row">
						<span class="wcb-app-card__label"><?php esc_html_e( 'Job', 'wp-career-board' ); ?></span>
						<?php if ( '' !== $job_link ) : ?>
							<a href="<?php echo esc_url( $job_link ); ?>"><?php echo esc_html( $job_title ); ?></a>
						<?php else : ?>
							<span><?php echo esc_html( $job_title ); ?></span>
						<?php endif; ?>
					</p>
				<?php endif; ?>
				<?php if ( '' !== $profile_url ) : ?>
					<p class="wcb-app-card__row">
						<a class="button button-secondary" href="<?php echo esc_url( $profile_url ); ?>">
							<?php esc_html_e( 'View candidate profile', 'wp-career-board' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
