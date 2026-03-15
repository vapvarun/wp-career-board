<?php
/**
 * Block render: wcb/job-form — multi-step job posting form for employers.
 *
 * WordPress injects:
 *   $attributes  (array)    Block attributes defined in block.json.
 *   $content     (string)   Inner block content (empty for this block).
 *   $block       (WP_Block) Block instance object.
 *
 * Developer extensibility hooks (all @since 1.0.0):
 *   Filter: wcb_job_form_initial_state( array $state, array $attributes )
 *   Action: wcb_job_form_step1_fields( array $attributes )
 *   Action: wcb_job_form_step2_fields( array $attributes )
 *   Action: wcb_job_form_step3_fields( array $attributes )
 *   Action: wcb_job_form_step4_preview( array $attributes )
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$wcb_can_post_job = function_exists( 'wp_is_ability_granted' )
	? wp_is_ability_granted( 'wcb_post_jobs' )
	: current_user_can( 'wcb_post_jobs' );

if ( ! is_user_logged_in() || ! $wcb_can_post_job ) {
	echo '<p>' . esc_html__( 'You must be logged in as an employer to post a job.', 'wp-career-board' ) . '</p>';
	return;
}

// ── Taxonomy terms ─────────────────────────────────────────────────────────
$wcb_categories_raw  = get_terms(
	array(
		'taxonomy'   => 'wcb_category',
		'hide_empty' => false,
	)
);
$wcb_job_types_raw   = get_terms(
	array(
		'taxonomy'   => 'wcb_job_type',
		'hide_empty' => false,
	)
);
$wcb_locations_raw   = get_terms(
	array(
		'taxonomy'   => 'wcb_location',
		'hide_empty' => false,
	)
);
$wcb_experiences_raw = get_terms(
	array(
		'taxonomy'   => 'wcb_experience',
		'hide_empty' => false,
	)
);

$wcb_categories  = is_wp_error( $wcb_categories_raw ) ? array() : $wcb_categories_raw;
$wcb_job_types   = is_wp_error( $wcb_job_types_raw ) ? array() : $wcb_job_types_raw;
$wcb_locations   = is_wp_error( $wcb_locations_raw ) ? array() : $wcb_locations_raw;
$wcb_experiences = is_wp_error( $wcb_experiences_raw ) ? array() : $wcb_experiences_raw;

// ── Slug → display-name maps (used by preview card getters in view.js) ─────
$wcb_type_names = array();
foreach ( $wcb_job_types as $wcb_term ) {
	$wcb_type_names[ $wcb_term->slug ] = $wcb_term->name;
}
$wcb_exp_names = array();
foreach ( $wcb_experiences as $wcb_term ) {
	$wcb_exp_names[ $wcb_term->slug ] = $wcb_term->name;
}
$wcb_location_names = array();
foreach ( $wcb_locations as $wcb_term ) {
	$wcb_location_names[ $wcb_term->slug ] = $wcb_term->name;
}
$wcb_category_names = array();
foreach ( $wcb_categories as $wcb_term ) {
	$wcb_category_names[ $wcb_term->slug ] = $wcb_term->name;
}

// ── Employer company name (for preview) ────────────────────────────────────
$wcb_user_id      = get_current_user_id();
$wcb_company_id   = (int) get_user_meta( $wcb_user_id, '_wcb_company_id', true );
$wcb_company_post = $wcb_company_id ? get_post( $wcb_company_id ) : null;
$wcb_company_name = ( $wcb_company_post instanceof \WP_Post ) ? $wcb_company_post->post_title : '';

// ── Currency options ───────────────────────────────────────────────────────
$wcb_currencies = array(
	'USD' => 'USD — US Dollar',
	'EUR' => 'EUR — Euro',
	'GBP' => 'GBP — British Pound',
	'CAD' => 'CAD — Canadian Dollar',
	'AUD' => 'AUD — Australian Dollar',
	'INR' => 'INR — Indian Rupee',
	'SGD' => 'SGD — Singapore Dollar',
);

/**
 * Filter the initial Interactivity API state for the job form block.
 *
 * Developers can add custom state keys here and extend view.js to handle them.
 *
 * @since 1.0.0
 *
 * @param array $state      Default initial state array.
 * @param array $attributes Block attributes.
 */
$wcb_initial_state = apply_filters(
	'wcb_job_form_initial_state',
	array(
		'step'            => 1,
		'title'           => '',
		'description'     => '',
		'salaryMin'       => '',
		'salaryMax'       => '',
		'currencyCode'    => 'USD',
		'remote'          => false,
		'deadline'        => '',
		'applyUrl'        => '',
		'applyEmail'      => '',
		'locationSlug'    => '',
		'typeSlug'        => '',
		'categorySlug'    => '',
		'expSlug'         => '',
		'tags'            => '',
		'companyName'     => $wcb_company_name,
		'submitting'      => false,
		'submitted'       => false,
		'jobUrl'          => '',
		'error'           => '',
		'validationError' => '',
		'apiBase'         => rest_url( 'wcb/v1' ),
		'nonce'           => wp_create_nonce( 'wp_rest' ),
		'customFields'    => (object) array(),
		'typeNames'       => (object) $wcb_type_names,
		'expNames'        => (object) $wcb_exp_names,
		'locationNames'   => (object) $wcb_location_names,
		'categoryNames'   => (object) $wcb_category_names,
	),
	$attributes
);

wp_interactivity_state( 'wcb-job-form', $wcb_initial_state );

// ── Step labels ────────────────────────────────────────────────────────────
$wcb_step_labels = array(
	1 => __( 'Basics', 'wp-career-board' ),
	2 => __( 'Details', 'wp-career-board' ),
	3 => __( 'Categories', 'wp-career-board' ),
	4 => __( 'Preview', 'wp-career-board' ),
);
?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-job-form-wrap' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-job-form"
>

	<!-- ── Step indicator ────────────────────────────────────────────────── -->
	<nav class="wcb-steps" aria-label="<?php esc_attr_e( 'Form progress', 'wp-career-board' ); ?>">
		<?php foreach ( $wcb_step_labels as $wcb_step_num => $wcb_step_label ) : ?>
			<span
				class="wcb-step<?php echo 1 === $wcb_step_num ? ' wcb-step--active' : ''; ?>"
				data-wp-class--wcb-step--active="state.step<?php echo esc_attr( (string) $wcb_step_num ); ?>Active"
				<?php if ( $wcb_step_num < 4 ) : ?>
				data-wp-class--wcb-step--done="state.step<?php echo esc_attr( (string) $wcb_step_num ); ?>Done"
				<?php endif; ?>
			>
				<span class="wcb-step__num"><?php echo esc_html( (string) $wcb_step_num ); ?></span>
				<span class="wcb-step__label"><?php echo esc_html( $wcb_step_label ); ?></span>
			</span>
			<?php if ( $wcb_step_num < 4 ) : ?>
				<span class="wcb-step__connector" aria-hidden="true"></span>
			<?php endif; ?>
		<?php endforeach; ?>
	</nav>

	<!-- ── Validation error banner ───────────────────────────────────────── -->
	<p
		class="wcb-form-error"
		data-wp-class--wcb-form-error--show="state.hasValidation"
		data-wp-text="state.validationError"
	></p>

	<!-- ── Submission success ────────────────────────────────────────────── -->
	<div class="wcb-form-success" data-wp-class--wcb-form-success--show="state.submitted">
		<span class="wcb-form-success__icon" aria-hidden="true">✓</span>
		<div>
			<p class="wcb-form-success__title">
				<?php esc_html_e( 'Job posted successfully!', 'wp-career-board' ); ?>
			</p>
			<a class="wcb-form-success__link" data-wp-bind--href="state.jobUrl">
				<?php esc_html_e( 'View your job listing →', 'wp-career-board' ); ?>
			</a>
		</div>
	</div>

	<!-- ── Form steps ────────────────────────────────────────────────────── -->
	<div class="wcb-form-steps" data-wp-class--wcb-form-steps--hidden="state.submitted">

		<!-- ── Step 1: Basics ──────────────────────────────────────────── -->
		<div class="wcb-form-step wcb-form-step--show" data-wp-class--wcb-form-step--show="state.isStep1">
			<h2 class="wcb-form-step__title">
				<?php esc_html_e( 'Job Basics', 'wp-career-board' ); ?>
			</h2>

			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-job-title">
					<?php esc_html_e( 'Job Title', 'wp-career-board' ); ?>
					<span class="wcb-required" aria-hidden="true">*</span>
				</label>
				<input
					id="wcb-job-title"
					type="text"
					class="wcb-field"
					placeholder="<?php esc_attr_e( 'e.g. Senior PHP Developer', 'wp-career-board' ); ?>"
					data-wcb-field="title"
					data-wp-bind--value="state.title"
					data-wp-on--input="actions.updateField"
					required
					autocomplete="off"
				/>
			</div>

			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-job-desc">
					<?php esc_html_e( 'Job Description', 'wp-career-board' ); ?>
					<span class="wcb-required" aria-hidden="true">*</span>
				</label>
				<textarea
					id="wcb-job-desc"
					class="wcb-field"
					rows="12"
					placeholder="<?php esc_attr_e( 'Describe the role, responsibilities and requirements…', 'wp-career-board' ); ?>"
					data-wcb-field="description"
					data-wp-bind--value="state.description"
					data-wp-on--input="actions.updateField"
					required
				></textarea>
				<span class="wcb-form-hint">
					<?php esc_html_e( 'Plain text or basic Markdown supported.', 'wp-career-board' ); ?>
				</span>
			</div>

			<?php
			/**
			 * Action: add custom fields after the default step 1 fields.
			 *
			 * @since 1.0.0
			 * @param array $attributes Block attributes.
			 */
			do_action( 'wcb_job_form_step1_fields', $attributes );
			?>

			<div class="wcb-form-nav wcb-form-nav--right">
				<button type="button" class="wcb-btn wcb-btn--primary" data-wp-on--click="actions.nextStep">
					<?php esc_html_e( 'Next: Details', 'wp-career-board' ); ?>
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
				</button>
			</div>
		</div>

		<!-- ── Step 2: Details ─────────────────────────────────────────── -->
		<div class="wcb-form-step" data-wp-class--wcb-form-step--show="state.isStep2">
			<h2 class="wcb-form-step__title">
				<?php esc_html_e( 'Job Details', 'wp-career-board' ); ?>
			</h2>

			<!-- Salary range -->
			<div class="wcb-form-field">
				<span class="wcb-form-label">
					<?php esc_html_e( 'Salary Range', 'wp-career-board' ); ?>
				</span>
				<div class="wcb-salary-row">
					<div class="wcb-field-group">
						<label class="wcb-field-group__label" for="wcb-currency">
							<?php esc_html_e( 'Currency', 'wp-career-board' ); ?>
						</label>
						<select
							id="wcb-currency"
							class="wcb-field"
							data-wcb-field="currencyCode"
							data-wp-bind--value="state.currencyCode"
							data-wp-on--change="actions.updateField"
						>
							<?php foreach ( $wcb_currencies as $wcb_code => $wcb_label ) : ?>
								<option value="<?php echo esc_attr( $wcb_code ); ?>">
									<?php echo esc_html( $wcb_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="wcb-field-group">
						<label class="wcb-field-group__label" for="wcb-salary-min">
							<?php esc_html_e( 'Min', 'wp-career-board' ); ?>
						</label>
						<input
							id="wcb-salary-min"
							type="number"
							class="wcb-field"
							placeholder="60000"
							min="0"
							data-wcb-field="salaryMin"
							data-wp-bind--value="state.salaryMin"
							data-wp-on--input="actions.updateField"
						/>
					</div>
					<div class="wcb-field-group">
						<label class="wcb-field-group__label" for="wcb-salary-max">
							<?php esc_html_e( 'Max', 'wp-career-board' ); ?>
						</label>
						<input
							id="wcb-salary-max"
							type="number"
							class="wcb-field"
							placeholder="90000"
							min="0"
							data-wcb-field="salaryMax"
							data-wp-bind--value="state.salaryMax"
							data-wp-on--input="actions.updateField"
						/>
					</div>
				</div>
				<span class="wcb-form-hint">
					<?php esc_html_e( 'Leave blank to hide salary from candidates.', 'wp-career-board' ); ?>
				</span>
			</div>

			<!-- Remote -->
			<div class="wcb-form-field">
				<label class="wcb-checkbox-label">
					<input
						type="checkbox"
						data-wp-bind--checked="state.remote"
						data-wp-on--change="actions.toggleRemote"
					/>
					<span><?php esc_html_e( 'Remote-friendly position', 'wp-career-board' ); ?></span>
				</label>
			</div>

			<!-- Deadline -->
			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-deadline">
					<?php esc_html_e( 'Application Deadline', 'wp-career-board' ); ?>
				</label>
				<input
					id="wcb-deadline"
					type="date"
					class="wcb-field wcb-field--date"
					data-wcb-field="deadline"
					data-wp-bind--value="state.deadline"
					data-wp-on--input="actions.updateField"
				/>
			</div>

			<!-- Apply URL -->
			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-apply-url">
					<?php esc_html_e( 'Apply URL', 'wp-career-board' ); ?>
				</label>
				<input
					id="wcb-apply-url"
					type="url"
					class="wcb-field"
					placeholder="https://yourcompany.com/careers/apply"
					data-wcb-field="applyUrl"
					data-wp-bind--value="state.applyUrl"
					data-wp-on--input="actions.updateField"
				/>
				<span class="wcb-form-hint">
					<?php esc_html_e( 'Link to your external ATS or application form.', 'wp-career-board' ); ?>
				</span>
			</div>

			<!-- Apply Email -->
			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-apply-email">
					<?php esc_html_e( 'Apply Email', 'wp-career-board' ); ?>
				</label>
				<input
					id="wcb-apply-email"
					type="email"
					class="wcb-field"
					placeholder="jobs@yourcompany.com"
					data-wcb-field="applyEmail"
					data-wp-bind--value="state.applyEmail"
					data-wp-on--input="actions.updateField"
				/>
				<span class="wcb-form-hint">
					<?php esc_html_e( 'Candidates can apply directly by email.', 'wp-career-board' ); ?>
				</span>
			</div>

			<?php
			/**
			 * Action: add custom fields after the default step 2 fields.
			 *
			 * @since 1.0.0
			 * @param array $attributes Block attributes.
			 */
			do_action( 'wcb_job_form_step2_fields', $attributes );
			?>

			<div class="wcb-form-nav">
				<button type="button" class="wcb-btn wcb-btn--ghost" data-wp-on--click="actions.prevStep">
					<?php esc_html_e( '← Back', 'wp-career-board' ); ?>
				</button>
				<button type="button" class="wcb-btn wcb-btn--primary" data-wp-on--click="actions.nextStep">
					<?php esc_html_e( 'Next: Categories', 'wp-career-board' ); ?>
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
				</button>
			</div>
		</div>

		<!-- ── Step 3: Categories ──────────────────────────────────────── -->
		<div class="wcb-form-step" data-wp-class--wcb-form-step--show="state.isStep3">
			<h2 class="wcb-form-step__title">
				<?php esc_html_e( 'Classify Your Job', 'wp-career-board' ); ?>
			</h2>

			<div class="wcb-form-grid">
				<div class="wcb-form-field">
					<label class="wcb-form-label" for="wcb-category">
						<?php esc_html_e( 'Category', 'wp-career-board' ); ?>
					</label>
					<select
						id="wcb-category"
						class="wcb-field"
						data-wcb-field="categorySlug"
						data-wp-bind--value="state.categorySlug"
						data-wp-on--change="actions.updateField"
					>
						<option value=""><?php esc_html_e( 'Select a category', 'wp-career-board' ); ?></option>
						<?php foreach ( $wcb_categories as $wcb_term ) : ?>
							<option value="<?php echo esc_attr( $wcb_term->slug ); ?>">
								<?php echo esc_html( $wcb_term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wcb-form-field">
					<label class="wcb-form-label" for="wcb-job-type">
						<?php esc_html_e( 'Job Type', 'wp-career-board' ); ?>
					</label>
					<select
						id="wcb-job-type"
						class="wcb-field"
						data-wcb-field="typeSlug"
						data-wp-bind--value="state.typeSlug"
						data-wp-on--change="actions.updateField"
					>
						<option value=""><?php esc_html_e( 'Select a job type', 'wp-career-board' ); ?></option>
						<?php foreach ( $wcb_job_types as $wcb_term ) : ?>
							<option value="<?php echo esc_attr( $wcb_term->slug ); ?>">
								<?php echo esc_html( $wcb_term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wcb-form-field">
					<label class="wcb-form-label" for="wcb-location">
						<?php esc_html_e( 'Location', 'wp-career-board' ); ?>
					</label>
					<select
						id="wcb-location"
						class="wcb-field"
						data-wcb-field="locationSlug"
						data-wp-bind--value="state.locationSlug"
						data-wp-on--change="actions.updateField"
					>
						<option value=""><?php esc_html_e( 'Select a location', 'wp-career-board' ); ?></option>
						<?php foreach ( $wcb_locations as $wcb_term ) : ?>
							<option value="<?php echo esc_attr( $wcb_term->slug ); ?>">
								<?php echo esc_html( $wcb_term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wcb-form-field">
					<label class="wcb-form-label" for="wcb-experience">
						<?php esc_html_e( 'Experience Level', 'wp-career-board' ); ?>
					</label>
					<select
						id="wcb-experience"
						class="wcb-field"
						data-wcb-field="expSlug"
						data-wp-bind--value="state.expSlug"
						data-wp-on--change="actions.updateField"
					>
						<option value=""><?php esc_html_e( 'Select experience level', 'wp-career-board' ); ?></option>
						<?php foreach ( $wcb_experiences as $wcb_term ) : ?>
							<option value="<?php echo esc_attr( $wcb_term->slug ); ?>">
								<?php echo esc_html( $wcb_term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<!-- Tags -->
			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-tags">
					<?php esc_html_e( 'Skills / Tags', 'wp-career-board' ); ?>
				</label>
				<input
					id="wcb-tags"
					type="text"
					class="wcb-field"
					placeholder="<?php esc_attr_e( 'e.g. React, TypeScript, Node.js', 'wp-career-board' ); ?>"
					data-wcb-field="tags"
					data-wp-bind--value="state.tags"
					data-wp-on--input="actions.updateField"
				/>
				<span class="wcb-form-hint">
					<?php esc_html_e( 'Comma-separated. Helps candidates find your job via keyword search.', 'wp-career-board' ); ?>
				</span>
			</div>

			<?php
			/**
			 * Action: add custom fields after the default step 3 fields.
			 *
			 * @since 1.0.0
			 * @param array $attributes Block attributes.
			 */
			do_action( 'wcb_job_form_step3_fields', $attributes );
			?>

			<div class="wcb-form-nav">
				<button type="button" class="wcb-btn wcb-btn--ghost" data-wp-on--click="actions.prevStep">
					<?php esc_html_e( '← Back', 'wp-career-board' ); ?>
				</button>
				<button type="button" class="wcb-btn wcb-btn--primary" data-wp-on--click="actions.nextStep">
					<?php esc_html_e( 'Preview Job', 'wp-career-board' ); ?>
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
				</button>
			</div>
		</div>

		<!-- ── Step 4: Preview & Submit ────────────────────────────────── -->
		<div class="wcb-form-step" data-wp-class--wcb-form-step--show="state.isStep4">
			<h2 class="wcb-form-step__title">
				<?php esc_html_e( 'Preview & Submit', 'wp-career-board' ); ?>
			</h2>

			<!-- Preview card — matches wcb-job-card design language -->
			<div class="wcb-preview-card">
				<div class="wcb-preview-card__header">
					<div class="wcb-preview-card__title-wrap">
						<h3 class="wcb-preview-card__title" data-wp-text="state.title"></h3>
						<p class="wcb-preview-card__company" data-wp-class--wcb-preview-card__company--show="state.hasCompany" data-wp-text="state.companyName"></p>
					</div>
					<span
						class="wcb-cbadge wcb-cbadge--remote"
						data-wp-class--wcb-cbadge--show="state.isRemote"
					><?php esc_html_e( 'Remote', 'wp-career-board' ); ?></span>
				</div>

				<!-- Badges row -->
				<div class="wcb-preview-card__badges">
					<span class="wcb-cbadge wcb-cbadge--type" data-wp-class--wcb-cbadge--show="state.hasType" data-wp-text="state.typeDisplay"></span>
					<span class="wcb-cbadge wcb-cbadge--exp" data-wp-class--wcb-cbadge--show="state.hasExp" data-wp-text="state.expDisplay"></span>
					<span class="wcb-cbadge wcb-cbadge--location" data-wp-class--wcb-cbadge--show="state.hasLocation" data-wp-text="state.locationDisplay"></span>
					<span class="wcb-cbadge wcb-cbadge--category" data-wp-class--wcb-cbadge--show="state.hasCategory" data-wp-text="state.categoryDisplay"></span>
				</div>

				<!-- Meta row -->
				<div class="wcb-preview-card__meta">
					<span class="wcb-preview-meta-item" data-wp-class--wcb-preview-meta-item--show="state.hasSalary">
						<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
						<span data-wp-text="state.salaryDisplay"></span>
					</span>
					<span class="wcb-preview-meta-item" data-wp-class--wcb-preview-meta-item--show="state.hasDeadline">
						<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
						<?php esc_html_e( 'Apply by', 'wp-career-board' ); ?>
						<span data-wp-text="state.deadline"></span>
					</span>
					<span class="wcb-preview-meta-item" data-wp-class--wcb-preview-meta-item--show="state.hasApplyUrl">
						<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
						<span class="wcb-preview-meta-item__url" data-wp-text="state.applyUrl"></span>
					</span>
					<span class="wcb-preview-meta-item" data-wp-class--wcb-preview-meta-item--show="state.hasApplyEmail">
						<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
						<span data-wp-text="state.applyEmail"></span>
					</span>
				</div>

				<?php
				/**
				 * Action: add custom content to the step 4 preview card.
				 *
				 * @since 1.0.0
				 * @param array $attributes Block attributes.
				 */
				do_action( 'wcb_job_form_step4_preview', $attributes );
				?>
			</div>

			<p class="wcb-form-notice">
				<?php esc_html_e( 'Review the details above. Go back to make changes before submitting.', 'wp-career-board' ); ?>
			</p>

			<p class="wcb-form-error" data-wp-class--wcb-form-error--show="state.hasError" data-wp-text="state.error"></p>

			<div class="wcb-form-nav">
				<button
					type="button"
					class="wcb-btn wcb-btn--ghost"
					data-wp-on--click="actions.prevStep"
					data-wp-bind--disabled="state.submitting"
				>
					<?php esc_html_e( '← Back', 'wp-career-board' ); ?>
				</button>
				<button
					type="button"
					class="wcb-btn wcb-btn--primary"
					data-wp-on--click="actions.submitJob"
					data-wp-bind--disabled="state.submitting"
					data-wp-class--wcb-is-submitting="state.submitting"
				>
					<span class="wcb-btn__label"><?php esc_html_e( 'Post Job', 'wp-career-board' ); ?></span>
					<span class="wcb-btn__spinner">
						<svg class="wcb-spinner" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
						<?php esc_html_e( 'Posting…', 'wp-career-board' ); ?>
					</span>
				</button>
			</div>
		</div>

	</div><!-- /.wcb-form-steps -->
</div>
