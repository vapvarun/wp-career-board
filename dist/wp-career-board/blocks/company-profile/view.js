/**
 * WP Career Board — company-profile block Interactivity API store.
 *
 * Actions:
 *   loadMore — fetch the next page of jobs for this company from /wcb/v1/jobs.
 *
 * @package WP_Career_Board
 */
import { store } from '@wordpress/interactivity';

const { state } = store( 'wcb-company-profile', {
	actions: {
		*loadMore() {
			if ( state.loading ) {
				return;
			}
			state.loading = true;
			state.page++;

			try {
				const url = new URL( state.apiBase );
				url.searchParams.set( 'author', String( state.author ) );
				url.searchParams.set( 'page', String( state.page ) );
				url.searchParams.set( 'per_page', String( state.perPage ) );

				const response = yield fetch( url.toString() );

				if ( ! response.ok ) {
					state.page--;
					return;
				}

				const jobs = yield response.json();
				state.jobs.push( ...jobs );
				state.hasMore = jobs.length === state.perPage;
			} catch {
				state.page--;
			} finally {
				state.loading = false;
			}
		},
	},
} );
