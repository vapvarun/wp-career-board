<?php
/**
 * REST controller for the wcb_resume post type.
 *
 * @package WP_Career_Board
 */

declare(strict_types=1);

namespace WCB\Modules\Candidates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enforces the per-candidate listing opt-in on core wp/v2 resume reads.
 *
 * wcb_resume is registered public + show_in_rest so the block editor and the
 * single profile template work, which hands core two read paths this plugin
 * did not write: the collection and a read by id.
 *
 * The collection was narrowed in 1.7.1 with `rest_wcb_resume_query`. A read by
 * id is NOT a query - it goes through check_read_permission(), which returns
 * true for any published post - so `/wp/v2/wcb_resume/{id}` kept serving the
 * title, slug and permalink of a candidate who never ticked "list my resume".
 * Ids are enumerable, so that walks the whole table.
 *
 * check_read_permission() is the override rather than get_item_permissions_check()
 * because core routes every read through it: the single item, each row of a
 * collection, and embedded/linked reads from another response. One decision,
 * every path.
 *
 * @since 1.7.1
 */
class ResumeRestController extends \WP_REST_Posts_Controller {

	/**
	 * Whether a given resume may be read by the current user.
	 *
	 * @since 1.7.1
	 *
	 * @param  \WP_Post $post Post to check.
	 * @return bool
	 */
	public function check_read_permission( $post ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- core's signature.
		if ( ! parent::check_read_permission( $post ) ) {
			return false;
		}

		return CandidatesModule::resume_is_readable( (int) $post->ID );
	}
}
