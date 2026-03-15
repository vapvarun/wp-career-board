/**
 * WP Career Board — candidate-dashboard block Interactivity API store.
 *
 * Actions:
 *   init                  — fetch applications on mount (via data-wp-init).
 *   switchToApplications  — activate My Applications tab.
 *   switchToBookmarks     — activate Saved Jobs tab (loads bookmarks if empty).
 *   unbookmark            — POST to /jobs/{id}/bookmark to toggle off.
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
	},
} );
