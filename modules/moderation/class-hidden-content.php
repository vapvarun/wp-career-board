<?php
/**
 * Hidden content - take a member's listings off the site and put them back.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Moderation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hides jobs, company pages and resumes, remembering what each one was.
 *
 * Two reasons hide content: a ban (every public post the member authored,
 * restored exactly on unban, owner decision D10) and reports (a job that
 * reached the auto-hide threshold, restored when the flags are dismissed).
 * A hidden post keeps its original status in `_wcb_hidden_status` and the
 * reason in `_wcb_hidden_by`, so a restore only undoes its own hide.
 *
 * @since 1.8.0
 */
final class HiddenContent {

	/**
	 * Post meta: the status the post had before it was hidden.
	 *
	 * @var string
	 */
	public const META_STATUS = '_wcb_hidden_status';

	/**
	 * Post meta: why it was hidden ('ban' or 'reports').
	 *
	 * @var string
	 */
	public const META_BY = '_wcb_hidden_by';

	/**
	 * Public post types a member owns.
	 *
	 * @var string[]
	 */
	private const POST_TYPES = array( 'wcb_job', 'wcb_company', 'wcb_resume' );

	/**
	 * The ban flag every ban writer sets (Employers and Candidates lists).
	 *
	 * @var string
	 */
	private const BAN_META = '_wcb_employer_banned';

	/**
	 * Hook the ban flag itself, so every writer hides and restores.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'added_user_meta', array( self::class, 'on_ban_set' ), 10, 4 );
		add_action( 'updated_user_meta', array( self::class, 'on_ban_set' ), 10, 4 );
		add_action( 'deleted_user_meta', array( self::class, 'on_ban_lifted' ), 10, 3 );
		// Someone changed a hidden post's status by hand (approved, trashed,
		// edited): it is theirs now, never ours to restore.
		add_action(
			'transition_post_status',
			static function ( string $new_status, string $old_status, \WP_Post $post ): void {
				if ( $new_status !== $old_status && in_array( $post->post_type, self::POST_TYPES, true ) ) {
					delete_post_meta( $post->ID, self::META_STATUS );
					delete_post_meta( $post->ID, self::META_BY );
				}
			},
			10,
			3
		);
	}

	/**
	 * A member was banned: hide their live and pending posts.
	 *
	 * @param int|array $meta_id Meta ID(s).
	 * @param int       $user_id User.
	 * @param string    $key     Meta key.
	 * @param mixed     $value   Meta value.
	 * @return void
	 */
	public static function on_ban_set( $meta_id, int $user_id, string $key, $value ): void {
		unset( $meta_id );
		if ( self::BAN_META !== $key || '1' !== (string) $value ) {
			return;
		}
		self::hide(
			get_posts(
				array(
					'post_type'      => self::POST_TYPES,
					'post_status'    => array( 'publish', 'pending' ),
					'author'         => $user_id,
					'fields'         => 'ids',
					'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- one member's posts.
					'no_found_rows'  => true,
				)
			),
			'ban',
			'draft'
		);
	}

	/**
	 * A ban was lifted: put back exactly what the ban hid.
	 *
	 * @param array  $meta_ids Meta IDs.
	 * @param int    $user_id  User.
	 * @param string $key      Meta key.
	 * @return void
	 */
	public static function on_ban_lifted( array $meta_ids, int $user_id, string $key ): void {
		unset( $meta_ids );
		if ( self::BAN_META !== $key ) {
			return;
		}
		self::restore(
			get_posts(
				array(
					'post_type'      => self::POST_TYPES,
					'post_status'    => 'any',
					'author'         => $user_id,
					'fields'         => 'ids',
					'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- one member's posts.
					'no_found_rows'  => true,
					'meta_key'       => self::META_BY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => 'ban', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			),
			'ban'
		);
	}

	/**
	 * Hide live or pending posts.
	 *
	 * @param int[]  $ids Post IDs.
	 * @param string $by  Reason: 'ban' or 'reports'.
	 * @param string $to  Status while hidden: 'draft' (ban) or 'pending' (awaiting review).
	 * @return void
	 */
	public static function hide( array $ids, string $by, string $to ): void {
		global $wpdb;
		$changed = array();
		// ponytail: about four queries a post, in the request; move to a background batch if members with thousands of live posts get banned.
		foreach ( $ids as $id ) {
			$status = (string) get_post_status( (int) $id );
			if ( ! in_array( $status, array( 'publish', 'pending' ), true ) ) {
				continue;
			}
			update_post_meta( (int) $id, self::META_STATUS, $status );
			update_post_meta( (int) $id, self::META_BY, $by );
			$wpdb->update( $wpdb->posts, array( 'post_status' => $to ), array( 'ID' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- see changed().
			$changed[] = (int) $id;
		}
		self::changed( $changed );
	}

	/**
	 * Restore posts this reason hid, unless someone has changed them since.
	 *
	 * @param int[]  $ids Post IDs.
	 * @param string $by  Reason: 'ban' or 'reports'.
	 * @return void
	 */
	public static function restore( array $ids, string $by ): void {
		global $wpdb;
		$changed = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( get_post_meta( $id, self::META_BY, true ) !== $by ) {
				continue;
			}
			$status = (string) get_post_meta( $id, self::META_STATUS, true );
			delete_post_meta( $id, self::META_STATUS );
			delete_post_meta( $id, self::META_BY );
			$wpdb->update( $wpdb->posts, array( 'post_status' => '' !== $status ? $status : 'publish' ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- see changed().
			$changed[] = $id;
		}
		self::changed( $changed );
	}

	/**
	 * Why a post is hidden: 'ban', 'reports' or '' when it is not.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function reason( int $post_id ): string {
		return (string) get_post_meta( $post_id, self::META_BY, true );
	}

	/**
	 * Refresh caches after a status change made without transition hooks.
	 *
	 * The change skips the status-transition hooks on purpose: they charge
	 * job credits, email employers and send job alerts, none of which a hide
	 * or a restore should do. The list caches (REST jobs and companies, the
	 * job feed, resumes) key their versions on save_post_{type}, whose other
	 * listeners are nonce-guarded or idempotent, so fire it once per type.
	 *
	 * @param int[] $ids Changed post IDs.
	 * @return void
	 */
	private static function changed( array $ids ): void {
		$fired = array();
		foreach ( $ids as $id ) {
			clean_post_cache( $id );
			$post = get_post( $id );
			if ( ! $post instanceof \WP_Post || isset( $fired[ $post->post_type ] ) ) {
				continue;
			}
			$fired[ $post->post_type ] = true;
			do_action( "save_post_{$post->post_type}", $post->ID, $post, true ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own hook.
		}
	}
}
