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
				state.error      = 'Application could not be submitted. Please try again.';
				state.submitting = false;
				return;
			}

			state.submitted  = true;
			state.submitting = false;
			state.panelOpen  = false;
		},

		*toggleBookmark() {
			if ( state.bookmarking ) {
				return;
			}

			state.bookmarking = true;

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

			state.bookmarking = false;

			if ( ! response.ok ) {
				return;
			}

			const data        = yield response.json();
			state.bookmarked  = data.bookmarked;
		},
	},
} );
