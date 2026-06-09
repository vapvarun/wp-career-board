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
		$this->process_flag_action();
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
					<?php if ( wp_is_ability_granted( 'wcb/post-jobs' ) ) : // phpcs:ignore -- ability polyfill, see core/abilities-api-polyfill.php ?>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcb_job' ) ); ?>" class="wcb-btn wcb-btn--primary">
							<i data-lucide="plus" class="wcb-icon--sm"></i>
							<?php esc_html_e( 'Add New', 'wp-career-board' ); ?>
						</a>
					<?php endif; ?>
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
			'flags'   => __( 'Flags', 'wp-career-board' ),
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
		$actions = array(
			'approve' => __( 'Approve', 'wp-career-board' ),
		);
		// Moderators can clear report flags — same approve/reject contract.
		if ( wp_is_ability_granted( 'wcb/moderate-jobs' ) ) { // phpcs:ignore -- ability polyfill, see core/abilities-api-polyfill.php
			$actions['resolve_flag'] = __( 'Dismiss flags', 'wp-career-board' );
		}
		// Trash is destructive and only admins should reach it. Board
		// Moderators see Approve only — their contract is approve/reject,
		// not deletion.
		if ( wp_is_ability_granted( 'wcb/manage-settings' ) ) { // phpcs:ignore -- ability polyfill, see core/abilities-api-polyfill.php
			$actions['trash'] = __( 'Move to Trash', 'wp-career-board' );
		}
		return $actions;
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
		// wcb_expired (cron-expired) and wcb_closed (employer-closed) are
		// surfaced here so admins can audit and republish past-deadline listings
		// without forcing the employer to close-then-reopen.
		$allowed_statuses = array( 'publish', 'pending', 'draft', 'wcb_expired', 'wcb_closed', 'trash' );
		$post_status      = in_array( $status_filter, $allowed_statuses, true )
			? $status_filter
			: array( 'publish', 'pending', 'draft', 'wcb_expired', 'wcb_closed' );

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

		// "Flagged" view — jobs with open report flags, regardless of status.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['wcb_flag'] ) && 'open' === sanitize_key( wp_unslash( $_GET['wcb_flag'] ) ) ) {
			$query_args['post_status'] = $allowed_statuses;
			$query_args['meta_key']    = '_wcb_flag_status'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query_args['meta_value']  = 'open'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}

		$query       = new \WP_Query( $query_args );
		$this->items = $query->posts;

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
					+ ( isset( $counts->draft ) ? (int) $counts->draft : 0 )
					+ ( isset( $counts->wcb_expired ) ? (int) $counts->wcb_expired : 0 )
					+ ( isset( $counts->wcb_closed ) ? (int) $counts->wcb_closed : 0 );
		$base_url = admin_url( 'admin.php?page=wcb-jobs' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$flag_active = isset( $_GET['wcb_flag'] ) && 'open' === sanitize_key( wp_unslash( $_GET['wcb_flag'] ) );

		$views = array();

		$views['all'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( $base_url ),
			'' === $current && ! $flag_active ? ' class="current"' : '',
			esc_html__( 'All', 'wp-career-board' ),
			$all
		);

		$statuses = array(
			'publish'     => __( 'Published', 'wp-career-board' ),
			'pending'     => __( 'Pending Review', 'wp-career-board' ),
			'draft'       => __( 'Draft', 'wp-career-board' ),
			'wcb_expired' => __( 'Expired', 'wp-career-board' ),
			'wcb_closed'  => __( 'Closed', 'wp-career-board' ),
			'trash'       => __( 'Trash', 'wp-career-board' ),
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

		// "Flagged" view — count jobs with an open report flag. Shown only when
		// there are flagged jobs or the filter is already active.
		$flagged_query = new \WP_Query(
			array(
				'post_type'      => 'wcb_job',
				'post_status'    => array( 'publish', 'pending', 'draft', 'wcb_expired', 'wcb_closed' ),
				'meta_key'       => '_wcb_flag_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'open', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		$flagged_count = (int) $flagged_query->found_posts;
		if ( $flagged_count > 0 || $flag_active ) {
			$views['flagged'] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'wcb_flag', 'open', $base_url ) ),
				$flag_active ? ' class="current"' : '',
				esc_html__( 'Flagged', 'wp-career-board' ),
				$flagged_count
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
		// Board Moderators can read the queue but can't open the post-edit
		// screen (no edit_posts cap), so don't surface the Edit affordance
		// to them — clicking it would land on a "you don't have permission"
		// screen. Admins keep both the title link and the row action.
		$can_edit  = current_user_can( 'wcb_manage_settings' ) && current_user_can( 'edit_post', $item->ID );
		$edit_link = $can_edit ? (string) get_edit_post_link( $item->ID ) : '';
		$view_link = (string) get_permalink( $item->ID );

		$out = $can_edit
			? sprintf(
				'<strong><a class="row-title" href="%s">%s</a></strong>',
				esc_url( $edit_link ),
				esc_html( get_the_title( $item ) )
			)
			: sprintf( '<strong>%s</strong>', esc_html( get_the_title( $item ) ) );

		$row_actions = array();
		if ( $can_edit ) {
			$row_actions['edit'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_link ),
				esc_html__( 'Edit', 'wp-career-board' )
			);
		}

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

		// Resolve-flag actions appear on jobs with open report flags, for
		// users holding the moderation ability. Both reuse
		// ModerationModule::resolve_job_flags() via process_flag_action().
		if ( 'open' === (string) get_post_meta( $item->ID, '_wcb_flag_status', true )
			&& wp_is_ability_granted( 'wcb/moderate-jobs' ) ) { // phpcs:ignore -- ability polyfill, see core/abilities-api-polyfill.php
			$dismiss_link               = wp_nonce_url(
				add_query_arg(
					array(
						'page'             => 'wcb-jobs',
						'wcb_resolve_flag' => 'dismiss',
						'job'              => $item->ID,
					),
					admin_url( 'admin.php' )
				),
				'wcb_resolve_flag_' . $item->ID
			);
			$row_actions['wcb_dismiss'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $dismiss_link ),
				esc_html__( 'Dismiss flag', 'wp-career-board' )
			);

			$unpublish_link               = wp_nonce_url(
				add_query_arg(
					array(
						'page'             => 'wcb-jobs',
						'wcb_resolve_flag' => 'unpublish',
						'job'              => $item->ID,
					),
					admin_url( 'admin.php' )
				),
				'wcb_resolve_flag_' . $item->ID
			);
			$row_actions['wcb_unpublish'] = sprintf(
				'<a href="%s" class="submitdelete">%s</a>',
				esc_url( $unpublish_link ),
				esc_html__( 'Unpublish', 'wp-career-board' )
			);
		}

		// Trash / restore / delete row actions stay admin-only. A Board
		// Moderator's contract is approve/reject — they don't get to clear
		// pending submissions or recover trashed ones.
		if ( wp_is_ability_granted( 'wcb/manage-settings' ) ) { // phpcs:ignore -- ability polyfill, see core/abilities-api-polyfill.php
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
			'publish'     => __( 'Published', 'wp-career-board' ),
			'pending'     => __( 'Pending', 'wp-career-board' ),
			'draft'       => __( 'Draft', 'wp-career-board' ),
			'wcb_expired' => __( 'Expired', 'wp-career-board' ),
			'wcb_closed'  => __( 'Closed', 'wp-career-board' ),
			'trash'       => __( 'Trash', 'wp-career-board' ),
		);
		$status = $item->post_status;
		$label  = $labels[ $status ] ?? ucfirst( $status );

		$badge_map = array(
			'publish'     => 'success',
			'pending'     => 'warn',
			'draft'       => 'default',
			'wcb_expired' => 'warn',
			'wcb_closed'  => 'default',
			'trash'       => 'danger',
		);
		$badge_var = $badge_map[ $status ] ?? 'default';

		return sprintf(
			'<span class="wcb-badge wcb-badge--%s">%s</span>',
			esc_attr( $badge_var ),
			esc_html( $label )
		);
	}

	/**
	 * Flags column — open report count with the top reason as a tooltip.
	 *
	 * @since 1.2.1
	 *
	 * @param \WP_Post $item Current row post object.
	 * @return string
	 */
	protected function column_flags( $item ): string {
		if ( 'open' !== (string) get_post_meta( $item->ID, '_wcb_flag_status', true ) ) {
			return '—';
		}

		$count = (int) get_post_meta( $item->ID, '_wcb_flag_count', true );
		if ( $count < 1 ) {
			return '—';
		}

		$reasons = get_post_meta( $item->ID, '_wcb_flag_reasons', true );
		$top     = '';
		if ( is_array( $reasons ) && $reasons ) {
			arsort( $reasons );
			$labels = \WCB\Modules\Moderation\ModerationModule::report_reasons();
			$top    = $labels[ (string) array_key_first( $reasons ) ] ?? '';
		}

		return sprintf(
			'<span class="wcb-badge wcb-badge--danger" title="%s">%s</span>',
			esc_attr( $top ),
			esc_html( sprintf( /* translators: %d: number of reports */ _n( '%d report', '%d reports', $count, 'wp-career-board' ), $count ) )
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

		// Approve runs on the wcb/moderate-jobs ability so Board Moderators
		// (who lack edit_post) can fire it. Trash stays on edit_post +
		// wcb_manage_settings — only admins clear pending submissions.
		$can_approve = wp_is_ability_granted( 'wcb/moderate-jobs' ); // phpcs:ignore -- ability polyfill, see core/abilities-api-polyfill.php
		$can_trash   = wp_is_ability_granted( 'wcb/manage-settings' ); // phpcs:ignore -- ability polyfill, see core/abilities-api-polyfill.php
		foreach ( $job_ids as $job_id ) {
			if ( 'approve' === $action ) {
				if ( ! $can_approve ) {
					continue;
				}
				// wcb_job_approved fires via EmailJobApproved::on_status_transition()
				// on the transition_post_status hook triggered by wp_update_post().
				wp_update_post(
					array(
						'ID'          => $job_id,
						'post_status' => 'publish',
					)
				);
			} elseif ( 'resolve_flag' === $action ) {
				if ( ! $can_approve ) {
					continue;
				}
				\WCB\Modules\Moderation\ModerationModule::resolve_job_flags( $job_id, 'dismiss' );
			} elseif ( 'trash' === $action ) {
				if ( ! $can_trash || ! current_user_can( 'edit_post', $job_id ) ) {
					continue;
				}
				wp_trash_post( $job_id );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wcb-jobs' ) );
		exit;
	}

	/**
	 * Handle single-job resolve-flag row-action links.
	 *
	 * Runs before the table renders. Each link carries its own per-job nonce
	 * and delegates to ModerationModule::resolve_job_flags() so the admin and
	 * REST surfaces share one implementation.
	 *
	 * @since 1.2.1
	 * @return void
	 */
	private function process_flag_action(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$resolve = isset( $_GET['wcb_resolve_flag'] ) ? sanitize_key( wp_unslash( $_GET['wcb_resolve_flag'] ) ) : '';
		if ( '' === $resolve ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$job_id = isset( $_GET['job'] ) ? (int) $_GET['job'] : 0;
		if ( ! $job_id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wcb_resolve_flag_' . $job_id ) ) {
			return;
		}

		if ( ! wp_is_ability_granted( 'wcb/moderate-jobs' ) ) { // phpcs:ignore -- ability polyfill, see core/abilities-api-polyfill.php
			return;
		}

		\WCB\Modules\Moderation\ModerationModule::resolve_job_flags(
			$job_id,
			'unpublish' === $resolve ? 'unpublish' : 'dismiss'
		);

		wp_safe_redirect( admin_url( 'admin.php?page=wcb-jobs&wcb_flag=open' ) );
		exit;
	}
}
