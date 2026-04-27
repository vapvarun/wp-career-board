<?php
/**
 * Quick Actions widget — shortlist / reject / hire shortcuts.
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
 * Renders one-click status shortcuts that hit the same REST endpoint as the changer.
 *
 * @since 1.1.0
 */
final class QuickActions extends AbstractWidget {

	/**
	 * Widget id.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function id(): string {
		return 'application/quick-actions';
	}

	/**
	 * Widget title.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function title(): string {
		return __( 'Quick Actions', 'wp-career-board' );
	}

	/**
	 * Required ability.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function ability(): string {
		return 'wcb_view_applications';
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
	 * Render the action buttons.
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
		$guest_email  = (string) get_post_meta( $post->ID, '_wcb_guest_email', true );
		$email        = '';
		if ( $candidate_id ) {
			$user = get_userdata( $candidate_id );
			if ( $user instanceof \WP_User ) {
				$email = $user->user_email;
			}
		} elseif ( '' !== $guest_email ) {
			$email = $guest_email;
		}

		$nonce = wp_create_nonce( 'wp_rest' );
		ob_start();
		?>
		<div class="wcb-app-section wcb-app-actions"
			data-application-id="<?php echo esc_attr( (string) $post->ID ); ?>"
			data-rest-nonce="<?php echo esc_attr( $nonce ); ?>"
			data-rest-url="<?php echo esc_attr( rest_url( 'wcb/v1/applications/' . $post->ID . '/status' ) ); ?>">
			<h3 class="wcb-app-section__title"><?php esc_html_e( 'Quick actions', 'wp-career-board' ); ?></h3>
			<div class="wcb-app-actions__row">
				<button type="button" class="button wcb-app-actions__btn" data-status="shortlisted">
					<?php esc_html_e( 'Shortlist', 'wp-career-board' ); ?>
				</button>
				<button type="button" class="button wcb-app-actions__btn" data-status="hired">
					<?php esc_html_e( 'Mark Hired', 'wp-career-board' ); ?>
				</button>
				<button type="button" class="button wcb-app-actions__btn wcb-app-actions__btn--danger" data-status="rejected">
					<?php esc_html_e( 'Reject', 'wp-career-board' ); ?>
				</button>
				<?php if ( '' !== $email ) : ?>
					<a class="button" href="<?php echo esc_url( 'mailto:' . $email . '?subject=' . rawurlencode( (string) get_the_title( $post ) ) ); ?>">
						<?php esc_html_e( 'Message', 'wp-career-board' ); ?>
					</a>
				<?php endif; ?>
				<span class="wcb-app-actions__feedback" role="status" aria-live="polite"></span>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
