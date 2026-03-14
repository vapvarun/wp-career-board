<?php
/**
 * Admin meta boxes — registers and saves custom fields for wcb_job CPT.
 *
 * Provides editable fields for: salary range, remote flag, application deadline,
 * and company name. These correspond directly to the post-meta keys used by the
 * REST API and the frontend blocks.
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
 * Registers meta boxes on the wcb_job edit screen and saves submitted values.
 *
 * @since 1.0.0
 */
class AdminMetaBoxes {

	/**
	 * Nonce action for the job details meta box.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NONCE_ACTION = 'wcb_job_meta_boxes';

	/**
	 * Nonce field name for the job details meta box.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NONCE_NAME = 'wcb_job_meta_nonce';

	/**
	 * Boot the meta box module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_wcb_job', array( $this, 'save_job_meta' ), 10, 2 );
	}

	/**
	 * Register all meta boxes for the wcb_job post type.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_meta_boxes(): void {
		add_meta_box(
			'wcb-job-details',
			__( 'Job Details', 'wp-career-board' ),
			array( $this, 'render_job_details_box' ),
			'wcb_job',
			'normal',
			'high'
		);
	}

	/**
	 * Render the Job Details meta box.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post The current post object.
	 * @return void
	 */
	public function render_job_details_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$wcb_settings     = (array) get_option( 'wcb_settings', array() );
		$wcb_currency     = isset( $wcb_settings['salary_currency'] ) ? $wcb_settings['salary_currency'] : '$';
		$wcb_salary_min   = (string) get_post_meta( $post->ID, '_wcb_salary_min', true );
		$wcb_salary_max   = (string) get_post_meta( $post->ID, '_wcb_salary_max', true );
		$wcb_remote       = '1' === (string) get_post_meta( $post->ID, '_wcb_remote', true );
		$wcb_deadline     = (string) get_post_meta( $post->ID, '_wcb_deadline', true );
		$wcb_company_name = (string) get_post_meta( $post->ID, '_wcb_company_name', true );

		// Employer's linked company (auto-fill suggestion).
		$wcb_employer_company_id = (int) get_user_meta( (int) $post->post_author, '_wcb_company_id', true );
		$wcb_employer_company    = $wcb_employer_company_id ? get_post( $wcb_employer_company_id ) : null;
		if ( ! $wcb_company_name && $wcb_employer_company instanceof \WP_Post ) {
			$wcb_company_name = $wcb_employer_company->post_title;
		}
		?>
		<style>
			.wcb-meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 20px; padding:8px 0; }
			.wcb-meta-full { grid-column:1/-1; }
			.wcb-meta-grid label { display:block; font-weight:600; margin-bottom:4px; }
			.wcb-meta-grid input[type=text],
			.wcb-meta-grid input[type=number],
			.wcb-meta-grid input[type=date] { width:100%; }
			.wcb-salary-prefix { display:inline-flex; align-items:center; gap:6px; }
			.wcb-salary-prefix span { font-size:13px; line-height:1; }
		</style>
		<div class="wcb-meta-grid">
			<div>
				<label for="wcb_salary_min">
					<?php
					/* translators: %s: currency symbol */
					printf( esc_html__( 'Salary Min (%s)', 'wp-career-board' ), esc_html( $wcb_currency ) );
					?>
				</label>
				<input
					type="number"
					id="wcb_salary_min"
					name="wcb_salary_min"
					value="<?php echo esc_attr( $wcb_salary_min ); ?>"
					min="0"
					step="1000"
					placeholder="e.g. 60000"
				/>
			</div>
			<div>
				<label for="wcb_salary_max">
					<?php
					/* translators: %s: currency symbol */
					printf( esc_html__( 'Salary Max (%s)', 'wp-career-board' ), esc_html( $wcb_currency ) );
					?>
				</label>
				<input
					type="number"
					id="wcb_salary_max"
					name="wcb_salary_max"
					value="<?php echo esc_attr( $wcb_salary_max ); ?>"
					min="0"
					step="1000"
					placeholder="e.g. 90000"
				/>
			</div>
			<div>
				<label for="wcb_deadline"><?php esc_html_e( 'Application Deadline', 'wp-career-board' ); ?></label>
				<input
					type="date"
					id="wcb_deadline"
					name="wcb_deadline"
					value="<?php echo esc_attr( $wcb_deadline ); ?>"
				/>
			</div>
			<div>
				<label for="wcb_company_name"><?php esc_html_e( 'Company Name', 'wp-career-board' ); ?></label>
				<input
					type="text"
					id="wcb_company_name"
					name="wcb_company_name"
					value="<?php echo esc_attr( $wcb_company_name ); ?>"
					placeholder="<?php esc_attr_e( 'Displayed on job listing', 'wp-career-board' ); ?>"
				/>
			</div>
			<div class="wcb-meta-full">
				<label>
					<input
						type="checkbox"
						name="wcb_remote"
						value="1"
						<?php checked( $wcb_remote ); ?>
					/>
					<?php esc_html_e( 'Remote-friendly position', 'wp-career-board' ); ?>
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Save job meta values when the post is saved.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id Post ID being saved.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_job_meta( int $post_id, \WP_Post $post ): void {
		// Bail on autosaves and revisions.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( 'revision' === $post->post_type ) {
			return;
		}

		// Verify nonce.
		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		// Check permission.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Salary min.
		$salary_min = isset( $_POST['wcb_salary_min'] ) ? sanitize_text_field( wp_unslash( $_POST['wcb_salary_min'] ) ) : '';
		update_post_meta( $post_id, '_wcb_salary_min', $salary_min );

		// Salary max.
		$salary_max = isset( $_POST['wcb_salary_max'] ) ? sanitize_text_field( wp_unslash( $_POST['wcb_salary_max'] ) ) : '';
		update_post_meta( $post_id, '_wcb_salary_max', $salary_max );

		// Deadline.
		$deadline = isset( $_POST['wcb_deadline'] ) ? sanitize_text_field( wp_unslash( $_POST['wcb_deadline'] ) ) : '';
		update_post_meta( $post_id, '_wcb_deadline', $deadline );

		// Company name.
		$company_name = isset( $_POST['wcb_company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wcb_company_name'] ) ) : '';
		update_post_meta( $post_id, '_wcb_company_name', $company_name );

		// Remote flag.
		$remote = isset( $_POST['wcb_remote'] ) && '1' === $_POST['wcb_remote'] ? '1' : '0';
		update_post_meta( $post_id, '_wcb_remote', $remote );
	}
}
