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
		<div class="wrap wcb-companies-list">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Companies', 'wp-career-board' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcb_company' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'wp-career-board' ); ?>
			</a>
			<hr class="wp-header-end">

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
			'cb'       => '<input type="checkbox">',
			'title'    => __( 'Company Name', 'wp-career-board' ),
			'employer' => __( 'Employer', 'wp-career-board' ),
			'website'  => __( 'Website', 'wp-career-board' ),
			'jobs'     => __( 'Active Jobs', 'wp-career-board' ),
			'status'   => __( 'Status', 'wp-career-board' ),
			'date'     => __( 'Date', 'wp-career-board' ),
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

		$this->set_pagination_args(
			array(
				'total_items' => $query->found_posts,
				'per_page'    => $per_page,
				'total_pages' => ceil( $query->found_posts / $per_page ),
			)
		);

		$this->_column_headers = array(
			$this->get_columns(),
			array(), // Hidden columns.
			$this->get_sortable_columns(),
			'title', // Primary column.
		);
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
		esc_html_e( 'No companies found.', 'wp-career-board' );
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
		$users = get_users(
			array(
				'meta_key'   => '_wcb_company_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $item->ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
				'fields'     => array( 'ID', 'display_name' ),
			)
		);

		if ( ! empty( $users ) ) {
			$emp = $users[0];
			return sprintf(
				'<a href="%s">%s</a>',
				esc_url( (string) get_edit_user_link( $emp->ID ) ),
				esc_html( $emp->display_name )
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
		$count = (int) ( new \WP_Query(
			array(
				'post_type'      => 'wcb_job',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_wcb_company_name',
						'value' => get_the_title( $item ),
					),
				),
			)
		) )->found_posts;

		return (string) $count;
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

		return sprintf(
			'<span class="wcb-status-badge wcb-job-status-%s">%s</span>',
			esc_attr( $status ),
			esc_html( $label )
		);
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
