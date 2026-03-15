/**
 * WP Career Board — job-single block Interactivity API store.
 *
 * Actions:
 *   openPanel          — slide the apply panel into view.
 *   closePanel         — dismiss the apply panel.
 *   updateCoverLetter  — sync textarea value to state.
 *   submitApplication  — POST cover letter to /wcb/v1/jobs/{id}/apply.
 *   toggleBookmark     — POST to /wcb/v1/jobs/{id}/bookmark and flip state.bookmarked.
 *
 * @package WP_Career_Board
 */
import { store } from '@wordpress/interactivity';

const { state } = store( 'wcb-job-single', {
	state: {
		get bookmarkLabel() {
			return state.bookmarked ? 'Saved' : 'Save Job';
		},
	},

	actions: {
		openPanel() {
			state.panelOpen = true;
		},

		closePanel() {
			state.panelOpen = false;
			state.error     = '';
		},

		updateCoverLetter( event ) {
			state.coverLetter = event.target.value;
		},

		*submitApplication() {
			if ( state.submitting ) {
				return;
			}

			state.submitting = true;
			state.error      = '';

			try {
				const response = yield fetch(
					state.apiBase + '/jobs/' + String( state.jobId ) + '/apply',
					{
						method: 'POST',
						headers: {
							'X-WP-Nonce':   state.nonce,
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( { cover_letter: state.coverLetter } ),
					}
				);

				if ( ! response.ok ) {
					state.error = 'Application could not be submitted. Please try again.';
					return;
				}

				state.submitted = true;
				state.panelOpen = false;
			} catch {
				state.error = 'Connection error. Please check your network and try again.';
			} finally {
				state.submitting = false;
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
