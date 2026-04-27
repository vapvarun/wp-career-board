<?php
/**
 * Status Changer widget — admin status select bound to the REST endpoint.
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
 * Inline status changer driven by the Interactivity API.
 *
 * @since 1.1.0
 */
final class StatusChanger extends AbstractWidget {

	/**
	 * Widget id.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function id(): string {
		return 'application/status-changer';
	}

	/**
	 * Widget title.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function title(): string {
		return __( 'Change Status', 'wp-career-board' );
	}

	/**
	 * Required ability — only reviewers should change status.
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
	 * Render the changer.
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

		$current = (string) get_post_meta( $post->ID, '_wcb_status', true );
		if ( '' === $current ) {
			$current = 'submitted';
		}
		$labels = StatusTimeline::status_labels();
		$nonce  = wp_create_nonce( 'wp_rest' );

		ob_start();
		?>
		<div class="wcb-app-section wcb-app-changer"
			data-application-id="<?php echo esc_attr( (string) $post->ID ); ?>"
			data-rest-nonce="<?php echo esc_attr( $nonce ); ?>"
			data-rest-url="<?php echo esc_attr( rest_url( 'wcb/v1/applications/' . $post->ID . '/status' ) ); ?>">
			<h3 class="wcb-app-section__title"><?php esc_html_e( 'Change status', 'wp-career-board' ); ?></h3>
			<div class="wcb-app-changer__row">
				<label for="wcb-app-status-select-<?php echo esc_attr( (string) $post->ID ); ?>" class="screen-reader-text">
					<?php esc_html_e( 'Status', 'wp-career-board' ); ?>
				</label>
				<select id="wcb-app-status-select-<?php echo esc_attr( (string) $post->ID ); ?>" class="wcb-app-changer__select">
					<?php foreach ( $labels as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button button-primary wcb-app-changer__save">
					<?php esc_html_e( 'Save', 'wp-career-board' ); ?>
				</button>
				<span class="wcb-app-changer__feedback" role="status" aria-live="polite"></span>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
