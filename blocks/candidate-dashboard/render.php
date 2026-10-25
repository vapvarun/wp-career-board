<?php
/**
 * Block render: wp-career-board/candidate-dashboard — sidebar layout candidate interface.
 *
 * WordPress injects:
 *   $attributes  (array)    Block attributes defined in block.json.
 *   $content     (string)   Inner block content (empty for this block).
 *   $block       (WP_Block) Block instance object.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	$wcb_gate = array(
		'icon'    => 'user',
		'title'   => __( 'Your candidate dashboard', 'wp-career-board' ),
		'signup'  => \WCB\Admin\Pages::get_id( 'employer_registration_page' ),
		'message' => __( 'Sign in to track your applications, resumes and job alerts.', 'wp-career-board' ),
		'actions' => array(
			array(
				'label'   => __( 'Sign in', 'wp-career-board' ),
				'url'     => wp_login_url( (string) get_permalink() ),
				'primary' => true,
			),
		),
	);
	require WCB_DIR . 'templates/parts/dashboard-gate.php';
	return;
}

$wcb_can_access_candidate_dashboard = wp_is_ability_granted( 'wcb/access-candidate-dashboard' );

if ( ! $wcb_can_access_candidate_dashboard ) {
	$wcb_gate = array(
		'icon'    => 'user-x',
		'title'   => __( 'Your candidate dashboard', 'wp-career-board' ),
		'message' => __( 'You do not have permission to view this dashboard.', 'wp-career-board' ),
	);
	require WCB_DIR . 'templates/parts/dashboard-gate.php';
	return;
}

// Shared confirm-modal — used by withdrawApplication() and deleteResume() in view.js.
wp_enqueue_style( 'wcb-confirm-modal' );
wp_enqueue_script( 'wcb-confirm-modal' );

$wcb_candidate_id = get_current_user_id();
$wcb_current_user = wp_get_current_user();
$wcb_display_name = $wcb_current_user->display_name;
// The photo from the candidate's most recently edited resume (any status: it
// is their own dashboard). No photo keeps the initials, never a grey Gravatar.
$wcb_own_photo     = get_posts(
	array(
		'post_type'      => 'wcb_resume',
		'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
		'author'         => $wcb_current_user->ID,
		'posts_per_page' => 1,
		'orderby'        => 'modified',
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_wcb_resume_photo_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_compare'   => '>',
		'meta_value'     => '0',                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	)
);
$wcb_own_photo_url = $wcb_own_photo ? (string) wp_get_attachment_image_url( (int) get_post_meta( (int) $wcb_own_photo[0], '_wcb_resume_photo_id', true ), array( 96, 96 ) ) : '';

$wcb_jobs_page_id   = \WCB\Admin\Pages::get_id( 'jobs_archive_page' );
$wcb_jobs_permalink = $wcb_jobs_page_id > 0 ? get_permalink( $wcb_jobs_page_id ) : false;
$wcb_jobs_url       = ( false !== $wcb_jobs_permalink && '' !== $wcb_jobs_permalink )
	? (string) $wcb_jobs_permalink
	: home_url( '/' );

// Pro signals its notifications module through the wcb_module_renders slot; when
// present, the dashboard shows a Notifications item in the ACCOUNT nav whose panel
// renders that markup (trusted plugin Interactivity HTML — emitted as-is below,
// since wp_kses_post would strip the <template>/data-wp-each loop).
$wcb_module_renders = (array) apply_filters( 'wcb_module_renders', array(), 'candidate-dashboard' );
$wcb_bell_enabled   = ! empty( $wcb_module_renders['notifications_bell'] );

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only param, no state mutation.
$wcb_resume_embed_id         = absint( wp_unslash( $_GET['resume_id'] ?? '0' ) );
$wcb_resume_builder_embedded = WP_Block_Type_Registry::get_instance()->is_registered( 'wcb/resume-builder' );
$wcb_dashboard_url           = (string) get_permalink();

// Public-resume permalink for the sidebar shortcut. Mirrors Employer's
// "Public Page ↗" link so a candidate can preview the version of their
// resume an employer sees. We pick the most recently updated published
// resume the candidate owns; if none exists, the link stays hidden.
$wcb_public_resume_url = '';
if ( post_type_exists( 'wcb_resume' ) ) {
	$wcb_public_resumes = get_posts(
		array(
			'post_type'      => 'wcb_resume',
			'author'         => $wcb_candidate_id,
			'post_status'    => 'publish',
			'meta_key'       => '_wcb_resume_public', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	if ( ! empty( $wcb_public_resumes ) ) {
		$wcb_public_resume_url = (string) get_permalink( (int) $wcb_public_resumes[0] );
	}
}

/**
 * Filter extra state keys for the candidate dashboard resumes tab.
 *
 * Pro uses this to inject maxResumes and resumeCount for cap enforcement.
 *
 * @since 1.0.0
 * @param array<string,mixed> $state   Default state with maxResumes=0, resumeCount=0.
 * @param int                 $user_id Current candidate user ID.
 */
// Count what the Saved Jobs list shows: a bookmark whose job was deleted is not listed (see
// CandidatesEndpoint::get_bookmarks), so the overview must not count it either.
$wcb_saved_jobs_count = count(
	array_filter(
		array_map( 'intval', (array) get_user_meta( $wcb_candidate_id, '_wcb_bookmark', false ) ),
		static fn ( int $wcb_bookmark_id ): bool => 'wcb_job' === get_post_type( $wcb_bookmark_id )
	)
);

$wcb_resumes_state = (array) apply_filters(
	'wcb_candidate_resumes_state',
	array(
		'maxResumes'  => 0,
		'resumeCount' => 0,
	),
	$wcb_candidate_id
);

// The welcome card is decided here, from the same counts the overview shows,
// so it paints in place instead of popping in after the client fetches.
$wcb_has_application = (bool) get_posts(
	array(
		'post_type'      => 'wcb_application',
		'post_status'    => 'any',
		'fields'         => 'ids',
		'posts_per_page' => 1,
		'no_found_rows'  => true,
		'meta_key'       => '_wcb_candidate_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'     => (string) $wcb_candidate_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	)
);
$wcb_first_time      = ! $wcb_has_application && 0 === $wcb_saved_jobs_count && 0 === (int) ( $wcb_resumes_state['resumeCount'] ?? 0 );

/*
 * Money format strings for the saved-search filter pills. The alert's
 * salary bounds arrive from /wcb/v1/alerts as raw integers, so the label has
 * to be assembled client-side; these numbered format strings let a translator
 * move the currency symbol after the amount (fr_FR/de_DE/sv_SE) and swap the
 * range separator. Only the four keys view.js actually reads are seeded --
 * SalaryFormat::js_strings() also carries the abbreviation/period keys, which
 * this block has no reader for.
 */
$wcb_money_i18n = array_intersect_key(
	\WCB\Core\SalaryFormat::js_strings(),
	array_flip(
		array(
			'moneyFormat',
			'salaryRange',
			'salaryOpenMin',
			'salaryUpTo',
		)
	)
);

/*
 * view.js is registered as a `viewScriptModule`. Script modules cannot load
 * JED translation files, so every user-facing string the store renders is
 * seeded here under the `i18n` key and read in JS through the `t()` helper.
 * Every key below has exactly one `t( 'key', 'English fallback' )` reader.
 */
wp_interactivity_state(
	'wcb-candidate-dashboard',
	array_merge(
		array(
			// Site locale as a BCP-47 tag, at state ROOT (sibling of `i18n`) so
			// view.js can hand it to Intl.NumberFormat. Without it,
			// Number#toLocaleString() formats against the BROWSER locale and a
			// de_DE site viewed from an en-US browser renders "1,000" for "1.000".
			'locale'                  => \WCB\Core\SalaryFormat::locale(),
			'i18n'                    => array_merge(
				$wcb_money_i18n,
				array(
					'tabOverview'            => __( 'Overview', 'wp-career-board' ),
					'tabApplications'        => __( 'My Applications', 'wp-career-board' ),
					'tabBookmarks'           => __( 'Saved jobs', 'wp-career-board' ),
					'tabSavedCompanies'      => __( 'Saved companies', 'wp-career-board' ),
					'tabSavedResumes'        => __( 'Saved resumes', 'wp-career-board' ),
					'tabResumes'             => __( 'My Resumes', 'wp-career-board' ),
					'tabAlerts'              => __( 'Job alerts', 'wp-career-board' ),
					'tabResumeBuilder'       => __( 'Edit Resume', 'wp-career-board' ),
					'tabDashboard'           => __( 'Dashboard', 'wp-career-board' ),
					'tabProfile'             => __( 'Profile', 'wp-career-board' ),
					'tabSettings'            => __( 'Settings', 'wp-career-board' ),
					'tabNotifications'       => __( 'Notifications', 'wp-career-board' ),
					/* translators: 1: number of resumes the candidate has created. 2: maximum number of resumes allowed. Label form, so one string fits every count. */
					'resumeCap'              => __( 'Resumes: %1$s of %2$s', 'wp-career-board' ),
					/* translators: %1$s: match score from 0 to 100, already localised. Move the percent sign, or add a space before it, as your locale requires. */
					'scoreFormat'            => __( '%1$s%', 'wp-career-board' ),
					'filterRemote'           => __( 'Remote', 'wp-career-board' ),
					/* translators: currency symbol shown on salary pills when the site has no configured currency symbol. */
					'currencySymbolFallback' => __( '$', 'wp-career-board' ),
					'alertLabelAllJobs'      => __( 'All jobs', 'wp-career-board' ),
					'uploadedCvTitle'        => __( 'Uploaded CV', 'wp-career-board' ),
					'accountUpdated'         => __( 'Account updated.', 'wp-career-board' ),
					'pwUpdated'              => __( 'Password updated.', 'wp-career-board' ),
					'errLoadApplications'    => __( 'Could not load your applications.', 'wp-career-board' ),
					'errLoadBookmarks'       => __( 'Could not load saved jobs.', 'wp-career-board' ),
					'errLoadSavedCompanies'  => __( 'Could not load saved companies.', 'wp-career-board' ),
					'errLoadSavedResumes'    => __( 'Could not load saved resumes.', 'wp-career-board' ),
					'errLoadResumes'         => __( 'Could not load your resumes.', 'wp-career-board' ),
					'errLoadAlerts'          => __( 'Could not load your alerts.', 'wp-career-board' ),
					'errRemoveBookmark'      => __( 'Could not remove saved job. Please try again.', 'wp-career-board' ),
					'errCreateResume'        => __( 'Could not create resume. Please try again.', 'wp-career-board' ),
					'errDeleteResume'        => __( 'Could not delete resume. Please try again.', 'wp-career-board' ),
					'errSaveProfile'         => __( 'Could not save profile. Please try again.', 'wp-career-board' ),
					'errSaveAccount'         => __( 'Could not save your account.', 'wp-career-board' ),
					'errPwRequired'          => __( 'Enter your current and new password.', 'wp-career-board' ),
					'errPwMismatch'          => __( 'New password and confirmation do not match.', 'wp-career-board' ),
					'errPwUpdate'            => __( 'Could not update your password.', 'wp-career-board' ),
					'errUploadFailed'        => __( 'Upload failed.', 'wp-career-board' ),
					'errUploadNoAttachment'  => __( 'Upload failed: no attachment returned.', 'wp-career-board' ),
					'errUploadRetry'         => __( 'Failed to upload resume file. Please try again.', 'wp-career-board' ),
					'errConnectionFull'      => __( 'Connection error. Please check your network and try again.', 'wp-career-board' ),
					'errConnectionRetry'     => __( 'Connection error. Please try again.', 'wp-career-board' ),
					'errConnectionShort'     => __( 'Connection error.', 'wp-career-board' ),
					'confirmWithdrawTitle'   => __( 'Withdraw application?', 'wp-career-board' ),
					'confirmWithdrawMsg'     => __( 'Are you sure you want to withdraw this application? This cannot be undone.', 'wp-career-board' ),
					'withdraw'               => __( 'Withdraw', 'wp-career-board' ),
					/* translators: %s: name of the item the menu acts on (a job, resume or company). */
					'moreActionsFor'         => __( 'More actions for %s', 'wp-career-board' ),
					/* translators: %s: job title. */
					'withdrawFor'            => __( 'Withdraw application for %s', 'wp-career-board' ),
					/* translators: %s: job title. */
					'removeFor'              => __( 'Remove application for %s', 'wp-career-board' ),
					'confirmClearAllTitle'   => __( 'Clear all notifications?', 'wp-career-board' ),
					'confirmClearAllMsg'     => __( 'This permanently removes all of your notifications. This cannot be undone.', 'wp-career-board' ),
					'clearAll'               => __( 'Clear all', 'wp-career-board' ),
					'errWithdraw'            => __( 'Could not withdraw application. Please try again.', 'wp-career-board' ),
					'confirmEraseTitle'      => __( 'Delete your account?', 'wp-career-board' ),
					'confirmEraseMsg'        => __( 'We\'ll send a confirmation email to your registered address. After you click the link in the email, the site administrator will permanently delete your applications, resumes, and account. This cannot be undone.', 'wp-career-board' ),
					'confirmEraseConfirm'    => __( 'Send confirmation email', 'wp-career-board' ),
				)
			),
			'tab'                     => $wcb_resume_embed_id > 0 && $wcb_resume_builder_embedded ? 'resume-builder' : 'overview',
			'savedJobsCount'          => $wcb_saved_jobs_count,
			'firstTimeSeed'           => $wcb_first_time,
			// Server render of the welcome card; the view.js getter takes over on the client.
			'isFirstTime'             => $wcb_first_time,
			'appsLoaded'              => false,
			'applications'            => array(),
			'appsPage'                => 1,
			'appsHasMore'             => false,
			'appsLoadingMore'         => false,
			'appsCounts'              => array(
				'total'     => 0,
				'by_status' => new \stdClass(),
			),
			'bookmarks'               => array(),
			'bookmarksLoaded'         => false,
			'savedCompanies'          => array(),
			'savedCompaniesLoading'   => false,
			'savedCompaniesError'     => '',
			'savedCompaniesCountSeed' => (int) count( (array) get_user_meta( $wcb_candidate_id, '_wcb_company_bookmark', false ) ),
			'savedResumes'            => array(),
			'savedResumesLoading'     => false,
			'savedResumesError'       => '',
			// Gated on the PRO filter, not post_type_exists(): Free registers the
			// wcb_resume CPT itself, so that check always passed and the tab
			// rendered on Free-only sites. Its unsave button POSTs to
			// /resumes/{id}/bookmark, which exists only in Pro, so the request
			// 404'd into a silent catch and the member's click did nothing.
			'savedResumesCountSeed'   => apply_filters( 'wcb_pro_resumes_enabled', false )
				? (int) count( (array) get_user_meta( $wcb_candidate_id, '_wcb_resume_bookmark', false ) )
				: 0,
			'resumes'                 => array(),
			'resumesLoaded'           => false,
			'loading'                 => false,
			'error'                   => '',
			'apiBase'                 => untrailingslashit( rest_url( 'wcb/v1' ) ),
			'nonce'                   => wp_create_nonce( 'wp_rest' ),
			'candidateId'             => $wcb_candidate_id,
			'candidateName'           => $wcb_display_name,
			'currentUserId'           => get_current_user_id(),
			'aiMatching'              => (bool) apply_filters( 'wcb_ai_matching_available', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			'recommendations'         => array(),
			'recsLoading'             => false,
			// `resumesEnabled` is true when Pro's Resumes module is loaded
			// (regardless of whether the customer has dropped a wcb/resume-builder
			// block). On Free-only installs, the My Resumes tab and the
			// `/wcb/v1/candidates/{id}/resumes` REST call are both Pro-only —
			// gating here prevents the "Could not load your resumes." 402 error
			// surfaced in the candidate-dashboard audit on 2026-05-06.
			'resumesEnabled'          => (bool) apply_filters( 'wcb_pro_resumes_enabled', false ) || $wcb_resume_builder_embedded,
			'dashboardUrl'            => $wcb_dashboard_url,
			'resumeBuilderEmbedded'   => $wcb_resume_builder_embedded,
			'resumeEmbedId'           => $wcb_resume_embed_id,
			'showNewResumeForm'       => false,
			'newResumeTitle'          => '',
			'customFieldGroups'       => apply_filters( 'wcb_candidate_form_fields', array(), $wcb_candidate_id ),
			'customFields'            => (object) (
				$wcb_candidate_id > 0
					? \WCB\Core\FormCustomFields::load_values(
						(array) apply_filters( 'wcb_candidate_form_fields', array(), $wcb_candidate_id ),
						$wcb_candidate_id,
						'user_meta'
					)
					: array()
			),
			'profileBio'              => get_the_author_meta( 'description', $wcb_candidate_id ),
			'profileEmail'            => $wcb_current_user->user_email,
			// Account Settings panel — editable display name + email + password.
			'accountName'             => $wcb_current_user->display_name,
			'accountEmail'            => $wcb_current_user->user_email,
			'accountEmailSaved'       => $wcb_current_user->user_email,
			'accountEmailPassword'    => '',
			'curPassword'             => '',
			'newPassword'             => '',
			'confPassword'            => '',
			'accountMsg'              => '',
			'accountMsgType'          => '',
			'accountSaving'           => false,
			'pwMsg'                   => '',
			'pwMsgType'               => '',
			'pwSaving'                => false,
			// Phone + Location surfaced from the structured `_wcb_resume_data`
			// user meta so the candidate profile UI matches the contact info
			// rendered in resume-single + Pro's resume PDF download.
			'profilePhone'            => (string) (
				static function () use ( $wcb_candidate_id ): string {
					$wcb_rd = get_user_meta( $wcb_candidate_id, '_wcb_resume_data', true );
					return is_array( $wcb_rd ) ? (string) ( $wcb_rd['phone'] ?? '' ) : '';
				}
			)(),
			'profileLocation'         => (string) (
				static function () use ( $wcb_candidate_id ): string {
					$wcb_rd = get_user_meta( $wcb_candidate_id, '_wcb_resume_data', true );
					if ( is_array( $wcb_rd ) && ! empty( $wcb_rd['location'] ) ) {
						return (string) $wcb_rd['location'];
					}
					return (string) get_user_meta( $wcb_candidate_id, '_wcb_location', true );
				}
			)(),
			'profileSaving'           => false,
			'profileSaved'            => false,
			'passwordResetUrl'        => wp_lostpassword_url( $wcb_dashboard_url ),
			'bellNotifications'       => array(),
			'bellUnreadCount'         => 0,
			'bellLoading'             => false,
			'bellEnabled'             => $wcb_bell_enabled,
			'alerts'                  => array(),
			'alertsLoading'           => false,
			// Withdraw is gated by BOTH the site setting and the user ability —
			// admins disable the setting on sites that want apply-once-final
			// flows. Intended default is ON: a fresh install (no saved value)
			// lets candidates withdraw, mirroring the REST gate's default.
			'allowWithdraw'           => (
				static function (): bool {
					if ( ! \WCB\Admin\Settings::bool( 'allow_withdraw', true ) ) {
						return false;
					}
					return wp_is_ability_granted( 'wcb/withdraw-application' );
				}
			)(),
			// Site-default currency symbol — surfaced to the saved-search filter
			// pill labels in view.js so dashboards on INR / EUR / etc. don't show
			// a hardcoded $ on the alert summary.
			'currencySymbol'          => (
				static function (): string {
					$wcb_default = \WCB\Modules\Jobs\JobsMeta::default_currency();
					$wcb_catalog = \WCB\Admin\AdminSettings::get_currency_catalog();
					return isset( $wcb_catalog[ $wcb_default ]['symbol'] )
						? (string) $wcb_catalog[ $wcb_default ]['symbol']
						: '$';
				}
			)(),
		),
		$wcb_resumes_state
	)
);
// Same list and key as VALID_TABS / tabRouter in view.js.
\WCB\Core\DashboardTabs::print_guard(
	array( 'overview', 'applications', 'bookmarks', 'saved-companies', 'saved-resumes', 'resumes', 'resume-builder', 'alerts', 'profile', 'settings', 'notifications' ),
	'wcb_candidate_tab',
	$wcb_resume_embed_id > 0 && $wcb_resume_builder_embedded ? 'resume-builder' : 'overview'
);
?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-candidate-dashboard"
	data-wp-init="actions.init"
>

<div class="wcb-dashboard-shell">

	<!-- SIDEBAR -->
	<aside class="wcb-sidebar" data-wp-class--wcb-nav-open="state.navOpen">
		<button type="button" class="wcb-nav-toggle"
			aria-label="<?php esc_attr_e( 'Toggle navigation', 'wp-career-board' ); ?>"
			data-wp-on--click="actions.toggleNav"
			data-wp-bind--aria-expanded="state.navOpen">
			<span data-wp-text="state.activeTabLabel"><?php esc_html_e( 'Dashboard', 'wp-career-board' ); ?></span>
			<span class="wcb-nav-toggle-icon" aria-hidden="true"></span>
		</button>
		<button type="button" class="wcb-sidebar-identity" data-wp-on--click="actions.switchToProfile">
			<?php if ( $wcb_own_photo_url ) : ?>
				<span class="wcb-avatar wcb-avatar--round wcb-sidebar-avatar" aria-hidden="true"><img src="<?php echo esc_url( $wcb_own_photo_url ); ?>" alt=""></span>
			<?php else : ?>
				<span class="wcb-avatar wcb-avatar--round wcb-sidebar-avatar" data-wp-text="state.candidateInitials" aria-hidden="true"></span>
			<?php endif; ?>
			<span class="wcb-sidebar-identity__text">
				<span class="wcb-sidebar-identity__name" data-wp-text="state.candidateName"><?php echo esc_html( $wcb_display_name ); ?></span>
				<span class="wcb-sidebar-identity__role"><?php echo esc_html( \WCB\Core\Roles::current_member_label() ); ?></span>
			</span>
		</button>

		<?php // A nav of labelled groups: it was a tablist, which may hold only tabs, so group names were never announced. ?>
		<nav class="wcb-sidebar-nav" aria-label="<?php esc_attr_e( 'Candidate dashboard navigation', 'wp-career-board' ); ?>">
			<button type="button" class="wcb-nav-item" id="wcb-tab-overview" data-wp-bind--aria-current="state.isTabOverview" data-wp-class--wcb-nav-active="state.isTabOverview" data-wp-on--click="actions.switchToOverview">
				<?php esc_html_e( 'Overview', 'wp-career-board' ); ?>
			</button>

			<div class="wcb-nav-group" role="group" aria-labelledby="wcb-nav-g-activity">
			<span id="wcb-nav-g-activity" class="wcb-nav-section-label"><?php esc_html_e( 'My activity', 'wp-career-board' ); ?></span>
			<button type="button" class="wcb-nav-item" data-wp-bind--aria-current="state.isTabApplications" data-wp-class--wcb-nav-active="state.isTabApplications" data-wp-on--click="actions.switchToApplications" id="wcb-tab-applications">
				<?php esc_html_e( 'My Applications', 'wp-career-board' ); ?>
				<span class="wcb-nav-badge" data-wp-class--wcb-hidden="!state.appsCount" data-wp-text="state.appsCount"></span>
			</button>
			<button
				type="button"
				class="wcb-nav-item"
				id="wcb-tab-resumes"
				data-wp-bind--aria-current="state.isTabResumes"
				data-wp-class--wcb-nav-active="state.isTabResumes"
				data-wp-on--click="actions.switchToResumes"
				data-wp-bind--hidden="!state.resumesEnabled"
				<?php
				if ( ! $wcb_resume_builder_embedded ) :
					?>
					hidden
					<?php
				endif;
				?>
			><?php esc_html_e( 'My Resumes', 'wp-career-board' ); ?></button>
			<?php if ( '' !== $wcb_public_resume_url ) : ?>
			<a
				class="wcb-nav-item wcb-nav-item--link"
				href="<?php echo esc_url( $wcb_public_resume_url ); ?>"
				target="_blank"
				rel="noopener noreferrer"
			>
				<?php esc_html_e( 'Public Resume', 'wp-career-board' ); ?> &#8599;
			</a>
			<?php endif; ?>
			<?php if ( $wcb_resume_builder_embedded ) : ?>
			<button
				type="button"
				class="wcb-nav-item"
				data-wp-class--wcb-nav-active="state.isTabResumeBuilder"
				data-wp-on--click="actions.switchToResumeBuilder"
				<?php echo $wcb_resume_embed_id > 0 ? '' : 'hidden'; ?>
				data-wp-bind--hidden="!state.resumeEmbedId"
			><?php esc_html_e( 'Edit Resume', 'wp-career-board' ); ?></button>
			<?php endif; ?>
			<?php if ( apply_filters( 'wcb_pro_alerts_enabled', false ) ) : ?>
			<button type="button" class="wcb-nav-item" id="wcb-tab-alerts"
				data-wp-bind--aria-current="state.isTabAlerts"
				data-wp-class--wcb-nav-active="state.isTabAlerts"
				data-wp-on--click="actions.switchToAlerts">
				<?php esc_html_e( 'Job alerts', 'wp-career-board' ); ?>
				<span class="wcb-nav-badge" data-wp-class--wcb-hidden="!state.alertsCount" data-wp-text="state.alertsCount"></span>
			</button>
			<?php endif; ?>
			</div>

			<?php
			/*
			My Saves - jobs, companies, and resumes are bookmark-able
					by any logged-in user (REST gate is `is_user_logged_in()`).
					Grouping them here keeps the three lists separate so saved
					jobs don't mix with saved companies or saved candidate
					profiles. Saved Resumes hides itself when the wcb_resume
					CPT isn't registered (Free-only sites). */
			?>
			<div class="wcb-nav-group" role="group" aria-labelledby="wcb-nav-g-saved">
			<span id="wcb-nav-g-saved" class="wcb-nav-section-label"><?php esc_html_e( 'Saved', 'wp-career-board' ); ?></span>
			<button type="button" class="wcb-nav-item" data-wp-bind--aria-current="state.isTabBookmarks" data-wp-class--wcb-nav-active="state.isTabBookmarks" data-wp-on--click="actions.switchToBookmarks" id="wcb-tab-bookmarks">
				<?php echo esc_html_x( 'Jobs', 'saved items menu: saved jobs', 'wp-career-board' ); ?>
				<span class="wcb-nav-badge" data-wp-class--wcb-hidden="!state.bookmarksCount" data-wp-text="state.bookmarksCount"><?php echo esc_html( (string) $wcb_saved_jobs_count ); ?></span>
			</button>
			<button type="button" class="wcb-nav-item" data-wp-bind--aria-current="state.isTabSavedCompanies" data-wp-class--wcb-nav-active="state.isTabSavedCompanies" data-wp-on--click="actions.switchToSavedCompanies" id="wcb-tab-saved-companies">
				<?php echo esc_html_x( 'Companies', 'saved items menu: saved companies', 'wp-career-board' ); ?>
				<span class="wcb-nav-badge" data-wp-class--wcb-hidden="!state.savedCompaniesCount" data-wp-text="state.savedCompaniesCount"></span>
			</button>
			<?php if ( apply_filters( 'wcb_pro_resumes_enabled', false ) ) : ?>
			<button type="button" class="wcb-nav-item" data-wp-bind--aria-current="state.isTabSavedResumes" data-wp-class--wcb-nav-active="state.isTabSavedResumes" data-wp-on--click="actions.switchToSavedResumes" id="wcb-tab-saved-resumes">
				<?php echo esc_html_x( 'Resumes', 'saved items menu: saved resumes', 'wp-career-board' ); ?>
				<span class="wcb-nav-badge" data-wp-class--wcb-hidden="!state.savedResumesCount" data-wp-text="state.savedResumesCount"></span>
			</button>
			<?php endif; ?>
			</div>

			<?php if ( '' !== \WCB\Core\Roles::moderation_queue_url() ) : ?>
				<?php // Moderators land here; their queue is one click away (the admin bar hides it on phones). One item: a divider, not a heading (sidebar rule, 10400673345). ?>
			<div class="wcb-nav-group wcb-nav-group--rule" role="group" aria-labelledby="wcb-nav-g-moderation">
			<span id="wcb-nav-g-moderation" class="wcb-nav-section-label screen-reader-text"><?php esc_html_e( 'Moderation', 'wp-career-board' ); ?></span>
			<a class="wcb-nav-item wcb-nav-item--link" href="<?php echo esc_url( \WCB\Core\Roles::moderation_queue_url() ); ?>">
				<?php esc_html_e( 'Review jobs', 'wp-career-board' ); ?> &#8599;
			</a>
			</div>
			<?php endif; ?>

			<div class="wcb-nav-group" role="group" aria-labelledby="wcb-nav-g-account">
			<span id="wcb-nav-g-account" class="wcb-nav-section-label"><?php esc_html_e( 'Account', 'wp-career-board' ); ?></span>
			<button type="button" class="wcb-nav-item" id="wcb-tab-profile"
				data-wp-bind--aria-current="state.isTabProfile"
				data-wp-class--wcb-nav-active="state.isTabProfile"
				data-wp-on--click="actions.switchToProfile">
				<?php esc_html_e( 'Profile', 'wp-career-board' ); ?>
			</button>
			<button type="button" class="wcb-nav-item" id="wcb-tab-settings"
				data-wp-bind--aria-current="state.isTabSettings"
				data-wp-class--wcb-nav-active="state.isTabSettings"
				data-wp-on--click="actions.switchToSettings">
				<?php esc_html_e( 'Settings', 'wp-career-board' ); ?>
			</button>
			<?php if ( $wcb_bell_enabled ) : ?>
			<button type="button" class="wcb-nav-item" id="wcb-tab-notifications"
				data-wp-bind--aria-current="state.isTabNotifications"
				data-wp-class--wcb-nav-active="state.isTabNotifications"
				data-wp-on--click="actions.switchToNotifications">
				<?php esc_html_e( 'Notifications', 'wp-career-board' ); ?>
				<span class="wcb-nav-badge" data-wp-class--wcb-hidden="!state.bellUnreadCount" data-wp-text="state.bellUnreadCount"></span>
			</button>
			<?php endif; ?>
			</div>
		</nav>

		<a href="<?php echo esc_url( $wcb_jobs_url ); ?>" class="wcb-sidebar-cta wcb-btn wcb-btn--primary">
			<?php esc_html_e( 'Browse jobs', 'wp-career-board' ); ?> &#8599;
		</a>
	</aside>

	<!-- MAIN CONTENT -->
	<div class="wcb-main">

		<!-- VIEW: Overview -->
		<div class="wcb-view-panel" role="region" data-wp-class--wcb-view-active="state.isTabOverview">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'Overview', 'wp-career-board' ); ?></h1>
			</div>

			<!-- ── Welcome card — only when applications=0 AND saved=0 AND resumes=0 ── -->
			<div class="wcb-welcome-card" data-wp-class--wcb-shown="state.isFirstTime">
				<div class="wcb-welcome-card__header">
					<h2 class="wcb-welcome-card__title">
						<?php
						printf(
							/* translators: %s: candidate's first name */
							esc_html__( 'Welcome, %s', 'wp-career-board' ),
							esc_html( $wcb_current_user->first_name ? $wcb_current_user->first_name : $wcb_display_name )
						);
						?>
					</h2>
					<p class="wcb-welcome-card__subtitle"><?php esc_html_e( 'Here\'s how to get started:', 'wp-career-board' ); ?></p>
				</div>
				<div class="wcb-welcome-card__steps">
					<a href="<?php echo esc_url( $wcb_jobs_url ); ?>" class="wcb-welcome-step">
						<span class="wcb-welcome-step__num">1</span>
						<div class="wcb-welcome-step__body">
							<span class="wcb-welcome-step__title"><?php esc_html_e( 'Browse jobs', 'wp-career-board' ); ?></span>
							<span class="wcb-welcome-step__desc"><?php esc_html_e( 'Search and apply to roles that match your skills.', 'wp-career-board' ); ?></span>
						</div>
					</a>
					<?php if ( $wcb_resume_builder_embedded ) : ?>
					<button type="button" class="wcb-welcome-step" data-wp-on--click="actions.switchToResumes">
						<span class="wcb-welcome-step__num">2</span>
						<div class="wcb-welcome-step__body">
							<span class="wcb-welcome-step__title"><?php esc_html_e( 'Build your resume', 'wp-career-board' ); ?></span>
							<span class="wcb-welcome-step__desc"><?php esc_html_e( 'A complete profile gets noticed by employers faster.', 'wp-career-board' ); ?></span>
						</div>
					</button>
					<?php endif; ?>
					<?php if ( apply_filters( 'wcb_pro_alerts_enabled', false ) ) : ?>
					<button type="button" class="wcb-welcome-step" data-wp-on--click="actions.switchToAlerts">
						<span class="wcb-welcome-step__num">3</span>
						<div class="wcb-welcome-step__body">
							<span class="wcb-welcome-step__title"><?php esc_html_e( 'Set up job alerts', 'wp-career-board' ); ?></span>
							<span class="wcb-welcome-step__desc"><?php esc_html_e( 'Get notified when matching roles are posted.', 'wp-career-board' ); ?></span>
						</div>
					</button>
					<?php endif; ?>
				</div>
			</div>

			<div class="wcb-stats">
			<div class="wcb-stats-row">
				<div class="wcb-stat-card">
					<span class="wcb-stat-value" data-wp-text="state.appsCount">0</span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Applications', 'wp-career-board' ); ?></span>
				</div>
				<div class="wcb-stat-card">
					<span class="wcb-stat-value" data-wp-text="state.overviewShortlistedCount">0</span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Shortlisted', 'wp-career-board' ); ?></span>
				</div>
				<div class="wcb-stat-card">
					<span class="wcb-stat-value" data-wp-text="state.bookmarksCount"><?php echo esc_html( (string) $wcb_saved_jobs_count ); ?></span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Saved jobs', 'wp-career-board' ); ?></span>
				</div>
				<div class="wcb-stat-card">
					<span class="wcb-stat-value" data-wp-text="state.resumeCount"><?php echo esc_html( (string) ( $wcb_resumes_state['resumeCount'] ?? 0 ) ); ?></span>
					<span class="wcb-stat-label"><?php esc_html_e( 'My Resumes', 'wp-career-board' ); ?></span>
				</div>
				<?php if ( apply_filters( 'wcb_pro_alerts_enabled', false ) ) : ?>
				<div class="wcb-stat-card wcb-stat-card--link" data-wp-on--click="actions.switchToAlerts">
					<span class="wcb-stat-value" data-wp-text="state.alertsCount">0</span>
					<span class="wcb-stat-label"><?php esc_html_e( 'Job alerts', 'wp-career-board' ); ?></span>
				</div>
				<?php endif; ?>
			</div>
			</div>

			<div class="wcb-two-col">
				<div class="wcb-panel wcb-shown">
					<div class="wcb-panel-header">
						<span class="wcb-panel-title"><?php esc_html_e( 'Recent Applications', 'wp-career-board' ); ?></span>
						<button type="button" class="wcb-panel-link" data-wp-on--click="actions.switchToApplications"><?php esc_html_e( 'View all →', 'wp-career-board' ); ?></button>
					</div>
					<div data-wp-class--wcb-shown="state.hasRecentApps">
						<template data-wp-each--app="state.overviewRecentApps" data-wp-each-key="context.app.id">
							<div class="wcb-overview-app-row">
								<div class="wcb-app-info">
									<span class="wcb-app-name" data-wp-text="context.app.jobTitle"></span>
									<span class="wcb-app-job" data-wp-text="context.app.company"></span>
								</div>
								<span class="wcb-status-badge" role="status" data-wp-text="context.app.statusLabel" data-wp-bind--data-status="context.app.status"></span>
							</div>
						</template>
					</div>
					<div class="wcb-empty-state wcb-empty-state--compact wcb-empty-state--toggle" data-wp-class--wcb-shown="state.noRecentApps">
						<span class="wcb-empty-state__icon" aria-hidden="true"><?php echo \WCB\Core\Icon::svg( 'briefcase' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?></span>
						<p class="wcb-empty-state__title"><?php esc_html_e( 'No applications yet.', 'wp-career-board' ); ?></p>
						<p class="wcb-empty-state__body"><?php esc_html_e( 'Jobs you apply to show up here with their status.', 'wp-career-board' ); ?></p>
						<a class="wcb-btn wcb-btn--outline wcb-btn--sm" href="<?php echo esc_url( $wcb_jobs_url ); ?>"><?php esc_html_e( 'Browse jobs', 'wp-career-board' ); ?></a>
					</div>
				</div>

				<div class="wcb-panel wcb-shown">
					<div class="wcb-panel-header">
						<span class="wcb-panel-title"><?php esc_html_e( 'Saved jobs', 'wp-career-board' ); ?></span>
						<button type="button" class="wcb-panel-link" data-wp-on--click="actions.switchToBookmarks"><?php esc_html_e( 'View all →', 'wp-career-board' ); ?></button>
					</div>
					<div data-wp-class--wcb-shown="state.hasRecentSavedJobs">
						<template data-wp-each--saved="state.overviewRecentSavedJobs" data-wp-each-key="context.saved.id">
							<div class="wcb-overview-app-row">
								<div class="wcb-app-info">
									<a class="wcb-app-name" role="link" tabindex="0" aria-label="<?php esc_attr_e( 'Saved job', 'wp-career-board' ); ?>" data-wp-bind--href="context.saved.permalink" data-wp-bind--aria-label="context.saved.title" data-wp-text="context.saved.title" target="_blank" rel="noopener noreferrer"></a>
									<span class="wcb-app-job" data-wp-text="context.saved.company"></span>
								</div>
								<span class="wcb-status-badge" role="status" data-wp-text="context.saved.type"></span>
							</div>
						</template>
					</div>
					<div class="wcb-empty-state wcb-empty-state--compact wcb-empty-state--toggle" data-wp-class--wcb-shown="state.noRecentSavedJobs">
						<span class="wcb-empty-state__icon" aria-hidden="true"><?php echo \WCB\Core\Icon::svg( 'bookmark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?></span>
						<p class="wcb-empty-state__title"><?php esc_html_e( 'No saved jobs yet', 'wp-career-board' ); ?></p>
						<p class="wcb-empty-state__body"><?php esc_html_e( 'Save jobs while you browse to come back to them here.', 'wp-career-board' ); ?></p>
						<a class="wcb-btn wcb-btn--outline wcb-btn--sm" href="<?php echo esc_url( $wcb_jobs_url ); ?>"><?php esc_html_e( 'Browse jobs', 'wp-career-board' ); ?></a>
					</div>
				</div>
			</div>

			<section class="wcb-recommends" data-wp-class--wcb-shown="state.showRecommendations">
				<h2 class="wcb-section-title"><?php esc_html_e( 'Recommended for you', 'wp-career-board' ); ?></h2>
				<div class="wcb-recommends-grid">
					<template data-wp-each--rec="state.recommendations" data-wp-each-key="context.rec.job_id">
						<a class="wcb-rec-card" data-wp-bind--href="context.rec.url">
							<span class="wcb-rec-score" data-wp-text="context.rec.score_label"></span>
							<span class="wcb-rec-title" data-wp-text="context.rec.title"></span>
							<span class="wcb-rec-company" data-wp-text="context.rec.company"></span>
							<span class="wcb-rec-location" data-wp-text="context.rec.location"></span>
						</a>
					</template>
				</div>
			</section>
		</div>

		<!-- VIEW: My Applications -->
		<div class="wcb-view-panel" role="region" aria-labelledby="wcb-tab-applications" data-wp-class--wcb-view-active="state.isTabApplications">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'My Applications', 'wp-career-board' ); ?></h1>
			</div>

			<div class="wcb-cd-loading" role="status" data-wp-class--wcb-shown="state.loading">
				<span class="wcb-cd-spinner" aria-hidden="true"></span>
				<?php esc_html_e( 'Loading…', 'wp-career-board' ); ?>
			</div>
			<p class="wcb-cd-error" role="alert" data-wp-bind--hidden="!state.error" data-wp-text="state.error"></p>

			<div class="wcb-panel" aria-live="polite" data-wp-class--wcb-shown="state.hasApplications" data-wp-on-document--click="actions.closeRowMenusOutside">
				<template data-wp-each--application="state.applications" data-wp-each-key="context.application.id">
					<div class="wcb-cd-app-row">
						<div class="wcb-cd-app-main">
							<h3 class="wcb-cd-app-title">
								<a
									data-wp-bind--href="context.application.jobPermalink"
									data-wp-text="context.application.jobTitle"
									target="_blank"
									rel="noopener noreferrer"
								></a>
							</h3>
							<span class="wcb-cd-app-company" data-wp-text="context.application.company"></span>
							<span class="wcb-cd-app-date" data-wp-text="context.application.date"></span>
						</div>
						<div class="wcb-cd-app-actions">
							<span
								class="wcb-cd-status-badge"
								role="status"
								data-wp-text="context.application.statusLabel"
								data-wp-bind--data-status="context.application.status"
							></span>
							<?php
							// One action at most per row, so a button, not a menu (Basecamp
							// 10439832520): Withdraw until there's an outcome, Remove once the
							// job is gone. Both call DELETE /applications/{id} after a confirm.
							?>
							<button
								type="button"
								class="wcb-btn wcb-btn--outline wcb-btn--sm"
								data-wp-class--wcb-hidden="!state.appCanWithdraw"
								data-wp-bind--aria-label="state.appWithdrawLabel"
								data-wp-on--click="actions.withdrawApplication"
							><?php esc_html_e( 'Withdraw', 'wp-career-board' ); ?></button>
							<button
								type="button"
								class="wcb-btn wcb-btn--outline wcb-btn--sm"
								data-wp-class--wcb-hidden="!context.application.jobRemoved"
								data-wp-bind--aria-label="state.appRemoveLabel"
								data-wp-on--click="actions.withdrawApplication"
							><?php esc_html_e( 'Remove', 'wp-career-board' ); ?></button>
						</div>
					</div>
				</template>
			</div>

			<div class="wcb-load-more-wrap" data-wp-class--wcb-shown="state.appsHasMore">
				<button type="button" class="wcb-btn wcb-btn--outline wcb-load-more-btn" data-wp-on--click="actions.loadMoreApplications" data-wp-bind--disabled="state.appsLoadingMore">
					<span data-wp-class--wcb-hidden="state.appsLoadingMore"><?php esc_html_e( 'Load more applications', 'wp-career-board' ); ?></span>
					<span class="wcb-load-more-loading" data-wp-class--wcb-shown="state.appsLoadingMore"><?php esc_html_e( 'Loading&hellip;', 'wp-career-board' ); ?></span>
				</button>
			</div>

			<div class="wcb-empty-state wcb-empty-state--plain wcb-empty-state--toggle" data-wp-class--wcb-shown="state.noApplications">
				<span class="wcb-empty-state__icon" aria-hidden="true"><?php echo \WCB\Core\Icon::svg( 'send' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?></span>
				<p class="wcb-empty-state__title"><?php esc_html_e( 'No applications yet.', 'wp-career-board' ); ?></p>
				<p class="wcb-empty-state__body"><?php esc_html_e( 'Apply to a job and follow its progress here.', 'wp-career-board' ); ?></p>
				<a href="<?php echo esc_url( $wcb_jobs_url ); ?>" class="wcb-btn wcb-btn--primary">
					<?php esc_html_e( 'Browse jobs', 'wp-career-board' ); ?>
				</a>
			</div>
		</div>

		<!-- VIEW: Saved Jobs -->
		<div class="wcb-view-panel" role="region" aria-labelledby="wcb-tab-bookmarks" data-wp-class--wcb-view-active="state.isTabBookmarks">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'Saved jobs', 'wp-career-board' ); ?></h1>
			</div>

			<div class="wcb-cd-loading" role="status" data-wp-class--wcb-shown="state.loading">
				<span class="wcb-cd-spinner" aria-hidden="true"></span>
				<?php esc_html_e( 'Loading…', 'wp-career-board' ); ?>
			</div>
			<p class="wcb-cd-error" role="alert" data-wp-bind--hidden="!state.error" data-wp-text="state.error"></p>

			<div class="wcb-panel" aria-live="polite" data-wp-class--wcb-shown="state.hasBookmarks">
				<template data-wp-each--bookmark="state.bookmarks" data-wp-each-key="context.bookmark.id">
					<div class="wcb-cd-bookmark-row">
						<div class="wcb-cd-bookmark-main">
							<h3 class="wcb-cd-bookmark-title">
								<a
									data-wp-bind--href="context.bookmark.permalink"
									data-wp-text="context.bookmark.title"
									target="_blank"
									rel="noopener noreferrer"
								></a>
							</h3>
							<div class="wcb-cd-bookmark-meta">
							<span data-wp-text="state.savedMeta"></span>
						</div>
					</div>
					<div class="wcb-cd-bookmark-actions">
						<details class="wcb-row-menu" data-wp-on--toggle="actions.onRowMenuToggle" data-wp-on--keydown="actions.onRowMenuKeydown" data-wp-on-document--click="actions.closeRowMenusOutside">
							<summary class="wcb-btn wcb-btn--outline wcb-btn--sm wcb-row-menu__toggle" data-wp-bind--aria-label="state.savedMenuLabel"><span aria-hidden="true">&#8943;</span></summary>
							<div class="wcb-row-menu__list" data-wp-on--click="actions.closeRowMenu">
								<a
								class="wcb-row-menu__item"
								data-wp-bind--href="context.bookmark.permalink"
								target="_blank"
								rel="noopener noreferrer"
							><?php esc_html_e( 'View ↗', 'wp-career-board' ); ?></a>
								<button type="button" class="wcb-row-menu__item wcb-row-menu__item--danger" data-wp-on--click="actions.unbookmark"><?php esc_html_e( 'Remove', 'wp-career-board' ); ?></button>
							</div>
						</details>
					</div>
					</div>
				</template>
			</div>

			<div class="wcb-empty-state wcb-empty-state--plain wcb-empty-state--toggle" data-wp-class--wcb-shown="state.noBookmarks">
				<span class="wcb-empty-state__icon" aria-hidden="true"><?php echo \WCB\Core\Icon::svg( 'bookmark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?></span>
				<p class="wcb-empty-state__title"><?php esc_html_e( 'No saved jobs', 'wp-career-board' ); ?></p>
				<p class="wcb-empty-state__body"><?php esc_html_e( 'Bookmark a job to find it here.', 'wp-career-board' ); ?></p>
				<a href="<?php echo esc_url( $wcb_jobs_url ); ?>" class="wcb-btn wcb-btn--primary">
					<?php esc_html_e( 'Browse jobs', 'wp-career-board' ); ?>
				</a>
			</div>
		</div>

		<!-- VIEW: Saved Companies -->
		<div class="wcb-view-panel" role="region" aria-labelledby="wcb-tab-saved-companies" data-wp-class--wcb-view-active="state.isTabSavedCompanies">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'Saved companies', 'wp-career-board' ); ?></h1>
			</div>

			<div class="wcb-cd-loading" role="status" data-wp-class--wcb-shown="state.savedCompaniesLoading">
				<span class="wcb-cd-spinner" aria-hidden="true"></span>
				<?php esc_html_e( 'Loading…', 'wp-career-board' ); ?>
			</div>
			<p class="wcb-cd-error" role="alert" data-wp-bind--hidden="!state.savedCompaniesError" data-wp-text="state.savedCompaniesError"></p>

			<div class="wcb-panel" aria-live="polite" data-wp-class--wcb-shown="state.hasSavedCompanies">
				<template data-wp-each--company="state.savedCompanies" data-wp-each-key="context.company.id">
					<div class="wcb-cd-bookmark-row">
						<span class="wcb-avatar wcb-avatar--sm" aria-hidden="true" data-wp-class--wcb-avatar--logo="context.company.logo"><img alt="" loading="lazy" data-wp-bind--src="context.company.logo" data-wp-bind--hidden="!context.company.logo"><span data-wp-bind--hidden="context.company.logo" data-wp-text="context.company.initial"></span></span>
						<div class="wcb-cd-bookmark-main">
							<h3 class="wcb-cd-bookmark-title">
								<a
									data-wp-bind--href="context.company.permalink"
									data-wp-text="context.company.title"
									target="_blank"
									rel="noopener noreferrer"
								></a>
							</h3>
							<div class="wcb-cd-bookmark-meta">
								<span data-wp-text="state.savedMeta"></span>
							</div>
						</div>
						<div class="wcb-cd-bookmark-actions">
							<details class="wcb-row-menu" data-wp-on--toggle="actions.onRowMenuToggle" data-wp-on--keydown="actions.onRowMenuKeydown" data-wp-on-document--click="actions.closeRowMenusOutside">
								<summary class="wcb-btn wcb-btn--outline wcb-btn--sm wcb-row-menu__toggle" data-wp-bind--aria-label="state.savedMenuLabel"><span aria-hidden="true">&#8943;</span></summary>
								<div class="wcb-row-menu__list" data-wp-on--click="actions.closeRowMenu">
									<a
								class="wcb-row-menu__item"
								data-wp-bind--href="context.company.permalink"
								target="_blank"
								rel="noopener noreferrer"
							><?php esc_html_e( 'View ↗', 'wp-career-board' ); ?></a>
									<button type="button" class="wcb-row-menu__item wcb-row-menu__item--danger" data-wp-on--click="actions.unbookmarkCompany"><?php esc_html_e( 'Remove', 'wp-career-board' ); ?></button>
								</div>
							</details>
						</div>
					</div>
				</template>
			</div>

			<div class="wcb-empty-state wcb-empty-state--plain wcb-empty-state--toggle" data-wp-class--wcb-shown="state.noSavedCompanies">
				<span class="wcb-empty-state__icon" aria-hidden="true"><?php echo \WCB\Core\Icon::svg( 'building' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?></span>
				<p class="wcb-empty-state__title"><?php esc_html_e( 'No saved companies', 'wp-career-board' ); ?></p>
				<p class="wcb-empty-state__body"><?php esc_html_e( 'Bookmark a company to find it here.', 'wp-career-board' ); ?></p>
				<a href="<?php echo esc_url( home_url( '/companies/' ) ); ?>" class="wcb-btn wcb-btn--primary">
					<?php esc_html_e( 'Browse Companies', 'wp-career-board' ); ?>
				</a>
			</div>
		</div>

		<?php if ( apply_filters( 'wcb_pro_resumes_enabled', false ) ) : ?>
		<!-- VIEW: Saved Resumes -->
		<div class="wcb-view-panel" role="region" aria-labelledby="wcb-tab-saved-resumes" data-wp-class--wcb-view-active="state.isTabSavedResumes">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'Saved resumes', 'wp-career-board' ); ?></h1>
			</div>

			<div class="wcb-cd-loading" role="status" data-wp-class--wcb-shown="state.savedResumesLoading">
				<span class="wcb-cd-spinner" aria-hidden="true"></span>
				<?php esc_html_e( 'Loading…', 'wp-career-board' ); ?>
			</div>
			<p class="wcb-cd-error" role="alert" data-wp-bind--hidden="!state.savedResumesError" data-wp-text="state.savedResumesError"></p>

			<div class="wcb-panel" aria-live="polite" data-wp-class--wcb-shown="state.hasSavedResumes">
				<template data-wp-each--resume="state.savedResumes" data-wp-each-key="context.resume.id">
					<div class="wcb-cd-bookmark-row">
						<span class="wcb-avatar wcb-avatar--round wcb-avatar--sm" aria-hidden="true"><img alt="" loading="lazy" data-wp-bind--src="context.resume.avatar"></span>
						<div class="wcb-cd-bookmark-main">
							<h3 class="wcb-cd-bookmark-title">
								<a
									data-wp-bind--href="context.resume.permalink"
									data-wp-text="context.resume.title"
									target="_blank"
									rel="noopener noreferrer"
								></a>
							</h3>
							<div class="wcb-cd-bookmark-meta">
								<span data-wp-text="state.savedMeta"></span>
							</div>
						</div>
						<div class="wcb-cd-bookmark-actions">
							<details class="wcb-row-menu" data-wp-on--toggle="actions.onRowMenuToggle" data-wp-on--keydown="actions.onRowMenuKeydown" data-wp-on-document--click="actions.closeRowMenusOutside">
								<summary class="wcb-btn wcb-btn--outline wcb-btn--sm wcb-row-menu__toggle" data-wp-bind--aria-label="state.savedMenuLabel"><span aria-hidden="true">&#8943;</span></summary>
								<div class="wcb-row-menu__list" data-wp-on--click="actions.closeRowMenu">
									<a
								class="wcb-row-menu__item"
								data-wp-bind--href="context.resume.permalink"
								target="_blank"
								rel="noopener noreferrer"
							><?php esc_html_e( 'View ↗', 'wp-career-board' ); ?></a>
									<button type="button" class="wcb-row-menu__item wcb-row-menu__item--danger" data-wp-on--click="actions.unbookmarkResume"><?php esc_html_e( 'Remove', 'wp-career-board' ); ?></button>
								</div>
							</details>
						</div>
					</div>
				</template>
			</div>

			<div class="wcb-empty-state wcb-empty-state--plain wcb-empty-state--toggle" data-wp-class--wcb-shown="state.noSavedResumes">
				<span class="wcb-empty-state__icon" aria-hidden="true"><?php echo \WCB\Core\Icon::svg( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?></span>
				<p class="wcb-empty-state__title"><?php esc_html_e( 'No saved resumes', 'wp-career-board' ); ?></p>
				<p class="wcb-empty-state__body"><?php esc_html_e( 'Bookmark a candidate to find it here.', 'wp-career-board' ); ?></p>
				<?php if ( \WCB\Admin\Pages::url( 'resume_archive_page' ) ) : ?>
				<a href="<?php echo esc_url( \WCB\Admin\Pages::url( 'resume_archive_page' ) ); ?>" class="wcb-btn wcb-btn--primary">
					<?php esc_html_e( 'Browse Candidates', 'wp-career-board' ); ?>
				</a>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- VIEW: My Resumes (Pro) -->
		<div class="wcb-view-panel" role="region" aria-labelledby="wcb-tab-resumes" data-wp-class--wcb-view-active="state.isTabResumes">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'My Resumes', 'wp-career-board' ); ?></h1>
				<div class="wcb-resumes-header">
					<div class="wcb-resumes-actions" data-wp-class--wcb-hidden="state.showNewResumeForm">
						<button
							type="button"
							class="wcb-btn wcb-btn--primary"
							data-wp-on--click="actions.toggleNewResumeForm"
							data-wp-bind--disabled="state.isAtResumesCap"
						><?php esc_html_e( '+ New Resume', 'wp-career-board' ); ?></button>
						<label class="wcb-btn wcb-btn--outline wcb-upload-label" data-wp-bind--hidden="state.isAtResumesCap">
							<?php esc_html_e( 'Upload CV', 'wp-career-board' ); ?>
							<input
								type="file"
								class="wcb-sr-only"
								accept=".pdf,.doc,.docx"
								data-wp-on--change="actions.uploadResumeFile"
							/>
						</label>
						<span class="wcb-resume-cap-info" data-wp-bind--hidden="!state.maxResumes" data-wp-text="state.resumeCapLabel"></span>
					</div>
					<div class="wcb-new-resume-form" data-wp-class--wcb-hidden="!state.showNewResumeForm">
						<label class="screen-reader-text" for="wcb-new-resume-title"><?php esc_html_e( 'Resume title', 'wp-career-board' ); ?></label>
						<input
							type="text"
							id="wcb-new-resume-title"
							class="wcb-input"
							placeholder="<?php esc_attr_e( 'e.g. Software Developer', 'wp-career-board' ); ?>"
							data-wp-bind--value="state.newResumeTitle"
							data-wp-on--input="actions.setNewResumeTitle"
						>
						<button type="button" class="wcb-btn wcb-btn--primary" data-wp-on--click="actions.createResume"><?php esc_html_e( 'Create', 'wp-career-board' ); ?></button>
						<button type="button" class="wcb-btn wcb-btn--outline" data-wp-on--click="actions.toggleNewResumeForm"><?php esc_html_e( 'Cancel', 'wp-career-board' ); ?></button>
					</div>
				</div>
			</div>

			<div class="wcb-cd-loading" role="status" data-wp-class--wcb-shown="state.loading">
				<span class="wcb-cd-spinner" aria-hidden="true"></span>
				<?php esc_html_e( 'Loading…', 'wp-career-board' ); ?>
			</div>
			<p class="wcb-cd-error" role="alert" data-wp-bind--hidden="!state.error" data-wp-text="state.error"></p>

			<div class="wcb-panel wcb-resume-skeleton" data-wp-class--wcb-shown="state.resumesPending" aria-hidden="true">
				<div class="wcb-resume-skeleton__row"><span class="wcb-skeleton wcb-resume-skeleton__title"></span><span class="wcb-skeleton wcb-resume-skeleton__meta"></span></div>
				<div class="wcb-resume-skeleton__row"><span class="wcb-skeleton wcb-resume-skeleton__title"></span><span class="wcb-skeleton wcb-resume-skeleton__meta"></span></div>
			</div>
			<div class="wcb-panel" aria-live="polite" data-wp-class--wcb-shown="state.hasResumes">
			<template data-wp-each--resume="state.resumes" data-wp-each-key="context.resume.id">
				<div class="wcb-resume-card" data-wp-context='{"confirmingDelete": false}'>
					<div class="wcb-resume-card-info">
						<span class="wcb-resume-card-title" data-wp-text="context.resume.title"></span>
						<span class="wcb-resume-card-date" data-wp-text="context.resume.dateLabel"></span>
					</div>
					<?php // One main action (Open CV for an uploaded file, Edit for a built resume) and the rest in a menu, like My Jobs. ?>
					<div class="wcb-resume-card-actions" data-wp-class--wcb-hidden="context.confirmingDelete">
						<a
							class="wcb-btn wcb-btn--outline wcb-btn--sm"
							data-wp-bind--href="context.resume.pdfUrl"
							data-wp-class--wcb-hidden="!context.resume.isPdf"
							target="_blank"
							rel="noopener"
						><?php esc_html_e( 'Open CV', 'wp-career-board' ); ?></a>
						<button
							type="button"
							class="wcb-btn wcb-btn--outline wcb-btn--sm"
							data-wp-class--wcb-hidden="context.resume.isPdf"
							data-wp-on--click="actions.openResumeEditor"
						><?php esc_html_e( 'Edit', 'wp-career-board' ); ?></button>
						<details class="wcb-row-menu" data-wp-on--toggle="actions.onRowMenuToggle" data-wp-on--keydown="actions.onRowMenuKeydown">
							<summary class="wcb-btn wcb-btn--outline wcb-btn--sm wcb-row-menu__toggle" data-wp-bind--aria-label="state.resumeMenuLabel">
								<span aria-hidden="true">&#8943;</span>
							</summary>
							<div class="wcb-row-menu__list" data-wp-on--click="actions.closeRowMenu">
								<a
									class="wcb-row-menu__item"
									data-wp-bind--href="context.resume.permalink"
									data-wp-class--wcb-hidden="context.resume.isPdf"
									data-wp-bind--hidden="!context.resume.permalink"
									target="_blank"
									rel="noopener"
								><?php esc_html_e( 'View ↗', 'wp-career-board' ); ?></a>
								<label class="wcb-row-menu__item wcb-resume-reupload" data-wp-class--wcb-hidden="!context.resume.isPdf">
									<?php esc_html_e( 'Replace CV', 'wp-career-board' ); ?>
									<input
										type="file"
										accept=".pdf,.doc,.docx"
										data-wp-on--change="actions.reuploadResumePdf"
										class="wcb-resume-reupload-input"
									/>
								</label>
								<button
									type="button"
									class="wcb-row-menu__item wcb-row-menu__item--danger"
									data-wp-on--click="actions.requestDeleteConfirm"
								><?php esc_html_e( 'Delete', 'wp-career-board' ); ?></button>
							</div>
						</details>
					</div>
					<div class="wcb-resume-card-confirm" data-wp-class--wcb-hidden="!context.confirmingDelete">
						<span class="wcb-resume-confirm-msg"><?php esc_html_e( 'Delete this resume?', 'wp-career-board' ); ?></span>
						<button
							type="button"
							class="wcb-btn wcb-btn--danger wcb-btn--sm"
							data-wp-on--click="actions.deleteResume"
						><?php esc_html_e( 'Confirm', 'wp-career-board' ); ?></button>
						<button
							type="button"
							class="wcb-btn wcb-btn--outline wcb-btn--sm"
							data-wp-on--click="actions.cancelDelete"
						><?php esc_html_e( 'Cancel', 'wp-career-board' ); ?></button>
					</div>
				</div>
			</template>
		</div><!-- .wcb-panel -->

		<div class="wcb-empty-state wcb-empty-state--plain wcb-empty-state--toggle" data-wp-class--wcb-shown="state.noResumes">
			<span class="wcb-empty-state__icon" aria-hidden="true"><?php echo \WCB\Core\Icon::svg( 'graduation-cap' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?></span>
			<p class="wcb-empty-state__title"><?php esc_html_e( 'No resumes yet', 'wp-career-board' ); ?></p>
			<?php // The header's New Resume / Upload CV are the actions; one primary per screen. ?>
			<p class="wcb-empty-state__body"><?php esc_html_e( 'Build a resume here or upload a CV you already have, using the buttons above.', 'wp-career-board' ); ?></p>
		</div>
		</div><!-- .wcb-view-panel: My Resumes -->

		<?php if ( $wcb_resume_builder_embedded ) : ?>
		<!-- VIEW: Resume Builder (Pro embedded) -->
		<div class="wcb-view-panel" data-wp-class--wcb-view-active="state.isTabResumeBuilder">
			<?php
			if ( $wcb_resume_embed_id > 0 ) {
				echo do_blocks( '<!-- wp:wcb/resume-builder /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				/* The Resume Builder block only renders when a specific resume
				 * is selected (via the ?wcb_resume=N query param wired by the
				 * Edit button on My Resumes). Without it the panel was an
				 * empty white card with no signal - this empty state tells
				 * the customer where to go next. */
				?>
				<div class="wcb-page-header">
					<h1 class="wcb-page-title"><?php esc_html_e( 'Resume Builder', 'wp-career-board' ); ?></h1>
				</div>
				<div class="wcb-empty-state wcb-empty-state--plain">
					<p class="wcb-empty-state__body"><?php esc_html_e( 'Pick a resume from My Resumes to edit it here, or create a new one to get started.', 'wp-career-board' ); ?></p>
					<button type="button" class="wcb-btn wcb-btn--primary" data-wp-on--click="actions.switchToResumes"><?php esc_html_e( 'Go to My Resumes', 'wp-career-board' ); ?></button>
				</div>
				<?php
			}
			?>
		</div>
		<?php endif; ?>

	<?php if ( apply_filters( 'wcb_pro_alerts_enabled', false ) ) : ?>
		<!-- VIEW: Job Alerts (Pro) -->
		<div class="wcb-view-panel" role="region" aria-labelledby="wcb-tab-alerts" data-wp-class--wcb-view-active="state.isTabAlerts">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'Job alerts', 'wp-career-board' ); ?></h1>
			</div>

			<div class="wcb-cd-loading" role="status" data-wp-class--wcb-shown="state.alertsLoading">
				<span class="wcb-cd-spinner" aria-hidden="true"></span>
			</div>

			<div class="wcb-panel" aria-live="polite" data-wp-class--wcb-shown="state.hasAlerts">
				<template data-wp-each--alert="state.alerts" data-wp-each-key="context.alert.id">
					<div class="wcb-alert-row">
						<div class="wcb-alert-main">
							<h3 class="wcb-alert-title" data-wp-text="context.alert.label"></h3>
							<div class="wcb-alert-meta">
								<template data-wp-each--pill="context.alert.filterPills" data-wp-each-key="context.pill">
									<span class="wcb-alert-pill" data-wp-text="context.pill"></span>
								</template>
							</div>
						</div>
						<div class="wcb-alert-actions">
							<select class="wcb-alert-freq" aria-label="<?php esc_attr_e( 'Alert frequency', 'wp-career-board' ); ?>" data-wp-bind--value="context.alert.frequency" data-wp-on--change="actions.changeAlertFrequency">
								<option value="instant"><?php esc_html_e( 'Instant', 'wp-career-board' ); ?></option>
								<option value="daily"><?php esc_html_e( 'Daily', 'wp-career-board' ); ?></option>
								<option value="weekly"><?php esc_html_e( 'Weekly', 'wp-career-board' ); ?></option>
							</select>
							<button type="button" class="wcb-btn wcb-btn--danger wcb-btn--sm" data-wp-on--click="actions.deleteAlert">
								<?php esc_html_e( 'Delete', 'wp-career-board' ); ?>
							</button>
						</div>
					</div>
				</template>
			</div>

			<div class="wcb-empty-state wcb-empty-state--plain wcb-empty-state--toggle" data-wp-class--wcb-shown="state.noAlerts">
				<span class="wcb-empty-state__icon" aria-hidden="true"><?php echo \WCB\Core\Icon::svg( 'bell' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?></span>
				<p class="wcb-empty-state__title"><?php esc_html_e( 'No job alerts yet', 'wp-career-board' ); ?></p>
				<p class="wcb-empty-state__body"><?php esc_html_e( 'Search for jobs and click "Alert me" to hear when matching jobs are posted.', 'wp-career-board' ); ?></p>
				<a href="<?php echo esc_url( $wcb_jobs_url ); ?>" class="wcb-btn wcb-btn--primary">
		<?php esc_html_e( 'Browse jobs', 'wp-career-board' ); ?>
				</a>
			</div>
		</div>
	<?php endif; ?>

	<!-- ── Profile panel ──────────────────────────────────────────── -->
	<div class="wcb-view-panel" role="region" aria-labelledby="wcb-tab-profile" data-wp-class--wcb-view-active="state.isTabProfile">
		<div class="wcb-page-header">
			<h1 class="wcb-page-title"><?php esc_html_e( 'My Profile', 'wp-career-board' ); ?></h1>
		</div>
		<div class="wcb-panel wcb-panel--form wcb-shown">
			<?php // Shown, not a second (read-only) email field: it is edited in Settings only. ?>
			<div class="wcb-form-field">
				<span class="wcb-form-label"><?php esc_html_e( 'Email', 'wp-career-board' ); ?></span>
				<p class="wcb-profile-email">
					<span data-wp-text="state.profileEmail"><?php echo esc_html( $wcb_current_user->user_email ); ?></span>
					<button type="button" class="wcb-panel-link" aria-label="<?php esc_attr_e( 'Change email in Settings', 'wp-career-board' ); ?>" data-wp-on--click="actions.switchToSettings"><?php esc_html_e( 'Change', 'wp-career-board' ); ?></button>
				</p>
			</div>
			<?php // What employers see (photo, skills, experience) lives on the resume, not here. Same gate as the My Resumes tab. ?>
			<p class="wcb-profile-resume-note" data-wp-bind--hidden="!state.resumesEnabled">
				<?php esc_html_e( 'Employers see your photo, skills and experience from your resume.', 'wp-career-board' ); ?>
				<button type="button" class="wcb-panel-link" data-wp-on--click="actions.switchToResumes"><?php esc_html_e( 'Edit my resume', 'wp-career-board' ); ?></button>
			</p>

			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-profile-phone"><?php esc_html_e( 'Phone', 'wp-career-board' ); ?></label>
				<input
					type="tel"
					id="wcb-profile-phone"
					class="wcb-input"
					placeholder="<?php esc_attr_e( 'e.g. +1 555 123 4567', 'wp-career-board' ); ?>"
					data-wp-bind--value="state.profilePhone"
					data-wp-on--input="actions.updateProfilePhone"
				/>
			</div>

			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-profile-location"><?php esc_html_e( 'Location', 'wp-career-board' ); ?></label>
				<input
					type="text"
					id="wcb-profile-location"
					class="wcb-input"
					placeholder="<?php esc_attr_e( 'e.g. Bengaluru, India', 'wp-career-board' ); ?>"
					data-wp-bind--value="state.profileLocation"
					data-wp-on--input="actions.updateProfileLocation"
				/>
			</div>

			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-profile-bio"><?php esc_html_e( 'Bio / About me', 'wp-career-board' ); ?></label>
				<div class="wcb-editor" data-placeholder="<?php esc_attr_e( 'Tell employers about yourself…', 'wp-career-board' ); ?>">
					<div class="wcb-editor-holder" id="wcb-editor-profile-bio"></div>
					<textarea
						id="wcb-profile-bio"
						class="wcb-editor-source"
						rows="1"
						tabindex="-1"
						aria-label="<?php esc_attr_e( 'Bio / About me', 'wp-career-board' ); ?>"
						data-wp-bind--value="state.profileBio"
						data-wp-on--input="actions.updateProfileBio"
					></textarea>
				</div>
			</div>

			<?php
			// Custom-field groups from wcb_candidate_form_fields filter.
			// Stored against the candidate's user meta.
			$wcb_cd_custom = (array) apply_filters( 'wcb_candidate_form_fields', array(), $wcb_candidate_id );
			if ( ! empty( $wcb_cd_custom ) ) {
				\WCB\Core\FormCustomFields::render_groups( $wcb_cd_custom, 'updateCustomField', 'wcb-candidate-custom', (int) $wcb_candidate_id, 'user_meta' );
			}
			?>

			<div class="wcb-form-actions">
				<button type="button" class="wcb-btn wcb-btn--primary"
					data-wp-on--click="actions.saveProfile"
					data-wp-bind--disabled="state.profileSaving">
					<span data-wp-class--wcb-hidden="state.profileSaving"><?php esc_html_e( 'Save Profile', 'wp-career-board' ); ?></span>
					<span data-wp-class--wcb-hidden="!state.profileSaving"><?php esc_html_e( 'Saving…', 'wp-career-board' ); ?></span>
				</button>
				<span class="wcb-save-confirm wcb-icon-label" data-wp-class--wcb-shown="state.profileSaved"><?php echo \WCB\Core\Icon::svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper. ?><?php esc_html_e( 'Saved', 'wp-career-board' ); ?></span>
			</div>
		</div>
	</div>

	<!-- ── Settings panel ─────────────────────────────────────────── -->
	<div class="wcb-view-panel" role="region" aria-labelledby="wcb-tab-settings" data-wp-class--wcb-view-active="state.isTabSettings">
		<div class="wcb-page-header">
			<h1 class="wcb-page-title"><?php esc_html_e( 'Account Settings', 'wp-career-board' ); ?></h1>
		</div>
		<form class="wcb-panel wcb-panel--form wcb-shown" data-wp-on--submit="actions.saveAccount">
			<?php // The account's sign-in name, hidden: password managers pair it with the fields below. ?>
			<input type="text" autocomplete="username" value="<?php echo esc_attr( wp_get_current_user()->user_login ); ?>" hidden>
			<p class="wcb-account-msg" role="status" data-wp-bind--hidden="!state.accountMsg" data-wp-bind--data-type="state.accountMsgType" data-wp-text="state.accountMsg"></p>
			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-account-name"><?php esc_html_e( 'Display Name', 'wp-career-board' ); ?></label>
				<input type="text" id="wcb-account-name" class="wcb-input" autocomplete="name" data-wp-bind--value="state.accountName" data-wp-on--input="actions.updateField" data-wcb-field="accountName" />
			</div>
			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-account-email"><?php esc_html_e( 'Email', 'wp-career-board' ); ?></label>
				<input type="email" id="wcb-account-email" class="wcb-input" autocomplete="email" data-wp-bind--value="state.accountEmail" data-wp-on--input="actions.updateField" data-wcb-field="accountEmail" />
			</div>
			<?php // Asked only once the email changes: the Change Password form below asks for it otherwise. ?>
			<div class="wcb-form-field" hidden data-wp-bind--hidden="!state.emailChanged">
				<label class="wcb-form-label" for="wcb-account-emailpw"><?php esc_html_e( 'Current password', 'wp-career-board' ); ?></label>
				<input type="password" id="wcb-account-emailpw" class="wcb-input" autocomplete="current-password" data-wp-bind--value="state.accountEmailPassword" data-wp-on--input="actions.updateField" data-wcb-field="accountEmailPassword" />
				<span class="wcb-field-hint"><?php esc_html_e( 'Needed only if you change your email.', 'wp-career-board' ); ?></span>
			</div>
			<div class="wcb-form-field">
				<button type="submit" class="wcb-btn wcb-btn--primary" data-wp-bind--disabled="state.accountSaving"><?php esc_html_e( 'Save changes', 'wp-career-board' ); ?></button>
			</div>
		</form>

		<div class="wcb-page-header wcb-page-header--section">
			<h2 class="wcb-page-subtitle"><?php esc_html_e( 'Change Password', 'wp-career-board' ); ?></h2>
		</div>
		<form class="wcb-panel wcb-panel--form wcb-shown" data-wp-on--submit="actions.changePassword">
			<?php // The account's sign-in name, hidden: password managers pair it with the fields below. ?>
			<input type="text" autocomplete="username" value="<?php echo esc_attr( wp_get_current_user()->user_login ); ?>" hidden>
			<p class="wcb-account-msg" role="status" data-wp-bind--hidden="!state.pwMsg" data-wp-bind--data-type="state.pwMsgType" data-wp-text="state.pwMsg"></p>
			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-account-curpw"><?php esc_html_e( 'Current password', 'wp-career-board' ); ?></label>
				<input type="password" id="wcb-account-curpw" class="wcb-input" autocomplete="current-password" data-wp-bind--value="state.curPassword" data-wp-on--input="actions.updateField" data-wcb-field="curPassword" />
			</div>
			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-account-newpw"><?php esc_html_e( 'New Password', 'wp-career-board' ); ?></label>
				<input type="password" id="wcb-account-newpw" class="wcb-input" autocomplete="new-password" data-wp-bind--value="state.newPassword" data-wp-on--input="actions.updateField" data-wcb-field="newPassword" />
			</div>
			<div class="wcb-form-field">
				<label class="wcb-form-label" for="wcb-account-confpw"><?php esc_html_e( 'Confirm New Password', 'wp-career-board' ); ?></label>
				<input type="password" id="wcb-account-confpw" class="wcb-input" autocomplete="new-password" data-wp-bind--value="state.confPassword" data-wp-on--input="actions.updateField" data-wcb-field="confPassword" />
			</div>
			<div class="wcb-form-field">
				<button type="submit" class="wcb-btn wcb-btn--secondary" data-wp-bind--disabled="state.pwSaving"><?php esc_html_e( 'Update password', 'wp-career-board' ); ?></button>
			</div>
		</form>

		<?php
		$wcb_email_prefs_for = 'candidate';
		require WCB_DIR . 'templates/parts/email-preferences.php';
		?>

		<?php require WCB_DIR . 'templates/parts/privacy-settings.php'; ?>
	</div>

		<?php if ( $wcb_bell_enabled ) : ?>
		<!-- VIEW: Notifications (Pro) -->
		<div class="wcb-view-panel" role="region" aria-labelledby="wcb-tab-notifications" data-wp-class--wcb-view-active="state.isTabNotifications">
			<div class="wcb-page-header">
				<h1 class="wcb-page-title"><?php esc_html_e( 'Notifications', 'wp-career-board' ); ?></h1>
			</div>
			<?php
			// Pro's notifications-list markup (trusted Interactivity HTML; see note at top).
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted plugin Interactivity markup.
			echo $wcb_module_renders['notifications_bell'];
			?>
		</div>
		<?php endif; ?>

	</div>

</div><!-- .wcb-dashboard-shell -->

</div>
