<?php
/**
 * A job, company, resume or board goes live only with a title.
 *
 * @package WP_Career_Board
 * @since   1.8.2
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registered where every write converges (`wp_insert_post_data`), so the
 * wp-admin editors, imports and any `wp_insert_post()` call get the same
 * rule the plugin's own REST routes already apply.
 *
 * Publishing or submitting an untitled record saves it as a draft and says
 * why; the block editor gets the refusal as an error instead. A record that
 * was already live keeps its status, so no older data is unpublished.
 *
 * @since 1.8.2
 */
final class RequiredTitle {

	/**
	 * Statuses that need a title.
	 *
	 * @var string[]
	 */
	private const LIVE = array( 'publish', 'pending', 'future' );

	/**
	 * Query arg that carries the notice through the editor's redirect.
	 *
	 * @var string
	 */
	private const NOTICE_ARG = 'wcb_title_missing';

	/**
	 * Set when the current save was held back as a draft.
	 *
	 * @var bool
	 */
	private static bool $held = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_filter( 'wp_insert_post_data', array( self::class, 'hold_untitled' ), 5, 2 );
		add_filter( 'redirect_post_location', array( self::class, 'carry_notice' ) );
		add_action( 'admin_notices', array( self::class, 'notice' ) );
		add_action(
			'init',
			static function (): void {
				foreach ( self::post_types() as $type ) {
					add_filter( "rest_pre_insert_{$type}", array( self::class, 'refuse_untitled' ), 10, 2 );
				}
			},
			99
		);
	}

	/**
	 * Post types that need a title to go live.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		/**
		 * Filter the post types that cannot be published without a title.
		 *
		 * @since 1.8.2
		 *
		 * @param string[] $types Post types.
		 */
		return (array) apply_filters( 'wcb_title_required_post_types', array( 'wcb_job', 'wcb_company', 'wcb_resume', 'wcb_board' ) );
	}

	/**
	 * Whether a save takes an untitled record live that was not live before.
	 *
	 * @param string $type   Post type.
	 * @param string $status Status being saved.
	 * @param string $title  Title being saved.
	 * @param int    $id     Post ID, 0 for a new post.
	 * @return bool
	 */
	private static function goes_live_untitled( string $type, string $status, string $title, int $id ): bool {
		return in_array( $type, self::post_types(), true )
			&& in_array( $status, self::LIVE, true )
			&& '' === trim( $title )
			&& ! ( $id > 0 && in_array( (string) get_post_status( $id ), self::LIVE, true ) );
	}

	/**
	 * `wp_insert_post_data`: save an untitled record as a draft.
	 *
	 * @param array<string, mixed> $data    Post data.
	 * @param array<string, mixed> $postarr Raw post array.
	 * @return array<string, mixed>
	 */
	public static function hold_untitled( array $data, array $postarr ): array {
		if ( self::goes_live_untitled( (string) ( $data['post_type'] ?? '' ), (string) ( $data['post_status'] ?? '' ), (string) ( $data['post_title'] ?? '' ), (int) ( $postarr['ID'] ?? 0 ) ) ) {
			$data['post_status'] = 'draft';
			self::$held          = true;
		}
		return $data;
	}

	/**
	 * `rest_pre_insert_{type}`: the block editor shows the refusal.
	 *
	 * @param \stdClass        $prepared Prepared post.
	 * @param \WP_REST_Request $request  Request.
	 * @return \stdClass|\WP_Error
	 */
	public static function refuse_untitled( $prepared, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- filter signature.
		$id     = (int) ( $prepared->ID ?? 0 );
		$type   = (string) ( $prepared->post_type ?? get_post_type( $id ) );
		$status = (string) ( $prepared->post_status ?? ( $id ? get_post_status( $id ) : 'draft' ) );
		$title  = (string) ( $prepared->post_title ?? ( $id ? get_post_field( 'post_title', $id ) : '' ) );
		if ( self::goes_live_untitled( $type, $status, $title, $id ) ) {
			return new \WP_Error( 'wcb_missing_title', __( 'Add a title before publishing.', 'wp-career-board' ), array( 'status' => 400 ) );
		}
		return $prepared;
	}

	/**
	 * `redirect_post_location`: carry the notice to the edit screen.
	 *
	 * @param string $location Redirect URL.
	 * @return string
	 */
	public static function carry_notice( string $location ): string {
		return self::$held ? add_query_arg( self::NOTICE_ARG, '1', remove_query_arg( 'message', $location ) ) : $location;
	}

	/**
	 * Explain the draft on the edit screen.
	 *
	 * @return void
	 */
	public static function notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag only.
		if ( empty( $_GET[ self::NOTICE_ARG ] ) ) {
			return;
		}
		echo '<div class="notice notice-error wcb-notice is-dismissible"><p>' . esc_html__( 'Add a title before publishing. It was saved as a draft.', 'wp-career-board' ) . '</p></div>';
	}
}
