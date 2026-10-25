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

import { store, getContext } from '@wordpress/interactivity';
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

/**
 * Drop the inline error once the member edits the field it points at.
 *
 * @param {string} field State key of the edited field.
 */
const clearInvalid = ( field ) => {
	if ( field === state.invalidField ) {
		state.invalidField = '';
		state.fieldError   = '';
	}
};

/**
 * First required field that is empty or malformed, as [ stateKey, inputId, i18nKey ].
 * The form is novalidate, so this replaces the browser's own popup (Basecamp 10412873869).
 *
 * @return {string[]|null} The failing field, or null when the form is complete.
 */
const firstInvalid = () => {
	const checks = [];
	if ( ! state.isLoggedIn ) {
		checks.push(
			[ 'firstName', 'wcb-reg-first-name', 'errorFirstName', () => !! state.firstName.trim() ],
			[ 'lastName', 'wcb-reg-last-name', 'errorLastName', () => !! state.lastName.trim() ],
			[ 'email', 'wcb-reg-email', 'errorEmail', () => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( state.email.trim() ) ]
		);
	}
	if ( 'employer' === state.role ) {
		checks.push( [ 'companyName', 'wcb-reg-company', 'errorCompanyName', () => !! state.companyName.trim() ] );
	}
	if ( ! state.isLoggedIn ) {
		checks.push( [ 'password', 'wcb-reg-password', 'errorPassword', () => state.password.length >= 8 ] );
	}
	const failed = checks.find( ( check ) => ! check[ 3 ]() );
	return failed ? failed.slice( 0, 3 ) : null;
};

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
		// One field at a time carries the inline error, like Post a Job. Each
		// field group names its field in data-wp-context.
		get fieldInvalid() {
			return getContext().field === state.invalidField;
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
			state.invalidField = '';
		},
		selectEmployer() {
			state.role  = 'employer';
			state.error = '';
			state.invalidField = '';
		},
		backToRolePicker() {
			state.role  = '';
			state.error = '';
			state.invalidField = '';
		},

		updateField( event ) {
			const field = event.target.dataset.wcbField;
			if ( field ) {
				state[ field ] = event.target.value;
				clearInvalid( field );
			}
		},

		*resendVerification() {
			state.resendSent = true;
			try {
				const response = yield wcbFetch(
					state.apiBase + '/auth/verify-email/resend',
					{
						method:  'POST',
						headers: { 'Content-Type': 'application/json' },
						body:    JSON.stringify( { email: state.email } ),
					}
				);
				// The route answers the same for every address; this member just
				// signed up with it, so tell them plainly.
				state.resendMessage = response.ok ? t( 'resendDone' ) : t( 'errorConnection' );
				state.resendSent    = response.ok;
			} catch {
				state.resendMessage = t( 'errorConnection' );
				state.resendSent    = false;
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

			const invalid = firstInvalid();
			if ( invalid ) {
				state.invalidField = invalid[ 0 ];
				state.fieldError   = t( invalid[ 2 ] );
				document.getElementById( invalid[ 1 ] )?.focus();
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

			// Registration now runs the same anti-spam gate as job and
			// application submits, so it needs the CAPTCHA token too.
			// Empty when no provider is configured.
			body.wcb_captcha_token = window.wcbCaptchaGetToken
				? yield window.wcbCaptchaGetToken()
				: '';

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

				state.dashboardUrl  = data.dashboard_url || '';
				state.verifyPending = !! data.verification_required;
				state.submitted     = true;
			} catch {
				state.error = t( 'errorConnection' );
			} finally {
				state.submitting = false;
			}
		},
	},
} );
