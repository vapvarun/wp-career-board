/**
 * WP Career Board — job-form block Interactivity API store.
 *
 * Actions:
 *   updateField  — generic field updater via data-wcb-field attribute.
 *   toggleRemote — flip the remote boolean.
 *   nextStep     — advance step; validates title on step 1.
 *   prevStep     — go back one step.
 *   submitJob    — POST job data to /wcb/v1/jobs.
 *
 * State getters:
 *   isStep1 … isStep4        — drive data-wp-show on each step panel.
 *   step1Active … step4Active — drive wcb-step--active CSS class.
 *   step1Done   … step3Done   — drive wcb-step--done CSS class.
 *   salaryDisplay             — formatted salary string for preview.
 *
 * @package WP_Career_Board
 */
import { store } from '@wordpress/interactivity';

store( 'wcb-job-form', {
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
		get salaryDisplay() {
			const { state } = store( 'wcb-job-form' );
			const min = state.salaryMin ? Number( state.salaryMin ) : 0;
			const max = state.salaryMax ? Number( state.salaryMax ) : 0;
			if ( ! min && ! max ) {
				return '';
			}
			const cur = state.currencyCode || 'USD';
			const fmt = ( v ) => v.toLocaleString();
			if ( min && max ) {
				return `${ cur } ${ fmt( min ) } – ${ fmt( max ) }`;
			}
			return `${ cur } ${ fmt( min || max ) }`;
		},
	},

	actions: {
		updateField( event ) {
			const { state } = store( 'wcb-job-form' );
			const field     = event.target.dataset.wcbField;
			if ( field ) {
				state[ field ] = event.target.value;
			}
		},

		toggleRemote() {
			const { state } = store( 'wcb-job-form' );
			state.remote = ! state.remote;
		},

		nextStep() {
			const { state } = store( 'wcb-job-form' );

			if ( state.step === 1 && ! state.title.trim() ) {
				state.validationError = 'Job title is required before you can continue.';
				return;
			}

			state.validationError = '';
			if ( state.step < 4 ) {
				state.step++;
			}
		},

		prevStep() {
			const { state } = store( 'wcb-job-form' );
			state.validationError = '';
			if ( state.step > 1 ) {
				state.step--;
			}
		},

		*submitJob() {
			const { state } = store( 'wcb-job-form' );

			if ( state.submitting ) {
				return;
			}

			state.submitting = true;
			state.error      = '';

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
				remote:          state.remote,
				deadline:        state.deadline,
				apply_url:       state.applyUrl,
				apply_email:     state.applyEmail,
				categories:      state.categorySlug   ? [ state.categorySlug ]   : [],
				job_types:       state.typeSlug        ? [ state.typeSlug ]        : [],
				locations:       state.locationSlug    ? [ state.locationSlug ]    : [],
				experience:      state.expSlug         ? [ state.expSlug ]         : [],
				tags:            tagSlugs,
			};

			const response = yield fetch(
				state.apiBase + '/jobs',
				{
					method:  'POST',
					headers: {
						'X-WP-Nonce':   state.nonce,
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( body ),
				}
			);

			if ( ! response.ok ) {
				const err        = yield response.json().catch( () => null );
				state.error      = ( err && err.message ) ? err.message : 'Job could not be posted. Please try again.';
				state.submitting = false;
				return;
			}

			const data       = yield response.json();
			state.jobUrl     = data.permalink || '';
			state.submitted  = true;
			state.submitting = false;
		},
	},
} );
