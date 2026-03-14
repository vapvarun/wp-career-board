<?php
/**
 * Admin settings page — registers, sanitizes, and renders WCB plugin settings.
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
 * Manages the WCB settings page (Tools > Career Board > Settings).
 *
 * @since 1.0.0
 */
class AdminSettings {

	/**
	 * WordPress option key for all WCB settings.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_KEY = 'wcb_settings';

	/**
	 * Boot the settings module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register the WCB settings group with WordPress.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'wcb_settings_group',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * Sanitize submitted settings values.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $input Raw input from the settings form.
	 * @return array
	 */
	public function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();

		return array(
			'auto_publish_jobs'        => ! empty( $input['auto_publish_jobs'] ),
			'jobs_expire_days'         => isset( $input['jobs_expire_days'] ) ? max( 1, (int) $input['jobs_expire_days'] ) : 30,
			'employer_dashboard_page'  => isset( $input['employer_dashboard_page'] ) ? (int) $input['employer_dashboard_page'] : 0,
			'candidate_dashboard_page' => isset( $input['candidate_dashboard_page'] ) ? (int) $input['candidate_dashboard_page'] : 0,
			'jobs_archive_page'        => isset( $input['jobs_archive_page'] ) ? (int) $input['jobs_archive_page'] : 0,
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render(): void {
		$settings = (array) get_option( self::OPTION_KEY, array() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP Career Board — Settings', 'wp-career-board' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'wcb_settings_group' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto-Publish Jobs', 'wp-career-board' ); ?></th>
						<td>
							<label>
								<input
									type="checkbox"
									name="wcb_settings[auto_publish_jobs]"
									value="1"
									<?php checked( ! empty( $settings['auto_publish_jobs'] ) ); ?>
								>
								<?php esc_html_e( 'Publish jobs immediately without admin review', 'wp-career-board' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Job Expiry (days)', 'wp-career-board' ); ?></th>
						<td>
							<input
								type="number"
								name="wcb_settings[jobs_expire_days]"
								value="<?php echo isset( $settings['jobs_expire_days'] ) ? (int) $settings['jobs_expire_days'] : 30; ?>"
								min="1"
								max="365"
							>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Employer Dashboard Page', 'wp-career-board' ); ?></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'             => 'wcb_settings[employer_dashboard_page]',
									'selected'         => isset( $settings['employer_dashboard_page'] ) ? (int) $settings['employer_dashboard_page'] : 0,
									'show_option_none' => esc_html__( '— Select —', 'wp-career-board' ),
								)
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Candidate Dashboard Page', 'wp-career-board' ); ?></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'             => 'wcb_settings[candidate_dashboard_page]',
									'selected'         => isset( $settings['candidate_dashboard_page'] ) ? (int) $settings['candidate_dashboard_page'] : 0,
									'show_option_none' => esc_html__( '— Select —', 'wp-career-board' ),
								)
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Jobs Archive Page', 'wp-career-board' ); ?></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'             => 'wcb_settings[jobs_archive_page]',
									'selected'         => isset( $settings['jobs_archive_page'] ) ? (int) $settings['jobs_archive_page'] : 0,
									'show_option_none' => esc_html__( '— Select —', 'wp-career-board' ),
								)
							);
							?>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
