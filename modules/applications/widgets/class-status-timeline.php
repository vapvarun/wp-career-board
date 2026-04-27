<?php
/**
 * Status Timeline widget.
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
 * Renders current application status plus the audit log of past changes.
 *
 * @since 1.1.0
 */
final class StatusTimeline extends AbstractWidget {

	/**
	 * Widget id.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function id(): string {
		return 'application/status-timeline';
	}

	/**
	 * Widget title.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function title(): string {
		return __( 'Status Timeline', 'wp-career-board' );
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
	 * Render the timeline.
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
		$log    = (array) get_post_meta( $post->ID, '_wcb_status_log', true );
		$labels = self::status_labels();

		ob_start();
		?>
		<div class="wcb-app-section">
			<h3 class="wcb-app-section__title"><?php esc_html_e( 'Status', 'wp-career-board' ); ?></h3>
			<p>
				<span class="wcb-app-status wcb-app-status--<?php echo esc_attr( $current ); ?>">
					<?php echo esc_html( $labels[ $current ] ?? $current ); ?>
				</span>
			</p>

			<?php if ( ! empty( $log ) ) : ?>
				<h4 class="wcb-app-section__subtitle"><?php esc_html_e( 'History', 'wp-career-board' ); ?></h4>
				<ol class="wcb-app-timeline">
					<?php
					foreach ( array_reverse( $log ) as $entry ) :
						if ( ! is_array( $entry ) ) {
							continue;
						}
						$user      = isset( $entry['by'] ) ? get_userdata( (int) $entry['by'] ) : false;
						$user_name = $user instanceof \WP_User ? $user->display_name : __( 'System', 'wp-career-board' );
						$from      = (string) ( $entry['from'] ?? '' );
						$to        = (string) ( $entry['to'] ?? '' );
						$at        = (string) ( $entry['at'] ?? '' );
						?>
						<li class="wcb-app-timeline__item">
							<span class="wcb-app-timeline__when"><?php echo esc_html( $at ); ?></span>
							<span class="wcb-app-timeline__what">
								<?php echo esc_html( $labels[ $from ] ?? ( '' !== $from ? $from : __( '(new)', 'wp-career-board' ) ) ); ?>
								&rarr;
								<?php echo esc_html( $labels[ $to ] ?? $to ); ?>
							</span>
							<span class="wcb-app-timeline__who"><?php echo esc_html( $user_name ); ?></span>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Map of status keys to human labels.
	 *
	 * @since 1.1.0
	 * @return array<string, string>
	 */
	public static function status_labels(): array {
		return array(
			'submitted'   => __( 'Submitted', 'wp-career-board' ),
			'reviewing'   => __( 'Reviewing', 'wp-career-board' ),
			'shortlisted' => __( 'Shortlisted', 'wp-career-board' ),
			'rejected'    => __( 'Rejected', 'wp-career-board' ),
			'hired'       => __( 'Hired', 'wp-career-board' ),
		);
	}
}
