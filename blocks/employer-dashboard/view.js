/**
 * WP Career Board — employer-dashboard block Interactivity API store.
 *
 * @package WP_Career_Board
 */
import { store, getContext } from '@wordpress/interactivity';

const { state, actions } = store( 'wcb-employer-dashboard', {
	state: {
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

		// Jobs list.
		get hasJobs() {
			return ! state.loading && ! state.noCompany && state.filteredJobs.length > 0;
		},
		get noJobs() {
			return ! state.loading && ! state.noCompany && ! state.error && state.filteredJobs.length === 0;
		},
		get totalJobs() {
			return state.jobs.length;
		},
		get publishedJobs() {
			return state.jobs.filter( ( j ) => j.status === 'publish' ).length;
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

		// Jobs with applications (for selector pills).
		get jobsWithApps() {
			return state.jobs.filter( ( j ) => j.appCount > 0 );
		},
		get hasJobsWithApps() {
			return state.jobsWithApps.length > 0;
		},

		// Context getter — inside data-wp-each--job loop for apps selector.
		get isSelectedAppsJob() {
			const ctx = getContext();
			return ctx.job?.id === state.appsJobId;
		},

		// Applications.
		get totalApps() {
			return state.allApplications.length > 0
				? state.allApplications.length
				: state.jobs.reduce( ( sum, j ) => sum + j.appCount, 0 );
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
		get isAppsFilterShortlisted() {
			return state.appsFilter === 'shortlisted';
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

		// Legacy heading used by some templates.
		get appsHeading() {
			return state.appsJobTitle
				? 'Applications: ' + state.appsJobTitle
				: 'Applications';
		},
	},

	actions: {
		*init() {
			if ( ! state.companyId ) {
				state.noCompany = true;
				return;
			}

			state.loading = true;
			state.error   = '';

			try {
				const jobsUrl = new URL( state.apiBase + '/employers/' + String( state.companyId ) + '/jobs' );
				jobsUrl.searchParams.set( 'per_page', '50' );
				const appsUrl = state.apiBase + '/employers/' + String( state.companyId ) + '/applications';
				const headers = { 'X-WP-Nonce': state.nonce };

				const [ jobsResp, allAppsResp ] = yield Promise.all( [
					fetch( jobsUrl.toString(), { headers } ),
					fetch( appsUrl, { headers } ),
				] );

				if ( ! jobsResp.ok ) {
					state.error = 'Could not load your jobs.';
					return;
				}

				const jobs  = yield jobsResp.json();
				state.jobs  = jobs.map( ( j ) => ( {
					...j,
					appsUrl:  j.appCount > 0 ? state.dashboardUrl + '?job_apps=' + String( j.id ) : null,
					isClosed: j.status !== 'publish',
				} ) );

				if ( allAppsResp.ok ) {
					state.allApplications = yield allAppsResp.json();
				}
			} catch {
				state.error = 'Connection error. Please check your network and try again.';
			} finally {
				state.loading = false;
			}

			if ( state.appsJobId > 0 ) {
				yield actions.loadApplications();
			}
		},

		switchToJobs() {
			state.currentView = 'jobs';
			state.error       = '';
		},

		switchToApplications() {
			state.currentView = 'applications';
		},

		switchToCompany() {
			state.currentView = 'company';
			state.saved       = false;
			state.error       = '';
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

		setAppsFilter( event ) {
			const f = event.target.dataset.wcbFilter;
			if ( f ) {
				state.appsFilter = f;
			}
		},

		*switchAppsJob( event ) {
			const id = parseInt( event.target.dataset.wcbJobId, 10 );
			if ( Number.isNaN( id ) ) {
				return;
			}
			state.appsJobId     = id;
			state.selectedAppId = null;
			state.currentView   = 'applications';
			yield actions.loadApplications();
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
					state.appsError = 'Could not load applications.';
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
				state.appsError = 'Connection error loading applications.';
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

		*saveProfile() {
			if ( state.saving ) {
				return;
			}

			state.saving = true;
			state.saved  = false;
			state.error  = '';

			try {
				const response = yield fetch(
					state.apiBase + '/employers/' + String( state.companyId ),
					{
						method: 'PATCH',
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
						} ),
					}
				);

				if ( ! response.ok ) {
					state.error = 'Could not save profile. Please try again.';
					return;
				}

				state.saved = true;
			} catch {
				state.error = 'Connection error. Please check your network and try again.';
			} finally {
				state.saving = false;
			}
		},
	},
} );
