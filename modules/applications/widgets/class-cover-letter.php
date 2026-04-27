<?php
/**
 * Cover Letter widget.
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
 * Renders the application's cover letter.
 *
 * @since 1.1.0
 */
final class CoverLetter extends AbstractWidget {

	/**
	 * Widget id.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function id(): string {
		return 'application/cover-letter';
	}

	/**
	 * Widget title.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function title(): string {
		return __( 'Cover Letter', 'wp-career-board' );
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
	 * Render the cover letter.
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

		$letter = trim( (string) get_post_meta( $post->ID, '_wcb_cover_letter', true ) );

		ob_start();
		?>
		<div class="wcb-app-section">
			<h3 class="wcb-app-section__title"><?php esc_html_e( 'Cover letter', 'wp-career-board' ); ?></h3>
			<?php if ( '' === $letter ) : ?>
				<p class="wcb-app-section__empty"><?php esc_html_e( 'No cover letter submitted.', 'wp-career-board' ); ?></p>
			<?php else : ?>
				<div class="wcb-app-section__body"><?php echo wp_kses_post( wpautop( $letter ) ); ?></div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
