<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated admin class name follows project autoloader convention.
/**
 * Admin Jobs list — full WP_List_Table with search, status tabs, pagination,
 * sortable columns, row actions, and bulk approve/trash.
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
 * WP_List_Table subclass for wcb_job posts.
 *
 * Registered as the `wcb-jobs` submenu callback via render().
 *
 * @since 1.0.0
 */
class AdminJobs extends \WP_List_Table {

	/**
	 * Constructor — configure singular/plural labels.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'job', 'wp-career-board' ),
				'plural'   => __( 'jobs', 'wp-career-board' ),
				'ajax'     => false,
			)
		);
	}

	// -------------------------------------------------------------------------
	// Page entrypoint
	// -------------------------------------------------------------------------

	/**
	 * Called by the admin menu callback — processes bulk actions, prepares
	 * items, then renders the full page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render(): void {
		$this->process_bulk_action();
		$this->prepare_items();
		?>
		<div class="wrap wcb-admin wcb-jobs-list">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Jobs', 'wp-career-board' ); ?></h1>
			<div class="wcb-page-header">
				<div class="wcb-page-header__left">
					<h2 class="wcb-page-header__title">
						<i data-lucide="briefcase"></i>
						<?php esc_html_e( 'Jobs', 'wp-career-board' ); ?>
					</h2>
					<p class="wcb-page-header__desc"><?php esc_html_e( 'Manage job listings, approve pending submissions, and track active postings.', 'wp-career-board' ); ?></p>
				</div>
				<div class="wcb-page-header__actions">
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcb_job' ) ); ?>" class="wcb-btn wcb-btn--primary">
						<i data-lucide="plus" class="wcb-icon--sm"></i>
						<?php esc_html_e( 'Add New', 'wp-career-board' ); ?>
					</a>
				</div>
			</div>

			<?php $this->views(); ?>

			<form method="get">
				<input type="hidden" name="page" value="wcb-jobs">
				<label for="wcb-job-search-input" class="screen-reader-text"><?php esc_html_e( 'Search jobs', 'wp-career-board' ); ?></label>
				<?php $this->search_box( __( 'Search Jobs', 'wp-career-board' ), 'wcb-job' ); ?>
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
			'cb'      => sprintf( '<input type="checkbox" aria-label="%s" />', esc_attr__( 'Select all jobs', 'wp-career-board' ) ),
			'title'   => __( 'Title', 'wp-career-board' ),
			'status'  => __( 'Status', 'wp-career-board' ),
			'company' => __( 'Company', 'wp-career-board' ),
			'author'  => __( 'Author', 'wp-career-board' ),
			'date'    => __( 'Date', 'wp-career-board' ),
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
			'approve' => __( 'Approve', 'wp-career-board' ),
			'trash'   => __( 'Move to Trash', 'wp-career-board' ),
		);
	}

	// -------------------------------------------------------------------------
	// Data preparation
	// -------------------------------------------------------------------------

	/**
	 * Query jobs and configure pagination.
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

		// Jobs go through moderation, so 'pending' is a valid status here.
		// Companies (class-admin-companies.php) don't moderate, so their list omits 'pending' by design.
		$post_status = in_array( $status_filter, array( 'publish', 'pending', 'draft', 'trash' ), true )
			? $status_filter
			: array( 'publish', 'pending', 'draft' );

		$query_args = array(
			'post_type'      => 'wcb_job',
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
		$counts   = wp_count_posts( 'wcb_job' );
		$all      = ( isset( $counts->publish ) ? (int) $counts->publish : 0 )
					+ ( isset( $counts->pending ) ? (int) $counts->pending : 0 )
					+ ( isset( $counts->draft ) ? (int) $counts->draft : 0 );
		$base_url = admin_url( 'admin.php?page=wcb-jobs' );

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
			'pending' => __( 'Pending Review', 'wp-career-board' ),
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
	 * Message shown when the jobs list is empty.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function no_items(): void {
		?>
		<div class="wcb-empty-state">
			<i data-lucide="briefcase" class="wcb-empty-state__icon"></i>
			<p class="wcb-empty-state__title"><?php esc_html_e( 'No jobs found', 'wp-career-board' ); ?></p>
			<p class="wcb-empty-state__desc"><?php esc_html_e( 'Post your first job listing or adjust the filters above.', 'wp-career-board' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcb_job' ) ); ?>" class="wcb-btn wcb-btn--primary">
				<?php esc_html_e( 'Add New Job', 'wp-career-board' ); ?>
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
			'<label class="screen-reader-text" for="cb-select-%1$d">%2$s</label><input type="checkbox" id="cb-select-%1$d" name="job[]" value="%1$d">',
			(int) $item->ID,
			esc_html__( 'Select job', 'wp-career-board' )
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

		if ( 'pending' === $item->post_status ) {
			$row_actions['wcb_approve'] = sprintf(
				'<button type="button" class="button-link wcb-approve-job" data-job-id="%d">%s</button>',
				(int) $item->ID,
				esc_html__( 'Approve', 'wp-career-board' )
			);
			$row_actions['wcb_reject']  = sprintf(
				'<button type="button" class="button-link wcb-reject-job" data-job-id="%d">%s</button>',
				(int) $item->ID,
				esc_html__( 'Reject', 'wp-career-board' )
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
			'pending' => __( 'Pending', 'wp-career-board' ),
			'draft'   => __( 'Draft', 'wp-career-board' ),
			'trash'   => __( 'Trash', 'wp-career-board' ),
		);
		$status = $item->post_status;
		$label  = $labels[ $status ] ?? ucfirst( $status );

		$badge_map = array(
			'publish' => 'success',
			'pending' => 'warn',
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
	 * Company column — reads `_wcb_company_name` postmeta.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $item Current row post object.
	 * @return string
	 */
	protected function column_company( $item ): string {
		$name = (string) get_post_meta( $item->ID, '_wcb_company_name', true );
		return $name ? esc_html( $name ) : '—';
	}

	/**
	 * Author column.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $item Current row post object.
	 * @return string
	 */
	protected function column_author( $item ): string {
		return esc_html( (string) get_the_author_meta( 'display_name', (int) $item->post_author ) );
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
	 * Handle bulk approve and trash actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function process_bulk_action(): void {
		$action = $this->current_action();
		if ( ! $action ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bulk-jobs' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$job_ids = isset( $_GET['job'] ) ? array_map( 'intval', (array) $_GET['job'] ) : array();
		if ( empty( $job_ids ) ) {
			return;
		}

		foreach ( $job_ids as $job_id ) {
			if ( ! current_user_can( 'edit_post', $job_id ) ) {
				continue;
			}
			if ( 'approve' === $action ) {
				// wcb_job_approved fires via EmailJobApproved::on_status_transition()
				// on the transition_post_status hook triggered by wp_update_post().
				wp_update_post(
					array(
						'ID'          => $job_id,
						'post_status' => 'publish',
					)
				);
			} elseif ( 'trash' === $action ) {
				wp_trash_post( $job_id );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wcb-jobs' ) );
		exit;
	}
}
