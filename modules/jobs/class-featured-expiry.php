<?php
/**
 * Featured-listing timed expiry cron.
 *
 * Sweeps wcb_job posts whose `_wcb_featured` flag was set more than
 * `apply_featured_days` (default 30) days ago and clears the flag, so the
 * "Featured" upgrade SKU has a meaningful time-bound value.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clears stale `_wcb_featured` flags on a daily cadence.
 *
 * @since 1.1.0
 */
final class FeaturedExpiry {

	/**
	 * Cron hook name.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const HOOK = 'wcb_expire_featured_jobs';

	/**
	 * Postmeta key recording when a job was promoted to featured.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const SINCE_META = '_wcb_featured_since';

	/**
	 * Boot hooks.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'maybe_schedule' ) );
		add_action( self::HOOK, array( $this, 'sweep' ) );
		add_action( 'updated_post_meta', array( $this, 'maybe_stamp_featured_since' ), 10, 4 );
		add_action( 'added_post_meta', array( $this, 'maybe_stamp_featured_since' ), 10, 4 );
	}

	/**
	 * Schedule the daily cron once.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function maybe_schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * When `_wcb_featured` is set to '1', record the timestamp for later expiry.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function maybe_stamp_featured_since( int $meta_id, int $post_id, string $meta_key, $meta_value ): void {
		if ( '_wcb_featured' !== $meta_key ) {
			return;
		}
		if ( '1' === (string) $meta_value ) {
			if ( ! get_post_meta( $post_id, self::SINCE_META, true ) ) {
				update_post_meta( $post_id, self::SINCE_META, gmdate( 'Y-m-d H:i:s' ) );
			}
		} else {
			delete_post_meta( $post_id, self::SINCE_META );
		}
	}

	/**
	 * Clear `_wcb_featured` on jobs whose featured-since timestamp is older
	 * than the configured window.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function sweep(): void {
		$settings = (array) get_option( 'wcb_settings', array() );
		$days     = isset( $settings['apply_featured_days'] ) ? (int) $settings['apply_featured_days'] : 30;
		$days     = max( 1, min( 365, $days ) );

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$jobs = get_posts(
			array(
				'post_type'      => 'wcb_job',
				'post_status'    => array( 'publish', 'pending' ),
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded by featured jobs older than cutoff.
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => '_wcb_featured',
						'value' => '1',
					),
					array(
						'key'     => self::SINCE_META,
						'value'   => $cutoff,
						'compare' => '<=',
						'type'    => 'DATETIME',
					),
				),
			)
		);

		foreach ( $jobs as $job_id ) {
			update_post_meta( (int) $job_id, '_wcb_featured', '0' );
			delete_post_meta( (int) $job_id, self::SINCE_META );
			/**
			 * Fired after a featured job's flag has expired.
			 *
			 * @since 1.1.0
			 *
			 * @param int $job_id Job post id.
			 */
			do_action( 'wcb_featured_expired', (int) $job_id );
		}
	}
}
