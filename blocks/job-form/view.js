/**
 * WP Career Board — job-form block Interactivity API store.
 *
 * Actions:
 *   updateField  — generic field updater via data-wcb-field attribute.
 *   toggleRemote — flip the remote boolean.
 *   nextStep     — advance step; validates title on step 1.
 *   prevStep     — go back one step.
 *   submitJob    — POST job data to /wcb/v1/jobs.
 *   resetForm    — Clear success state and return to step 1.
 *
 * State getters:
 *   isStep1 … isStep4          — drive wcb-form-step--show CSS class on each step panel.
 *   step1Active … step4Active  — drive wcb-step--active CSS class on the step indicator.
 *   step1AriaCurrent … step4AriaCurrent — drive aria-current on the step indicator.
 *   step1Done   … step3Done    — drive wcb-step--done CSS class on the step indicator.
 *   salaryDisplay              — formatted salary string for preview.
 *   hasCompany, isRemote, hasType, hasExp, hasLocation, hasCategory — preview card conditionals.
 *   hasSalary, hasDeadline, hasApplyUrl, hasApplyEmail              — preview meta conditionals.
 *   hasError, hasValidation                                          — error banner conditionals.
 *
 * @package WP_Career_Board
 */
import { store } from '@wordpress/interactivity';
import { wcbFetch } from '@wcb/fetch';

/**
 * Translation reader.
 *
 * view.js ships as a script module (viewScriptModule) and script modules cannot
 * load JED translation files on this plugin's WP floor, so every user-facing
 * string is translated in render.php and seeded under state.i18n via
 * wp_interactivity_state(). The fallback is the identical English source.
 *
 * @param {string} key      Key seeded in render.php's `i18n` array.
 * @param {string} fallback English source string (must match the PHP __() text).
 * @return {string} Translated string, or the English fallback.
 */
const t = ( key, fallback ) => ( state.i18n && state.i18n[ key ] ) || fallback;

/**
 * Fill a numbered/anonymous gettext placeholder.
 *
 * Uses a replacer function so a `$` inside the injected value is never treated
 * as a `$&`/`$1` replacement pattern.
 *
 * @param {string} template Format string containing the placeholder.
 * @param {string} token    Placeholder to fill, e.g. '%1$s'.
 * @param {string} value    Replacement value.
 * @return {string} Filled string.
 */
const fill = ( template, token, value ) => template.replace( token, () => String( value ) );

/**
 * The site locale (seeded at state root as a BCP-47 tag by
 * \WCB\Core\SalaryFormat::locale()), validated once and memoised.
 *
 * Returns `undefined` — the runtime default — only when the tag is missing or
 * Intl rejects it, so an exotic WP locale can never throw inside a getter.
 *
 * @return {string|undefined} Locale tag for Intl, or undefined.
 */
let wcbLocale;
const locale = () => {
	if ( undefined === wcbLocale ) {
		wcbLocale = null;
		const tag = state.locale || '';
		if ( tag ) {
			try {
				new Intl.NumberFormat( tag );
				wcbLocale = tag;
			} catch ( _e ) {
				wcbLocale = null;
			}
		}
	}
	return wcbLocale || undefined;
};

/**
 * Localised number, formatted against the SITE locale, never the browser
 * locale — a de_DE site viewed from an en-US browser must render "1.000".
 *
 * @param {number} value   Number to format.
 * @param {Object} options Intl.NumberFormat options.
 * @return {string} Localised number.
 */
const num = ( value, options ) => new Intl.NumberFormat( locale(), options ).format( value );

/**
 * Abbreviate a figure, mirroring \WCB\Core\SalaryFormat::abbreviate().
 *
 * @param {number} value Raw amount.
 * @return {string} Abbreviated, localised amount.
 */
const abbreviate = ( value ) => {
	if ( value >= 1000000 ) {
		const n = Math.round( ( value / 1000000 ) * 10 ) / 10;
		const amount = Number.isInteger( n )
			? num( n )
			: num( n, { minimumFractionDigits: 1, maximumFractionDigits: 1 } );
		return fill( t( 'salaryMillion', '%sM' ), '%s', amount );
	}
	if ( value >= 1000 ) {
		return fill( t( 'salaryThousand', '%sk' ), '%s', num( Math.round( value / 1000 ) ) );
	}
	return num( value );
};

/**
 * Combine a currency symbol with an already-localised amount, mirroring
 * \WCB\Core\SalaryFormat::money(). The placeholders are numbered so translators
 * can move the symbol after the amount (fr_FR / de_DE / sv_SE).
 *
 * @param {string} symbol Currency symbol.
 * @param {string} amount Localised amount.
 * @return {string} Money string.
 */
const money = ( symbol, amount ) =>
	fill( fill( t( 'moneyFormat', '%1$s%2$s' ), '%1$s', symbol ), '%2$s', amount );

/**
 * Currency symbol for a code, mirroring \WCB\Core\SalaryFormat::symbol()'s
 * fallback (uppercased code plus a trailing space).
 *
 * @param {string} code ISO 4217 code.
 * @return {string} Display symbol.
 */
const symbolFor = ( code ) =>
	( state.currencySymbols && state.currencySymbols[ code ] ) || code + ' ';

/**
 * Format an ISO (Y-m-d) date against the SITE locale.
 *
 * @param {string} iso ISO date string.
 * @return {string} Localised date, or the raw ISO string when unparseable.
 */
const formatDate = ( iso ) => {
	if ( ! iso ) {
		return '';
	}
	try {
		const d = new Date( iso + 'T00:00:00' );
		if ( ! isNaN( d.getTime() ) ) {
			return new Intl.DateTimeFormat( locale(), {
				year: 'numeric',
				month: 'long',
				day: 'numeric',
			} ).format( d );
		}
	} catch ( _e ) {
		// Fall through to the raw ISO date.
	}
	return iso;
};

const { state } = store(
	'wcb-job-form',
	{
		state: {
			// ── Step panel visibility ─────────────────────────────────────────
			get isStep1() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 1;
			},
			get isStep2() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 2;
			},
			get isStep3() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 3;
			},
			get isStep4() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 4;
			},

			// ── Step indicator: active state ──────────────────────────────────
			get step1Active() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 1;
			},
			get step2Active() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 2;
			},
			get step3Active() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 3;
			},
			get step4Active() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 4;
			},

			// ── Step indicator: aria-current (a11y) ───────────────
			// Drives data-wp-bind--aria-current on each step. Returns the
			// token 'step' for the active step and false otherwise, so WP
			// Interactivity sets aria-current="step" on the current step and
			// removes it from the rest, keeping the announced position in
			// sync as the user moves between steps.
			get step1AriaCurrent() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 1 ? 'step' : false;
			},
			get step2AriaCurrent() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 2 ? 'step' : false;
			},
			get step3AriaCurrent() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 3 ? 'step' : false;
			},
			get step4AriaCurrent() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 4 ? 'step' : false;
			},

			// ── Step indicator: done (completed) state ────────────────────────
			get step1Done() {
				const { state } = store( 'wcb-job-form' );
				return state.step > 1;
			},
			get step2Done() {
				const { state } = store( 'wcb-job-form' );
				return state.step > 2;
			},
			get step3Done() {
				const { state } = store( 'wcb-job-form' );
				return state.step > 3;
			},

			// ── Preview computed ──────────────────────────────────────────────
			// Mirrors \WCB\Core\SalaryFormat::format() so the preview card reads
			// exactly like the published job card. Formatted client-side (and
			// only here) because the figures change on every keystroke with no
			// server round-trip; everywhere the value is server-known we use the
			// preformatted `salary_label` from REST instead.
			get salaryDisplay() {
				const { state } = store( 'wcb-job-form' );
				const min       = state.salaryMin ? Number( state.salaryMin ) : 0;
				const max       = state.salaryMax ? Number( state.salaryMax ) : 0;
				if ( ! min && ! max ) {
					return '';
				}
				const symbol = symbolFor( state.currencyCode || 'USD' );
				const suffix = state.salaryType === 'monthly'
					? t( 'salaryPerMonth', '/mo' )
					: state.salaryType === 'hourly'
						? t( 'salaryPerHour', '/hr' )
						: t( 'salaryPerYear', '/yr' );
				const fmt = ( v ) => money( symbol, abbreviate( v ) );
				if ( min && max ) {
					const range = fill(
						fill( t( 'salaryRange', '%1$s–%2$s' ), '%1$s', fmt( min ) ),
						'%2$s',
						fmt( max )
					);
					return range + suffix;
				}
				if ( min ) {
					return fill( t( 'salaryOpenMin', '%s+' ), '%s', fmt( min ) ) + suffix;
				}
				return fill( t( 'salaryUpTo', 'Up to %s' ), '%s', fmt( max ) ) + suffix;
			},

			// ── Preview card conditionals (all use data-wp-class) ─────────────
			get hasCompany() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.companyName;
			},
			get isRemote() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.remote;
			},
			get hasType() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.typeSlug;
			},
			get hasExp() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.expSlug;
			},
			get hasLocation() {
				const { state } = store( 'wcb-job-form' );
				return state.locationSlug === '__custom__'
					? !! ( state.locationCustom || '' ).trim()
					: !! state.locationSlug;
			},
			get locationIsCustom() {
				const { state } = store( 'wcb-job-form' );
				return state.locationSlug === '__custom__';
			},
			get hasCategory() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.categorySlug;
			},
			get hasSalary() {
				const { state } = store( 'wcb-job-form' );
				const min       = state.salaryMin ? Number( state.salaryMin ) : 0;
				const max       = state.salaryMax ? Number( state.salaryMax ) : 0;
				return ! ! ( min || max );
			},
			get hasDeadline() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.deadline;
			},
			get hasApplyUrl() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.applyUrl;
			},
			get hasApplyEmail() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.applyEmail;
			},
			get hasError() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.error;
			},
			get hasValidation() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.validationError;
			},

			// ── Edit mode ─────────────────────────────────────────────────────────
			get isEditing() {
				const { state } = store( 'wcb-job-form' );
				return state.editJobId > 0;
			},
			get submitLabel() {
				const { state } = store( 'wcb-job-form' );
				return state.editJobId > 0
					? t( 'submitLabelUpdate', 'Update Job' )
					: t( 'submitLabelPost', 'Post Job' );
			},
			get jobPending() {
				const { state } = store( 'wcb-job-form' );
				return state.jobStatus === 'pending';
			},

			// ── Credit getters ────────────────────────────────────────────────
			get hasCreditCost() {
				const { state } = store( 'wcb-job-form' );
				return state.creditCost > 0;
			},
			// Banner visibility — true for paid boards AND for free boards,
			// which get their own "free to post" sentence. Keeps the employer
			// informed when switching to a zero-cost board.
			get hasCreditBanner() {
				const { state } = store( 'wcb-job-form' );
				return state.creditCost > 0 || !! state.creditMessage;
			},
			get insufficientCredits() {
				const { state } = store( 'wcb-job-form' );
				return state.creditCost > 0 && state.creditBalance < state.creditCost;
			},
			// The banner sentence is chosen server-side, per board, in render.php:
			// free / insufficient / deduction, each already run through _n() with
			// the REAL credit count and through number_format_i18n(). Both inputs
			// (per-board cost, employer balance) are known at render time and
			// cannot change without a page load, so nothing is re-pluralised here
			// — a `count === 1` pick in JS is only correct in two-form locales.
			get creditMessage() {
				const { state } = store( 'wcb-job-form' );
				const messages  = state.creditMessages || {};
				return messages[ String( state.boardId || 0 ) ] || '';
			},

			// Pre-publish: tell the employer when their listing will expire so
			// the deadline isn't a surprise. Pairs with the read-only deadline
			// field (admin policy, not employer-editable).
			get listingWindowMessage() {
				const { state } = store( 'wcb-job-form' );
				if ( ! state.deadline ) {
					return '';
				}
				return fill(
					t(
						'listingWindow',
						'Listing runs until %1$s. Reopen on the dashboard to extend (counts as a republish).'
					),
					'%1$s',
					formatDate( state.deadline )
				);
			},

			get hasListingWindow() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.deadline;
			},

			// One format string, not "Apply by" + a value node — the date must be
			// able to precede the label in locales that need it.
			get applyByLabel() {
				const { state } = store( 'wcb-job-form' );
				if ( ! state.deadline ) {
					return '';
				}
				return fill( t( 'applyBy', 'Apply by %s' ), '%s', formatDate( state.deadline ) );
			},

			// ── AI generation ─────────────────────────────────────────────
			get aiGenerating() {
				const { state } = store( 'wcb-job-form' );
				return !! state._aiGenerating;
			},

			// ── Preview badge display names (slug → term name via PHP-injected map) ──
			get typeDisplay() {
				const { state } = store( 'wcb-job-form' );
				return state.typeSlug ? ( state.typeNames[ state.typeSlug ] || state.typeSlug ) : '';
			},
			get expDisplay() {
				const { state } = store( 'wcb-job-form' );
				return state.expSlug ? ( state.expNames[ state.expSlug ] || state.expSlug ) : '';
			},
			get locationDisplay() {
				const { state } = store( 'wcb-job-form' );
				if ( state.locationSlug === '__custom__' ) {
					return ( state.locationCustom || '' ).trim();
				}
				return state.locationSlug ? ( state.locationNames[ state.locationSlug ] || state.locationSlug ) : '';
			},
			get categoryDisplay() {
				const { state } = store( 'wcb-job-form' );
				return state.categorySlug ? ( state.categoryNames[ state.categorySlug ] || state.categorySlug ) : '';
			},
		},

		actions: {
			updateField( event ) {
				const { state } = store( 'wcb-job-form' );
				const field     = event.target.dataset.wcbField;
				if ( field ) {
					state[ field ] = event.target.value;
					if ( field === 'title' && state.validationError ) {
						state.validationError = '';
					}
					// When the employer switches boards, re-derive the
					// credit cost AND currency from the seeded per-board
					// maps so the banner ("Posting deducts X credits") and
					// the salary currency dropdown update without a REST
					// round-trip. Maps are keyed by stringified ID.
					if ( field === 'boardId' ) {
						const costMap = state.boardCreditCosts || {};
						const curMap  = state.boardCurrencies || {};
						const key     = String( state.boardId || 0 );
						const cost    = costMap[ key ];
						const cur     = curMap[ key ];
						state.creditCost = Number.isFinite( cost ) ? cost : 0;
						if ( cur ) {
							state.currencyCode = cur;
						}
					}
				}
			},

			updateCustomField( event ) {
				const { state } = store( 'wcb-job-form' );
				const key       = event.target.getAttribute( 'data-wcb-field' );
				if ( ! key ) {
					return;
				}
				const target = event.target;
				let value;
				if ( target.dataset.wcbMulti ) {
					// multiselect — collect every checked box sharing this field key.
					value = Array.from(
						document.querySelectorAll( '[data-wcb-field="' + key + '"][data-wcb-multi]' )
					)
						.filter( ( el ) => el.checked )
						.map( ( el ) => el.value );
				} else if ( target.type === 'checkbox' ) {
					value = target.checked;
				} else {
					value = target.value;
				}
				state.customFields = { ...state.customFields, [ key ]: value };
			},

			toggleRemote() {
				const { state } = store( 'wcb-job-form' );
				state.remote    = ! state.remote;
			},

			* generateDescription() {
				const { state } = store( 'wcb-job-form' );
				if ( state._aiGenerating || ! state.title ) {
					if ( ! state.title ) {
						state.validationError = t( 'errorAiNoTitle', 'Enter a job title first so AI can generate a description.' );
					}
					return;
				}
				state._aiGenerating = true;
				try {
					const response = yield wcbFetch( state.apiBase + '/jobs/ai-description', {
						method: 'POST',
						// LLM generation legitimately takes 10-30s; the 15s wcbFetch
						// default (meant for quick REST calls) aborts mid-generation.
						timeout: 60000,
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': state.nonce,
						},
						body: JSON.stringify( {
							title: state.title,
							company_type: state.companyName || '',
							location: state.locationSlug || 'remote',
						} ),
					} );
					const data = yield response.json();
					if ( data.description ) {
						state.description = data.description;
						// The rich editor only re-reads its source textarea on the
						// wcb:editor:hydrate event (see assets/js/wcb-editor.js). The
						// data-wp-bind--value update alone won't repaint the visible
						// editor, so push the value and fire the hydrate event.
						const source = document.querySelector(
							'.wcb-editor textarea.wcb-editor-source'
						);
						if ( source ) {
							source.value = data.description;
							source.dispatchEvent(
								new Event( 'wcb:editor:hydrate', { bubbles: true } )
							);
						}
					} else if ( data.message ) {
						state.error = data.message;
					}
				} catch {
					state.error = t( 'errorAiFailed', 'Failed to generate description. Please try again.' );
				} finally {
					state._aiGenerating = false;
				}
			},

			nextStep() {
				const { state } = store( 'wcb-job-form' );

				if ( state.step === 1 ) {
					if ( ! state.title.trim() ) {
						state.validationError = t( 'errorTitleRequired', 'Job title is required before you can continue.' );
						return;
					}
					if ( ! state.description.trim() ) {
						state.validationError = t( 'errorDescriptionRequired', 'Job description is required before you can continue.' );
						return;
					}
				}

				state.validationError = '';
				if ( state.step < 4 ) {
					state.step++;
				}
			},

			prevStep() {
				const { state }       = store( 'wcb-job-form' );
				state.validationError = '';
				if ( state.step > 1 ) {
					state.step--;
				}
			},

			* submitJob() {
				const { state } = store( 'wcb-job-form' );

				if ( state.submitting ) {
					return;
				}

				// Honeypot check — bots filling all fields get a fake success response.
				const hpEl = document.getElementById( 'wcb-hp' );
				if ( hpEl && hpEl.value ) {
					state.submitted = true;
					return;
				}

				// Credit gate — block submission if balance is insufficient. The
				// sentence (with its plural-correct credit noun and localised
				// numbers) is resolved per board in render.php; JS only selects.
				if ( state.creditCost > 0 && state.creditBalance < state.creditCost ) {
					const errors = state.creditErrors || {};
					state.error  = errors[ String( state.boardId || 0 ) ]
						|| t( 'errorInsufficientCredits', 'Insufficient credits to post on this board.' );
					return;
				}

				// Optional CAPTCHA token (Turnstile / reCAPTCHA). Empty when provider is 'none'.
				const captchaToken = window.wcbCaptchaGetToken
					? yield window.wcbCaptchaGetToken()
					: '';

				state.submitting = true;
				state.error      = '';

				try {
					// Parse comma-separated tags into a slug array.
					const tagSlugs = state.tags
					? state.tags.split( ',' ).map( ( t ) => t.trim() ).filter( Boolean )
					: [];

					const body = {
						title:           state.title,
						description:     state.description,
						salary_min:      state.salaryMin,
						salary_max:      state.salaryMax,
						salary_currency: state.currencyCode || 'USD',
						salary_type:     state.salaryType || 'yearly',
						remote:          state.remote,
						deadline:        state.deadline,
						apply_url:       state.applyUrl,
						apply_email:     state.applyEmail,
						categories:      state.categorySlug ? [ state.categorySlug ] : [],
						job_types:       state.typeSlug ? [ state.typeSlug ] : [],
						locations:       state.locationSlug && state.locationSlug !== '__custom__'
							? [ state.locationSlug ]
							: [],
						location_custom: state.locationSlug === '__custom__'
							? ( state.locationCustom || '' ).trim()
							: '',
						experience:      state.expSlug ? [ state.expSlug ] : [],
						tags:              tagSlugs,
						board_id:          state.boardId ? Number( state.boardId ) : 0,
						custom_fields:     state.customFields || {},
						hp:                hpEl ? hpEl.value : '',
						wcb_captcha_token: captchaToken,
					};

					const isEdit   = state.editJobId > 0;
				const response = yield wcbFetch(
						isEdit
							? state.apiBase + '/jobs/' + String( state.editJobId )
							: state.apiBase + '/jobs',
						{
							method:  isEdit ? 'PATCH' : 'POST',
							headers: {
								'X-WP-Nonce':   state.nonce,
								'Content-Type': 'application/json',
							},
							body: JSON.stringify( body ),
						}
					);

					if ( ! response.ok ) {
						const err = yield response.json().catch( () => null );
						if ( err && err.code === 'rest_cookie_invalid_nonce' ) {
							state.error = t( 'errorSessionExpired', 'Your session has expired. Please refresh the page and try again.' );
						} else {
							state.error = ( err && err.message )
								? err.message
								: t( 'errorSubmitFailed', 'Job could not be posted. Please try again.' );
						}
						return;
					}

					const data      = yield response.json();
					state.jobUrl    = data.permalink || '';
					state.jobStatus = data.status    || 'publish';
					state.submitted = true;

					// Signal the embedded employer dashboard (if present) to refresh
					// its My Jobs list when the user navigates there. try/catch: the
					// form also runs standalone (shortcode) where that store is absent.
					try {
						const dash = store( 'wcb-employer-dashboard' );
						if ( dash && dash.state ) {
							dash.state._needsJobsRefresh = true;
						}
					} catch {}

					// Auto-reset the success state after 8 seconds so a returning
					// user sees a fresh form instead of a stuck success banner —
					// matches the spec on Basecamp card 9817915492.
					setTimeout( () => {
						const live = store( 'wcb-job-form' );
						if ( live && live.state && live.state.submitted ) {
							if ( typeof live.actions?.resetForm === 'function' ) {
								live.actions.resetForm();
							} else {
								live.state.submitted = false;
							}
						}
					}, 8000 );
				} catch {
					state.error = t( 'errorConnection', 'Connection error. Please check your network and try again.' );
				} finally {
					state.submitting = false;
				}
			},

			resetForm() {
				// Clear success-state flags AND all volatile form values so the
				// user is not editing a copy of their last submission.
				const { state } = store( 'wcb-job-form' );
				state.submitted        = false;
				state.step             = 1;
				state.error            = '';
				state.validationError  = '';
				state.jobUrl           = '';
				state.jobStatus        = '';
				state.editJobId        = 0;
				state.title            = '';
				state.description      = '';
				state.salaryMin        = '';
				state.salaryMax        = '';
				state.deadline         = '';
				state.applyUrl         = '';
				state.applyEmail       = '';
				state.locationSlug     = '';
				state.locationCustom   = '';
				state.typeSlug         = '';
				state.categorySlug     = '';
				state.expSlug          = '';
				state.tags             = '';
				state.remote           = false;
				state.customFields     = {};

				// The rich editor mirrors a source textarea and only repaints on
				// the wcb:editor:hydrate event; clearing state.description alone
				// leaves the just-submitted text visible. Sync the source to empty
				// and fire hydrate (mirrors the AI-description handler above).
				const source = document.querySelector(
					'.wcb-editor textarea.wcb-editor-source'
				);
				if ( source ) {
					source.value = '';
					source.dispatchEvent(
						new Event( 'wcb:editor:hydrate', { bubbles: true } )
					);
				}
			},
		},
	}
);
