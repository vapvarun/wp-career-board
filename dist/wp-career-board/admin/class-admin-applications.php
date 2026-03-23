<?php
/**
 * Admin Applications list — full WP_List_Table with search, status tabs,
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
 * WP_List_Table subclass for wcb_application posts.
 *
 * Registered as the `wcb-applications` submenu callback via render().
 *
 * @since 1.0.0
 */
class AdminApplications extends \WP_List_Table {

	/**
	 * Valid application status values.
	 *
	 * @var string[]
	 */
	private const STATUSES = array( 'submitted', 'reviewing', 'shortlisted', 'rejected', 'hired' );

	/**
	 * Constructor — configure singular/plural labels.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'application', 'wp-career-board' ),
				'plural'   => __( 'applications', 'wp-career-board' ),
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
		$this->prepare_items();
		?>
		<div class="wrap wcb-applications-list">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Applications', 'wp-career-board' ); ?></h1>
			<hr class="wp-header-end">

			<?php $this->views(); ?>

			<form method="get">
				<input type="hidden" name="page" value="wcb-applications">
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$wcb_search_val = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
				?>
				<p class="search-box">
					<label class="screen-reader-text" for="wcb-application-search-input">
						<?php esc_html_e( 'Search Applications', 'wp-career-board' ); ?>
					</label>
					<input type="search" id="wcb-application-search-input" name="s" value="<?php echo esc_attr( $wcb_search_val ); ?>" placeholder="<?php esc_attr_e( 'Job title or candidate name…', 'wp-career-board' ); ?>">
					<?php submit_button( __( 'Search Applications', 'wp-career-board' ), '', '', false, array( 'id' => 'search-submit' ) ); ?>
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
			'cb'        => '<input type="checkbox">',
			'candidate' => __( 'Candidate', 'wp-career-board' ),
			'job'       => __( 'Job', 'wp-career-board' ),
			'status'    => __( 'Status', 'wp-career-board' ),
			'change'    => __( 'Change Status', 'wp-career-board' ),
			'date'      => __( 'Date', 'wp-career-board' ),
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
			'date' => array( 'date', true ),
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
			'bulk_status_reviewing'   => __( 'Mark as Reviewing', 'wp-career-board' ),
			'bulk_status_shortlisted' => __( 'Mark as Shortlisted', 'wp-career-board' ),
			'bulk_status_rejected'    => __( 'Mark as Rejected', 'wp-career-board' ),
			'bulk_status_hired'       => __( 'Mark as Hired', 'wp-career-board' ),
			'trash'                   => __( 'Move to Trash', 'wp-career-board' ),
		);
	}

	// -------------------------------------------------------------------------
	// Data preparation
	// -------------------------------------------------------------------------

	/**
	 * Query applications and configure pagination.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_filter = isset( $_GET['app_status'] ) ? sanitize_text_field( wp_unslash( $_GET['app_status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'DESC';
		$order = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';

		$query_args = array(
			'post_type'      => 'wcb_application',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $current_page,
			'orderby'        => 'date',
			'order'          => $order,
		);

		// Filter by application status meta.
		if ( $status_filter && in_array( $status_filter, self::STATUSES, true ) ) {
			$query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_wcb_status',
					'value' => $status_filter,
				),
			);
		}

		// Custom search: match by job title or candidate name/email (not post title).
		if ( $search ) {
			global $wpdb;
			$like = '%' . $wpdb->esc_like( $search ) . '%';

			// Find wcb_job IDs whose title matches.
			$job_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wcb_job' AND post_title LIKE %s",
					$like
				)
			);

			// Find user IDs whose display_name, login, or email matches.
			$user_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->users} WHERE display_name LIKE %s OR user_login LIKE %s OR user_email LIKE %s",
					$like,
					$like,
					$like
				)
			);

			// Build an OR meta_query over job_id and candidate_id.
			$search_clauses = array( 'relation' => 'OR' );
			if ( ! empty( $job_ids ) ) {
				$search_clauses[] = array(
					'key'     => '_wcb_job_id',
					'value'   => array_map( 'intval', $job_ids ),
					'compare' => 'IN',
				);
			}
			if ( ! empty( $user_ids ) ) {
				$search_clauses[] = array(
					'key'     => '_wcb_candidate_id',
					'value'   => array_map( 'intval', $user_ids ),
					'compare' => 'IN',
				);
			}

			if ( count( $search_clauses ) > 1 ) {
				// Combine with any existing status meta_query using AND.
				if ( isset( $query_args['meta_query'] ) ) { // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					$query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'relation' => 'AND',
						$query_args['meta_query'],
						$search_clauses,
					);
				} else {
					$query_args['meta_query'] = $search_clauses; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				}
			} else {
				// No jobs or candidates matched — force zero results.
				$query_args['post__in'] = array( 0 );
			}
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
			'candidate', // Primary column.
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
		global $wpdb;

		$base_url = admin_url( 'admin.php?page=wcb-applications' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_GET['app_status'] ) ? sanitize_text_field( wp_unslash( $_GET['app_status'] ) ) : '';

		// Count total applications (all publish).
		$total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
				'wcb_application'
			)
		);

		$views = array();

		$views['all'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( $base_url ),
			'' === $current ? ' class="current"' : '',
			esc_html__( 'All', 'wp-career-board' ),
			$total
		);

		foreach ( self::STATUSES as $slug ) {
			$label = ucfirst( $slug );
			$count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT p.ID)
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
					WHERE p.post_type = %s
					  AND p.post_status = 'publish'
					  AND pm.meta_key = '_wcb_status'
					  AND pm.meta_value = %s",
					'wcb_application',
					$slug
				)
			);

			if ( 0 === $count && $current !== $slug ) {
				continue;
			}

			$views[ $slug ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'app_status', $slug, $base_url ) ),
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
	 * Message shown when the applications list is empty.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function no_items(): void {
		?>
		<div class="wcb-no-items-state">
			<span class="dashicons dashicons-email-alt"></span>
			<span class="wcb-no-items-title"><?php esc_html_e( 'No applications found', 'wp-career-board' ); ?></span>
			<p><?php esc_html_e( 'Applications appear here once candidates apply to your jobs. Try adjusting the search or status filter above.', 'wp-career-board' ); ?></p>
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
			'<label class="screen-reader-text" for="cb-select-%1$d">%2$s</label><input type="checkbox" id="cb-select-%1$d" name="application[]" value="%1$d">',
			(int) $item->ID,
			esc_html__( 'Select application', 'wp-career-board' )
		);
	}

	/**
	 * Candidate column — name linked to user profile, with row actions.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $item Current row post object.
	 * @return string
	 */
	protected function column_candidate( $item ): string {
		$candidate_id = (int) get_post_meta( $item->ID, '_wcb_candidate_id', true );
		$candidate    = $candidate_id ? get_userdata( $candidate_id ) : false;
		$edit_link    = (string) get_edit_post_link( $item->ID );
		$row_actions  = array(
			'view' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_link ),
				esc_html__( 'View', 'wp-career-board' )
			),
		);

		// Guest application — candidate_id is 0, use stored guest meta.
		if ( 0 === $candidate_id && ! $candidate instanceof \WP_User ) {
			$guest_name  = (string) get_post_meta( $item->ID, '_wcb_guest_name', true );
			$guest_email = (string) get_post_meta( $item->ID, '_wcb_guest_email', true );
			$display     = $guest_name ? $guest_name : __( '(unknown)', 'wp-career-board' );
			$out         = '<span class="wcb-guest-badge">' . esc_html__( 'Guest', 'wp-career-board' ) . '</span> ';
			$out        .= '<strong>' . esc_html( $display ) . '</strong>';
			if ( $guest_email ) {
				$out                 .= '<br><small>' . esc_html( $guest_email ) . '</small>';
				$row_actions['email'] = sprintf(
					'<a href="mailto:%s">%s</a>',
					esc_attr( $guest_email ),
					esc_html__( 'Email Guest', 'wp-career-board' )
				);
			}
			$out .= $this->row_actions( $row_actions );
			return $out;
		}

		$name = $candidate instanceof \WP_User ? $candidate->display_name : __( '(deleted)', 'wp-career-board' );

		if ( $candidate instanceof \WP_User ) {
			$out = sprintf(
				'<strong><a class="row-title" href="%s">%s</a></strong>',
				esc_url( (string) get_edit_user_link( $candidate->ID ) ),
				esc_html( $name )
			);
		} else {
			$out = '<strong>' . esc_html( $name ) . '</strong>';
		}

		if ( $candidate instanceof \WP_User && $candidate->user_email ) {
			$row_actions['email'] = sprintf(
				'<a href="mailto:%s">%s</a>',
				esc_attr( $candidate->user_email ),
				esc_html__( 'Email Candidate', 'wp-career-board' )
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
		}

		$out .= $this->row_actions( $row_actions );
		return $out;
	}

	/**
	 * Job column — title linked to job edit page.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $item Current row post object.
	 * @return string
	 */
	protected function column_job( $item ): string {
		$job_id = (int) get_post_meta( $item->ID, '_wcb_job_id', true );
		$job    = $job_id ? get_post( $job_id ) : null;

		if ( $job instanceof \WP_Post ) {
			return sprintf(
				'<a href="%s">%s</a>',
				esc_url( (string) get_edit_post_link( $job->ID ) ),
				esc_html( $job->post_title )
			);
		}

		return esc_html__( '(deleted)', 'wp-career-board' );
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
		$raw    = (string) get_post_meta( $item->ID, '_wcb_status', true );
		$status = in_array( $raw, self::STATUSES, true ) ? $raw : 'submitted';

		return sprintf(
			'<span class="wcb-status-badge wcb-status-%s">%s</span>',
			esc_attr( $status ),
			esc_html( ucfirst( $status ) )
		);
	}

	/**
	 * Change-status column — inline select, updated via JS/REST.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $item Current row post object.
	 * @return string
	 */
	protected function column_change( $item ): string {
		$raw    = (string) get_post_meta( $item->ID, '_wcb_status', true );
		$status = in_array( $raw, self::STATUSES, true ) ? $raw : 'submitted';

		$select = sprintf(
			'<select class="wcb-status-select" data-app-id="%1$d" aria-label="%2$s">',
			(int) $item->ID,
			esc_attr__( 'Change application status', 'wp-career-board' )
		);
		foreach ( self::STATUSES as $opt ) {
			$select .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $opt ),
				selected( $status, $opt, false ),
				esc_html( ucfirst( $opt ) )
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
	public function process_bulk_action(): void {
		$action = $this->current_action();
		if ( ! $action ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bulk-applications' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$app_ids = isset( $_GET['application'] ) ? array_map( 'intval', (array) $_GET['application'] ) : array();
		if ( empty( $app_ids ) ) {
			return;
		}

		$bulk_status_map = array(
			'bulk_status_reviewing'   => 'reviewing',
			'bulk_status_shortlisted' => 'shortlisted',
			'bulk_status_rejected'    => 'rejected',
			'bulk_status_hired'       => 'hired',
		);

		foreach ( $app_ids as $app_id ) {
			if ( ! current_user_can( 'edit_post', $app_id ) ) {
				continue;
			}
			if ( 'trash' === $action ) {
				wp_trash_post( $app_id );
			} elseif ( isset( $bulk_status_map[ $action ] ) ) {
				$new_status = $bulk_status_map[ $action ];
				$old_status = (string) get_post_meta( $app_id, '_wcb_status', true );
				update_post_meta( $app_id, '_wcb_status', $new_status );
				do_action( 'wcb_application_status_changed', $app_id, $old_status, $new_status );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wcb-applications' ) );
		exit;
	}
}
