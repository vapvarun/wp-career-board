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
import { store, getElement } from '@wordpress/interactivity';

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

	callbacks: {
		// Toggle the active state of a radio filter by comparing its `value`
		// to the current state field. The "All …" option has an empty value
		// so it activates whenever the filter is cleared. Null-guarded
		// because `getElement().ref` can briefly be null during the first
		// pre-paint pass before the Interactivity runtime wires up the DOM
		// reference.
		isIndustryActive() {
			const el = getElement();
			const ref = el && el.ref;
			if ( ! ref ) {
				return false;
			}
			return ( state.industry || '' ) === ( ref.value || '' );
		},

		isSizeActive() {
			const el = getElement();
			const ref = el && el.ref;
			if ( ! ref ) {
				return false;
			}
			return ( state.size || '' ) === ( ref.value || '' );
		},

		// Hide the "Clear all" button when no filters are active so the
		// affordance only appears when there's something to clear.
		noActiveFilters() {
			return ! state.industry && ! state.size;
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
			// Radio filter inside the sidebar panel. Each radio's value is
			// the industry slug (empty for "All industries"). The change
			// event fires with the freshly-checked input as `target`.
			state.industry = ( event && event.target && event.target.value ) || '';
			wcbFetchCompanies();
		},

		filterSize( event ) {
			state.size = ( event && event.target && event.target.value ) || '';
			wcbFetchCompanies();
		},

		clearFilters() {
			state.industry = '';
			state.size     = '';
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
