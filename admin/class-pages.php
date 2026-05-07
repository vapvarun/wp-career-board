<?php
/**
 * Resolver for the canonical Career Board pages — Post a Job, the two
 * dashboards, archives, and registration.
 *
 * Single source of truth so admin dropdowns and frontend renders agree on
 * which post ID is which page. Resolution falls back to a known slug map
 * when the assigned ID is missing or points at a non-published post, so an
 * install whose setup wizard ran before this resolver existed still surfaces
 * the right page in the Pages tab dropdowns.
 *
 * @package WP_Career_Board
 * @since   1.2.4
 */

declare( strict_types=1 );

namespace WCB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static resolver around the seven canonical page settings keys.
 *
 * @since 1.2.4
 */
final class Pages {

	/**
	 * Map of wcb_settings key => canonical page slug.
	 *
	 * The slugs here match what the setup wizard creates via
	 * AdminSettings::handle_create_pages and SetupWizard::create_required_pages,
	 * so an install with the canonical pages in place but no wcb_settings
	 * entries (the failure mode this class fixes) still resolves correctly.
	 *
	 * @since 1.2.4
	 * @var array<string,string>
	 */
	private const CANONICAL_SLUGS = array(
		'post_job_page'              => 'post-a-job',
		'employer_dashboard_page'    => 'employer-dashboard',
		'candidate_dashboard_page'   => 'candidate-dashboard',
		'jobs_archive_page'          => 'find-jobs',
		'company_archive_page'       => 'find-companies',
		'employer_registration_page' => 'employer-registration',
		'resume_archive_page'        => 'find-resumes',
	);

	/**
	 * Return the list of known wcb_settings page keys.
	 *
	 * @since 1.2.4
	 * @return array<int,string>
	 */
	public static function known_keys(): array {
		return array_keys( self::CANONICAL_SLUGS );
	}

	/**
	 * Return the canonical slug for a known page key, or empty string.
	 *
	 * @since 1.2.4
	 *
	 * @param string $key wcb_settings page key.
	 * @return string
	 */
	public static function canonical_slug( string $key ): string {
		return self::CANONICAL_SLUGS[ $key ] ?? '';
	}

	/**
	 * Resolve the page ID for a known key.
	 *
	 * Order of resolution:
	 *  1. wcb_settings[$key] — if non-zero and the post is published.
	 *  2. Slug match against CANONICAL_SLUGS — if a published page exists.
	 *  3. 0 — neither assignment nor slug match resolves.
	 *
	 * @since 1.2.4
	 *
	 * @param string $key wcb_settings page key.
	 * @return int Post ID, or 0 when nothing resolves.
	 */
	public static function get_id( string $key ): int {
		if ( ! isset( self::CANONICAL_SLUGS[ $key ] ) ) {
			return 0;
		}

		$assigned = Settings::int( $key, 0 );
		if ( $assigned > 0 && 'publish' === get_post_status( $assigned ) ) {
			return $assigned;
		}

		$page = get_page_by_path( self::CANONICAL_SLUGS[ $key ] );
		return $page instanceof \WP_Post ? (int) $page->ID : 0;
	}

	/**
	 * Walk every known key and write its slug-resolved ID into wcb_settings
	 * when no assigned ID is in place. Idempotent — keys that already point
	 * at a published post are left alone.
	 *
	 * Called from the 1.2.4 install gate so existing sites whose setup wizard
	 * ran before pages were persisted into wcb_settings get backfilled
	 * automatically without admin intervention.
	 *
	 * @since 1.2.4
	 * @return array<string,int> Map of key => id for every key written.
	 */
	public static function backfill_from_slugs(): array {
		$written  = array();
		$settings = (array) get_option( 'wcb_settings', array() );
		$changed  = false;

		foreach ( self::CANONICAL_SLUGS as $key => $slug ) {
			$current = isset( $settings[ $key ] ) ? (int) $settings[ $key ] : 0;
			if ( $current > 0 && 'publish' === get_post_status( $current ) ) {
				continue;
			}
			$page = get_page_by_path( $slug );
			if ( $page instanceof \WP_Post ) {
				$settings[ $key ] = (int) $page->ID;
				$written[ $key ]  = (int) $page->ID;
				$changed          = true;
			}
		}

		if ( $changed ) {
			update_option( 'wcb_settings', $settings );
		}

		return $written;
	}
}
