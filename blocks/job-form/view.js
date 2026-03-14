/**
 * WP Career Board — job-form block Interactivity API store.
 *
 * Actions:
 *   updateField  — generic field updater via data-wcb-field attribute.
 *   toggleRemote — flip the remote boolean.
 *   nextStep     — advance step counter (validates step 1 title requirement).
 *   prevStep     — go back one step.
 *   submitJob    — POST job data to /wcb/v1/jobs.
 *
 * State getters:
 *   isStep1 … isStep4 — drive data-wp-show on each step panel.
 *
 * @package WP_Career_Board
 */
import { store } from '@wordpress/interactivity';

store( 'wcb-job-form', {
	state: {
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

			// Basic validation for step 1.
			if ( state.step === 1 && ! state.title.trim() ) {
				return;
			}

			if ( state.step < 4 ) {
				state.step++;
			}
		},

		prevStep() {
			const { state } = store( 'wcb-job-form' );

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

			const body = {
				title:       state.title,
				description: state.description,
				salary_min:  state.salaryMin,
				salary_max:  state.salaryMax,
				remote:      state.remote,
				deadline:    state.deadline,
				categories:  state.categorySlug ? [ state.categorySlug ] : [],
				job_types:   state.typeSlug     ? [ state.typeSlug ]     : [],
				locations:   state.locationSlug ? [ state.locationSlug ] : [],
				experience:  state.expSlug      ? [ state.expSlug ]      : [],
			};

			const response = yield fetch(
				state.apiBase + '/jobs',
				{
					method: 'POST',
					headers: {
						'X-WP-Nonce':   state.nonce,
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( body ),
				}
			);

			if ( ! response.ok ) {
				state.error      = 'Job could not be posted. Please try again.';
				state.submitting = false;
				return;
			}

			const data      = yield response.json();
			state.jobUrl    = data.permalink || '';
			state.submitted = true;
			state.submitting = false;
		},
	},
} );
