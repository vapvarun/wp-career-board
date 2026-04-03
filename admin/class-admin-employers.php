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
			'cb'         => '<input type="checkbox">',
			'name'       => __( 'Name', 'wp-career-board' ),
			'company'    => __( 'Company', 'wp-career-board' ),
			'website'    => __( 'Website', 'wp-career-board' ),
			'jobs'       => __( 'Active Jobs', 'wp-career-board' ),
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
			'delete' => __( 'Delete', 'wp-career-board' ),
		);
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
		$count    = ( new \WP_User_Query(
			array(
				'role__in' => array( 'wcb_employer' ),
				'number'   => 0,
			)
		) )->get_total();
		$base_url = admin_url( 'admin.php?page=wcb-employers' );

		return array(
			'all' => sprintf(
				'<a href="%s" class="current">%s <span class="count">(%d)</span></a>',
				esc_url( $base_url ),
				esc_html__( 'All', 'wp-career-board' ),
				$count
			),
		);
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

		$query_args = array(
			'role__in' => array( 'wcb_employer' ),
			'orderby'  => $orderby,
			'order'    => $order,
			'number'   => $per_page,
			'offset'   => ( $current_page - 1 ) * $per_page,
		);

		if ( $search ) {
			// Search user fields + company name via meta.
			$company_user_ids = $this->get_user_ids_by_company_name( $search );

			$query_args['search']         = '*' . $search . '*';
			$query_args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );

			if ( ! empty( $company_user_ids ) ) {
				// Include users matched by company name too.
				$query_args['include'] = array_unique(
					array_merge(
						$this->get_matching_user_ids( $search ),
						$company_user_ids
					)
				);
				// When using include, drop the text search to avoid double-filtering.
				unset( $query_args['search'], $query_args['search_columns'] );
			}
		}

		$query       = new \WP_User_Query( $query_args );
		$this->items = $query->get_results();

		$this->set_pagination_args(
			array(
				'total_items' => $query->get_total(),
				'per_page'    => $per_page,
				'total_pages' => ceil( $query->get_total() / $per_page ),
			)
		);

		$this->_column_headers = array(
			$this->get_columns(),
			array(), // Hidden columns.
			$this->get_sortable_columns(),
			'name', // Primary column.
		);
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
		$q = new \WP_User_Query(
			array(
				'role__in'       => array( 'wcb_employer' ),
				'search'         => '*' . $search . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
				'fields'         => 'ID',
				'number'         => 9999,
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
			'view' => sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( (string) get_author_posts_url( $item->ID ) ),
				esc_html__( 'View', 'wp-career-board' )
			),
		);

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
		$count = (int) ( new \WP_Query(
			array(
				'post_type'      => 'wcb_job',
				'post_status'    => 'publish',
				'author'         => $item->ID,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		) )->found_posts;

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
