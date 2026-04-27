/**
 * Job Listings block — Interactivity API store.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

import { store, getContext } from '@wordpress/interactivity';

let searchDebounceTimer = null;

/**
 * Compact salary formatter — 60000 → "$60k", 1500000 → "$1.5M".
 * Lives outside the store so getters stay pure.
 *
 * @param {number} value Salary in base currency units.
 * @return {string}
 */
function wcbFormatSalaryShort( value ) {
	const n = Number( value ) || 0;
	if ( n <= 0 ) {
		return '';
	}
	if ( n >= 1_000_000 ) {
		return '$' + ( n / 1_000_000 ).toFixed( 1 ).replace( /\.0$/, '' ) + 'M';
	}
	if ( n >= 1_000 ) {
		return '$' + Math.round( n / 1_000 ) + 'k';
	}
	return '$' + n;
}

const { state, actions } = store( 'wcb-job-listings', {
	state: {
		// ── Derived: layout ──────────────────────────────────────────
		get isGrid() {
			return state.layout === 'grid';
		},
		get isList() {
			return state.layout === 'list';
		},

		// ── Derived: filter state ─────────────────────────────────────
		get noActiveFilters() {
			return Object.keys( state.activeFilters ).length === 0;
		},
		get hasActiveFilters() {
			return Object.keys( state.activeFilters ).length > 0;
		},

		/**
		 * Loop-context getter — ONLY valid inside data-wp-each--chip on
		 * filterOptions.types. Returns true when this chip's typeSlug is active.
		 */
		get isTypeActive() {
			const ctx = getContext();
			return !! state.activeFilters[ 'type_' + ctx.typeSlug ];
		},

		/**
		 * Loop-context getter — ONLY valid inside data-wp-each--chip on
		 * filterOptions.experiences. Returns true when this chip's expSlug is active.
		 */
		get isExpActive() {
			const ctx = getContext();
			return !! state.activeFilters[ 'exp_' + ctx.expSlug ];
		},

		get isRemoteActive() {
			return !! state.activeFilters.remote;
		},

		get isBoardActive() {
			const ctx = getContext();
			return !! state.activeFilters[ 'board_' + ctx.boardId ];
		},

		get isSalaryActive() {
			return !! ( state.activeFilters.salary_min || state.activeFilters.salary_max );
		},

		get salaryMinDisplay() {
			return state.salaryMin > 0 ? wcbFormatSalaryShort( state.salaryMin ) : ( state.strings.anyLabel || 'Any' );
		},

		get salaryMaxDisplay() {
			return state.salaryMax > 0 ? wcbFormatSalaryShort( state.salaryMax ) : ( state.strings.anyLabel || 'Any' );
		},

		get salaryChipLabel() {
			const min = state.salaryMin > 0 ? wcbFormatSalaryShort( state.salaryMin ) : '';
			const max = state.salaryMax > 0 ? wcbFormatSalaryShort( state.salaryMax ) : '';
			const fallback = state.strings.salaryChipDefault || 'Salary';
			if ( ! min && ! max ) {
				return fallback;
			}
			if ( min && max ) {
				return min + '–' + max;
			}
			return min ? min + '+' : '≤' + max;
		},

		/** Array of { key, label } for active filter pills. */
		get activeFilterChips() {
			return Object.entries( state.activeFilters ).map( ( [ key, value ] ) => {
				let label = value;
				if ( key.startsWith( 'type_' ) ) {
					const slug = key.slice( 5 );
					const match = state.filterOptions.types.find( ( t ) => t.slug === slug );
					label = match ? match.name : slug;
				} else if ( key.startsWith( 'exp_' ) ) {
					const slug = key.slice( 4 );
					const match = state.filterOptions.experiences.find( ( e ) => e.slug === slug );
					label = match ? match.name : slug;
				} else if ( key.startsWith( 'board_' ) ) {
					const id = parseInt( key.slice( 6 ), 10 );
					const match = ( state.filterOptions.boards || [] ).find( ( b ) => b.id === id );
					label = match ? match.name : value;
				} else if ( key === 'salary_min' ) {
					label = wcbFormatSalaryShort( parseInt( value, 10 ) ) + '+';
				} else if ( key === 'salary_max' ) {
					label = '≤' + wcbFormatSalaryShort( parseInt( value, 10 ) );
				}
				return { key, label };
			} );
		},

		get resultsLabel() {
			const shown = state.jobs.length;
			const total = state.totalCount;
			if ( shown >= total ) {
				return total === 1
					? state.strings.jobCountSingle
					: state.strings.jobCountPlural.replace( '%d', total );
			}
			return state.strings.jobCountOf
				.replace( '%1$d', shown )
				.replace( '%2$d', total );
		},

		// ── Derived: job list ─────────────────────────────────────────
		get hasNoJobs() {
			return ! state.loading && state.jobs.length === 0;
		},

		// ── Derived: bookmark ─────────────────────────────────────────
		get bookmarkLabel() {
			const ctx = getContext();
			return ctx.job?.bookmarked
				? state.strings.bookmarkRemove
				: state.strings.bookmarkAdd;
		},
	},

	actions: {
		// ── Save search as alert ──────────────────────────────────────
		*saveSearchAlert() {
			if ( state.alertSaved || state.alertSaving ) {
				return;
			}

			state.alertSaving = true;

			const filters = {};
			Object.keys( state.activeFilters ).forEach( ( key ) => {
				if ( key.startsWith( 'type_' ) ) {
					filters.type = key.replace( 'type_', '' );
				} else if ( key.startsWith( 'exp_' ) ) {
					filters.experience = key.replace( 'exp_', '' );
				}
			} );

			try {
				const response = yield fetch(
					state.apiBase.replace( '/jobs', '/alerts' ),
					{
						method:  'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce':   state.nonce,
						},
						body: JSON.stringify( {
							search_query: state.searchQuery || '',
							filters,
							frequency:    'daily',
						} ),
					}
				);

				if ( response.ok ) {
					state.alertSaved = true;
				}
			} catch {
				// Silent failure — button stays enabled.
			} finally {
				state.alertSaving = false;
			}
		},

		// ── Layout toggle ─────────────────────────────────────────────
		setGridLayout() {
			state.layout = 'grid';
			try { localStorage.setItem( 'wcb_layout', 'grid' ); } catch {}
		},
		setListLayout() {
			state.layout = 'list';
			try { localStorage.setItem( 'wcb_layout', 'list' ); } catch {}
		},

		// ── Search ────────────────────────────────────────────────────
		updateSearch( event ) {
			state.searchQuery = event.target.value;
			clearTimeout( searchDebounceTimer );
			searchDebounceTimer = setTimeout( () => {
				store( 'wcb-job-listings' ).actions.applyFilters();
			}, 400 );
		},

		// ── Sort ──────────────────────────────────────────────────────
		* changeSort( event ) {
			state.sortBy = event.target.value;
			yield actions.applyFilters();
		},

		// ── Type chip ─────────────────────────────────────────────────
		* toggleTypeChip() {
			const ctx = getContext();
			const key = 'type_' + ctx.typeSlug;
			if ( state.activeFilters[ key ] ) {
				const next = { ...state.activeFilters };
				delete next[ key ];
				state.activeFilters = next;
			} else {
				state.activeFilters = {
					...state.activeFilters,
					[ key ]: ctx.typeSlug,
				};
			}
			yield actions.applyFilters();
		},

		// ── Experience chip ───────────────────────────────────────────
		* toggleExpChip() {
			const ctx = getContext();
			const key = 'exp_' + ctx.expSlug;
			if ( state.activeFilters[ key ] ) {
				const next = { ...state.activeFilters };
				delete next[ key ];
				state.activeFilters = next;
			} else {
				state.activeFilters = {
					...state.activeFilters,
					[ key ]: ctx.expSlug,
				};
			}
			yield actions.applyFilters();
		},

		// ── Remote toggle ─────────────────────────────────────────────
		* toggleRemote() {
			if ( state.activeFilters.remote ) {
				const next = { ...state.activeFilters };
				delete next.remote;
				state.activeFilters = next;
			} else {
				state.activeFilters = { ...state.activeFilters, remote: '1' };
			}
			yield actions.applyFilters();
		},

		// ── Board chip ────────────────────────────────────────────────
		* toggleBoardChip() {
			const ctx = getContext();
			const key = 'board_' + ctx.boardId;
			if ( state.activeFilters[ key ] ) {
				const next = { ...state.activeFilters };
				delete next[ key ];
				state.activeFilters = next;
			} else {
				state.activeFilters = {
					...state.activeFilters,
					[ key ]: String( ctx.boardId ),
				};
			}
			yield actions.applyFilters();
		},

		// ── Remove single filter pill ─────────────────────────────────
		* removeFilter() {
			const ctx = getContext();
			const key = ctx.chip?.key;
			if ( ! key ) return;
			const next = { ...state.activeFilters };
			delete next[ key ];
			state.activeFilters = next;
			yield actions.applyFilters();
		},

		// ── Salary slider — preview shows live, change commits ────────
		previewSalaryMin( event ) {
			state.salaryMin = parseInt( event.target.value, 10 ) || 0;
			// Keep max ≥ min so the popover never shows an inverted range.
			if ( state.salaryMax > 0 && state.salaryMin > state.salaryMax ) {
				state.salaryMax = state.salaryMin;
			}
		},

		previewSalaryMax( event ) {
			state.salaryMax = parseInt( event.target.value, 10 ) || 0;
		},

		* updateSalaryMin( event ) {
			state.salaryMin = parseInt( event.target.value, 10 ) || 0;
			if ( state.salaryMin > 0 ) {
				state.activeFilters.salary_min = String( state.salaryMin );
			} else {
				delete state.activeFilters.salary_min;
			}
			yield actions.applyFilters();
		},

		* updateSalaryMax( event ) {
			state.salaryMax = parseInt( event.target.value, 10 ) || 0;
			if ( state.salaryMax > 0 ) {
				state.activeFilters.salary_max = String( state.salaryMax );
			} else {
				delete state.activeFilters.salary_max;
			}
			yield actions.applyFilters();
		},

		* resetSalary() {
			state.salaryMin = 0;
			state.salaryMax = 0;
			delete state.activeFilters.salary_min;
			delete state.activeFilters.salary_max;
			yield actions.applyFilters();
		},

		// ── Clear all filters ─────────────────────────────────────────
		* clearFilters() {
			state.activeFilters = {};
			state.searchQuery = '';
			state.salaryMin = 0;
			state.salaryMax = 0;
			yield actions.applyFilters();
		},

		// ── Apply filters (reset to page 1) ───────────────────────────
		* applyFilters() {
			state.page = 1;
			yield actions.fetchJobs();
		},

		// ── Load more (append next page) ──────────────────────────────
		* loadMore() {
			if ( ! state.hasMore || state.loading ) return;
			state.page += 1;
			yield actions.fetchJobs();
		},

		// ── Core fetch ────────────────────────────────────────────────
		* fetchJobs() {
			state.loading = true;

			const url = new URL( state.apiBase );
			url.searchParams.set( 'per_page', state.perPage );
			url.searchParams.set( 'page', state.page );

			if ( state.searchQuery ) {
				url.searchParams.set( 'search', state.searchQuery );
			}

			if ( state.authorId ) {
				url.searchParams.set( 'author', state.authorId );
			}

			// Sort
			if ( state.sortBy === 'date_asc' ) {
				url.searchParams.set( 'orderby', 'date' );
				url.searchParams.set( 'order', 'ASC' );
			} else {
				url.searchParams.set( 'orderby', 'date' );
				url.searchParams.set( 'order', 'DESC' );
			}

			// Active filters — handles both in-block chip keys (type_*, exp_*) and
			// external filter block keys (wcb_category, wcb_location, etc.).
			for ( const [ key, value ] of Object.entries( state.activeFilters ) ) {
				if ( key.startsWith( 'type_' ) ) {
					url.searchParams.append( 'type', value );
				} else if ( key.startsWith( 'exp_' ) ) {
					url.searchParams.append( 'experience', value );
				} else if ( key === 'remote' || key === 'wcb_remote' ) {
					url.searchParams.set( 'remote', '1' );
				} else if ( key === 'wcb_category' ) {
					url.searchParams.set( 'category', value );
				} else if ( key === 'wcb_location' ) {
					url.searchParams.set( 'location', value );
				} else if ( key === 'wcb_experience' ) {
					url.searchParams.set( 'experience', value );
				} else if ( key === 'wcb_job_type' ) {
					url.searchParams.set( 'type', value );
				} else if ( key === 'salary_min' && value ) {
					url.searchParams.set( 'salary_min', value );
				} else if ( key === 'salary_max' && value ) {
					url.searchParams.set( 'salary_max', value );
				} else if ( key.startsWith( 'board_' ) ) {
					url.searchParams.set( 'board', value );
				} else if ( key.startsWith( 'meta_' ) && value ) {
					// Forward as ?meta_<key>=<value>; REST endpoint validates against allowlist.
					url.searchParams.set( key, value );
				}
			}

			try {
				const response = yield fetch( url.toString(), {
					headers: { 'X-WP-Nonce': state.nonce },
				} );

				const data = yield response.json();
				// Envelope shape since 1.1.0: { jobs, total, pages, has_more }.
				// Fall back to bare-array + header for any external reverse proxy still serving the old shape.
				const jobs  = Array.isArray( data ) ? data : ( data?.jobs ?? [] );
				const total = Array.isArray( data )
					? parseInt( response.headers.get( 'X-WCB-Total' ) ?? '0', 10 )
					: ( data?.total ?? jobs.length );
				const hasMore = Array.isArray( data )
					? ( ( ( state.page * jobs.length ) || jobs.length ) < total )
					: !! data?.has_more;

				state.totalCount = total;
				if ( state.page === 1 ) {
					state.jobs = jobs;
				} else {
					state.jobs = [ ...state.jobs, ...jobs ];
				}
				state.hasMore = hasMore && state.jobs.length < total;
			} finally {
				state.loading = false;
			}
		},

		// ── Bookmark ──────────────────────────────────────────────────
		* toggleBookmark() {
			const ctx = getContext();
			const jobId = ctx.job.id;
			const wasBookmarked = ctx.job.bookmarked;

			ctx.job.bookmarked = ! wasBookmarked;

			const response = yield fetch( state.apiBase + '/' + jobId + '/bookmark', {
				method: 'POST',
				headers: { 'X-WP-Nonce': state.nonce },
			} );

			if ( ! response.ok ) {
				ctx.job.bookmarked = wasBookmarked;
			}
		},
	},

	callbacks: {
		init() {
			try {
				const saved = localStorage.getItem( 'wcb_layout' );
				if ( saved === 'grid' || saved === 'list' ) {
					state.layout = saved;
				}
			} catch {}
			// Hydrate search state from URL so reloads and shared links survive.
			// Mirrors the ?wcb_search=… param written by job-search/view.js.
			// Only refetch when a value was actually present, to avoid an
			// unnecessary REST round-trip on a clean page load (the SSR
			// already rendered the unfiltered first page).
			try {
				const urlParams = new URLSearchParams( window.location.search );
				const initialQuery = urlParams.get( 'wcb_search' );
				if ( initialQuery !== null && initialQuery !== '' ) {
					state.searchQuery = initialQuery;
					store( 'wcb-job-listings' ).actions.applyFilters();
				}
			} catch {}

			// `wcb:search` event contract (dispatched by job-search and
			// job-filters blocks): { detail: { query: string, filters: object } }
			document.addEventListener( 'wcb:search', ( event ) => {
				const params = event.detail ?? {};
				if ( params.query !== undefined ) {
					state.searchQuery = params.query;
				}
				// Merge external filter block values (wcb_category, wcb_location,
				// wcb_experience, salary_min, salary_max, remote) into activeFilters.
				if ( params.filters && typeof params.filters === 'object' ) {
					const merged = Object.assign( {}, state.activeFilters );
					for ( const [ key, value ] of Object.entries( params.filters ) ) {
						if ( value ) {
							merged[ key ] = String( value );
						} else {
							delete merged[ key ];
						}
					}
					state.activeFilters = merged;
				}
				store( 'wcb-job-listings' ).actions.applyFilters();
			} );
		},
	},
} );
