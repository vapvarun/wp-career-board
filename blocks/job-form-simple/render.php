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

$wcb_can_post_job = wp_is_ability_granted( 'wcb/post-jobs' );

if ( ! is_user_logged_in() || ! $wcb_can_post_job ) {
	echo '<p class="wcb-form-simple__gate">' . esc_html__( 'You must be logged in as an employer to post a job.', 'wp-career-board' ) . '</p>';
	return;
}

$wcb_board_id_attr     = isset( $attributes['boardId'] ) ? (int) $attributes['boardId'] : 0;
$wcb_show_company_attr = ! isset( $attributes['showCompanyField'] ) || (bool) $attributes['showCompanyField'];
$wcb_compact_attr      = ! empty( $attributes['compact'] );

// ── Board picker options — mirrors blocks/job-form/render.php so multi-board
// sites (Pro) get a dropdown and the employer can target the post at a
// specific board. Single-board sites skip the picker entirely; the REST
// callback falls back to the default board when state.boardId stays 0.
$wcb_board_options      = array();
$wcb_board_credit_costs = array();
$wcb_board_currencies   = array();
if ( post_type_exists( 'wcb_board' ) ) {
	$wcb_board_posts = get_posts(
		array(
			'post_type'      => 'wcb_board',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'no_found_rows'  => true,
		)
	);
	foreach ( $wcb_board_posts as $wcb_b ) {
		$wcb_board_options[] = array(
			'id'    => (int) $wcb_b->ID,
			'title' => $wcb_b->post_title,
		);
		// Per-board credit cost + currency maps seeded at render so view.js
		// can update state.creditCost and state.currencyCode reactively when
		// the employer switches boards. Pro fulfils both overrides via the
		// wcb_board_credit_cost and wcb_board_currency filters.
		$wcb_board_credit_costs[ (int) $wcb_b->ID ] = (int) apply_filters( 'wcb_board_credit_cost', 0, (int) $wcb_b->ID );
		$wcb_board_currencies[ (int) $wcb_b->ID ]   = (string) apply_filters( 'wcb_board_currency', '', (int) $wcb_b->ID );
	}

	/**
	 * Filter the Boards dropdown shown to the current employer in the single-page
	 * job form. Same filter that the multi-step wizard uses — Pro's BP-group
	 * integration scopes the list to boards whose linked group the employer is
	 * a member, mod, or admin of.
	 *
	 * @since 1.1.1
	 *
	 * @param array<int,array{id:int,title:string}> $wcb_board_options Default board list.
	 * @param int                                   $wcb_user_id       Current user id.
	 */
	$wcb_board_options = (array) apply_filters( 'wcb_board_options_for_employer', $wcb_board_options, get_current_user_id() );
}

// Resolve effective board id: explicit attribute → site-wide default option → first option.
$wcb_resolved_board_id = $wcb_board_id_attr;
if ( 0 === $wcb_resolved_board_id && ! empty( $wcb_board_options ) ) {
	$wcb_default_board_post = function_exists( 'get_option' ) ? (int) get_option( 'wcb_default_board_id', 0 ) : 0;
	$wcb_resolved_board_id  = $wcb_default_board_post > 0 ? $wcb_default_board_post : (int) $wcb_board_options[0]['id'];
}

// ── Taxonomy terms ─────────────────────────────────────────────────────────
$wcb_term_args = static function ( string $tax ): array {
	return array(
		'taxonomy'   => $tax,
		'hide_empty' => false,
	);
};

$wcb_categories = array_filter( (array) get_terms( $wcb_term_args( 'wcb_category' ) ), static fn( $t ) => $t instanceof \WP_Term );
$wcb_job_types  = array_filter( (array) get_terms( $wcb_term_args( 'wcb_job_type' ) ), static fn( $t ) => $t instanceof \WP_Term );
// Location dropdown is scoped to the employer's company HQ + reserved
// 'remote' / 'other' terms. Admins (wcb/manage-settings) still get every term.
$wcb_locations   = \WCB\Core\Locations::get_dropdown_terms( get_current_user_id() );
$wcb_experiences = array_filter( (array) get_terms( $wcb_term_args( 'wcb_experience' ) ), static fn( $t ) => $t instanceof \WP_Term );

// ── Currency: site-wide admin setting → USD. One source of truth across every
// employer; the dropdown still lets them override per job.
$wcb_user_id      = get_current_user_id();
$wcb_company_id   = (int) get_user_meta( $wcb_user_id, '_wcb_company_id', true );
$wcb_company_post = $wcb_company_id ? get_post( $wcb_company_id ) : null;
$wcb_company_name = ( $wcb_company_post instanceof \WP_Post ) ? $wcb_company_post->post_title : '';

$wcb_currency_catalog = \WCB\Admin\AdminSettings::get_currency_catalog();

$wcb_preferred        = strtoupper( \WCB\Admin\Settings::string( 'salary_currency', 'USD' ) );
$wcb_default_currency = array_key_exists( $wcb_preferred, $wcb_currency_catalog )
	? $wcb_preferred
	: ( array_key_exists( 'USD', $wcb_currency_catalog ) ? 'USD' : (string) array_key_first( $wcb_currency_catalog ) );

// Pro returns a per-board currency override when boardId is set — same wiring
// as the wizard so the form pre-fills the right currency when targeting a
// board that doesn't accept the site default (e.g. a JPY-only partner board).
$wcb_board_currency   = $wcb_resolved_board_id > 0
	? (string) apply_filters( 'wcb_board_currency', '', $wcb_resolved_board_id )
	: '';
$wcb_initial_currency = $wcb_board_currency && array_key_exists( $wcb_board_currency, $wcb_currency_catalog )
	? $wcb_board_currency
	: $wcb_default_currency;

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
		'title'                      => '',
		'description'                => '',
		'salaryMin'                  => '',
		'salaryMax'                  => '',
		'currencyCode'               => $wcb_initial_currency,
		'salaryType'                 => 'yearly',
		'remote'                     => false,
		// Auto-filled from the wcb_job_default_expiry_days filter chain (board
		// override if set, otherwise the global jobs_expire_days). Form input
		// is read-only; admins control the policy.
		'deadline'                   => ( static function () use ( $wcb_resolved_board_id ): string {
			$wcb_preview_request = new \WP_REST_Request( 'POST', '/wcb/v1/jobs' );
			$wcb_preview_request->set_param( 'board_id', $wcb_resolved_board_id );
			$wcb_default_days  = (int) \WCB\Admin\Settings::int( 'jobs_expire_days', 30 );
			$wcb_resolved_days = (int) apply_filters( 'wcb_job_default_expiry_days', $wcb_default_days, $wcb_preview_request );
			$wcb_resolved_days = $wcb_resolved_days > 0 ? $wcb_resolved_days : 30;
			return gmdate( 'Y-m-d', strtotime( '+' . $wcb_resolved_days . ' days' ) );
		} )(),
		'applyUrl'                   => '',
		'applyEmail'                 => '',
		'locationSlug'               => '',
		'locationCustom'             => '',
		'typeSlug'                   => '',
		'categorySlug'               => '',
		'expSlug'                    => '',
		'tags'                       => '',
		// Board picker state mirrors the wizard so future board-related changes
		// flow through both forms. showBoardPicker drives the dropdown visibility;
		// single-board sites suppress it and the REST callback falls back to the
		// default board id when boardId stays 0.
		'boardId'                    => $wcb_resolved_board_id,
		'boardOptions'               => $wcb_board_options,
		'showBoardPicker'            => count( $wcb_board_options ) > 1,
		'companyName'                => $wcb_company_name,
		'submitting'                 => false,
		'submitted'                  => false,
		'_aiGenerating'              => false,
		'jobUrl'                     => '',
		'error'                      => '',
		'apiBase'                    => untrailingslashit( rest_url( 'wcb/v1' ) ),
		'nonce'                      => wp_create_nonce( 'wp_rest' ),
		'creditCost'                 => (int) apply_filters( 'wcb_board_credit_cost', 0, $wcb_resolved_board_id ),
		// Per-board cost lookup so view.js can recompute creditCost when the
		// employer switches boards in the picker. Object keyed by board ID.
		'boardCreditCosts'           => array_map( 'intval', $wcb_board_credit_costs ),
		// Per-board currency override map so view.js can update currencyCode
		// on board switch. Empty string means no override - keep current.
		'boardCurrencies'            => array_map( 'strval', $wcb_board_currencies ),
		'creditBalance'              => (int) apply_filters( 'wcb_employer_credit_balance', 0, $wcb_user_id ),
		'creditPurchaseUrl'          => (string) apply_filters( 'wcb_credit_purchase_url', '' ),
		// Translated templates for state.creditMessage. JS interpolates with
		// live cost / balance — the strings live in the .pot file, not in view.js.
		/* translators: 1: required credits, 2: current balance. */
		'creditInsufficientTemplate' => __( 'This board requires %1$d credits. Your balance: %2$d. Please purchase more credits.', 'wp-career-board' ),
		/* translators: 1: pluralised credits ("1 credit" / "N credits"), 2: balance after deduction, 3: current balance. */
		'creditDeductionTemplate'    => __( 'Posting deducts %1$s. Balance after: %2$d (currently %3$d).', 'wp-career-board' ),
		/* translators: %d: current credit balance. Shown when the selected board has no credit cost. */
		'creditFreeTemplate'         => __( 'Free to post on this board. Your balance: %d.', 'wp-career-board' ),
		/* translators: %d: number of credits (singular). */
		'creditNounSingular'         => __( '%d credit', 'wp-career-board' ),
		/* translators: %d: number of credits (plural). */
		'creditNounPlural'           => __( '%d credits', 'wp-career-board' ),
		'customFieldGroups'          => apply_filters( 'wcb_job_form_fields', array(), $wcb_resolved_board_id ),
		'customFields'               => (object) array(),
		'strings'                    => array(
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
		<h2 class="wcb-icon-label"><?php echo \WCB\Core\Icon::svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?><?php esc_html_e( 'Job posted', 'wp-career-board' ); ?></h2>
		<p
			class="wcb-form-simple__meta"
			data-wp-class--wcb-hidden="!state.hasListingWindow"
			data-wp-text="state.listingWindowMessage"
		></p>
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

		<!-- Credit + listing window banners -->
		<p
			class="wcb-form-simple__credit"
			data-wp-class--wcb-shown="state.hasCreditBanner"
			data-wp-class--wcb-form-simple__credit--warn="state.hasInsufficientCredits"
			data-wp-text="state.creditMessage"
		></p>
		<p
			class="wcb-form-simple__listing-window"
			data-wp-class--wcb-shown="state.hasListingWindow"
			data-wp-text="state.listingWindowMessage"
		></p>

		<!-- ── Section 1: Basics ───────────────────────────────────── -->
		<section class="wcb-form-simple__section">
			<p class="wcb-form-simple__eyebrow"><?php esc_html_e( 'About the role', 'wp-career-board' ); ?></p>

			<?php if ( count( $wcb_board_options ) > 1 ) : ?>
			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-simple-board-id">
					<?php esc_html_e( 'Post to Board', 'wp-career-board' ); ?>
				</label>
				<select
					id="wcb-simple-board-id"
					class="wcb-field"
					data-wcb-field="boardId"
					data-wp-bind--value="state.boardId"
					data-wp-on--change="actions.updateField"
				>
					<?php foreach ( $wcb_board_options as $wcb_b ) : ?>
						<option value="<?php echo (int) $wcb_b['id']; ?>" <?php selected( $wcb_resolved_board_id, (int) $wcb_b['id'] ); ?>>
							<?php echo esc_html( $wcb_b['title'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>

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
				<div class="wcb-form-label-row">
					<label class="wcb-form-label" for="wcb-simple-desc">
						<?php esc_html_e( 'Job Description', 'wp-career-board' ); ?>
						<span class="wcb-required" aria-hidden="true">*</span>
					</label>
					<?php if ( apply_filters( 'wcb_ai_description_enabled', false ) ) : ?>
					<button type="button" class="wcb-ai-btn"
						data-wp-on--click="actions.generateDescription"
						data-wp-bind--disabled="state._aiGenerating"
					>
						<span data-wp-class--wcb-hidden="state._aiGenerating">&#10024; <?php esc_html_e( 'Generate with AI', 'wp-career-board' ); ?></span>
						<span data-wp-class--wcb-hidden="!state._aiGenerating"><?php esc_html_e( 'Generating…', 'wp-career-board' ); ?></span>
					</button>
					<?php endif; ?>
				</div>
				<div class="wcb-editor" data-placeholder="<?php esc_attr_e( 'Describe the role, responsibilities and requirements…', 'wp-career-board' ); ?>">
					<div class="wcb-editor-holder" id="wcb-editor-job-desc-simple"></div>
					<textarea
						id="wcb-simple-desc"
						class="wcb-editor-source"
						rows="1"
						tabindex="-1"
						aria-label="<?php esc_attr_e( 'Job description', 'wp-career-board' ); ?>"
						data-wcb-field="description"
						data-wp-bind--value="state.description"
						data-wp-on--input="actions.updateField"
						required
					></textarea>
				</div>
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
						<option value="__custom__"><?php esc_html_e( 'Other (enter manually)…', 'wp-career-board' ); ?></option>
					</select>
					<input
						type="text"
						id="wcb-simple-location-custom"
						class="wcb-field wcb-field--mt"
						data-wcb-field="locationCustom"
						data-wp-bind--value="state.locationCustom"
						data-wp-on--input="actions.updateField"
						data-wp-class--wcb-hidden="!state.locationIsCustom"
						aria-label="<?php esc_attr_e( 'Custom location', 'wp-career-board' ); ?>"
						placeholder="<?php esc_attr_e( 'e.g. Berlin, DE or Remote - Europe', 'wp-career-board' ); ?>"
						maxlength="120"
					/>
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
						<?php foreach ( $wcb_currency_catalog as $wcb_code => $wcb_meta ) : ?>
							<option value="<?php echo esc_attr( $wcb_code ); ?>">
								<?php
								printf(
									/* translators: 1: code (USD), 2: name (US Dollar), 3: symbol ($). */
									esc_html__( '%1$s  -  %2$s (%3$s)', 'wp-career-board' ),
									esc_html( (string) $wcb_code ),
									esc_html( (string) $wcb_meta['name'] ),
									esc_html( (string) $wcb_meta['symbol'] )
								);
								?>
							</option>
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

			<div class="wcb-form-field">
				<label class="wcb-checkbox-label">
					<input type="checkbox" data-wp-bind--checked="state.remote" data-wp-on--change="actions.toggleRemote" />
					<span><?php esc_html_e( 'Remote-friendly position', 'wp-career-board' ); ?></span>
				</label>
			</div>
			<div class="wcb-form-field wcb-form-field--deadline">
				<label class="wcb-form-label" for="wcb-simple-deadline"><?php esc_html_e( 'Application Deadline', 'wp-career-board' ); ?></label>
				<input id="wcb-simple-deadline" type="date" class="wcb-field" data-wp-bind--value="state.deadline" readonly aria-readonly="true" tabindex="-1" />
				<span class="wcb-form-hint"><?php esc_html_e( 'Auto-filled from the job-board policy. Contact your site admin to extend the listing window.', 'wp-career-board' ); ?></span>
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

		<?php
		// Render custom-field groups injected via wcb_job_form_fields (used by
		// Pro Field Builder and by add-ons). Always-rendered when groups are
		// non-empty; binds into state.customFields via the updateCustomField
		// action that already exists in view.js. Values persist via the Pro
		// Fields_Module which hooks wcb_job_created + wcb_job_updated.
		$wcb_simple_custom_groups = (array) apply_filters( 'wcb_job_form_fields', array(), $wcb_resolved_board_id );
		if ( ! empty( $wcb_simple_custom_groups ) ) :
			?>
			<section class="wcb-form-simple__section">
				<p class="wcb-form-simple__eyebrow"><?php esc_html_e( 'Additional details', 'wp-career-board' ); ?></p>
				<?php \WCB\Core\FormCustomFields::render_groups( $wcb_simple_custom_groups, 'updateCustomField', 'wcb-simple-custom' ); ?>
			</section>
			<?php
		endif;
		?>

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
