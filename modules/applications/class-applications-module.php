<?php
/**
 * Applications module — registers wcb_application CPT.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Applications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applications module class.
 *
 * @since 1.0.0
 */
final class ApplicationsModule {

	/**
	 * Boot the module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_widgets' ) );
		// Link a new account's matching guest applications so they surface in the
		// candidate's My Applications instead of staying orphaned as post_author 0.
		add_action( 'user_register', array( $this, 'claim_guest_applications' ) );
		( new ApplicationLifecycle() )->boot();
	}

	/**
	 * Reassign a newly-registered user's guest applications to their account.
	 *
	 * Guest applications are stored with post_author 0 + _wcb_guest_email. A
	 * visitor who applied as a guest and later registers with that same email
	 * would otherwise never see those applications (My Applications queries by
	 * post_author). On registration, claim every wcb_application whose
	 * _wcb_guest_email matches the new account so its history surfaces.
	 *
	 * WordPress enforces unique user emails, so a match unambiguously belongs to
	 * this user. Idempotent: only guest-owned (post_author 0) rows are reassigned,
	 * never a row already owned by a real candidate.
	 *
	 * @since 1.7.0
	 *
	 * @param int $user_id The newly-registered user ID.
	 * @return void
	 */
	public function claim_guest_applications( int $user_id ): void {
		$wcb_user = get_userdata( $user_id );
		if ( ! $wcb_user || '' === (string) $wcb_user->user_email ) {
			return;
		}

		$wcb_app_ids = get_posts(
			array(
				'post_type'      => 'wcb_application',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 200,
				'no_found_rows'  => true,
				'meta_key'       => '_wcb_guest_email', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-off on register, bounded, indexed key.
				'meta_value'     => $wcb_user->user_email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- exact match, bounded.
			)
		);
		if ( empty( $wcb_app_ids ) ) {
			return;
		}

		$wcb_claimed = array();
		foreach ( $wcb_app_ids as $wcb_app_id ) {
			if ( 0 !== (int) get_post_field( 'post_author', $wcb_app_id ) ) {
				continue; // Already owned by a real candidate — never override.
			}
			wp_update_post(
				array(
					'ID'          => (int) $wcb_app_id,
					'post_author' => $user_id,
				)
			);
			update_post_meta( (int) $wcb_app_id, '_wcb_candidate_id', $user_id );
			$wcb_claimed[] = (int) $wcb_app_id;
		}

		if ( $wcb_claimed ) {
			/**
			 * Fires after a newly-registered user's guest applications are claimed.
			 *
			 * @since 1.7.0
			 *
			 * @param int        $user_id     The user who claimed the applications.
			 * @param array<int> $wcb_claimed Application IDs reassigned to them.
			 */
			do_action( 'wcb_guest_applications_claimed', $user_id, $wcb_claimed );
		}
	}

	/**
	 * Register all application widgets with the global registry.
	 *
	 * Each widget renders identically inside an admin metabox, a [wcb_widget]
	 * shortcode, or a future Gutenberg block — see WCB\Core\Widgets.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_widgets(): void {
		$registry = \WCB\Core\Widgets\WidgetRegistry::instance();
		$registry->register( new Widgets\ApplicantCard() );
		$registry->register( new Widgets\CoverLetter() );
		$registry->register( new Widgets\ResumePreview() );
		$registry->register( new Widgets\StatusTimeline() );
		$registry->register( new Widgets\StatusChanger() );
		$registry->register( new Widgets\QuickActions() );
	}

	/**
	 * Register the wcb_application post type.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			'wcb_application',
			array(
				'labels'          => array(
					'name'               => __( 'Applications', 'wp-career-board' ),
					'singular_name'      => __( 'Application', 'wp-career-board' ),
					'add_new_item'       => __( 'Add New Application', 'wp-career-board' ),
					'edit_item'          => __( 'Edit Application', 'wp-career-board' ),
					'not_found'          => __( 'No applications found.', 'wp-career-board' ),
					'not_found_in_trash' => __( 'No applications found in Trash.', 'wp-career-board' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_rest'    => true,
				'show_in_menu'    => false,
				'supports'        => array( 'title', 'custom-fields' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}
}
