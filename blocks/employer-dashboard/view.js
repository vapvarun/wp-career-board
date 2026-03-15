/**
 * WP Career Board — employer-dashboard block Interactivity API store.
 *
 * @package WP_Career_Board
 */
import { store } from '@wordpress/interactivity';

const { state } = store( 'wcb-employer-dashboard', {
	state: {
		get isTabJobs() {
			return state.tab === 'jobs';
		},
		get isTabProfile() {
			return state.tab === 'profile';
		},
		get hasJobs() {
			return ! state.loading && ! state.noCompany && state.jobs.length > 0;
		},
		get noJobs() {
			return ! state.loading && ! state.noCompany && ! state.error && state.jobs.length === 0;
		},
		get totalJobs() {
			return state.jobs.length;
		},
		get publishedJobs() {
			return state.jobs.filter( ( j ) => j.status === 'publish' ).length;
		},
		get totalApps() {
			return state.jobs.reduce( ( sum, j ) => sum + j.appCount, 0 );
		},
	},

	actions: {
		*init() {
			if ( ! state.companyId ) {
				state.noCompany = true;
				return;
			}

			state.loading = true;
			state.error   = '';

			try {
				const url = new URL( state.apiBase + '/employers/' + String( state.companyId ) + '/jobs' );
				url.searchParams.set( 'per_page', '50' );

				const response = yield fetch(
					url.toString(),
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);

				if ( ! response.ok ) {
					state.error = 'Could not load your jobs.';
					return;
				}

				state.jobs = yield response.json();
			} catch {
				state.error = 'Connection error. Please check your network and try again.';
			} finally {
				state.loading = false;
			}
		},

		switchToJobs() {
			state.tab   = 'jobs';
			state.error = '';
		},

		switchToProfile() {
			state.tab   = 'profile';
			state.saved  = false;
			state.error  = '';
		},

		updateField( event ) {
			const field = event.target.dataset.wcbField;
			if ( field ) {
				state[ field ] = event.target.value;
			}
		},

		*saveProfile() {
			if ( state.saving ) {
				return;
			}

			state.saving = true;
			state.saved  = false;
			state.error  = '';

			try {
				const response = yield fetch(
					state.apiBase + '/employers/' + String( state.companyId ),
					{
						method: 'PATCH',
						headers: {
							'X-WP-Nonce':   state.nonce,
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( {
							name:        state.companyName,
							description: state.companyDesc,
							tagline:     state.companyTagline,
							website:     state.companySite,
							industry:    state.companyIndustry,
							size:        state.companySize,
							hq:          state.companyHq,
						} ),
					}
				);

				if ( ! response.ok ) {
					state.error = 'Could not save profile. Please try again.';
					return;
				}

				state.saved = true;
			} catch {
				state.error = 'Connection error. Please check your network and try again.';
			} finally {
				state.saving = false;
			}
		},
	},
} );
