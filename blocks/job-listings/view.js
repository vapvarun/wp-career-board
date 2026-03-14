/**
 * WP Career Board — job-listings block Interactivity API store.
 *
 * Actions:
 *   setGrid / setList  — toggle layout without a page reload.
 *   loadMore           — fetch the next page of jobs from /wcb/v1/jobs.
 *   toggleBookmark     — POST to /wcb/v1/jobs/{id}/bookmark and flip context.job.bookmarked.
 *
 * Event listener:
 *   wcb:search  — fired by job-search / job-filters blocks; resets the list
 *                 and re-fetches page 1 with updated search/filter params.
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
		get bookmarkLabel() {
			const ctx = getContext();
			return ctx.job && ctx.job.bookmarked ? 'Remove bookmark' : 'Bookmark job';
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
			url.searchParams.set( 'per_page', String( state.perPage ) );

			// Forward any active search/filter params from the page URL.
			const searchParams = new URLSearchParams( window.location.search );
			for ( const [ key, val ] of searchParams ) {
				url.searchParams.set( key, val );
			}

			const response = yield fetch( url.toString() );

			if ( ! response.ok ) {
				state.page--;
				state.loading = false;
				return;
			}

			const jobs = yield response.json();

			state.jobs.push( ...jobs );
			state.hasMore = jobs.length === state.perPage;
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

			if ( ! response.ok ) {
				return;
			}

			const data  = yield response.json();
			job.bookmarked = data.bookmarked;
		},
	},
} );

// Respond to search / filter changes dispatched by sibling blocks.
document.addEventListener( 'wcb:search', function( event ) {
	const url = new URL( state.apiBase );
	url.searchParams.set( 'page', '1' );
	url.searchParams.set( 'per_page', String( state.perPage ) );

	const detail  = ( event.detail !== null && event.detail !== undefined ) ? event.detail : {};
	const query   = detail.query;
	const filters = detail.filters;

	if ( query ) {
		url.searchParams.set( 'wcb_search', query );
	}

	if ( filters ) {
		Object.keys( filters ).forEach( function( key ) {
			url.searchParams.set( key, filters[ key ] );
		} );
	}

	state.loading = true;
	state.page    = 1;

	fetch( url.toString() )
		.then( function( response ) {
			if ( ! response.ok ) {
				state.loading = false;
				return undefined;
			}
			return response.json();
		} )
		.then( function( jobs ) {
			if ( ! jobs ) {
				return;
			}
			state.jobs    = jobs;
			state.hasMore = jobs.length === state.perPage;
			state.loading = false;
		} )
		.catch( function() {
			state.loading = false;
		} );
} );
