/**
 * WP Career Board — job-single block Interactivity API store.
 *
 * Actions:
 *   openPanel          — slide the apply panel into view.
 *   closePanel         — dismiss the apply panel.
 *   updateCoverLetter  — sync textarea value to state.
 *   submitApplication  — POST cover letter to /wcb/v1/jobs/{id}/apply.
 *
 * @package WP_Career_Board
 */
import { store } from '@wordpress/interactivity';

store( 'wcb-job-single', {
	actions: {
		openPanel() {
			const { state } = store( 'wcb-job-single' );
			state.panelOpen = true;
		},

		closePanel() {
			const { state } = store( 'wcb-job-single' );
			state.panelOpen = false;
			state.error     = '';
		},

		updateCoverLetter( event ) {
			const { state } = store( 'wcb-job-single' );
			state.coverLetter = event.target.value;
		},

		*submitApplication() {
			const { state } = store( 'wcb-job-single' );

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
	},
} );
