<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated admin class name follows project autoloader convention.
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
	 * Most job / candidate IDs a search feeds into the meta_query below.
	 *
	 * The list is 20 rows a page, so a term matching more than this is already
	 * unusable as a list; capping keeps an unbounded `IN (...)` off the query
	 * on a site with 20k users.
	 *
	 * @since 1.7.1
	 * @var int
	 */
	private const SEARCH_ID_CAP = 500;

	/**
	 * Shortest term that triggers the wp_users scan.
	 *
	 * SEARCH_ID_CAP is what actually bounds the cost - with a LIMIT the scan
	 * stops as soon as it has enough rows, so a short term is cheap, not
	 * expensive. This gate exists for the result, not the query: one character
	 * returns an arbitrary 500-of-20000 slice, which is worse than no match at
	 * all. Two characters is a plausible search ("Li", "Wu"), so the floor sits
	 * just above the useless case rather than at ft_min_word_len.
	 *
	 * @since 1.7.1
	 * @var int
	 */
	private const MIN_USER_SEARCH_LEN = 2;

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
		$this->process_bulk_action();
		$this->prepare_items();
		?>
		<div class="wrap wcb-admin wcb-applications-list">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Applications', 'wp-career-board' ); ?></h1>
			<div class="wcb-page-header">
				<div class="wcb-page-header__left">
					<h2 class="wcb-page-header__title">
						<i data-lucide="clipboard-list"></i>
						<?php esc_html_e( 'Applications', 'wp-career-board' ); ?>
					</h2>
					<p class="wcb-page-header__desc"><?php esc_html_e( 'Review candidate applications, update statuses, and manage your hiring pipeline.', 'wp-career-board' ); ?></p>
				</div>
			</div>

			<?php $this->views(); ?>

			<?php
			$wcb_job_filter = $this->job_filter();
			$wcb_export_url = wp_nonce_url(
				add_query_arg( array_merge( $this->filter_args(), array( 'wcb_export' => 'all' ) ), admin_url( 'admin.php?page=wcb-applications' ) ),
				'wcb-export-applications'
			);
			?>
			<p class="wcb-applications-toolbar">
				<?php if ( $wcb_job_filter > 0 ) : ?>
					<span class="wcb-filter-chip">
						<?php
						/* translators: %s: job title */
						printf( esc_html__( 'Job: %s', 'wp-career-board' ), esc_html( (string) get_post_field( 'post_title', $wcb_job_filter ) ) );
						?>
						<a href="<?php echo esc_url( remove_query_arg( array( 'job_id', 'paged' ) ) ); ?>" aria-label="<?php esc_attr_e( 'Show all jobs', 'wp-career-board' ); ?>">&times;</a>
					</span>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( $wcb_export_url ); ?>"><?php esc_html_e( 'Export all matching to CSV', 'wp-career-board' ); ?></a>
			</p>

			<form method="get">
				<input type="hidden" name="page" value="wcb-applications">
				<?php foreach ( array_diff_key( $this->filter_args(), array( 's' => 1 ) ) as $wcb_key => $wcb_val ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $wcb_key ); ?>" value="<?php echo esc_attr( $wcb_val ); ?>">
				<?php endforeach; ?>
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$wcb_search_val = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
				?>
				<p class="search-box">
					<label class="screen-reader-text" for="wcb-application-search-input">
						<?php esc_html_e( 'Search Applications', 'wp-career-board' ); ?>
					</label>
					<label for="wcb-application-search-input" class="screen-reader-text"><?php esc_html_e( 'Search applications', 'wp-career-board' ); ?></label>
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
			'cb'        => sprintf( '<input type="checkbox" aria-label="%s" />', esc_attr__( 'Select all applications', 'wp-career-board' ) ),
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
		if ( $this->is_trash_view() ) {
			return array(
				'untrash' => __( 'Restore', 'wp-career-board' ),
				'delete'  => __( 'Delete Permanently', 'wp-career-board' ),
			);
		}

		return array(
			'bulk_status_reviewing'   => __( 'Mark as Reviewing', 'wp-career-board' ),
			'bulk_status_shortlisted' => __( 'Mark as Shortlisted', 'wp-career-board' ),
			'bulk_status_rejected'    => __( 'Mark as Rejected', 'wp-career-board' ),
			'bulk_status_hired'       => __( 'Mark as Hired', 'wp-career-board' ),
			'export_csv'              => __( 'Export to CSV', 'wp-career-board' ),
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
		$per_page = 20;

		$query_args                   = $this->query_args();
		$query_args['posts_per_page'] = $per_page;
		$query_args['paged']          = $this->get_pagenum();

		$query       = new \WP_Query( $query_args );
		$this->items = $query->posts;

		// Prime the candidate user cache in one query so column_candidate()'s
		// per-row get_userdata() doesn't fire an uncached user lookup per row
		// (N+1 across the page). WP_Query already primed the postmeta cache, so
		// these _wcb_candidate_id reads are free. See DATA-AT-SCALE Rule 3.
		$wcb_candidate_ids = array();
		foreach ( $this->items as $wcb_item ) {
			$wcb_cid = (int) get_post_meta( $wcb_item->ID, '_wcb_candidate_id', true );
			if ( $wcb_cid > 0 ) {
				$wcb_candidate_ids[] = $wcb_cid;
			}
		}
		if ( ! empty( $wcb_candidate_ids ) ) {
			cache_users( array_values( array_unique( $wcb_candidate_ids ) ) );
		}
		// Same for the Job column's get_post(): one query for the page.
		$wcb_job_ids = array_filter( array_map( static fn( $item ) => (int) get_post_meta( $item->ID, '_wcb_job_id', true ), $this->items ) );
		if ( $wcb_job_ids ) {
			_prime_post_caches( array_values( array_unique( $wcb_job_ids ) ), false, false );
		}

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
			'candidate', // Primary column.
		);
	}

	/**
	 * The filters on screen as WP_Query args (status tab, job, search, Trash).
	 *
	 * Shared by the table and "Export all matching", so an export always
	 * contains exactly what the filters show.
	 *
	 * @since 1.8.0
	 * @return array<string,mixed>
	 */
	private function query_args(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$status_filter = isset( $_GET['app_status'] ) ? sanitize_text_field( wp_unslash( $_GET['app_status'] ) ) : '';
		$search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$order         = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'DESC';
		// phpcs:enable

		$query_args = array(
			'post_type'   => 'wcb_application',
			'post_status' => $this->is_trash_view() ? 'trash' : 'publish',
			'orderby'     => 'date',
			'order'       => in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC',
		);

		$clauses = array( 'relation' => 'AND' );
		if ( $status_filter && \WCB\Modules\Applications\ApplicationStatus::is_valid( $status_filter ) ) {
			$clauses[] = array(
				'key'   => '_wcb_status',
				'value' => $status_filter,
			);
		}
		if ( $this->job_filter() > 0 ) {
			$clauses[] = array(
				'key'   => '_wcb_job_id',
				'value' => (string) $this->job_filter(),
			);
		}

		// Custom search: match by job title or candidate name/email (not post title).
		if ( $search ) {
			global $wpdb;
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$job_ids  = array();
			$user_ids = array();

			// Job half - reuse the FULLTEXT clause the public listing searches
			// already use (Install migration 1.2.6 indexes wp_posts.post_title).
			$title_clause = \WCB\Core\TitleSearch::title_clause( $search );
			if ( '' !== $title_clause ) {
				// $title_clause is already prepared; the cap is an int constant.
				$job_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
					"SELECT ID FROM {$wpdb->posts}
					 WHERE post_type = 'wcb_job' AND {$title_clause}
					 LIMIT " . self::SEARCH_ID_CAP
				);
			}

			// Candidate half - wp_users has no FULLTEXT index, so the cap is what
			// bounds both the scan and the IN (...) the meta_query builds. This
			// screen is for finding one application, not listing thousands.
			if ( strlen( $search ) >= self::MIN_USER_SEARCH_LEN ) {
				$user_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->users}
						 WHERE display_name LIKE %s OR user_login LIKE %s OR user_email LIKE %s
						 LIMIT %d",
						$like,
						$like,
						$like,
						self::SEARCH_ID_CAP
					)
				);
			}

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
				$clauses[] = $search_clauses;
			} else {
				// No jobs or candidates matched - force zero results.
				$query_args['post__in'] = array( 0 );
			}
		}

		if ( count( $clauses ) > 1 ) {
			$query_args['meta_query'] = $clauses; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		return $query_args;
	}

	/**
	 * Whether the Trash view is showing.
	 *
	 * @since 1.8.0
	 * @return bool
	 */
	private function is_trash_view(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch.
		return isset( $_GET['post_status'] ) && 'trash' === $_GET['post_status'];
	}

	/**
	 * Job the list is narrowed to, 0 for all jobs.
	 *
	 * @since 1.8.0
	 * @return int
	 */
	private function job_filter(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		return isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;
	}

	/**
	 * The current filters as query args, for links that must keep them.
	 *
	 * @since 1.8.0
	 * @return array<string,string>
	 */
	private function filter_args(): array {
		$args = array();
		foreach ( array( 'app_status', 'job_id', 'post_status', 's' ) as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters.
			if ( isset( $_GET[ $key ] ) && '' !== $_GET[ $key ] ) {
				$args[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
		return $args;
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
		// Views keep the job filter; switching status or Trash drops the others.
		$base_url = add_query_arg( array_intersect_key( $this->filter_args(), array( 'job_id' => 1 ) ), admin_url( 'admin.php?page=wcb-applications' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_GET['app_status'] ) ? sanitize_text_field( wp_unslash( $_GET['app_status'] ) ) : '';
		$trash   = $this->is_trash_view();

		$counts = $this->job_filter() > 0
			? \WCB\Modules\Applications\ApplicationStatus::counts( 'job', $this->job_filter() )
			: $this->get_status_counts();

		$views = array();

		$views['all'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( $base_url ),
			'' === $current && ! $trash ? ' class="current"' : '',
			esc_html__( 'All', 'wp-career-board' ),
			$counts['total']
		);

		foreach ( \WCB\Modules\Applications\ApplicationStatus::all() as $slug ) {
			$count = (int) ( $counts['by_status'][ $slug ] ?? 0 );

			if ( 0 === $count && $current !== $slug ) {
				continue;
			}

			$views[ $slug ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'app_status', $slug, $base_url ) ),
				$current === $slug && ! $trash ? ' class="current"' : '',
				esc_html( \WCB\Modules\Applications\ApplicationStatus::label( $slug, \WCB\Modules\Applications\ApplicationStatus::AUDIENCE_ADMIN ) ),
				$count
			);
		}

		$trashed = (int) ( wp_count_posts( 'wcb_application' )->trash ?? 0 );
		if ( $trashed > 0 || $trash ) {
			$views['trash'] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'post_status', 'trash', admin_url( 'admin.php?page=wcb-applications' ) ) ),
				$trash ? ' class="current"' : '',
				esc_html__( 'Trash', 'wp-career-board' ),
				$trashed
			);
		}

		return $views;
	}

	/**
	 * Total + per-status application counts for the status tabs.
	 *
	 * One grouped query behind a transient that is cleared on every status
	 * change, new, trashed or deleted application (ApplicationStatus::counts()).
	 *
	 * @since 1.2.9
	 * @return array{total:int,by_status:array<string,int>}
	 */
	protected function get_status_counts(): array {
		return \WCB\Modules\Applications\ApplicationStatus::counts();
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
		<div class="wcb-empty-state">
			<i data-lucide="mail" class="wcb-empty-state__icon"></i>
			<p class="wcb-empty-state__title"><?php esc_html_e( 'No applications found', 'wp-career-board' ); ?></p>
			<p class="wcb-empty-state__desc"><?php esc_html_e( 'Applications appear here once candidates apply to your jobs. Try adjusting the search or status filter above.', 'wp-career-board' ); ?></p>
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
			// The title narrows the list to this job (per-job review and export).
			return sprintf(
				'<a href="%1$s" title="%2$s">%3$s</a>',
				esc_url( add_query_arg( 'job_id', $job->ID, admin_url( 'admin.php?page=wcb-applications' ) ) ),
				esc_attr__( 'Show only this job\'s applications', 'wp-career-board' ),
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
		$raw    = \WCB\Modules\Applications\ApplicationLifecycle::current_status( $item->ID );
		$status = \WCB\Modules\Applications\ApplicationStatus::is_valid( $raw ) ? $raw : 'submitted';

		return \WCB\Modules\Applications\ApplicationStatus::admin_badge( $status );
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
		$raw    = \WCB\Modules\Applications\ApplicationLifecycle::current_status( $item->ID );
		$status = '' !== $raw ? $raw : 'submitted';

		// A candidate-side or system outcome is shown, not offered as a choice.
		if ( ! in_array( $status, \WCB\Modules\Applications\ApplicationStatus::employer_actionable(), true ) ) {
			return esc_html( \WCB\Modules\Applications\ApplicationStatus::label( $status, \WCB\Modules\Applications\ApplicationStatus::AUDIENCE_ADMIN ) );
		}

		$select = sprintf(
			'<select class="wcb-status-select" data-app-id="%1$d" aria-label="%2$s">',
			(int) $item->ID,
			esc_attr__( 'Change application status', 'wp-career-board' )
		);
		foreach ( \WCB\Modules\Applications\ApplicationStatus::employer_actionable() as $opt ) {
			$select .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $opt ),
				selected( $status, $opt, false ),
				esc_html( \WCB\Modules\Applications\ApplicationStatus::label( $opt, \WCB\Modules\Applications\ApplicationStatus::AUDIENCE_ADMIN ) )
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
		// "Export all matching": every row the current filters select, not just
		// the ticked rows of this page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified on the next line.
		if ( isset( $_GET['wcb_export'] ) && check_admin_referer( 'wcb-export-applications' ) ) {
			\WCB\Core\ApplicationsCsv::stream( $this->query_args() );
			return; // exits inside.
		}

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

		if ( 'export_csv' === $action ) {
			\WCB\Core\ApplicationsCsv::stream(
				array(
					'post_type'   => 'wcb_application',
					'post_status' => 'any',
					'post__in'    => $app_ids,
					'orderby'     => 'post__in',
				)
			);
			return; // exits inside.
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
			} elseif ( 'untrash' === $action ) {
				wp_untrash_post( $app_id );
			} elseif ( 'delete' === $action && current_user_can( 'delete_post', $app_id ) ) {
				wp_delete_post( $app_id, true );
			} elseif ( isset( $bulk_status_map[ $action ] ) ) {
				\WCB\Modules\Applications\ApplicationLifecycle::transition( (int) $app_id, $bulk_status_map[ $action ], 'admin_bulk' );
			}
		}

		wp_safe_redirect( add_query_arg( $this->filter_args(), admin_url( 'admin.php?page=wcb-applications' ) ) );
		exit;
	}
}
