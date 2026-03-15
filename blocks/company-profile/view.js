/**
 * WP Career Board — company-profile block Interactivity API store.
 *
 * Actions:
 *   init  — fetch active job listings on mount (via data-wp-init).
 *
 * @package WP_Career_Board
 */
import { store, getContext } from '@wordpress/interactivity';

const { state } = store( 'wcb-company-profile', {
	state: {
		get isLoaded() {
			return ! state.loading;
		},
		get hasNoJobs() {
			return state.isLoaded && state.jobs.length === 0;
		},
	},

	actions: {
		*init() {
			try {
				const url = new URL( state.apiBase + '/employers/' + String( state.companyId ) + '/jobs' );
				url.searchParams.set( 'per_page', '20' );

				const response = yield fetch(
					url.toString(),
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);

				if ( ! response.ok ) {
					return;
				}

				state.jobs = yield response.json();
			} catch {
				// Loading failed silently — empty jobs list is shown.
			} finally {
				state.loading = false;
			}
		},
	},
} );
