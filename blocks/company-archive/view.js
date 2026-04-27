/**
 * WP Career Board — company-archive block Interactivity API store.
 *
 * Actions:
 *   setGrid / setList    — toggle layout without a page reload.
 *   filterIndustry       — update industry filter and re-fetch from /wcb/v1/companies.
 *   filterSize           — update size filter and re-fetch from /wcb/v1/companies.
 *   loadMore             — fetch the next page of companies.
 *
 * @package WP_Career_Board
 */
import { store } from '@wordpress/interactivity';

const { state } = store( 'wcb-company-archive', {
	state: {
		get isGrid() {
			return state.layout === 'grid';
		},
		get isList() {
			return state.layout === 'list';
		},
		get resultsLabel() {
			const count = state.companies.length;
			return count === 1 ? '1 company found' : count + ' companies found';
		},
		get hasNoCompanies() {
			return ! state.loading && state.companies.length === 0;
		},
	},

	actions: {
		setGrid() {
			state.layout = 'grid';
			localStorage.setItem( 'wcb-company-archive-layout', 'grid' );
		},

		setList() {
			state.layout = 'list';
			localStorage.setItem( 'wcb-company-archive-layout', 'list' );
		},

		filterIndustry( event ) {
			state.industry = event.target.value;
			wcbFetchCompanies();
		},

		filterSize( event ) {
			state.size = event.target.value;
			wcbFetchCompanies();
		},

		*loadMore() {
			if ( state.loading ) {
				return;
			}
			state.loading = true;
			state.page++;

			try {
				const response = yield fetch( wcbBuildUrl( state.page ) );

				if ( ! response.ok ) {
					state.page--;
					return;
				}

				const data = yield response.json();
				const companies = Array.isArray( data ) ? data : ( data?.companies ?? [] );
				const hasMore   = Array.isArray( data ) ? companies.length === state.perPage : !! data?.has_more;
				state.companies.push( ...companies );
				state.hasMore = hasMore;
			} catch {
				state.page--;
			} finally {
				state.loading = false;
			}
		},
	},
} );

// Initialize layout from localStorage
if ( typeof window !== 'undefined' ) {
	const savedLayout = localStorage.getItem( 'wcb-company-archive-layout' );
	if ( savedLayout && [ 'grid', 'list' ].includes( savedLayout ) ) {
		state.layout = savedLayout;
	}
}

/**
 * Build a URL for the /wcb/v1/companies endpoint.
 *
 * @param {number} page Page number to fetch.
 * @return {string}
 */
function wcbBuildUrl( page ) {
	const url = new URL( state.apiBase );
	url.searchParams.set( 'page', String( page ) );
	url.searchParams.set( 'per_page', String( state.perPage ) );
	if ( state.industry ) {
		url.searchParams.set( 'industry', state.industry );
	}
	if ( state.size ) {
		url.searchParams.set( 'size', state.size );
	}
	return url.toString();
}

/**
 * Reset to page 1 and re-fetch companies (used by filter actions).
 */
function wcbFetchCompanies() {
	state.loading   = true;
	state.page      = 1;
	state.companies = [];

	fetch( wcbBuildUrl( 1 ) )
		.then( function( response ) {
			if ( ! response.ok ) {
				state.loading = false;
				return undefined;
			}
			return response.json();
		} )
		.then( function( data ) {
			if ( ! data ) {
				return;
			}
			const companies = Array.isArray( data ) ? data : ( data.companies ?? [] );
			const hasMore   = Array.isArray( data ) ? companies.length === state.perPage : !! data.has_more;
			state.companies = companies;
			state.hasMore   = hasMore;
			state.loading   = false;
		} )
		.catch( function() {
			state.loading = false;
		} );
}
