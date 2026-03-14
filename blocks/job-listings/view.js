/**
 * WP Career Board — job-listings block Interactivity API store.
 *
 * Actions:
 *   setGrid / setList  — toggle layout without a page reload.
 *   loadMore           — fetch the next page of jobs from /wcb/v1/jobs.
 *   toggleBookmark     — POST to /wcb/v1/jobs/{id}/bookmark and flip context.job.bookmarked.
 *
 * @package WP_Career_Board
 */
import { store, getContext } from '@wordpress/interactivity';

const { state } = store( 'wcb-job-listings', {
	state: {
		get isGrid() {
			return state.layout === 'grid';
		},
		get isList() {
			return state.layout === 'list';
		},
	},

	actions: {
		setGrid() {
			state.layout = 'grid';
		},

		setList() {
			state.layout = 'list';
		},

		*loadMore() {
			if ( state.loading ) {
				return;
			}
			state.loading = true;
			state.page++;

			const url = new URL( state.apiBase );
			url.searchParams.set( 'page', String( state.page ) );
			url.searchParams.set( 'per_page', '20' );

			// Forward any active search/filter params from the page URL.
			const searchParams = new URLSearchParams( window.location.search );
			for ( const [ key, val ] of searchParams ) {
				url.searchParams.set( key, val );
			}

			const response = yield fetch( url.toString() );
			const jobs     = yield response.json();

			state.jobs.push( ...jobs );
			state.hasMore = jobs.length === 20;
			state.loading = false;
		},

		*toggleBookmark() {
			const ctx = getContext();
			const job = ctx.job;

			const response = yield fetch(
				state.apiBase + '/' + String( job.id ) + '/bookmark',
				{
					method: 'POST',
					headers: {
						'X-WP-Nonce': state.nonce,
						'Content-Type': 'application/json',
					},
				}
			);
			const data     = yield response.json();
			job.bookmarked = data.bookmarked;
		},
	},
} );
