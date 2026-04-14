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
 * Registers meta boxes on the wcb_job and wcb_company edit screens and saves submitted values.
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
	 * Nonce action for the company meta boxes.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const COMPANY_NONCE_ACTION = 'wcb_company_meta_boxes';

	/**
	 * Nonce field name for the company meta boxes.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const COMPANY_NONCE_NAME = 'wcb_company_meta_nonce';

	/**
	 * Boot the meta box module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_wcb_job', array( $this, 'save_job_meta' ), 10, 2 );
		add_action( 'save_post_wcb_company', array( $this, 'save_company_meta' ), 10, 2 );
		// wcb_application is managed via REST; no save_post hook needed.
	}

	/**
	 * Register all meta boxes for wcb_job and wcb_company post types.
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

		add_meta_box(
			'wcb-company-details',
			__( 'Company Details', 'wp-career-board' ),
			array( $this, 'render_company_details_box' ),
			'wcb_company',
			'normal',
			'high'
		);

		add_meta_box(
			'wcb-company-social',
			__( 'Social Links', 'wp-career-board' ),
			array( $this, 'render_company_social_box' ),
			'wcb_company',
			'side',
			'default'
		);

		add_meta_box(
			'wcb-company-trust',
			__( 'Trust &amp; Visibility', 'wp-career-board' ),
			array( $this, 'render_company_trust_box' ),
			'wcb_company',
			'side',
			'high'
		);

		add_meta_box(
			'wcb-job-rejection',
			__( 'Rejection Reason', 'wp-career-board' ),
			array( $this, 'render_job_rejection_box' ),
			'wcb_job',
			'side',
			'default'
		);

		add_meta_box(
			'wcb-application-audit-log',
			__( 'Status History', 'wp-career-board' ),
			array( $this, 'render_application_audit_log_box' ),
			'wcb_application',
			'normal',
			'default'
		);
	}

	/**
	 * Render the Rejection Reason meta box on the job edit screen (read-only).
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post The current post object.
	 * @return void
	 */
	public function render_job_rejection_box( \WP_Post $post ): void {
		$reason = (string) get_post_meta( $post->ID, '_wcb_rejection_reason', true );
		if ( '' === $reason ) {
			echo '<p class="wcb-audit-log-empty">' . esc_html__( 'No rejection reason recorded.', 'wp-career-board' ) . '</p>';
			return;
		}
		echo '<p style="margin:0;">' . esc_html( $reason ) . '</p>';
	}

	/**
	 * Render the Status History (audit log) meta box on the application edit screen.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post The current post object.
	 * @return void
	 */
	public function render_application_audit_log_box( \WP_Post $post ): void {
		$log = (array) get_post_meta( $post->ID, '_wcb_status_log', true );
		if ( empty( $log ) ) {
			echo '<p class="wcb-audit-log-empty">' . esc_html__( 'No status changes recorded yet.', 'wp-career-board' ) . '</p>';
			return;
		}

		echo '<ul class="wcb-audit-log">';
		foreach ( array_reverse( $log ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$user      = isset( $entry['by'] ) ? get_userdata( (int) $entry['by'] ) : false;
			$user_name = $user instanceof \WP_User ? $user->display_name : __( 'System', 'wp-career-board' );
			$from      = isset( $entry['from'] ) ? (string) $entry['from'] : '';
			$to        = isset( $entry['to'] ) ? (string) $entry['to'] : '';
			$at        = isset( $entry['at'] ) ? (string) $entry['at'] : '';
			printf(
				'<li><span class="wcb-audit-log-date">%s</span><span>%s &rarr; %s &mdash; %s</span></li>',
				esc_html( $at ),
				esc_html( '' !== $from ? $from : __( '(none)', 'wp-career-board' ) ),
				esc_html( $to ),
				esc_html( $user_name )
			);
		}
		echo '</ul>';
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

		$wcb_salary_min          = (string) get_post_meta( $post->ID, '_wcb_salary_min', true );
		$wcb_salary_max          = (string) get_post_meta( $post->ID, '_wcb_salary_max', true );
		$wcb_salary_currency_raw = (string) get_post_meta( $post->ID, '_wcb_salary_currency', true );
		$wcb_salary_currency     = in_array( $wcb_salary_currency_raw, array( 'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'INR', 'SGD' ), true ) ? $wcb_salary_currency_raw : 'USD';
		$wcb_salary_type_raw     = (string) get_post_meta( $post->ID, '_wcb_salary_type', true );
		$wcb_salary_type         = in_array( $wcb_salary_type_raw, array( 'yearly', 'monthly', 'hourly' ), true ) ? $wcb_salary_type_raw : 'yearly';
		$wcb_meta_currencies     = array(
			'USD' => 'USD ($)',
			'EUR' => 'EUR (€)',
			'GBP' => 'GBP (£)',
			'CAD' => 'CAD (CA$)',
			'AUD' => 'AUD (A$)',
			'INR' => 'INR (₹)',
			'SGD' => 'SGD (S$)',
		);
		$wcb_remote              = '1' === (string) get_post_meta( $post->ID, '_wcb_remote', true );
		$wcb_featured            = '1' === (string) get_post_meta( $post->ID, '_wcb_featured', true );
		$wcb_deadline            = (string) get_post_meta( $post->ID, '_wcb_deadline', true );
		$wcb_company_id          = (int) get_post_meta( $post->ID, '_wcb_company_id', true );
		$wcb_company_name        = (string) get_post_meta( $post->ID, '_wcb_company_name', true );

		// Fall back to employer's linked company when no company is selected yet.
		if ( ! $wcb_company_id ) {
			$wcb_employer_company_id = (int) get_user_meta( (int) $post->post_author, '_wcb_company_id', true );
			if ( $wcb_employer_company_id ) {
				$wcb_company_id = $wcb_employer_company_id;
			}
		}

		$wcb_companies = get_posts(
			array(
				'post_type'      => 'wcb_company',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
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
				<label for="wcb_salary_currency"><?php esc_html_e( 'Currency', 'wp-career-board' ); ?></label>
				<select id="wcb_salary_currency" name="wcb_salary_currency">
					<?php foreach ( $wcb_meta_currencies as $wcb_code => $wcb_label ) : ?>
						<option value="<?php echo esc_attr( $wcb_code ); ?>" <?php selected( $wcb_salary_currency, $wcb_code ); ?>><?php echo esc_html( $wcb_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="wcb_salary_type"><?php esc_html_e( 'Per', 'wp-career-board' ); ?></label>
				<select id="wcb_salary_type" name="wcb_salary_type">
					<option value="yearly" <?php selected( $wcb_salary_type, 'yearly' ); ?>><?php esc_html_e( 'Year', 'wp-career-board' ); ?></option>
					<option value="monthly" <?php selected( $wcb_salary_type, 'monthly' ); ?>><?php esc_html_e( 'Month', 'wp-career-board' ); ?></option>
					<option value="hourly" <?php selected( $wcb_salary_type, 'hourly' ); ?>><?php esc_html_e( 'Hour', 'wp-career-board' ); ?></option>
				</select>
			</div>
			<div>
				<label for="wcb_salary_min"><?php esc_html_e( 'Salary Min', 'wp-career-board' ); ?></label>
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
				<label for="wcb_salary_max"><?php esc_html_e( 'Salary Max', 'wp-career-board' ); ?></label>
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
				<label for="wcb_company_id"><?php esc_html_e( 'Company', 'wp-career-board' ); ?></label>
				<?php if ( $wcb_companies ) : ?>
					<select id="wcb_company_id" name="wcb_company_id">
						<option value="0"><?php esc_html_e( '— Select a company —', 'wp-career-board' ); ?></option>
						<?php foreach ( $wcb_companies as $wcb_cid ) : ?>
							<option value="<?php echo esc_attr( (string) $wcb_cid ); ?>" <?php selected( $wcb_company_id, $wcb_cid ); ?>>
								<?php echo esc_html( (string) get_the_title( $wcb_cid ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: URL to add a new company */
							esc_html__( 'No companies yet. %s', 'wp-career-board' ),
							'<a href="' . esc_url( admin_url( 'post-new.php?post_type=wcb_company' ) ) . '">' . esc_html__( 'Add one', 'wp-career-board' ) . '</a>'
						);
						?>
					</p>
					<input type="text" id="wcb_company_name" name="wcb_company_name" aria-label="<?php esc_attr_e( 'Company name', 'wp-career-board' ); ?>" value="<?php echo esc_attr( $wcb_company_name ); ?>" placeholder="<?php esc_attr_e( 'Company name (displayed on listing)', 'wp-career-board' ); ?>" />
				<?php endif; ?>
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
			<div class="wcb-meta-full">
				<label>
					<input
						type="checkbox"
						name="wcb_featured"
						value="1"
						<?php checked( $wcb_featured ); ?>
					/>
					<?php esc_html_e( 'Featured listing', 'wp-career-board' ); ?>
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Company Details meta box.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post The current post object.
	 * @return void
	 */
	public function render_company_details_box( \WP_Post $post ): void {
		wp_nonce_field( self::COMPANY_NONCE_ACTION, self::COMPANY_NONCE_NAME );

		$wcb_company_featured = '1' === (string) get_post_meta( $post->ID, '_wcb_featured', true );
		$wcb_website          = (string) get_post_meta( $post->ID, '_wcb_website', true );
		$wcb_tagline          = (string) get_post_meta( $post->ID, '_wcb_tagline', true );
		$wcb_industry         = (string) get_post_meta( $post->ID, '_wcb_industry', true );
		$wcb_company_size     = (string) get_post_meta( $post->ID, '_wcb_company_size', true );
		$wcb_company_type     = (string) get_post_meta( $post->ID, '_wcb_company_type', true );
		$wcb_founded          = (string) get_post_meta( $post->ID, '_wcb_founded', true );
		$wcb_hq_location      = (string) get_post_meta( $post->ID, '_wcb_hq_location', true );

		$wcb_industries = \WCB\Core\Industries::all();

		$wcb_sizes = array(
			''          => __( '— Select Size —', 'wp-career-board' ),
			'1-10'      => __( '1–10 employees', 'wp-career-board' ),
			'11-50'     => __( '11–50 employees', 'wp-career-board' ),
			'51-200'    => __( '51–200 employees', 'wp-career-board' ),
			'201-500'   => __( '201–500 employees', 'wp-career-board' ),
			'501-1000'  => __( '501–1,000 employees', 'wp-career-board' ),
			'1001-5000' => __( '1,001–5,000 employees', 'wp-career-board' ),
			'5001+'     => __( '5,001+ employees', 'wp-career-board' ),
		);

		$wcb_types = array(
			''              => __( '— Select Type —', 'wp-career-board' ),
			'public'        => __( 'Public Company', 'wp-career-board' ),
			'private'       => __( 'Privately Held', 'wp-career-board' ),
			'self-employed' => __( 'Self-Employed / Freelance', 'wp-career-board' ),
			'nonprofit'     => __( 'Non-profit', 'wp-career-board' ),
			'government'    => __( 'Government Agency', 'wp-career-board' ),
			'educational'   => __( 'Educational Institution', 'wp-career-board' ),
			'partnership'   => __( 'Partnership', 'wp-career-board' ),
		);
		?>
		<div class="wcb-company-meta-grid">
			<div class="wcb-meta-full">
				<label>
					<input
						type="checkbox"
						name="wcb_company_featured"
						value="1"
						<?php checked( $wcb_company_featured ); ?>
					/>
					<?php esc_html_e( 'Featured company', 'wp-career-board' ); ?>
				</label>
			</div>

			<div class="wcb-meta-row wcb-meta-full">
				<label for="wcb_tagline"><?php esc_html_e( 'Tagline / Slogan', 'wp-career-board' ); ?></label>
				<input
					type="text"
					id="wcb_tagline"
					name="wcb_tagline"
					value="<?php echo esc_attr( $wcb_tagline ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. Building a better future, one product at a time', 'wp-career-board' ); ?>"
				/>
				<p class="description"><?php esc_html_e( 'Short company motto shown below the name.', 'wp-career-board' ); ?></p>
			</div>

			<div class="wcb-meta-row wcb-meta-full">
				<label for="wcb_website"><?php esc_html_e( 'Website URL', 'wp-career-board' ); ?></label>
				<input
					type="url"
					id="wcb_website"
					name="wcb_website"
					value="<?php echo esc_attr( $wcb_website ); ?>"
					placeholder="https://"
				/>
			</div>

			<div class="wcb-meta-row">
				<label for="wcb_industry"><?php esc_html_e( 'Industry', 'wp-career-board' ); ?></label>
				<select id="wcb_industry" name="wcb_industry">
					<?php foreach ( $wcb_industries as $wcb_val => $wcb_label ) : ?>
						<option value="<?php echo esc_attr( $wcb_val ); ?>" <?php selected( $wcb_industry, $wcb_val ); ?>>
							<?php echo esc_html( $wcb_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="wcb-meta-row">
				<label for="wcb_company_type"><?php esc_html_e( 'Company Type', 'wp-career-board' ); ?></label>
				<select id="wcb_company_type" name="wcb_company_type">
					<?php foreach ( $wcb_types as $wcb_val => $wcb_label ) : ?>
						<option value="<?php echo esc_attr( $wcb_val ); ?>" <?php selected( $wcb_company_type, $wcb_val ); ?>>
							<?php echo esc_html( $wcb_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="wcb-meta-row">
				<label for="wcb_company_size"><?php esc_html_e( 'Company Size', 'wp-career-board' ); ?></label>
				<select id="wcb_company_size" name="wcb_company_size">
					<?php foreach ( $wcb_sizes as $wcb_val => $wcb_label ) : ?>
						<option value="<?php echo esc_attr( $wcb_val ); ?>" <?php selected( $wcb_company_size, $wcb_val ); ?>>
							<?php echo esc_html( $wcb_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="wcb-meta-row">
				<label for="wcb_founded"><?php esc_html_e( 'Founded Year', 'wp-career-board' ); ?></label>
				<input
					type="number"
					id="wcb_founded"
					name="wcb_founded"
					value="<?php echo esc_attr( $wcb_founded ); ?>"
					min="1800"
					max="<?php echo esc_attr( (string) gmdate( 'Y' ) ); ?>"
					placeholder="e.g. 2012"
				/>
			</div>

			<div class="wcb-meta-row wcb-meta-full">
				<label for="wcb_hq_location"><?php esc_html_e( 'Headquarters Location', 'wp-career-board' ); ?></label>
				<input
					type="text"
					id="wcb_hq_location"
					name="wcb_hq_location"
					value="<?php echo esc_attr( $wcb_hq_location ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. San Francisco, CA, USA', 'wp-career-board' ); ?>"
				/>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Company Social Links meta box.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post The current post object.
	 * @return void
	 */
	public function render_company_social_box( \WP_Post $post ): void {
		$wcb_linkedin = (string) get_post_meta( $post->ID, '_wcb_linkedin', true );
		$wcb_twitter  = (string) get_post_meta( $post->ID, '_wcb_twitter', true );
		?>
		<div class="wcb-social-links">
			<div class="wcb-social-row">
				<label for="wcb_linkedin">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="#0a66c2" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
					<?php esc_html_e( 'LinkedIn URL', 'wp-career-board' ); ?>
				</label>
				<input type="url" id="wcb_linkedin" name="wcb_linkedin" value="<?php echo esc_attr( $wcb_linkedin ); ?>" placeholder="https://linkedin.com/company/…" />
			</div>
			<div class="wcb-social-row">
				<label for="wcb_twitter">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="#000" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.748l7.73-8.835L1.254 2.25H8.08l4.259 5.63 5.905-5.63zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
					<?php esc_html_e( 'X (Twitter) URL', 'wp-career-board' ); ?>
				</label>
				<input type="url" id="wcb_twitter" name="wcb_twitter" value="<?php echo esc_attr( $wcb_twitter ); ?>" placeholder="https://x.com/…" />
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Company Trust & Visibility meta box.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post The current post object.
	 * @return void
	 */
	public function render_company_trust_box( \WP_Post $post ): void {
		$wcb_trust          = (string) get_post_meta( $post->ID, '_wcb_trust_level', true );
		$wcb_trusted_labels = array(
			''         => __( 'None', 'wp-career-board' ),
			'verified' => __( 'Verified ✓', 'wp-career-board' ),
			'trusted'  => __( 'Trusted ★', 'wp-career-board' ),
			'premium'  => __( 'Premium ◆', 'wp-career-board' ),
		);
		?>
		<div class="wcb-trust-box">
			<label for="wcb_trust_level"><?php esc_html_e( 'Trust Level', 'wp-career-board' ); ?></label>
			<select id="wcb_trust_level" name="wcb_trust_level" style="width:100%; margin-top:4px;">
				<?php foreach ( $wcb_trusted_labels as $wcb_val => $wcb_label ) : ?>
					<option value="<?php echo esc_attr( $wcb_val ); ?>" <?php selected( $wcb_trust, $wcb_val ); ?>>
						<?php echo esc_html( $wcb_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description" style="margin-top:6px;"><?php esc_html_e( '"Verified" shows the checkmark badge on job listings.', 'wp-career-board' ); ?></p>
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

		// Salary currency.
		$salary_currency_raw = isset( $_POST['wcb_salary_currency'] ) ? sanitize_text_field( wp_unslash( $_POST['wcb_salary_currency'] ) ) : 'USD'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$salary_currency     = in_array( $salary_currency_raw, array( 'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'INR', 'SGD' ), true ) ? $salary_currency_raw : 'USD';
		update_post_meta( $post_id, '_wcb_salary_currency', $salary_currency );

		// Salary type.
		$salary_type_raw = isset( $_POST['wcb_salary_type'] ) ? sanitize_text_field( wp_unslash( $_POST['wcb_salary_type'] ) ) : 'yearly'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$salary_type     = in_array( $salary_type_raw, array( 'yearly', 'monthly', 'hourly' ), true ) ? $salary_type_raw : 'yearly';
		update_post_meta( $post_id, '_wcb_salary_type', $salary_type );

		// Salary min.
		$salary_min = isset( $_POST['wcb_salary_min'] ) ? sanitize_text_field( wp_unslash( $_POST['wcb_salary_min'] ) ) : '';
		update_post_meta( $post_id, '_wcb_salary_min', $salary_min );

		// Salary max.
		$salary_max = isset( $_POST['wcb_salary_max'] ) ? sanitize_text_field( wp_unslash( $_POST['wcb_salary_max'] ) ) : '';
		update_post_meta( $post_id, '_wcb_salary_max', $salary_max );

		// Deadline.
		$deadline = isset( $_POST['wcb_deadline'] ) ? sanitize_text_field( wp_unslash( $_POST['wcb_deadline'] ) ) : '';
		update_post_meta( $post_id, '_wcb_deadline', $deadline );

		// Company — prefer the select dropdown; fall back to the text input shown when no companies exist.
		$company_id = isset( $_POST['wcb_company_id'] ) ? absint( $_POST['wcb_company_id'] ) : 0;
		if ( $company_id > 0 ) {
			$company_post = get_post( $company_id );
			if ( $company_post instanceof \WP_Post && 'wcb_company' === $company_post->post_type ) {
				update_post_meta( $post_id, '_wcb_company_id', $company_id );
				update_post_meta( $post_id, '_wcb_company_name', $company_post->post_title );
			}
		} else {
			// Fallback text input (shown when no wcb_company posts exist yet).
			$company_name = isset( $_POST['wcb_company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wcb_company_name'] ) ) : '';
			delete_post_meta( $post_id, '_wcb_company_id' );
			if ( '' !== $company_name ) {
				update_post_meta( $post_id, '_wcb_company_name', $company_name );
			} else {
				delete_post_meta( $post_id, '_wcb_company_name' );
			}
		}

		// Remote flag.
		$remote = isset( $_POST['wcb_remote'] ) && '1' === $_POST['wcb_remote'] ? '1' : '0';
		update_post_meta( $post_id, '_wcb_remote', $remote );

		// Featured flag.
		$featured = isset( $_POST['wcb_featured'] ) && '1' === $_POST['wcb_featured'] ? '1' : '0';
		update_post_meta( $post_id, '_wcb_featured', $featured );
	}

	/**
	 * Save company meta values when the post is saved.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id Post ID being saved.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_company_meta( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( 'revision' === $post->post_type ) {
			return;
		}

		$nonce = isset( $_POST[ self::COMPANY_NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::COMPANY_NONCE_NAME ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::COMPANY_NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$allowed_sizes = array( '', '1-10', '11-50', '51-200', '201-500', '501-1000', '1001-5000', '5001+' );
		$allowed_types = array( '', 'public', 'private', 'self-employed', 'nonprofit', 'government', 'educational', 'partnership' );
		$allowed_trust = array( '', 'verified', 'trusted', 'premium' );

		$wcb_text_fields = array(
			'wcb_website'     => '_wcb_website',
			'wcb_tagline'     => '_wcb_tagline',
			'wcb_industry'    => '_wcb_industry',
			'wcb_founded'     => '_wcb_founded',
			'wcb_hq_location' => '_wcb_hq_location',
			'wcb_linkedin'    => '_wcb_linkedin',
			'wcb_twitter'     => '_wcb_twitter',
		);
		foreach ( $wcb_text_fields as $wcb_post_key => $wcb_meta_key ) {
			$wcb_value = isset( $_POST[ $wcb_post_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $wcb_post_key ] ) ) : '';
			update_post_meta( $post_id, $wcb_meta_key, $wcb_value );
		}

		$wcb_size = isset( $_POST['wcb_company_size'] ) ? sanitize_text_field( wp_unslash( $_POST['wcb_company_size'] ) ) : '';
		if ( in_array( $wcb_size, $allowed_sizes, true ) ) {
			update_post_meta( $post_id, '_wcb_company_size', $wcb_size );
		}

		$wcb_type = isset( $_POST['wcb_company_type'] ) ? sanitize_text_field( wp_unslash( $_POST['wcb_company_type'] ) ) : '';
		if ( in_array( $wcb_type, $allowed_types, true ) ) {
			update_post_meta( $post_id, '_wcb_company_type', $wcb_type );
		}

		$wcb_trust = isset( $_POST['wcb_trust_level'] ) ? sanitize_text_field( wp_unslash( $_POST['wcb_trust_level'] ) ) : '';
		if ( in_array( $wcb_trust, $allowed_trust, true ) ) {
			update_post_meta( $post_id, '_wcb_trust_level', $wcb_trust );
		}

		$wcb_featured = isset( $_POST['wcb_company_featured'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['wcb_company_featured'] ) ) ? '1' : '0';
		update_post_meta( $post_id, '_wcb_featured', $wcb_featured );
	}
}
