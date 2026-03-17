<?php
/**
 * Admin Candidates list — full WP_List_Table with search, pagination,
 * and sortable columns.
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
 * WP_List_Table subclass for candidate users.
 *
 * @since 1.0.0
 */
class AdminCandidates extends \WP_List_Table {

	/**
	 * Constructor — configure singular/plural labels.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'candidate', 'wp-career-board' ),
				'plural'   => __( 'candidates', 'wp-career-board' ),
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
		<div class="wrap wcb-candidates-list">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Candidates', 'wp-career-board' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'wp-career-board' ); ?>
			</a>
			<hr class="wp-header-end">

			<form method="get">
				<input type="hidden" name="page" value="wcb-candidates">
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$wcb_s = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
				?>
				<p class="search-box">
					<label class="screen-reader-text" for="wcb-candidate-search-input">
						<?php esc_html_e( 'Search Candidates', 'wp-career-board' ); ?>
					</label>
					<input type="search" id="wcb-candidate-search-input" name="s" value="<?php echo esc_attr( $wcb_s ); ?>" placeholder="<?php esc_attr_e( 'Name or email…', 'wp-career-board' ); ?>">
					<?php submit_button( __( 'Search Candidates', 'wp-career-board' ), '', '', false, array( 'id' => 'search-submit' ) ); ?>
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
			'cb'           => '<input type="checkbox">',
			'name'         => __( 'Name', 'wp-career-board' ),
			'visibility'   => __( 'Profile Visibility', 'wp-career-board' ),
			'applications' => __( 'Applications', 'wp-career-board' ),
			'bookmarks'    => __( 'Bookmarks', 'wp-career-board' ),
			'registered'   => __( 'Registered', 'wp-career-board' ),
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
	 * Message shown when the candidates list is empty.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function no_items(): void {
		?>
		<div class="wcb-no-items-state">
			<span class="dashicons dashicons-groups"></span>
			<span class="wcb-no-items-title"><?php esc_html_e( 'No candidates yet', 'wp-career-board' ); ?></span>
			<p><?php esc_html_e( 'Candidates register on the frontend and appear here once they create an account.', 'wp-career-board' ); ?></p>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Data preparation
	// -------------------------------------------------------------------------

	/**
	 * Query candidate users and configure pagination.
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
			'role__in' => array( 'wcb_candidate' ),
			'orderby'  => $orderby,
			'order'    => $order,
			'number'   => $per_page,
			'offset'   => ( $current_page - 1 ) * $per_page,
		);

		if ( $search ) {
			$query_args['search']         = '*' . $search . '*';
			$query_args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
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
			esc_html__( 'Select candidate', 'wp-career-board' )
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
	 * Profile visibility column — coloured badge.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $item Current row user object.
	 * @return string
	 */
	protected function column_visibility( $item ): string {
		$vis   = (string) get_user_meta( $item->ID, '_wcb_profile_visibility', true );
		$vis   = $vis ? $vis : 'public';
		$class = 'public' === $vis ? 'wcb-job-status-publish' : 'wcb-job-status-draft';

		return sprintf(
			'<span class="wcb-status-badge %s">%s</span>',
			esc_attr( $class ),
			esc_html( ucfirst( $vis ) )
		);
	}

	/**
	 * Applications count column.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $item Current row user object.
	 * @return string
	 */
	protected function column_applications( $item ): string {
		$count = (int) ( new \WP_Query(
			array(
				'post_type'      => 'wcb_application',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_wcb_candidate_id',
						'value' => $item->ID,
						'type'  => 'NUMERIC',
					),
				),
			)
		) )->found_posts;

		if ( $count > 0 ) {
			return sprintf(
				'<a href="%s">%d</a>',
				esc_url( add_query_arg( array( 'page' => 'wcb-applications' ), admin_url( 'admin.php' ) ) ),
				$count
			);
		}

		return '0';
	}

	/**
	 * Bookmarks count column.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $item Current row user object.
	 * @return string
	 */
	protected function column_bookmarks( $item ): string {
		return (string) count( (array) get_user_meta( $item->ID, '_wcb_bookmark', false ) );
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
