<?php
/**
 * Registers WCB user roles and capabilities.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages custom user roles and capabilities for the job board.
 *
 * @since 1.0.0
 */
final class Roles {

	/**
	 * Register all custom roles and add admin capabilities.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void {
		// Role names are stored in English and translated when shown, the way
		// WordPress does its own roles. Storing __() wrote whichever language
		// the last visitor used into wp_user_roles for everyone.
		add_filter( 'gettext_with_context', array( self::class, 'translate_role_name' ), 10, 4 );
		$this->add_employer_role();
		$this->add_candidate_role();
		$this->add_moderator_role();
		$this->add_admin_caps();
	}

	/**
	 * Add or sync the Employer role.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function add_employer_role(): void {
		$this->sync_role(
			'wcb_employer',
			'Employer',
			array(
				'read'                          => true,
				'wcb_post_jobs'                 => true,
				'wcb_manage_company'            => true,
				'wcb_view_applications'         => true,
				'wcb_access_employer_dashboard' => true,
			)
		);
	}

	/**
	 * Add or sync the Candidate role.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function add_candidate_role(): void {
		$this->sync_role(
			'wcb_candidate',
			'Candidate',
			array(
				'read'                           => true,
				'wcb_apply_jobs'                 => true,
				'wcb_manage_resume'              => true,
				'wcb_bookmark_jobs'              => true,
				'wcb_access_candidate_dashboard' => true,
				'wcb_withdraw_application'       => true,
			)
		);
	}

	/**
	 * Add or sync the Job Moderator role.
	 *
	 * Slug stays `wcb_board_moderator` for back-compat with existing assigned
	 * users; only the display label is "Job Moderator". The role moderates
	 * jobs (the user-submitted content) — boards are admin-only config and
	 * carry nothing to moderate, so the old "Board Moderator" label was a
	 * misnomer that over-promised per-board scoping that never existed.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function add_moderator_role(): void {
		$this->sync_role(
			'wcb_board_moderator',
			'Job moderator',
			array(
				'read'                  => true,
				'wcb_moderate_jobs'     => true,
				'wcb_access_admin_jobs' => true,
			)
		);
	}

	/**
	 * Translate our role names wherever WordPress shows a role
	 * (translate_user_role(), which reads the "User role" context).
	 *
	 * Untyped on purpose: a role saved without a name (membership and role
	 * plugins do this) reaches gettext as null, and a strict string here took
	 * the whole Users screen down (1.8.3).
	 *
	 * @since 1.8.2
	 * @since 1.8.3 Accepts any value and passes non-strings through.
	 * @param mixed $translation Translated text.
	 * @param mixed $text        Stored role name.
	 * @param mixed $context     Gettext context.
	 * @param mixed $domain      Text domain.
	 * @return mixed
	 */
	public static function translate_role_name( $translation, $text, $context, $domain ) {
		if ( ! is_string( $text ) || 'User role' !== $context || 'default' !== $domain ) {
			return $translation;
		}
		$names = array(
			'Employer'      => __( 'Employer', 'wp-career-board' ),
			'Candidate'     => __( 'Candidate', 'wp-career-board' ),
			'Job moderator' => __( 'Job moderator', 'wp-career-board' ),
		);
		return $names[ $text ] ?? $translation;
	}

	/**
	 * Create a role or top up missing capabilities on an existing one.
	 *
	 * Idempotent: safe to run on every `init` so cap changes shipped in plugin
	 * upgrades reach existing installs without requiring re-activation. Also
	 * refreshes the display label when it drifts, so a label rename shipped in
	 * an upgrade (e.g. Board Moderator -> Job Moderator) reaches installs where
	 * the role already exists without forcing re-activation.
	 *
	 * @since 1.0.2
	 * @param string             $role_slug Role slug.
	 * @param string             $label     Human-readable role label.
	 * @param array<string,bool> $caps      Capability map.
	 * @return void
	 */
	private function sync_role( string $role_slug, string $label, array $caps ): void {
		$role = get_role( $role_slug );
		if ( ! $role ) {
			add_role( $role_slug, $label, $caps );
			return;
		}
		foreach ( $caps as $cap => $grant ) {
			if ( $grant && ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}

		// Refresh the stored display label if it drifted. WordPress keeps role
		// names in the wp_user_roles option at creation time; get_role() never
		// updates them, so a renamed label needs a guarded one-time write.
		$wp_roles = wp_roles();
		if ( isset( $wp_roles->roles[ $role_slug ] ) && $wp_roles->roles[ $role_slug ]['name'] !== $label ) {
			$wp_roles->roles[ $role_slug ]['name'] = $label;
			$wp_roles->role_names[ $role_slug ]    = $label;
			update_option( $wp_roles->role_key, $wp_roles->roles );
		}
	}

	/**
	 * Grant all WCB capabilities to the administrator role.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function add_admin_caps(): void {
		$admin = get_role( 'administrator' );

		if ( ! $admin ) {
			return;
		}

		$caps = array(
			'wcb_post_jobs',
			'wcb_manage_company',
			'wcb_view_applications',
			'wcb_apply_jobs',
			'wcb_manage_resume',
			'wcb_bookmark_jobs',
			'wcb_moderate_jobs',
			'wcb_manage_settings',
			'wcb_access_admin_jobs',
			'wcb_view_analytics',
			'wcb_access_employer_dashboard',
			'wcb_access_candidate_dashboard',
			'wcb_withdraw_application',
		);

		foreach ( $caps as $cap ) {
			if ( ! $admin->has_cap( $cap ) ) {
				$admin->add_cap( $cap );
			}
		}
	}

	/**
	 * Background hook that gives members their default role back (1.8.3).
	 *
	 * @since 1.8.3
	 */
	public const REPAIR_HOOK = 'wcb_restore_default_role';

	/**
	 * The roles a member gets from signing up, applying or posting.
	 *
	 * @since 1.8.3
	 */
	private const MEMBER_ROLES = array( 'wcb_employer', 'wcb_candidate' );

	/**
	 * Give a user a plugin role when they become an employer or candidate.
	 *
	 * The role is added, so the member keeps the site's default role and every
	 * permission other plugins give it (enrolling, submitting listings,
	 * posting). Until 1.8.3 a plain member's default role was replaced.
	 * Sites that want that back return true from
	 * `wcb_member_role_replaces_default`.
	 *
	 * @since 1.8.0
	 * @since 1.8.3 Adds the role instead of replacing the default role.
	 *
	 * @param \WP_User $user User becoming an employer or candidate.
	 * @param string   $role wcb_employer or wcb_candidate.
	 * @return void
	 */
	public static function grant_member_role( \WP_User $user, string $role ): void {
		$plain = array( (string) get_option( 'default_role', 'subscriber' ), 'subscriber' );
		if ( array() === array_diff( (array) $user->roles, $plain ) && self::replaces_default_role( $user, $role ) ) {
			$user->set_role( $role );
			return;
		}
		$user->add_role( $role );
	}

	/**
	 * Employer and Candidate checkboxes on the core Edit User screen.
	 *
	 * Core's Role dropdown holds one role, so a member who is Subscriber and
	 * Employer shows as Subscriber, and saving the screen would drop Employer.
	 * These boxes carry the plugin roles through the save; unticking one is
	 * how an owner takes it away.
	 *
	 * @since 1.8.3
	 *
	 * @param \WP_User $user User being edited.
	 * @return void
	 */
	public static function render_member_roles( \WP_User $user ): void {
		if ( ! current_user_can( 'promote_user', $user->ID ) ) {
			return;
		}
		$names = wp_roles()->role_names;
		?>
		<h2><?php esc_html_e( 'Career Board', 'wp-career-board' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Career Board roles', 'wp-career-board' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Career Board roles', 'wp-career-board' ); ?></legend>
						<?php wp_nonce_field( 'wcb_member_roles_' . $user->ID, 'wcb_member_roles_nonce' ); ?>
						<?php foreach ( self::MEMBER_ROLES as $role ) : ?>
							<label>
								<input type="checkbox" name="wcb_member_roles[]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, (array) $user->roles, true ) ); ?> />
								<?php echo esc_html( translate_user_role( $names[ $role ] ?? $role ) ); ?>
							</label><br />
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Added on top of the Role above, so the member keeps it.', 'wp-career-board' ); ?></p>
					</fieldset>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Apply the Career Board boxes after core has saved the Role dropdown.
	 *
	 * Runs on `profile_update`, which fires after core's set_role, and only
	 * for a save from the Edit User screen (our nonce present). A plugin role
	 * picked in the dropdown counts as ticked.
	 *
	 * @since 1.8.3
	 *
	 * @param int $user_id User saved.
	 * @return void
	 */
	public static function save_member_roles( int $user_id ): void {
		$nonce = isset( $_POST['wcb_member_roles_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wcb_member_roles_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wcb_member_roles_' . $user_id ) || ! current_user_can( 'promote_user', $user_id ) ) {
			return;
		}
		$ticked   = isset( $_POST['wcb_member_roles'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['wcb_member_roles'] ) ) : array();
		$ticked[] = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
		$user     = new \WP_User( $user_id );
		foreach ( self::MEMBER_ROLES as $role ) {
			$has = in_array( $role, (array) $user->roles, true );
			if ( in_array( $role, $ticked, true ) && ! $has ) {
				$user->add_role( $role );
			} elseif ( ! in_array( $role, $ticked, true ) && $has ) {
				$user->remove_role( $role );
			}
		}
	}

	/**
	 * Whether this site swaps the default role for the plugin role.
	 *
	 * @since 1.8.3
	 *
	 * @param \WP_User $user User becoming an employer or candidate.
	 * @param string   $role wcb_employer or wcb_candidate.
	 * @return bool
	 */
	private static function replaces_default_role( \WP_User $user, string $role ): bool {
		/**
		 * Filters whether becoming an employer or candidate replaces a plain
		 * member's default role instead of adding to it.
		 *
		 * @since 1.8.3
		 *
		 * @param bool     $replace Default false: the default role is kept.
		 * @param \WP_User $user    User becoming an employer or candidate.
		 * @param string   $role    wcb_employer or wcb_candidate.
		 */
		return (bool) apply_filters( 'wcb_member_role_replaces_default', false, $user, $role );
	}

	/**
	 * Give the default role back to members who lost it before 1.8.3.
	 *
	 * Only members holding nothing but employer and/or candidate are touched,
	 * 200 a batch in the background after the upgrade. Skipped when the site
	 * opted into replacing the default role.
	 *
	 * @since 1.8.3
	 *
	 * @param int $page Batch number, from 1.
	 * @return void
	 */
	public static function restore_default_role( int $page = 1 ): void {
		$default = (string) get_option( 'default_role', 'subscriber' );
		if ( null === get_role( $default ) || in_array( $default, self::MEMBER_ROLES, true ) ) {
			return;
		}
		// The set never shrinks (members keep their plugin roles), so paging by ID is stable.
		$ids = get_users(
			array(
				'role__in' => self::MEMBER_ROLES,
				'orderby'  => 'ID',
				'order'    => 'ASC',
				'number'   => 200,
				'paged'    => max( 1, $page ),
				'fields'   => 'ID',
			)
		);
		foreach ( $ids as $id ) {
			$user  = new \WP_User( (int) $id );
			$roles = (array) $user->roles;
			$role  = in_array( 'wcb_employer', $roles, true ) ? 'wcb_employer' : 'wcb_candidate';
			if ( array() === array_diff( $roles, self::MEMBER_ROLES ) && ! self::replaces_default_role( $user, $role ) ) {
				$user->add_role( $default );
			}
		}
		if ( 200 === count( $ids ) ) {
			wp_schedule_single_event( time() + 30, self::REPAIR_HOOK, array( max( 1, $page ) + 1 ) );
		}
	}

	/**
	 * What the current user is on this board, for dashboard identity lines.
	 *
	 * @since 1.8.1
	 * @return string
	 */
	public static function current_member_label(): string {
		// phpcs:disable WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
		if ( wp_is_ability_granted( 'wcb/manage-settings' ) ) {
			return __( 'Site admin', 'wp-career-board' );
		}
		if ( wp_is_ability_granted( 'wcb/manage-company' ) ) {
			return __( 'Employer', 'wp-career-board' );
		}
		if ( wp_is_ability_granted( 'wcb/moderate-jobs' ) ) {
			return __( 'Job moderator', 'wp-career-board' );
		}
		// phpcs:enable
		return __( 'Candidate', 'wp-career-board' );
	}

	/**
	 * The moderation queue for whoever reviews jobs, or '' for everyone else.
	 *
	 * @since 1.8.1
	 * @return string
	 */
	public static function moderation_queue_url(): string {
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
		return is_user_logged_in() && wp_is_ability_granted( 'wcb/moderate-jobs' )
			? admin_url( 'admin.php?page=wcb-jobs&post_status=pending' )
			: '';
	}

	/**
	 * Admin bar link to the moderation queue, front and back, with the count
	 * waiting: a moderator otherwise had no route to their work.
	 *
	 * @since 1.8.1
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 * @return void
	 */
	public static function moderation_bar_node( \WP_Admin_Bar $bar ): void {
		$url = self::moderation_queue_url();
		if ( '' === $url ) {
			return;
		}
		$waiting = (int) ( wp_count_posts( 'wcb_job' )->pending ?? 0 );
		$bar->add_node(
			array(
				'id'    => 'wcb-moderation',
				'title' => $waiting
					/* translators: %s: number of jobs waiting for review. */
					? sprintf( __( 'Jobs to review (%s)', 'wp-career-board' ), number_format_i18n( $waiting ) )
					: __( 'Jobs to review', 'wp-career-board' ),
				'href'  => $url,
			)
		);
	}
}
