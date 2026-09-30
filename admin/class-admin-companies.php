<?php
/**
 * Admin Companies list — full WP_List_Table with search, status tabs,
 * pagination, sortable columns, row actions, and bulk trash.
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
 * WP_List_Table subclass for wcb_company posts.
 *
 * @since 1.0.0
 */
class AdminCompanies extends \WP_List_Table {

	/**
	 * Employer for each company on the current page: company ID => {id, display_name}.
	 *
	 * @since 1.8.0
	 * @var array<int,array{id:int,display_name:string}>
	 */
	private array $employers = array();

	/**
	 * Published job counts for the companies on the current page, by company ID.
	 *
	 * @since 1.8.0
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
				'singular' => __( 'company', 'wp-career-board' ),
				'plural'   => __( 'companies', 'wp-career-board' ),
				'ajax'     => false,
			)
		);
	}

	// -------------------------------------------------------------------------
	// Page entrypoint
	// -------------------------------------------------------------------------

	/**
	 * Process bulk actions, prepare items, then render the full page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render(): void {
		$this->process_bulk_action();
		$this->prepare_items();
		?>
		<div class="wrap wcb-admin wcb-companies-list">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Companies', 'wp-career-board' ); ?></h1>
			<div class="wcb-page-header">
				<div class="wcb-page-header__left">
					<h2 class="wcb-page-header__title">
						<i data-lucide="building-2"></i>
						<?php esc_html_e( 'Companies', 'wp-career-board' ); ?>
					</h2>
					<p class="wcb-page-header__desc"><?php esc_html_e( 'Manage company profiles, trust levels, and employer associations.', 'wp-career-board' ); ?></p>
				</div>
				<div class="wcb-page-header__actions">
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcb_company' ) ); ?>" class="wcb-btn wcb-btn--primary">
						<i data-lucide="plus" class="wcb-icon--sm"></i>
						<?php esc_html_e( 'Add New', 'wp-career-board' ); ?>
					</a>
				</div>
			</div>

			<?php $this->views(); ?>

			<form method="get">
				<input type="hidden" name="page" value="wcb-companies">
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$wcb_s = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
				?>
				<p class="search-box">
					<label class="screen-reader-text" for="wcb-company-search-input">
						<?php esc_html_e( 'Search Companies', 'wp-career-board' ); ?>
					</label>
					<label for="wcb-company-search-input" class="screen-reader-text"><?php esc_html_e( 'Search companies', 'wp-career-board' ); ?></label>
					<input type="search" id="wcb-company-search-input" name="s" value="<?php echo esc_attr( $wcb_s ); ?>" placeholder="<?php esc_attr_e( 'Company name…', 'wp-career-board' ); ?>">
					<?php submit_button( __( 'Search Companies', 'wp-career-board' ), '', '', false, array( 'id' => 'search-submit' ) ); ?>
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
			'cb'          => sprintf( '<input type="checkbox" aria-label="%s" />', esc_attr__( 'Select all companies', 'wp-career-board' ) ),
			'title'       => __( 'Company Name', 'wp-career-board' ),
			'employer'    => __( 'Employer', 'wp-career-board' ),
			'website'     => __( 'Website', 'wp-career-board' ),
			'jobs'        => __( 'Active Jobs', 'wp-career-board' ),
			'trust_level' => __( 'Trust Level', 'wp-career-board' ),
			'status'      => __( 'Status', 'wp-career-board' ),
			'date'        => __( 'Date', 'wp-career-board' ),
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
			'title' => array( 'title', false ),
			'date'  => array( 'date', true ),
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
			'trash' => __( 'Move to Trash', 'wp-career-board' ),
		);
	}

	// -------------------------------------------------------------------------
	// Data preparation
	// -------------------------------------------------------------------------

	/**
	 * Query companies and configure pagination.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_filter = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'date';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'DESC';
		$order = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';

		// Companies are not moderated — no 'pending' status by design.
		// Jobs (class-admin-jobs.php) include 'pending' because they go through moderation.
		$post_status = in_array( $status_filter, array( 'publish', 'draft', 'trash' ), true )
			? $status_filter
			: array( 'publish', 'draft' );

		$query_args = array(
			'post_type'      => 'wcb_company',
			'post_status'    => $post_status,
			'posts_per_page' => $per_page,
			'paged'          => $current_page,
			'orderby'        => $orderby,
			'order'          => $order,
		);

		if ( $search ) {
			$query_args['s'] = $search;
		}

		$query       = new \WP_Query( $query_args );
		$this->items = $query->posts;
		$this->prime_page( wp_list_pluck( $this->items, 'ID' ) );

		$this->set_pagination_args(
			array(
				'total_items' => $query->found_posts,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $query->found_posts / $per_page ),
			)
		);

		$this->_column_headers = array(
			$this->get_columns(),
			array(), // Hidden columns.
			$this->get_sortable_columns(),
			'title', // Primary column.
		);
	}

	/**
	 * Preload each page's employer link and published-job count in two
	 * grouped queries, replacing the get_users() + WP_Query that column_employer()
	 * and column_jobs() used to run once per row.
	 *
	 * @since 1.8.0
	 *
	 * @param int[] $company_ids Company post IDs on the current page.
	 * @return void
	 */
	private function prime_page( array $company_ids ): void {
		global $wpdb;
		$this->employers  = array();
		$this->job_counts = array();
		if ( ! $company_ids ) {
			return;
		}

		$company_ids = array_map( 'intval', $company_ids );
		$in          = implode( ',', array_fill( 0, count( $company_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- two grouped lookups for the page; both meta_value columns are indexed as part of the usermeta/postmeta composite keys.
		$meta_rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value AS company_id FROM {$wpdb->usermeta} WHERE meta_key = '_wcb_company_id' AND meta_value IN ( {$in} )", $company_ids ) );
		$job_rows  = $wpdb->get_results( $wpdb->prepare( "SELECT pm.meta_value AS company_id, COUNT(*) AS n FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_wcb_company_id' AND pm.meta_value IN ( {$in} ) AND p.post_type = 'wcb_job' AND p.post_status = 'publish' GROUP BY pm.meta_value", $company_ids ) );
		// phpcs:enable

		foreach ( (array) $job_rows as $row ) {
			$this->job_counts[ (int) $row->company_id ] = (int) $row->n;
		}

		$user_ids = wp_list_pluck( (array) $meta_rows, 'user_id' );
		if ( ! $user_ids ) {
			return;
		}
		// cache_users() primes users + usermeta in two queries, so the
		// get_edit_user_link() call in column_employer() reads from cache
		// instead of loading each employer row by row.
		$user_ids = array_map( 'intval', $user_ids );
		cache_users( $user_ids );
		$names = array();
		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$names[ $user_id ] = $user->display_name;
			}
		}
		foreach ( (array) $meta_rows as $row ) {
			$company_id = (int) $row->company_id;
			$user_id    = (int) $row->user_id;
			// A company can only have one employer link in the UI; keep the first.
			if ( isset( $names[ $user_id ] ) && ! isset( $this->employers[ $company_id ] ) ) {
				$this->employers[ $company_id ] = array(
					'id'           => $user_id,
					'display_name' => $names[ $user_id ],
				);
			}
		}
	}

	// -------------------------------------------------------------------------
	// Status tabs
	// -------------------------------------------------------------------------

	/**
	 * Build the status-filter view links shown above the table.
	 *
	 * @since 1.0.0
	 * @return array<string,string>
	 */
	protected function get_views(): array {
		$counts   = wp_count_posts( 'wcb_company' );
		$all      = ( isset( $counts->publish ) ? (int) $counts->publish : 0 )
					+ ( isset( $counts->draft ) ? (int) $counts->draft : 0 );
		$base_url = admin_url( 'admin.php?page=wcb-companies' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : '';

		$views = array();

		$views['all'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( $base_url ),
			'' === $current ? ' class="current"' : '',
			esc_html__( 'All', 'wp-career-board' ),
			$all
		);

		$statuses = array(
			'publish' => __( 'Published', 'wp-career-board' ),
			'draft'   => __( 'Draft', 'wp-career-board' ),
			'trash'   => __( 'Trash', 'wp-career-board' ),
		);

		foreach ( $statuses as $slug => $label ) {
			$count = isset( $counts->$slug ) ? (int) $counts->$slug : 0;
			if ( 0 === $count && $current !== $slug ) {
				continue;
			}
			$views[ $slug ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'post_status', $slug, $base_url ) ),
				$current === $slug ? ' class="current"' : '',
				esc_html( $label ),
				$count
			);
		}

		return $views;
	}

	// -------------------------------------------------------------------------
	// Empty state
	// -------------------------------------------------------------------------

	/**
	 * Message shown when the companies list is empty.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function no_items(): void {
		?>
		<div class="wcb-empty-state">
			<i data-lucide="building-2" class="wcb-empty-state__icon"></i>
			<p class="wcb-empty-state__title"><?php esc_html_e( 'No companies yet', 'wp-career-board' ); ?></p>
			<p class="wcb-empty-state__desc"><?php esc_html_e( 'Company profiles are created when employers complete their profile on the employer dashboard.', 'wp-career-board' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcb_company' ) ); ?>" class="wcb-btn wcb-btn--primary">
				<?php esc_html_e( 'Add Company', 'wp-career-board' ); ?>
			</a>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Column renderers
	// -------------------------------------------------------------------------

	/**
	 * Checkbox column.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $item Current row post object.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return sprintf(
			'<label class="screen-reader-text" for="cb-select-%1$d">%2$s</label><input type="checkbox" id="cb-select-%1$d" name="company[]" value="%1$d">',
			(int) $item->ID,
			esc_html__( 'Select company', 'wp-career-board' )
		);
	}

	/**
	 * Title column with row actions.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $item Current row post object.
	 * @return string
	 */
	protected function column_title( $item ): string {
		$edit_link = (string) get_edit_post_link( $item->ID );
		$view_link = (string) get_permalink( $item->ID );

		$out = sprintf(
			'<strong><a class="row-title" href="%s">%s</a></strong>',
			esc_url( $edit_link ),
			esc_html( get_the_title( $item ) )
		);

		$row_actions = array(
			'edit' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_link ),
				esc_html__( 'Edit', 'wp-career-board' )
			),
		);

		if ( 'publish' === $item->post_status ) {
			$row_actions['view'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $view_link ),
				esc_html__( 'View', 'wp-career-board' )
			);
		}

		if ( 'trash' !== $item->post_status ) {
			$trash_link = get_delete_post_link( $item->ID );
			if ( $trash_link ) {
				$row_actions['trash'] = sprintf(
					'<a href="%s" class="submitdelete">%s</a>',
					esc_url( $trash_link ),
					esc_html__( 'Trash', 'wp-career-board' )
				);
			}
		} else {
			$restore_link           = wp_nonce_url(
				admin_url( 'post.php?action=untrash&post=' . $item->ID ),
				'untrash-post_' . $item->ID
			);
			$row_actions['restore'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $restore_link ),
				esc_html__( 'Restore', 'wp-career-board' )
			);
			$delete_link            = get_delete_post_link( $item->ID, '', true );
			if ( $delete_link ) {
				$row_actions['delete'] = sprintf(
					'<a href="%s" class="submitdelete">%s</a>',
					esc_url( $delete_link ),
					esc_html__( 'Delete Permanently', 'wp-career-board' )
				);
			}
		}

		$out .= $this->row_actions( $row_actions );
		return $out;
	}

	/**
	 * Employer column — the user linked to this company via _wcb_company_id meta.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $item Current row post object.
	 * @return string
	 */
	protected function column_employer( $item ): string {
		$emp = $this->employers[ (int) $item->ID ] ?? null;

		if ( null !== $emp ) {
			return sprintf(
				'<a href="%s">%s</a>',
				esc_url( (string) get_edit_user_link( $emp['id'] ) ),
				esc_html( $emp['display_name'] )
			);
		}

		return '—';
	}

	/**
	 * Website column.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $item Current row post object.
	 * @return string
	 */
	protected function column_website( $item ): string {
		$website = (string) get_post_meta( $item->ID, '_wcb_website', true );

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
	 * Active jobs column — count of published wcb_job posts linked to this company.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $item Current row post object.
	 * @return string
	 */
	protected function column_jobs( $item ): string {
		return (string) ( $this->job_counts[ (int) $item->ID ] ?? 0 );
	}

	/**
	 * Status column — coloured badge.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $item Current row post object.
	 * @return string
	 */
	protected function column_status( $item ): string {
		$labels = array(
			'publish' => __( 'Published', 'wp-career-board' ),
			'draft'   => __( 'Draft', 'wp-career-board' ),
			'trash'   => __( 'Trash', 'wp-career-board' ),
		);
		$status = $item->post_status;
		$label  = $labels[ $status ] ?? ucfirst( $status );

		$badge_map = array(
			'publish' => 'success',
			'draft'   => 'default',
			'trash'   => 'danger',
		);
		$badge_var = $badge_map[ $status ] ?? 'default';

		return sprintf(
			'<span class="wcb-badge wcb-badge--%s">%s</span>',
			esc_attr( $badge_var ),
			esc_html( $label )
		);
	}

	/**
	 * Trust level column — inline select updated via JS/REST.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $item Current row post object.
	 * @return string
	 */
	protected function column_trust_level( $item ): string {
		$current = (string) get_post_meta( $item->ID, '_wcb_trust_level', true );
		$options = array(
			''         => __( ' -  None  - ', 'wp-career-board' ),
			'verified' => __( 'Verified', 'wp-career-board' ),
			'trusted'  => __( 'Trusted', 'wp-career-board' ),
			'premium'  => __( 'Premium', 'wp-career-board' ),
		);

		$select = sprintf(
			'<select class="wcb-trust-select" data-company-id="%1$d" aria-label="%2$s">',
			(int) $item->ID,
			esc_attr__( 'Change trust level', 'wp-career-board' )
		);
		foreach ( $options as $value => $label ) {
			$select .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		$select .= '</select>';
		return $select;
	}

	/**
	 * Date column.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $item Current row post object.
	 * @return string
	 */
	protected function column_date( $item ): string {
		return esc_html( get_the_date( 'Y-m-d', $item ) );
	}

	/**
	 * Default column fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $item        Current row post object.
	 * @param string   $column_name Column slug.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		return '';
	}

	// -------------------------------------------------------------------------
	// Bulk action handler
	// -------------------------------------------------------------------------

	/**
	 * Handle bulk trash action.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function process_bulk_action(): void {
		$action = $this->current_action();
		if ( ! $action ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bulk-companies' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$company_ids = isset( $_GET['company'] ) ? array_map( 'intval', (array) $_GET['company'] ) : array();
		if ( empty( $company_ids ) ) {
			return;
		}

		foreach ( $company_ids as $company_id ) {
			if ( ! current_user_can( 'edit_post', $company_id ) ) {
				continue;
			}
			if ( 'trash' === $action ) {
				wp_trash_post( $company_id );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wcb-companies' ) );
		exit;
	}
}
