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

/**
 * Translation reader. Every user-facing string is seeded from render.php under
 * the `i18n` state key — this view script is registered as a script module and
 * therefore cannot load JED translations itself. The fallback is the English
 * source text and matches the __() call in render.php exactly.
 *
 * @param {string} key      Key inside state.i18n.
 * @param {string} fallback English source text.
 * @return {string} Translated string.
 */
const t = ( key, fallback ) => ( state.i18n && state.i18n[ key ] ) || fallback;

/**
 * Format an integer against the SITE locale, seeded from PHP as `state.locale`
 * (a BCP-47 tag such as "de-DE"). Never call toLocaleString() without a locale
 * argument: that formats against the visitor's BROWSER locale, so a de_DE site
 * viewed from an en-US browser would render "1,000" instead of "1.000".
 *
 * @param {number} n Value to format.
 * @return {string} Localised number.
 */
const fmtNumber = ( n ) => {
	const value = Number( n ) || 0;
	try {
		return new Intl.NumberFormat( state.locale || undefined ).format( value );
	} catch {
		// Malformed state.locale tag (site misconfiguration). Fall back to raw,
		// ungrouped digits — never Intl's no-argument default, which formats
		// against the VISITOR's browser locale instead of the site's.
		return String( value );
	}
};

/**
 * Render an AI fit score as a percentage. The percent sign's position is a
 * translatable format string, not a hardcoded suffix, and the digits run
 * through the site locale.
 *
 * @param {number} score 0-100.
 * @return {string} e.g. "82%".
 */
const aiScoreLabel = ( score ) =>
	t( 'aiScorePercent', '%1$s%' ).replace( '%1$s', fmtNumber( score ) );

/**
 * Filter-pill count: blank when zero (the pill hides its badge), otherwise the
 * site-locale-formatted number.
 *
 * @param {number} n Count.
 * @return {string} Localised count or ''.
 */
const countLabel = ( n ) => ( n ? fmtNumber( n ) : '' );

/**
 * Resolve a job's status slug to its seeded, translated badge label. Applied at
 * job-load time so the first paint shows the SAME translated label the optimistic
 * close/reopen updates later assign — never the REST payload's untranslated
 * English statusLabel string.
 *
 * @param {Object} job Raw job object from the REST payload.
 * @return {string} Translated status label.
 */
const jobStatusLabel = ( job ) => {
	// A rejected listing is stored as a draft carrying a rejection reason, so it
	// must be checked before the plain draft case.
	if ( job.rejected ) {
		return t( 'jobStatusRejected', 'Rejected' );
	}
	switch ( job.status ) {
		case 'closed':
			return t( 'jobStatusClosed', 'Closed' );
		case 'expired':
			return t( 'jobStatusExpired', 'Expired' );
		case 'pending':
			return t( 'jobStatusPending', 'Pending' );
		case 'draft':
			return t( 'jobStatusDraft', 'Draft' );
		case 'publish':
		default:
			return t( 'jobStatusPublished', 'Published' );
	}
};

const { state, actions } = store( 'wcb-employer-dashboard', {
	state: {
		navOpen: false,
		// Applications tab layout — 'list' (split panel) or 'board' (Kanban by
		// status). draggingAppId holds the application id mid-drag so the drop
		// target knows which card to re-status.
		appsLayout: 'list',
		draggingAppId: null,
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
		// Resolves the stored company-size slug ('11-50', '5000+', …) to its
		// translated label using the companySizeLabels map seeded from PHP.
		// The Live Preview chip binds this so a non-English site never shows the
		// raw option slug. Falls back to the raw value for any unmapped entry.
		get companySizeLabel() {
			const slug = state.companySize || '';
			if ( ! slug ) {
				return '';
			}
			const labels = state.companySizeLabels || {};
			return labels[ slug ] || slug;
		},
		get activeTabLabel() {
			const map = {
				overview:     t( 'overview', 'Overview' ),
				jobs:         t( 'myJobs', 'My Jobs' ),
				applications: t( 'applications', 'Applications' ),
				company:      t( 'profile', 'Profile' ),
				'post-job':   t( 'postAJob', 'Post a Job' ),
			};
			return map[ state.currentView ] || t( 'dashboard', 'Dashboard' );
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

		// Sidebar badge digits — formatted against the site locale. The raw
		// numeric state values stay untouched for the `!state.*` class bindings.
		get savedJobsCountLabel() {
			return fmtNumber( state.savedJobsCount || 0 );
		},
		get savedCompaniesCountLabel() {
			return fmtNumber( state.savedCompaniesCount || 0 );
		},
		get savedResumesCountLabel() {
			return fmtNumber( state.savedResumesCount || 0 );
		},
		get bellUnreadCountLabel() {
			return fmtNumber( state.bellUnreadCount || 0 );
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
		// Pre-resolved server-side (render.php) with the real count, because a
		// script module cannot call _n() and `count === 1` only picks the right
		// plural form in 2-form locales.
		get justAddedCreditsMessage() {
			return t( 'creditsAddedMessage', '' );
		},
		get isCreditBalanceLow() {
			if ( ! state.creditsEnabled ) {
				return false;
			}
			const threshold = Number( state.creditLowThreshold || 0 );
			if ( threshold <= 0 ) {
				return false;
			}
			return Number( state.creditBalance || 0 ) <= threshold;
		},
		// Pre-resolved server-side. state.creditBalance never changes without a
		// page load, so the plural form seeded at render time stays correct.
		get lowBalanceMessage() {
			return t( 'lowBalanceMessage', '' );
		},

		// Jobs list.
		get hasJobs() {
			// Jobs are loaded author-scoped (/employers/me/jobs), so an employer
			// who hasn't created a company profile still has real jobs to show —
			// never gate the jobs list on company existence.
			return ! state.loading && state.filteredJobs.length > 0;
		},
		get noJobs() {
			return ! state.loading && ! state.error && state.filteredJobs.length === 0;
		},
		// Onboarding banners must never contradict real content. Author-scoped
		// jobs exist independently of a company profile, so suppress the "set up
		// company" CTA the moment the employer has jobs to manage, and only offer
		// "post your first job" once a company exists.
		get showCompanySetup() {
			return state.noCompany && ! state.hasJobs;
		},
		get showPostFirstJob() {
			return ! state.noCompany && state.noJobs;
		},
		// Stat-card figures are rendered straight into the DOM, so they are
		// formatted against the site locale here.
		get totalJobs() {
			return fmtNumber( state.jobs.length || state.ssrTotalJobs || 0 );
		},
		get publishedJobs() {
			return fmtNumber(
				state.jobs.length
					? state.jobs.filter( ( j ) => j.status === 'publish' ).length
					: ( state.ssrPublishedJobs || 0 )
			);
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
			const filtered = state.filteredJobsWithApps.length;
			if ( ( state.appsJobSearch || '' ).trim() === '' ) {
				// Whole sentence pre-pluralised in render.php from a COUNT(*) of
				// jobs that have applications — i.e. matches found, not the rows the
				// REST page happened to load (per_page is capped at 50).
				return t( 'jobsWithAppsLabel', '' );
			}
			// The total and the plural form of "job(s)" are resolved in render.php
			// from the same COUNT(*); only the runtime-varying search-match count is
			// filled in here. No client-side plural/number assembly.
			return t( 'jobsFilteredCount', '%1$s of %2$s jobs' )
				.replace( '%1$s', fmtNumber( filtered ) );
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
				return fmtNumber( state.allApplications.length );
			}
			if ( state.jobs.length > 0 ) {
				return fmtNumber( state.jobs.reduce( ( sum, j ) => sum + j.appCount, 0 ) );
			}
			return fmtNumber( state.ssrTotalApps || 0 );
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
			const list = f === 'all'
				? state.applications
				: state.applications.filter( ( a ) => a.status === f );
			if ( state.aiRanked ) {
				return [ ...list ].sort( ( a, b ) => ( b.aiScore ?? -1 ) - ( a.aiScore ?? -1 ) );
			}
			return list;
		},
		// AI ranking controls (Pro — only shown when wcb_ai_ranking_available).
		get showAiRankButton() {
			return state.aiRanking && state.appsJobId > 0 && ! state.appsLoading && state.applications.length > 0;
		},
		get aiRankBtnLabel() {
			return state.aiRankLoading
				? t( 'aiRankingLabel', 'Ranking…' )
				: t( 'aiRankButton', 'Rank by AI fit' );
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

		// Applications layout toggle — List (split panel) vs Board (Kanban).
		get isAppsBoardLayout() {
			return state.appsLayout === 'board';
		},
		get isAppsListLayout() {
			return state.appsLayout !== 'board';
		},
		// Board columns — one per status, each carrying its filtered (and
		// optionally AI-ranked) applications. Same source of truth as the list.
		get appsBoardColumns() {
			const defs = [
				{ key: 'submitted',   label: t( 'statusSubmitted', 'Submitted' ) },
				{ key: 'reviewing',   label: t( 'statusReviewing', 'Reviewing' ) },
				{ key: 'shortlisted', label: t( 'statusShortlisted', 'Shortlisted' ) },
				{ key: 'hired',       label: t( 'statusHired', 'Hired' ) },
				{ key: 'rejected',    label: t( 'statusRejected', 'Rejected' ) },
			];
			return defs.map( ( d ) => {
				let apps = state.applications.filter( ( a ) => a.status === d.key );
				if ( state.aiRanked ) {
					apps = [ ...apps ].sort( ( a, b ) => ( b.aiScore ?? -1 ) - ( a.aiScore ?? -1 ) );
				}
				return { key: d.key, label: d.label, count: countLabel( apps.length ), apps };
			} );
		},

		// Per-status counts — computed from already-loaded applications, no extra
		// REST calls. Zero renders as an empty pill, anything else as a
		// site-locale-formatted number.
		get appsCountAll() {
			return countLabel( state.applications.length );
		},
		get appsCountSubmitted() {
			return countLabel( state.applications.filter( ( a ) => a.status === 'submitted' ).length );
		},
		get appsCountReviewing() {
			return countLabel( state.applications.filter( ( a ) => a.status === 'reviewing' ).length );
		},
		get appsCountShortlisted() {
			return countLabel( state.applications.filter( ( a ) => a.status === 'shortlisted' ).length );
		},
		get appsCountRejected() {
			return countLabel( state.applications.filter( ( a ) => a.status === 'rejected' ).length );
		},
		get appsCountHired() {
			return countLabel( state.applications.filter( ( a ) => a.status === 'hired' ).length );
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
			// Display the localised label; submitted_at is raw ISO for sorting.
			return state.selectedApp?.submitted_at_label ?? '';
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
		get selectedAppResumePermalink() {
			return state.selectedApp?.resume_permalink ?? null;
		},
		get selectedAppHasResume() {
			return !! ( state.selectedApp?.resume_permalink || state.selectedApp?.resume_url );
		},
		get selectedAppInitials() {
			const n = state.selectedAppName;
			return n
				? n.split( ' ' ).map( ( p ) => p[ 0 ] ).slice( 0, 2 ).join( '' ).toUpperCase()
				: '?';
		},
		get selectedAppHasAiScore() {
			return typeof state.selectedApp?.aiScore === 'number';
		},
		get selectedAppAiScoreLabel() {
			return state.selectedApp?.aiScoreLabel ?? '';
		},
		get selectedAppAiReason() {
			return state.selectedApp?.aiReason ?? '';
		},
		get selectedAppAiSummary() {
			return state.selectedApp?.aiSummary ?? '';
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
			const ctx  = getContext();
			const name = ctx.app?.applicant_name || '';
			return t( 'viewApplicationFrom', 'View application from %1$s' ).replace( '%1$s', name );
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
				return fmtNumber( state.ssrNewThisWeek || 0 );
			}
			const cutoff = Date.now() - 7 * 24 * 60 * 60 * 1000;
			return fmtNumber(
				state.allApplications.filter(
					( a ) => new Date( a.submitted_at ).getTime() > cutoff
				).length
			);
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
			if ( state.logoUploading ) return t( 'logoUploading', 'Uploading…' );
			return state.companyLogoUrl
				? t( 'logoChange', 'Change Logo' )
				: t( 'logoUpload', 'Upload Logo' );
		},

		// Credit balance — displayed in the sidebar badge and the stat card, so it
		// is formatted against the site locale rather than dumped as raw digits.
		get creditBalanceLabel() {
			return fmtNumber( state.creditBalance || 0 );
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

				// Applications dataset (Overview "Recent Applications" widget + the
				// Applications tab). Use the company-scoped route when a company
				// profile exists, else the author-scoped /employers/me/applications
				// so employers who post jobs BEFORE creating a company still see
				// their applications instead of an empty widget beside a non-zero
				// stat card.
				const appsUrl = state.companyId
					? state.apiBase + '/employers/' + String( state.companyId ) + '/applications'
					: state.apiBase + '/employers/me/applications';
				const appsResp = yield wcbFetch(
					appsUrl,
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);
				if ( appsResp.ok ) {
					const appsData = yield appsResp.json();
					state.allApplications = Array.isArray( appsData ) ? appsData : ( appsData?.applications ?? [] );
				}
			} catch {
				state.error = t( 'errorConnection', 'Connection error. Please check your network and try again.' );
			} finally {
				state.loading = false;
			}

			// Guard against a stale / cross-tenant job id restored from
			// sessionStorage: only keep appsJobId if it belongs to THIS
			// employer's own jobs. A leaked id from a prior session as a
			// different employer would otherwise 403 the applications fetch and
			// surface a broken error state instead of this employer's own.
			if ( state.appsJobId > 0 && ! state.jobs.some( function ( j ) { return Number( j.id ) === Number( state.appsJobId ); } ) ) {
				state.appsJobId = 0;
				try { sessionStorage.removeItem( 'wcb_employer_apps_job' ); } catch {}
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
				state.error = t( 'errorLoadJobs', 'Could not load your jobs.' );
				return;
			}
			const jobsData = yield resp.json();
			const jobs     = Array.isArray( jobsData ) ? jobsData : ( jobsData?.jobs ?? [] );
			// "closed" = employer-closed, "expired" = past-deadline (cron); both
			// render as finished listings. "pending" = awaiting moderation, "draft" = unsaved.
			state.jobs = jobs.map( ( j ) => ( {
				...j,
				// Site-locale digits for the selector badge; j.appCount stays numeric
				// for the filters and the reduce() in state.totalApps.
				appCountLabel: fmtNumber( j.appCount ),
				// Translated badge label from the seeded strings, overriding the
				// REST payload's English statusLabel so the first paint is localised.
				statusLabel: jobStatusLabel( j ),
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
					state.savedJobsError = t( 'errorLoadSavedJobs', 'Could not load saved jobs.' );
					return;
				}
				const data = yield response.json();
				state.savedJobs = Array.isArray( data ) ? data : ( data?.bookmarks ?? [] );
			} catch {
				state.savedJobsError = t( 'errorConnectionShort', 'Connection error.' );
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
					state.savedCompaniesError = t( 'errorLoadSavedCompanies', 'Could not load saved companies.' );
					return;
				}
				const data = yield response.json();
				state.savedCompanies = Array.isArray( data ) ? data : ( data?.items ?? [] );
			} catch {
				state.savedCompaniesError = t( 'errorConnectionShort', 'Connection error.' );
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
					state.savedResumesError = t( 'errorLoadSavedResumes', 'Could not load saved resumes.' );
					return;
				}
				const data = yield response.json();
				state.savedResumes = Array.isArray( data ) ? data : ( data?.items ?? [] );
			} catch {
				state.savedResumesError = t( 'errorConnectionShort', 'Connection error.' );
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
					state.appsError = t( 'errorLoadApps', 'Could not load applications.' );
					return;
				}

				const appsData = yield response.json();
				const apps     = Array.isArray( appsData ) ? appsData : ( appsData?.applications ?? [] );
				state.applications = apps.map( ( a ) => ( {
					...a,
					initials: a.applicant_name
						? a.applicant_name.split( ' ' ).map( ( p ) => p[ 0 ] ).slice( 0, 2 ).join( '' ).toUpperCase()
						: '?',
					...( typeof a.ai_score === 'number'
						? {
							aiScore: a.ai_score,
							aiScoreLabel: aiScoreLabel( a.ai_score ),
							aiReason: a.ai_reason || '',
							aiSummary: a.ai_summary || '',
						}
						: {} ),
				} ) );
				if ( state.applications.some( ( a ) => typeof a.aiScore === 'number' ) ) {
					state.aiRanked = true;
				}
				const match = state.jobs.find( ( j ) => j.id === state.appsJobId );
				if ( match ) {
					state.appsJobTitle = match.title;
				}
			} catch {
				state.appsError = t( 'errorConnectionApps', 'Connection error loading applications.' );
			} finally {
				state.appsLoading = false;
			}
		},

		// Rank the loaded applications by AI fit score (Pro /ai/ranked-applications).
		*rankByAi() {
			if ( ! state.appsJobId || state.aiRankLoading ) {
				return;
			}
			state.aiRankLoading = true;
			state.appsError     = '';
			try {
				const response = yield wcbFetch(
					state.apiBase + '/ai/ranked-applications/' + String( state.appsJobId ),
					{ headers: { 'X-WP-Nonce': state.nonce }, timeout: 120000 }
				);
				if ( ! response.ok ) {
					throw new Error( 'rank failed' );
				}
				const ranked = yield response.json();
				const byId   = {};
				( Array.isArray( ranked ) ? ranked : [] ).forEach( ( r ) => {
					byId[ Number( r.application_id ) ] = r;
				} );
				state.applications = state.applications.map( ( a ) => {
					const r = byId[ a.id ];
					return r
						? { ...a, aiScore: Number( r.score ), aiReason: String( r.reason || '' ), aiSummary: String( r.summary || '' ), aiScoreLabel: aiScoreLabel( Number( r.score ) ) }
						: a;
				} );
				state.aiRanked = true;
			} catch {
				state.appsError = t( 'errorConnectionApps', 'Connection error loading applications.' );
			} finally {
				state.aiRankLoading = false;
			}
		},

		// Shared status PATCH — the single source of truth for changing an
		// application's status. Both the detail-panel <select> (updateAppStatus)
		// and the Board drag-and-drop (onColumnDrop) route through here so the
		// same endpoint, local update, and confirmation message apply.
		*applyStatusChange( appId, newStatus ) {
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
					state.statusMsg = t( 'statusSaved', 'Status updated. The candidate has been notified.' );
				} else {
					state.statusMsg = t( 'statusError', 'Could not update the status. Please try again.' );
				}
			} catch {
				state.statusMsg = t( 'statusError', 'Could not update the status. Please try again.' );
			}
		},

		*updateAppStatus( event ) {
			const appId     = Number( event.target.dataset.wcbAppId );
			const newStatus = event.target.value;
			yield actions.applyStatusChange( appId, newStatus );
		},

		setAppsLayout( event ) {
			state.appsLayout = event.target.dataset.layout === 'board' ? 'board' : 'list';
		},

		onCardDragStart( event ) {
			state.draggingAppId = Number( getContext().app.id );
			event.dataTransfer.effectAllowed = 'move';
		},

		onColumnDragOver( event ) {
			event.preventDefault();
		},

		*onColumnDrop( event ) {
			event.preventDefault();
			const status = getContext().column.key;
			const appId  = state.draggingAppId;
			state.draggingAppId = null;
			if ( appId && status ) {
				const app = state.applications.find( ( a ) => a.id === appId );
				if ( app && app.status !== status ) {
					yield actions.applyStatusChange( appId, status );
				}
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
					title:        t( 'confirmCloseTitle', 'Close this job?' ),
					message:      t( 'confirmCloseJob', 'Are you sure you want to close this job? It will no longer be visible to candidates.' ),
					confirmText:  t( 'confirmCloseConfirm', 'Close job' ),
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
						state.jobs[ idx ].statusLabel = t( 'jobStatusClosed', 'Closed' );
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
						state.jobs[ idx ].statusLabel = wasRejected
							? t( 'jobStatusPending', 'Pending' )
							: t( 'jobStatusPublished', 'Published' );
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
					state.accountMsg     = t( 'accountUpdated', 'Account updated.' );
				} else {
					state.accountMsgType = 'error';
					state.accountMsg     = data?.message || t( 'errorSaveAccount', 'Could not save your account.' );
				}
			} catch {
				state.accountMsgType = 'error';
				state.accountMsg     = t( 'errorConnectionRetry', 'Connection error. Please try again.' );
			} finally {
				state.accountSaving = false;
			}
		},

		*changePassword() {
			state.pwMsg = '';
			if ( ! state.curPassword || ! state.newPassword ) {
				state.pwMsgType = 'error';
				state.pwMsg     = t( 'pwEnterBoth', 'Enter your current and new password.' );
				return;
			}
			if ( state.newPassword !== state.confPassword ) {
				state.pwMsgType = 'error';
				state.pwMsg     = t( 'pwMismatch', 'New password and confirmation do not match.' );
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
					state.pwMsg        = t( 'pwUpdated', 'Password updated.' );
				} else {
					state.pwMsgType = 'error';
					state.pwMsg     = data?.message || t( 'errorUpdatePassword', 'Could not update your password.' );
				}
			} catch {
				state.pwMsgType = 'error';
				state.pwMsg     = t( 'errorConnectionRetry', 'Connection error. Please try again.' );
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
				state.error = t( 'errorSaveLogo', 'Please save your company profile before uploading a logo.' );
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
				state.error = t( 'errorCompanyNameRequired', 'Company name is required.' );
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
					state.error = ( data && data.message )
						? String( data.message )
						: t( 'errorSaveProfile', 'Could not save profile. Please try again.' );
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
				state.error = t( 'errorConnection', 'Connection error. Please check your network and try again.' );
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

		*deleteBellNotification() {
			const ctx = getContext();
			const id  = ctx.notif?.id;
			if ( ! id ) {
				return;
			}
			yield wcbFetch( state.apiBase + '/notifications/' + String( id ), {
				method:  'DELETE',
				headers: { 'X-WP-Nonce': state.nonce },
			} );
			state.bellNotifications = state.bellNotifications.filter( ( n ) => n.id !== id );
			state.bellUnreadCount   = state.bellNotifications.filter( ( n ) => ! n.is_read ).length;
		},

		*clearBellNotifications() {
			try {
				yield window.wcbConfirm( {
					title:       t( 'confirmClearAllTitle', 'Clear all notifications?' ),
					message:     t( 'confirmClearAllMsg', 'This permanently removes all of your notifications. This cannot be undone.' ),
					confirmText: t( 'clearAll', 'Clear all' ),
					destructive: true,
				} );
			} catch ( cancelled ) {
				return;
			}
			yield wcbFetch( state.apiBase + '/notifications', {
				method:  'DELETE',
				headers: { 'X-WP-Nonce': state.nonce },
			} );
			state.bellNotifications = [];
			state.bellUnreadCount   = 0;
		},
	},
} );
