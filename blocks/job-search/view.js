/**
 * WP Career Board — job-search block Interactivity API store.
 *
 * Actions:
 *   updateQuery  — sync state.query as the user types.
 *   search       — prevent default form submit, push URL params, dispatch wcb:search.
 *
 * @package WP_Career_Board
 */
import { store } from '@wordpress/interactivity';

const { state } = store( 'wcb-search', {
	actions: {
		updateQuery( event ) {
			state.query = event.target.value;
		},

		search( event ) {
			event.preventDefault();

			const params = new URLSearchParams( window.location.search );

			if ( state.query ) {
				params.set( 'wcb_search', state.query );
			} else {
				params.delete( 'wcb_search' );
			}

			window.history.pushState( {}, '', '?' + params.toString() );

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
