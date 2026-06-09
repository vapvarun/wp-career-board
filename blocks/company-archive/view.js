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
import { store, getElement, getContext } from '@wordpress/interactivity';
import { wcbFetch } from '@wcb/fetch';

// Module-scoped debounce timer for the search input. Declared before the
// store() call so the closure captured by `actions.updateSearch` is bound
// to the same identifier across re-fires.
let wcbSearchTimer = null;

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
		// Multi-select active-state lookups. Each checkbox carries a
		// `data-wp-context` payload with its industry / size slug; the
		// callback reads that and checks whether the slug is present in
		// the active array. Mirrors the multi-select pattern Find Jobs
		// uses for type / experience / board filters.
		isIndustryActive() {
			const { industrySlug } = getContext() || {};
			if ( ! industrySlug ) {
				return false;
			}
			return state.industries.includes( industrySlug );
		},

		isSizeActive() {
			const { sizeSlug } = getContext() || {};
			if ( ! sizeSlug ) {
				return false;
			}
			return state.sizes.includes( sizeSlug );
		},

		// Hide the "Clear all" button when no filters are active so the
		// affordance only appears when there's something to clear.
		noActiveFilters() {
			return state.industries.length === 0 && state.sizes.length === 0 && ! state.searchQuery;
		},
	},

	actions: {
		setGrid() {
			state.layout = 'grid';
			localStorage.setItem( 'wcb_archive_layout', 'grid' );
		},

		setList() {
			state.layout = 'list';
			localStorage.setItem( 'wcb_archive_layout', 'list' );
		},

		// Multi-select toggle - flips the slug in/out of the active array.
		// Reads the slug from `data-wp-context` instead of `event.target.value`
		// so the same handler can serve both lists without per-checkbox
		// JSON encoding of the value attribute.
		toggleIndustry() {
			const { industrySlug } = getContext() || {};
			if ( ! industrySlug ) {
				return;
			}
			const idx = state.industries.indexOf( industrySlug );
			if ( idx > -1 ) {
				state.industries = state.industries.filter( function( s ) { return s !== industrySlug; } );
			} else {
				state.industries = [ ...state.industries, industrySlug ];
			}
			wcbFetchCompanies();
		},

		toggleSize() {
			const { sizeSlug } = getContext() || {};
			if ( ! sizeSlug ) {
				return;
			}
			const idx = state.sizes.indexOf( sizeSlug );
			if ( idx > -1 ) {
				state.sizes = state.sizes.filter( function( s ) { return s !== sizeSlug; } );
			} else {
				state.sizes = [ ...state.sizes, sizeSlug ];
			}
			wcbFetchCompanies();
		},

		clearFilters() {
			state.industries  = [];
			state.sizes       = [];
			state.searchQuery = '';
			wcbFetchCompanies();
		},

		// Toggle bookmark on a company card. Mirrors the Jobs pattern in
		// job-listings/view.js - optimistic UI update, rollback on REST
		// failure. Anyone logged in can save any company; the REST gate
		// is `is_user_logged_in()`.
		*toggleBookmark( event ) {
			if ( event ) {
				event.preventDefault();
				event.stopPropagation();
			}
			const ctx = getContext();
			if ( ! ctx?.company ) {
				return;
			}
			const wasBookmarked = !! ctx.company.bookmarked;
			ctx.company.bookmarked = ! wasBookmarked;

			const nonce = state.restNonce || ( window.wpApiSettings && window.wpApiSettings.nonce ) || '';
			const url   = state.apiBase + '/' + ctx.company.id + '/bookmark';

			try {
				const response = yield wcbFetch( url, {
					method:      'POST',
					credentials: 'same-origin',
					headers:     {
						'Content-Type': 'application/json',
						'X-WP-Nonce':   nonce,
					},
				} );
				if ( ! response.ok ) {
					ctx.company.bookmarked = wasBookmarked;
				}
			} catch {
				ctx.company.bookmarked = wasBookmarked;
			}
		},

		// Debounced search input - matches the 250ms debounce used in
		// job-listings/view.js so all three archives feel consistent.
		updateSearch( event ) {
			state.searchQuery = ( event && event.target && event.target.value ) || '';
			if ( wcbSearchTimer ) {
				clearTimeout( wcbSearchTimer );
			}
			wcbSearchTimer = setTimeout( wcbFetchCompanies, 250 );
		},

		// Sort dropdown - mirrors jobs + resumes. Resets to page 1 and
		// re-fetches so newest/oldest order is reflected immediately.
		changeSort( event ) {
			const value = ( event && event.target && event.target.value ) || 'date_desc';
			state.sortBy = value;
			wcbFetchCompanies();
		},

		*loadMore() {
			if ( state.loading ) {
				return;
			}
			state.loading = true;
			state.page++;

			try {
				const response = yield wcbFetch( wcbBuildUrl( state.page ) );

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

// Initialize layout from localStorage. The unified `wcb_archive_layout`
// key is shared across Jobs, Companies, Find Candidates so a user's
// list/grid preference syncs across the 3 archives. Migration: fall
// back to the legacy per-archive key for users who set their preference
// before 1.2.0.
if ( typeof window !== 'undefined' ) {
	let savedLayout = localStorage.getItem( 'wcb_archive_layout' );
	if ( ! savedLayout || ! [ 'grid', 'list' ].includes( savedLayout ) ) {
		const legacy = localStorage.getItem( 'wcb-company-archive-layout' );
		if ( legacy === 'grid' || legacy === 'list' ) {
			savedLayout = legacy;
			localStorage.setItem( 'wcb_archive_layout', legacy );
		}
	}
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
	// Multi-select: send each selected slug as `industry[]=slug` so the
	// REST handler receives an array (PHP `$request->get_param('industry')`
	// resolves to an array when the same key appears multiple times).
	if ( state.industries && state.industries.length ) {
		state.industries.forEach( function( slug ) {
			url.searchParams.append( 'industry[]', slug );
		} );
	}
	if ( state.sizes && state.sizes.length ) {
		state.sizes.forEach( function( slug ) {
			url.searchParams.append( 'size[]', slug );
		} );
	}
	if ( state.searchQuery ) {
		url.searchParams.set( 'search', state.searchQuery );
	}
	// Sort: REST endpoint accepts orderby + order (date | ASC/DESC).
	// Default `date_desc` matches the SSR-painted first page so the
	// initial UI doesn't shuffle on the first client-side fetch.
	if ( state.sortBy === 'date_asc' ) {
		url.searchParams.set( 'orderby', 'date' );
		url.searchParams.set( 'order', 'ASC' );
	} else {
		url.searchParams.set( 'orderby', 'date' );
		url.searchParams.set( 'order', 'DESC' );
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

	wcbFetch( wcbBuildUrl( 1 ) )
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
