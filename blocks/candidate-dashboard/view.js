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
import { wcbFetch } from '@wcb/fetch';

/**
 * Tabs that are eligible to appear in the URL hash. Anything outside this
 * allowlist is ignored on read so a stale `#whatever` never lands the user
 * in an undefined tab state.
 */
const VALID_TABS = [
	'overview',
	'applications',
	'bookmarks',
	'saved-companies',
	'saved-resumes',
	'resumes',
	'resume-builder',
	'alerts',
	'profile',
	'settings',
];

/**
 * Translation reader.
 *
 * view.js is a script module, so it cannot load JED catalogs. Every string is
 * seeded by render.php under `state.i18n`; the fallback mirrors the English in
 * the matching PHP `__()` call for the (impossible in practice) case where the
 * key is missing.
 *
 * @param {string} key      Key in the seeded `state.i18n` array.
 * @param {string} fallback English source text.
 * @return {string} Translated string.
 */
const t = ( key, fallback ) => ( state.i18n && state.i18n[ key ] ) || fallback;

/**
 * Read the active tab slug from the current URL hash, or null if missing/invalid.
 */
function readHashTab() {
	const raw = ( window.location.hash || '' ).replace( /^#/, '' );
	return VALID_TABS.includes( raw ) ? raw : null;
}

/**
 * Sync the URL hash to a given tab slug without pushing a new history entry.
 * `replaceState` keeps Back/Forward predictable — one entry per real navigation,
 * not per tab click. Skip the write when the hash already matches.
 */
function writeHashTab( tab ) {
	if ( ! VALID_TABS.includes( tab ) ) {
		return;
	}
	const target = '#' + tab;
	if ( window.location.hash === target ) {
		return;
	}
	const url = window.location.pathname + window.location.search + target;
	window.history.replaceState( null, '', url );
}

/**
 * Format a number against the SITE locale, not the browser locale.
 *
 * `Number#toLocaleString()` with no argument resolves against the visitor's
 * browser locale, so a de_DE site browsed from an en-US machine renders
 * "1,000" where it must render "1.000". `state.locale` is seeded by render.php
 * from SalaryFormat::locale() as a BCP-47 tag.
 *
 * @param {number|string} value Raw numeric value.
 * @return {string} Localised digits.
 */
function formatNumber( value ) {
	const n = Number( value );
	if ( ! Number.isFinite( n ) ) {
		return '';
	}
	try {
		return new Intl.NumberFormat( state.locale || undefined ).format( n );
	} catch {
		// Malformed site locale tag — fall back to a FIXED, deterministic locale
		// ('en-US') rather than `new Intl.NumberFormat()` with no argument, which
		// would resolve against the visitor's BROWSER locale (the exact bug this
		// function exists to avoid). A constant tag keeps output identical for
		// every visitor regardless of their browser.
		return new Intl.NumberFormat( 'en-US' ).format( n );
	}
}

/**
 * Resolve the CLDR plural category ('one', 'other', …) for a count against the
 * SITE locale. Used so plural nouns agree with a number that changes after
 * render (e.g. the resume count), which a PHP-frozen `_n()` cannot track.
 *
 * @param {number|string} n Count to classify.
 * @return {string} CLDR plural category.
 */
function pluralCategory( n ) {
	try {
		return new Intl.PluralRules( state.locale || undefined ).select( Number( n ) );
	} catch {
		return Number( n ) === 1 ? 'one' : 'other';
	}
}

/**
 * Compose "symbol + amount" through a translatable, numbered format string so
 * locales that place the symbol after the amount ("1 000 €") can reorder it.
 *
 * @param {number|string} value Raw amount.
 * @return {string} Localised money string.
 */
function formatMoney( value ) {
	const amount = formatNumber( value );
	if ( '' === amount ) {
		return '';
	}
	return t( 'moneyFormat', '%1$s%2$s' )
		.replace( '%1$s', String( state.currencySymbol || t( 'currencySymbolFallback', '$' ) ) )
		.replace( '%2$s', amount );
}

/**
 * Build the salary pill for a saved search.
 *
 * The bounds arrive as raw integers on the alert's `filters` payload, so the
 * label is assembled here rather than server-preformatted. Every separator and
 * marker ("–", "+", "Up to %s") is a seeded format string, never a `+` join.
 *
 * @param {number|string} min Minimum bound, falsy when unset.
 * @param {number|string} max Maximum bound, falsy when unset.
 * @return {string} Pill label, or '' when neither bound is set.
 */
function buildSalaryPill( min, max ) {
	if ( min && max ) {
		return t( 'salaryRange', '%1$s–%2$s' )
			.replace( '%1$s', formatMoney( min ) )
			.replace( '%2$s', formatMoney( max ) );
	}
	if ( min ) {
		return t( 'salaryOpenMin', '%s+' ).replace( '%s', formatMoney( min ) );
	}
	if ( max ) {
		return t( 'salaryUpTo', 'Up to %s' ).replace( '%s', formatMoney( max ) );
	}
	return '';
}

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
	if ( filters.remote ) pills.push( t( 'filterRemote', 'Remote' ) );
	if ( filters.salary_min || filters.salary_max ) {
		const salaryPill = buildSalaryPill( filters.salary_min, filters.salary_max );
		if ( salaryPill ) {
			pills.push( salaryPill );
		}
	}
	return pills;
}

const { state, actions } = store( 'wcb-candidate-dashboard', {
	state: {
		navOpen: false,
		get activeTabLabel() {
			const map = {
				overview:           t( 'tabOverview', 'Overview' ),
				applications:       t( 'tabApplications', 'My Applications' ),
				bookmarks:          t( 'tabBookmarks', 'Saved Jobs' ),
				'saved-companies':  t( 'tabSavedCompanies', 'Saved Companies' ),
				'saved-resumes':    t( 'tabSavedResumes', 'Saved Resumes' ),
				resumes:            t( 'tabResumes', 'My Resumes' ),
				alerts:             t( 'tabAlerts', 'Job Alerts' ),
				'resume-builder':   t( 'tabResumeBuilder', 'Edit Resume' ),
				profile:            t( 'tabProfile', 'Profile' ),
				settings:           t( 'tabSettings', 'Settings' ),
				notifications:      t( 'tabNotifications', 'Notifications' ),
			};
			return map[ state.tab ] || t( 'tabDashboard', 'Dashboard' );
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
		get isTabNotifications() {
			return state.tab === 'notifications';
		},
		get isTabSavedCompanies() {
			return state.tab === 'saved-companies';
		},
		get isTabSavedResumes() {
			return state.tab === 'saved-resumes';
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
			if ( state.maxResumes <= 0 ) {
				return '';
			}
			// The noun agrees with resumeCount, which is mutated CLIENT-SIDE as the
			// candidate creates/deletes resumes. A PHP `_n()` frozen at render against
			// the cap would show the wrong form once the count changes, so the plural
			// is resolved here against the live count via Intl.PluralRules.
			const template = ( 'one' === pluralCategory( state.resumeCount ) )
				? t( 'resumeCapOne', '%1$s/%2$s resume' )
				: t( 'resumeCapOther', '%1$s/%2$s resumes' );
			return template
				.replace( '%1$s', formatNumber( state.resumeCount ) )
				.replace( '%2$s', formatNumber( state.maxResumes ) );
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
		// `savedCompaniesCountSeed` + `savedResumesCountSeed` are bootstrapped
		// from PHP at render time so the sidebar badges show the correct
		// total BEFORE the user clicks the tab (the lazy fetch only kicks
		// in on tab open). Once the user has fetched, the live array length
		// is the source of truth.
		get savedCompaniesCount() {
			return state.savedCompanies.length || ( state.savedCompaniesCountSeed || 0 );
		},
		get savedResumesCount() {
			return state.savedResumes.length || ( state.savedResumesCountSeed || 0 );
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
		get hasResumes() {
			return ! state.loading && Array.isArray( state.resumes ) && state.resumes.length > 0;
		},
		get noResumes() {
			return ! state.loading && ! state.error && Array.isArray( state.resumes ) && state.resumes.length === 0;
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
		get showRecommendations() {
			return state.aiMatching && state.recommendations.length > 0;
		},
	},

	actions: {
		*init() {
			// Tab restoration priority: URL hash → sessionStorage → server default.
			// Hash wins so deep-links / shared URLs / browser back-forward land on
			// the intended tab regardless of any prior session activity.
			const hashTab = readHashTab();
			if ( hashTab ) {
				state.tab = hashTab;
			} else if ( state.tab === 'overview' ) {
				const saved = sessionStorage.getItem( 'wcb_candidate_tab' );
				if ( saved ) {
					state.tab = saved;
				}
			}
			writeHashTab( state.tab );

			// Browser back/forward and manual hash edits stay in sync.
			window.addEventListener( 'hashchange', () => {
				const next = readHashTab();
				if ( next && next !== state.tab ) {
					state.tab = next;
				}
			} );

			state.loading = true;
			state.error   = '';

			try {
				const response = yield wcbFetch(
					state.apiBase + '/candidates/' + String( state.candidateId ) + '/applications',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);

				if ( ! response.ok ) {
					state.error = t( 'errLoadApplications', 'Could not load your applications.' );
					return;
				}

				const data = yield response.json();
				// Envelope since 1.1.0; tolerate the legacy bare-array shape.
				state.applications = Array.isArray( data ) ? data : ( data?.applications ?? [] );
			} catch {
				state.error = t( 'errConnectionFull', 'Connection error. Please check your network and try again.' );
			} finally {
				state.loading = false;
			}

			// Prefetch bookmarks so the Overview panel can display recent saved jobs.
			try {
				const bmResponse = yield wcbFetch(
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
				const alertsRes = yield wcbFetch(
					state.apiBase + '/alerts',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);
				if ( alertsRes.ok ) {
					const raw = yield alertsRes.json();
					state.alerts = raw.map( ( a ) => ( {
						id:          a.id,
						label:       a.search_query || t( 'alertLabelAllJobs', 'All jobs' ),
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

			// Deep-link prefetch: when the URL hash lands the user directly on
			// Saved Companies or Saved Resumes, switchToSaved* never fires from
			// a click and the panel sits empty against a populated sidebar
			// badge. Pull the data inline so the panel paints correctly on
			// first navigation.
			if ( state.tab === 'saved-companies' && state.savedCompanies.length === 0 ) {
				yield actions.switchToSavedCompanies();
			}
			if ( state.tab === 'saved-resumes' && state.savedResumes.length === 0 ) {
				yield actions.switchToSavedResumes();
			}

			if ( state.bellEnabled ) {
				yield actions.fetchBellNotifications();
			}

			if ( state.aiMatching ) {
				yield actions.loadRecommendations();
			}
		},

		toggleNav() {
			state.navOpen = ! state.navOpen;
		},

		switchToOverview() {
			state.tab     = 'overview';
			state.navOpen = false;
			sessionStorage.removeItem( 'wcb_candidate_tab' );
			writeHashTab( 'overview' );
		},

		switchToApplications() {
			state.tab     = 'applications';
			state.error   = '';
			state.navOpen = false;
			sessionStorage.setItem( 'wcb_candidate_tab', 'applications' );
			writeHashTab( 'applications' );
		},

		*switchToBookmarks() {
			state.tab     = 'bookmarks';
			state.error   = '';
			state.navOpen = false;
			sessionStorage.setItem( 'wcb_candidate_tab', 'bookmarks' );
			writeHashTab( 'bookmarks' );

			if ( state.bookmarks.length ) {
				return;
			}

			state.loading = true;

			try {
				const response = yield wcbFetch(
					state.apiBase + '/candidates/' + String( state.candidateId ) + '/bookmarks',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);

				if ( ! response.ok ) {
					state.error = t( 'errLoadBookmarks', 'Could not load saved jobs.' );
					return;
				}

				const data = yield response.json();
				state.bookmarks = Array.isArray( data ) ? data : ( data?.bookmarks ?? [] );
			} catch {
				state.error = t( 'errConnectionFull', 'Connection error. Please check your network and try again.' );
			} finally {
				state.loading = false;
			}
		},

		switchToResumeBuilder() {
			state.tab     = 'resume-builder';
			state.navOpen = false;
			sessionStorage.setItem( 'wcb_candidate_tab', 'resume-builder' );
		},

		/**
		 * Saved Companies tab. Lazy-fetches once per session, mirroring
		 * the `switchToBookmarks` shape.
		 */
		*switchToSavedCompanies() {
			state.tab     = 'saved-companies';
			state.navOpen = false;
			state.savedCompaniesError = '';
			sessionStorage.setItem( 'wcb_candidate_tab', 'saved-companies' );
			writeHashTab( 'saved-companies' );

			if ( state.savedCompanies.length ) {
				return;
			}
			state.savedCompaniesLoading = true;
			try {
				const response = yield wcbFetch(
					state.apiBase + '/candidates/' + String( state.candidateId ) + '/saved-companies',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);
				if ( ! response.ok ) {
					state.savedCompaniesError = t( 'errLoadSavedCompanies', 'Could not load saved companies.' );
					return;
				}
				const data = yield response.json();
				state.savedCompanies = Array.isArray( data ) ? data : ( data?.items ?? [] );
			} catch {
				state.savedCompaniesError = t( 'errConnectionFull', 'Connection error. Please check your network and try again.' );
			} finally {
				state.savedCompaniesLoading = false;
			}
		},

		/**
		 * Saved Resumes tab. Same shape as Saved Companies; Pro-only data
		 * source but the endpoint exists in Free and returns an empty list
		 * when the wcb_resume CPT isn't registered.
		 */
		*switchToSavedResumes() {
			state.tab     = 'saved-resumes';
			state.navOpen = false;
			state.savedResumesError = '';
			sessionStorage.setItem( 'wcb_candidate_tab', 'saved-resumes' );
			writeHashTab( 'saved-resumes' );

			if ( state.savedResumes.length ) {
				return;
			}
			state.savedResumesLoading = true;
			try {
				const response = yield wcbFetch(
					state.apiBase + '/candidates/' + String( state.candidateId ) + '/saved-resumes',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);
				if ( ! response.ok ) {
					state.savedResumesError = t( 'errLoadSavedResumes', 'Could not load saved resumes.' );
					return;
				}
				const data = yield response.json();
				state.savedResumes = Array.isArray( data ) ? data : ( data?.items ?? [] );
			} catch {
				state.savedResumesError = t( 'errConnectionFull', 'Connection error. Please check your network and try again.' );
			} finally {
				state.savedResumesLoading = false;
			}
		},

		*loadRecommendations() {
			if ( ! state.aiMatching || ! state.currentUserId ) {
				return;
			}
			state.recsLoading = true;
			try {
				const response = yield wcbFetch(
					state.apiBase + '/candidates/' + String( state.currentUserId ) + '/matches',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);
				if ( ! response.ok ) {
					return;
				}
				const data = yield response.json();
				state.recommendations = ( Array.isArray( data ) ? data : [] ).map( ( r ) => ( {
					...r,
					score_label: t( 'scoreFormat', '%1$s%' ).replace( '%1$s', formatNumber( r.score_pct ) ),
				} ) );
			} catch {
				// Silent — recommendations are non-critical.
			} finally {
				state.recsLoading = false;
			}
		},

		/**
		 * Remove a company bookmark. Mirrors `unbookmark` for jobs.
		 */
		*unbookmarkCompany() {
			const ctx = getContext();
			if ( ! ctx?.company ) {
				return;
			}
			const companyId = ctx.company.id;
			try {
				const response = yield wcbFetch(
					state.apiBase + '/companies/' + String( companyId ) + '/bookmark',
					{
						method:  'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce':   state.nonce,
						},
					}
				);
				if ( response.ok ) {
					state.savedCompanies = state.savedCompanies.filter( function( c ) {
						return c.id !== companyId;
					} );
				}
			} catch {
				// Silent fail - user can retry.
			}
		},

		/**
		 * Remove a resume bookmark. Mirrors `unbookmark` for jobs.
		 */
		*unbookmarkResume() {
			const ctx = getContext();
			if ( ! ctx?.resume ) {
				return;
			}
			const resumeId = ctx.resume.id;
			try {
				const response = yield wcbFetch(
					state.apiBase + '/resumes/' + String( resumeId ) + '/bookmark',
					{
						method:  'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce':   state.nonce,
						},
					}
				);
				if ( response.ok ) {
					state.savedResumes = state.savedResumes.filter( function( r ) {
						return r.id !== resumeId;
					} );
				}
			} catch {
				// Silent fail - user can retry.
			}
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

		*switchToNotifications() {
			state.tab     = 'notifications';
			state.navOpen = false;
			sessionStorage.setItem( 'wcb_candidate_tab', 'notifications' );
			if ( state.bellEnabled && state.bellNotifications.length === 0 ) {
				yield actions.fetchBellNotifications();
			}
		},

		updateProfileBio( event ) {
			state.profileBio   = event.target.value;
			state.profileSaved = false;
		},

		updateProfilePhone( event ) {
			state.profilePhone = event.target.value;
			state.profileSaved = false;
		},

		updateProfileLocation( event ) {
			state.profileLocation = event.target.value;
			state.profileSaved    = false;
		},

		updateCustomField( event ) {
			const key = event.target.getAttribute( 'data-wcb-field' );
			if ( ! key ) {
				return;
			}
			const target = event.target;
			const value  = ( target.type === 'checkbox' )
				? target.checked
				: target.value;
			state.customFields = { ...state.customFields, [ key ]: value };
		},

		*saveProfile() {
			state.profileSaving = true;
			state.profileSaved  = false;
			state.error         = '';
			try {
				const response = yield wcbFetch(
					state.apiBase + '/candidates/' + String( state.candidateId ),
					{
						method: 'PUT',
						headers: {
							'X-WP-Nonce':   state.nonce,
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( {
						bio:           state.profileBio,
						resume_data:   {
							phone:    state.profilePhone || '',
							location: state.profileLocation || '',
						},
						custom_fields: state.customFields || {},
					} ),
					}
				);
				if ( response.ok ) {
					state.profileSaved = true;
					setTimeout( () => {
						state.profileSaved = false;
					}, 3000 );
				} else {
					state.error = t( 'errSaveProfile', 'Could not save profile. Please try again.' );
				}
			} catch {
				state.error = t( 'errConnectionRetry', 'Connection error. Please try again.' );
			} finally {
				state.profileSaving = false;
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
					state.profileEmail   = data.email;
					state.accountMsgType = 'success';
					state.accountMsg     = t( 'accountUpdated', 'Account updated.' );
				} else {
					state.accountMsgType = 'error';
					state.accountMsg     = data?.message || t( 'errSaveAccount', 'Could not save your account.' );
				}
			} catch {
				state.accountMsgType = 'error';
				state.accountMsg     = t( 'errConnectionRetry', 'Connection error. Please try again.' );
			} finally {
				state.accountSaving = false;
			}
		},

		*changePassword() {
			state.pwMsg = '';
			if ( ! state.curPassword || ! state.newPassword ) {
				state.pwMsgType = 'error';
				state.pwMsg     = t( 'errPwRequired', 'Enter your current and new password.' );
				return;
			}
			if ( state.newPassword !== state.confPassword ) {
				state.pwMsgType = 'error';
				state.pwMsg     = t( 'errPwMismatch', 'New password and confirmation do not match.' );
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
					state.pwMsg     = data?.message || t( 'errPwUpdate', 'Could not update your password.' );
				}
			} catch {
				state.pwMsgType = 'error';
				state.pwMsg     = t( 'errConnectionRetry', 'Connection error. Please try again.' );
			} finally {
				state.pwSaving = false;
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
				const response = yield wcbFetch(
					state.apiBase + '/alerts',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);

				if ( ! response.ok ) {
					state.error = t( 'errLoadAlerts', 'Could not load your alerts.' );
					return;
				}

				const raw = yield response.json();
				state.alerts = raw.map( ( a ) => ( {
					id:          a.id,
					label:       a.search_query || t( 'alertLabelAllJobs', 'All jobs' ),
					frequency:   a.frequency,
					filterPills: buildFilterPills( a.filters ),
				} ) );
			} catch {
				state.error = t( 'errConnectionShort', 'Connection error.' );
			} finally {
				state.alertsLoading = false;
			}
		},

		*deleteAlert() {
			const ctx = getContext();
			const alertId = ctx.alert.id;

			try {
				const response = yield wcbFetch(
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
				yield wcbFetch(
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
				const response = yield wcbFetch(
					state.apiBase + '/candidates/' + String( state.candidateId ) + '/resumes',
					{ headers: { 'X-WP-Nonce': state.nonce } }
				);

				if ( ! response.ok ) {
					state.error = t( 'errLoadResumes', 'Could not load your resumes.' );
					return;
				}

				state.resumes = yield response.json();
			} catch {
				state.error = t( 'errConnectionFull', 'Connection error. Please check your network and try again.' );
			} finally {
				state.loading = false;
			}
		},

		*unbookmark() {
			const ctx      = getContext();
			const bookmark = ctx.bookmark;

			try {
				const response = yield wcbFetch(
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
					state.error = t( 'errRemoveBookmark', 'Could not remove saved job. Please try again.' );
					return;
				}

				state.bookmarks = state.bookmarks.filter( function( b ) {
					return b.id !== bookmark.id;
				} );
			} catch {
				state.error = t( 'errConnectionFull', 'Connection error. Please check your network and try again.' );
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

		*reuploadResumePdf( event ) {
			const ctx  = getContext();
			const file = event.target.files && event.target.files[ 0 ];
			if ( ! ctx.resume || ! file ) {
				return;
			}
			const formData = new FormData();
			formData.append( 'resume_file', file );

			state.loading = true;
			state.error   = '';
			try {
				const response = yield wcbFetch(
					state.apiBase + '/resumes/' + String( ctx.resume.id ) + '/pdf',
					{
						method:  'POST',
						headers: { 'X-WP-Nonce': state.nonce },
						body:    formData,
					}
				);
				if ( ! response.ok ) {
					// Re-upload failed — report the upload, not a phantom list load.
					state.error = t( 'errUploadRetry', 'Failed to upload resume file. Please try again.' );
					return;
				}
				const data    = yield response.json();
				ctx.resume.pdfUrl       = data.pdf_url || ctx.resume.pdfUrl;
				ctx.resume.attachmentId = data.attachment_id || ctx.resume.attachmentId;
				ctx.resume.isPdf        = !! ctx.resume.pdfUrl;
			} catch {
				state.error = t( 'errConnectionShort', 'Connection error.' );
			} finally {
				state.loading           = false;
				event.target.value      = '';
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
				const response = yield wcbFetch(
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
					state.error = t( 'errCreateResume', 'Could not create resume. Please try again.' );
					return;
				}

				const resume = yield response.json();
				state.resumes = [ resume, ...state.resumes ];
				state.resumeCount = state.resumeCount + 1;

				if ( state.resumeBuilderEmbedded ) {
					window.location.href = state.dashboardUrl + '?resume_id=' + String( resume.id );
				}
			} catch {
				state.error = t( 'errConnectionFull', 'Connection error. Please check your network and try again.' );
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

				const uploadResp = yield wcbFetch(
					state.apiBase + '/candidates/resume-upload',
					{
						method: 'POST',
						headers: { 'X-WP-Nonce': state.nonce },
						body: formData,
					}
				);
				const uploadData = yield uploadResp.json();

				if ( ! uploadResp.ok ) {
					state.error = uploadData.message || t( 'errUploadFailed', 'Upload failed.' );
					return;
				}

				const attachmentId = uploadData.attachment_id;
				if ( ! attachmentId ) {
					state.error = t( 'errUploadNoAttachment', 'Upload failed: no attachment returned.' );
					return;
				}

				// Wrap the uploaded file in a wcb_resume post so it shows up
				// in the candidate's resume list. Without this step the file
				// uploads silently and the user has nothing to click on.
				const title = file.name.replace( /\.[^.]+$/, '' ) || t( 'uploadedCvTitle', 'Uploaded CV' );
				const createResp = yield wcbFetch(
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
					state.error = t( 'errCreateResume', 'Could not create resume. Please try again.' );
					return;
				}

				const resume     = yield createResp.json();
				state.resumes    = [ resume, ...state.resumes ];
				state.resumeCount = state.resumeCount + 1;
			} catch {
				state.error = t( 'errUploadRetry', 'Failed to upload resume file. Please try again.' );
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
				const response = yield wcbFetch(
					state.apiBase + '/resumes/' + String( resume.id ),
					{
						method: 'DELETE',
						headers: { 'X-WP-Nonce': state.nonce },
					}
				);

				if ( ! response.ok ) {
					ctx.confirmingDelete = false;
					state.error = t( 'errDeleteResume', 'Could not delete resume. Please try again.' );
					return;
				}

				state.resumes = state.resumes.filter( function( r ) {
					return r.id !== resume.id;
				} );
				state.resumeCount = Math.max( 0, state.resumeCount - 1 );
			} catch {
				ctx.confirmingDelete = false;
				state.error = t( 'errConnectionFull', 'Connection error. Please check your network and try again.' );
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

		*withdrawApplication() {
			const ctx         = getContext();
			const application = ctx.application;

			try {
				yield window.wcbConfirm( {
					title:       t( 'confirmWithdrawTitle', 'Withdraw application?' ),
					message:     t( 'confirmWithdrawMsg', 'Are you sure you want to withdraw this application? This cannot be undone.' ),
					confirmText: t( 'withdraw', 'Withdraw' ),
					destructive: true,
				} );
			} catch ( cancelled ) {
				return;
			}

			try {
				const response = yield wcbFetch(
					state.apiBase + '/applications/' + String( application.id ),
					{
						method:  'DELETE',
						headers: { 'X-WP-Nonce': state.nonce },
					}
				);

				if ( ! response.ok ) {
					state.error = t( 'errWithdraw', 'Could not withdraw application. Please try again.' );
					return;
				}

				state.applications = state.applications.filter( function( a ) {
					return a.id !== application.id;
				} );
			} catch {
				state.error = t( 'errConnectionFull', 'Connection error. Please check your network and try again.' );
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
					title:       t( 'confirmEraseTitle', 'Delete your account?' ),
					message:     t( 'confirmEraseMsg', 'We\'ll send a confirmation email to your registered address. After you click the link in the email, the site administrator will permanently delete your applications, resumes, and account. This cannot be undone.' ),
					confirmText: t( 'confirmEraseConfirm', 'Send confirmation email' ),
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
				const response = yield wcbFetch(
					state.apiBase + '/candidates/me/privacy/' + String( action ),
					{
						method:  'POST',
						headers: { 'X-WP-Nonce': state.nonce },
					}
				);
				if ( ! response.ok ) {
					state.privacyError = t( 'errPrivacy', 'Could not submit your privacy request. Please try again or contact support.' );
					return;
				}
				if ( 'export' === action ) {
					state.privacyExportRequested = true;
				} else {
					state.privacyEraseRequested = true;
				}
			} catch {
				state.privacyError = t( 'errConnectionFull', 'Connection error. Please check your network and try again.' );
			} finally {
				state.privacyBusy = false;
			}
		},
	},
} );
