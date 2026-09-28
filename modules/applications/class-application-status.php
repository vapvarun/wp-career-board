<?php
/**
 * Application status registry.
 *
 * @package WP_Career_Board
 * @since   1.1.2
 */

declare( strict_types=1 );

namespace WCB\Modules\Applications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical list of application statuses.
 *
 * Centralises the strings stored in `_wcb_status` meta and the human
 * labels rendered in the dashboard, so a feature touching application
 * state never invents a magic string.
 *
 * @since 1.1.2
 */
final class ApplicationStatus {

	public const SUBMITTED   = 'submitted';
	public const REVIEWING   = 'reviewing';
	public const SHORTLISTED = 'shortlisted';
	public const REJECTED    = 'rejected';
	public const HIRED       = 'hired';
	public const WITHDRAWN   = 'withdrawn';
	public const JOB_REMOVED = 'job_removed';

	/**
	 * The employer closed the job before deciding (owner decision D15).
	 *
	 * @since 1.8.0
	 */
	public const POSITION_CLOSED = 'position_closed';

	/**
	 * All valid status slugs.
	 *
	 * @since 1.1.2
	 * @return array<int,string>
	 */
	public static function all(): array {
		return array(
			self::SUBMITTED,
			self::REVIEWING,
			self::SHORTLISTED,
			self::REJECTED,
			self::HIRED,
			self::WITHDRAWN,
			self::JOB_REMOVED,
			self::POSITION_CLOSED,
		);
	}

	/**
	 * The statuses an employer may actually set on an application.
	 *
	 * Not the same as all(): `withdrawn` is candidate-only and `job_removed` is
	 * set by ApplicationLifecycle, so neither belongs in an employer's picker.
	 * This list was inlined in ApplicationsEndpoint::update_status() and
	 * nowhere else, which meant every API client had to carry its own copy —
	 * the mobile app included. It is the single source for both the endpoint's
	 * validation and the set published to clients, so the two cannot drift.
	 *
	 * @since 1.7.2
	 * @return array<int,string>
	 */
	public static function employer_actionable(): array {
		/**
		 * Filter the statuses an employer may set.
		 *
		 * @since 1.7.2
		 *
		 * @param array<int,string> $statuses Employer-actionable status slugs.
		 */
		return (array) apply_filters(
			'wcb_employer_actionable_statuses',
			array(
				self::SUBMITTED,
				self::REVIEWING,
				self::SHORTLISTED,
				self::REJECTED,
				self::HIRED,
			)
		);
	}

	/**
	 * The employer-actionable set as slug + translated label, for API clients.
	 *
	 * Published so a client renders the site's vocabulary in the site's locale
	 * instead of hardcoding five English strings.
	 *
	 * @since 1.7.2
	 * @return array<int,array{slug:string,label:string}>
	 */
	public static function employer_actionable_options(): array {
		return array_map(
			static fn( string $slug ): array => array(
				'slug'  => $slug,
				'label' => self::label( $slug ),
			),
			array_values( self::employer_actionable() )
		);
	}

	/**
	 * Statuses nobody moves an application out of: the candidate withdrew, the
	 * position closed, or the job is gone. The one rule behind the REST status
	 * route, the Kanban and ApplicationLifecycle::transition().
	 *
	 * @since 1.8.0
	 * @return array<int,string>
	 */
	public static function closed(): array {
		return array( self::WITHDRAWN, self::JOB_REMOVED, self::POSITION_CLOSED );
	}

	/**
	 * Statuses that represent end-of-pipeline states (no further employer action expected).
	 *
	 * @since 1.1.2
	 * @return array<int,string>
	 */
	public static function terminal(): array {
		return array( self::HIRED, self::REJECTED, self::WITHDRAWN, self::JOB_REMOVED, self::POSITION_CLOSED );
	}

	/**
	 * Audience: the candidate who applied.
	 *
	 * @since 1.8.0
	 */
	public const AUDIENCE_CANDIDATE = 'candidate';

	/**
	 * Audience: the employer who owns the job.
	 *
	 * @since 1.8.0
	 */
	public const AUDIENCE_EMPLOYER = 'employer';

	/**
	 * Audience: a site administrator.
	 *
	 * @since 1.8.0
	 */
	public const AUDIENCE_ADMIN = 'admin';

	/**
	 * Translated label for a status slug, worded for who is reading it.
	 *
	 * The one place a status becomes words. Every badge, email, bell, push and
	 * API payload calls this, so a candidate never reads "Rejected" in one
	 * place and "Not selected" in another (owner decision D12: candidates see
	 * "Not selected", employers and admins see "Rejected").
	 *
	 * @since 1.1.2
	 * @since 1.8.0 Added `$audience`.
	 *
	 * @param string $status   Status slug. Empty means submitted.
	 * @param string $audience One of the AUDIENCE_* constants.
	 * @return string Translated label, or the slug itself if unknown.
	 */
	public static function label( string $status, string $audience = self::AUDIENCE_EMPLOYER ): string {
		$status = '' !== $status ? $status : self::SUBMITTED;
		$labels = array(
			self::SUBMITTED       => __( 'Submitted', 'wp-career-board' ),
			self::REVIEWING       => __( 'Reviewing', 'wp-career-board' ),
			self::SHORTLISTED     => __( 'Shortlisted', 'wp-career-board' ),
			self::REJECTED        => self::AUDIENCE_CANDIDATE === $audience
				? __( 'Not selected', 'wp-career-board' )
				: __( 'Rejected', 'wp-career-board' ),
			self::HIRED           => __( 'Hired', 'wp-career-board' ),
			self::WITHDRAWN       => __( 'Withdrawn', 'wp-career-board' ),
			self::JOB_REMOVED     => __( 'Job removed', 'wp-career-board' ),
			self::POSITION_CLOSED => self::AUDIENCE_CANDIDATE === $audience
				? __( 'Position closed', 'wp-career-board' )
				: __( 'Closed', 'wp-career-board' ),
		);

		/**
		 * Filter the label shown for an application status.
		 *
		 * @since 1.8.0
		 *
		 * @param string $label    Translated label.
		 * @param string $status   Status slug.
		 * @param string $audience candidate, employer or admin.
		 */
		return (string) apply_filters( 'wcb_application_status_label', $labels[ $status ] ?? $status, $status, $audience );
	}

	/**
	 * Visual tone for a status, so every client colours a badge the same way.
	 *
	 * @since 1.8.0
	 *
	 * @param string $status Status slug. Empty means submitted.
	 * @return string neutral, info, warning, accent, success or danger.
	 */
	public static function tone( string $status ): string {
		$tones = array(
			self::SUBMITTED   => 'info',
			self::REVIEWING   => 'warning',
			self::SHORTLISTED => 'accent',
			self::HIRED       => 'success',
			self::REJECTED    => 'danger',
		);
		return $tones[ '' !== $status ? $status : self::SUBMITTED ] ?? 'neutral';
	}

	/**
	 * Escaped wp-admin badge for a status (admin wording, tone colour).
	 *
	 * @since 1.8.0
	 *
	 * @param string $status Status slug. Empty means submitted.
	 * @return string `<span class="wcb-badge ...">` markup, already escaped.
	 */
	public static function admin_badge( string $status ): string {
		$variants = array(
			'info'    => 'info',
			'warning' => 'warn',
			'accent'  => 'info',
			'success' => 'success',
			'danger'  => 'danger',
		);
		return sprintf(
			'<span class="wcb-badge wcb-badge--%1$s">%2$s</span>',
			esc_attr( $variants[ self::tone( $status ) ] ?? 'default' ),
			esc_html( self::label( $status, self::AUDIENCE_ADMIN ) )
		);
	}

	/**
	 * Every status as slug => label for one audience, in pipeline order.
	 *
	 * @since 1.8.0
	 *
	 * @param string $audience One of the AUDIENCE_* constants.
	 * @return array<string,string>
	 */
	public static function labels( string $audience = self::AUDIENCE_EMPLOYER ): array {
		$out = array();
		foreach ( self::all() as $slug ) {
			$out[ $slug ] = self::label( $slug, $audience );
		}
		return $out;
	}

	/**
	 * The status fields every API payload carries: slug, label and tone.
	 *
	 * @since 1.8.0
	 *
	 * @param string $status   Status slug. Empty means submitted.
	 * @param string $audience One of the AUDIENCE_* constants.
	 * @return array{status:string,status_label:string,status_tone:string,statusLabel:string}
	 */
	public static function payload( string $status, string $audience ): array {
		$status = '' !== $status ? $status : self::SUBMITTED;
		$label  = self::label( $status, $audience );
		return array(
			'status'       => $status,
			'status_label' => $label,
			'status_tone'  => self::tone( $status ),
			// camelCase twin read by the dashboards since 1.1.
			'statusLabel'  => $label,
		);
	}

	/**
	 * Application counts per status, site-wide or for one job or candidate.
	 *
	 * One GROUP BY instead of counting a loaded page, so pills and tabs stay
	 * right at any size. Applications with no status meta (pre-1.1 rows)
	 * count as submitted. The site-wide figure is cached until the next
	 * status change (ApplicationLifecycle clears `wcb_app_status_counts`).
	 *
	 * @since 1.8.0
	 *
	 * @param string $scope    '' for all, 'job' or 'candidate'.
	 * @param int    $scope_id Job or candidate user ID.
	 * @return array{total:int,by_status:array<string,int>}
	 */
	public static function counts( string $scope = '', int $scope_id = 0 ): array {
		$scope_key = array(
			'job'       => '_wcb_job_id',
			'candidate' => '_wcb_candidate_id',
		)[ $scope ] ?? '';

		if ( '' === $scope_key ) {
			$cached = get_transient( 'wcb_app_status_counts' );
			if ( is_array( $cached ) && isset( $cached['total'], $cached['by_status'] ) ) {
				return $cached;
			}
		}

		global $wpdb;

		$scope_join = '' !== $scope_key
			? $wpdb->prepare( " INNER JOIN {$wpdb->postmeta} sc ON sc.post_id = p.ID AND sc.meta_key = %s AND sc.meta_value = %s", $scope_key, (string) $scope_id )
			: '';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $scope_join is prepared above.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COALESCE(st.meta_value, '') AS status, COUNT(*) AS cnt
				FROM {$wpdb->posts} p{$scope_join}
				LEFT JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = '_wcb_status'
				WHERE p.post_type = %s AND p.post_status = 'publish'
				GROUP BY status",
				'wcb_application'
			),
			ARRAY_A
		);
		// phpcs:enable

		$counts = array(
			'total'     => 0,
			'by_status' => array(),
		);
		foreach ( (array) $rows as $row ) {
			$status                         = '' !== $row['status'] ? (string) $row['status'] : self::SUBMITTED;
			$counts['by_status'][ $status ] = ( $counts['by_status'][ $status ] ?? 0 ) + (int) $row['cnt'];
			$counts['total']               += (int) $row['cnt'];
		}

		if ( '' === $scope_key ) {
			set_transient( 'wcb_app_status_counts', $counts, HOUR_IN_SECONDS );
		}

		return $counts;
	}

	/**
	 * Whether a status slug is recognised.
	 *
	 * @since 1.1.2
	 *
	 * @param string $status Status slug.
	 * @return bool
	 */
	public static function is_valid( string $status ): bool {
		return in_array( $status, self::all(), true );
	}
}
