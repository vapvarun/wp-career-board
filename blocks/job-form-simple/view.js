/**
 * Job Form Simple block — Interactivity API store.
 *
 * Single-page sibling of blocks/job-form. Same /wcb/v1/jobs endpoint,
 * same body shape, same honeypot + captcha hooks. Drops step navigation,
 * preview, and edit mode by design — keep this store small and focused.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

import { store } from '@wordpress/interactivity';
import { wcbFetch } from '@wcb/fetch';

/**
 * Translated-string reader.
 *
 * view.js ships as a script MODULE (block.json: viewScriptModule), and script
 * modules cannot load JED translation files. Every user-facing string is
 * therefore seeded by render.php under state.i18n and read through t(). The
 * fallback is the English source text, byte-identical to the __() call in
 * render.php, so a missing key degrades to English instead of blank.
 *
 * @param {string} key      Key seeded in render.php's `i18n` array.
 * @param {string} fallback English source text.
 * @return {string} Translated string, or the English fallback.
 */
const t = ( key, fallback ) => ( state.i18n && state.i18n[ key ] ) || fallback;

/**
 * Format an integer against the SITE locale (state.locale, a BCP-47 tag seeded
 * by render.php) — never the browser locale.
 *
 * Number(n).toLocaleString() with no locale argument groups digits per the
 * VISITOR's browser, so a de_DE site viewed from an en-US browser renders
 * "1,000" where it must render "1.000".
 *
 * @param {number|string} value Number to format.
 * @return {string} Locale-formatted number.
 */
const nf = ( value ) => {
	const num = Number( value );
	const safe = Number.isFinite( num ) ? num : 0;
	try {
		return new Intl.NumberFormat( state.locale || undefined ).format( safe );
	} catch ( _e ) {
		// state.locale is SalaryFormat::locale() = str_replace( '_', '-', get_user_locale() ),
		// not a true BCP-47 normalizer, so a WP locale with a variant/suffix (e.g.
		// "de_DE_formal" -> "de-DE-formal") can throw. Retry with the primary language
		// subtag, then a deterministic 'en' — never construct with no locale, which
		// would group digits per the VISITOR's browser instead of the SITE.
		try {
			const primary = String( state.locale || '' ).split( '-' )[ 0 ];
			return new Intl.NumberFormat( primary || 'en' ).format( safe );
		} catch ( _e2 ) {
			return new Intl.NumberFormat( 'en' ).format( safe );
		}
	}
};

const { state, actions } = store( 'wcb-job-form-simple', {
	state: {
		get hasInsufficientCredits() {
			return state.creditCost > 0 && state.creditBalance < state.creditCost;
		},

		get hasCreditCost() {
			return state.creditCost > 0;
		},

		// Banner visibility — the credits system is only in play when this board
		// charges (creditCost > 0) or the employer holds a balance (creditBalance
		// > 0). Sites that don't use credits leave both at 0, so the banner stays
		// hidden instead of showing a "Free to post. Balance: 0." non-message.
		// Do NOT gate on the presence of a seeded i18n string — creditFree is always
		// seeded, which made this a compile-time-constant `true`.
		get hasCreditBanner() {
			return state.creditCost > 0 || state.creditBalance > 0;
		},

		// Dynamic message — numbers come live from state.creditCost /
		// state.creditBalance (seeded server-side). Format strings are
		// pre-translated PHP-side and pushed via wp_interactivity_state
		// so the literal English strings live in the .pot file.
		get creditMessage() {
			const cost    = state.creditCost;
			const balance = state.creditBalance;
			if ( ! cost ) {
				return t( 'creditFree', 'Free to post on this board. Your balance: %s.' )
					.replace( '%s', nf( balance ) );
			}
			// Plural form resolved in PHP by _n() against the real count — see
			// render.php's $wcb_credit_noun. JS never branches on `count === 1`.
			const noun = state.creditNoun || '';
			if ( balance < cost ) {
				return t( 'creditInsufficient', 'This board requires %1$s. Your balance: %2$s. Please purchase more credits.' )
					.replace( '%1$s', noun )
					.replace( '%2$s', nf( balance ) );
			}
			return t( 'creditDeduction', 'Posting deducts %1$s. Balance after: %2$s (currently %3$s).' )
				.replace( '%1$s', noun )
				.replace( '%2$s', nf( balance - cost ) )
				.replace( '%3$s', nf( balance ) );
		},

		get hasListingWindow() {
			return ! ! state.deadline;
		},

		get listingWindowMessage() {
			if ( ! state.deadline ) {
				return '';
			}
			// The deadline is read-only and server-owned, so its human-readable
			// form is pre-rendered by wp_date() against the SITE locale and the
			// site's date_format. Never re-derive it with toLocaleDateString(),
			// which would format against the visitor's BROWSER locale.
			const formatted = state.deadlineLabel || state.deadline;
			return t( 'listingWindow', 'Listing runs until %1$s. Reopen on the dashboard to extend (counts as a republish).' )
				.replace( '%1$s', formatted );
		},

		get locationIsCustom() {
			return state.locationSlug === '__custom__';
		},
	},

	actions: {
		updateField( event ) {
			const key = event.target.getAttribute( 'data-wcb-field' );
			if ( key && key in state ) {
				state[ key ] = event.target.value;
				// When the employer switches boards, re-derive the credit
				// cost AND currency from the seeded per-board maps so the
				// deduction banner and the salary currency dropdown update
				// without a REST round-trip.
				if ( key === 'boardId' ) {
					const costMap  = state.boardCreditCosts || {};
					const nounMap  = state.boardCreditNouns || {};
					const curMap   = state.boardCurrencies || {};
					const boardKey = String( state.boardId || 0 );
					const cost     = Number( costMap[ boardKey ] );
					state.creditCost = Number.isFinite( cost ) ? cost : 0;
					// Swap in the plural form PHP already resolved for this board.
					state.creditNoun = nounMap[ boardKey ] || state.creditNoun;
					if ( curMap[ boardKey ] ) {
						state.currencyCode = curMap[ boardKey ];
					}
				}
			}
		},

		*generateDescription() {
			if ( state._aiGenerating || ! state.title ) {
				if ( ! state.title ) {
					state.error = t( 'errorAiNoTitle', 'Enter a job title first so AI can generate a description.' );
				}
				return;
			}
			state._aiGenerating = true;
			state.error = '';
			try {
				const response = yield wcbFetch( state.apiBase + '/jobs/ai-description', {
					method:  'POST',
					// LLM generation takes 10-30s; the 15s wcbFetch default aborts mid-generation.
					timeout: 60000,
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce':   state.nonce,
					},
					body: JSON.stringify( {
						title:        state.title,
						company_type: state.companyName || '',
						location:     state.locationSlug || 'remote',
					} ),
				} );
				const data = yield response.json();
				if ( data.description ) {
					state.description = data.description;
					// Force the rich editor to repaint from its source textarea.
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
			} catch ( _e ) {
				state.error = t( 'errorAiFailed', 'Failed to generate description. Please try again.' );
			} finally {
				state._aiGenerating = false;
			}
		},

		toggleRemote( event ) {
			state.remote = !! event.target.checked;
		},

		updateCustomField( event ) {
			const key = event.target.getAttribute( 'data-wcb-field' );
			if ( ! key ) return;
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

		* submitJob() {
			if ( state.submitting ) {
				return;
			}

			// Honeypot — bots that fill all fields trigger a fake success.
			const hpEl = document.getElementById( 'wcb-hp-simple' );
			if ( hpEl && hpEl.value ) {
				state.submitted = true;
				return;
			}

			// Required field gate (matches markup `required` attributes).
			if ( ! state.title.trim() ) {
				state.error = t( 'errorTitleRequired', 'Job title is required.' );
				return;
			}
			if ( ! state.description.trim() ) {
				state.error = t( 'errorDescriptionRequired', 'Job description is required.' );
				return;
			}

			// Credit gate.
			if ( state.hasInsufficientCredits ) {
				state.error = t( 'errorInsufficientCredits', 'Insufficient credits. This board requires %1$s but your balance is %2$s.' )
					.replace( '%1$s', state.creditNoun || '' )
					.replace( '%2$s', nf( state.creditBalance ) );
				return;
			}

			// Optional CAPTCHA token (Turnstile / reCAPTCHA via Free antispam module).
			const captchaToken = window.wcbCaptchaGetToken
				? yield window.wcbCaptchaGetToken()
				: '';

			state.submitting = true;
			state.error      = '';

			try {
				const tagSlugs = state.tags
					? state.tags.split( ',' ).map( ( t ) => t.trim() ).filter( Boolean )
					: [];

				const body = {
					title:             state.title,
					description:       state.description,
					salary_min:        state.salaryMin,
					salary_max:        state.salaryMax,
					salary_currency:   state.currencyCode || 'USD',
					salary_type:       state.salaryType || 'yearly',
					remote:            state.remote,
					deadline:          state.deadline,
					apply_url:         state.applyUrl,
					apply_email:       state.applyEmail,
					categories:        state.categorySlug ? [ state.categorySlug ] : [],
					job_types:         state.typeSlug ? [ state.typeSlug ] : [],
					locations:         state.locationSlug ? [ state.locationSlug ] : [],
					experience:        state.expSlug ? [ state.expSlug ] : [],
					tags:              tagSlugs,
					board_id:          state.boardId ? Number( state.boardId ) : 0,
					custom_fields:     state.customFields,
					hp:                hpEl ? hpEl.value : '',
					wcb_captcha_token: captchaToken,
				};

				const response = yield wcbFetch( state.apiBase + '/jobs', {
					method:  'POST',
					headers: {
						'X-WP-Nonce':   state.nonce,
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( body ),
				} );

				if ( ! response.ok ) {
					const err = yield response.json().catch( () => null );
					state.error = ( err && err.message )
						? err.message
						: t( 'errorGeneric', 'Job could not be posted. Please try again.' );
					return;
				}

				const json = yield response.json();
				state.jobUrl    = json && json.permalink ? json.permalink : '';
				state.submitted = true;

				if ( window.wcbCaptchaReset ) {
					window.wcbCaptchaReset();
				}

				// Auto-dismiss the success state after 8 seconds and clear
				// volatile fields so the user gets a fresh form instead of a
				// stuck banner — same UX rule as the multi-step job-form.
				setTimeout( () => {
					if ( ! state.submitted ) return;
					state.submitted   = false;
					state.jobUrl      = '';
					state.error       = '';
					state.title       = '';
					state.description = '';
					state.salaryMin   = '';
					state.salaryMax   = '';
					// state.deadline / state.deadlineLabel are server-owned (readonly
					// input, pre-computed from the wcb_job_default_expiry_days chain and
					// wp_date()-formatted). Leave them intact so the next post keeps a
					// valid deadline and the two stay in sync.
					state.applyUrl    = '';
					state.applyEmail  = '';
					state.locationSlug = '';
					state.typeSlug     = '';
					state.categorySlug = '';
					state.expSlug      = '';
					state.tags         = '';
					state.remote       = false;
					state.customFields = {};
				}, 8000 );
			} catch {
				state.error = t( 'errorConnection', 'Connection error. Please check your network and try again.' );
			} finally {
				state.submitting = false;
			}
		},
	},
} );
