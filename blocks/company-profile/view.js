/**
 * WP Career Board — company-profile block Interactivity API store.
 *
 * Actions:
 *   init         — fetch active job listings on mount (via data-wp-init).
 *   toggleEdit   — switch between read and edit views (owner only).
 *   updateField  — generic field updater via data-wcb-field attribute.
 *   saveProfile  — PATCH company profile via /wcb/v1/employers/{id}.
 *
 * @package WP_Career_Board
 */
import { store } from '@wordpress/interactivity';

store( 'wcb-company-profile', {
	actions: {
		*init() {
			const { state } = store( 'wcb-company-profile' );
			state.loading = true;

			const url = new URL( state.apiBase + '/employers/' + String( state.employerId ) + '/jobs' );
			url.searchParams.set( 'per_page', '20' );

			const response = yield fetch(
				url.toString(),
				{ headers: { 'X-WP-Nonce': state.nonce } }
			);

			if ( ! response.ok ) {
				state.loading = false;
				return;
			}

			const jobs    = yield response.json();
			state.jobs    = jobs;
			state.loading = false;
		},

		toggleEdit() {
			const { state } = store( 'wcb-company-profile' );
			state.editing = ! state.editing;
			state.saved   = false;
		},

		updateField( event ) {
			const { state } = store( 'wcb-company-profile' );
			const field     = event.target.dataset.wcbField;
			if ( field ) {
				state[ field ] = event.target.value;
			}
		},

		*saveProfile() {
			const { state } = store( 'wcb-company-profile' );

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

			state.saving  = false;
			state.saved   = true;
			state.error   = '';
			state.editing = false;
		},
	},
} );
