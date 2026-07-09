/**
 * WP Career Board — unified registration block Interactivity API store.
 *
 * Step 1: Role picker (candidate / employer).
 * Step 2: Registration form (company name shown only for employers).
 * Step 3: Success state with dashboard redirect.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

import { store } from '@wordpress/interactivity';
import { wcbFetch } from '@wcb/fetch';

/**
 * Translation reader. Every user-facing string is authored once as a PHP __()
 * call in render.php and seeded onto the `i18n` state key; this module holds no
 * English source text of its own. An unseeded key resolves to an empty string
 * rather than leaking a raw key name into the UI.
 *
 * @param {string} key Key seeded in render.php's `i18n` array.
 * @return {string} Translated string.
 */
const t = ( key ) => ( state.i18n && state.i18n[ key ] ) || '';

const { state } = store( 'wcb-employer-registration', {
	state: {
		get isCandidate() {
			return state.role === 'candidate';
		},
		get isEmployer() {
			return state.role === 'employer';
		},
		get roleTitle() {
			return state.role === 'candidate'
				? t( 'roleTitleCandidate' )
				: t( 'roleTitleEmployer' );
		},
		get emailLabel() {
			return state.role === 'candidate'
				? t( 'emailLabel' )
				: t( 'emailLabelWork' );
		},
	},

	actions: {
		selectCandidate() {
			state.role  = 'candidate';
			state.error = '';
		},
		selectEmployer() {
			state.role  = 'employer';
			state.error = '';
		},
		backToRolePicker() {
			state.role  = '';
			state.error = '';
		},

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
		updateField( event ) {
			const field = event.target.dataset.wcbField;
			if ( field ) {
				state[ field ] = event.target.value;
			}
		},

		*submit( event ) {
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

			const endpoint = state.role === 'candidate'
				? '/candidates/register'
				: '/employers/register';

			const body = state.isLoggedIn
				? {}
				: {
					first_name: state.firstName,
					last_name:  state.lastName,
					email:      state.email,
					password:   state.password,
				};

			if ( state.role === 'employer' ) {
				body.company_name = state.companyName;
				if ( state.companyWebsite ) body.website = state.companyWebsite;
				if ( state.companyIndustry ) body.industry = state.companyIndustry;
				if ( state.companySize ) body.size = state.companySize;
				if ( state.companyHq ) body.hq = state.companyHq;
			}

			try {
				const response = yield wcbFetch(
					state.apiBase + endpoint,
					{
						method:  'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce':   state.nonce,
						},
						body: JSON.stringify( body ),
					}
				);

				const data = yield response.json();

				if ( ! response.ok ) {
					if ( data && 'wcb_missing_params' === data.code ) {
						// The server assembles this message by concatenating raw,
						// untranslated field slugs; show a single localized string
						// instead of rendering that slug list verbatim.
						state.error = t( 'errorMissingFields' );
					} else if ( data && data.message ) {
						// Remaining server errors are single, self-contained __()
						// strings (already localized), safe to surface as-is.
						state.error = data.message;
					} else {
						state.error = t( 'errorRegistration' );
					}
					return;
				}

				state.dashboardUrl = data.dashboard_url || '';
				state.submitted    = true;
			} catch {
				state.error = t( 'errorConnection' );
			} finally {
				state.submitting = false;
			}
		},
	},
} );
