<?php
/**
 * Block render: wcb/job-form — 4-step job posting form for employers.
 *
 * WordPress injects:
 *   $attributes  (array)    Block attributes defined in block.json.
 *   $content     (string)   Inner block content (empty for this block).
 *   $block       (WP_Block) Block instance object.
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

wp_interactivity_state(
	'wcb-job-form',
	array(
		'step'         => 1,
		'title'        => '',
		'description'  => '',
		'salaryMin'    => '',
		'salaryMax'    => '',
		'remote'       => false,
		'deadline'     => '',
		'locationSlug' => '',
		'typeSlug'     => '',
		'categorySlug' => '',
		'expSlug'      => '',
		'submitting'   => false,
		'submitted'    => false,
		'jobUrl'       => '',
		'error'        => '',
		'apiBase'      => rest_url( 'wcb/v1' ),
		'nonce'        => wp_create_nonce( 'wp_rest' ),
	)
);
?>
<div
	<?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-job-form"
>
	<!-- Step indicator -->
	<div class="wcb-steps">
		<?php
		$wcb_step_labels = array(
			1 => __( 'Basics', 'wp-career-board' ),
			2 => __( 'Details', 'wp-career-board' ),
			3 => __( 'Categories', 'wp-career-board' ),
			4 => __( 'Preview', 'wp-career-board' ),
		);
		foreach ( $wcb_step_labels as $wcb_step_num => $wcb_step_label ) :
			?>
			<span class="wcb-step" data-wcb-step="<?php echo esc_attr( (string) $wcb_step_num ); ?>">
				<?php echo esc_html( $wcb_step_label ); ?>
			</span>
		<?php endforeach; ?>
	</div>

	<!-- Submission success -->
	<div class="wcb-form-success" data-wp-show="state.submitted">
		<p><?php esc_html_e( 'Job posted successfully!', 'wp-career-board' ); ?></p>
		<a data-wp-bind--href="state.jobUrl"><?php esc_html_e( 'View your job listing', 'wp-career-board' ); ?></a>
	</div>

	<div data-wp-show="!state.submitted">

		<!-- Step 1: Title + Description -->
		<div class="wcb-form-step" data-wp-show="state.isStep1">
			<h2><?php esc_html_e( 'Job Basics', 'wp-career-board' ); ?></h2>

			<label for="wcb-job-title"><?php esc_html_e( 'Job Title', 'wp-career-board' ); ?> <span aria-hidden="true">*</span></label>
			<input
				id="wcb-job-title"
				type="text"
				class="wcb-field"
				placeholder="<?php esc_attr_e( 'e.g. Senior PHP Developer', 'wp-career-board' ); ?>"
				data-wcb-field="title"
				data-wp-bind--value="state.title"
				data-wp-on--input="actions.updateField"
				required
			/>

			<label for="wcb-job-desc"><?php esc_html_e( 'Job Description', 'wp-career-board' ); ?> <span aria-hidden="true">*</span></label>
			<textarea
				id="wcb-job-desc"
				class="wcb-field"
				rows="10"
				placeholder="<?php esc_attr_e( 'Describe the role, responsibilities and requirements…', 'wp-career-board' ); ?>"
				data-wcb-field="description"
				data-wp-on--input="actions.updateField"
			></textarea>

			<button type="button" class="wcb-btn wcb-btn-next" data-wp-on--click="actions.nextStep">
				<?php esc_html_e( 'Next', 'wp-career-board' ); ?>
			</button>
		</div>

		<!-- Step 2: Salary + Remote + Deadline -->
		<div class="wcb-form-step" data-wp-show="state.isStep2">
			<h2><?php esc_html_e( 'Job Details', 'wp-career-board' ); ?></h2>

			<div class="wcb-salary-row">
				<div class="wcb-field-group">
					<label for="wcb-salary-min"><?php esc_html_e( 'Salary Min', 'wp-career-board' ); ?></label>
					<input
						id="wcb-salary-min"
						type="text"
						class="wcb-field"
						placeholder="<?php esc_attr_e( 'e.g. 60000', 'wp-career-board' ); ?>"
						data-wcb-field="salaryMin"
						data-wp-bind--value="state.salaryMin"
						data-wp-on--input="actions.updateField"
					/>
				</div>
				<div class="wcb-field-group">
					<label for="wcb-salary-max"><?php esc_html_e( 'Salary Max', 'wp-career-board' ); ?></label>
					<input
						id="wcb-salary-max"
						type="text"
						class="wcb-field"
						placeholder="<?php esc_attr_e( 'e.g. 90000', 'wp-career-board' ); ?>"
						data-wcb-field="salaryMax"
						data-wp-bind--value="state.salaryMax"
						data-wp-on--input="actions.updateField"
					/>
				</div>
			</div>

			<label class="wcb-checkbox-label">
				<input
					type="checkbox"
					data-wp-bind--checked="state.remote"
					data-wp-on--change="actions.toggleRemote"
				/>
				<?php esc_html_e( 'Remote-friendly position', 'wp-career-board' ); ?>
			</label>

			<label for="wcb-deadline"><?php esc_html_e( 'Application Deadline', 'wp-career-board' ); ?></label>
			<input
				id="wcb-deadline"
				type="date"
				class="wcb-field"
				data-wcb-field="deadline"
				data-wp-bind--value="state.deadline"
				data-wp-on--input="actions.updateField"
			/>

			<div class="wcb-form-nav">
				<button type="button" class="wcb-btn wcb-btn-back" data-wp-on--click="actions.prevStep">
					<?php esc_html_e( 'Back', 'wp-career-board' ); ?>
				</button>
				<button type="button" class="wcb-btn wcb-btn-next" data-wp-on--click="actions.nextStep">
					<?php esc_html_e( 'Next', 'wp-career-board' ); ?>
				</button>
			</div>
		</div>

		<!-- Step 3: Taxonomy selects -->
		<div class="wcb-form-step" data-wp-show="state.isStep3">
			<h2><?php esc_html_e( 'Categories', 'wp-career-board' ); ?></h2>

			<label for="wcb-category"><?php esc_html_e( 'Category', 'wp-career-board' ); ?></label>
			<select
				id="wcb-category"
				class="wcb-field"
				data-wcb-field="categorySlug"
				data-wp-on--change="actions.updateField"
			>
				<option value=""><?php esc_html_e( 'Select a category', 'wp-career-board' ); ?></option>
				<?php foreach ( $wcb_categories as $wcb_term ) : ?>
					<option value="<?php echo esc_attr( $wcb_term->slug ); ?>"><?php echo esc_html( $wcb_term->name ); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="wcb-job-type"><?php esc_html_e( 'Job Type', 'wp-career-board' ); ?></label>
			<select
				id="wcb-job-type"
				class="wcb-field"
				data-wcb-field="typeSlug"
				data-wp-on--change="actions.updateField"
			>
				<option value=""><?php esc_html_e( 'Select a job type', 'wp-career-board' ); ?></option>
				<?php foreach ( $wcb_job_types as $wcb_term ) : ?>
					<option value="<?php echo esc_attr( $wcb_term->slug ); ?>"><?php echo esc_html( $wcb_term->name ); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="wcb-location"><?php esc_html_e( 'Location', 'wp-career-board' ); ?></label>
			<select
				id="wcb-location"
				class="wcb-field"
				data-wcb-field="locationSlug"
				data-wp-on--change="actions.updateField"
			>
				<option value=""><?php esc_html_e( 'Select a location', 'wp-career-board' ); ?></option>
				<?php foreach ( $wcb_locations as $wcb_term ) : ?>
					<option value="<?php echo esc_attr( $wcb_term->slug ); ?>"><?php echo esc_html( $wcb_term->name ); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="wcb-experience"><?php esc_html_e( 'Experience Level', 'wp-career-board' ); ?></label>
			<select
				id="wcb-experience"
				class="wcb-field"
				data-wcb-field="expSlug"
				data-wp-on--change="actions.updateField"
			>
				<option value=""><?php esc_html_e( 'Select an experience level', 'wp-career-board' ); ?></option>
				<?php foreach ( $wcb_experiences as $wcb_term ) : ?>
					<option value="<?php echo esc_attr( $wcb_term->slug ); ?>"><?php echo esc_html( $wcb_term->name ); ?></option>
				<?php endforeach; ?>
			</select>

			<div class="wcb-form-nav">
				<button type="button" class="wcb-btn wcb-btn-back" data-wp-on--click="actions.prevStep">
					<?php esc_html_e( 'Back', 'wp-career-board' ); ?>
				</button>
				<button type="button" class="wcb-btn wcb-btn-next" data-wp-on--click="actions.nextStep">
					<?php esc_html_e( 'Preview', 'wp-career-board' ); ?>
				</button>
			</div>
		</div>

		<!-- Step 4: Preview + Submit -->
		<div class="wcb-form-step" data-wp-show="state.isStep4">
			<h2><?php esc_html_e( 'Preview & Submit', 'wp-career-board' ); ?></h2>

			<div class="wcb-preview-card">
				<h3 data-wp-text="state.title"></h3>
				<p class="wcb-preview-meta" data-wp-text="state.locationSlug"></p>
				<p class="wcb-preview-meta" data-wp-text="state.typeSlug"></p>
			</div>

			<p class="wcb-form-error" data-wp-show="state.error" data-wp-text="state.error"></p>

			<div class="wcb-form-nav">
				<button type="button" class="wcb-btn wcb-btn-back" data-wp-on--click="actions.prevStep" data-wp-bind--disabled="state.submitting">
					<?php esc_html_e( 'Back', 'wp-career-board' ); ?>
				</button>
				<button
					type="button"
					class="wcb-btn wcb-btn-submit"
					data-wp-on--click="actions.submitJob"
					data-wp-bind--disabled="state.submitting"
				>
					<span data-wp-show="!state.submitting"><?php esc_html_e( 'Post Job', 'wp-career-board' ); ?></span>
					<span data-wp-show="state.submitting"><?php esc_html_e( 'Posting…', 'wp-career-board' ); ?></span>
				</button>
			</div>
		</div>

	</div><!-- /!submitted -->
</div>
