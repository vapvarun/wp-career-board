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

const { state, actions } = store( 'wcb-job-form-simple', {
	state: {
		get hasInsufficientCredits() {
			return state.creditCost > 0 && state.creditBalance < state.creditCost;
		},

		get hasCreditCost() {
			return state.creditCost > 0;
		},

		// Dynamic message — numbers come live from state.creditCost /
		// state.creditBalance (seeded server-side via SDK). Templates
		// are pre-translated PHP-side and pushed via wp_interactivity_state
		// so the literal English strings live in the .pot file.
		get creditMessage() {
			const cost    = state.creditCost;
			const balance = state.creditBalance;
			if ( ! cost ) {
				return '';
			}
			if ( balance < cost ) {
				return ( state.creditInsufficientTemplate || '' )
					.replace( '%1$d', cost )
					.replace( '%2$d', balance );
			}
			const noun = 1 === cost
				? ( state.creditNounSingular || '' )
				: ( state.creditNounPlural || '' );
			const balanceAfter = balance - cost;
			return ( state.creditDeductionTemplate || '' )
				.replace( '%1$s', noun.replace( '%d', cost ) )
				.replace( '%2$d', balanceAfter )
				.replace( '%3$d', balance );
		},

		get hasListingWindow() {
			return ! ! state.deadline;
		},

		get listingWindowMessage() {
			if ( ! state.deadline ) {
				return '';
			}
			let formatted = state.deadline;
			try {
				const d = new Date( state.deadline + 'T00:00:00' );
				if ( ! isNaN( d.getTime() ) ) {
					formatted = d.toLocaleDateString( undefined, { year: 'numeric', month: 'long', day: 'numeric' } );
				}
			} catch ( _e ) {
				// Fall back to the raw ISO date.
			}
			return `Listing runs until ${ formatted }. Reopen on the dashboard to extend (counts as a republish).`;
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
			}
		},

		*generateDescription() {
			if ( state._aiGenerating || ! state.title ) {
				if ( ! state.title ) {
					state.error = 'Enter a job title first so AI can generate a description.';
				}
				return;
			}
			state._aiGenerating = true;
			state.error = '';
			try {
				const response = yield fetch( state.apiBase + '/jobs/ai-description', {
					method:  'POST',
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
				} else if ( data.message ) {
					state.error = data.message;
				}
			} catch ( _e ) {
				state.error = 'Failed to generate description. Please try again.';
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
			state.customFields = { ...state.customFields, [ key ]: event.target.value };
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
				state.error = state.strings.errorRequired || 'Job title is required.';
				return;
			}
			if ( ! state.description.trim() ) {
				state.error = state.strings.errorRequired || 'Job description is required.';
				return;
			}

			// Credit gate.
			if ( state.hasInsufficientCredits ) {
				state.error = `Insufficient credits. This board requires ${ state.creditCost } credits but your balance is ${ state.creditBalance }.`;
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

				const response = yield fetch( state.apiBase + '/jobs', {
					method:  'POST',
					headers: {
						'X-WP-Nonce':   state.nonce,
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( body ),
				} );

				if ( ! response.ok ) {
					const err = yield response.json().catch( () => null );
					state.error = ( err && err.message ) ? err.message : state.strings.errorGeneric;
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
					state.deadline    = '';
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
				state.error = state.strings.errorConnection;
			} finally {
				state.submitting = false;
			}
		},
	},
} );
