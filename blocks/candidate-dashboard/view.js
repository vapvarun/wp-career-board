/**
 * WP Career Board — candidate-dashboard block Interactivity API store.
 *
 * Actions:
 *   init                  — fetch applications on mount (via data-wp-init).
 *   switchToApplications  — activate My Applications tab.
 *   switchToBookmarks     — activate Saved Jobs tab (loads bookmarks if empty).
 *   switchToResumes       — activate My Resumes tab (loads resumes if empty).
 *   unbookmark            — POST to /jobs/{id}/bookmark to toggle off.
 *   createResume          — POST to /candidates/{id}/resumes to create a new resume.
 *   resumeEditUrl         — computed href for Edit button (resumeBuilderUrl + ?resume_id=N).
 *   deleteResume          — DELETE /resumes/{id}.
 *
 * @package WP_Career_Board
 */
import { store, getContext } from '@wordpress/interactivity';

store( 'wcb-candidate-dashboard', {
	state: {
		get isTabApplications() {
			const { state } = store( 'wcb-candidate-dashboard' );
			return state.tab === 'applications';
		},
		get isTabBookmarks() {
			const { state } = store( 'wcb-candidate-dashboard' );
			return state.tab === 'bookmarks';
		},
		get isTabResumes() {
			const { state } = store( 'wcb-candidate-dashboard' );
			return state.tab === 'resumes';
		},
		get resumeEditUrl() {
			const { state } = store( 'wcb-candidate-dashboard' );
			const ctx        = getContext();
			if ( ! ctx.resume || ! state.resumeBuilderUrl ) {
				return '#';
			}
			return state.resumeBuilderUrl + '?resume_id=' + String( ctx.resume.id );
		},
	},

	actions: {
		*init() {
			const { state } = store( 'wcb-candidate-dashboard' );
			state.loading = true;
			state.error   = '';

			try {
				const response = yield fetch(
					state.apiBase + '/candidates/' + String( state.candidateId ) + '/applications',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);

				if ( ! response.ok ) {
					state.error = 'Could not load your applications.';
					return;
				}

				state.applications = yield response.json();
			} catch {
				state.error = 'Connection error. Please check your network and try again.';
			} finally {
				state.loading = false;
			}
		},

		switchToApplications() {
			const { state } = store( 'wcb-candidate-dashboard' );
			state.tab   = 'applications';
			state.error = '';
		},

		*switchToBookmarks() {
			const { state } = store( 'wcb-candidate-dashboard' );
			state.tab   = 'bookmarks';
			state.error = '';

			// Load bookmarks on first switch.
			if ( state.bookmarks.length ) {
				return;
			}

			state.loading = true;

			try {
				const response = yield fetch(
					state.apiBase + '/candidates/' + String( state.candidateId ) + '/bookmarks',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);

				if ( ! response.ok ) {
					state.error = 'Could not load saved jobs.';
					return;
				}

				state.bookmarks = yield response.json();
			} catch {
				state.error = 'Connection error. Please check your network and try again.';
			} finally {
				state.loading = false;
			}
		},

		*switchToResumes() {
			const { state } = store( 'wcb-candidate-dashboard' );
			state.tab   = 'resumes';
			state.error = '';

			// Load resumes on first switch.
			if ( state.resumes.length ) {
				return;
			}

			state.loading = true;

			try {
				const response = yield fetch(
					state.apiBase + '/candidates/' + String( state.candidateId ) + '/resumes',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);

				if ( ! response.ok ) {
					state.error = 'Could not load your resumes.';
					return;
				}

				state.resumes = yield response.json();
			} catch {
				state.error = 'Connection error. Please check your network and try again.';
			} finally {
				state.loading = false;
			}
		},

		*unbookmark() {
			const { state } = store( 'wcb-candidate-dashboard' );
			const ctx        = getContext();
			const bookmark   = ctx.bookmark;

			try {
				const response = yield fetch(
					state.apiBase + '/jobs/' + String( bookmark.id ) + '/bookmark',
					{
						method: 'POST',
						headers: {
							'X-WP-Nonce':   state.nonce,
							'Content-Type': 'application/json',
						},
					}
				);

				if ( ! response.ok ) {
					state.error = 'Could not remove saved job. Please try again.';
					return;
				}

				// Remove bookmark from list.
				state.bookmarks = state.bookmarks.filter( function( b ) {
					return b.id !== bookmark.id;
				} );
			} catch {
				state.error = 'Connection error. Please check your network and try again.';
			}
		},

		*createResume() {
			const { state } = store( 'wcb-candidate-dashboard' );

			// eslint-disable-next-line no-alert
			const title = window.prompt( 'Resume title (e.g. "Software Developer")' );
			if ( ! title ) {
				return;
			}

			state.loading = true;
			state.error   = '';

			try {
				const response = yield fetch(
					state.apiBase + '/candidates/' + String( state.candidateId ) + '/resumes',
					{
						method: 'POST',
						headers: {
							'X-WP-Nonce':   state.nonce,
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( { title } ),
					}
				);

				if ( ! response.ok ) {
					state.error = 'Could not create resume. Please try again.';
					return;
				}

				const resume = yield response.json();
				state.resumes = [ resume, ...state.resumes ];

				// Navigate to the resume builder for the new resume.
				if ( state.resumeBuilderUrl ) {
					window.location.href = state.resumeBuilderUrl + '?resume_id=' + String( resume.id );
				}
			} catch {
				state.error = 'Connection error. Please check your network and try again.';
			} finally {
				state.loading = false;
			}
		},

		*deleteResume() {
			const { state } = store( 'wcb-candidate-dashboard' );
			const ctx        = getContext();
			const resume     = ctx.resume;

			// eslint-disable-next-line no-alert
			if ( ! window.confirm( 'Delete "' + resume.title + '"? This cannot be undone.' ) ) {
				return;
			}

			try {
				const response = yield fetch(
					state.apiBase + '/resumes/' + String( resume.id ),
					{
						method: 'DELETE',
						headers: { 'X-WP-Nonce': state.nonce },
					}
				);

				if ( ! response.ok ) {
					state.error = 'Could not delete resume. Please try again.';
					return;
				}

				state.resumes = state.resumes.filter( function( r ) {
					return r.id !== resume.id;
				} );
			} catch {
				state.error = 'Connection error. Please check your network and try again.';
			}
		},
	},
} );
