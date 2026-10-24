<?php
/**
 * Job taxonomy terms in the order the site owner set.
 *
 * Every dropdown, chip list and filter that lists categories, types,
 * locations, experience levels or tags reads its terms here, so ordering and
 * parent/child nesting behave the same on every surface.
 *
 * @package WP_Career_Board
 * @since   1.8.1
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ordered, nested term options for the job taxonomies.
 *
 * @since 1.8.1
 */
final class Taxonomy {

	/**
	 * Term meta key holding the admin-set position (lower first).
	 */
	public const ORDER_META = 'wcb_order';

	/**
	 * Taxonomies that get an Order field.
	 */
	public const TAXONOMIES = array( 'wcb_category', 'wcb_job_type', 'wcb_location', 'wcb_experience', 'wcb_tag' );

	/**
	 * Default seniority positions for experience levels the admin has not
	 * ordered, matched on slug words, so "Entry" never sorts after "Executive".
	 */
	private const SENIORITY = array(
		'intern'    => 10,
		'entry'     => 20,
		'junior'    => 30,
		'mid'       => 40,
		'senior'    => 50,
		'lead'      => 60,
		'principal' => 70,
		'staff'     => 70,
		'manager'   => 80,
		'director'  => 90,
		'executive' => 100,
	);

	/**
	 * Register the order meta and the Order field on each term screen.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_action(
			'init',
			static function (): void {
				foreach ( self::TAXONOMIES as $taxonomy ) {
					register_term_meta(
						$taxonomy,
						self::ORDER_META,
						array(
							'type'              => 'integer',
							'single'            => true,
							'sanitize_callback' => 'absint',
							'show_in_rest'      => true,
						)
					);
					add_action( $taxonomy . '_add_form_fields', array( self::class, 'render_add_field' ) );
					add_action( $taxonomy . '_edit_form_fields', array( self::class, 'render_edit_field' ) );
					add_action( 'created_' . $taxonomy, array( self::class, 'save_order' ) );
					add_action( 'edited_' . $taxonomy, array( self::class, 'save_order' ) );
					add_filter( 'manage_edit-' . $taxonomy . '_columns', array( self::class, 'add_order_column' ) );
					add_filter( 'manage_edit-' . $taxonomy . '_sortable_columns', array( self::class, 'add_order_column_sort' ) );
					add_filter( 'manage_' . $taxonomy . '_custom_column', array( self::class, 'render_order_column' ), 10, 3 );
				}
			},
			20
		);
		add_action( 'quick_edit_custom_box', array( self::class, 'render_quick_edit_field' ), 10, 3 );
		add_filter( 'get_terms_args', array( self::class, 'sort_by_order' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_term_list_script' ) );
	}

	/**
	 * Order column on the term list, so the sort is visible without opening
	 * each term (Basecamp 10417521347).
	 *
	 * @param array<string, string> $columns List columns.
	 * @return array<string, string>
	 */
	public static function add_order_column( array $columns ): array {
		$columns[ self::ORDER_META ] = __( 'Order', 'wp-career-board' );
		return $columns;
	}

	/**
	 * Make the Order column sortable.
	 *
	 * @param array<string, string> $columns Sortable columns.
	 * @return array<string, string>
	 */
	public static function add_order_column_sort( array $columns ): array {
		$columns[ self::ORDER_META ] = self::ORDER_META;
		return $columns;
	}

	/**
	 * Order cell: the number, or a dash when the term sorts by name. Term
	 * meta is primed for the whole page by the list query.
	 *
	 * @param string $content Cell content.
	 * @param string $column  Column slug.
	 * @param int    $term_id Term ID.
	 * @return string
	 */
	public static function render_order_column( $content, string $column, int $term_id ): string {
		if ( self::ORDER_META !== $column ) {
			return (string) $content;
		}
		$order = (string) get_term_meta( $term_id, self::ORDER_META, true );
		return '' === $order ? '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . esc_html__( 'Sorted by name', 'wp-career-board' ) . '</span>' : esc_html( $order );
	}

	/**
	 * Order field in the term list's Quick Edit; core's inline save fires
	 * edited_{taxonomy}, so save_order() stores it.
	 *
	 * @param string $column    Column slug.
	 * @param string $screen    'edit-tags' on a term list.
	 * @param string $taxonomy  Taxonomy slug.
	 * @return void
	 */
	public static function render_quick_edit_field( string $column, string $screen, string $taxonomy = '' ): void {
		if ( 'edit-tags' !== $screen || self::ORDER_META !== $column || ! in_array( $taxonomy, self::TAXONOMIES, true ) ) {
			return;
		}
		?>
		<fieldset>
			<div class="inline-edit-col">
				<label>
					<span class="title"><?php esc_html_e( 'Order', 'wp-career-board' ); ?></span>
					<span class="input-text-wrap"><input type="number" name="wcb_order" class="ptitle" min="0" step="1" value="" /></span>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Sort the term list by Order when its column header is clicked; terms
	 * with no Order keep their place after the ordered ones.
	 *
	 * @param array<string, mixed> $args       get_terms() args.
	 * @param string[]             $taxonomies Taxonomies queried.
	 * @return array<string, mixed>
	 */
	public static function sort_by_order( array $args, array $taxonomies ): array {
		if ( self::ORDER_META !== ( $args['orderby'] ?? '' ) || ! array_intersect( $taxonomies, self::TAXONOMIES ) ) {
			return $args;
		}
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin list sort, one page.
			'relation'  => 'OR',
			'wcb_order' => array(
				'key'  => self::ORDER_META,
				'type' => 'NUMERIC',
			),
			'no_order'  => array(
				'key'     => self::ORDER_META,
				'compare' => 'NOT EXISTS',
			),
		);
		$args['orderby']    = 'wcb_order';
		return $args;
	}

	/**
	 * Quick Edit prefill script on the five term lists.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public static function enqueue_term_list_script( string $hook ): void {
		$screen = get_current_screen();
		if ( 'edit-tags.php' !== $hook || ! $screen || ! in_array( $screen->taxonomy, self::TAXONOMIES, true ) ) {
			return;
		}
		wp_enqueue_script( 'wcb-term-order', WCB_URL . 'assets/js/admin/term-order.js', array( 'inline-edit-tax' ), WCB_VERSION, true );
	}

	/**
	 * Terms of a taxonomy, ordered and (for nested taxonomies) in tree order.
	 *
	 * @param string $taxonomy   Taxonomy slug.
	 * @param bool   $hide_empty Skip terms with no jobs (parents of used terms stay).
	 * @return array<int, object{id:int, slug:string, name:string, depth:int, parent:int, parent_name:string, label:string}>
	 */
	public static function options( string $taxonomy, bool $hide_empty = false ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => $hide_empty,
			)
		);
		if ( ! is_array( $terms ) ) {
			return array();
		}

		usort(
			$terms,
			static fn( \WP_Term $a, \WP_Term $b ): int => array( self::position( $a ), strtolower( $a->name ) ) <=> array( self::position( $b ), strtolower( $b->name ) )
		);

		$children = array();
		$ids      = wp_list_pluck( $terms, 'term_id' );
		foreach ( $terms as $term ) {
			// A parent outside this list (deleted) would hide the term; show it at the top level.
			$parent                = in_array( $term->parent, $ids, true ) ? $term->parent : 0;
			$children[ $parent ][] = $term;
		}

		$out   = array();
		$names = wp_list_pluck( $terms, 'name', 'term_id' );
		$walk  = static function ( int $parent_id, int $depth ) use ( &$walk, &$out, $children, $names ): void {
			foreach ( $children[ $parent_id ] ?? array() as $term ) {
				$out[] = (object) array(
					'id'          => (int) $term->term_id,
					'slug'        => $term->slug,
					'name'        => $term->name,
					'depth'       => $depth,
					'parent'      => $parent_id,
					// Child names repeat across groups ("Other", "Management"): search shows whose.
					'parent_name' => (string) ( $names[ $parent_id ] ?? '' ),
					'label'       => str_repeat( '— ', $depth ) . $term->name,
				);
				$walk( (int) $term->term_id, $depth + 1 );
			}
		};
		$walk( 0, 0 );
		return $out;
	}

	/**
	 * Sort position: the admin's Order, else a seniority guess for experience
	 * levels, else last (then by name).
	 *
	 * @param \WP_Term $term Term.
	 * @return int
	 */
	private static function position( \WP_Term $term ): int {
		$order = get_term_meta( $term->term_id, self::ORDER_META, true );
		if ( '' !== $order ) {
			return (int) $order;
		}
		if ( 'wcb_experience' === $term->taxonomy ) {
			foreach ( self::SENIORITY as $word => $position ) {
				if ( str_contains( $term->slug, $word ) ) {
					return $position;
				}
			}
		}
		return PHP_INT_MAX;
	}

	/**
	 * "in Parent" after a child category in a searchable list. Part of the
	 * checkbox's name for screen readers, shown on screen while searching.
	 *
	 * @since 1.8.1
	 * @param string $parent_name Parent category name ('' for a top-level one).
	 * @return string Escaped HTML.
	 */
	public static function parent_note( string $parent_name ): string {
		if ( '' === $parent_name ) {
			return '';
		}
		/* translators: %s: parent category name, e.g. "in Hospitality & Tourism". */
		return '<span class="wcb-filter-parent">' . esc_html( sprintf( __( 'in %s', 'wp-career-board' ), $parent_name ) ) . '</span>';
	}

	/**
	 * Live region after a searchable list; filterList() writes the message
	 * into it when a search hides every row.
	 *
	 * @since 1.8.1
	 * @return string Escaped HTML.
	 */
	public static function no_match_note(): string {
		return '<p class="wcb-filter-empty" role="status" data-message="' . esc_attr__( 'No categories match.', 'wp-career-board' ) . '"></p>';
	}

	/**
	 * Order field on the Add term form.
	 *
	 * @return void
	 */
	public static function render_add_field(): void {
		?>
		<div class="form-field">
			<label for="wcb-term-order"><?php esc_html_e( 'Order', 'wp-career-board' ); ?></label>
			<input type="number" id="wcb-term-order" name="wcb_order" min="0" step="1" value="" />
			<p><?php esc_html_e( 'Lower numbers show first in dropdowns and filters. Leave empty to sort by name.', 'wp-career-board' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Order field on the Edit term form.
	 *
	 * @param \WP_Term $term Term being edited.
	 * @return void
	 */
	public static function render_edit_field( \WP_Term $term ): void {
		?>
		<tr class="form-field">
			<th scope="row"><label for="wcb-term-order"><?php esc_html_e( 'Order', 'wp-career-board' ); ?></label></th>
			<td>
				<input type="number" id="wcb-term-order" name="wcb_order" min="0" step="1" value="<?php echo esc_attr( (string) get_term_meta( $term->term_id, self::ORDER_META, true ) ); ?>" />
				<p class="description"><?php esc_html_e( 'Lower numbers show first in dropdowns and filters. Leave empty to sort by name.', 'wp-career-board' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the Order field. Core has already checked the nonce and the
	 * user's right to manage the term before these hooks fire.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public static function save_order( int $term_id ): void {
		if ( ! isset( $_POST['wcb_order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by core's term screen.
			return;
		}
		$order = trim( sanitize_text_field( wp_unslash( $_POST['wcb_order'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $order ) {
			delete_term_meta( $term_id, self::ORDER_META );
			return;
		}
		update_term_meta( $term_id, self::ORDER_META, absint( $order ) );
	}
}
