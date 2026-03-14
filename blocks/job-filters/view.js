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
	actions: {
		updateFilter( event ) {
			const key   = event.target.dataset.wcbFilter;
			const value = event.target.value;

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

			window.history.pushState( {}, '', '?' + params.toString() );

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
