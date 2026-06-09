/**
 * WP Career Board — employer-dashboard block Interactivity API store.
 *
 * @package WP_Career_Board
 */
import { store, getContext } from '@wordpress/interactivity';
import { wcbFetch } from '@wcb/fetch';

/**
 * Views that are eligible to appear in the URL hash. Same shape as the
 * candidate-dashboard `VALID_TABS` allowlist so deep links / bookmarks /
 * browser back-forward survive across the 2 dashboards. Anything outside
 * this list is ignored on read.
 */
const VALID_VIEWS = [
	'overview',
	'jobs',
	'post-job',
	'applications',
	'company',
	'saved-jobs',
	'saved-companies',
	'saved-resumes',
	'settings',
];

function readHashView() {
	const raw = ( window.location.hash || '' ).replace( /^#/, '' );
	return VALID_VIEWS.includes( raw ) ? raw : null;
}

function writeHashView( view ) {
	if ( ! VALID_VIEWS.includes( view ) ) {
		return;
	}
	const target = '#' + view;
	if ( window.location.hash === target ) {
		return;
	}
	const url = window.location.pathname + window.location.search + target;
	window.history.replaceState( null, '', url );
}

const { state, actions } = store( 'wcb-employer-dashboard', {
	state: {
		navOpen: false,
		// Resolves the stored industry slug to its translated label using the
		// industryLabels map seeded from PHP. Falls back to the raw value so
		// legacy free-text entries still display until migrated.
		get companyIndustryLabel() {
			const slug = state.companyIndustry || '';
			if ( ! slug ) {
				return '';
			}
			const labels = state.industryLabels || {};
			return labels[ slug ] || slug;
		},
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
		get isViewNotifications() {
			return state.currentView === 'notifications';
		},
		get isViewSavedJobs() {
			return state.currentView === 'saved-jobs';
		},
		get isViewSavedCompanies() {
			return state.currentView === 'saved-companies';
		},
		get isViewSavedResumes() {
			return state.currentView === 'saved-resumes';
		},

		// Saved-list count + empty/populated derived getters.
		get hasSavedJobs() {
			return ! state.savedJobsLoading && state.savedJobs.length > 0;
		},
		get noSavedJobs() {
			return ! state.savedJobsLoading && ! state.savedJobsError && state.savedJobs.length === 0;
		},
		get hasSavedCompanies() {
			return ! state.savedCompaniesLoading && state.savedCompanies.length > 0;
		},
		get noSavedCompanies() {
			return ! state.savedCompaniesLoading && ! state.savedCompaniesError && state.savedCompanies.length === 0;
		},
		get hasSavedResumes() {
			return ! state.savedResumesLoading && state.savedResumes.length > 0;
		},
		get noSavedResumes() {
			return ! state.savedResumesLoading && ! state.savedResumesError && state.savedResumes.length === 0;
		},

		// Credits banners — derived state so the markup can stay declarative.
		get justAddedCredits() {
			return state.creditsEnabled && Number( state.creditsJustAdded || 0 ) > 0;
		},
		get justAddedCreditsMessage() {
			const n = Number( state.creditsJustAdded || 0 );
			if ( n === 1 ) {
				return state.strings.creditsAddedSingular;
			}
			return ( state.strings.creditsAdded || '' ).replace( '%d', String( n ) );
		},
		get isCreditBalanceLow() {
			if ( ! state.creditsEnabled ) {
				return false;
			}
			const t = Number( state.creditLowThreshold || 0 );
			if ( t <= 0 ) {
				return false;
			}
			return Number( state.creditBalance || 0 ) <= t;
		},
		get lowBalanceMessage() {
			const n = Number( state.creditBalance || 0 );
			if ( n === 1 ) {
				return state.strings.lowBalanceSingular;
			}
			return ( state.strings.lowBalance || '' ).replace( '%d', String( n ) );
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
			if ( f === 'live' )          jobs = jobs.filter( ( j ) => j.status === 'publish' );
			// Draft excludes rejected jobs (a rejected job is a draft carrying a
			// rejection reason; it surfaces under its own Rejected pill instead).
			else if ( f === 'draft' )    jobs = jobs.filter( ( j ) => j.status === 'draft' && ! j.rejected );
			else if ( f === 'pending' )  jobs = jobs.filter( ( j ) => j.status === 'pending' );
			else if ( f === 'rejected' ) jobs = jobs.filter( ( j ) => j.rejected );
			// Closed pill surfaces both manually-closed and auto-expired jobs —
			// employers manage both via the same Reopen flow.
			else if ( f === 'closed' )   jobs = jobs.filter( ( j ) => j.isClosed || j.isExpired );
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
		get isFilterRejected() {
			return state.jobFilter === 'rejected';
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

		// Context getter — inside data-wp-each--job loop on My Jobs.
		// "Inactive" covers both employer-closed and cron-expired jobs so the
		// row swaps the Close button for Reopen identically in both cases.
		get isJobInactive() {
			const ctx = getContext();
			return Boolean( ctx.job?.isClosed || ctx.job?.isExpired );
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
		// Sidebar identity falls back to the user's display name when no company
		// name is set yet, so the sidebar never shows a lone "?" with no label.
		get sidebarName() {
			return state.companyName || state.displayName || '';
		},
		get companyInitials() {
			const n = state.companyName || state.displayName;
			return n
				? n.split( ' ' ).map( ( p ) => p[ 0 ] ).slice( 0, 2 ).join( '' ).toUpperCase()
				: '?';
		},
		get companyDescExcerpt() {
			// Description is now rich HTML (Editor.js output). For the live preview
			// snippet we want a plain-text teaser, not raw markup, so strip tags
			// and collapse whitespace before truncating.
			const raw   = state.companyDesc || '';
			const plain = raw.replace( /<[^>]*>/g, ' ' ).replace( /\s+/g, ' ' ).trim();
			return plain.length > 120 ? plain.slice( 0, 120 ) + '\u2026' : plain;
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
		// Hide the credits-added success banner. Also strips the
		// ?wcb_credits_added param from the URL so a manual reload doesn't
		// resurface the banner.
		dismissCreditSuccess() {
			state.creditsJustAdded = 0;
			if ( typeof window !== 'undefined' && window.history?.replaceState ) {
				const url = new URL( window.location.href );
				url.searchParams.delete( 'wcb_credits_added' );
				window.history.replaceState( {}, '', url.toString() );
			}
		},
		*init() {
			// Tab restoration priority: URL hash → sessionStorage → server default.
			// Mirrors candidate-dashboard so deep-linking to #saved-jobs /
			// #applications / etc lands the user on the right tab regardless
			// of any prior session activity.
			const hashView = readHashView();
			if ( hashView ) {
				state.currentView = hashView;
			} else if ( state.currentView === 'overview' ) {
				const saved = sessionStorage.getItem( 'wcb_employer_view' );
				if ( saved ) {
					state.currentView = saved;
				}
			}
			const savedJob = Number( sessionStorage.getItem( 'wcb_employer_apps_job' ) );
			if ( savedJob > 0 && state.currentView === 'applications' ) {
				state.appsJobId = savedJob;
			}
			writeHashView( state.currentView );

			// Browser back/forward and manual hash edits stay in sync.
			window.addEventListener( 'hashchange', () => {
				const next = readHashView();
				if ( next && next !== state.currentView ) {
					state.currentView = next;
				}
			} );

			state.loading = true;
			state.error   = '';

			try {
				yield actions.loadJobs();

				// Company applications (the Applications tab's dataset).
				if ( state.companyId ) {
					const appsResp = yield wcbFetch(
						state.apiBase + '/employers/' + String( state.companyId ) + '/applications',
						{ headers: { 'X-WP-Nonce': state.nonce } }
					);
					if ( appsResp.ok ) {
						const appsData = yield appsResp.json();
						state.allApplications = Array.isArray( appsData ) ? appsData : ( appsData?.applications ?? [] );
					}
				}
			} catch {
				state.error = state.strings.errorConnection;
			} finally {
				state.loading = false;
			}

			if ( state.appsJobId > 0 ) {
				yield actions.loadApplications();
			}

			// Tab-restoration prefetch: when sessionStorage restores the
			// user to a Saved-* tab, the corresponding switchToSaved*
			// action never fires from a click and the panel sits empty
			// against a populated sidebar badge. Pull the data inline so
			// the panel paints correctly on first navigation.
			if ( state.currentView === 'saved-jobs' && state.savedJobs.length === 0 ) {
				yield actions.switchToSavedJobs();
			}
			if ( state.currentView === 'saved-companies' && state.savedCompanies.length === 0 ) {
				yield actions.switchToSavedCompanies();
			}
			if ( state.currentView === 'saved-resumes' && state.savedResumes.length === 0 ) {
				yield actions.switchToSavedResumes();
			}

			if ( state.bellEnabled ) {
				yield actions.fetchBellNotifications();
			}
		},

		toggleNav() {
			state.navOpen = ! state.navOpen;
		},

		switchToOverview() {
			state.currentView = 'overview';
			state.navOpen     = false;
			sessionStorage.removeItem( 'wcb_employer_view' );
			writeHashView( 'overview' );
		},

		// Fetch + normalize the employer's jobs. Shared by init() and the
		// post-a-job refresh path so My Jobs reflects a just-posted job without
		// a page reload.
		*loadJobs() {
			const jobsBase = state.companyId
				? state.apiBase + '/employers/' + String( state.companyId ) + '/jobs'
				: state.apiBase + '/employers/me/jobs';
			const jobsUrl = new URL( jobsBase );
			jobsUrl.searchParams.set( 'per_page', '50' );

			const resp = yield wcbFetch( jobsUrl.toString(), { headers: { 'X-WP-Nonce': state.nonce } } );
			if ( ! resp.ok ) {
				state.error = state.strings.errorLoadJobs;
				return;
			}
			const jobsData = yield resp.json();
			const jobs     = Array.isArray( jobsData ) ? jobsData : ( jobsData?.jobs ?? [] );
			// "closed" = employer-closed, "expired" = past-deadline (cron); both
			// render as finished listings. "pending" = awaiting moderation, "draft" = unsaved.
			state.jobs = jobs.map( ( j ) => ( {
				...j,
				appsUrl:   j.appCount > 0 ? state.dashboardUrl + '?job_apps=' + String( j.id ) : null,
				isClosed:  j.status === 'closed',
				isExpired: j.status === 'expired',
				isRejected: !! j.rejected,
				isDraft:   j.status === 'draft' && ! j.rejected,
			} ) );
		},

		*switchToJobs() {
			state.currentView = 'jobs';
			state.error       = '';
			state.navOpen     = false;
			sessionStorage.setItem( 'wcb_employer_view', 'jobs' );
			writeHashView( 'jobs' );

			// The embedded Post-a-Job form flags a refresh after a successful
			// submit; reload the list silently so the new job appears.
			if ( state._needsJobsRefresh ) {
				state._needsJobsRefresh = false;
				yield actions.loadJobs();
			}
		},

		switchToApplications() {
			state.currentView = 'applications';
			state.navOpen     = false;
			sessionStorage.setItem( 'wcb_employer_view', 'applications' );
			writeHashView( 'applications' );
		},

		switchToCompany() {
			state.currentView = 'company';
			state.saved       = false;
			state.error       = '';
			state.navOpen     = false;
			sessionStorage.setItem( 'wcb_employer_view', 'company' );
			writeHashView( 'company' );
		},

		switchToSettings() {
			state.currentView = 'settings';
			state.error       = '';
			state.navOpen     = false;
			sessionStorage.setItem( 'wcb_employer_view', 'settings' );
			writeHashView( 'settings' );
		},

		*switchToNotifications() {
			state.currentView = 'notifications';
			state.error       = '';
			state.navOpen     = false;
			sessionStorage.setItem( 'wcb_employer_view', 'notifications' );
			writeHashView( 'notifications' );
			if ( state.bellEnabled && state.bellNotifications.length === 0 ) {
				yield actions.fetchBellNotifications();
			}
		},

		switchToPostJob() {
			state.currentView = 'post-job';
			state.navOpen     = false;
			sessionStorage.setItem( 'wcb_employer_view', 'post-job' );
			writeHashView( 'post-job' );
		},

		/**
		 * Saved Jobs tab. Lazy-fetches once per session. Mirrors the
		 * candidate-dashboard `switchToBookmarks` shape but lives on the
		 * employer dashboard because any logged-in user can bookmark.
		 */
		*switchToSavedJobs() {
			state.currentView       = 'saved-jobs';
			state.navOpen           = false;
			state.savedJobsError    = '';
			sessionStorage.setItem( 'wcb_employer_view', 'saved-jobs' );
			writeHashView( 'saved-jobs' );

			if ( state.savedJobs.length ) {
				return;
			}
			state.savedJobsLoading = true;
			try {
				const response = yield wcbFetch(
					state.apiBase + '/candidates/' + String( state.employerId ) + '/bookmarks',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);
				if ( ! response.ok ) {
					state.savedJobsError = 'Could not load saved jobs.';
					return;
				}
				const data = yield response.json();
				state.savedJobs = Array.isArray( data ) ? data : ( data?.bookmarks ?? [] );
			} catch {
				state.savedJobsError = 'Connection error.';
			} finally {
				state.savedJobsLoading = false;
			}
		},

		*switchToSavedCompanies() {
			state.currentView          = 'saved-companies';
			state.navOpen              = false;
			state.savedCompaniesError  = '';
			sessionStorage.setItem( 'wcb_employer_view', 'saved-companies' );
			writeHashView( 'saved-companies' );

			if ( state.savedCompanies.length ) {
				return;
			}
			state.savedCompaniesLoading = true;
			try {
				const response = yield wcbFetch(
					state.apiBase + '/candidates/' + String( state.employerId ) + '/saved-companies',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);
				if ( ! response.ok ) {
					state.savedCompaniesError = 'Could not load saved companies.';
					return;
				}
				const data = yield response.json();
				state.savedCompanies = Array.isArray( data ) ? data : ( data?.items ?? [] );
			} catch {
				state.savedCompaniesError = 'Connection error.';
			} finally {
				state.savedCompaniesLoading = false;
			}
		},

		*switchToSavedResumes() {
			state.currentView        = 'saved-resumes';
			state.navOpen            = false;
			state.savedResumesError  = '';
			sessionStorage.setItem( 'wcb_employer_view', 'saved-resumes' );
			writeHashView( 'saved-resumes' );

			if ( state.savedResumes.length ) {
				return;
			}
			state.savedResumesLoading = true;
			try {
				const response = yield wcbFetch(
					state.apiBase + '/candidates/' + String( state.employerId ) + '/saved-resumes',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);
				if ( ! response.ok ) {
					state.savedResumesError = 'Could not load saved resumes.';
					return;
				}
				const data = yield response.json();
				state.savedResumes = Array.isArray( data ) ? data : ( data?.items ?? [] );
			} catch {
				state.savedResumesError = 'Connection error.';
			} finally {
				state.savedResumesLoading = false;
			}
		},

		*unbookmarkJob() {
			const ctx = getContext();
			if ( ! ctx?.job ) {
				return;
			}
			const jobId = ctx.job.id;
			try {
				const response = yield wcbFetch(
					state.apiBase + '/jobs/' + String( jobId ) + '/bookmark',
					{ method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': state.nonce } }
				);
				if ( response.ok ) {
					state.savedJobs = state.savedJobs.filter( function( j ) { return j.id !== jobId; } );
				}
			} catch {
				// Silent fail.
			}
		},

		*unbookmarkCompany() {
			const ctx = getContext();
			if ( ! ctx?.company ) {
				return;
			}
			const companyId = ctx.company.id;
			try {
				const response = yield wcbFetch(
					state.apiBase + '/companies/' + String( companyId ) + '/bookmark',
					{ method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': state.nonce } }
				);
				if ( response.ok ) {
					state.savedCompanies = state.savedCompanies.filter( function( c ) { return c.id !== companyId; } );
				}
			} catch {
				// Silent fail.
			}
		},

		*unbookmarkResume() {
			const ctx = getContext();
			if ( ! ctx?.resume ) {
				return;
			}
			const resumeId = ctx.resume.id;
			try {
				const response = yield wcbFetch(
					state.apiBase + '/resumes/' + String( resumeId ) + '/bookmark',
					{ method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': state.nonce } }
				);
				if ( response.ok ) {
					state.savedResumes = state.savedResumes.filter( function( r ) { return r.id !== resumeId; } );
				}
			} catch {
				// Silent fail.
			}
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
				const response = yield wcbFetch(
					state.apiBase + '/jobs/' + String( state.appsJobId ) + '/applications',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);

				if ( ! response.ok ) {
					state.appsError = state.strings.errorLoadApps;
					return;
				}

				const appsData = yield response.json();
				const apps     = Array.isArray( appsData ) ? appsData : ( appsData?.applications ?? [] );
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
				const response = yield wcbFetch(
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
			// Promise-based modal replacement for window.confirm() —
			// wcbConfirm() resolves on confirm and rejects on cancel/ESC.
			try {
				yield window.wcbConfirm( {
					title:        state.strings.confirmCloseTitle || state.strings.confirmCloseJob,
					message:      state.strings.confirmCloseJob,
					confirmText:  state.strings.confirmCloseConfirm || 'Close job',
					destructive:  true,
				} );
			} catch ( cancelled ) {
				return;
			}
			try {
				const response = yield wcbFetch(
					state.apiBase + '/jobs/' + String( jobId ),
					{
						method: 'PATCH',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce':   state.nonce,
						},
						body: JSON.stringify( { status: 'closed' } ),
					}
				);
				if ( response.ok ) {
					const idx = state.jobs.findIndex( ( j ) => j.id === jobId );
					if ( idx !== -1 ) {
						state.jobs[ idx ].status      = 'closed';
						state.jobs[ idx ].statusLabel = 'Closed';
						state.jobs[ idx ].isClosed    = true;
						state.jobs[ idx ].isExpired   = false;
						state.jobs[ idx ].isDraft     = false;
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
				const response = yield wcbFetch(
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
						// A rejected listing resubmits to moderation (pending), not
						// straight live — the server enforces this; mirror it in the
						// optimistic update so the badge matches what the API stored.
						const wasRejected = state.jobs[ idx ].isRejected;
						state.jobs[ idx ].status      = wasRejected ? 'pending' : 'publish';
						state.jobs[ idx ].statusLabel = wasRejected ? 'Pending' : 'Published';
						state.jobs[ idx ].isClosed    = false;
						state.jobs[ idx ].isExpired   = false;
						state.jobs[ idx ].isRejected  = false;
						state.jobs[ idx ].isDraft     = false;
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

		*saveAccount() {
			state.accountSaving = true;
			state.accountMsg    = '';
			try {
				const response = yield wcbFetch(
					state.apiBase + '/account',
					{
						method: 'POST',
						headers: {
							'X-WP-Nonce':   state.nonce,
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( {
							display_name: state.accountName,
							email:        state.accountEmail,
						} ),
					}
				);
				const data = yield response.json();
				if ( response.ok ) {
					state.accountName    = data.display_name;
					state.accountEmail   = data.email;
					state.displayName    = data.display_name;
					state.employerEmail  = data.email;
					state.accountMsgType = 'success';
					state.accountMsg     = 'Account updated.';
				} else {
					state.accountMsgType = 'error';
					state.accountMsg     = data?.message || 'Could not save your account.';
				}
			} catch {
				state.accountMsgType = 'error';
				state.accountMsg     = 'Connection error. Please try again.';
			} finally {
				state.accountSaving = false;
			}
		},

		*changePassword() {
			state.pwMsg = '';
			if ( ! state.curPassword || ! state.newPassword ) {
				state.pwMsgType = 'error';
				state.pwMsg     = 'Enter your current and new password.';
				return;
			}
			if ( state.newPassword !== state.confPassword ) {
				state.pwMsgType = 'error';
				state.pwMsg     = 'New password and confirmation do not match.';
				return;
			}
			state.pwSaving = true;
			try {
				const response = yield wcbFetch(
					state.apiBase + '/account',
					{
						method: 'POST',
						headers: {
							'X-WP-Nonce':   state.nonce,
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( {
							current_password: state.curPassword,
							new_password:     state.newPassword,
						} ),
					}
				);
				const data = yield response.json();
				if ( response.ok ) {
					if ( data.nonce ) {
						state.nonce = data.nonce;
					}
					state.curPassword  = '';
					state.newPassword  = '';
					state.confPassword = '';
					state.pwMsgType    = 'success';
					state.pwMsg        = 'Password updated.';
				} else {
					state.pwMsgType = 'error';
					state.pwMsg     = data?.message || 'Could not update your password.';
				}
			} catch {
				state.pwMsgType = 'error';
				state.pwMsg     = 'Connection error. Please try again.';
			} finally {
				state.pwSaving = false;
			}
		},

		updateCustomField( event ) {
			const key = event.target.getAttribute( 'data-wcb-field' );
			if ( ! key ) {
				return;
			}
			const target = event.target;
			let value;
			if ( target.dataset.wcbMulti ) {
				// multiselect — collect every checked box sharing this field key.
				value = Array.from(
					document.querySelectorAll( '[data-wcb-field="' + key + '"][data-wcb-multi]' )
				)
					.filter( ( el ) => el.checked )
					.map( ( el ) => el.value );
			} else if ( target.type === 'checkbox' ) {
				value = target.checked;
			} else {
				value = target.value;
			}
			state.customFields = { ...state.customFields, [ key ]: value };
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
				const response = yield wcbFetch(
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

			const isNew = ! state.companyId;

			if ( isNew && ! state.companyName.trim() ) {
				state.error = state.strings.errorCompanyNameRequired;
				return;
			}

			state.saving = true;
			state.saved  = false;
			state.error  = '';
			const url   = isNew
				? state.apiBase + '/employers'
				: state.apiBase + '/employers/' + String( state.companyId );

			try {
				const response = yield wcbFetch(
					url,
					{
						method: isNew ? 'POST' : 'PATCH',
						headers: {
							'X-WP-Nonce':   state.nonce,
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( {
							name:          state.companyName,
							description:   state.companyDesc,
							tagline:       state.companyTagline,
							website:       state.companySite,
							industry:      state.companyIndustry,
							size:          state.companySize,
							hq:            state.companyHq,
							company_type:  state.companyType,
							founded:       state.companyFounded,
							linkedin:      state.companyLinkedin,
							twitter:       state.companyTwitter,
							custom_fields: state.customFields || {},
						} ),
					}
				);

				const data = yield response.json();
				if ( ! response.ok ) {
					state.error = ( data && data.message ) ? String( data.message ) : state.strings.errorSaveProfile;
					return;
				}

				if ( isNew ) {
					state.companyId = data.id ?? 0;
					state.noCompany = ! state.companyId;
				}

				state.saved = true;
				setTimeout( () => {
					state.saved = false;
				}, 3000 );
			} catch {
				state.error = state.strings.errorConnection;
			} finally {
				state.saving = false;
			}
		},


		*fetchBellNotifications() {
			state.bellLoading = true;
			try {
				const res = yield wcbFetch( state.apiBase + '/notifications?per_page=20', {
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
			yield wcbFetch( state.apiBase + '/notifications/' + String( id ) + '/read', {
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
			yield wcbFetch( state.apiBase + '/notifications/read-all', {
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
