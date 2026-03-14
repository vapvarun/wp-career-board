/**
 * WP Career Board — employer-dashboard block Interactivity API store.
 *
 * Actions:
 *   init           — fetch employer's jobs on mount (via data-wp-init).
 *   switchToJobs   — activate My Jobs tab.
 *   switchToProfile— activate Company Profile tab.
 *   updateField    — generic field updater via data-wcb-field attribute.
 *   saveProfile    — PATCH employer profile via /wcb/v1/employers/{id}.
 *
 * @package WP_Career_Board
 */
import { store } from '@wordpress/interactivity';

store( 'wcb-employer-dashboard', {
	state: {
		get isTabJobs() {
			const { state } = store( 'wcb-employer-dashboard' );
			return state.tab === 'jobs';
		},
		get isTabProfile() {
			const { state } = store( 'wcb-employer-dashboard' );
			return state.tab === 'profile';
		},
	},

	actions: {
		*init() {
			const { state } = store( 'wcb-employer-dashboard' );
			state.loading = true;

			const url = new URL( state.apiBase + '/employers/' + String( state.employerId ) + '/jobs' );
			url.searchParams.set( 'per_page', '50' );

			const response = yield fetch(
				url.toString(),
				{ headers: { 'X-WP-Nonce': state.nonce } }
			);

			if ( ! response.ok ) {
				state.loading = false;
				state.error   = 'Could not load your jobs.';
				return;
			}

			const jobs    = yield response.json();
			state.jobs    = jobs;
			state.loading = false;
		},

		switchToJobs() {
			const { state } = store( 'wcb-employer-dashboard' );
			state.tab = 'jobs';
		},

		switchToProfile() {
			const { state } = store( 'wcb-employer-dashboard' );
			state.tab  = 'profile';
			state.saved = false;
		},

		updateField( event ) {
			const { state } = store( 'wcb-employer-dashboard' );
			const field     = event.target.dataset.wcbField;
			if ( field ) {
				state[ field ] = event.target.value;
			}
		},

		*saveProfile() {
			const { state } = store( 'wcb-employer-dashboard' );

			if ( state.saving ) {
				return;
			}

			state.saving = true;
			state.saved  = false;

			const response = yield fetch(
				state.apiBase + '/employers/' + String( state.employerId ),
				{
					method: 'PATCH',
					headers: {
						'X-WP-Nonce':   state.nonce,
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( {
						company_name:        state.companyName,
						company_description: state.companyDesc,
						company_website:     state.companySite,
					} ),
				}
			);

			if ( ! response.ok ) {
				state.saving = false;
				state.error  = 'Could not save profile. Please try again.';
				return;
			}

			state.saving = false;
			state.saved  = true;
			state.error  = '';
		},
	},
} );
