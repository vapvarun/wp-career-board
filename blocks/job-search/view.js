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

			// One name for the keyword, the one the job listings write.
			params.delete( 'wcb_search' );
			params.delete( 's' );
			if ( state.query ) {
				params.set( 'search', state.query );
			} else {
				params.delete( 'search' );
			}

			const wcbSearchQs = params.toString();
			window.history.pushState( {}, '', wcbSearchQs ? '?' + wcbSearchQs : window.location.pathname );

			document.dispatchEvent(
				new CustomEvent( 'wcb:search', {
					detail: {
						// The query only: the filters block keeps the listing's filters
						// in step itself, and resending its single-value copy here would
						// drop a multi-value one from a shared link.
						query:  state.query,
						// This search already has its history entry (pushed above).
						pushed: true,
					},
				} )
			);
		},
	},
} );
