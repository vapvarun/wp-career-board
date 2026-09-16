<?php
/**
 * Application answers widget.
 *
 * @package WP_Career_Board
 * @since   1.7.1
 */

declare( strict_types=1 );

namespace WCB\Modules\Applications\Widgets;

use WCB\Core\Widgets\AbstractWidget;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the applicant's answers to the job's custom application questions.
 *
 * @since 1.7.1
 */
final class CustomAnswers extends AbstractWidget {

	/**
	 * Widget id.
	 *
	 * @since 1.7.1
	 * @return string
	 */
	public function id(): string {
		return 'application/custom-answers';
	}

	/**
	 * Widget title.
	 *
	 * @since 1.7.1
	 * @return string
	 */
	public function title(): string {
		return __( 'Application answers', 'wp-career-board' );
	}

	/**
	 * Default args.
	 *
	 * @since 1.7.1
	 * @return array<string, mixed>
	 */
	public function default_args(): array {
		return array( 'application_id' => 0 );
	}

	/**
	 * Render the answers.
	 *
	 * Returns an empty string when the job asked no questions or none were
	 * answered — unlike the cover-letter widget there is no useful empty state,
	 * since most jobs register no custom fields at all.
	 *
	 * @since 1.7.1
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

		$job_id  = (int) get_post_meta( $post->ID, '_wcb_job_id', true );
		$groups  = (array) apply_filters( 'wcb_application_form_fields_groups', array(), $job_id );
		$answers = \WCB\Core\FormCustomFields::labelled_values(
			$groups,
			$post->ID,
			'post_meta',
			\WCB\Api\Endpoints\ApplicationsEndpoint::FIELD_META_PREFIX
		);

		if ( ! $answers ) {
			return '';
		}

		ob_start();
		?>
		<div class="wcb-app-section">
			<h3 class="wcb-app-section__title"><?php esc_html_e( 'Application answers', 'wp-career-board' ); ?></h3>
			<dl class="wcb-app-section__body wcb-app-answers">
				<?php foreach ( $answers as $answer ) : ?>
					<dt><?php echo esc_html( $answer['label'] ); ?></dt>
					<dd><?php echo esc_html( $answer['value'] ); ?></dd>
				<?php endforeach; ?>
			</dl>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
