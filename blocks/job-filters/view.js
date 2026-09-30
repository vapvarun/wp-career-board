/**
 * WP Career Board — job-filters block Interactivity API store.
 *
 * Merges into the shared 'wcb-search' namespace.
 *
 * Actions:
 *   updateFilter  — read the changed select value, update state.filters,
 *                   push new URL params, and dispatch wcb:search.
 *
 * @package WP_Career_Board
 */
import { store } from '@wordpress/interactivity';

const { state } = store( 'wcb-search', {
	state: {
		/** Active filters, for the "Filters (n)" button on tablet and phone. */
		get activeFilterCount() {
			return Object.values( state.filters || {} ).filter( Boolean ).length;
		},
	},
	actions: {
		updateFilter( event ) {
			const key   = event.target.dataset.wcbFilter;
			const value = event.target.type === 'checkbox'
				? ( event.target.checked ? '1' : '' )
				: event.target.value;

			// Clone filters to avoid mutating state directly.
			const filters = Object.assign( {}, state.filters );

			if ( value ) {
				filters[ key ] = value;
			} else {
				delete filters[ key ];
			}

			state.filters = filters;

			// Push updated URL params.
			const params = new URLSearchParams( window.location.search );

			if ( value ) {
				params.set( key, value );
			} else {
				params.delete( key );
			}

			const wcbFilterQs = params.toString();
			window.history.pushState( {}, '', wcbFilterQs ? '?' + wcbFilterQs : window.location.pathname );

			// Notify the job-listings block.
			document.dispatchEvent(
				new CustomEvent( 'wcb:search', {
					detail: {
						query:   state.query,
						filters: state.filters,
					},
				} )
			);
		},
	},
} );

// The listing cleared some filters (a pill's ×, "Clear all"): drop them here.
document.addEventListener( 'wcb:filters-cleared', ( event ) => {
	const filters = Object.assign( {}, state.filters );
	( event.detail?.keys || [] ).forEach( ( key ) => delete filters[ key ] );
	state.filters = filters;
} );
