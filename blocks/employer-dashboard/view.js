/**
 * WP Career Board — employer-dashboard block Interactivity API store.
 *
 * @package WP_Career_Board
 */
import { store, getContext } from '@wordpress/interactivity';
import { wcbFetch } from '@wcb/fetch';
import { customFieldValue } from '@wcb/fields';
import { formatSiteDate, relativeTime, rowMenuActions, showError, tabRouter } from '@wcb/ui';

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
	'companies',
	'company',
	'saved-jobs',
	'saved-companies',
	'saved-resumes',
	'settings',
	'notifications',
	'credits',
];

const views = tabRouter( VALID_VIEWS, 'wcb_employer_view' );

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
// Applicants fetched per page (endpoint maximum is 100).
const APPS_PER_PAGE = 50;

/**
 * Open an applicant from the list. Where list and detail stack (tablet and
 * phone) the detail sits below the list, so bring it into view.
 *
 * @param {HTMLElement} row Applicant row.
 */
function pickApplicant( row ) {
	const id = parseInt( row.dataset.wcbAppId, 10 );
	state.selectedAppId = Number.isNaN( id ) ? null : id;
	if ( null !== state.selectedAppId && window.matchMedia( '(max-width: 1024px)' ).matches ) {
		window.requestAnimationFrame( () => row.closest( '.wcb-split-panel' )?.querySelector( '.wcb-applicant-detail' )?.scrollIntoView( { behavior: 'smooth', block: 'start' } ) );
	}
}

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
	// Approved, waiting for enough credits: it goes live on its own once
	// the balance covers it.
	if ( job.awaiting_payment ) {
		return t( 'jobStatusAwaitingPayment', 'Awaiting payment' );
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
		// The email field differs from the saved one: ask for the current password.
		// Names the row's "more actions" button for screen readers.
		// Only a company with a public page can be main (an offline one has no permalink).
		get canMakeMain() {
			const co = getContext().co;
			return ! co.isMain && !! co.permalink;
		},
		// Saved rows ('...' menu): named after the saved job, company or resume.
		get savedMenuLabel() {
			const ctx  = getContext();
			const item = ctx.bookmark || ctx.job || ctx.company || ctx.resume || {};
			return t( 'moreActionsFor', 'More actions for %s' ).replace( '%s', item.title || item.name || '' );
		},
		get companyMenuLabel() {
			return t( 'moreActionsFor', 'More actions for %s' ).replace( '%s', getContext().co.name );
		},
		get rowMenuLabel() {
			return t( 'moreActionsFor', 'More actions for %s' ).replace( '%s', getContext().job.title );
		},
		get emailChanged() {
			return state.accountEmail.trim() !== state.accountEmailSaved;
		},
		get activeTabLabel() {
			const map = {
				overview:     t( 'overview', 'Overview' ),
				jobs:         t( 'myJobs', 'My Jobs' ),
				applications: t( 'applications', 'Applications' ),
				company:      t( 'profile', 'Profile' ),
				'post-job':   t( 'postAJob', 'Post a Job' ),
				// Every tab names itself in the phone menu button (was 'Dashboard').
				companies:         t( 'tabCompanies', 'Companies' ),
				'saved-jobs':      t( 'tabSavedJobs', 'Saved Jobs' ),
				'saved-companies': t( 'tabSavedCompanies', 'Saved Companies' ),
				'saved-resumes':   t( 'tabSavedResumes', 'Saved Resumes' ),
				settings:          t( 'tabSettings', 'Settings' ),
				notifications:     t( 'tabNotifications', 'Notifications' ),
				credits:           t( 'tabCredits', 'Credits' ),
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
		get isViewCompanies() {
			return state.currentView === 'companies';
		},
		get hasManyCompanies() {
			return state.companies.length > 1;
		},
		get isCompanyBusy() {
			return state.companyBusy > 0;
		},
		get companyEditUrl() {
			const url = new URL( state.dashboardUrl );
			url.searchParams.set( 'company', String( getContext().co.id ) );
			return url.toString() + '#company';
		},
		get mainRoleLabel() {
			return state.mainCompanyName
				? t( 'employerAt', 'Employer · %s' ).replace( '%s', state.mainCompanyName )
				: t( 'employerRole', 'Employer' );
		},
		// The main company's page; empty (link hidden) when there is no company.
		get mainCompanyUrl() {
			const main = state.companies.find( ( c ) => c.isMain );
			return ( main && main.permalink ) || '';
		},
		get mainInitials() {
			const n = state.mainCompanyName || state.displayName;
			return n
				? n.split( ' ' ).filter( ( p ) => /^[\p{L}\p{N}]/u.test( p ) ).map( ( p ) => p[ 0 ] ).slice( 0, 2 ).join( '' ).toUpperCase() || '?'
				: '?';
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
		get isViewCredits() {
			return state.currentView === 'credits';
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
			if ( threshold <= 0 || ! state.creditHasHistory ) {
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
			// Jobs load from /employers/me/jobs (posted by the employer or under
			// any company they run), so an employer without a company profile
			// still has real jobs to show — never gate on company existence.
			return ! state.loading && state.filteredJobs.length > 0;
		},
		// Chips and search filter something only once there are jobs.
		get hasAnyJobs() {
			return state.jobs.length > 0;
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
			// Server total (render time, then every reload), never the loaded page.
			return fmtNumber( state.ssrTotalJobs || 0 );
		},
		get publishedJobs() {
			// A company-filtered list is not the whole dashboard: keep the server figure.
			return fmtNumber(
				state.jobs.length && ! state.companyFilter
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
			const hint     = state.appsHints[ String( state.companyFilter ) ] || {};
			if ( ( state.appsJobSearch || '' ).trim() === '' ) {
				// Whole sentence pre-pluralised in render.php from a COUNT(*) of
				// jobs that have applications (per company filter value) — matches
				// found, not the rows the REST page happened to load.
				return hint.label || '';
			}
			// The total and the plural form of "job(s)" are resolved in render.php
			// from the same COUNT(*); only the runtime-varying search-match count is
			// filled in here. No client-side plural/number assembly.
			return ( hint.filtered || '%1$s' ).replace( '%1$s', fmtNumber( filtered ) );
		},

		// The folded job control: the chosen job and its application count.
		get appsJobLabel() {
			if ( state.isAllApps ) {
				return t( 'allJobs', 'All jobs' );
			}
			return state.jobsWithApps.find( ( j ) => j.id === state.appsJobId )?.title || state.appsJobTitle || '';
		},
		get appsJobCount() {
			return state.isAllApps
				? state.totalApps
				: ( state.jobsWithApps.find( ( j ) => j.id === state.appsJobId )?.appCountLabel || '' );
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

		// Only a live job can be closed; the REST route refuses the rest (a
		// pending job closed and reopened used to skip moderation).
		get isJobNotClosable() {
			const ctx = getContext();
			return 'publish' !== ctx.job?.status;
		},

		// Applications.
		get totalApps() {
			// Server total, never the loaded page (capped at 50).
			return fmtNumber( state.ssrTotalApps || 0 );
		},
		get hasApplications() {
			return ! state.appsLoading && state.applications.length > 0;
		},
		// No job picked: every job's applications (GET /employers/me/applications).
		get isAllApps() {
			return ! state.appsJobId;
		},
		// Filters follow the list's total, not the filtered page: a zero-count
		// status must not hide the way back.
		get jobHasApplications() {
			return ! state.appsError && state.appsCounts.total > 0;
		},
		// Board view and CSV export work on one job's applications.
		get oneJobHasApplications() {
			return state.appsJobId > 0 && state.jobHasApplications;
		},
		get noFilteredApplications() {
			return state.jobHasApplications && ! state.appsLoading && 0 === state.applications.length;
		},
		get noApplications() {
			return state.appsLoaded && ! state.appsLoading && ! state.appsError && ! state.appsCounts.total;
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
		get selectedAppRated() {
			return ( state.selectedApp?.rating || 0 ) > 0;
		},
		get isStarOn() {
			return ( getContext().star?.value || 0 ) <= ( state.selectedApp?.rating || 0 );
		},
		get appsExportUrl() {
			return state.appsJobId
				? state.apiBase + '/jobs/' + String( state.appsJobId ) + '/applications/export?_wpnonce=' + state.nonce
				: '';
		},
		get isBoardOptSelected() {
			const ctx = getContext();
			return ctx.opt?.key === ctx.app?.status;
		},
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
				return { key: d.key, label: d.label, count: countLabel( state.appsCounts.by_status[ d.key ] ), apps };
			} );
		},

		// The board has one column per live status, so closed, withdrawn and removed
		// applications are in the total but on no column: say so instead of letting the
		// column counts disagree with "All".
		get appsNotOnBoard() {
			const by = state.appsCounts.by_status || {};
			const onBoard = [ 'submitted', 'reviewing', 'shortlisted', 'hired', 'rejected' ]
				.reduce( ( sum, key ) => sum + Number( by[ key ] || 0 ), 0 );
			return Math.max( 0, Number( state.appsCounts.total || 0 ) - onBoard );
		},
		get showBoardNote() {
			return state.isAppsBoardLayout && state.appsNotOnBoard > 0;
		},
		get boardNoteLabel() {
			return t( 'boardNotShown', 'Not on the board (closed, withdrawn or removed): %s' )
				.replace( '%s', fmtNumber( state.appsNotOnBoard ) );
		},

		// Per-status counts — computed from already-loaded applications, no extra
		// REST calls. Zero renders as an empty pill, anything else as a
		// site-locale-formatted number.
		get appsCountAll() {
			return countLabel( state.appsCounts.total );
		},
		get appsCountSubmitted() {
			return countLabel( state.appsCounts.by_status.submitted );
		},
		get appsCountReviewing() {
			return countLabel( state.appsCounts.by_status.reviewing );
		},
		get appsCountShortlisted() {
			return countLabel( state.appsCounts.by_status.shortlisted );
		},
		get appsCountRejected() {
			return countLabel( state.appsCounts.by_status.rejected );
		},
		get appsCountHired() {
			return countLabel( state.appsCounts.by_status.hired );
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
		// Withdrawn / job removed: an outcome the employer cannot change.
		get selectedAppClosed() {
			const status = state.selectedApp?.status;
			return !! status && ! ( state.actionableStatuses || [] ).includes( status );
		},
		get selectedAppStatusLabel() {
			return state.selectedApp?.statusLabel ?? '';
		},
		get selectedAppCoverLetter() {
			return state.selectedApp?.cover_letter ?? '';
		},
		get selectedAppHasCoverLetter() {
			return '' !== state.selectedAppCoverLetter.trim();
		},
		get selectedAppCustomFields() {
			return state.selectedApp?.custom_fields ?? [];
		},
		get selectedAppHasCustomFields() {
			return ( state.selectedApp?.custom_fields ?? [] ).length > 0;
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
		get isAppClosed() {
			const status = getContext().app?.status;
			return !! status && ! ( state.actionableStatuses || [] ).includes( status );
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
		// Needs attention: live jobs closing within 7 days (site-calendar dates
		// compared as Y-m-d), soonest first. shortcut: reads the loaded page of
		// jobs (50); a job past that page is not listed until it is loaded.
		get closingSoonJobs() {
			return state.jobs
				.filter( ( j ) => 'publish' === j.status && j.deadline && j.deadline >= state.siteToday && j.deadline <= state.siteSoon )
				.sort( ( a, b ) => ( a.deadline < b.deadline ? -1 : 1 ) )
				.slice( 0, 5 )
				.map( ( j ) => ( {
					...j,
					closesLabel: t( 'closesOn', 'Closes %s' ).replace( '%s', formatSiteDate( j.deadline, state.locale ) ),
				} ) );
		},
		get nothingNeedsAttention() {
			return ! state.awaitingApps && 0 === state.closingSoonJobs.length;
		},
		get newThisWeek() {
			// Server COUNT(*) over the whole scope, never the loaded page of applications.
			return fmtNumber( state.ssrNewThisWeek || 0 );
		},

		// Company helpers.
		// Sidebar identity falls back to the user's display name when no company
		// name is set yet, so the sidebar never shows a lone "?" with no label.

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
			state.currentView = views.initial( state.currentView );
			const savedJob = Number( sessionStorage.getItem( 'wcb_employer_apps_job' ) );
			if ( savedJob > 0 && state.currentView === 'applications' ) {
				state.appsJobId = savedJob;
			}
			views.go( state.currentView );

			// Browser back/forward and manual hash edits stay in sync.
			window.addEventListener( 'hashchange', () => {
				const next = views.read();
				if ( next && next !== state.currentView ) {
					state.currentView = next;
				}
			} );

			state.loading = true;
			state.error   = '';

			try {
				yield actions.loadJobs();

				// Overview "Recent Applications": every company the employer runs,
				// plus jobs posted before a company profile existed.
				const appsResp = yield wcbFetch(
					state.apiBase + '/employers/me/applications',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);
				if ( appsResp.ok ) {
					const appsData = yield appsResp.json();
					state.allApplications = Array.isArray( appsData ) ? appsData : ( appsData?.applications ?? [] );
					if ( typeof appsData?.total === 'number' ) {
						state.ssrTotalApps = appsData.total;
					}
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

			yield actions.loadApplications();

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
			views.go( 'overview' );
		},

		// Fetch + normalize the employer's jobs. Shared by init() and the
		// post-a-job refresh path so My Jobs reflects a just-posted job without
		// a page reload.
		*loadJobs() {
			const jobsUrl = new URL( state.apiBase + '/employers/me/jobs' );
			jobsUrl.searchParams.set( 'per_page', '50' );
			if ( state.companyFilter ) {
				jobsUrl.searchParams.set( 'company_id', String( state.companyFilter ) );
			}

			const resp = yield wcbFetch( jobsUrl.toString(), { headers: { 'X-WP-Nonce': state.nonce } } );
			if ( ! resp.ok ) {
				state.error = t( 'errorLoadJobs', 'Could not load your jobs.' );
				return;
			}
			const jobsData = yield resp.json();
			const jobs     = Array.isArray( jobsData ) ? jobsData : ( jobsData?.jobs ?? [] );
			// The Total Jobs card counts every company; a filtered total is not it.
			if ( typeof jobsData?.total === 'number' && ! state.companyFilter ) {
				state.ssrTotalJobs = jobsData.total;
			}
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
				canFeature: Number( state.featuredCost ) > 0 && j.status === 'publish' && ! j.featured,
			} ) );
		},

		// Called by the embedded Post-a-Job form after a successful post: the
		// badges, the Overview figures and My Jobs update now, not on the next
		// visit to My Jobs.
		*afterJobPosted( balance ) {
			if ( typeof balance === 'number' ) {
				state.creditBalance = balance;
			}
			yield actions.loadJobs();
		},

		switchToJobs() {
			state.currentView = 'jobs';
			state.error       = '';
			state.navOpen     = false;
			views.go( 'jobs' );
		},

		// Overview tiles: open My Jobs on the filter the tile counts.
		openJobsFiltered( event ) {
			state.jobFilter = event.currentTarget.dataset.wcbFilter || 'all';
			actions.switchToJobs();
		},

		// 'Review': every job's applications, narrowed to the unreviewed ones.
		*openAwaitingApps() {
			state.appsJobId     = 0;
			state.selectedAppId = null;
			state.appsFilter    = 'submitted';
			actions.switchToApplications();
			yield actions.loadApplications();
		},

		switchToApplications() {
			state.currentView = 'applications';
			state.navOpen     = false;
			views.go( 'applications' );
		},

		switchToCompanies() {
			state.currentView = 'companies';
			state.error       = '';
			state.navOpen     = false;
			views.go( 'companies' );
		},

		// Show a fresh company list everywhere it appears: this view, the
		// sidebar, the company filters and the Post a Job picker.
		applyCompanies( list ) {
			state.companies = list;
			const main      = list.find( ( c ) => c.isMain );
			state.mainCompanyId   = main ? main.id : 0;
			state.mainCompanyName = main ? main.name : '';
			state.mainLogoUrl     = main ? main.logo : '';
			state.noCompany       = ! main;
			if ( state.companyFilter && ! list.some( ( c ) => c.id === state.companyFilter ) ) {
				state.companyFilter = 0;
			}
			const form = store( 'wcb-job-form' ).state;
			form.companyList  = list.map( ( c ) => ( { id: c.id, name: c.name } ) );
			form.companyNames = Object.fromEntries( list.map( ( c ) => [ String( c.id ), c.name ] ) );
			// A new job starts on the main company; a job being edited keeps its own while it exists.
			if ( main && ( ! form.editJobId || ! list.some( ( c ) => c.id === Number( form.companyId ) ) ) ) {
				form.companyId   = main.id;
				form.companyName = main.name;
			}
		},

		*makeMainCompany() {
			const id = getContext().co.id;
			state.companyBusy = id;
			try {
				const response = yield wcbFetch( state.apiBase + '/employers/' + String( id ) + '/main', {
					method:  'POST',
					headers: { 'X-WP-Nonce': state.nonce },
				} );
				if ( response.ok ) {
					actions.applyCompanies( ( yield response.json() ).companies || [] );
				} else {
					yield actions.reportRefusal( response );
				}
			} catch {
				showError( t( 'errorConnection', 'Connection error. Please check your network and try again.' ) );
			} finally {
				state.companyBusy = 0;
			}
		},

		*removeCompany() {
			const co = getContext().co;
			try {
				yield window.wcbConfirm( {
					title:       t( 'removeCompanyTitle', 'Remove this company?' ),
					message:     t( 'removeCompanyText', 'Its page goes offline and its live jobs close; their applicants are told. Jobs waiting for review go back to draft. A site admin can restore the company, but closed jobs stay closed until you reopen them.' ),
					confirmText: t( 'removeCompanyButton', 'Remove company' ),
					destructive: true,
				} );
			} catch ( cancelled ) {
				return;
			}
			state.companyBusy = co.id;
			try {
				const response = yield wcbFetch( state.apiBase + '/employers/' + String( co.id ), {
					method:  'DELETE',
					headers: { 'X-WP-Nonce': state.nonce },
				} );
				if ( response.ok ) {
					actions.applyCompanies( ( yield response.json() ).companies || [] );
					// Its jobs just closed: My Jobs and the counts follow.
					yield actions.loadJobs();
				} else {
					yield actions.reportRefusal( response );
				}
			} catch {
				showError( t( 'errorConnection', 'Connection error. Please check your network and try again.' ) );
			} finally {
				state.companyBusy = 0;
			}
		},

		switchToCompany() {
			state.currentView = 'company';
			state.saved       = false;
			state.error       = '';
			state.navOpen     = false;
			views.go( 'company' );
		},

		switchToSettings() {
			state.currentView = 'settings';
			state.error       = '';
			state.navOpen     = false;
			views.go( 'settings' );
		},

		switchToCredits() {
			state.currentView = 'credits';
			state.error       = '';
			state.navOpen     = false;
			views.go( 'credits' );
		},

		*switchToNotifications() {
			state.currentView = 'notifications';
			state.error       = '';
			state.navOpen     = false;
			views.go( 'notifications' );
			if ( state.bellEnabled && state.bellNotifications.length === 0 ) {
				yield actions.fetchBellNotifications();
			}
		},

		switchToPostJob() {
			state.currentView = 'post-job';
			state.navOpen     = false;
			views.go( 'post-job' );
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
			views.go( 'saved-jobs' );

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
			views.go( 'saved-companies' );

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
			views.go( 'saved-resumes' );

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
				showError( t( 'errorConnection', 'Connection error. Please check your network and try again.' ) );
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
				showError( t( 'errorConnection', 'Connection error. Please check your network and try again.' ) );
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
				showError( t( 'errorConnection', 'Connection error. Please check your network and try again.' ) );
			}
		},

		setJobFilter( event ) {
			const f = event.target.dataset.wcbFilter;
			if ( f ) {
				state.jobFilter = f;
			}
		},

		// My Jobs + Applications company filter: reload the job list for the
		// company and drop a selected job that is no longer in it.
		*setCompanyFilter( event ) {
			state.companyFilter = parseInt( event.target.value, 10 ) || 0;
			state.loading       = true;
			state.error         = '';
			try {
				yield actions.loadJobs();
			} catch {
				state.error = t( 'errorConnection', 'Connection error. Please check your network and try again.' );
			} finally {
				state.loading = false;
			}
			if ( state.appsJobId > 0 && ! state.jobs.some( ( j ) => Number( j.id ) === Number( state.appsJobId ) ) ) {
				state.appsJobId     = 0;
				state.selectedAppId = null;
				state.applications  = [];
				try { sessionStorage.removeItem( 'wcb_employer_apps_job' ); } catch {}
			}
			// All jobs follows the company filter.
			if ( ! state.appsJobId ) {
				yield actions.loadApplications();
			}
		},

		setJobSearch( event ) {
			state.jobSearch = event.target.value;
		},

		setNoteDraft( event ) {
			state.noteDraft = event.target.value;
		},

		*addNote() {
			const appId = state.selectedAppId;
			if ( ! appId || ! state.noteDraft.trim() ) {
				return;
			}
			// Guard against a double-click firing two concurrent POSTs (this
			// yields on wcbFetch, so a second click during that wait used to
			// re-enter before the first resolved) - Basecamp 10350213909.
			if ( state.addingNote ) {
				return;
			}
			state.addingNote = true;
			try {
				const response = yield wcbFetch( state.apiBase + '/applications/' + String( appId ) + '/notes', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': state.nonce },
					body: JSON.stringify( { text: state.noteDraft } ),
				} );
				if ( response.ok ) {
					state.appNotes  = yield response.json();
					state.noteDraft = '';
				}
			} finally {
				state.addingNote = false;
			}
		},

		*deleteNote() {
			const appId = state.selectedAppId;
			const note  = getContext().note;
			const response = yield wcbFetch( state.apiBase + '/applications/' + String( appId ) + '/notes/' + note.id, {
				method: 'DELETE',
				headers: { 'X-WP-Nonce': state.nonce },
			} );
			if ( response.ok ) {
				state.appNotes = yield response.json();
			}
		},

		*setRating() {
			const app   = state.selectedApp;
			const value = getContext().star.value;
			if ( ! app ) {
				return;
			}
			// Clicking the current rating again clears it.
			const rating   = app.rating === value ? 0 : value;
			const response = yield wcbFetch( state.apiBase + '/applications/' + String( app.id ) + '/rating', {
				method: 'PUT',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': state.nonce },
				body: JSON.stringify( { rating } ),
			} );
			if ( response.ok ) {
				const data = yield response.json();
				const idx  = state.applications.findIndex( ( a ) => a.id === app.id );
				if ( idx !== -1 ) {
					state.applications[ idx ].rating = data.rating;
				}
			}
		},

		// Board card: open the applicant's details (the list view shows them).
		openFromBoard( event ) {
			if ( event.target.closest( 'select' ) ) {
				return;
			}
			state.selectedAppId = Number( getContext().app.id );
			state.appsLayout    = 'list';
		},

		openFromBoardKey( event ) {
			if ( event.target === event.currentTarget && ( event.key === 'Enter' || event.key === ' ' ) ) {
				event.preventDefault();
				state.selectedAppId = Number( getContext().app.id );
				state.appsLayout    = 'list';
			}
		},

		selectApplicant( event ) {
			pickApplicant( event.currentTarget );
		},

		handleRowKeydown( event ) {
			if ( event.key === 'Enter' || event.key === ' ' ) {
				event.preventDefault();
				pickApplicant( event.currentTarget );
			}
		},

		*setAppsFilter( event ) {
			const f = event.target.closest( '[data-wcb-filter]' )?.dataset.wcbFilter;
			if ( f && f !== state.appsFilter ) {
				state.appsFilter = f;
				yield actions.loadApplications();
			}
		},

		*switchAppsJob( event ) {
			const id = parseInt( event.currentTarget.dataset.wcbJobId, 10 );
			if ( Number.isNaN( id ) ) {
				return;
			}
			event.currentTarget.closest( 'details' )?.removeAttribute( 'open' );
			state.appsJobId     = id;
			state.selectedAppId = null;
			state.appsFilter    = 'all';
			state.appsLayout    = id ? state.appsLayout : 'list';
			state.currentView   = 'applications';
			views.go( 'applications' );
			try {
				if ( id ) {
					sessionStorage.setItem( 'wcb_employer_apps_job', String( id ) );
				} else {
					sessionStorage.removeItem( 'wcb_employer_apps_job' );
				}
			} catch {}
			yield actions.loadApplications();
		},

		setAppsJobSearch( event ) {
			state.appsJobSearch = event.target.value;
		},

		// One page of the selected job's applicants (server-side status
		// filter), plus per-status totals for the whole job. `append` adds the
		// next page for Load more.
		*loadApplications( append = false ) {
			const flag = append ? 'appsLoadingMore' : 'appsLoading';
			state[ flag ]   = true;
			state.appsError = '';

			try {
				// One job's applications, or every job's (newest first, company filter applied).
				const url = new URL( state.appsJobId
					? state.apiBase + '/jobs/' + String( state.appsJobId ) + '/applications'
					: state.apiBase + '/employers/me/applications' );
				if ( ! state.appsJobId && state.companyFilter ) {
					url.searchParams.set( 'company_id', String( state.companyFilter ) );
				}
				url.searchParams.set( 'per_page', String( APPS_PER_PAGE ) );
				url.searchParams.set( 'page', String( append ? state.appsPage + 1 : 1 ) );
				if ( state.appsFilter !== 'all' ) {
					url.searchParams.set( 'status', state.appsFilter );
				}

				const response = yield wcbFetch( url.toString(), { headers: { 'X-WP-Nonce': state.nonce } } );

				if ( ! response.ok ) {
					state.appsError = t( 'errorLoadApps', 'Could not load applications.' );
					return;
				}

				const appsData = yield response.json();
				const apps     = ( appsData?.applications ?? [] ).map( ( a ) => ( {
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
				state.applications = append ? [ ...state.applications, ...apps ] : apps;
				// Side by side (desktop), open the newest applicant instead of an empty pane.
				if ( ! append && null === state.selectedAppId && apps.length && window.matchMedia( '(min-width: 1025px)' ).matches ) {
					state.selectedAppId = apps[ 0 ].id;
				}
				state.appsPage     = append ? state.appsPage + 1 : 1;
				state.appsHasMore  = !! appsData?.has_more;
				state.appsCounts   = appsData?.counts ?? { total: 0, by_status: {} };
				state.appsLoaded   = true;
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
				state[ flag ] = false;
			}
		},

		*loadMoreApps() {
			yield actions.loadApplications( true );
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
					if ( ! r ) {
						return a;
					}
					// A provider failure is "Not scored" (retried later), never 0%.
					if ( typeof r.score !== 'number' ) {
						return { ...a, aiScore: undefined, aiScoreLabel: t( 'aiNotScored', 'Not scored' ) };
					}
					return { ...a, aiScore: r.score, aiReason: String( r.reason || '' ), aiSummary: String( r.summary || '' ), aiScoreLabel: aiScoreLabel( r.score ) };
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
					// The server's label and tone win: one wording everywhere (ApplicationStatus).
					const data = yield response.json();
					const idx  = state.applications.findIndex( ( a ) => a.id === appId );
					if ( idx !== -1 && data.changed ) {
						const by = state.appsCounts.by_status;
						const was = state.applications[ idx ].status;
						by[ was ] = Math.max( 0, ( by[ was ] || 0 ) - 1 );
						by[ data.status ] = ( by[ data.status ] || 0 ) + 1;
					}
					if ( idx !== -1 ) {
						state.applications[ idx ].status      = data.status || newStatus;
						state.applications[ idx ].statusLabel = data.status_label || state.applications[ idx ].statusLabel;
						state.applications[ idx ].status_tone = data.status_tone || '';
					}
					if ( ! data.changed ) {
						state.statusMsg = t( 'statusUnchanged', 'No change. The candidate was not notified.' );
					} else {
						state.statusMsg = data.notified
							? t( 'statusSaved', 'Status updated. The candidate has been notified.' )
							: t( 'statusSavedGuest', 'Status updated. This applicant left no email address, so they were not notified.' );
					}
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

		*featureJob( event ) {
			const jobId = Number( event.target.dataset.wcbJobId );
			if ( ! jobId ) {
				return;
			}
			try {
				yield window.wcbConfirm( {
					title:       t( 'confirmFeatureTitle', 'Feature this job?' ),
					message:     t( 'confirmFeatureMsg', 'Featured jobs list first. This uses %s credits from your balance.' ).replace( '%s', fmtNumber( state.featuredCost ) ),
					confirmText: t( 'confirmFeatureConfirm', 'Feature job' ),
				} );
			} catch ( cancelled ) {
				return;
			}
			state.error = '';
			try {
				const response = yield wcbFetch( state.apiBase + '/jobs/' + String( jobId ) + '/feature', {
					method:  'POST',
					headers: { 'X-WP-Nonce': state.nonce },
				} );
				const data = yield response.json();
				if ( ! response.ok ) {
					// 402 carries the Credits tab link; the low-balance banner and
					// the Credits nav item offer it too.
					state.error = ( data && data.message ) || t( 'errorConnection', 'Connection error. Please check your network and try again.' );
					return;
				}
				const idx = state.jobs.findIndex( ( j ) => j.id === jobId );
				if ( idx !== -1 ) {
					state.jobs[ idx ].featured   = true;
					state.jobs[ idx ].canFeature = false;
				}
				if ( typeof data.balance === 'number' ) {
					state.creditBalance = data.balance;
				}
			} catch {
				state.error = t( 'errorConnection', 'Connection error. Please check your network and try again.' );
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
					message:      t( 'confirmCloseJob', 'It leaves the job listings and stops taking applications. Applicants you have not hired or rejected are told the position is closed.' ),
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
				} else {
					yield actions.reportRefusal( response );
				}
			} catch {
				showError( t( 'errorConnection', 'Connection error. Please check your network and try again.' ) );
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
						state.jobs[ idx ].closedNote  = '';
					}
				} else {
					yield actions.reportRefusal( response );
				}
			} catch {
				showError( t( 'errorConnection', 'Connection error. Please check your network and try again.' ) );
			}
		},

		...rowMenuActions,

		// The server said no (402 credits, 409 company removed): show its reason.
		*reportRefusal( response ) {
			const data = yield response.json().catch( () => ( {} ) );
			showError( data.message || t( 'errorConnection', 'Connection error. Please check your network and try again.' ) );
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
							display_name:     state.accountName,
							email:            state.accountEmail,
							current_password: state.accountEmailPassword,
						} ),
					}
				);
				const data = yield response.json();
				if ( response.ok ) {
					state.accountEmailPassword = '';
					state.accountName    = data.display_name;
					state.accountEmail   = data.email;
					state.accountEmailSaved = data.email;
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
			const value = customFieldValue( target );
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
				const data = yield response.json();
				if ( response.ok ) {
					state.companyLogoUrl = data.logo_url;
					if ( state.companyId === state.mainCompanyId ) {
						state.mainLogoUrl = data.logo_url;
					}
					const card = state.companies.find( ( c ) => c.id === state.companyId );
					if ( card ) {
						card.logo = data.logo_url;
					}
				} else {
					state.error = ( data && data.message ) || t( 'errorConnection', 'Connection error. Please check your network and try again.' );
				}
			} catch {
				state.error = t( 'errorConnection', 'Connection error. Please check your network and try again.' );
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

				if ( isNew && data.id ) {
					// Open the new company's own profile (logo, custom fields), saved.
					const url = new URL( state.dashboardUrl );
					url.searchParams.set( 'company', String( data.id ) );
					window.location.assign( url.toString() + '#company' );
					return;
				}
				if ( state.companyId === state.mainCompanyId ) {
					state.mainCompanyName = state.companyName;
				}
				const card = state.companies.find( ( c ) => c.id === state.companyId );
				if ( card ) {
					card.name = state.companyName;
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
					state.bellNotifications = ( data.notifications || [] ).map( ( n ) => ( { ...n, created_at: relativeTime( state.locale, n.created_at ) } ) );
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

	callbacks: {
		// Notes for the applicant in the detail panel; re-runs when another
		// applicant is selected (reads state.selectedAppId).
		loadNotes() {
			const id = state.selectedAppId;
			state.noteDraft = '';
			if ( ! id ) {
				state.appNotes = [];
				return;
			}
			wcbFetch( state.apiBase + '/applications/' + String( id ) + '/notes', { headers: { 'X-WP-Nonce': state.nonce } } )
				.then( ( r ) => ( r.ok ? r.json() : [] ) )
				.then( ( notes ) => {
					if ( state.selectedAppId === id ) {
						state.appNotes = Array.isArray( notes ) ? notes : [];
					}
				} )
				.catch( () => {} );
		},
	},
} );
