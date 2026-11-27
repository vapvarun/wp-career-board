<?php
/**
 * WP Career Board — seed data cleanup fixture.
 *
 * Permanently deletes exactly what seed-data.php created: every post, user
 * and term it tagged with the `_wcb_test_seed` marker when it made them.
 * Anything the seed found already on the site (a matching email, slug or
 * term name) was reused, not tagged, so it is never touched here.
 *
 * Until 1.8.6 this file deleted by seed email, slug and term name, and swept
 * every wcb_candidate / wcb_employer user it did not recognise. On a shared
 * site that removed real members, their posts and the site's own terms.
 *
 * Usage:
 *   wp eval-file wp-content/plugins/wp-career-board/tests/fixtures/cleanup-seed-data.php
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/seed-marker.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

// Posts first (applications, resumes, jobs, companies), any status, trash included.
WP_CLI::log( '=== Deleting seeded posts ===' );
$wcb_seed_posts = get_posts(
	array(
		'post_type'      => 'any',
		'post_status'    => array_keys( get_post_stati() ),
		'meta_key'       => WCB_TEST_SEED_MARKER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $wcb_seed_posts as $wcb_id ) {
	$wcb_post = get_post( (int) $wcb_id );
	wp_delete_post( (int) $wcb_id, true );
	WP_CLI::log( '  → deleted ' . $wcb_post->post_type . ' (ID ' . $wcb_id . '): ' . $wcb_post->post_title );
}

WP_CLI::log( '=== Deleting seeded users ===' );
foreach ( get_users( array( 'meta_key' => WCB_TEST_SEED_MARKER ) ) as $wcb_user ) { // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	wp_delete_user( (int) $wcb_user->ID );
	WP_CLI::log( '  → deleted user (ID ' . $wcb_user->ID . '): ' . $wcb_user->display_name );
}

WP_CLI::log( '=== Deleting seeded terms ===' );
$wcb_seed_terms = get_terms(
	array(
		'taxonomy'   => get_taxonomies(),
		'hide_empty' => false,
		'meta_key'   => WCB_TEST_SEED_MARKER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	)
);
foreach ( is_array( $wcb_seed_terms ) ? $wcb_seed_terms : array() as $wcb_term ) {
	wp_delete_term( $wcb_term->term_id, $wcb_term->taxonomy );
	WP_CLI::log( '  → deleted ' . $wcb_term->taxonomy . ': ' . $wcb_term->name );
}

WP_CLI::success( 'Seed data cleanup complete: only rows the seed created were removed.' );
