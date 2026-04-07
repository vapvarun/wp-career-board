/**
 * WP Career Board — employer-dashboard block Interactivity API store.
 *
 * @package WP_Career_Board
 */
import { store, getContext } from '@wordpress/interactivity';

const { state, actions } = store( 'wcb-employer-dashboard', {
	state: {
		navOpen: false,
		get activeTabLabel() {
			const map = {
				overview:     state.strings.overview,
				jobs:         state.strings.myJobs,
				applications: state.strings.applications,
				company:      state.strings.profile,
				'post-job':   state.strings.postAJob,
			};
			return map[ state.currentView ] || state.strings.dashboard;
		},

		// View getters.
		get isViewOverview() {
			return state.currentView === 'overview';
		},
		get isViewJobs() {
			return state.currentView === 'jobs';
		},
		get isViewApplications() {
			return state.currentView === 'applications';
		},
		get isViewCompany() {
			return state.currentView === 'company';
		},
		get isViewPostJob() {
			return state.currentView === 'post-job';
		},
		get isViewSettings() {
			return state.currentView === 'settings';
		},

		// Jobs list.
		get hasJobs() {
			return ! state.loading && ! state.noCompany && state.filteredJobs.length > 0;
		},
		get noJobs() {
			return ! state.loading && ! state.noCompany && ! state.error && state.filteredJobs.length === 0;
		},
		get totalJobs() {
			return state.jobs.length || state.ssrTotalJobs || 0;
		},
		get publishedJobs() {
			return state.jobs.length
				? state.jobs.filter( ( j ) => j.status === 'publish' ).length
				: ( state.ssrPublishedJobs || 0 );
		},

		// Job filter.
		get filteredJobs() {
			let jobs = state.jobs;
			const f  = state.jobFilter;
			if ( f === 'live' )         jobs = jobs.filter( ( j ) => j.status === 'publish' );
			else if ( f === 'draft' )   jobs = jobs.filter( ( j ) => j.status === 'draft' );
			else if ( f === 'pending' ) jobs = jobs.filter( ( j ) => j.status === 'pending' );
			else if ( f === 'closed' )  jobs = jobs.filter( ( j ) => j.isClosed );
			if ( state.jobSearch ) {
				const q = state.jobSearch.toLowerCase();
				jobs = jobs.filter( ( j ) => j.title.toLowerCase().includes( q ) );
			}
			return jobs;
		},
		get isFilterAll() {
			return state.jobFilter === 'all';
		},
		get isFilterLive() {
			return state.jobFilter === 'live';
		},
		get isFilterDraft() {
			return state.jobFilter === 'draft';
		},
		get isFilterPending() {
			return state.jobFilter === 'pending';
		},
		get isFilterClosed() {
			return state.jobFilter === 'closed';
		},

		// Jobs with applications (for selector list).
		get jobsWithApps() {
			return state.jobs.filter( ( j ) => j.appCount > 0 );
		},
		get hasJobsWithApps() {
			return state.jobsWithApps.length > 0;
		},
		get filteredJobsWithApps() {
			const q = ( state.appsJobSearch || '' ).toLowerCase().trim();
			return q
				? state.jobsWithApps.filter( ( j ) => j.title.toLowerCase().includes( q ) )
				: state.jobsWithApps;
		},
		get appsJobNoMatch() {
			return state.hasJobsWithApps && ( state.appsJobSearch || '' ).trim() !== '' && state.filteredJobsWithApps.length === 0;
		},
		get appsJobSelectorHint() {
			const total    = state.jobsWithApps.length;
			const filtered = state.filteredJobsWithApps.length;
			if ( ( state.appsJobSearch || '' ).trim() === '' ) {
				return total + state.strings.jobSingular + ( total === 1 ? '' : 's' ) + state.strings.jobsWithApps;
			}
			return filtered + state.strings.jobsOf + total + state.strings.jobsPlural;
		},

		// Context getter — inside data-wp-each--job loop for apps selector.
		get isSelectedAppsJob() {
			const ctx = getContext();
			return ctx.job?.id === state.appsJobId;
		},

		// Applications.
		get totalApps() {
			if ( state.allApplications.length > 0 ) {
				return state.allApplications.length;
			}
			if ( state.jobs.length > 0 ) {
				return state.jobs.reduce( ( sum, j ) => sum + j.appCount, 0 );
			}
			return state.ssrTotalApps || 0;
		},
		get hasApplications() {
			return state.appsJobId > 0 && ! state.appsLoading && state.applications.length > 0;
		},
		get noJobSelected() {
			return ! state.appsJobId;
		},
		get noApplications() {
			return state.appsJobId > 0 && ! state.appsLoading && ! state.appsError && state.applications.length === 0;
		},

		// Application filter.
		get filteredApps() {
			const f = state.appsFilter;
			return f === 'all'
				? state.applications
				: state.applications.filter( ( a ) => a.status === f );
		},
		get isAppsFilterAll() {
			return state.appsFilter === 'all';
		},
		get isAppsFilterSubmitted() {
			return state.appsFilter === 'submitted';
		},
		get isAppsFilterReviewing() {
			return state.appsFilter === 'reviewing';
		},
		get isAppsFilterShortlisted() {
			return state.appsFilter === 'shortlisted';
		},
		get isAppsFilterRejected() {
			return state.appsFilter === 'rejected';
		},
		get isAppsFilterHired() {
			return state.appsFilter === 'hired';
		},

		// Per-status counts — computed from already-loaded applications, no extra REST calls.
		get appsCountAll() {
			return state.applications.length || '';
		},
		get appsCountSubmitted() {
			return state.applications.filter( ( a ) => a.status === 'submitted' ).length || '';
		},
		get appsCountReviewing() {
			return state.applications.filter( ( a ) => a.status === 'reviewing' ).length || '';
		},
		get appsCountShortlisted() {
			return state.applications.filter( ( a ) => a.status === 'shortlisted' ).length || '';
		},
		get appsCountRejected() {
			return state.applications.filter( ( a ) => a.status === 'rejected' ).length || '';
		},
		get appsCountHired() {
			return state.applications.filter( ( a ) => a.status === 'hired' ).length || '';
		},

		// Selected applicant detail.
		get noAppSelected() {
			return state.selectedAppId === null;
		},
		get selectedApp() {
			return state.selectedAppId === null
				? null
				: state.applications.find( ( a ) => a.id === state.selectedAppId ) ?? null;
		},
		get selectedAppName() {
			return state.selectedApp?.applicant_name ?? '';
		},
		get selectedAppEmail() {
			return state.selectedApp?.applicant_email ?? '';
		},
		get selectedAppDate() {
			return state.selectedApp?.submitted_at ?? '';
		},
		get selectedAppStatus() {
			return state.selectedApp?.status ?? '';
		},
		get selectedAppCoverLetter() {
			return state.selectedApp?.cover_letter ?? '';
		},
		get selectedAppResumeUrl() {
			return state.selectedApp?.resume_url ?? null;
		},
		get selectedAppInitials() {
			const n = state.selectedAppName;
			return n
				? n.split( ' ' ).map( ( p ) => p[ 0 ] ).slice( 0, 2 ).join( '' ).toUpperCase()
				: '?';
		},

		// Context getters — inside data-wp-each--app loop.
		get isSelectedApp() {
			const ctx = getContext();
			return ctx.app?.id === state.selectedAppId;
		},
		get isUnread() {
			const ctx = getContext();
			return ctx.app?.status === 'submitted';
		},
		get applicantRowLabel() {
			const ctx = getContext();
			const name = ctx.app?.applicant_name || '';
			return state.strings.viewAppFrom + name;
		},

		// Overview panel getters.
		get overviewRecentApps() {
			return [ ...state.allApplications ]
				.sort( ( a, b ) => new Date( b.submitted_at ) - new Date( a.submitted_at ) )
				.slice( 0, 4 )
				.map( ( a ) => ( {
					...a,
					initials: a.applicant_name
						? a.applicant_name.split( ' ' ).map( ( p ) => p[ 0 ] ).slice( 0, 2 ).join( '' ).toUpperCase()
						: '?',
				} ) );
		},
		get hasRecentApps() {
			return state.overviewRecentApps.length > 0;
		},
		get noRecentApps() {
			return state.overviewRecentApps.length === 0;
		},
		get overviewActiveJobs() {
			return state.jobs.filter( ( j ) => j.status === 'publish' ).slice( 0, 3 );
		},
		get hasActiveJobs() {
			return state.overviewActiveJobs.length > 0;
		},
		get noActiveJobs() {
			return state.overviewActiveJobs.length === 0;
		},
		get newThisWeek() {
			if ( state.allApplications.length === 0 ) {
				return state.ssrNewThisWeek || 0;
			}
			const cutoff = Date.now() - 7 * 24 * 60 * 60 * 1000;
			return state.allApplications.filter(
				( a ) => new Date( a.submitted_at ).getTime() > cutoff
			).length;
		},

		// Company helpers.
		get companyInitials() {
			const n = state.companyName;
			return n
				? n.split( ' ' ).map( ( p ) => p[ 0 ] ).slice( 0, 2 ).join( '' ).toUpperCase()
				: '';
		},
		get companyDescExcerpt() {
			const d = state.companyDesc || '';
			return d.length > 120 ? d.slice( 0, 120 ) + '\u2026' : d;
		},

		get logoUploadLabel() {
			if ( state.logoUploading ) return state.strings.logoUploading;
			return state.companyLogoUrl ? state.strings.logoChange : state.strings.logoUpload;
		},

		// Legacy heading used by some templates.
		get appsHeading() {
			return state.appsJobTitle
				? state.strings.appsHeadingPrefix + state.appsJobTitle
				: state.strings.appsHeadingDefault;
		},

		// Bell notification getters.
		get hasBellNotifications() {
			return state.bellNotifications.length > 0;
		},
	},

	actions: {
		*init() {
			// Restore last active view from sessionStorage (skip if URL already dictates view).
			if ( state.currentView === 'overview' ) {
				const saved = sessionStorage.getItem( 'wcb_employer_view' );
				if ( saved ) {
					state.currentView = saved;
				}
				const savedJob = Number( sessionStorage.getItem( 'wcb_employer_apps_job' ) );
				if ( savedJob > 0 && state.currentView === 'applications' ) {
					state.appsJobId = savedJob;
				}
			}

			state.loading = true;
			state.error   = '';

			try {
				// Use /me/jobs when no company yet — employers can post jobs before
				// creating a company profile, and those jobs must still appear.
				const jobsBase = state.companyId
					? state.apiBase + '/employers/' + String( state.companyId ) + '/jobs'
					: state.apiBase + '/employers/me/jobs';
				const jobsUrl = new URL( jobsBase );
				jobsUrl.searchParams.set( 'per_page', '50' );

				const appsUrl = state.companyId
					? state.apiBase + '/employers/' + String( state.companyId ) + '/applications'
					: null;

				const headers = { 'X-WP-Nonce': state.nonce };

				const fetchPromises = [ fetch( jobsUrl.toString(), { headers } ) ];
				if ( appsUrl ) {
					fetchPromises.push( fetch( appsUrl, { headers } ) );
				}

				const [ jobsResp, allAppsResp ] = yield Promise.all( fetchPromises );

				if ( ! jobsResp.ok ) {
					state.error = state.strings.errorLoadJobs;
					return;
				}

				const jobs  = yield jobsResp.json();
				state.jobs  = jobs.map( ( j ) => ( {
					...j,
					appsUrl:  j.appCount > 0 ? state.dashboardUrl + '?job_apps=' + String( j.id ) : null,
					isClosed: j.status !== 'publish',
					isDraft:  j.status === 'draft',
				} ) );

				if ( allAppsResp && allAppsResp.ok ) {
					state.allApplications = yield allAppsResp.json();
				}
			} catch {
				state.error = state.strings.errorConnection;
			} finally {
				state.loading = false;
			}

			if ( state.appsJobId > 0 ) {
				yield actions.loadApplications();
			}

			yield actions.fetchBellNotifications();
		},

		toggleNav() {
			state.navOpen = ! state.navOpen;
		},

		switchToOverview() {
			state.currentView = 'overview';
			state.navOpen     = false;
			sessionStorage.removeItem( 'wcb_employer_view' );
		},

		switchToJobs() {
			state.currentView = 'jobs';
			state.error       = '';
			state.navOpen     = false;
			sessionStorage.setItem( 'wcb_employer_view', 'jobs' );
		},

		switchToApplications() {
			state.currentView = 'applications';
			state.navOpen     = false;
			sessionStorage.setItem( 'wcb_employer_view', 'applications' );
		},

		switchToCompany() {
			state.currentView = 'company';
			state.saved       = false;
			state.error       = '';
			state.navOpen     = false;
			sessionStorage.setItem( 'wcb_employer_view', 'company' );
		},

		switchToSettings() {
			state.currentView = 'settings';
			state.error       = '';
			state.navOpen     = false;
			sessionStorage.setItem( 'wcb_employer_view', 'settings' );
		},

		switchToPostJob() {
			state.currentView = 'post-job';
			state.navOpen     = false;
			sessionStorage.setItem( 'wcb_employer_view', 'post-job' );
		},

		setJobFilter( event ) {
			const f = event.target.dataset.wcbFilter;
			if ( f ) {
				state.jobFilter = f;
			}
		},

		setJobSearch( event ) {
			state.jobSearch = event.target.value;
		},

		selectApplicant( event ) {
			const id = parseInt( event.currentTarget.dataset.wcbAppId, 10 );
			state.selectedAppId = Number.isNaN( id ) ? null : id;
		},

		handleRowKeydown( event ) {
			if ( event.key === 'Enter' || event.key === ' ' ) {
				event.preventDefault();
				const id = parseInt( event.currentTarget.dataset.wcbAppId, 10 );
				state.selectedAppId = Number.isNaN( id ) ? null : id;
			}
		},

		setAppsFilter( event ) {
			const f = event.target.dataset.wcbFilter;
			if ( f ) {
				state.appsFilter = f;
			}
		},

		*switchAppsJob( event ) {
			const id = parseInt( event.currentTarget.dataset.wcbJobId, 10 );
			if ( Number.isNaN( id ) ) {
				return;
			}
			state.appsJobId     = id;
			state.selectedAppId = null;
			state.currentView   = 'applications';
			sessionStorage.setItem( 'wcb_employer_view', 'applications' );
			sessionStorage.setItem( 'wcb_employer_apps_job', String( id ) );
			yield actions.loadApplications();
		},

		setAppsJobSearch( event ) {
			state.appsJobSearch = event.target.value;
		},

		*loadApplications() {
			if ( ! state.appsJobId ) {
				return;
			}

			state.appsLoading = true;
			state.appsError   = '';

			try {
				const response = yield fetch(
					state.apiBase + '/jobs/' + String( state.appsJobId ) + '/applications',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);

				if ( ! response.ok ) {
					state.appsError = state.strings.errorLoadApps;
					return;
				}

				const apps         = yield response.json();
				state.applications = apps.map( ( a ) => ( {
					...a,
					initials: a.applicant_name
						? a.applicant_name.split( ' ' ).map( ( p ) => p[ 0 ] ).slice( 0, 2 ).join( '' ).toUpperCase()
						: '?',
				} ) );
				const match = state.jobs.find( ( j ) => j.id === state.appsJobId );
				if ( match ) {
					state.appsJobTitle = match.title;
				}
			} catch {
				state.appsError = state.strings.errorConnectionApps;
			} finally {
				state.appsLoading = false;
			}
		},

		*updateAppStatus( event ) {
			const appId     = Number( event.target.dataset.wcbAppId );
			const newStatus = event.target.value;
			if ( ! appId || ! newStatus ) {
				return;
			}
			try {
				const response = yield fetch(
					state.apiBase + '/applications/' + String( appId ) + '/status',
					{
						method: 'PATCH',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce':   state.nonce,
						},
						body: JSON.stringify( { status: newStatus } ),
					}
				);
				if ( response.ok ) {
					const idx = state.applications.findIndex( ( a ) => a.id === appId );
					if ( idx !== -1 ) {
						state.applications[ idx ].status = newStatus;
					}
				}
			} catch {
				// Network error — select will show stale value until next load.
			}
		},

		*closeJob( event ) {
			const jobId = Number( event.target.dataset.wcbJobId );
			if ( ! jobId ) {
				return;
			}
			if ( ! window.confirm( state.strings.confirmCloseJob ) ) {
				return;
			}
			try {
				const response = yield fetch(
					state.apiBase + '/jobs/' + String( jobId ),
					{
						method: 'PATCH',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce':   state.nonce,
						},
						body: JSON.stringify( { status: 'draft' } ),
					}
				);
				if ( response.ok ) {
					const idx = state.jobs.findIndex( ( j ) => j.id === jobId );
					if ( idx !== -1 ) {
						state.jobs[ idx ].status      = 'draft';
						state.jobs[ idx ].statusLabel = 'Draft';
						state.jobs[ idx ].isClosed    = true;
					}
				}
			} catch {
				// Network error — status unchanged.
			}
		},

		*reopenJob( event ) {
			const jobId = Number( event.target.dataset.wcbJobId );
			if ( ! jobId ) {
				return;
			}
			try {
				const response = yield fetch(
					state.apiBase + '/jobs/' + String( jobId ),
					{
						method: 'PATCH',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce':   state.nonce,
						},
						body: JSON.stringify( { status: 'publish' } ),
					}
				);
				if ( response.ok ) {
					const idx = state.jobs.findIndex( ( j ) => j.id === jobId );
					if ( idx !== -1 ) {
						state.jobs[ idx ].status      = 'publish';
						state.jobs[ idx ].statusLabel = 'Published';
						state.jobs[ idx ].isClosed    = false;
					}
				}
			} catch {
				// Network error — status unchanged.
			}
		},

		updateField( event ) {
			const field = event.target.dataset.wcbField;
			if ( field ) {
				state[ field ] = event.target.value;
			}
		},

		*uploadLogo( event ) {
			const file = event.target.files[ 0 ];
			if ( ! file ) {
				return;
			}
			if ( ! state.companyId ) {
				state.error = state.strings.errorSaveLogo;
				return;
			}
			state.logoUploading = true;
			try {
				const fd = new FormData();
				fd.append( 'logo', file );
				const response = yield fetch(
					state.apiBase + '/employers/' + String( state.companyId ) + '/logo',
					{
						method:  'POST',
						headers: { 'X-WP-Nonce': state.nonce },
						body:    fd,
					}
				);
				if ( response.ok ) {
					const data          = yield response.json();
					state.companyLogoUrl = data.logo_url;
				}
			} catch {
				// Upload failed — user can retry.
			} finally {
				state.logoUploading = false;
			}
		},

		*saveProfile() {
			if ( state.saving ) {
				return;
			}

			state.saving = true;
			state.saved  = false;
			state.error  = '';

			const isNew = ! state.companyId;
			const url   = isNew
				? state.apiBase + '/employers'
				: state.apiBase + '/employers/' + String( state.companyId );

			try {
				const response = yield fetch(
					url,
					{
						method: isNew ? 'POST' : 'PATCH',
						headers: {
							'X-WP-Nonce':   state.nonce,
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( {
							name:        state.companyName,
							description: state.companyDesc,
							tagline:     state.companyTagline,
							website:     state.companySite,
							industry:    state.companyIndustry,
							size:        state.companySize,
							hq:          state.companyHq,
							company_type: state.companyType,
							founded:     state.companyFounded,
							linkedin:    state.companyLinkedin,
							twitter:     state.companyTwitter,
						} ),
					}
				);

				if ( ! response.ok ) {
					state.error = state.strings.errorSaveProfile;
					return;
				}

				if ( isNew ) {
					const data       = yield response.json();
					state.companyId  = data.id ?? 0;
				}

				state.saved = true;
			} catch {
				state.error = state.strings.errorConnection;
			} finally {
				state.saving = false;
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
