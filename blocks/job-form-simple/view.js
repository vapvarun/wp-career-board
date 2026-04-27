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
	},

	actions: {
		updateField( event ) {
			const key = event.target.getAttribute( 'data-wcb-field' );
			if ( key && key in state ) {
				state[ key ] = event.target.value;
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
					board_id:          state.boardId || 0,
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
			} catch {
				state.error = state.strings.errorConnection;
			} finally {
				state.submitting = false;
			}
		},
	},
} );
