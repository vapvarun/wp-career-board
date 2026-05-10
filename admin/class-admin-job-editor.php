<?php
/**
 * Admin Job Editor — replaces the default WP editor on the wcb_job edit
 * screen with our Editor.js surface, matching the simplified admin UX
 * pattern used in Learnomy.
 *
 * Approach:
 *   1. Remove `editor` support from `wcb_job` so WP doesn't render the
 *      Block/Classic editor area.
 *   2. Add a high-priority metabox `wcb-job-description` that renders our
 *      `.wcb-editor` markup with a hidden `<textarea name="content">`.
 *      WP's standard post save handler picks `$_POST['content']` up and
 *      writes it to post_content, so no save hook is required here.
 *   3. Enqueue the same Editor.js bundles + bootstrap that the frontend
 *      uses, so authors get the identical editing surface across
 *      backend and frontend.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

declare( strict_types=1 );

namespace WCB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces the default WP post editor on wcb_job with Editor.js.
 *
 * @since 1.1.0
 */
class AdminJobEditor {

	/**
	 * Boot the editor replacement.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'remove_default_editor_support' ), 100 );
		add_action( 'add_meta_boxes_wcb_job', array( $this, 'register_description_metabox' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_editor_assets' ) );
	}

	/**
	 * Strip `editor` from wcb_job's post-type-supports.
	 *
	 * Runs at priority 100 so it executes after the JobsModule has registered
	 * the post type. Because the CPT label / capabilities are unchanged, every
	 * existing job permalink, REST route, and taxonomy assignment continues to
	 * work — only the admin edit-screen editor area is removed.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function remove_default_editor_support(): void {
		remove_post_type_support( 'wcb_job', 'editor' );
	}

	/**
	 * Register the metabox that hosts our Editor.js surface.
	 *
	 * Position is `normal` + priority `high` so it renders directly under
	 * the title field — exactly where the default editor used to sit, so
	 * the visual hierarchy of the edit screen is preserved.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_description_metabox(): void {
		add_meta_box(
			'wcb-job-description',
			__( 'Job Description', 'wp-career-board' ),
			array( $this, 'render_description_metabox' ),
			'wcb_job',
			'normal',
			'high'
		);
	}

	/**
	 * Render the Editor.js metabox.
	 *
	 * The hidden `<textarea name="content">` is the canonical save target —
	 * WP's `wp_insert_post()` / `wp_update_post()` flow reads `$_POST['content']`
	 * and persists it to `post_content`. The `.wcb-editor-source` class +
	 * `tabindex="-1"` keep the textarea focusable for assistive tech without
	 * showing it visually.
	 *
	 * @since 1.1.0
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_description_metabox( \WP_Post $post ): void {
		?>
		<div class="wcb-editor wcb-editor--admin" data-placeholder="<?php esc_attr_e( 'Describe the role, responsibilities and requirements…', 'wp-career-board' ); ?>">
			<div class="wcb-editor-holder" id="wcb-editor-admin-job-content"></div>
			<textarea
				name="content"
				id="content"
				class="wcb-editor-source"
				rows="1"
				tabindex="-1"
				aria-label="<?php esc_attr_e( 'Job description', 'wp-career-board' ); ?>"
			><?php echo esc_textarea( (string) $post->post_content ); ?></textarea>
		</div>
		<p class="description" style="margin-top:8px;">
			<?php esc_html_e( 'Use the inline toolbar (select text) and the block menu (+) to format - headings, lists, links, quotes.', 'wp-career-board' ); ?>
		</p>
		<?php
	}

	/**
	 * Enqueue Editor.js core, the supported tool bundles, our editor CSS,
	 * and the WCB bootstrap on the wcb_job edit screen only.
	 *
	 * @since 1.1.0
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_editor_assets( string $hook ): void {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'wcb_job' !== $screen->post_type ) {
			return;
		}

		$vendor = WCB_URL . 'assets/js/vendor/editorjs/';

		wp_enqueue_style(
			'wcb-frontend-tokens',
			WCB_URL . 'assets/css/frontend-tokens.css',
			array(),
			WCB_VERSION
		);

		wp_enqueue_style(
			'wcb-editor',
			WCB_URL . 'assets/css/wcb-editor.css',
			array( 'wcb-frontend-tokens' ),
			WCB_VERSION
		);

		wp_enqueue_script( 'editorjs', $vendor . 'editor.umd.js', array(), '2.30.8', true );

		$tools = array(
			'editorjs-header'      => 'header.umd.js',
			'editorjs-list'        => 'list.umd.js',
			'editorjs-quote'       => 'quote.umd.js',
			'editorjs-marker'      => 'marker.umd.js',
			'editorjs-inline-code' => 'inline-code.umd.js',
			'editorjs-delimiter'   => 'delimiter.umd.js',
		);
		foreach ( $tools as $handle => $file ) {
			wp_enqueue_script( $handle, $vendor . $file, array( 'editorjs' ), WCB_VERSION, true );
		}

		wp_enqueue_script(
			'wcb-editor',
			WCB_URL . 'assets/js/wcb-editor.js',
			array_merge( array( 'editorjs' ), array_keys( $tools ) ),
			WCB_VERSION,
			true
		);
	}
}
