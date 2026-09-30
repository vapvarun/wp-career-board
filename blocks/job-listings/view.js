/**
 * Job Listings block — Interactivity API store.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

import { store, getContext } from '@wordpress/interactivity';
import { wcbFetch } from '@wcb/fetch';

let searchDebounceTimer = null;

/**
 * Translated-string reader.
 *
 * view.js ships as a script module, and script modules cannot load JED
 * translation files — `wp_set_script_module_translations()` only lands in
 * WP 7.0 and this plugin's floor is 6.9. The ONLY way a translated string
 * reaches this file is render.php seeding it under `state.i18n`, so every
 * user-facing string must be read through this helper. The fallback is the
 * exact English source text of the matching `__()` call in render.php.
 *
 * @param {string} key      Key in the `i18n` array seeded by render.php.
 * @param {string} fallback English source text, identical to the PHP __() call.
 * @return {string}
 */
const t = ( key, fallback ) => ( state.i18n && state.i18n[ key ] ) || fallback;

/**
 * Substitute a printf-style placeholder without letting `$` sequences in the
 * replacement (currency symbols such as "$60k") be read as capture-group refs.
 *
 * @param {string} template    Format string, e.g. "%1$s+".
 * @param {string} placeholder Placeholder token, e.g. "%1$s".
 * @param {string} value       Literal replacement value.
 * @return {string}
 */
function wcbFill( template, placeholder, value ) {
	return String( template ).replace( placeholder, () => String( value ) );
}

/**
 * Format a number against the SITE locale, not the browser's.
 *
 * `Number#toLocaleString()` and a bare `new Intl.NumberFormat()` both resolve
 * against the visitor's browser locale, so a de_DE site viewed from an en-US
 * browser rendered "1,000" where it must render "1.000". `state.locale` is the
 * site locale as a BCP-47 tag, seeded at the root of the Interactivity state by
 * render.php (\WCB\Core\SalaryFormat::locale()).
 *
 * @param {number} value Raw number.
 * @return {string} Localised digits.
 */
function wcbNumber( value ) {
	const n = Number( value ) || 0;
	try {
		return new Intl.NumberFormat( state.locale || undefined ).format( n );
	} catch {
		// A malformed locale tag must not take the whole listing down — and a
		// bare `new Intl.NumberFormat()` would silently group against the
		// browser locale, the exact drift this helper exists to prevent. Fall
		// back to the ungrouped, locale-neutral digit string instead.
		return String( n );
	}
}

/**
 * Combine a currency symbol with an already-localised amount.
 *
 * Never `symbol + amount`: fr_FR / de_DE / sv_SE place the symbol AFTER the
 * figure. `moneyFormat` is a translatable format string ("%1$s%2$s") whose
 * numbered placeholders a translator reorders to "%2$s %1$s".
 *
 * @param {string} symbol Currency symbol.
 * @param {string} amount Localised amount.
 * @return {string}
 */
function wcbMoney( symbol, amount ) {
	return wcbFill(
		wcbFill( t( 'moneyFormat', '%1$s%2$s' ), '%1$s', symbol ),
		'%2$s',
		amount
	);
}

/**
 * Compact salary formatter — 60000 → "$60k", 1500000 → "$1.5M".
 * Lives outside the store so getters stay pure.
 *
 * Mirrors \WCB\Core\SalaryFormat::abbreviate() + ::money(): the "k" / "M" unit
 * is glued to the localised digits, then the currency symbol is applied through
 * the reorderable `moneyFormat` string. Used ONLY by the salary sliders + their
 * chips, whose values change with no server round trip. Every other money string
 * in this block (each card's `salary_label`) is pre-formatted server-side.
 *
 * @param {number} value  Salary in base currency units.
 * @param {string} symbol Currency symbol seeded by render.php.
 * @return {string}
 */
function wcbFormatSalaryShort( value, symbol ) {
	const n = Number( value ) || 0;
	const s = String( symbol || '$' );
	if ( n <= 0 ) {
		return '';
	}
	// Exact at one decimal only (1.5M); 1,250,000 falls through to the thousands rule.
	if ( n >= 1_000_000 && n % 100_000 === 0 ) {
		// Match PHP's round( $value / 1000000, 1 ): one decimal, ".0" dropped.
		const millions = Math.round( ( n / 1_000_000 ) * 10 ) / 10;
		return wcbMoney( s, wcbFill( t( 'salaryMillion', '%sM' ), '%s', wcbNumber( millions ) ) );
	}
	if ( n >= 1_000 ) {
		// Match PHP: abbreviate only when exact at one decimal (4,500 -> 4.5k), else the full figure.
		if ( n % 100 !== 0 ) {
			return wcbMoney( s, wcbNumber( n ) );
		}
		return wcbMoney( s, wcbFill( t( 'salaryThousand', '%sk' ), '%s', wcbNumber( n / 1_000 ) ) );
	}
	return wcbMoney( s, wcbNumber( n ) );
}

/**
 * Adopt the results-count label the REST response resolved server-side.
 *
 * `results_label` is additive on /wcb/v1/jobs (since 1.5.1) and reports the
 * matches FOUND (`total`), not the rows loaded so far. It has already been
 * through _n() + number_format_i18n() in PHP, so both the plural form and the
 * digit grouping are correct for the site locale in every language — including
 * the ones that need three (Polish, Russian) or six (Arabic) forms, which a
 * `count === 1` pick between two seeded strings can never produce.
 *
 * A legacy bare-array response carries no label, so there is nothing to adopt
 * and the seeded label from render.php stays put rather than being guessed at.
 *
 * @param {Object|Array} data Parsed REST payload.
 */
function wcbApplyResultsLabel( data ) {
	if ( ! Array.isArray( data ) && typeof data?.results_label === 'string' ) {
		state.resultsLabel = data.results_label;
	}
}

/**
 * Active filters as REST params. In-block chip keys (type_*, exp_*, cat_*,
 * tag_*) and external filter-block keys (wcb_category, …) join into one
 * comma list per filter (any of): repeated ?type=a&type=b keeps only the
 * last in PHP. Shared by the job search and "Alert me", so an alert saves
 * exactly the search the visitor is looking at.
 *
 * @param {Object<string,string>} merged activeFilters + baseFilters.
 * @return {Object<string,string>} Param => value.
 */
function wcbFilterParams( merged ) {
	const params   = {};
	const multi    = { type: [], experience: [], category: [], tag: [], location: [] };
	const prefixes = { type_: 'type', exp_: 'experience', cat_: 'category', tag_: 'tag' };
	const external = { wcb_job_type: 'type', wcb_experience: 'experience', wcb_category: 'category', wcb_tag: 'tag', wcb_location: 'location' };
	for ( const [ key, value ] of Object.entries( merged ) ) {
		const prefix = Object.keys( prefixes ).find( ( p ) => key.startsWith( p ) );
		if ( prefix ) {
			multi[ prefixes[ prefix ] ].push( value );
		} else if ( external[ key ] && value ) {
			multi[ external[ key ] ].push( ...String( value ).split( ',' ) );
		} else if ( key === 'remote' || key === 'wcb_remote' ) {
			params.remote = '1';
		} else if ( ( key === 'salary_min' || key === 'salary_max' ) && value ) {
			params[ key ] = value;
		} else if ( key.startsWith( 'board_' ) ) {
			params.board = value;
		} else if ( key.startsWith( 'meta_' ) && value ) {
			// REST checks meta_<key> against its allowlist.
			params[ key ] = value;
		}
	}
	for ( const [ param, values ] of Object.entries( multi ) ) {
		const unique = [ ...new Set( values.filter( Boolean ) ) ];
		if ( unique.length ) {
			params[ param ] = unique.join( ',' );
		}
	}
	return params;
}

/**
 * Maximum-salary slider value: the right end means "Any" (0).
 *
 * @param {HTMLInputElement} input Range input.
 * @return {number} Maximum, or 0 for no limit.
 */
function wcbSliderMax( input ) {
	const value = parseInt( input.value, 10 ) || 0;
	return value >= parseInt( input.max, 10 ) ? 0 : value;
}

/**
 * Reset the standalone job-filters block, when a page still carries one.
 *
 * job-filters is a separate block that filters by navigating with query args,
 * while this block keeps its filter state client-side. A page with both ends up
 * with two sets of the same controls that never sync, and clearing here used to
 * leave the other one still displaying a value and still in the URL - so the
 * dropdown claimed a filter the results no longer had applied.
 *
 * New sites no longer get both blocks provisioned together, but pages created
 * before that change still do, so put the visible controls and the address bar
 * back in step with the results. No-ops when the block is not on the page.
 *
 * @param {string[]|null} only Filter names to reset, or null for all.
 */
function clearLegacyFilterBar( only = null ) {
	if ( typeof document === 'undefined' ) return;

	const ALL   = [ 'wcb_category', 'wcb_job_type', 'wcb_location', 'wcb_experience', 'wcb_tag', 'salary_min', 'salary_max', 'wcb_remote', 'remote' ];
	const NAMES = only ? ALL.filter( ( name ) => only.includes( name ) ) : ALL;

	NAMES.forEach( ( name ) => {
		document.querySelectorAll( `[name="${ name }"]` ).forEach( ( el ) => {
			if ( el.type === 'checkbox' || el.type === 'radio' ) {
				el.checked = false;
			} else {
				el.value = '';
			}
		} );
	} );

	try {
		const url = new URL( window.location.href );
		let touched = false;
		NAMES.forEach( ( name ) => {
			if ( url.searchParams.has( name ) ) {
				url.searchParams.delete( name );
				touched = true;
			}
		} );
		if ( touched ) {
			window.history.replaceState( {}, '', url.toString() );
		}
	} catch ( e ) {
		// A malformed location is not worth breaking the clear over.
	}

	// The job-filters block keeps its own copy (for its "Filters (n)" count).
	document.dispatchEvent( new CustomEvent( 'wcb:filters-cleared', { detail: { keys: NAMES } } ) );
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

		/** Loop-context getter — valid inside data-wp-each on filterOptions.categories. */
		get isCatActive() {
			const ctx = getContext();
			return !! state.activeFilters[ 'cat_' + ctx.catSlug ];
		},

		/** Loop-context getter — valid inside data-wp-each on filterOptions.tags. */
		get isTagActive() {
			const ctx = getContext();
			return !! state.activeFilters[ 'tag_' + ctx.tagSlug ];
		},

		get isRemoteActive() {
			return !! state.activeFilters.remote;
		},

		get isBoardActive() {
			const ctx = getContext();
			const key = 'board_' + ctx.boardId;
			// Treat shortcode-baked boardId scope as "active" too, so the
			// matching chip in the bar reads as selected even though the
			// scope is immutable.
			return !! ( state.activeFilters[ key ] || state.baseFilters?.[ key ] );
		},

		get isSalaryActive() {
			return !! ( state.activeFilters.salary_min || state.activeFilters.salary_max );
		},

		get salaryMinDisplay() {
			return state.salaryMin > 0 ? wcbFormatSalaryShort( state.salaryMin, state.currencySymbol ) : t( 'anyLabel', 'Any' );
		},

		/** The max handle sits at the right end while no maximum is set. */
		get salaryMaxSlider() {
			return state.salaryMax > 0 ? state.salaryMax : 500000;
		},
		get salaryMaxDisplay() {
			return state.salaryMax > 0 ? wcbFormatSalaryShort( state.salaryMax, state.currencySymbol ) : t( 'anyLabel', 'Any' );
		},

		get isMetaChipActive() {
			const { metaKey, metaValue } = getContext();
			return state.activeFilters[ 'meta_' + metaKey ] === metaValue;
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
				} else if ( key.startsWith( 'cat_' ) ) {
					const slug = key.slice( 4 );
					const match = ( state.filterOptions.categories || [] ).find( ( c ) => c.slug === slug );
					label = match ? match.name : slug;
				} else if ( key.startsWith( 'tag_' ) ) {
					const slug = key.slice( 4 );
					const match = ( state.filterOptions.tags || [] ).find( ( t ) => t.slug === slug );
					label = match ? match.name : slug;
				} else if ( key.startsWith( 'board_' ) ) {
					const id = parseInt( key.slice( 6 ), 10 );
					const match = ( state.filterOptions.boards || [] ).find( ( b ) => b.id === id );
					label = match ? match.name : value;
				} else if ( [ 'wcb_category', 'wcb_job_type', 'wcb_experience', 'wcb_location', 'wcb_tag' ].includes( key ) ) {
					// External filter-block and URL keys carry one slug or a
					// comma list; show each as its human name.
					const lists = {
						wcb_category: state.filterOptions.categories,
						wcb_job_type: state.filterOptions.types,
						wcb_experience: state.filterOptions.experiences,
						wcb_location: state.filterOptions.locations,
						wcb_tag: state.filterOptions.tags,
					};
					label = String( value )
						.split( ',' )
						.map( ( slug ) => ( lists[ key ] || [] ).find( ( o ) => o.slug === slug )?.name || slug )
						.join( ', ' );
				} else if ( key.startsWith( 'meta_' ) && state.metaLabels && state.metaLabels[ key ] ) {
					// Custom fields (Pro seeds metaLabels): "Label: value", "Yes" for a checkbox.
					label = state.metaLabels[ key ] + ': ' + ( value === '1' && state.metaYes ? state.metaYes : value );
				} else if ( key === 'salary_min' ) {
					label = wcbFill(
						t( 'salaryOpenMin', '%s+' ),
						'%s',
						wcbFormatSalaryShort( parseInt( value, 10 ), state.currencySymbol )
					);
				} else if ( key === 'salary_max' ) {
					label = wcbFill(
						t( 'salaryUpTo', 'Up to %s' ),
						'%s',
						wcbFormatSalaryShort( parseInt( value, 10 ), state.currencySymbol )
					);
				} else if ( key === 'remote' || key === 'wcb_remote' ) {
					// Without this the pill printed the raw filter value, so
					// every locale — English included — saw a bare "1".
					label = t( 'remoteLabel', 'Remote only' );
				}
				return { key, label };
			} );
		},

		// `resultsLabel` is NOT a getter: it is a plain seeded value that
		// render.php resolves with _n() and fetchJobs() replaces with the REST
		// response's `results_label`. Both sides run the plural through gettext
		// on the server, which is the only place a correct plural form can be
		// chosen for locales with more than two of them.

		// ── Derived: job list ─────────────────────────────────────────
		get hasNoJobs() {
			return ! state.loading && state.jobs.length === 0;
		},

		// ── Derived: bookmark ─────────────────────────────────────────
		get bookmarkLabel() {
			const ctx = getContext();
			return ctx.job?.bookmarked
				? t( 'bookmarkRemove', 'Saved' )
				: t( 'bookmarkAdd', 'Save job' );
		},
	},

	actions: {
		// ── Save search as alert ──────────────────────────────────────
		*saveSearchAlert() {
			if ( state.alertSaved || state.alertSaving ) {
				return;
			}
			// Guests (when the owner allows it) type an email next to the button.
			const emailInput = document.querySelector( '.wcb-alert-guest-email' );
			// On phones the email field is collapsed behind the button: the first tap opens it.
			if ( emailInput && null === emailInput.offsetParent ) {
				state.alertEmailOpen = true;
				yield Promise.resolve();
				emailInput.focus();
				return;
			}
			if ( emailInput && ! emailInput.reportValidity() ) {
				return;
			}

			state.alertSaving = true;
			const params  = wcbFilterParams( { ...( state.activeFilters || {} ), ...( state.baseFilters || {} ) } );
			const boardId = parseInt( params.board || '0', 10 ) || 0;
			delete params.board;

			try {
				const response = yield wcbFetch(
					state.apiBase.replace( '/jobs', '/alerts' ),
					{
						method:  'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce':   state.nonce,
						},
						body: JSON.stringify( {
							search_query: state.searchQuery || '',
							filters:      params,
							board_id:     boardId,
							frequency:    'daily',
							email:        emailInput ? emailInput.value.trim() : '',
						} ),
					}
				);
				const data = yield response.json();
				if ( response.ok ) {
					state.alertSaved        = true;
					state.alertNeedsConfirm = !! data?.needsConfirm;
				} else {
					state.alertError = data?.message || '';
				}
			} catch {
				// Network failure: the button stays enabled to retry.
			} finally {
				state.alertSaving = false;
			}
		},

		// ── Layout toggle ─────────────────────────────────────────────
		setGridLayout() {
			state.layout = 'grid';
			try { localStorage.setItem( 'wcb_archive_layout', 'grid' ); } catch {}
		},
		setListLayout() {
			state.layout = 'list';
			try { localStorage.setItem( 'wcb_archive_layout', 'list' ); } catch {}
		},

		// ── Search ────────────────────────────────────────────────────
		updateSearch( event ) {
			state.searchQuery = event.target.value;
			clearTimeout( searchDebounceTimer );
			searchDebounceTimer = setTimeout( () => {
				store( 'wcb-job-listings' ).actions.applyFilters();
			}, 400 );
		},

		// ── Custom-field chip (Pro filterable fields) ─────────────────
		// One value per field: picking another value of the same field
		// replaces it, picking the same one clears it.
		* toggleMetaChip() {
			const { metaKey, metaValue } = getContext();
			const key  = 'meta_' + metaKey;
			const next = { ...state.activeFilters };
			if ( next[ key ] === metaValue ) {
				delete next[ key ];
			} else {
				next[ key ] = metaValue;
			}
			state.activeFilters = next;
			yield actions.applyFilters();
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

		// ── Category chip ─────────────────────────────────────────────
		* toggleCatChip() {
			const ctx = getContext();
			const key = 'cat_' + ctx.catSlug;
			if ( state.activeFilters[ key ] ) {
				const next = { ...state.activeFilters };
				delete next[ key ];
				state.activeFilters = next;
			} else {
				state.activeFilters = {
					...state.activeFilters,
					[ key ]: ctx.catSlug,
				};
			}
			yield actions.applyFilters();
		},

		// ── Tag chip ──────────────────────────────────────────────────
		* toggleTagChip() {
			const ctx = getContext();
			const key = 'tag_' + ctx.tagSlug;
			if ( state.activeFilters[ key ] ) {
				const next = { ...state.activeFilters };
				delete next[ key ];
				state.activeFilters = next;
			} else {
				state.activeFilters = {
					...state.activeFilters,
					[ key ]: ctx.tagSlug,
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
			// A pill that came from the filter dropdowns or the URL: reset that
			// control and URL param too, so the two never disagree.
			clearLegacyFilterBar( [ key ] );
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
			state.salaryMax = wcbSliderMax( event.target );
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
			state.salaryMax = wcbSliderMax( event.target );
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
			clearLegacyFilterBar();
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

			// Forward savedBy so Load More pages stay scoped to that
			// user's bookmarks. Without this the REST endpoint returns
			// the full publish list and Load More keeps reappearing
			// because totals from the unfiltered fetch overwrite the
			// SSR-scoped total.
			if ( state.savedBy ) {
				url.searchParams.set( 'saved_by', state.savedBy );
			}

			// Sort: '' leaves it to the server (best match for a keyword,
			// else the owner's default order).
			if ( state.sortBy ) {
				url.searchParams.set( 'sort', state.sortBy );
			}

			// Merge immutable shortcode/block scope (baseFilters: boardId,
			// metaFilter) with the user-controlled activeFilters before
			// forwarding to REST. baseFilters wins on key collision so an
			// integrator can pin a scope the user can't override from the UI.
			const merged = { ...( state.activeFilters || {} ), ...( state.baseFilters || {} ) };

			for ( const [ param, value ] of Object.entries( wcbFilterParams( merged ) ) ) {
				url.searchParams.set( param, value );
			}

			try {
				const response = yield wcbFetch( url.toString(), {
					headers: { 'X-WP-Nonce': state.nonce },
				} );

				const data = yield response.json();
				// Envelope shape since 1.1.0: { jobs, total, pages, has_more },
				// plus `results_label` since 1.5.1.
				// Fall back to bare-array + header for any external reverse proxy still serving the old shape.
				const jobs  = Array.isArray( data ) ? data : ( data?.jobs ?? [] );
				const total = Array.isArray( data )
					? parseInt( response.headers.get( 'X-WCB-Total' ) ?? '0', 10 )
					: ( data?.total ?? jobs.length );

				state.totalCount = total;
				wcbApplyResultsLabel( data );
				if ( state.page === 1 ) {
					state.jobs = jobs;
				} else {
					state.jobs = [ ...state.jobs, ...jobs ];
				}

				// Truth source for the Load More button — recompute from the
				// final cumulative count instead of trusting the per-response
				// flag alone. Covers:
				//   1. envelope says has_more=true but we already have every row
				//      (totals can race when the cache version bumps between pages),
				//   2. legacy array shape that doesn't carry has_more,
				//   3. a fetched page that returned 0 jobs.
				const apiHasMore = Array.isArray( data ) ? true : !! data?.has_more;
				state.hasMore    = apiHasMore && jobs.length > 0 && state.jobs.length < total;

				// Broadcast the resolved result set so listeners (e.g. the Pro
				// job-map block) can sync to the actually-visible jobs. The
				// `wcb:search` event only carries the query/filters — not which
				// jobs matched — so the map could never narrow its pins from it.
				document.dispatchEvent(
					new CustomEvent( 'wcb:results', {
						detail: {
							jobIds: state.jobs
								.map( ( job ) => job.id )
								.filter( ( id ) => id != null ),
							total: state.totalCount,
						},
					} )
				);
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

			const response = yield wcbFetch( state.apiBase + '/' + jobId + '/bookmark', {
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
				// Unified key shared across Jobs, Companies, Find Candidates so
				// a user's list/grid preference syncs across the 3 archives.
				// Migration: fall back to the legacy per-archive key for users
				// who set their preference before 1.2.0.
				let saved = localStorage.getItem( 'wcb_archive_layout' );
				if ( saved !== 'grid' && saved !== 'list' ) {
					const legacy = localStorage.getItem( 'wcb_layout' );
					if ( legacy === 'grid' || legacy === 'list' ) {
						saved = legacy;
						localStorage.setItem( 'wcb_archive_layout', legacy );
					}
				}
				if ( saved === 'grid' || saved === 'list' ) {
					state.layout = saved;
				}
			} catch {}
			// URL filters (keyword, hero category/location, shared links) are
			// applied by the server on first paint and seeded into
			// state.searchQuery / state.activeFilters, so no refetch here.

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
