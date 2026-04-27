<?php
/**
 * Block render: wcb/job-form-simple — single-page job posting form.
 *
 * Sibling of wcb/job-form (the multi-step wizard) for embeds where every
 * field on one screen is the right UX: sidebars, modals, partner pages,
 * single-page sites, classic themes without much vertical real estate.
 *
 * Submits to the same POST /wcb/v1/jobs endpoint and honours the same
 * extensibility hooks as the wizard:
 *   Filter: wcb_job_form_fields( array $groups, int $board_id )
 *   Filter: wcb_job_form_simple_initial_state( array $state, array $attributes )
 *   Action: wcb_job_form_simple_extra_fields( array $attributes )
 *
 * Does NOT support edit mode by design — single-page form is for posting,
 * not editing. Editing routes through the wizard.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$wcb_can_post_job = function_exists( 'wp_is_ability_granted' )
	? wp_is_ability_granted( 'wcb_post_jobs' )
	: current_user_can( 'wcb_post_jobs' );

if ( ! is_user_logged_in() || ! $wcb_can_post_job ) {
	echo '<p class="wcb-form-simple__gate">' . esc_html__( 'You must be logged in as an employer to post a job.', 'wp-career-board' ) . '</p>';
	return;
}

$wcb_board_id_attr     = isset( $attributes['boardId'] ) ? (int) $attributes['boardId'] : 0;
$wcb_show_company_attr = ! isset( $attributes['showCompanyField'] ) || (bool) $attributes['showCompanyField'];
$wcb_compact_attr      = ! empty( $attributes['compact'] );

// ── Taxonomy terms ─────────────────────────────────────────────────────────
$wcb_term_args = static function ( string $tax ): array {
	return array(
		'taxonomy'   => $tax,
		'hide_empty' => false,
	);
};

$wcb_categories  = array_filter( (array) get_terms( $wcb_term_args( 'wcb_category' ) ), static fn( $t ) => $t instanceof \WP_Term );
$wcb_job_types   = array_filter( (array) get_terms( $wcb_term_args( 'wcb_job_type' ) ), static fn( $t ) => $t instanceof \WP_Term );
$wcb_locations   = array_filter( (array) get_terms( $wcb_term_args( 'wcb_location' ) ), static fn( $t ) => $t instanceof \WP_Term );
$wcb_experiences = array_filter( (array) get_terms( $wcb_term_args( 'wcb_experience' ) ), static fn( $t ) => $t instanceof \WP_Term );

// ── Currency: employer preference → admin setting → USD ──────────────────────
$wcb_user_id      = get_current_user_id();
$wcb_company_id   = (int) get_user_meta( $wcb_user_id, '_wcb_company_id', true );
$wcb_company_post = $wcb_company_id ? get_post( $wcb_company_id ) : null;
$wcb_company_name = ( $wcb_company_post instanceof \WP_Post ) ? $wcb_company_post->post_title : '';

$wcb_preferred = (string) get_user_meta( $wcb_user_id, '_wcb_preferred_currency', true );
if ( ! $wcb_preferred ) {
	$wcb_settings  = (array) get_option( 'wcb_settings', array() );
	$wcb_preferred = ! empty( $wcb_settings['salary_currency'] ) ? $wcb_settings['salary_currency'] : 'USD';
}
$wcb_default_currency = in_array( $wcb_preferred, array( 'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'INR', 'SGD' ), true ) ? $wcb_preferred : 'USD';

$wcb_currencies = array(
	'USD' => __( 'USD — US Dollar', 'wp-career-board' ),
	'EUR' => __( 'EUR — Euro', 'wp-career-board' ),
	'GBP' => __( 'GBP — British Pound', 'wp-career-board' ),
	'CAD' => __( 'CAD — Canadian Dollar', 'wp-career-board' ),
	'AUD' => __( 'AUD — Australian Dollar', 'wp-career-board' ),
	'INR' => __( 'INR — Indian Rupee', 'wp-career-board' ),
	'SGD' => __( 'SGD — Singapore Dollar', 'wp-career-board' ),
);

// ── Initial Interactivity state ────────────────────────────────────────────
/**
 * Filter the initial state for the single-page job form.
 *
 * @since 1.1.0
 *
 * @param array $state      Default initial state.
 * @param array $attributes Block attributes.
 */
$wcb_state = apply_filters(
	'wcb_job_form_simple_initial_state',
	array(
		'title'             => '',
		'description'       => '',
		'salaryMin'         => '',
		'salaryMax'         => '',
		'currencyCode'      => $wcb_default_currency,
		'salaryType'        => 'yearly',
		'remote'            => false,
		'deadline'          => '',
		'applyUrl'          => '',
		'applyEmail'        => '',
		'locationSlug'      => '',
		'typeSlug'          => '',
		'categorySlug'      => '',
		'expSlug'           => '',
		'tags'              => '',
		'boardId'           => $wcb_board_id_attr,
		'companyName'       => $wcb_company_name,
		'submitting'        => false,
		'submitted'         => false,
		'jobUrl'            => '',
		'error'             => '',
		'apiBase'           => rest_url( 'wcb/v1' ),
		'nonce'             => wp_create_nonce( 'wp_rest' ),
		'creditCost'        => (int) apply_filters( 'wcb_board_credit_cost', 0, $wcb_board_id_attr ),
		'creditBalance'     => (int) apply_filters( 'wcb_employer_credit_balance', 0, $wcb_user_id ),
		'creditPurchaseUrl' => (string) apply_filters( 'wcb_credit_purchase_url', '' ),
		'customFieldGroups' => apply_filters( 'wcb_job_form_fields', array(), $wcb_board_id_attr ),
		'customFields'      => (object) array(),
		'strings'           => array(
			'errorConnection' => __( 'Connection error. Please check your network and try again.', 'wp-career-board' ),
			'errorGeneric'    => __( 'Job could not be posted. Please try again.', 'wp-career-board' ),
		),
	),
	$attributes
);

wp_interactivity_state( 'wcb-job-form-simple', $wcb_state );

$wcb_wrapper_class = 'wcb-form-simple' . ( $wcb_compact_attr ? ' wcb-form-simple--compact' : '' );
?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => $wcb_wrapper_class ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-job-form-simple"
>

	<!-- Honeypot — bots filling all fields trigger a fake success. -->
	<label class="wcb-hp" aria-hidden="true">
		<span><?php esc_html_e( 'Leave this field blank', 'wp-career-board' ); ?></span>
		<input type="text" id="wcb-hp-simple" name="wcb_hp" tabindex="-1" autocomplete="off" />
	</label>

	<!-- Success state -->
	<div class="wcb-form-simple__success" data-wp-class--wcb-shown="state.submitted">
		<h2><?php esc_html_e( '✓ Job posted', 'wp-career-board' ); ?></h2>
		<p data-wp-class--wcb-hidden="!state.jobUrl">
			<a class="wcb-btn wcb-btn--primary" data-wp-bind--href="state.jobUrl" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'View your job', 'wp-career-board' ); ?>
			</a>
		</p>
	</div>

	<!-- Form -->
	<div class="wcb-form-simple__body" data-wp-class--wcb-hidden="state.submitted">

		<!-- Error banner -->
		<p class="wcb-form-simple__error" data-wp-class--wcb-shown="state.error" data-wp-text="state.error"></p>

		<!-- ── Section 1: Basics ───────────────────────────────────── -->
		<section class="wcb-form-simple__section">
			<p class="wcb-form-simple__eyebrow"><?php esc_html_e( 'About the role', 'wp-career-board' ); ?></p>

			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-simple-title">
					<?php esc_html_e( 'Job Title', 'wp-career-board' ); ?>
					<span class="wcb-required" aria-hidden="true">*</span>
				</label>
				<input
					id="wcb-simple-title"
					type="text"
					class="wcb-field"
					placeholder="<?php esc_attr_e( 'e.g. Senior PHP Developer', 'wp-career-board' ); ?>"
					data-wcb-field="title"
					data-wp-bind--value="state.title"
					data-wp-on--input="actions.updateField"
					required
				/>
			</div>

			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-simple-desc">
					<?php esc_html_e( 'Job Description', 'wp-career-board' ); ?>
					<span class="wcb-required" aria-hidden="true">*</span>
				</label>
				<textarea
					id="wcb-simple-desc"
					class="wcb-field"
					rows="8"
					placeholder="<?php esc_attr_e( 'Describe the role, responsibilities and requirements…', 'wp-career-board' ); ?>"
					data-wcb-field="description"
					data-wp-bind--value="state.description"
					data-wp-on--input="actions.updateField"
					required
				></textarea>
			</div>
		</section>

		<!-- ── Section 2: Classification ───────────────────────────── -->
		<section class="wcb-form-simple__section">
			<p class="wcb-form-simple__eyebrow"><?php esc_html_e( 'Classification', 'wp-career-board' ); ?></p>

			<div class="wcb-form-simple__tax-grid">
				<div class="wcb-form-field">
					<label class="wcb-form-label" for="wcb-simple-category"><?php esc_html_e( 'Category', 'wp-career-board' ); ?></label>
					<select id="wcb-simple-category" class="wcb-field" data-wcb-field="categorySlug" data-wp-bind--value="state.categorySlug" data-wp-on--change="actions.updateField">
						<option value=""><?php esc_html_e( 'Select a category', 'wp-career-board' ); ?></option>
						<?php foreach ( $wcb_categories as $wcb_t ) : ?>
							<option value="<?php echo esc_attr( $wcb_t->slug ); ?>"><?php echo esc_html( $wcb_t->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="wcb-form-field">
					<label class="wcb-form-label" for="wcb-simple-type"><?php esc_html_e( 'Job Type', 'wp-career-board' ); ?></label>
					<select id="wcb-simple-type" class="wcb-field" data-wcb-field="typeSlug" data-wp-bind--value="state.typeSlug" data-wp-on--change="actions.updateField">
						<option value=""><?php esc_html_e( 'Select a job type', 'wp-career-board' ); ?></option>
						<?php foreach ( $wcb_job_types as $wcb_t ) : ?>
							<option value="<?php echo esc_attr( $wcb_t->slug ); ?>"><?php echo esc_html( $wcb_t->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="wcb-form-field">
					<label class="wcb-form-label" for="wcb-simple-location"><?php esc_html_e( 'Location', 'wp-career-board' ); ?></label>
					<select id="wcb-simple-location" class="wcb-field" data-wcb-field="locationSlug" data-wp-bind--value="state.locationSlug" data-wp-on--change="actions.updateField">
						<option value=""><?php esc_html_e( 'Select a location', 'wp-career-board' ); ?></option>
						<?php foreach ( $wcb_locations as $wcb_t ) : ?>
							<option value="<?php echo esc_attr( $wcb_t->slug ); ?>"><?php echo esc_html( $wcb_t->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="wcb-form-field">
					<label class="wcb-form-label" for="wcb-simple-exp"><?php esc_html_e( 'Experience', 'wp-career-board' ); ?></label>
					<select id="wcb-simple-exp" class="wcb-field" data-wcb-field="expSlug" data-wp-bind--value="state.expSlug" data-wp-on--change="actions.updateField">
						<option value=""><?php esc_html_e( 'Select experience level', 'wp-career-board' ); ?></option>
						<?php foreach ( $wcb_experiences as $wcb_t ) : ?>
							<option value="<?php echo esc_attr( $wcb_t->slug ); ?>"><?php echo esc_html( $wcb_t->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-simple-tags"><?php esc_html_e( 'Skills / Tags', 'wp-career-board' ); ?></label>
				<input
					id="wcb-simple-tags"
					type="text"
					class="wcb-field"
					placeholder="<?php esc_attr_e( 'e.g. React, TypeScript, Node.js', 'wp-career-board' ); ?>"
					data-wcb-field="tags"
					data-wp-bind--value="state.tags"
					data-wp-on--input="actions.updateField"
				/>
				<span class="wcb-form-hint"><?php esc_html_e( 'Comma-separated.', 'wp-career-board' ); ?></span>
			</div>
		</section>

		<!-- ── Section 3: Compensation & Schedule ──────────────────── -->
		<section class="wcb-form-simple__section">
			<p class="wcb-form-simple__eyebrow"><?php esc_html_e( 'Compensation & schedule', 'wp-career-board' ); ?></p>

			<div class="wcb-form-field">
				<span class="wcb-form-label"><?php esc_html_e( 'Salary Range', 'wp-career-board' ); ?></span>
				<div class="wcb-salary-row">
					<select class="wcb-field" data-wcb-field="currencyCode" data-wp-bind--value="state.currencyCode" data-wp-on--change="actions.updateField" aria-label="<?php esc_attr_e( 'Currency', 'wp-career-board' ); ?>">
						<?php foreach ( $wcb_currencies as $wcb_code => $wcb_label ) : ?>
							<option value="<?php echo esc_attr( $wcb_code ); ?>"><?php echo esc_html( $wcb_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="number" class="wcb-field" placeholder="<?php esc_attr_e( 'Min', 'wp-career-board' ); ?>" min="0" data-wcb-field="salaryMin" data-wp-bind--value="state.salaryMin" data-wp-on--input="actions.updateField" aria-label="<?php esc_attr_e( 'Minimum salary', 'wp-career-board' ); ?>" />
					<input type="number" class="wcb-field" placeholder="<?php esc_attr_e( 'Max', 'wp-career-board' ); ?>" min="0" data-wcb-field="salaryMax" data-wp-bind--value="state.salaryMax" data-wp-on--input="actions.updateField" aria-label="<?php esc_attr_e( 'Maximum salary', 'wp-career-board' ); ?>" />
					<select class="wcb-field" data-wcb-field="salaryType" data-wp-bind--value="state.salaryType" data-wp-on--change="actions.updateField" aria-label="<?php esc_attr_e( 'Period', 'wp-career-board' ); ?>">
						<option value="yearly"><?php esc_html_e( 'Year', 'wp-career-board' ); ?></option>
						<option value="monthly"><?php esc_html_e( 'Month', 'wp-career-board' ); ?></option>
						<option value="hourly"><?php esc_html_e( 'Hour', 'wp-career-board' ); ?></option>
					</select>
				</div>
				<span class="wcb-form-hint"><?php esc_html_e( 'Leave blank to hide salary from candidates.', 'wp-career-board' ); ?></span>
			</div>

			<div class="wcb-form-grid wcb-form-simple__remote-deadline">
				<div class="wcb-form-field wcb-form-field--remote">
					<label class="wcb-checkbox-label">
						<input type="checkbox" data-wp-bind--checked="state.remote" data-wp-on--change="actions.toggleRemote" />
						<span><?php esc_html_e( 'Remote-friendly position', 'wp-career-board' ); ?></span>
					</label>
				</div>
				<div class="wcb-form-field">
					<label class="wcb-form-label" for="wcb-simple-deadline"><?php esc_html_e( 'Application Deadline', 'wp-career-board' ); ?></label>
					<input id="wcb-simple-deadline" type="date" class="wcb-field" data-wcb-field="deadline" data-wp-bind--value="state.deadline" data-wp-on--input="actions.updateField" />
				</div>
			</div>
		</section>

		<!-- ── Section 4: How candidates apply ─────────────────────── -->
		<section class="wcb-form-simple__section">
			<p class="wcb-form-simple__eyebrow"><?php esc_html_e( 'How candidates apply', 'wp-career-board' ); ?></p>

			<div class="wcb-form-grid">
				<div class="wcb-form-field">
					<label class="wcb-form-label" for="wcb-simple-apply-url"><?php esc_html_e( 'Apply URL', 'wp-career-board' ); ?></label>
					<input id="wcb-simple-apply-url" type="url" class="wcb-field" placeholder="https://yourcompany.com/careers/apply" data-wcb-field="applyUrl" data-wp-bind--value="state.applyUrl" data-wp-on--input="actions.updateField" />
				</div>
				<div class="wcb-form-field">
					<label class="wcb-form-label" for="wcb-simple-apply-email"><?php esc_html_e( 'Apply Email', 'wp-career-board' ); ?></label>
					<input id="wcb-simple-apply-email" type="email" class="wcb-field" placeholder="jobs@yourcompany.com" data-wcb-field="applyEmail" data-wp-bind--value="state.applyEmail" data-wp-on--input="actions.updateField" />
				</div>
			</div>

			<?php
			/**
			 * Action: render extra fields inside the single-page form, after the
			 * default field set and before the submit button.
			 *
			 * Mirrors wcb_job_form_step*_fields on the wizard so integrators
			 * that target the wizard via those actions can opt into the
			 * simple form too.
			 *
			 * @since 1.1.0
			 *
			 * @param array $attributes Block attributes.
			 */
			do_action( 'wcb_job_form_simple_extra_fields', $attributes );
			?>
		</section>

		<!-- Submit -->
		<div class="wcb-form-simple__nav">
			<button
				type="button"
				class="wcb-btn wcb-btn--primary"
				data-wp-on--click="actions.submitJob"
				data-wp-bind--disabled="state.submitting"
			>
				<span data-wp-class--wcb-hidden="state.submitting"><?php esc_html_e( 'Post Job', 'wp-career-board' ); ?></span>
				<span data-wp-class--wcb-hidden="!state.submitting"><?php esc_html_e( 'Posting…', 'wp-career-board' ); ?></span>
			</button>
		</div>
	</div>
</div>
