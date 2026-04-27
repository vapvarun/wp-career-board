<?php
/**
 * Resume Preview widget.
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
 * Shows the attached resume with download + preview links.
 *
 * @since 1.1.0
 */
final class ResumePreview extends AbstractWidget {

	/**
	 * Widget id.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function id(): string {
		return 'application/resume-preview';
	}

	/**
	 * Widget title.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function title(): string {
		return __( 'Resume Preview', 'wp-career-board' );
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
	 * Render the resume block.
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

		$attachment_id = (int) get_post_meta( $post->ID, '_wcb_resume_attachment_id', true );
		$resume_post   = (int) get_post_meta( $post->ID, '_wcb_resume_id', true );

		if ( ! $attachment_id && $resume_post ) {
			$linked = (int) get_post_meta( $resume_post, '_wcb_attachment_id', true );
			if ( $linked ) {
				$attachment_id = $linked;
			}
		}

		ob_start();
		?>
		<div class="wcb-app-section">
			<h3 class="wcb-app-section__title"><?php esc_html_e( 'Resume', 'wp-career-board' ); ?></h3>
			<?php if ( ! $attachment_id ) : ?>
				<p class="wcb-app-section__empty"><?php esc_html_e( 'No resume uploaded with this application.', 'wp-career-board' ); ?></p>
				<?php
			else :
				$url      = (string) wp_get_attachment_url( $attachment_id );
				$path     = (string) get_attached_file( $attachment_id );
				$filename = '' !== $path ? basename( $path ) : '';
				$bytes    = ( '' !== $path && file_exists( $path ) ) ? (int) filesize( $path ) : 0;
				$size     = $bytes > 0 ? size_format( $bytes ) : '';
				?>
				<div class="wcb-app-resume">
					<div class="wcb-app-resume__meta">
						<strong class="wcb-app-resume__filename"><?php echo esc_html( $filename ); ?></strong>
						<?php if ( '' !== $size ) : ?>
							<span class="wcb-app-resume__size"><?php echo esc_html( $size ); ?></span>
						<?php endif; ?>
					</div>
					<div class="wcb-app-resume__actions">
						<a class="button button-primary" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Open', 'wp-career-board' ); ?>
						</a>
						<a class="button button-secondary" href="<?php echo esc_url( $url ); ?>" download>
							<?php esc_html_e( 'Download', 'wp-career-board' ); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
