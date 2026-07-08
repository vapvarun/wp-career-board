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
import { wcbFetch } from '@wcb/fetch';

// Holds the element that triggered the apply panel so focus can be restored on close.
let panelTriggerEl = null;

// Translation lookup. view.js is a script module, so it cannot load JED translation
// files; render.php seeds every string, already translated, into state.i18n. The
// English fallback matches the __() source text in render.php exactly.
const t = ( key, fallback ) => ( state.i18n && state.i18n[ key ] ) || fallback;

const { state } = store( 'wcb-job-single', {
	state: {
		// Filled per-input by actions.updateCustomField when a site uses
		// the wcb_application_form_fields_groups filter to add custom
		// application fields. submitApplication appends each entry as
		// custom_fields[<key>] = <value> in the POST body. The PHP
		// endpoint (api/endpoints/class-applications-endpoint.php) reads
		// them, validates against the filter's active output, and
		// persists per-key as postmeta on the wcb_application post.
		// Closes Basecamp 9874915447 (custom fields silently dropped).
		customFields: {},
		get bookmarkLabel() {
			return state.bookmarked
				? t( 'bookmarkSaved', 'Saved' )
				: t( 'bookmarkSave', 'Save Job' );
		},
		get hasResumes() {
			return state.userResumes && state.userResumes.length > 0;
		},
		get aiCoverBtnLabel() {
			return state.coverLoading
				? t( 'aiCoverBusy', 'Writing…' )
				: t( 'aiCoverBtn', 'Write with AI' );
		},
	},

	actions: {
		// Captures every custom field's value as the user types/selects.
		// Bound to data-wp-on--input + data-wp-on--change in render.php
		// (lines ~891, 893, 900). The data-wcb-field attribute on the
		// input/select carries the field key.
		updateCustomField( event ) {
			const target = event && event.target;
			if ( ! target ) {
				return;
			}
			const key = target.getAttribute( 'data-wcb-field' );
			if ( ! key ) {
				return;
			}
			state.customFields = { ...state.customFields, [ key ]: target.value };
		},

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

		// Pro-only: generate a cover letter from the candidate's resume + this
		// job via POST /jobs/{id}/ai-cover-letter, then push the text into the
		// Editor.js-backed cover-letter field. The endpoint and the
		// wcb_ai_completion_available gate are answered by the Pro plugin;
		// in Free the gate is false so the button never renders.
		*generateCoverLetter() {
			if ( state.coverLoading || ! state.aiCoverEnabled ) {
				return;
			}
			state.coverLoading = true;
			try {
				const response = yield wcbFetch(
					state.apiBase + '/jobs/' + String( state.jobId ) + '/ai-cover-letter',
					{
						method:  'POST',
						headers: { 'X-WP-Nonce': state.nonce },
						timeout: 60000,
					}
				);
				if ( ! response.ok ) {
					return;
				}
				const data = yield response.json();
				if ( data && data.cover_letter ) {
					state.coverLetter = String( data.cover_letter );
					// Mirror into the hidden Editor.js source textarea and force
					// the rich editor to re-render (see assets/js/wcb-editor.js —
					// it listens for `wcb:editor:hydrate` after a value push).
					const ta = document.querySelector(
						'.wcb-apply-panel textarea.wcb-editor-source'
					);
					if ( ta ) {
						ta.value = state.coverLetter;
						ta.dispatchEvent( new Event( 'input', { bubbles: true } ) );
						ta.dispatchEvent( new Event( 'wcb:editor:hydrate' ) );
					}
				}
			} catch {
				// Silent — the candidate can still write the letter manually.
			} finally {
				state.coverLoading = false;
			}
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
				const response = yield wcbFetch(
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
					state.error = t( 'guestFieldsRequired', 'Please enter your name and email to apply.' );
					return;
				}
			}

			// Resume requirement check (server enforces too).
			if ( state.resumeRequired && ! state.resumeFile && ! ( state.proActive && state.selectedResumeId > 0 ) ) {
				state.error = t( 'resumeRequiredError', 'Please attach your resume to apply.' );
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

				// Custom application fields registered by site code via the
				// wcb_application_form_fields_groups filter. Values were
				// captured into state.customFields by actions.updateCustomField
				// as the user typed. PHP endpoint validates against the
				// filter's active output before persisting.
				for ( const customKey in state.customFields ) {
					if ( Object.prototype.hasOwnProperty.call( state.customFields, customKey ) ) {
						formData.append(
							'custom_fields[' + customKey + ']',
							String( state.customFields[ customKey ] ?? '' )
						);
					}
				}

				const response = yield wcbFetch(
					state.apiBase + '/jobs/' + String( state.jobId ) + '/apply',
					{
						method:  'POST',
						headers: { 'X-WP-Nonce': state.nonce },
						body:    formData,
					}
				);

				if ( ! response.ok ) {
					const err   = yield response.json().catch( () => null );
					state.error = ( err && err.message )
						? err.message
						: t( 'applicationFailed', 'Application could not be submitted. Please try again.' );
					return;
				}

				state.submitted = true;
				state.panelOpen = false;
				if ( window.wcbCaptchaReset ) {
					window.wcbCaptchaReset();
				}
			} catch {
				state.error = t( 'connectionError', 'Connection error. Please check your network and try again.' );
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
				const response = yield wcbFetch(
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

		toggleReport() {
			state.reportOpen = ! state.reportOpen;
			if ( state.reportOpen ) {
				state.reportError = '';
			}
		},

		updateReportReason( event ) {
			state.reportReason = event.target.value;
			state.reportError  = '';
		},

		*submitReport() {
			if ( state.reportSubmitting ) {
				return;
			}
			if ( ! state.reportReason ) {
				state.reportError = t( 'reportReasonRequired', 'Please choose a reason for reporting.' );
				return;
			}

			state.reportSubmitting = true;
			state.reportError      = '';

			try {
				const response = yield wcbFetch(
					state.apiBase + '/jobs/' + String( state.jobId ) + '/report',
					{
						method:  'POST',
						headers: {
							'X-WP-Nonce':   state.nonce,
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( { reason: state.reportReason } ),
					}
				);

				if ( ! response.ok ) {
					state.reportError = t( 'reportFailed', 'Could not submit your report. Please try again.' );
					return;
				}

				yield response.json();
				state.reportDone = true;
				state.reportOpen = false;
			} catch {
				state.reportError = t( 'connectionError', 'Connection error. Please check your network and try again.' );
			} finally {
				state.reportSubmitting = false;
			}
		},
	},
} );
