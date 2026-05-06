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

				const data = yield response.json();
				// /wcb/v1/jobs since 1.1.0 returns { jobs, total, has_more }.
				// Fall back to bare-array + per_page heuristic for old proxies,
				// but prefer the authoritative value when available.
				const jobs = Array.isArray( data ) ? data : ( data?.jobs ?? [] );
				const total = Array.isArray( data )
					? parseInt( response.headers.get( 'X-WCB-Total' ) ?? '0', 10 )
					: ( data?.total ?? 0 );
				state.jobs.push( ...jobs );
				if ( ! Array.isArray( data ) && typeof data?.has_more === 'boolean' ) {
					state.hasMore = data.has_more && state.jobs.length < total;
				} else {
					state.hasMore = state.jobs.length < total;
				}
			} catch {
				state.page--;
			} finally {
				state.loading = false;
			}
		},
	},
} );
