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
 *   openResumeEditor      — navigate to resume builder for the current resume.
 *   deleteResume          — DELETE /resumes/{id}.
 *
 * @package WP_Career_Board
 */
import { store, getContext } from '@wordpress/interactivity';

const { state, actions } = store( 'wcb-candidate-dashboard', {
	state: {
		get isTabApplications() {
			return state.tab === 'applications';
		},
		get isTabBookmarks() {
			return state.tab === 'bookmarks';
		},
		get isTabResumes() {
			return state.tab === 'resumes';
		},
		get isAtResumesCap() {
			return state.maxResumes > 0 && state.resumeCount >= state.maxResumes;
		},
		get resumeCapLabel() {
			return state.maxResumes > 0 ? state.resumeCount + '/' + state.maxResumes + ' resumes' : '';
		},

		// Sidebar display.
		get candidateInitials() {
			const n = state.candidateName;
			return n
				? n.split( ' ' ).map( ( p ) => p[ 0 ] ).slice( 0, 2 ).join( '' ).toUpperCase()
				: '?';
		},

		// Nav badges.
		get appsCount() {
			return state.applications.length;
		},
		get bookmarksCount() {
			return state.bookmarks.length;
		},

		// Empty / populated state per panel.
		get hasApplications() {
			return ! state.loading && state.applications.length > 0;
		},
		get noApplications() {
			return ! state.loading && ! state.error && state.applications.length === 0;
		},
		get hasBookmarks() {
			return ! state.loading && state.bookmarks.length > 0;
		},
		get noBookmarks() {
			return ! state.loading && ! state.error && state.bookmarks.length === 0;
		},

		// Bell notification getters.
		get hasBellNotifications() {
			return state.bellNotifications.length > 0;
		},
	},

	actions: {
		*init() {
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

			yield actions.fetchBellNotifications();
		},

		switchToApplications() {
			state.tab   = 'applications';
			state.error = '';
		},

		*switchToBookmarks() {
			state.tab   = 'bookmarks';
			state.error = '';

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
			state.tab   = 'resumes';
			state.error = '';

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
			const ctx      = getContext();
			const bookmark = ctx.bookmark;

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

				state.bookmarks = state.bookmarks.filter( function( b ) {
					return b.id !== bookmark.id;
				} );
			} catch {
				state.error = 'Connection error. Please check your network and try again.';
			}
		},

		openResumeEditor() {
			const ctx = getContext();
			if ( ! ctx.resume || ! state.resumeBuilderUrl ) {
				return;
			}
			window.location.href = state.resumeBuilderUrl + '?resume_id=' + String( ctx.resume.id );
		},

		*createResume() {
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
				state.resumeCount = state.resumeCount + 1;

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
			const ctx    = getContext();
			const resume = ctx.resume;

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
				state.resumeCount = Math.max( 0, state.resumeCount - 1 );
			} catch {
				state.error = 'Connection error. Please check your network and try again.';
			}
		},

		*toggleBell() {
			state.bellOpen = ! state.bellOpen;
			if ( state.bellOpen && state.bellNotifications.length === 0 ) {
				yield actions.fetchBellNotifications();
			}
		},

		*fetchBellNotifications() {
			state.bellLoading = true;
			try {
				const res = yield fetch( state.apiBase + '/notifications?per_page=20', {
					headers: { 'X-WP-Nonce': state.nonce },
				} );
				if ( res.ok ) {
					const data              = yield res.json();
					state.bellNotifications = data.notifications || [];
					state.bellUnreadCount   = data.unread_count  || 0;
				}
			} finally {
				state.bellLoading = false;
			}
		},

		*markBellRead() {
			const ctx = getContext();
			const id  = ctx.notif?.id;
			if ( ! id ) {
				return;
			}
			yield fetch( state.apiBase + '/notifications/' + String( id ) + '/read', {
				method:  'PATCH',
				headers: { 'X-WP-Nonce': state.nonce },
			} );
			const notif = state.bellNotifications.find( ( n ) => n.id === id );
			if ( notif && ! notif.is_read ) {
				notif.is_read         = true;
				state.bellUnreadCount = Math.max( 0, state.bellUnreadCount - 1 );
			}
		},

		*markAllRead() {
			yield fetch( state.apiBase + '/notifications/read-all', {
				method:  'POST',
				headers: { 'X-WP-Nonce': state.nonce },
			} );
			state.bellNotifications.forEach( ( n ) => {
				n.is_read = true;
			} );
			state.bellUnreadCount = 0;
		},
	},
} );
