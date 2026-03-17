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

const { state } = store( 'wcb-job-single', {
	state: {
		get bookmarkLabel() {
			return state.bookmarked ? 'Saved' : 'Save Job';
		},
		get hasResumes() {
			return state.userResumes && state.userResumes.length > 0;
		},
	},

	actions: {
		openPanel() {
			state.panelOpen = true;
		},

		closePanel() {
			state.panelOpen  = false;
			state.error      = '';
			state.resumeFile = null;
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
			state.resumeFile = event.target.files[ 0 ] || null;
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
					state.error = 'Please enter your name and email to apply.';
					return;
				}
			}

			state.submitting = true;
			state.error      = '';

			try {
				let resumeAttachmentId = 0;

				// Free mode: upload the file first, then get attachment ID.
				if ( ! state.proActive && state.resumeFile ) {
					const formData = new FormData();
					formData.append( 'resume_file', state.resumeFile );

					const uploadRes = yield fetch(
						state.apiBase + '/candidates/resume-upload',
						{
							method:  'POST',
							headers: { 'X-WP-Nonce': state.nonce },
							body:    formData,
						}
					);

					if ( ! uploadRes.ok ) {
						state.error = 'Resume upload failed. Please try again.';
						return;
					}

					const uploadData   = yield uploadRes.json();
					resumeAttachmentId = uploadData.attachment_id || 0;
				}

				const body = {
					cover_letter:      state.coverLetter,
					hp:                hpEl ? hpEl.value : '',
					wcb_captcha_token: captchaToken,
				};

				if ( ! state.isLoggedIn ) {
					body.guest_name  = state.guestName;
					body.guest_email = state.guestEmail;
				}

				if ( state.proActive && state.selectedResumeId > 0 ) {
					body.resume_id = state.selectedResumeId;
				}

				if ( resumeAttachmentId > 0 ) {
					body.resume_attachment_id = resumeAttachmentId;
				}

				const response = yield fetch(
					state.apiBase + '/jobs/' + String( state.jobId ) + '/apply',
					{
						method:  'POST',
						headers: {
							'X-WP-Nonce':   state.nonce,
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( body ),
					}
				);

				if ( ! response.ok ) {
					const err   = yield response.json().catch( () => null );
					state.error = ( err && err.message ) ? err.message : 'Application could not be submitted. Please try again.';
					return;
				}

				state.submitted = true;
				state.panelOpen = false;
				if ( window.wcbCaptchaReset ) {
					window.wcbCaptchaReset();
				}
			} catch {
				state.error = 'Connection error. Please check your network and try again.';
			} finally {
				state.submitting = false;
			}
		},

		async copyLink() {
			try {
				await navigator.clipboard.writeText( state.jobPermalink );
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
