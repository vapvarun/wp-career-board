/**
 * WP Career Board — job-single block Interactivity API store.
 *
 * Actions:
 *   openPanel          — slide the apply panel into view.
 *   closePanel         — dismiss the apply panel.
 *   updateCoverLetter  — sync textarea value to state.
 *   submitApplication  — POST cover letter to /wcb/v1/jobs/{id}/apply.
 *   toggleBookmark     — POST to /wcb/v1/jobs/{id}/bookmark and flip state.bookmarked.
 *   copyLink           — copy job permalink to clipboard, show checkmark for 2s.
 *
 * @package WP_Career_Board
 */
import { store } from '@wordpress/interactivity';

// Holds the element that triggered the apply panel so focus can be restored on close.
let panelTriggerEl = null;

const { state } = store( 'wcb-job-single', {
	state: {
		get bookmarkLabel() {
			return state.bookmarked ? state.strings.bookmarkSaved : state.strings.bookmarkSave;
		},
		get hasResumes() {
			return state.userResumes && state.userResumes.length > 0;
		},
	},

	actions: {
		openPanel( event ) {
			panelTriggerEl  = event?.currentTarget ?? null;
			state.panelOpen = true;
			// Move focus into the panel after the DOM updates.
			queueMicrotask( () => {
				const panel    = document.querySelector( '.wcb-apply-panel' );
				const focusable = panel?.querySelector( 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])' );
				if ( focusable ) {
					focusable.focus();
				}
			} );
		},

		closePanel() {
			state.panelOpen  = false;
			state.error      = '';
			state.resumeFile = null;
			if ( panelTriggerEl ) {
				panelTriggerEl.focus();
				panelTriggerEl = null;
			}
		},

		handlePanelKeydown( event ) {
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				state.panelOpen  = false;
				state.error      = '';
				state.resumeFile = null;
				if ( panelTriggerEl ) {
					panelTriggerEl.focus();
					panelTriggerEl = null;
				}
				return;
			}

			if ( event.key !== 'Tab' ) {
				return;
			}

			const panel      = event.currentTarget;
			const focusables = Array.from(
				panel.querySelectorAll( 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])' )
			).filter( ( el ) => ! el.disabled );
			const first = focusables[ 0 ];
			const last  = focusables[ focusables.length - 1 ];

			if ( ! first ) {
				return;
			}

			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		},

		updateCoverLetter( event ) {
			state.coverLetter = event.target.value;
		},

		updateGuestName( event ) {
			state.guestName = event.target.value;
		},

		updateGuestEmail( event ) {
			state.guestEmail = event.target.value;
		},

		selectResume( event ) {
			state.selectedResumeId = Number( event.target.value );
		},

		selectResumeFile( event ) {
			state.resumeFile     = event.target.files[ 0 ] || null;
			state.resumeFileName = state.resumeFile ? state.resumeFile.name : '';
		},

		*createAlertFromJob() {
			if ( state.alertFromJobSaved || state.alertFromJobSaving ) {
				return;
			}

			state.alertFromJobSaving = true;

			const filters = {};
			if ( state.jobCategories && state.jobCategories.length ) {
				filters.category = state.jobCategories[ 0 ];
			}
			if ( state.jobTypes && state.jobTypes.length ) {
				filters.type = state.jobTypes[ 0 ];
			}
			if ( state.jobRemote ) {
				filters.remote = true;
			}

			try {
				const response = yield fetch(
					state.apiBase + '/alerts',
					{
						method:  'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce':   state.nonce,
						},
						body: JSON.stringify( {
							search_query: state.jobTitle,
							filters,
							frequency:    'daily',
						} ),
					}
				);

				if ( response.ok ) {
					state.alertFromJobSaved = true;
				}
			} catch {
				// Silent.
			} finally {
				state.alertFromJobSaving = false;
			}
		},

		*submitApplication() {
			if ( state.submitting ) {
				return;
			}

			// Honeypot — bots that autofill all fields trigger a fake success.
			const hpEl = document.getElementById( 'wcb-hp-apply' );
			if ( hpEl && hpEl.value ) {
				state.submitted = true;
				return;
			}

			// Optional CAPTCHA token (Turnstile / reCAPTCHA). Empty when provider is 'none'.
			const captchaToken = window.wcbCaptchaGetToken
				? yield window.wcbCaptchaGetToken()
				: '';

			// Guest validation — require name + email before submitting.
			if ( ! state.isLoggedIn ) {
				if ( ! state.guestName.trim() || ! state.guestEmail.trim() ) {
					state.error = state.strings.guestFieldsRequired;
					return;
				}
			}

			// Resume requirement check (server enforces too).
			if ( state.resumeRequired && ! state.resumeFile && ! ( state.proActive && state.selectedResumeId > 0 ) ) {
				state.error = state.strings.resumeRequiredError;
				return;
			}

			state.submitting = true;
			state.error      = '';

			try {
				const formData = new FormData();
				formData.append( 'cover_letter', state.coverLetter );
				formData.append( 'hp', hpEl ? hpEl.value : '' );
				formData.append( 'wcb_captcha_token', captchaToken || '' );

				if ( ! state.isLoggedIn ) {
					formData.append( 'guest_name', state.guestName );
					formData.append( 'guest_email', state.guestEmail );
				}

				if ( state.proActive && state.selectedResumeId > 0 ) {
					formData.append( 'resume_id', String( state.selectedResumeId ) );
				}

				if ( state.resumeFile ) {
					formData.append( 'resume_file', state.resumeFile );
				}

				const response = yield fetch(
					state.apiBase + '/jobs/' + String( state.jobId ) + '/apply',
					{
						method:  'POST',
						headers: { 'X-WP-Nonce': state.nonce },
						body:    formData,
					}
				);

				if ( ! response.ok ) {
					const err   = yield response.json().catch( () => null );
					state.error = ( err && err.message ) ? err.message : state.strings.applicationFailed;
					return;
				}

				state.submitted = true;
				state.panelOpen = false;
				if ( window.wcbCaptchaReset ) {
					window.wcbCaptchaReset();
				}
			} catch {
				state.error = state.strings.connectionError;
			} finally {
				state.submitting = false;
			}
		},

		* copyLink() {
			try {
				yield navigator.clipboard.writeText( state.jobPermalink );
				state.linkCopied = true;
				setTimeout( () => { state.linkCopied = false; }, 2000 );
			} catch {
				// Clipboard API unavailable (non-HTTPS or denied) — fail silently.
			}
		},

		*toggleBookmark() {
			if ( state.bookmarking ) {
				return;
			}

			state.bookmarking = true;

			try {
				const response = yield fetch(
					state.apiBase + '/jobs/' + String( state.jobId ) + '/bookmark',
					{
						method: 'POST',
						headers: {
							'X-WP-Nonce':   state.nonce,
							'Content-Type': 'application/json',
						},
					}
				);

				if ( ! response.ok ) {
					return;
				}

				const data       = yield response.json();
				state.bookmarked = data.bookmarked;
			} catch {
				// Bookmark toggle failed silently — no UI disruption needed.
			} finally {
				state.bookmarking = false;
			}
		},
	},
} );
