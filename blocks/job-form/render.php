<?php
/**
 * Block render: wp-career-board/job-form — multi-step job posting form for employers.
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

defined( 'ABSPATH' ) || exit;

$wcb_can_post_job = wp_is_ability_granted( 'wcb/post-jobs' );

if ( ! is_user_logged_in() || ! $wcb_can_post_job ) {
	require WCB_DIR . 'templates/parts/post-job-gate.php';
	return;
}

// ── Taxonomy terms ─────────────────────────────────────────────────────────
$wcb_categories = \WCB\Core\Taxonomy::options( 'wcb_category' );
$wcb_job_types  = \WCB\Core\Taxonomy::options( 'wcb_job_type' );
// Location dropdown is scoped to the employer: company HQ + reserved
// 'remote' / 'other' terms. Admins (wcb/manage-settings) get the full taxonomy.
// See \WCB\Core\Locations::get_dropdown_terms() for the resolution logic.
$wcb_locations   = \WCB\Core\Locations::get_dropdown_terms( get_current_user_id() );
$wcb_experiences = \WCB\Core\Taxonomy::options( 'wcb_experience' );

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
$wcb_company_id   = \WCB\Core\CompanyMetaShape::resolve_company_id( $wcb_user_id );
$wcb_company_post = $wcb_company_id ? get_post( $wcb_company_id ) : null;
$wcb_company_name = ( $wcb_company_post instanceof \WP_Post ) ? $wcb_company_post->post_title : '';

// ── Edit mode — pre-populate from existing job ──────────────────────────────
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only param, no state mutation.
$wcb_edit_id  = absint( wp_unslash( $_GET['edit'] ?? '0' ) );
$wcb_edit_job = null;
$wcb_e_cats   = array();
$wcb_e_types  = array();
$wcb_e_locs   = array();
$wcb_e_exps   = array();
$wcb_e_tags   = array();

if ( $wcb_edit_id > 0 ) {
	$wcb_edit_job = get_post( $wcb_edit_id );

	// Same rule as the REST update route: the poster, or anyone running the job's company.
	$wcb_can_edit = $wcb_edit_job instanceof \WP_Post
	&& 'wcb_job' === $wcb_edit_job->post_type
	&& ( \WCB\Modules\Employers\EmployersModule::owns_job( $wcb_user_id, $wcb_edit_job )
	|| wp_is_ability_granted( 'wcb/manage-settings' ) );

	if ( ! $wcb_can_edit ) {
		echo '<p>' . esc_html__( 'You are not authorized to edit this job.', 'wp-career-board' ) . '</p>';
		return;
	}

	$wcb_e_cats  = wp_get_object_terms( $wcb_edit_id, 'wcb_category', array( 'fields' => 'slugs' ) );
	$wcb_e_types = wp_get_object_terms( $wcb_edit_id, 'wcb_job_type', array( 'fields' => 'slugs' ) );
	$wcb_e_locs  = wp_get_object_terms( $wcb_edit_id, 'wcb_location', array( 'fields' => 'slugs' ) );
	$wcb_e_exps  = wp_get_object_terms( $wcb_edit_id, 'wcb_experience', array( 'fields' => 'slugs' ) );
	$wcb_e_tags  = wp_get_object_terms( $wcb_edit_id, 'wcb_tag', array( 'fields' => 'slugs' ) );
}

// ── Company picker: the companies this employer runs. An edit keeps the
// job's own company selected, even one outside that list (an admin editing),
// unless that company is gone: then the main company (set above) is offered.
$wcb_companies   = \WCB\Modules\Employers\EmployersModule::owned_companies( $wcb_user_id, true );
$wcb_job_company = $wcb_edit_job ? (int) get_post_meta( $wcb_edit_id, '_wcb_company_id', true ) : 0;
if ( $wcb_job_company > 0 && in_array( get_post_status( $wcb_job_company ), \WCB\Modules\Employers\EmployersModule::LIVE_COMPANY, true ) ) {
	$wcb_company_id = $wcb_job_company;
	if ( ! isset( $wcb_companies[ $wcb_job_company ] ) ) {
		// Raw title: companyNames feeds data-wp-text, which would print '&#038;'.
		$wcb_companies[ $wcb_job_company ] = (string) get_post_field( 'post_title', $wcb_job_company );
	}
}
$wcb_company_name = $wcb_companies[ $wcb_company_id ] ?? $wcb_company_name;

// ── Currency catalog — pulled from the canonical source in AdminSettings so
// admin settings + Pro additions (JPY/BRL/MXN/etc.) are guaranteed to match
// what the post-form lets employers pick. Each entry is array{name,symbol}.
$wcb_currency_catalog = \WCB\Admin\AdminSettings::get_currency_catalog();

// ── Default currency: site-wide admin setting wins for every employer. The
// per-job currency stored on an existing post still wins when editing, and the
// Pro per-board currency still wins when posting against a board (see below).
$wcb_preferred        = \WCB\Modules\Jobs\JobsMeta::default_currency();
$wcb_default_currency = array_key_exists( $wcb_preferred, $wcb_currency_catalog )
	? $wcb_preferred
	: ( array_key_exists( 'USD', $wcb_currency_catalog ) ? 'USD' : (string) array_key_first( $wcb_currency_catalog ) );

// ── Board currency — Pro returns the per-board currency when boardId is set ──
$wcb_board_id       = isset( $attributes['boardId'] ) ? (int) $attributes['boardId'] : 0;
$wcb_board_currency = $wcb_board_id > 0
	? (string) apply_filters( 'wcb_board_currency', '', $wcb_board_id )
	: '';

// ── Board picker options — multi-board sites (Pro) get a dropdown so the
// employer can target the post at a specific board. Single-board sites skip
// the picker entirely; the REST callback falls back to the default board.
$wcb_board_options = array();
// Per-board credit cost + currency maps: { board_id => value }. Seeded at
// render so the JS layer can update `state.creditCost` / `state.currencyCode`
// reactively when the employer switches boards in the picker, without a REST
// round-trip. Pro fulfils the per-board overrides via the
// `wcb_board_credit_cost` and `wcb_board_currency` filters.
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
		$wcb_board_options[]                        = array(
			'id'    => (int) $wcb_b->ID,
			'title' => $wcb_b->post_title,
		);
		$wcb_board_credit_costs[ (int) $wcb_b->ID ] = (int) apply_filters( 'wcb_board_credit_cost', 0, (int) $wcb_b->ID );
		$wcb_board_currencies[ (int) $wcb_b->ID ]   = (string) apply_filters( 'wcb_board_currency', '', (int) $wcb_b->ID );
	}

	/**
	 * Filter the Boards dropdown shown to the current employer in the job form.
	 *
	 * Pro's BP-group integration uses this to drop boards whose linked
	 * BuddyPress group the employer is not a member, mod, or admin of, so a
	 * site-admin who manages every group is the only role that sees the full
	 * list.
	 *
	 * @since 1.1.1
	 *
	 * @param array<int,array{id:int,title:string}> $wcb_board_options Default board list (every published wcb_board).
	 * @param int                                   $wcb_user_id       Current user id (0 if logged out).
	 */
	$wcb_board_options = (array) apply_filters( 'wcb_board_options_for_employer', $wcb_board_options, get_current_user_id() );
}
// Editing: preselect the job's own board, or saving would move (and re-price) it.
$wcb_edit_board_id = $wcb_edit_job ? \WCB\Modules\Jobs\JobsMeta::sanitize_board_id( get_post_meta( $wcb_edit_id, '_wcb_board_id', true ) ) : 0;
if ( $wcb_edit_board_id > 0 ) {
	$wcb_board_id = $wcb_edit_board_id;
}
if ( 0 === $wcb_board_id && ! empty( $wcb_board_options ) ) {
	$wcb_default_board_post = \WCB\Modules\Boards\BoardsModule::get_default_board_id();
	$wcb_board_id           = $wcb_default_board_post > 0 ? $wcb_default_board_post : (int) $wcb_board_options[0]['id'];
}

// ── Credit banner + credit gate messages ───────────────────────────────────
// Both the cost (per board) and the balance (per employer) are fully known at
// render time and cannot change without a page load — the board picker only
// swaps between values already seeded below. So every plural-bearing sentence
// is resolved HERE with its real count via _n(), never re-selected in JS with
// `count === 1`. A JS-side `one/many` pick is only correct in two-form locales:
// Polish needs distinct forms at 2, 5 and 22; Russian at 1, 2, 5; Arabic has six.
$wcb_credit_balance = (int) apply_filters( 'wcb_employer_credit_balance', 0, $wcb_user_id );

// Guarantee the currently selected board has a cost entry (a site with no
// wcb_board post type at all resolves to board 0).
if ( ! array_key_exists( $wcb_board_id, $wcb_board_credit_costs ) ) {
	$wcb_board_credit_costs[ $wcb_board_id ] = (int) apply_filters( 'wcb_board_credit_cost', 0, $wcb_board_id );
}
// Site admins post for free (JobPayment::is_exempt()), so every board reads as free to them.
if ( \WCB\Modules\Jobs\JobPayment::is_exempt( $wcb_edit_id ) ) {
	$wcb_board_credit_costs = array_map( '__return_zero', $wcb_board_credit_costs );
}

// A new job opens on a board the employer can afford, not on a paid default
// that greets a 0-credit employer with "This board requires 3 credits".
if ( ! $wcb_edit_job && empty( $attributes['boardId'] ) && ( $wcb_board_credit_costs[ $wcb_board_id ] ?? 0 ) > $wcb_credit_balance ) {
	foreach ( $wcb_board_options as $wcb_opt ) {
		if ( ( $wcb_board_credit_costs[ (int) $wcb_opt['id'] ] ?? 0 ) <= $wcb_credit_balance ) {
			$wcb_board_id = (int) $wcb_opt['id'];
			break;
		}
	}
}

/**
 * Pluralised credit noun, resolved with the real count.
 *
 * @param int $wcb_n Number of credits.
 * @return string
 */
$wcb_credit_noun = static function ( int $wcb_n ): string {
	return sprintf(
		/* translators: %s: number of credits, already formatted for the locale. */
		_n( '%s credit', '%s credits', $wcb_n, 'wp-career-board' ),
		number_format_i18n( $wcb_n )
	);
};

$wcb_credit_messages = array();
$wcb_credit_errors   = array();
// Only point at buying when the site actually sells credits.
$wcb_credit_short = '' !== (string) apply_filters( 'wcb_credit_purchase_url', '' )
	/* translators: 1: pluralised credit cost ("1 credit" / "5 credits"), 2: employer's current balance. */
	? __( 'This board requires %1$s. Your balance: %2$s. Please purchase more credits.', 'wp-career-board' )
	/* translators: 1: pluralised credit cost ("1 credit" / "5 credits"), 2: employer's current balance. */
	: __( 'This board requires %1$s. Your balance: %2$s. Contact the site owner to add credits.', 'wp-career-board' );
foreach ( $wcb_board_credit_costs as $wcb_bid => $wcb_cost ) {
	$wcb_cost = (int) $wcb_cost;

	if ( $wcb_cost <= 0 ) {
		// A free board needs no notice; the banner only speaks when posting costs.
		$wcb_credit_messages[ (string) $wcb_bid ] = '';
		$wcb_credit_errors[ (string) $wcb_bid ]   = '';
		continue;
	}

	if ( $wcb_credit_balance < $wcb_cost ) {
		$wcb_credit_messages[ (string) $wcb_bid ] = sprintf(
			$wcb_credit_short,
			$wcb_credit_noun( $wcb_cost ),
			number_format_i18n( $wcb_credit_balance )
		);
	} else {
		$wcb_credit_messages[ (string) $wcb_bid ] = sprintf(
			/* translators: 1: pluralised credit cost ("1 credit" / "5 credits"), 2: balance after deduction, 3: current balance. */
			__( 'Posting deducts %1$s. Balance after: %2$s (currently %3$s).', 'wp-career-board' ),
			$wcb_credit_noun( $wcb_cost ),
			number_format_i18n( $wcb_credit_balance - $wcb_cost ),
			number_format_i18n( $wcb_credit_balance )
		);
	}

	$wcb_credit_errors[ (string) $wcb_bid ] = sprintf(
		/* translators: 1: pluralised credit cost ("1 credit" / "5 credits"), 2: employer's current balance. */
		__( 'Insufficient credits. This board requires %1$s but your balance is %2$s.', 'wp-career-board' ),
		$wcb_credit_noun( $wcb_cost ),
		number_format_i18n( $wcb_credit_balance )
	);
}

// ── Currency code → symbol map, for the client-side salary preview ─────────
// Mirrors \WCB\Core\SalaryFormat::symbol()'s fallback (code + trailing space).
$wcb_currency_symbols = array();
foreach ( $wcb_currency_catalog as $wcb_code => $wcb_meta ) {
	$wcb_currency_symbols[ (string) $wcb_code ] = (string) $wcb_meta['symbol'];
}

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
		'editBoardId'       => $wcb_edit_board_id,
		'step'              => 1,
		'title'             => $wcb_edit_job ? $wcb_edit_job->post_title : '',
		'description'       => $wcb_edit_job ? $wcb_edit_job->post_content : '',
		'salaryMin'         => $wcb_edit_job ? (string) get_post_meta( $wcb_edit_id, '_wcb_salary_min', true ) : '',
		'salaryMax'         => $wcb_edit_job ? (string) get_post_meta( $wcb_edit_id, '_wcb_salary_max', true ) : '',
		'defaultCurrency'   => $wcb_default_currency,
		'currencyCode'      => $wcb_edit_job
			? ( get_post_meta( $wcb_edit_id, '_wcb_salary_currency', true ) ? get_post_meta( $wcb_edit_id, '_wcb_salary_currency', true ) : $wcb_default_currency )
			: ( $wcb_board_currency ? $wcb_board_currency : $wcb_default_currency ),
		'salaryType'        => $wcb_edit_job && in_array( get_post_meta( $wcb_edit_id, '_wcb_salary_type', true ), array( 'yearly', 'monthly', 'hourly' ), true ) ? get_post_meta( $wcb_edit_id, '_wcb_salary_type', true ) : 'yearly',
		'requireLocation'   => \WCB\Admin\Settings::bool( 'require_job_location', false ),
		'remote'            => $wcb_edit_job && '1' === (string) get_post_meta( $wcb_edit_id, '_wcb_remote', true ),
		// The employer may pick any deadline up to the board's listing length
		// (owner decision, 1.8.1); REST clamps to the same maximum on save.
		'deadline'          => $wcb_edit_job ? (string) get_post_meta( $wcb_edit_id, '_wcb_deadline', true ) : \WCB\Core\JobDeadline::default_end_for_board( $wcb_board_id ),
		'deadlineMax'       => max( $wcb_edit_job ? (string) get_post_meta( $wcb_edit_id, '_wcb_deadline', true ) : '', \WCB\Core\JobDeadline::default_end_for_board( $wcb_board_id ) ),
		'deadlineMin'       => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
		'applyUrl'          => $wcb_edit_job ? (string) get_post_meta( $wcb_edit_id, '_wcb_apply_url', true ) : '',
		'applyEmail'        => $wcb_edit_job ? (string) get_post_meta( $wcb_edit_id, '_wcb_apply_email', true ) : '',
		'locationSlug'      => ! is_wp_error( $wcb_e_locs ) && $wcb_e_locs ? $wcb_e_locs[0] : '',
		// Manual override path for jobs whose location isn't an existing term —
		// employer types a one-off string ('Berlin, DE', 'Remote — Europe', etc.).
		// On submit the form sends `location_custom` and the create/update
		// callback wp_inserts a matching term so the listings filter still works.
		'locationCustom'    => $wcb_edit_job ? (string) get_post_meta( $wcb_edit_id, '_wcb_location_custom', true ) : '',
		'typeSlug'          => ! is_wp_error( $wcb_e_types ) && $wcb_e_types ? $wcb_e_types[0] : '',
		// Every category the job has, even past the limit, so an edit never drops one.
		'categorySlugs'     => ! is_wp_error( $wcb_e_cats ) ? $wcb_e_cats : array(),
		'expSlug'           => ! is_wp_error( $wcb_e_exps ) && $wcb_e_exps ? $wcb_e_exps[0] : '',
		'tags'              => ! is_wp_error( $wcb_e_tags ) ? implode( ', ', $wcb_e_tags ) : '',
		'companyId'         => $wcb_company_id,
		'companyName'       => $wcb_company_name,
		'companyNames'      => (object) $wcb_companies,
		// The picker's options; the employer dashboard updates it when a company is added or removed.
		'companyList'       => array_map(
			static fn ( $id, $name ): array => array(
				'id'   => (int) $id,
				'name' => (string) $name,
			),
			array_keys( $wcb_companies ),
			$wcb_companies
		),
		'submitting'        => false,
		'submitted'         => false,
		'jobUrl'            => '',
		'error'             => '',
		'validationError'   => '',
		'validationField'   => '',
		'apiBase'           => untrailingslashit( rest_url( 'wcb/v1' ) ),
		'nonce'             => wp_create_nonce( 'wp_rest' ),
		'creditCost'        => (int) $wcb_board_credit_costs[ $wcb_board_id ],
		// Per-board cost lookup so view.js can update creditCost
		// reactively when the employer switches boards. Object keyed by
		// stringified board ID for predictable JS access. See the
		// matching `actions.updateField` board-switch branch.
		'boardCreditCosts'  => (object) array_map( 'intval', $wcb_board_credit_costs ),
		// Per-board currency override map: { board_id => 'EUR' }. JS reads
		// this on board switch so the currency dropdown updates without a
		// REST round-trip. Empty string means no override - fall back to
		// the current state.currencyCode.
		'boardCurrencies'   => (object) array_map( 'strval', $wcb_board_currencies ),
		'creditBalance'     => $wcb_credit_balance,
		// Fully resolved, plural-correct banner + gate sentences keyed by board
		// id. Built above with _n() against the REAL count, because both the
		// cost and the balance are known server-side. JS reads the entry for the
		// selected board; it never re-selects a plural form with `count === 1`.
		'creditMessages'    => (object) $wcb_credit_messages,
		'creditErrors'      => (object) $wcb_credit_errors,
		'creditPurchaseUrl' => (string) apply_filters( 'wcb_credit_purchase_url', '' ),
		'buyUrl'            => '',
		'featured'          => false,
		'featureError'      => '',
		// Board picker — only meaningful when more than one board exists, since
		// a single-board site has nothing to pick from. The REST callback falls
		// back to the default board id when boardId stays 0.
		'boardId'           => $wcb_board_id,
		'boardOptions'      => $wcb_board_options,
		// Server half of view.js `isGroupOnBoard`, so the first paint already
		// shows only the selected board's custom-field groups.
		'isGroupOnBoard'    => static fn (): bool => \WCB\Core\FormCustomFields::group_on_board( $wcb_board_id ),
		'customFields'      => (object) (
			$wcb_edit_id > 0
				? \WCB\Core\FormCustomFields::load_values(
					(array) apply_filters( 'wcb_job_form_fields', array(), $wcb_board_id ),
					$wcb_edit_id
				)
				: array()
		),
		'typeNames'         => (object) $wcb_type_names,
		'expNames'          => (object) $wcb_exp_names,
		'locationNames'     => (object) $wcb_location_names,
		'categoryNames'     => (object) $wcb_category_names,
		// ── Locale for client-side number / date formatting ───────────────
		// Root-level sibling of `i18n` (NOT inside it). Intl.NumberFormat and
		// Intl.DateTimeFormat with no locale argument format against the
		// BROWSER locale — a de_DE site viewed from an en-US browser would
		// render "1,000" where it must render "1.000".
		'locale'            => \WCB\Core\SalaryFormat::locale(),
		// Currency code → display symbol, mirroring SalaryFormat::symbol().
		// The salary preview changes on every keystroke with no server
		// round-trip, so it is the one place the money string must be
		// assembled client-side; `i18n.moneyFormat` carries the symbol
		// POSITION so fr_FR/de_DE/sv_SE can render "60 k €", not "€60k".
		'currencySymbols'   => (object) $wcb_currency_symbols,
		// ── i18n contract ─────────────────────────────────────────────────
		// view.js is registered as a script *module* (viewScriptModule) and
		// script modules cannot load JED translation files on this plugin's
		// WP floor. Every user-facing string view.js renders is therefore
		// translated here and seeded through wp_interactivity_state(). view.js
		// reads each one with its t( key, fallback ) helper; the fallback is
		// the identical English source string.
		//
		// HARD RULE: every key view.js reads must exist in this array, and
		// every key in this array must be read by view.js (no dead seeds).
		//
		// NOTE: no key here may be a pre-selected plural form. Counts that are
		// known server-side are resolved with _n() before seeding (see
		// $wcb_credit_messages / $wcb_credit_errors above).
		'i18n'              => array_merge(
			// moneyFormat, salaryThousand, salaryMillion, salaryRange,
			// salaryOpenMin, salaryUpTo, salaryPerYear/Month/Hour — the
			// canonical money-format strings shared with the PHP formatter.
			\WCB\Core\SalaryFormat::js_strings(),
			array(
				// Errors / validation.
				'errorSessionExpired'      => __( 'Your session has expired. Please refresh the page and try again.', 'wp-career-board' ),
				'errorConnection'          => __( 'Connection error. Please check your network and try again.', 'wp-career-board' ),
				'errorSubmitFailed'        => __( 'Job could not be posted. Please try again.', 'wp-career-board' ),
				'errorTitleRequired'       => __( 'Job title is required before you can continue.', 'wp-career-board' ),
				'errorDescriptionRequired' => __( 'Job description is required before you can continue.', 'wp-career-board' ),
				'errorLocationRequired'    => __( 'Add a location, or mark the job as remote.', 'wp-career-board' ),
				'errorSalaryRange'         => __( 'The minimum salary is more than the maximum.', 'wp-career-board' ),
				'errorAiNoTitle'           => __( 'Enter a job title first so AI can generate a description.', 'wp-career-board' ),
				'errorAiFailed'            => __( 'Failed to generate description. Please try again.', 'wp-career-board' ),
				// Count-free fallback for the credit gate; the numbered,
				// plural-resolved sentence comes from state.creditErrors.
				'errorInsufficientCredits' => __( 'Insufficient credits to post on this board.', 'wp-career-board' ),

				// Submit button label (mirrors the server-rendered default).
				'submitLabelPost'          => __( 'Post Job', 'wp-career-board' ),
				'submitLabelUpdate'        => __( 'Update Job', 'wp-career-board' ),

				// Listing window banner.
				/* translators: %1$s: localised expiry date, e.g. "June 12, 2026". */
				'listingWindow'            => __( 'Your listing stays live until %1$s. You can renew it from your dashboard.', 'wp-career-board' ),
				/* translators: %s: latest allowed deadline date. */
				'deadlineHelp'             => __( 'Pick any date up to %s, the longest this board allows.', 'wp-career-board' ),

				// Preview card meta row.
				/* translators: %s: localized application deadline date. */
				'applyBy'                  => __( 'Apply by %s', 'wp-career-board' ),
				'listSeparator'            => _x( ', ', 'separator between category names', 'wp-career-board' ),
			)
		),
	),
	$attributes
);

// Paid Featured upgrade (Pro prices it; 0 means not offered).
$wcb_featured_cost = (int) apply_filters( 'wcb_featured_upgrade_cost', 0 );
$wcb_featured_days = \WCB\Admin\Settings::int( 'apply_featured_days', 30 );

wp_interactivity_state( 'wcb-job-form', $wcb_initial_state );

// ── Step labels ────────────────────────────────────────────────────────────
$wcb_dashboard_id = \WCB\Admin\Pages::get_id( 'employer_dashboard_page' );
$wcb_my_jobs_url  = $wcb_dashboard_id > 0 ? (string) get_permalink( $wcb_dashboard_id ) . '#jobs' : '';

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

	<?php
	// The screen's title, as on every other dashboard view, unless the page
	// carries its own H1 (Basecamp 10439524696).
	if ( ! \WCB\Core\ArchiveHeading::page_has_h1() ) :
		?>
		<div class="wcb-page-header">
			<h1 class="wcb-page-title"><?php echo esc_html( $wcb_edit_id > 0 ? __( 'Edit Job', 'wp-career-board' ) : __( 'Post a Job', 'wp-career-board' ) ); ?></h1>
		</div>
	<?php endif; ?>

	<!-- ── Step indicator ────────────────────────────────────────────────── -->
	<nav class="wcb-steps" aria-label="<?php esc_attr_e( 'Form progress', 'wp-career-board' ); ?>" data-wp-class--wcb-hidden="state.submitted">
		<?php foreach ( $wcb_step_labels as $wcb_step_num => $wcb_step_label ) : ?>
			<span
				class="wcb-step<?php echo 1 === $wcb_step_num ? ' wcb-step--active' : ''; ?>"
				data-wp-class--wcb-step--active="state.step<?php echo esc_attr( (string) $wcb_step_num ); ?>Active"
			<?php if ( $wcb_step_num < 4 ) : ?>
				data-wp-class--wcb-step--done="state.step<?php echo esc_attr( (string) $wcb_step_num ); ?>Done"
			<?php endif; ?>
			<?php if ( 1 === $wcb_step_num ) : ?>
				aria-current="step"
			<?php endif; ?>
				data-wp-bind--aria-current="state.step<?php echo esc_attr( (string) $wcb_step_num ); ?>AriaCurrent"
			>
				<span class="wcb-step__num"><?php echo esc_html( number_format_i18n( $wcb_step_num ) ); ?></span>
				<span class="wcb-step__label"><?php echo esc_html( $wcb_step_label ); ?></span>
			</span>
			<?php if ( $wcb_step_num < 4 ) : ?>
				<span class="wcb-step__connector" aria-hidden="true"></span>
			<?php endif; ?>
		<?php endforeach; ?>
	</nav>
	<?php // Phones show numbers only, so the step's name is spelled out under them (Basecamp 10439851743). ?>
	<p class="wcb-steps__caption" aria-hidden="true" data-wp-class--wcb-hidden="state.submitted">
		<?php foreach ( $wcb_step_labels as $wcb_step_num => $wcb_step_label ) : ?>
			<span class="<?php echo 1 === $wcb_step_num ? '' : 'wcb-hidden'; ?>" data-wp-class--wcb-hidden="!state.isStep<?php echo esc_attr( (string) $wcb_step_num ); ?>">
				<?php
				/* translators: 1: current step number, 2: number of steps, 3: step name. */
				echo esc_html( sprintf( __( 'Step %1$s of %2$s: %3$s', 'wp-career-board' ), number_format_i18n( $wcb_step_num ), number_format_i18n( count( $wcb_step_labels ) ), $wcb_step_label ) );
				?>
			</span>
		<?php endforeach; ?>
	</p>

	<!-- ── Credit info banner ────────────────────────────────────────────── -->
	<div
		class="wcb-credit-banner"
		data-wp-class--wcb-credit-banner--show="state.hasCreditBanner"
		data-wp-class--wcb-credit-banner--warn="state.insufficientCredits"
	>
		<span class="wcb-credit-banner__text" data-wp-text="state.creditMessage"></span>
		<?php
		$wcb_purchase_url_for_banner = (string) apply_filters( 'wcb_credit_purchase_url', '' );
		if ( '' !== $wcb_purchase_url_for_banner ) :
			?>
		<a
			class="wcb-credit-banner__link"
			data-wp-bind--href="state.creditPurchaseUrl"
			data-wp-class--wcb-hidden="!state.insufficientCredits"
		><?php esc_html_e( 'Buy Credits →', 'wp-career-board' ); ?></a>
		<?php endif; ?>
	</div>

	<!-- ── Submission success ────────────────────────────────────────────── -->
	<div class="wcb-form-success" data-wp-class--wcb-form-success--show="state.submitted">
		<span class="wcb-form-success__icon" aria-hidden="true"><?php echo \WCB\Core\Icon::svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?></span>
		<div>
			<p class="wcb-form-success__title" data-wp-class--wcb-form-success__title--pending="state.jobPending">
				<span data-wp-class--wcb-hidden="state.jobPending">
					<span data-wp-class--wcb-hidden="state.isEditing"><?php esc_html_e( 'Job posted successfully!', 'wp-career-board' ); ?></span>
					<span data-wp-class--wcb-hidden="!state.isEditing"><?php esc_html_e( 'Job updated.', 'wp-career-board' ); ?></span>
				</span>
				<span data-wp-class--wcb-hidden="!state.jobPending">
					<?php esc_html_e( 'Job submitted for review. You\'ll be notified once it\'s approved.', 'wp-career-board' ); ?>
				</span>
			</p>
			<p
				class="wcb-form-success__meta"
				data-wp-class--wcb-hidden="!state.hasListingWindow"
				data-wp-text="state.listingWindowMessage"
			></p>
			<p class="wcb-form-success__meta" hidden data-wp-bind--hidden="!state.featureError" data-wp-text="state.featureError"></p>
			<a class="wcb-form-success__link" data-wp-bind--href="state.jobUrl" data-wp-class--wcb-hidden="state.jobPending">
				<?php esc_html_e( 'View your job listing →', 'wp-career-board' ); ?>
			</a>
			<?php // Somewhere to go next, pending or live (Basecamp 10439859260). ?>
			<div class="wcb-form-success__actions">
				<?php if ( $wcb_my_jobs_url ) : ?>
					<a class="wcb-btn wcb-btn--primary" href="<?php echo esc_url( $wcb_my_jobs_url ); ?>"><?php esc_html_e( 'View my jobs', 'wp-career-board' ); ?></a>
				<?php endif; ?>
				<button type="button" class="wcb-form-success__reset" data-wp-on--click="actions.resetForm">
					<?php esc_html_e( 'Post another job', 'wp-career-board' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- ── Form steps ────────────────────────────────────────────────────── -->
	<div class="wcb-form-steps" data-wp-class--wcb-form-steps--hidden="state.submitted">

		<!-- ── Step 1: Basics ──────────────────────────────────────────── -->
		<div class="wcb-form-step wcb-form-step--show" data-wp-class--wcb-form-step--show="state.isStep1">
			<h2 class="wcb-form-step__title">
				<?php esc_html_e( 'Job Basics', 'wp-career-board' ); ?>
			</h2>

			<?php if ( count( $wcb_board_options ) > 1 ) : ?>
			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-board-id">
					<?php esc_html_e( 'Post to Board', 'wp-career-board' ); ?>
				</label>
				<select
					id="wcb-board-id"
					class="wcb-field"
					data-wcb-field="boardId"
					data-wp-bind--value="state.boardId"
					data-wp-on--change="actions.updateField"
				>
					<?php foreach ( $wcb_board_options as $wcb_b ) : ?>
						<option value="<?php echo (int) $wcb_b['id']; ?>" <?php selected( $wcb_board_id, (int) $wcb_b['id'] ); ?>>
							<?php echo esc_html( $wcb_b['title'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>

			<div class="wcb-form-field<?php echo count( $wcb_companies ) > 1 ? '' : ' wcb-hidden'; ?>" data-wp-class--wcb-hidden="!state.hasCompanyChoice">
				<label class="wcb-form-label" for="wcb-company-id">
					<?php esc_html_e( 'Company', 'wp-career-board' ); ?>
				</label>
				<select
					id="wcb-company-id"
					class="wcb-field"
					data-wcb-field="companyId"
					data-wp-bind--value="state.companyId"
					data-wp-on--change="actions.updateField"
				>
					<template data-wp-each--co="state.companyList" data-wp-each-key="context.co.id">
						<option data-wp-bind--value="context.co.id" data-wp-text="context.co.name"></option>
					</template>
				</select>
			</div>
			

			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-job-title">
					<?php esc_html_e( 'Job title', 'wp-career-board' ); ?>
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
					aria-required="true"
					aria-describedby="wcb-job-title-error"
					data-wp-bind--aria-invalid="state.titleInvalid"
					autocomplete="off"
				/>
				<p class="wcb-field-error" id="wcb-job-title-error" role="alert" hidden data-wp-bind--hidden="!state.titleInvalid" data-wp-text="state.validationError"></p>
			</div>

			<div class="wcb-form-field">
				<div class="wcb-form-label-row">
					<label class="wcb-form-label" for="wcb-job-desc">
						<?php esc_html_e( 'Job description', 'wp-career-board' ); ?>
						<span class="wcb-required" aria-hidden="true">*</span>
					</label>
					<?php if ( apply_filters( 'wcb_ai_description_enabled', false ) ) : ?>
					<button type="button" class="wcb-ai-btn"
						data-wp-on--click="actions.generateDescription"
						data-wp-bind--disabled="state.aiGenerating"
					>
						<span data-wp-class--wcb-hidden="state.aiGenerating">&#10024; <?php esc_html_e( 'Generate with AI', 'wp-career-board' ); ?></span>
						<span data-wp-class--wcb-hidden="!state.aiGenerating"><?php esc_html_e( 'Generating…', 'wp-career-board' ); ?></span>
					</button>
					<?php endif; ?>
				</div>
				<div class="wcb-editor" data-placeholder="<?php esc_attr_e( 'Describe the role, responsibilities and requirements…', 'wp-career-board' ); ?>">
					<?php require __DIR__ . '/editor-toolbar.php'; ?>
					<div class="wcb-editor-holder" id="wcb-editor-job-desc" aria-describedby="wcb-job-desc-error" data-wp-bind--aria-invalid="state.descriptionInvalid"></div>
					<textarea
						id="wcb-job-desc"
						class="wcb-editor-source"
						rows="1"
						tabindex="-1"
						aria-label="<?php esc_attr_e( 'Job description', 'wp-career-board' ); ?>"
						data-wcb-field="description"
						data-wp-bind--value="state.description"
						data-wp-on--input="actions.updateField"
						required
						aria-required="true"
					><?php echo esc_textarea( $wcb_edit_job ? (string) $wcb_edit_job->post_content : '' ); ?></textarea>
				</div>
				<p class="wcb-field-error" id="wcb-job-desc-error" role="alert" hidden data-wp-bind--hidden="!state.descriptionInvalid" data-wp-text="state.validationError"></p>
				<span class="wcb-form-hint">
					<?php esc_html_e( 'Format with the toolbar above. For headings and quotes, use the + beside a line.', 'wp-career-board' ); ?>
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
							aria-describedby="wcb-salary-error"
							data-wp-bind--aria-invalid="state.salaryInvalid"
						/>
					</div>
					<div class="wcb-field-group">
						<label class="wcb-field-group__label" for="wcb-salary-type">
							<?php esc_html_e( 'Per', 'wp-career-board' ); ?>
						</label>
						<select
							id="wcb-salary-type"
							class="wcb-field"
							data-wcb-field="salaryType"
							data-wp-bind--value="state.salaryType"
							data-wp-on--change="actions.updateField"
						>
							<option value="yearly"><?php esc_html_e( 'Year', 'wp-career-board' ); ?></option>
							<option value="monthly"><?php esc_html_e( 'Month', 'wp-career-board' ); ?></option>
							<option value="hourly"><?php esc_html_e( 'Hour', 'wp-career-board' ); ?></option>
						</select>
					</div>
				</div>
				<span class="wcb-form-hint">
					<?php esc_html_e( 'Leave blank to hide salary from candidates.', 'wp-career-board' ); ?>
				</span>
				<p class="wcb-field-error" id="wcb-salary-error" role="alert" hidden data-wp-bind--hidden="!state.salaryInvalid" data-wp-text="state.validationError"></p>
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

			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-deadline">
					<?php esc_html_e( 'Application Deadline', 'wp-career-board' ); ?>
				</label>
				<input
					id="wcb-deadline"
					type="date"
					class="wcb-field wcb-field--date"
					data-wp-bind--value="state.deadline"
					data-wp-bind--min="state.deadlineMin"
					data-wp-bind--max="state.deadlineMax"
					data-wp-on--change="actions.setDeadline"
					aria-describedby="wcb-deadline-help"
				/>
				<p class="wcb-form-help" id="wcb-deadline-help" data-wp-text="state.deadlineHelp"></p>
				<?php // Beside the date it describes, not above the first field before a board is picked (Basecamp 10439851754). ?>
				<p class="wcb-form-help" data-wp-class--wcb-hidden="!state.hasListingWindow" data-wp-text="state.listingWindowMessage"></p>
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
				<button type="button" class="wcb-btn wcb-btn--secondary" data-wp-on--click="actions.prevStep">
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
				<?php
				$wcb_jobcat_form  = 'wcb-job-form';
				$wcb_jobcat_id    = 'wcb-category';
				$wcb_jobcat_terms = $wcb_categories;
				require WCB_DIR . 'templates/parts/job-category-field.php';
				?>

				<div class="wcb-form-field">
					<label class="wcb-form-label" for="wcb-job-type">
						<?php esc_html_e( 'Job type', 'wp-career-board' ); ?>
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
						<?php if ( \WCB\Admin\Settings::bool( 'require_job_location', false ) ) : ?>
							<span class="wcb-form-hint"><?php esc_html_e( '(required unless the job is remote)', 'wp-career-board' ); ?></span>
						<?php endif; ?>
					</label>
					<select
						id="wcb-location"
						class="wcb-field"
						aria-describedby="wcb-location-error"
						data-wp-bind--aria-invalid="state.locationInvalid"
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
						<option value="__custom__"><?php esc_html_e( 'Other (enter manually)…', 'wp-career-board' ); ?></option>
					</select>
					<input
						type="text"
						id="wcb-location-custom"
						class="wcb-field"
						data-wcb-field="locationCustom"
						data-wp-bind--value="state.locationCustom"
						data-wp-on--input="actions.updateField"
						data-wp-class--wcb-hidden="!state.locationIsCustom"
						aria-label="<?php esc_attr_e( 'Custom location', 'wp-career-board' ); ?>"
						placeholder="<?php esc_attr_e( 'e.g. Berlin, DE or Remote - Europe', 'wp-career-board' ); ?>"
						maxlength="120"
					/>
					<p class="wcb-field-error" id="wcb-location-error" role="alert" hidden data-wp-bind--hidden="!state.locationInvalid" data-wp-text="state.validationError"></p>
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

			// Custom-field groups from wcb_job_form_fields (Pro Field Builder
			// + any add-on), at the end of step 3, for every board in the picker.
			\WCB\Core\FormCustomFields::render_job_groups_by_board( array_merge( array( $wcb_board_id ), wp_list_pluck( $wcb_board_options, 'id' ) ), 'updateCustomField', 'wcb-job-step3-custom', (int) $wcb_edit_id );
			?>

			<div class="wcb-form-nav">
				<button type="button" class="wcb-btn wcb-btn--secondary" data-wp-on--click="actions.prevStep">
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

				<div class="wcb-preview-card__tags" data-wp-bind--hidden="!state.hasTags">
					<template data-wp-each--tag="state.tagList">
						<span class="wcb-cbadge wcb-cbadge--tag wcb-cbadge--show" data-wp-text="context.tag"></span>
					</template>
				</div>

				<!-- Meta row -->
				<div class="wcb-preview-card__meta">
					<span class="wcb-preview-meta-item" data-wp-class--wcb-preview-meta-item--show="state.hasSalary">
						<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
						<span data-wp-text="state.salaryDisplay"></span>
					</span>
					<?php // Single format string ("Apply by %s"), not label + value concatenated: the date must be able to precede the label in other locales. ?>
					<span class="wcb-preview-meta-item" data-wp-class--wcb-preview-meta-item--show="state.hasDeadline">
						<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
						<span data-wp-text="state.applyByLabel"></span>
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

				<div class="wcb-preview-card__desc" data-wp-bind--hidden="!state.descriptionPreview">
					<h4 class="wcb-preview-card__desc-title"><?php esc_html_e( 'Description', 'wp-career-board' ); ?></h4>
					<p class="wcb-preview-card__desc-text" data-wp-text="state.descriptionPreview"></p>
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

<?php if ( $wcb_featured_cost > 0 ) : ?>
			<label class="wcb-form-feature">
				<input type="checkbox" data-wp-on--change="actions.toggleFeatured" />
				<span>
				<?php
				printf(
					/* translators: 1: number of days, 2: number of credits */
					esc_html( _n( 'Feature this job: it lists first for %1$d days (%2$s credit).', 'Feature this job: it lists first for %1$d days (%2$s credits).', $wcb_featured_cost, 'wp-career-board' ) ),
					(int) $wcb_featured_days,
					esc_html( number_format_i18n( $wcb_featured_cost ) )
				);
				?>
				</span>
			</label>
			<?php endif; ?>

			<p class="wcb-form-error" role="alert" data-wp-class--wcb-form-error--show="state.hasError" data-wp-text="state.error"></p>
			<p class="wcb-form-buy" hidden data-wp-bind--hidden="!state.buyUrl"><a class="wcb-btn wcb-btn--secondary" data-wp-bind--href="state.buyUrl"><?php esc_html_e( 'Buy credits', 'wp-career-board' ); ?></a></p>

			<div class="wcb-form-nav">
				<button
					type="button"
					class="wcb-btn wcb-btn--secondary"
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
					<span class="wcb-btn__label" data-wp-text="state.submitLabel"><?php echo esc_html( $wcb_edit_id > 0 ? __( 'Update Job', 'wp-career-board' ) : __( 'Post Job', 'wp-career-board' ) ); ?></span>
					<span class="wcb-btn__spinner">
						<svg class="wcb-spinner" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
						<?php esc_html_e( 'Posting…', 'wp-career-board' ); ?>
					</span>
				</button>
			</div>
		</div>

	<!-- ── Honeypot anti-spam ────────────────────────────────────────────── -->
	<div class="wcb-hp-wrap" aria-hidden="true">
		<label for="wcb-hp"><?php esc_html_e( 'Leave this field blank', 'wp-career-board' ); ?></label>
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
