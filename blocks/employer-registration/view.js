/**
 * WP Career Board — employer-registration block Interactivity API store.
 *
 * Actions:
 *   updateFirstName   — sync first name field.
 *   updateLastName    — sync last name field.
 *   updateEmail       — sync email field.
 *   updateCompanyName — sync company name field.
 *   updatePassword    — sync password field.
 *   submit            — POST to /wcb/v1/employers/register, log user in on success.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

import { store } from '@wordpress/interactivity';

const { state } = store( 'wcb-employer-registration', {
	actions: {
		updateFirstName( event ) {
			state.firstName = event.target.value;
		},
		updateLastName( event ) {
			state.lastName = event.target.value;
		},
		updateEmail( event ) {
			state.email = event.target.value;
		},
		updateCompanyName( event ) {
			state.companyName = event.target.value;
		},
		updatePassword( event ) {
			state.password = event.target.value;
		},

		* submit( event ) {
			event.preventDefault();

			// Honeypot — bail silently if filled.
			const hp = document.getElementById( 'wcb-hp-reg' );
			if ( hp && hp.value ) {
				state.submitted = true;
				return;
			}

			if ( state.submitting ) {
				return;
			}

			state.submitting = true;
			state.error      = '';

			try {
				const response = yield fetch(
					state.apiBase + '/employers/register',
					{
						method:  'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce':   state.nonce,
						},
						body: JSON.stringify( {
							first_name:   state.firstName,
							last_name:    state.lastName,
							email:        state.email,
							company_name: state.companyName,
							password:     state.password,
						} ),
					}
				);

				const data = yield response.json();

				if ( ! response.ok ) {
					state.error = ( data && data.message ) ? data.message : 'Registration failed. Please try again.';
					return;
				}

				state.dashboardUrl = data.dashboard_url || '';
				state.submitted    = true;
			} catch {
				state.error = 'Connection error. Please check your network and try again.';
			} finally {
				state.submitting = false;
			}
		},
	},
} );
