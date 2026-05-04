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
 *   Filter: wcb_job_form_fields( array $groups, int $board_id ) — custom field group definitions
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

defined('ABSPATH') || exit;

$wcb_can_post_job = function_exists('wp_is_ability_granted')
    ? wp_is_ability_granted('wcb_post_jobs')
    : current_user_can('wcb_post_jobs');

if (! is_user_logged_in() || ! $wcb_can_post_job ) {
    echo '<p>' . esc_html__('You must be logged in as an employer to post a job.', 'wp-career-board') . '</p>';
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

$wcb_categories  = is_wp_error($wcb_categories_raw) ? array() : $wcb_categories_raw;
$wcb_job_types   = is_wp_error($wcb_job_types_raw) ? array() : $wcb_job_types_raw;
$wcb_locations   = is_wp_error($wcb_locations_raw) ? array() : $wcb_locations_raw;
$wcb_experiences = is_wp_error($wcb_experiences_raw) ? array() : $wcb_experiences_raw;

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
$wcb_company_id   = (int) get_user_meta($wcb_user_id, '_wcb_company_id', true);
$wcb_company_post = $wcb_company_id ? get_post($wcb_company_id) : null;
$wcb_company_name = ( $wcb_company_post instanceof \WP_Post ) ? $wcb_company_post->post_title : '';

// ── Edit mode — pre-populate from existing job ──────────────────────────────
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only param, no state mutation.
$wcb_edit_id  = absint(wp_unslash($_GET['edit'] ?? '0'));
$wcb_edit_job = null;
$wcb_e_cats   = array();
$wcb_e_types  = array();
$wcb_e_locs   = array();
$wcb_e_exps   = array();
$wcb_e_tags   = array();

if ($wcb_edit_id > 0 ) {
    $wcb_edit_job = get_post($wcb_edit_id);
    $wcb_can_edit = $wcb_edit_job instanceof \WP_Post
    && 'wcb_job' === $wcb_edit_job->post_type
    && ( (int) $wcb_edit_job->post_author === $wcb_user_id
    || ( function_exists('wp_is_ability_granted') && wp_is_ability_granted('wcb_manage_settings') ) );

    if (! $wcb_can_edit ) {
        echo '<p>' . esc_html__('You are not authorized to edit this job.', 'wp-career-board') . '</p>';
        return;
    }

    $wcb_e_cats  = wp_get_object_terms($wcb_edit_id, 'wcb_category', array( 'fields' => 'slugs' ));
    $wcb_e_types = wp_get_object_terms($wcb_edit_id, 'wcb_job_type', array( 'fields' => 'slugs' ));
    $wcb_e_locs  = wp_get_object_terms($wcb_edit_id, 'wcb_location', array( 'fields' => 'slugs' ));
    $wcb_e_exps  = wp_get_object_terms($wcb_edit_id, 'wcb_experience', array( 'fields' => 'slugs' ));
    $wcb_e_tags  = wp_get_object_terms($wcb_edit_id, 'wcb_tag', array( 'fields' => 'slugs' ));
}

// ── Default currency: employer preference → site admin setting → USD ──────────
$wcb_preferred = (string) get_user_meta($wcb_user_id, '_wcb_preferred_currency', true);
if (! $wcb_preferred ) {
    $wcb_site_settings = (array) get_option('wcb_settings', array());
    $wcb_preferred     = ! empty($wcb_site_settings['salary_currency']) ? $wcb_site_settings['salary_currency'] : 'USD';
}
$wcb_default_currency = in_array($wcb_preferred, array( 'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'INR', 'SGD' ), true) ? $wcb_preferred : 'USD';

// ── Currency options ───────────────────────────────────────────────────────
$wcb_currencies = array(
    'USD' => __('USD — US Dollar', 'wp-career-board'),
    'EUR' => __('EUR — Euro', 'wp-career-board'),
    'GBP' => __('GBP — British Pound', 'wp-career-board'),
    'CAD' => __('CAD — Canadian Dollar', 'wp-career-board'),
    'AUD' => __('AUD — Australian Dollar', 'wp-career-board'),
    'INR' => __('INR — Indian Rupee', 'wp-career-board'),
    'SGD' => __('SGD — Singapore Dollar', 'wp-career-board'),
);

// Extend currency list — Pro hooks this filter to add JPY/BRL/MXN/etc.
$wcb_currencies = (array) apply_filters('wcb_currency_options', $wcb_currencies);

// ── Board currency — Pro returns the per-board currency when boardId is set ──
$wcb_board_id       = isset($attributes['boardId']) ? (int) $attributes['boardId'] : 0;
$wcb_board_currency = $wcb_board_id > 0
    ? (string) apply_filters('wcb_board_currency', '', $wcb_board_id)
    : '';

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
        'editJobId'         => $wcb_edit_id,
        'step'              => 1,
        'title'             => $wcb_edit_job ? $wcb_edit_job->post_title : '',
        'description'       => $wcb_edit_job ? $wcb_edit_job->post_content : '',
        'salaryMin'         => $wcb_edit_job ? (string) get_post_meta($wcb_edit_id, '_wcb_salary_min', true) : '',
        'salaryMax'         => $wcb_edit_job ? (string) get_post_meta($wcb_edit_id, '_wcb_salary_max', true) : '',
        'currencyCode'      => $wcb_edit_job
            ? ( get_post_meta($wcb_edit_id, '_wcb_salary_currency', true) ? get_post_meta($wcb_edit_id, '_wcb_salary_currency', true) : $wcb_default_currency )
            : ( $wcb_board_currency ? $wcb_board_currency : $wcb_default_currency ),
        'salaryType'        => $wcb_edit_job ? ( get_post_meta($wcb_edit_id, '_wcb_salary_type', true) ? get_post_meta($wcb_edit_id, '_wcb_salary_type', true) : 'yearly' ) : 'yearly',
        'remote'            => $wcb_edit_job && '1' === (string) get_post_meta($wcb_edit_id, '_wcb_remote', true),
        'deadline'          => $wcb_edit_job ? (string) get_post_meta($wcb_edit_id, '_wcb_deadline', true) : '',
        'applyUrl'          => $wcb_edit_job ? (string) get_post_meta($wcb_edit_id, '_wcb_apply_url', true) : '',
        'applyEmail'        => $wcb_edit_job ? (string) get_post_meta($wcb_edit_id, '_wcb_apply_email', true) : '',
        'locationSlug'      => ! is_wp_error($wcb_e_locs) && $wcb_e_locs ? $wcb_e_locs[0] : '',
        'typeSlug'          => ! is_wp_error($wcb_e_types) && $wcb_e_types ? $wcb_e_types[0] : '',
        'categorySlug'      => ! is_wp_error($wcb_e_cats) && $wcb_e_cats ? $wcb_e_cats[0] : '',
        'expSlug'           => ! is_wp_error($wcb_e_exps) && $wcb_e_exps ? $wcb_e_exps[0] : '',
        'tags'              => ! is_wp_error($wcb_e_tags) ? implode(', ', $wcb_e_tags) : '',
        'companyName'       => $wcb_company_name,
        'submitting'        => false,
        'submitted'         => false,
        'jobUrl'            => '',
        'error'             => '',
        'validationError'   => '',
        'apiBase'           => untrailingslashit( rest_url( 'wcb/v1' ) ),
        'nonce'             => wp_create_nonce('wp_rest'),
        'creditCost'        => (int) apply_filters('wcb_board_credit_cost', 0, $wcb_board_id),
        'creditBalance'     => (int) apply_filters('wcb_employer_credit_balance', 0, get_current_user_id()),
        'creditPurchaseUrl' => (string) apply_filters('wcb_credit_purchase_url', ''),
        'customFieldGroups' => apply_filters('wcb_job_form_fields', array(), (int) ( $attributes['boardId'] ?? 0 )),
        'customFields'      => (object) array(),
        'typeNames'         => (object) $wcb_type_names,
        'expNames'          => (object) $wcb_exp_names,
        'locationNames'     => (object) $wcb_location_names,
        'categoryNames'     => (object) $wcb_category_names,
        'strings'           => array(
            'errorSessionExpired' => __('Your session has expired. Please refresh the page and try again.', 'wp-career-board'),
            'errorConnection'     => __('Connection error. Please check your network and try again.', 'wp-career-board'),
        ),
    ),
    $attributes
);

wp_interactivity_state('wcb-job-form', $wcb_initial_state);

// ── Step labels ────────────────────────────────────────────────────────────
$wcb_step_labels = array(
    1 => __('Basics', 'wp-career-board'),
    2 => __('Details', 'wp-career-board'),
    3 => __('Categories', 'wp-career-board'),
    4 => __('Preview', 'wp-career-board'),
);
?>
<div
    <?php echo get_block_wrapper_attributes(array( 'class' => 'wcb-job-form-wrap' )); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    data-wp-interactive="wcb-job-form"
>

    <!-- ── Step indicator ────────────────────────────────────────────────── -->
    <nav class="wcb-steps" aria-label="<?php esc_attr_e('Form progress', 'wp-career-board'); ?>">
        <?php foreach ( $wcb_step_labels as $wcb_step_num => $wcb_step_label ) : ?>
            <span
                class="wcb-step<?php echo 1 === $wcb_step_num ? ' wcb-step--active' : ''; ?>"
                data-wp-class--wcb-step--active="state.step<?php echo esc_attr((string) $wcb_step_num); ?>Active"
            <?php if ($wcb_step_num < 4 ) : ?>
                data-wp-class--wcb-step--done="state.step<?php echo esc_attr((string) $wcb_step_num); ?>Done"
            <?php endif; ?>
            <?php if (1 === $wcb_step_num ) : ?>
                aria-current="step"
            <?php endif; ?>
                data-wp-bind--aria-current="state.step<?php echo esc_attr((string) $wcb_step_num); ?>AriaCurrent"
            >
                <span class="wcb-step__num"><?php echo esc_html((string) $wcb_step_num); ?></span>
                <span class="wcb-step__label"><?php echo esc_html($wcb_step_label); ?></span>
            </span>
            <?php if ($wcb_step_num < 4 ) : ?>
                <span class="wcb-step__connector" aria-hidden="true"></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <!-- ── Validation error banner ───────────────────────────────────────── -->
    <p
        id="wcb-form-validation-error"
        class="wcb-form-error"
        role="alert"
        data-wp-class--wcb-form-error--show="state.hasValidation"
        data-wp-text="state.validationError"
    ></p>

    <!-- ── Credit info banner ────────────────────────────────────────────── -->
    <div
        class="wcb-credit-banner"
        data-wp-class--wcb-credit-banner--show="state.hasCreditCost"
        data-wp-class--wcb-credit-banner--warn="state.insufficientCredits"
    >
        <span class="wcb-credit-banner__text" data-wp-text="state.creditMessage"></span>
        <a
            class="wcb-credit-banner__link"
            data-wp-bind--href="state.creditPurchaseUrl"
            data-wp-class--wcb-hidden="!state.insufficientCredits"
        ><?php esc_html_e('Buy Credits →', 'wp-career-board'); ?></a>
    </div>

    <!-- ── Submission success ────────────────────────────────────────────── -->
    <div class="wcb-form-success" data-wp-class--wcb-form-success--show="state.submitted">
        <span class="wcb-form-success__icon" aria-hidden="true">✓</span>
        <div>
            <p class="wcb-form-success__title" data-wp-class--wcb-form-success__title--pending="state.jobPending">
                <span data-wp-class--wcb-hidden="state.jobPending">
                    <?php esc_html_e('Job posted successfully!', 'wp-career-board'); ?>
                </span>
                <span data-wp-class--wcb-hidden="!state.jobPending">
                    <?php esc_html_e('Job submitted for review. You\'ll be notified once it\'s approved.', 'wp-career-board'); ?>
                </span>
            </p>
            <a class="wcb-form-success__link" data-wp-bind--href="state.jobUrl" data-wp-class--wcb-hidden="state.jobPending">
                <?php esc_html_e('View your job listing →', 'wp-career-board'); ?>
            </a>
            <button type="button" class="wcb-form-success__reset" data-wp-on--click="actions.resetForm">
                <?php esc_html_e('Post another job', 'wp-career-board'); ?>
            </button>
        </div>
    </div>

    <!-- ── Form steps ────────────────────────────────────────────────────── -->
    <div class="wcb-form-steps" data-wp-class--wcb-form-steps--hidden="state.submitted">

        <!-- ── Step 1: Basics ──────────────────────────────────────────── -->
        <div class="wcb-form-step wcb-form-step--show" data-wp-class--wcb-form-step--show="state.isStep1">
            <h2 class="wcb-form-step__title">
                <?php esc_html_e('Job Basics', 'wp-career-board'); ?>
            </h2>

            <div class="wcb-form-field">
                <label class="wcb-form-label" for="wcb-job-title">
                    <?php esc_html_e('Job Title', 'wp-career-board'); ?>
                    <span class="wcb-required" aria-hidden="true">*</span>
                </label>
                <input
                    id="wcb-job-title"
                    type="text"
                    class="wcb-field"
                    placeholder="<?php esc_attr_e('e.g. Senior PHP Developer', 'wp-career-board'); ?>"
                    data-wcb-field="title"
                    data-wp-bind--value="state.title"
                    data-wp-on--input="actions.updateField"
                    required
                    aria-required="true"
                    aria-describedby="wcb-form-validation-error"
                    autocomplete="off"
                />
            </div>

            <div class="wcb-form-field">
                <div class="wcb-form-label-row">
                    <label class="wcb-form-label" for="wcb-job-desc">
                        <?php esc_html_e('Job Description', 'wp-career-board'); ?>
                        <span class="wcb-required" aria-hidden="true">*</span>
                    </label>
                    <?php if (apply_filters('wcb_ai_description_enabled', false) ) : ?>
                    <button type="button" class="wcb-ai-btn"
                        data-wp-on--click="actions.generateDescription"
                        data-wp-bind--disabled="state.aiGenerating"
                    >
                        <span data-wp-class--wcb-hidden="state.aiGenerating">&#10024; <?php esc_html_e('Generate with AI', 'wp-career-board'); ?></span>
                        <span data-wp-class--wcb-hidden="!state.aiGenerating"><?php esc_html_e('Generating…', 'wp-career-board'); ?></span>
                    </button>
                    <?php endif; ?>
                </div>
                <textarea id="wcb-job-desc" class="wcb-field" rows="12" aria-label="<?php esc_attr_e('Job description', 'wp-career-board'); ?>" placeholder="<?php esc_attr_e('Describe the role, responsibilities and requirements…', 'wp-career-board'); ?>" data-wcb-field="description" data-wp-bind--value="state.description" data-wp-on--input="actions.updateField" required aria-required="true"></textarea>
                <span class="wcb-form-hint">
                    <?php esc_html_e('Plain text or basic Markdown supported.', 'wp-career-board'); ?>
                </span>
            </div>

            <?php
            /**
             * Action: add custom fields after the default step 1 fields.
             *
             * @since 1.0.0
             * @param array $attributes Block attributes.
             */
            do_action('wcb_job_form_step1_fields', $attributes);
            ?>

            <div class="wcb-form-nav wcb-form-nav--right">
                <button type="button" class="wcb-btn wcb-btn--primary" data-wp-on--click="actions.nextStep">
                    <?php esc_html_e('Next: Details', 'wp-career-board'); ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>
            </div>
        </div>

        <!-- ── Step 2: Details ─────────────────────────────────────────── -->
        <div class="wcb-form-step" data-wp-class--wcb-form-step--show="state.isStep2">
            <h2 class="wcb-form-step__title">
                <?php esc_html_e('Job Details', 'wp-career-board'); ?>
            </h2>

            <!-- Salary range -->
            <div class="wcb-form-field">
                <span class="wcb-form-label">
                    <?php esc_html_e('Salary Range', 'wp-career-board'); ?>
                </span>
                <div class="wcb-salary-row">
                    <div class="wcb-field-group">
                        <label class="wcb-field-group__label" for="wcb-currency">
                            <?php esc_html_e('Currency', 'wp-career-board'); ?>
                        </label>
                        <select
                            id="wcb-currency"
                            class="wcb-field"
                            data-wcb-field="currencyCode"
                            data-wp-bind--value="state.currencyCode"
                            data-wp-on--change="actions.updateField"
                        >
                            <?php foreach ( $wcb_currencies as $wcb_code => $wcb_label ) : ?>
                                <option value="<?php echo esc_attr($wcb_code); ?>">
                                <?php echo esc_html($wcb_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="wcb-field-group">
                        <label class="wcb-field-group__label" for="wcb-salary-min">
                            <?php esc_html_e('Min', 'wp-career-board'); ?>
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
                            <?php esc_html_e('Max', 'wp-career-board'); ?>
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
                    <div class="wcb-field-group">
                        <label class="wcb-field-group__label" for="wcb-salary-type">
                            <?php esc_html_e('Per', 'wp-career-board'); ?>
                        </label>
                        <select
                            id="wcb-salary-type"
                            class="wcb-field"
                            data-wcb-field="salaryType"
                            data-wp-bind--value="state.salaryType"
                            data-wp-on--change="actions.updateField"
                        >
                            <option value="yearly"><?php esc_html_e('Year', 'wp-career-board'); ?></option>
                            <option value="monthly"><?php esc_html_e('Month', 'wp-career-board'); ?></option>
                            <option value="hourly"><?php esc_html_e('Hour', 'wp-career-board'); ?></option>
                        </select>
                    </div>
                </div>
                <span class="wcb-form-hint">
                    <?php esc_html_e('Leave blank to hide salary from candidates.', 'wp-career-board'); ?>
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
                    <span><?php esc_html_e('Remote-friendly position', 'wp-career-board'); ?></span>
                </label>
            </div>

            <!-- Deadline -->
            <div class="wcb-form-field">
                <label class="wcb-form-label" for="wcb-deadline">
                    <?php esc_html_e('Application Deadline', 'wp-career-board'); ?>
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
                    <?php esc_html_e('Apply URL', 'wp-career-board'); ?>
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
                    <?php esc_html_e('Link to your external ATS or application form.', 'wp-career-board'); ?>
                </span>
            </div>

            <!-- Apply Email -->
            <div class="wcb-form-field">
                <label class="wcb-form-label" for="wcb-apply-email">
                    <?php esc_html_e('Apply Email', 'wp-career-board'); ?>
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
                    <?php esc_html_e('Candidates can apply directly by email.', 'wp-career-board'); ?>
                </span>
            </div>

            <?php
            /**
             * Action: add custom fields after the default step 2 fields.
             *
             * @since 1.0.0
             * @param array $attributes Block attributes.
             */
            do_action('wcb_job_form_step2_fields', $attributes);
            ?>

            <div class="wcb-form-nav">
                <button type="button" class="wcb-btn wcb-btn--ghost" data-wp-on--click="actions.prevStep">
                    <?php esc_html_e('← Back', 'wp-career-board'); ?>
                </button>
                <button type="button" class="wcb-btn wcb-btn--primary" data-wp-on--click="actions.nextStep">
                    <?php esc_html_e('Next: Categories', 'wp-career-board'); ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>
            </div>
        </div>

        <!-- ── Step 3: Categories ──────────────────────────────────────── -->
        <div class="wcb-form-step" data-wp-class--wcb-form-step--show="state.isStep3">
            <h2 class="wcb-form-step__title">
                <?php esc_html_e('Classify Your Job', 'wp-career-board'); ?>
            </h2>

            <div class="wcb-form-grid">
                <div class="wcb-form-field">
                    <label class="wcb-form-label" for="wcb-category">
                        <?php esc_html_e('Category', 'wp-career-board'); ?>
                    </label>
                    <select
                        id="wcb-category"
                        class="wcb-field"
                        data-wcb-field="categorySlug"
                        data-wp-bind--value="state.categorySlug"
                        data-wp-on--change="actions.updateField"
                    >
                        <option value=""><?php esc_html_e('Select a category', 'wp-career-board'); ?></option>
                        <?php foreach ( $wcb_categories as $wcb_term ) : ?>
                            <option value="<?php echo esc_attr($wcb_term->slug); ?>">
                            <?php echo esc_html($wcb_term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="wcb-form-field">
                    <label class="wcb-form-label" for="wcb-job-type">
                        <?php esc_html_e('Job Type', 'wp-career-board'); ?>
                    </label>
                    <select
                        id="wcb-job-type"
                        class="wcb-field"
                        data-wcb-field="typeSlug"
                        data-wp-bind--value="state.typeSlug"
                        data-wp-on--change="actions.updateField"
                    >
                        <option value=""><?php esc_html_e('Select a job type', 'wp-career-board'); ?></option>
                        <?php foreach ( $wcb_job_types as $wcb_term ) : ?>
                            <option value="<?php echo esc_attr($wcb_term->slug); ?>">
                            <?php echo esc_html($wcb_term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="wcb-form-field">
                    <label class="wcb-form-label" for="wcb-location">
                        <?php esc_html_e('Location', 'wp-career-board'); ?>
                    </label>
                    <select
                        id="wcb-location"
                        class="wcb-field"
                        data-wcb-field="locationSlug"
                        data-wp-bind--value="state.locationSlug"
                        data-wp-on--change="actions.updateField"
                    >
                        <option value=""><?php esc_html_e('Select a location', 'wp-career-board'); ?></option>
                        <?php foreach ( $wcb_locations as $wcb_term ) : ?>
                            <option value="<?php echo esc_attr($wcb_term->slug); ?>">
                            <?php echo esc_html($wcb_term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="wcb-form-field">
                    <label class="wcb-form-label" for="wcb-experience">
                        <?php esc_html_e('Experience Level', 'wp-career-board'); ?>
                    </label>
                    <select
                        id="wcb-experience"
                        class="wcb-field"
                        data-wcb-field="expSlug"
                        data-wp-bind--value="state.expSlug"
                        data-wp-on--change="actions.updateField"
                    >
                        <option value=""><?php esc_html_e('Select experience level', 'wp-career-board'); ?></option>
                        <?php foreach ( $wcb_experiences as $wcb_term ) : ?>
                            <option value="<?php echo esc_attr($wcb_term->slug); ?>">
                            <?php echo esc_html($wcb_term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Tags -->
            <div class="wcb-form-field">
                <label class="wcb-form-label" for="wcb-tags">
                    <?php esc_html_e('Skills / Tags', 'wp-career-board'); ?>
                </label>
                <input
                    id="wcb-tags"
                    type="text"
                    class="wcb-field"
                    placeholder="<?php esc_attr_e('e.g. React, TypeScript, Node.js', 'wp-career-board'); ?>"
                    data-wcb-field="tags"
                    data-wp-bind--value="state.tags"
                    data-wp-on--input="actions.updateField"
                />
                <span class="wcb-form-hint">
                    <?php esc_html_e('Comma-separated. Helps candidates find your job via keyword search.', 'wp-career-board'); ?>
                </span>
            </div>

            <?php
            /**
             * Action: add custom fields after the default step 3 fields.
             *
             * @since 1.0.0
             * @param array $attributes Block attributes.
             */
            do_action('wcb_job_form_step3_fields', $attributes);
            ?>

            <div class="wcb-form-nav">
                <button type="button" class="wcb-btn wcb-btn--ghost" data-wp-on--click="actions.prevStep">
                    <?php esc_html_e('← Back', 'wp-career-board'); ?>
                </button>
                <button type="button" class="wcb-btn wcb-btn--primary" data-wp-on--click="actions.nextStep">
                    <?php esc_html_e('Preview Job', 'wp-career-board'); ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>
            </div>
        </div>

        <!-- ── Step 4: Preview & Submit ────────────────────────────────── -->
        <div class="wcb-form-step" data-wp-class--wcb-form-step--show="state.isStep4">
            <h2 class="wcb-form-step__title">
                <?php esc_html_e('Preview & Submit', 'wp-career-board'); ?>
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
                    ><?php esc_html_e('Remote', 'wp-career-board'); ?></span>
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
                        <?php esc_html_e('Apply by', 'wp-career-board'); ?>
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
                do_action('wcb_job_form_step4_preview', $attributes);
                ?>
            </div>

            <p class="wcb-form-notice">
                <?php esc_html_e('Review the details above. Go back to make changes before submitting.', 'wp-career-board'); ?>
            </p>

            <p class="wcb-form-error" role="alert" data-wp-class--wcb-form-error--show="state.hasError" data-wp-text="state.error"></p>

            <div class="wcb-form-nav">
                <button
                    type="button"
                    class="wcb-btn wcb-btn--ghost"
                    data-wp-on--click="actions.prevStep"
                    data-wp-bind--disabled="state.submitting"
                >
                    <?php esc_html_e('← Back', 'wp-career-board'); ?>
                </button>
                <button
                    type="button"
                    class="wcb-btn wcb-btn--primary"
                    data-wp-on--click="actions.submitJob"
                    data-wp-bind--disabled="state.submitting"
                    data-wp-class--wcb-is-submitting="state.submitting"
                >
                    <span class="wcb-btn__label" data-wp-text="state.submitLabel"><?php echo esc_html($wcb_edit_id > 0 ? __('Update Job', 'wp-career-board') : __('Post Job', 'wp-career-board')); ?></span>
                    <span class="wcb-btn__spinner">
                        <svg class="wcb-spinner" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                        <?php esc_html_e('Posting…', 'wp-career-board'); ?>
                    </span>
                </button>
            </div>
        </div>

    <!-- ── Honeypot anti-spam ────────────────────────────────────────────── -->
    <div class="wcb-hp-wrap" aria-hidden="true">
        <label for="wcb-hp"><?php esc_html_e('Leave this field blank', 'wp-career-board'); ?></label>
        <input
            type="text"
            id="wcb-hp"
            name="wcb_hp"
            class="wcb-hp"
            tabindex="-1"
            autocomplete="off"
        />
    </div>

    </div><!-- /.wcb-form-steps -->
</div>
