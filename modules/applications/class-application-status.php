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
	 * Statuses that represent end-of-pipeline states (no further employer action expected).
	 *
	 * @since 1.1.2
	 * @return array<int,string>
	 */
	public static function terminal(): array {
		return array( self::HIRED, self::REJECTED, self::WITHDRAWN, self::JOB_REMOVED );
	}

	/**
	 * Human-friendly translated label for a status slug.
	 *
	 * @since 1.1.2
	 *
	 * @param string $status Status slug.
	 * @return string Translated label, or the slug itself if unknown.
	 */
	public static function label( string $status ): string {
		$labels = array(
			self::SUBMITTED   => __( 'Submitted', 'wp-career-board' ),
			self::REVIEWING   => __( 'Reviewing', 'wp-career-board' ),
			self::SHORTLISTED => __( 'Shortlisted', 'wp-career-board' ),
			self::REJECTED    => __( 'Rejected', 'wp-career-board' ),
			self::HIRED       => __( 'Hired', 'wp-career-board' ),
			self::WITHDRAWN   => __( 'Withdrawn', 'wp-career-board' ),
			self::JOB_REMOVED => __( 'Job removed', 'wp-career-board' ),
		);
		return $labels[ $status ] ?? $status;
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
