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
 *   withdrawApplication   — DELETE /applications/{id}; removes from list.
 *
 * @package WP_Career_Board
 */
import { store, getContext } from '@wordpress/interactivity';

/**
 * Convert an alert's filters JSON string/object into readable pill labels.
 */
function buildFilterPills( filters ) {
	if ( typeof filters === 'string' ) {
		try {
			filters = JSON.parse( filters );
		} catch {
			return [];
		}
	}
	if ( ! filters || typeof filters !== 'object' ) {
		return [];
	}
	const pills = [];
	if ( filters.category ) pills.push( filters.category );
	if ( filters.type ) pills.push( filters.type );
	if ( filters.location ) pills.push( filters.location );
	if ( filters.remote ) pills.push( state.strings.filterRemote );
	if ( filters.salary_min || filters.salary_max ) {
		const min = filters.salary_min ? '$' + Number( filters.salary_min ).toLocaleString() : '';
		const max = filters.salary_max ? '$' + Number( filters.salary_max ).toLocaleString() : '';
		pills.push( min && max ? min + '–' + max : min || max );
	}
	return pills;
}

const { state, actions } = store( 'wcb-candidate-dashboard', {
	state: {
		navOpen: false,
		get activeTabLabel() {
			const map = {
				overview:           state.strings.tabOverview,
				applications:       state.strings.tabApplications,
				bookmarks:          state.strings.tabBookmarks,
				resumes:            state.strings.tabResumes,
				alerts:             state.strings.tabAlerts,
				'resume-builder':   state.strings.tabResumeBuilder,
				profile:            state.strings.tabProfile,
				settings:           state.strings.tabSettings,
			};
			return map[ state.tab ] || state.strings.tabDashboard;
		},
		get isTabApplications() {
			return state.tab === 'applications';
		},
		get isTabBookmarks() {
			return state.tab === 'bookmarks';
		},
		get isTabResumes() {
			return state.tab === 'resumes';
		},
		get isTabResumeBuilder() {
			return state.tab === 'resume-builder';
		},
		get isTabOverview() {
			return state.tab === 'overview';
		},
		get isTabAlerts() {
			return state.tab === 'alerts';
		},
		get isTabProfile() {
			return state.tab === 'profile';
		},
		get isTabSettings() {
			return state.tab === 'settings';
		},
		get alertsCount() {
			return state.alerts.length;
		},
		get hasAlerts() {
			return ! state.alertsLoading && state.alerts.length > 0;
		},
		get noAlerts() {
			return ! state.alertsLoading && state.alerts.length === 0;
		},
		get isAtResumesCap() {
			return state.maxResumes > 0 && state.resumeCount >= state.maxResumes;
		},
		get resumeCapLabel() {
			return state.maxResumes > 0 ? state.resumeCount + '/' + state.maxResumes + ' ' + state.strings.resumesUnit : '';
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

		// Overview computed data.
		get overviewRecentApps() {
			return state.applications.slice( 0, 4 );
		},
		get hasRecentApps() {
			return ! state.loading && state.overviewRecentApps.length > 0;
		},
		get noRecentApps() {
			return ! state.loading && state.overviewRecentApps.length === 0;
		},
		/**
		 * Surface the welcome card only when the user has zero of every key signal.
		 *
		 * Render-side signals are pulled from initial state (savedJobsCount,
		 * resumeCount) so the card displays on first paint without waiting for
		 * the applications API call. Once any signal flips to non-zero (apply,
		 * save, build resume, set up alert) the card hides automatically.
		 */
		get isFirstTime() {
			return ! state.loading
				&& state.applications.length === 0
				&& Number( state.savedJobsCount ) === 0
				&& Number( state.resumeCount ) === 0
				&& ( ! state.alertsCount || Number( state.alertsCount ) === 0 );
		},
		get overviewShortlistedCount() {
			return state.applications.filter( ( a ) => a.status === 'shortlisted' ).length;
		},
		get overviewRecentSavedJobs() {
			return state.bookmarks.slice( 0, 3 );
		},
		get hasRecentSavedJobs() {
			return state.overviewRecentSavedJobs.length > 0;
		},
		get noRecentSavedJobs() {
			return state.overviewRecentSavedJobs.length === 0;
		},
	},

	actions: {
		*init() {
			// Restore last active tab from sessionStorage (skip if URL forces resume-builder).
			if ( state.tab === 'overview' ) {
				const saved = sessionStorage.getItem( 'wcb_candidate_tab' );
				if ( saved ) {
					state.tab = saved;
				}
			}

			state.loading = true;
			state.error   = '';

			try {
				const response = yield fetch(
					state.apiBase + '/candidates/' + String( state.candidateId ) + '/applications',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);

				if ( ! response.ok ) {
					state.error = state.strings.errLoadApplications;
					return;
				}

				const data = yield response.json();
				// Envelope since 1.1.0; tolerate the legacy bare-array shape.
				state.applications = Array.isArray( data ) ? data : ( data?.applications ?? [] );
			} catch {
				state.error = state.strings.errConnectionFull;
			} finally {
				state.loading = false;
			}

			// Prefetch bookmarks so the Overview panel can display recent saved jobs.
			try {
				const bmResponse = yield fetch(
					state.apiBase + '/candidates/' + String( state.candidateId ) + '/bookmarks',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);
				if ( bmResponse.ok ) {
					const bmData = yield bmResponse.json();
					state.bookmarks = Array.isArray( bmData ) ? bmData : ( bmData?.bookmarks ?? [] );
				}
			} catch {
				// Non-critical — overview saved jobs panel will show empty state.
			}

			// Prefetch alerts count for Overview stat card and nav badge.
			try {
				const alertsRes = yield fetch(
					state.apiBase + '/alerts',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);
				if ( alertsRes.ok ) {
					const raw = yield alertsRes.json();
					state.alerts = raw.map( ( a ) => ( {
						id:          a.id,
						label:       a.search_query || state.strings.alertLabelAllJobs,
						frequency:   a.frequency,
						filterPills: buildFilterPills( a.filters ),
					} ) );
				}
			} catch {
				// Non-critical.
			}

			// If resumes tab was restored from sessionStorage, fetch resumes now.
			if ( state.tab === 'resumes' ) {
				yield actions.switchToResumes();
			}

			yield actions.fetchBellNotifications();
		},

		toggleNav() {
			state.navOpen = ! state.navOpen;
		},

		switchToOverview() {
			state.tab     = 'overview';
			state.navOpen = false;
			sessionStorage.removeItem( 'wcb_candidate_tab' );
		},

		switchToApplications() {
			state.tab     = 'applications';
			state.error   = '';
			state.navOpen = false;
			sessionStorage.setItem( 'wcb_candidate_tab', 'applications' );
		},

		*switchToBookmarks() {
			state.tab     = 'bookmarks';
			state.error   = '';
			state.navOpen = false;
			sessionStorage.setItem( 'wcb_candidate_tab', 'bookmarks' );

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
					state.error = state.strings.errLoadBookmarks;
					return;
				}

				const data = yield response.json();
				state.bookmarks = Array.isArray( data ) ? data : ( data?.bookmarks ?? [] );
			} catch {
				state.error = state.strings.errConnectionFull;
			} finally {
				state.loading = false;
			}
		},

		switchToResumeBuilder() {
			state.tab     = 'resume-builder';
			state.navOpen = false;
			sessionStorage.setItem( 'wcb_candidate_tab', 'resume-builder' );
		},

		switchToProfile() {
			state.tab         = 'profile';
			state.navOpen     = false;
			state.profileSaved = false;
			sessionStorage.setItem( 'wcb_candidate_tab', 'profile' );
		},

		switchToSettings() {
			state.tab     = 'settings';
			state.navOpen = false;
			sessionStorage.setItem( 'wcb_candidate_tab', 'settings' );
		},

		updateProfileBio( event ) {
			state.profileBio   = event.target.value;
			state.profileSaved = false;
		},

		*saveProfile() {
			state.profileSaving = true;
			state.profileSaved  = false;
			state.error         = '';
			try {
				const response = yield fetch(
					state.apiBase + '/candidates/' + String( state.candidateId ),
					{
						method: 'PUT',
						headers: {
							'X-WP-Nonce':   state.nonce,
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( { bio: state.profileBio } ),
					}
				);
				if ( response.ok ) {
					state.profileSaved = true;
				} else {
					state.error = 'Could not save profile. Please try again.';
				}
			} catch {
				state.error = 'Connection error. Please try again.';
			} finally {
				state.profileSaving = false;
			}
		},

		*switchToAlerts() {
			state.tab     = 'alerts';
			state.error   = '';
			state.navOpen = false;
			sessionStorage.setItem( 'wcb_candidate_tab', 'alerts' );

			if ( state.alerts.length ) {
				return;
			}

			state.alertsLoading = true;

			try {
				const response = yield fetch(
					state.apiBase + '/alerts',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);

				if ( ! response.ok ) {
					state.error = state.strings.errLoadAlerts;
					return;
				}

				const raw = yield response.json();
				state.alerts = raw.map( ( a ) => ( {
					id:          a.id,
					label:       a.search_query || state.strings.alertLabelAllJobs,
					frequency:   a.frequency,
					filterPills: buildFilterPills( a.filters ),
				} ) );
			} catch {
				state.error = state.strings.errConnectionShort;
			} finally {
				state.alertsLoading = false;
			}
		},

		*deleteAlert() {
			const ctx = getContext();
			const alertId = ctx.alert.id;

			try {
				const response = yield fetch(
					state.apiBase + '/alerts/' + String( alertId ),
					{
						method:  'DELETE',
						headers: { 'X-WP-Nonce': state.nonce },
					}
				);

				if ( response.ok ) {
					state.alerts = state.alerts.filter( ( a ) => a.id !== alertId );
				}
			} catch {
				// Silent — row stays visible.
			}
		},

		*changeAlertFrequency( event ) {
			const ctx       = getContext();
			const alertId   = ctx.alert.id;
			const frequency = event.target.value;

			ctx.alert.frequency = frequency;

			try {
				yield fetch(
					state.apiBase + '/alerts/' + String( alertId ),
					{
						method:  'PATCH',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce':   state.nonce,
						},
						body: JSON.stringify( { frequency } ),
					}
				);
			} catch {
				// Silent — optimistic update already applied.
			}
		},

		toggleNewResumeForm() {
			state.showNewResumeForm = ! state.showNewResumeForm;
			state.newResumeTitle   = '';
		},

		setNewResumeTitle( event ) {
			state.newResumeTitle = event.target.value;
		},

		*switchToResumes() {
			state.tab     = 'resumes';
			state.error   = '';
			state.navOpen = false;
			sessionStorage.setItem( 'wcb_candidate_tab', 'resumes' );

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
					state.error = state.strings.errLoadResumes;
					return;
				}

				state.resumes = yield response.json();
			} catch {
				state.error = state.strings.errConnectionFull;
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
					state.error = state.strings.errRemoveBookmark;
					return;
				}

				state.bookmarks = state.bookmarks.filter( function( b ) {
					return b.id !== bookmark.id;
				} );
			} catch {
				state.error = state.strings.errConnectionFull;
			}
		},

		openResumeEditor() {
			const ctx = getContext();
			if ( ! ctx.resume ) {
				return;
			}
			// When embedded resume builder is active, reload current page with resume_id param.
			if ( state.resumeBuilderEmbedded ) {
				window.location.href = state.dashboardUrl + '?resume_id=' + String( ctx.resume.id );
			}
		},

		*createResume() {
			const title = state.newResumeTitle.trim();
			if ( ! title ) {
				return;
			}
			state.showNewResumeForm = false;
			state.newResumeTitle    = '';

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
					state.error = state.strings.errCreateResume;
					return;
				}

				const resume = yield response.json();
				state.resumes = [ resume, ...state.resumes ];
				state.resumeCount = state.resumeCount + 1;

				if ( state.resumeBuilderEmbedded ) {
					window.location.href = state.dashboardUrl + '?resume_id=' + String( resume.id );
				}
			} catch {
				state.error = state.strings.errConnectionFull;
			} finally {
				state.loading = false;
			}
		},

		*uploadResumeFile( event ) {
			const file = event.target?.files?.[ 0 ];
			if ( ! file ) {
				return;
			}
			event.target.value = '';

			state.loading = true;
			state.error   = '';

			try {
				// Upload the file and get an attachment_id back.
				const formData = new FormData();
				formData.append( 'resume_file', file );

				const uploadResp = yield fetch(
					state.apiBase + '/candidates/resume-upload',
					{
						method: 'POST',
						headers: { 'X-WP-Nonce': state.nonce },
						body: formData,
					}
				);
				const uploadData = yield uploadResp.json();

				if ( ! uploadResp.ok ) {
					state.error = uploadData.message || 'Upload failed.';
					return;
				}

				const attachmentId = uploadData.attachment_id;
				if ( ! attachmentId ) {
					state.error = 'Upload failed: no attachment returned.';
					return;
				}

				// Wrap the uploaded file in a wcb_resume post so it shows up
				// in the candidate's resume list. Without this step the file
				// uploads silently and the user has nothing to click on.
				const title = file.name.replace( /\.[^.]+$/, '' ) || 'Uploaded CV';
				const createResp = yield fetch(
					state.apiBase + '/candidates/' + String( state.candidateId ) + '/resumes',
					{
						method: 'POST',
						headers: {
							'X-WP-Nonce':   state.nonce,
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( { title, attachment_id: attachmentId } ),
					}
				);

				if ( ! createResp.ok ) {
					state.error = state.strings.errCreateResume || 'Could not create resume.';
					return;
				}

				const resume     = yield createResp.json();
				state.resumes    = [ resume, ...state.resumes ];
				state.resumeCount = state.resumeCount + 1;
			} catch {
				state.error = 'Failed to upload resume file. Please try again.';
			} finally {
				state.loading = false;
			}
		},

		requestDeleteConfirm() {
			getContext().confirmingDelete = true;
		},

		cancelDelete() {
			getContext().confirmingDelete = false;
		},

		*deleteResume() {
			const ctx    = getContext();
			const resume = ctx.resume;

			try {
				const response = yield fetch(
					state.apiBase + '/resumes/' + String( resume.id ),
					{
						method: 'DELETE',
						headers: { 'X-WP-Nonce': state.nonce },
					}
				);

				if ( ! response.ok ) {
					ctx.confirmingDelete = false;
					state.error = state.strings.errDeleteResume;
					return;
				}

				state.resumes = state.resumes.filter( function( r ) {
					return r.id !== resume.id;
				} );
				state.resumeCount = Math.max( 0, state.resumeCount - 1 );
			} catch {
				ctx.confirmingDelete = false;
				state.error = state.strings.errConnectionFull;
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

		*withdrawApplication() {
			const ctx         = getContext();
			const application = ctx.application;

			try {
				yield window.wcbConfirm( {
					title:       state.strings.confirmWithdrawTitle,
					message:     state.strings.confirmWithdrawMsg,
					confirmText: state.strings.withdraw,
					destructive: true,
				} );
			} catch ( cancelled ) {
				return;
			}

			try {
				const response = yield fetch(
					state.apiBase + '/applications/' + String( application.id ),
					{
						method:  'DELETE',
						headers: { 'X-WP-Nonce': state.nonce },
					}
				);

				if ( ! response.ok ) {
					state.error = state.strings.errWithdraw;
					return;
				}

				state.applications = state.applications.filter( function( a ) {
					return a.id !== application.id;
				} );
			} catch {
				state.error = state.strings.errConnectionFull;
			}
		},

		/**
		 * GDPR self-service: request a data export. Hits the privacy REST route
		 * which calls wp_create_user_request() server-side. Visual feedback is
		 * a one-shot toggle on state.privacyExportRequested — the actual export
		 * is processed by the site admin.
		 */
		*requestExport() {
			yield this.runPrivacyRequest( 'export' );
		},

		/**
		 * GDPR self-service: request account erasure. Confirms first via the
		 * shared modal, then hits the same privacy REST route.
		 */
		*requestErase() {
			try {
				yield window.wcbConfirm( {
					title:       state.strings.confirmEraseTitle,
					message:     state.strings.confirmEraseMsg,
					confirmText: state.strings.confirmEraseConfirm,
					destructive: true,
				} );
			} catch ( cancelled ) {
				return;
			}
			yield this.runPrivacyRequest( 'erase' );
		},

		/**
		 * Internal: shared POST helper for both export and erase requests.
		 *
		 * @param {string} action 'export' or 'erase'.
		 */
		*runPrivacyRequest( action ) {
			if ( state.privacyBusy ) {
				return;
			}
			state.privacyBusy  = true;
			state.privacyError = '';

			try {
				const response = yield fetch(
					state.apiBase + '/candidates/me/privacy/' + String( action ),
					{
						method:  'POST',
						headers: { 'X-WP-Nonce': state.nonce },
					}
				);
				if ( ! response.ok ) {
					state.privacyError = state.strings.errPrivacy;
					return;
				}
				if ( 'export' === action ) {
					state.privacyExportRequested = true;
				} else {
					state.privacyEraseRequested = true;
				}
			} catch {
				state.privacyError = state.strings.errConnectionFull;
			} finally {
				state.privacyBusy = false;
			}
		},
	},
} );
