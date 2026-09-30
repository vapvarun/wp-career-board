/**
 * Dashboard "Email Notifications" panel: each change saves the member's
 * list of turned-off emails through POST /wcb/v1/account.
 */
import { store, getContext } from '@wordpress/interactivity';
import { wcbFetch } from '@wcb/fetch';

const { state } = store( 'wcb-email-prefs', {
	actions: {
		*toggle( event ) {
			const { id } = getContext();
			const optout = state.optout.filter( ( item ) => item !== id );
			if ( ! event.target.checked ) {
				optout.push( id );
			}
			state.optout = optout;
			state.msg    = '';
			try {
				const response = yield wcbFetch( state.apiBase + '/account', {
					method: 'POST',
					headers: { 'X-WP-Nonce': state.nonce, 'Content-Type': 'application/json' },
					body: JSON.stringify( { email_optout: optout } ),
				} );
				state.msg = response.ok ? state.saved : state.failed;
			} catch {
				state.msg = state.failed;
			}
		},
	},
} );
