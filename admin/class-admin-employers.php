<?php
/**
 * Admin Employers list — full WP_List_Table with search, pagination,
 * sortable columns, and row actions.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table subclass for employer users.
 *
 * @since 1.0.0
 */
class AdminEmployers extends \WP_List_Table {

	use ReportedMembers;

	/**
	 * Memoized employer user-ID set (role holders ∪ job authors).
	 *
	 * @since 1.7.0
	 * @var int[]|null
	 */
	private ?array $employer_ids_cache = null;

	/**
	 * Live job counts for the employers on the current page, by user ID.
	 *
	 * @var array<int,int>
	 */
	private array $job_counts = array();

	/**
	 * Constructor — configure singular/plural labels.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'employer', 'wp-career-board' ),
				'plural'   => __( 'employers', 'wp-career-board' ),
				'ajax'     => false,
			)
		);
	}

	// -------------------------------------------------------------------------
	// Page entrypoint
	// -------------------------------------------------------------------------

	/**
	 * Prepare items then render the full page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render(): void {
		$this->prepare_items();
		?>
		<div class="wrap wcb-admin wcb-employers-list">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Employers', 'wp-career-board' ); ?></h1>
			<div class="wcb-page-header">
				<div class="wcb-page-header__left">
					<h2 class="wcb-page-header__title">
						<i data-lucide="briefcase-business"></i>
						<?php esc_html_e( 'Employers', 'wp-career-board' ); ?>
					</h2>
					<p class="wcb-page-header__desc"><?php esc_html_e( 'View employer accounts, their companies, active job listings, and activity.', 'wp-career-board' ); ?></p>
				</div>
				<div class="wcb-page-header__actions">
					<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="wcb-btn wcb-btn--primary">
						<i data-lucide="user-plus" class="wcb-icon--sm"></i>
						<?php esc_html_e( 'Add New', 'wp-career-board' ); ?>
					</a>
				</div>
			</div>

			<?php $this->views(); ?>

			<form method="get">
				<input type="hidden" name="page" value="wcb-employers">
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$wcb_s = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
				?>
				<p class="search-box">
					<label class="screen-reader-text" for="wcb-employer-search-input">
						<?php esc_html_e( 'Search Employers', 'wp-career-board' ); ?>
					</label>
					<label for="wcb-employer-search-input" class="screen-reader-text"><?php esc_html_e( 'Search employers', 'wp-career-board' ); ?></label>
					<input type="search" id="wcb-employer-search-input" name="s" value="<?php echo esc_attr( $wcb_s ); ?>" placeholder="<?php esc_attr_e( 'Name, email, or company…', 'wp-career-board' ); ?>">
					<?php submit_button( __( 'Search Employers', 'wp-career-board' ), '', '', false, array( 'id' => 'search-submit' ) ); ?>
				</p>
				<?php $this->display(); ?>
			</form>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Column definitions
	// -------------------------------------------------------------------------

	/**
	 * Define all visible columns.
	 *
	 * @since 1.0.0
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'cb'         => sprintf( '<input type="checkbox" aria-label="%s" />', esc_attr__( 'Select all employers', 'wp-career-board' ) ),
			'name'       => __( 'Name', 'wp-career-board' ),
			'company'    => __( 'Company', 'wp-career-board' ),
			'website'    => __( 'Website', 'wp-career-board' ),
			'jobs'       => __( 'Active Jobs', 'wp-career-board' ),
			'status'     => __( 'Status', 'wp-career-board' ),
			'registered' => __( 'Registered', 'wp-career-board' ),
		);
	}

	/**
	 * Define sortable columns.
	 *
	 * @since 1.0.0
	 * @return array<string,array<int,mixed>>
	 */
	protected function get_sortable_columns(): array {
		return array(
			'name'       => array( 'display_name', false ),
			'registered' => array( 'registered', true ),
		);
	}

	/**
	 * Return available bulk actions.
	 *
	 * @since 1.0.0
	 * @return array<string,string>
	 */
	protected function get_bulk_actions(): array {
		return array(
			'ban'   => __( 'Ban', 'wp-career-board' ),
			'unban' => __( 'Unban', 'wp-career-board' ),
		);
	}

	/**
	 * Process ban / unban bulk + row actions.
	 *
	 * Wired to `load-{employers page}` in class-admin.php. Writes the
	 * `_wcb_employer_banned` user-meta that core/class-abilities.php reads to
	 * strip every WCB ability from a banned employer — the write side that was
	 * missing (the gate read a flag no admin action ever set).
	 *
	 * @since 1.4.2
	 * @return void
	 */
	public function process_bulk_action(): void {
		$action = $this->current_action();
		if ( ! in_array( $action, array( 'ban', 'unban', 'resolve_flags' ), true ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bulk-employers' ) ) {
			return;
		}

		if ( ! wp_is_ability_granted( 'wcb/manage-settings' ) ) { // phpcs:ignore -- ability polyfill, see core/abilities-api-polyfill.php
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$user_ids = isset( $_GET['user'] ) ? array_map( 'intval', (array) $_GET['user'] ) : array();
		$current  = get_current_user_id();

		foreach ( $user_ids as $user_id ) {
			if ( $user_id <= 0 || $user_id === $current ) {
				continue; // Never let an admin ban themselves.
			}
			if ( 'resolve_flags' === $action ) {
				\WCB\Modules\Moderation\ModerationModule::resolve_member_flags( $user_id );
			} elseif ( 'ban' === $action ) {
				// Hides their jobs and company page (HiddenContent, on the flag).
				update_user_meta( $user_id, '_wcb_employer_banned', '1' );
				do_action( 'wcb_employer_banned', $user_id );
			} else {
				delete_user_meta( $user_id, '_wcb_employer_banned' );
				do_action( 'wcb_employer_unbanned', $user_id );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wcb-employers' ) );
		exit;
	}

	/**
	 * Status column — flags a banned employer.
	 *
	 * @since 1.4.2
	 *
	 * @param \WP_User $item Current row user object.
	 * @return string
	 */
	protected function column_status( $item ): string {
		$banned = '1' === (string) get_user_meta( $item->ID, '_wcb_employer_banned', true );
		return sprintf(
			'<span class="wcb-badge wcb-badge--%s">%s</span>',
			$banned ? 'danger' : 'success',
			$banned ? esc_html__( 'Banned', 'wp-career-board' ) : esc_html__( 'Active', 'wp-career-board' )
		) . $this->reports_badge( $item->ID );
	}

	// -------------------------------------------------------------------------
	// Empty state
	// -------------------------------------------------------------------------

	/**
	 * Message shown when the employers list is empty.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function no_items(): void {
		?>
		<div class="wcb-empty-state">
			<i data-lucide="briefcase-business" class="wcb-empty-state__icon"></i>
			<p class="wcb-empty-state__title"><?php esc_html_e( 'No employers yet', 'wp-career-board' ); ?></p>
			<p class="wcb-empty-state__desc"><?php esc_html_e( 'Employers are users with the Employer role. Invite them via the Add New button above or let them self-register from the frontend.', 'wp-career-board' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="wcb-btn wcb-btn--primary">
				<?php esc_html_e( 'Add Employer', 'wp-career-board' ); ?>
			</a>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Count view
	// -------------------------------------------------------------------------

	/**
	 * Build the view links shown above the table.
	 *
	 * @since 1.0.0
	 * @return array<string,string>
	 */
	protected function get_views(): array {
		$ids = $this->employer_user_ids();
		return $ids ? $this->member_views( 'wcb-employers', count( $ids ), array( 'include' => $ids ) ) : array();
	}

	// -------------------------------------------------------------------------
	// Data preparation
	// -------------------------------------------------------------------------

	/**
	 * Query employer users and configure pagination.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'registered';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'DESC';
		$order = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';

		// The employer set is role holders ∪ job authors (see employer_user_ids).
		$employer_ids = $this->employer_user_ids();

		if ( $search ) {
			// Narrow the employer set to those matching the term by user field
			// or company name. Intersect so a company/name match on a NON-
			// employer never surfaces here.
			$matched      = array_unique(
				array_merge(
					$this->get_matching_user_ids( $search ),
					$this->get_user_ids_by_company_name( $search )
				)
			);
			$employer_ids = array_values( array_intersect( $employer_ids, $matched ) );
		}

		$this->_column_headers = array(
			$this->get_columns(),
			array(), // Hidden columns.
			$this->get_sortable_columns(),
			'name', // Primary column.
		);

		if ( empty( $employer_ids ) ) {
			// An empty `include` makes WP_User_Query return ALL users — guard it.
			$this->items = array();
			$this->set_pagination_args(
				array(
					'total_items' => 0,
					'per_page'    => $per_page,
					'total_pages' => 0,
				)
			);
			return;
		}

		$query_args = array(
			'include' => $employer_ids,
			'orderby' => $orderby,
			'order'   => $order,
			'number'  => $per_page,
			'offset'  => ( $current_page - 1 ) * $per_page,
		);
		if ( $this->reported_active() ) {
			$query_args['meta_query'] = $this->reported_meta_query(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
		$query       = new \WP_User_Query( $query_args );
		$this->items = $query->get_results();
		$this->prime_page( wp_list_pluck( $this->items, 'ID' ) );

		$this->set_pagination_args(
			array(
				'total_items' => $query->get_total(),
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $query->get_total() / $per_page ),
			)
		);
	}

	/**
	 * Live job counts and company posts for one page of employers, in two queries
	 * instead of a query per row.
	 *
	 * @param int[] $user_ids Employers on this page.
	 * @return void
	 */
	private function prime_page( array $user_ids ): void {
		global $wpdb;
		$this->job_counts = array();
		if ( ! $user_ids ) {
			return;
		}

		$user_ids = array_map( 'intval', $user_ids );
		$in       = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one grouped count for the page; post_author is indexed.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_author, COUNT(*) AS n FROM {$wpdb->posts} WHERE post_type = 'wcb_job' AND post_status = 'publish' AND post_author IN ( {$in} ) GROUP BY post_author", $user_ids ) );
		// phpcs:enable
		foreach ( (array) $rows as $row ) {
			$this->job_counts[ (int) $row->post_author ] = (int) $row->n;
		}

		$company_ids = array_filter( array_map( static fn ( int $id ): int => (int) get_user_meta( $id, '_wcb_company_id', true ), $user_ids ) );
		if ( $company_ids ) {
			_prime_post_caches( array_unique( $company_ids ), false, true );
		}
	}

	/**
	 * Every user who is functionally an employer on this board: anyone holding
	 * the wcb_employer role PLUS anyone who has authored a job.
	 *
	 * Job posting is capability-gated, and administrators hold the posting cap
	 * too, so a job author need not carry the employer role — but they ARE
	 * running listings (the frontend attributes those jobs to them), so the
	 * site owner must see them here. Keying the screen on `role__in` alone hid
	 * every admin/non-role poster, which read as "No employers yet" on boards
	 * whose listings were all posted by admins. Memoized per request.
	 *
	 * @since 1.7.0
	 * @return int[]
	 */
	private function employer_user_ids(): array {
		if ( null !== $this->employer_ids_cache ) {
			return $this->employer_ids_cache;
		}

		$role_ids = ( new \WP_User_Query(
			array(
				'role__in' => array( 'wcb_employer' ),
				'fields'   => 'ID',
				'number'   => 0,
			)
		) )->get_results();

		global $wpdb;
		// Distinct authors of real (non-trashed) job posts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$author_ids = $wpdb->get_col(
			"SELECT DISTINCT post_author FROM {$wpdb->posts}
			 WHERE post_type = 'wcb_job' AND post_author > 0
			   AND post_status NOT IN ( 'trash', 'auto-draft' )"
		);

		$this->employer_ids_cache = array_values(
			array_unique(
				array_map( 'intval', array_merge( (array) $role_ids, (array) $author_ids ) )
			)
		);

		return $this->employer_ids_cache;
	}

	/**
	 * Return employer user IDs whose company name matches the search term.
	 *
	 * @since 1.0.0
	 *
	 * @param string $search Search term.
	 * @return int[]
	 */
	private function get_user_ids_by_company_name( string $search ): array {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( $search ) . '%';

		// Find wcb_company post IDs matching the name.
		$company_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wcb_company' AND post_title LIKE %s AND post_status != 'trash'",
				$like
			)
		);

		if ( empty( $company_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $company_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta}
				WHERE meta_key = '_wcb_company_id' AND meta_value IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$company_ids
			)
		);
		// phpcs:enable

		return array_map( 'intval', $user_ids );
	}

	/**
	 * Return employer user IDs matching the search term via WP_User_Query.
	 *
	 * @since 1.0.0
	 *
	 * @param string $search Search term.
	 * @return int[]
	 */
	private function get_matching_user_ids( string $search ): array {
		// Not role-scoped: an employer may be a job author without the role.
		// The caller intersects the result with the employer set.
		$employer_ids = $this->employer_user_ids();
		if ( ! $employer_ids ) {
			return array(); // An empty `include` would match every user.
		}
		$q = new \WP_User_Query(
			array(
				'search'         => '*' . $search . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
				'fields'         => 'ID',
				'include'        => $employer_ids,
			)
		);
		return array_map( 'intval', $q->get_results() );
	}

	// -------------------------------------------------------------------------
	// Column renderers
	// -------------------------------------------------------------------------

	/**
	 * Checkbox column.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $item Current row user object.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return sprintf(
			'<label class="screen-reader-text" for="cb-select-%1$d">%2$s</label><input type="checkbox" id="cb-select-%1$d" name="user[]" value="%1$d">',
			(int) $item->ID,
			esc_html__( 'Select employer', 'wp-career-board' )
		);
	}

	/**
	 * Name column — display name, email, and row actions.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $item Current row user object.
	 * @return string
	 */
	protected function column_name( $item ): string {
		$out = sprintf(
			'<strong><a class="row-title" href="%s">%s</a></strong><br><small>%s</small>',
			esc_url( (string) get_edit_user_link( $item->ID ) ),
			esc_html( $item->display_name ),
			esc_html( $item->user_email )
		);

		$row_actions = array(
			'edit' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( (string) get_edit_user_link( $item->ID ) ),
				esc_html__( 'Edit', 'wp-career-board' )
			),
		);
		$company_id  = (int) get_user_meta( $item->ID, '_wcb_company_id', true );
		if ( $company_id > 0 && 'wcb_company' === get_post_type( $company_id ) ) {
			$row_actions['view'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( (string) get_permalink( $company_id ) ),
				esc_html__( 'View company', 'wp-career-board' )
			);
		}
		$row_actions += $this->dismiss_reports_action( $item->ID, 'wcb-employers', 'bulk-employers' );

		if ( $item->ID !== get_current_user_id() ) {
			$is_banned              = '1' === (string) get_user_meta( $item->ID, '_wcb_employer_banned', true );
			$toggle                 = $is_banned ? 'unban' : 'ban';
			$toggle_link            = wp_nonce_url(
				add_query_arg(
					array(
						'page'   => 'wcb-employers',
						'action' => $toggle,
						'user'   => array( $item->ID ),
					),
					admin_url( 'admin.php' )
				),
				'bulk-employers'
			);
			$row_actions[ $toggle ] = sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( $toggle_link ),
				$is_banned ? '' : ' class="wcb-row-action--danger"',
				$is_banned ? esc_html__( 'Unban', 'wp-career-board' ) : esc_html__( 'Ban', 'wp-career-board' )
			);
		}

		$out .= $this->row_actions( $row_actions );
		return $out;
	}

	/**
	 * Company column — name linked to company edit page.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $item Current row user object.
	 * @return string
	 */
	protected function column_company( $item ): string {
		$company_id  = (int) get_user_meta( $item->ID, '_wcb_company_id', true );
		$company_obj = $company_id ? get_post( $company_id ) : null;

		if ( $company_obj instanceof \WP_Post ) {
			return sprintf(
				'<a href="%s">%s</a>',
				esc_url( (string) get_edit_post_link( $company_id ) ),
				esc_html( $company_obj->post_title )
			);
		}

		return '—';
	}

	/**
	 * Website column.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $item Current row user object.
	 * @return string
	 */
	protected function column_website( $item ): string {
		$company_id = (int) get_user_meta( $item->ID, '_wcb_company_id', true );
		$website    = $company_id ? (string) get_post_meta( $company_id, '_wcb_website', true ) : '';

		if ( $website ) {
			return sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $website ),
				esc_html( wp_parse_url( $website, PHP_URL_HOST ) ? wp_parse_url( $website, PHP_URL_HOST ) : $website )
			);
		}

		return '—';
	}

	/**
	 * Active jobs column — count of published wcb_job posts by this employer.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $item Current row user object.
	 * @return string
	 */
	protected function column_jobs( $item ): string {
		$count = $this->job_counts[ (int) $item->ID ] ?? 0;

		if ( $count > 0 ) {
			return sprintf(
				'<a href="%s">%d</a>',
				esc_url(
					add_query_arg(
						array(
							'page'   => 'wcb-jobs',
							'author' => $item->ID,
						),
						admin_url( 'admin.php' )
					)
				),
				$count
			);
		}

		return '0';
	}

	/**
	 * Registered date column.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $item Current row user object.
	 * @return string
	 */
	protected function column_registered( $item ): string {
		return esc_html( gmdate( 'Y-m-d', (int) strtotime( $item->user_registered ) ) );
	}

	/**
	 * Default column fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $item        Current row user object.
	 * @param string   $column_name Column slug.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		return '';
	}
}
