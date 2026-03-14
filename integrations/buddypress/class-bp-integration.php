<?php
/**
 * BuddyPress integration for WP Career Board.
 *
 * Activated automatically when BuddyPress is active (buddypress() function exists).
 * Provides:
 *  - BP member types: 'employer' and 'candidate' (synced from WCB roles)
 *  - Activity stream entries when a job is posted or an application is submitted
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Integrations\Buddypress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BuddyPress integration.
 *
 * @since 1.0.0
 */
class BpIntegration {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'bp_init', array( $this, 'register_member_types' ) );
		add_action( 'wcb_job_created', array( $this, 'activity_job_posted' ), 10, 2 );
		add_action( 'wcb_application_submitted', array( $this, 'activity_applied' ), 10, 3 );
	}

	/**
	 * Register 'employer' and 'candidate' BuddyPress member types and sync them
	 * from WCB roles whenever a user's role is changed.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_member_types(): void {
		bp_register_member_type(
			'employer',
			array(
				'labels' => array(
					'name'          => __( 'Employers', 'wp-career-board' ),
					'singular_name' => __( 'Employer', 'wp-career-board' ),
				),
			)
		);

		bp_register_member_type(
			'candidate',
			array(
				'labels' => array(
					'name'          => __( 'Candidates', 'wp-career-board' ),
					'singular_name' => __( 'Candidate', 'wp-career-board' ),
				),
			)
		);

		// Sync WCB role → BP member type on user save.
		add_action(
			'set_user_role',
			function ( int $user_id, string $role ): void {
				if ( 'wcb_employer' === $role ) {
					bp_set_member_type( $user_id, 'employer' );
				}
				if ( 'wcb_candidate' === $role ) {
					bp_set_member_type( $user_id, 'candidate' );
				}
			},
			10,
			2
		);
	}

	/**
	 * Post a BuddyPress activity entry when a WCB job is published.
	 *
	 * @since 1.0.0
	 *
	 * @param int $job_id Post ID of the newly created job.
	 * @return void
	 */
	public function activity_job_posted( int $job_id ): void {
		if ( ! function_exists( 'bp_activity_add' ) ) {
			return;
		}
		$job = get_post( $job_id );
		if ( ! $job instanceof \WP_Post || 'publish' !== $job->post_status ) {
			return;
		}

		bp_activity_add(
			array(
				'user_id'       => (int) $job->post_author,
				'action'        => sprintf(
					/* translators: 1: linked author name, 2: linked job title */
					__( '%1$s posted a new job: %2$s', 'wp-career-board' ),
					bp_core_get_userlink( (int) $job->post_author ),
					'<a href="' . esc_url( (string) get_permalink( $job_id ) ) . '">' . esc_html( $job->post_title ) . '</a>'
				),
				'component'     => 'wp-career-board',
				'type'          => 'wcb_job_posted',
				'item_id'       => $job_id,
				'hide_sitewide' => false,
			)
		);
	}

	/**
	 * Post a BuddyPress activity entry when a candidate submits an application.
	 *
	 * @since 1.0.0
	 *
	 * @param int $app_id       Post ID of the new application.
	 * @param int $job_id       Post ID of the job applied to.
	 * @param int $candidate_id User ID of the applying candidate.
	 * @return void
	 */
	public function activity_applied( int $app_id, int $job_id, int $candidate_id ): void {
		if ( ! function_exists( 'bp_activity_add' ) ) {
			return;
		}
		$job = get_post( $job_id );
		if ( ! $job instanceof \WP_Post ) {
			return;
		}

		bp_activity_add(
			array(
				'user_id'       => $candidate_id,
				'action'        => sprintf(
					/* translators: 1: linked candidate name, 2: linked job title */
					__( '%1$s applied for: %2$s', 'wp-career-board' ),
					bp_core_get_userlink( $candidate_id ),
					'<a href="' . esc_url( (string) get_permalink( $job_id ) ) . '">' . esc_html( $job->post_title ) . '</a>'
				),
				'component'     => 'wp-career-board',
				'type'          => 'wcb_application_submitted',
				'item_id'       => $app_id,
				'hide_sitewide' => false,
			)
		);
	}
}
