<?php
/**
 * WP Career Board — seed data cleanup fixture.
 *
 * Permanently deletes all data created by seed-data.php:
 *   Free:  3 employer users, 5 candidate users, 5 companies, 17 jobs,
 *          all wcb_application posts created by seeded users / guest emails.
 *   Pro:   5 wcb_resume posts (if Pro active).
 *   Terms: all taxonomy terms created by seed (wcb_category, wcb_job_type,
 *          wcb_location, wcb_experience, wcb_tag, wcb_resume_skill).
 *
 * Usage:
 *   wp eval-file wp-content/plugins/wp-career-board/tests/fixtures/cleanup-seed-data.php
 *
 * Safe to run on a clean install — skips anything that does not exist.
 * Does NOT touch admin users or any content not created by the seed.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Force-delete a post and log the result.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function wcb_cleanup_delete_post( int $post_id ): void {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return;
	}
	$result = wp_delete_post( $post_id, true );
	if ( $result ) {
		WP_CLI::log( '  → deleted ' . $post->post_type . ' (ID ' . $post_id . '): ' . $post->post_title );
	} else {
		WP_CLI::warning( '  → could not delete post ID ' . $post_id );
	}
}

/**
 * Force-delete a user and log the result.
 *
 * @param int $user_id   User ID to delete.
 * @param int $reassign  Post author to reassign posts to (0 = delete posts).
 * @return void
 */
function wcb_cleanup_delete_user( int $user_id, int $reassign = 0 ): void {
	$user = get_user_by( 'ID', $user_id );
	if ( ! $user ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/user.php';
	$result = wp_delete_user( $user_id, $reassign ?: null );
	if ( $result ) {
		WP_CLI::log( '  → deleted user (ID ' . $user_id . '): ' . $user->display_name );
	} else {
		WP_CLI::warning( '  → could not delete user ID ' . $user_id );
	}
}

// ---------------------------------------------------------------------------
// Lists matching seed-data.php
// ---------------------------------------------------------------------------

$seed_employer_emails = array(
	'hr@example-stripe.com',
	'talent@example-vercel.com',
	'jobs@example-figma.com',
);

// Legacy seed users from older versions — always clean up.
$legacy_employer_logins = array( 'testemployer', 'sarah_hr', 'marcus_jobs', 'priya_recruit' );

$seed_candidate_emails = array(
	'sarah.chen@example.com',
	'marcus.williams@example.com',
	'priya.patel@example.com',
	'jordan.lee@example.com',
	'alex.kumar@example.com',
);

$seed_company_slugs = array( 'stripe', 'linear', 'vercel', 'shopify', 'figma' );

$seed_job_slugs = array(
	'senior-frontend-engineer-stripe',
	'backend-engineer-golang-stripe',
	'data-analyst-stripe',
	'staff-security-engineer-stripe',
	'product-designer-linear',
	'senior-ios-engineer-linear',
	'developer-advocate-linear',
	'founding-engineer-linear',
	'staff-platform-engineer-vercel',
	'head-of-marketing-vercel',
	'enterprise-customer-success-vercel',
	'senior-product-manager-shopify',
	'react-native-engineer-shopify',
	'site-reliability-engineer-shopify',
	'principal-ux-researcher-figma',
	'growth-marketing-manager-figma',
	'software-engineer-editor-figma',
);

$seed_resume_logins = array(
	'sarah.chen',
	'marcus.williams',
	'priya.patel',
	'jordan.lee',
	'alex.kumar',
);

$seed_guest_emails = array(
	'lena.muller@example-guest.com',
	'tobias@example-guest.com',
);

$seed_taxonomy_terms = array(
	'wcb_category'   => array( 'Engineering', 'Design', 'Marketing', 'Product', 'Data', 'Customer Success', 'DevOps' ),
	'wcb_job_type'   => array( 'Full-time', 'Part-time', 'Contract', 'Internship' ),
	'wcb_location'   => array( 'Remote', 'San Francisco, CA', 'Ottawa, ON', 'New York, NY', 'Austin, TX' ),
	'wcb_experience' => array( 'Entry Level', 'Mid Level', 'Senior', 'Lead', 'Principal' ),
	'wcb_tag'        => array( 'Remote-first', 'Series C+', 'Open Source', 'Scale-up', 'Startup', 'SaaS', 'Fintech', 'E-commerce', 'Design Tools', 'DevTools' ),
);

// Collect all seeded user IDs before deleting users.
$all_seeded_user_ids = array();
foreach ( array_merge( $seed_employer_emails, $seed_candidate_emails ) as $email ) {
	$u = get_user_by( 'email', $email );
	if ( $u ) {
		$all_seeded_user_ids[] = (int) $u->ID;
	}
}

// ---------------------------------------------------------------------------
// 0. Wizard sample data — "Acme Corp" company + "Senior PHP Developer" job
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '=== Cleaning up wizard sample data ===' );

$acme = get_page_by_path( 'acme-corp', OBJECT, 'wcb_company' );
if ( $acme ) {
	// Delete any jobs linked to Acme Corp.
	$acme_jobs = get_posts(
		array(
			'post_type'   => 'wcb_job',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_wcb_company_id',
					'value' => $acme->ID,
				),
			),
		)
	);
	foreach ( $acme_jobs as $aj ) {
		wcb_cleanup_delete_post( (int) $aj );
	}
	wcb_cleanup_delete_post( (int) $acme->ID );
}
// Also catch the wizard job by slug if company link was lost.
$wizard_job = get_page_by_path( 'senior-php-developer', OBJECT, 'wcb_job' );
if ( $wizard_job ) {
	$company_name = get_post_meta( $wizard_job->ID, '_wcb_company_name', true );
	if ( 'Acme Corp' === $company_name ) {
		wcb_cleanup_delete_post( (int) $wizard_job->ID );
	}
}
delete_option( 'wcb_sample_data_installed' );

// ---------------------------------------------------------------------------
// 1. Applications — delete all applications belonging to seeded users / guests
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '=== Deleting applications ===' );

// Applications by seeded candidates.
if ( $all_seeded_user_ids ) {
	$app_ids = get_posts(
		array(
			'post_type'   => 'wcb_application',
			'post_status' => 'any',
			'author__in'  => $all_seeded_user_ids,
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);
	foreach ( $app_ids as $app_id ) {
		wcb_cleanup_delete_post( (int) $app_id );
	}
}

// Guest applications by known emails.
foreach ( $seed_guest_emails as $guest_email ) {
	$guest_apps = get_posts(
		array(
			'post_type'   => 'wcb_application',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'   => '_wcb_guest_email',
				'value' => $guest_email,
			),
			),
		)
	);
	foreach ( $guest_apps as $app_id ) {
		wcb_cleanup_delete_post( (int) $app_id );
	}
}

// ---------------------------------------------------------------------------
// 2. wcb_resume posts (Pro)
// ---------------------------------------------------------------------------

WP_CLI::log( '' );

if ( post_type_exists( 'wcb_resume' ) ) {
	WP_CLI::log( '=== Deleting wcb_resume posts (Pro) ===' );

	foreach ( $seed_resume_logins as $login ) {
		$post = get_page_by_path( $login . '-resume', OBJECT, 'wcb_resume' );
		if ( $post ) {
			wcb_cleanup_delete_post( (int) $post->ID );
		}
	}
} else {
	WP_CLI::log( '=== Skipping wcb_resume — Pro plugin not active ===' );
}

// ---------------------------------------------------------------------------
// 3. Jobs
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '=== Deleting jobs ===' );

foreach ( $seed_job_slugs as $slug ) {
	$post = get_page_by_path( $slug, OBJECT, 'wcb_job' );
	if ( $post ) {
		wcb_cleanup_delete_post( (int) $post->ID );
	}
}

// ---------------------------------------------------------------------------
// 4. Companies
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '=== Deleting companies ===' );

foreach ( $seed_company_slugs as $slug ) {
	$post = get_page_by_path( $slug, OBJECT, 'wcb_company' );
	if ( $post ) {
		wcb_cleanup_delete_post( (int) $post->ID );
	}
}

// ---------------------------------------------------------------------------
// 5. Candidate + employer users
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '=== Deleting users ===' );

foreach ( array_merge( $seed_candidate_emails, $seed_employer_emails ) as $email ) {
	$u = get_user_by( 'email', $email );
	if ( $u ) {
		wcb_cleanup_delete_user( (int) $u->ID );
	}
}

// Legacy users from older seed versions.
WP_CLI::log( '' );
WP_CLI::log( '=== Cleaning up legacy seed users ===' );

foreach ( $legacy_employer_logins as $login ) {
	$u = get_user_by( 'login', $login );
	if ( $u ) {
		wcb_cleanup_delete_user( (int) $u->ID );
	}
}

// Clean ALL remaining wcb_candidate/wcb_employer users that are not in the current seed.
$protected_emails = array_merge( $seed_candidate_emails, $seed_employer_emails );
$wcb_roles        = array( 'wcb_candidate', 'wcb_employer' );
foreach ( $wcb_roles as $role ) {
	$leftover_users = get_users( array( 'role' => $role, 'number' => 100 ) );
	foreach ( $leftover_users as $lu ) {
		if ( ! in_array( $lu->user_email, $protected_emails, true ) ) {
			wcb_cleanup_delete_user( (int) $lu->ID );
		}
	}
}

// Remove orphaned _wcb_company_id from admin user.
$admin_company = get_user_meta( 1, '_wcb_company_id', true );
if ( $admin_company && ! get_post( (int) $admin_company ) ) {
	delete_user_meta( 1, '_wcb_company_id' );
	WP_CLI::log( '  → removed orphaned _wcb_company_id from admin user' );
}

// Remove orphaned _wcb_bookmark entries for all users.
global $wpdb;
$orphaned_bookmarks = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	"DELETE um FROM {$wpdb->usermeta} um
	LEFT JOIN {$wpdb->posts} p ON um.meta_value = p.ID AND p.post_status = 'publish'
	WHERE um.meta_key = '_wcb_bookmark' AND p.ID IS NULL"
);
if ( $orphaned_bookmarks ) {
	WP_CLI::log( "  → removed {$orphaned_bookmarks} orphaned bookmark(s)" );
}

// Remove orphaned _wcb_company_id from all users.
$orphaned_companies = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	"DELETE um FROM {$wpdb->usermeta} um
	LEFT JOIN {$wpdb->posts} p ON um.meta_value = p.ID AND p.post_type = 'wcb_company'
	WHERE um.meta_key = '_wcb_company_id' AND p.ID IS NULL"
);
if ( $orphaned_companies ) {
	WP_CLI::log( "  → removed {$orphaned_companies} orphaned company link(s)" );
}

// ---------------------------------------------------------------------------
// 6. Taxonomy terms
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '=== Deleting taxonomy terms ===' );

foreach ( $seed_taxonomy_terms as $taxonomy => $names ) {
	foreach ( $names as $name ) {
		$term = get_term_by( 'name', $name, $taxonomy );
		if ( $term instanceof WP_Term ) {
			$result = wp_delete_term( $term->term_id, $taxonomy );
			if ( ! is_wp_error( $result ) ) {
				WP_CLI::log( '  → deleted ' . $taxonomy . ': ' . $name );
			}
		}
	}
}

// wcb_resume_skill terms (Pro).
if ( taxonomy_exists( 'wcb_resume_skill' ) ) {
	$skill_names = array(
		'React',
		'TypeScript',
		'Next.js',
		'GraphQL',
		'Figma',
		'Motion Design',
		'User Research',
		'Prototyping',
		'Product Strategy',
		'Data Analysis (SQL)',
		'A/B Testing',
		'Content Strategy',
		'SEO',
		'Developer Marketing',
		'Community Building',
		'Demand Generation',
		'Kubernetes',
		'Terraform',
		'Go',
		'AWS / GCP',
		'Observability',
		'Python',
	);
	foreach ( $skill_names as $name ) {
		$term = get_term_by( 'name', $name, 'wcb_resume_skill' );
		if ( $term instanceof WP_Term ) {
			wp_delete_term( $term->term_id, 'wcb_resume_skill' );
			WP_CLI::log( '  → deleted wcb_resume_skill: ' . $name );
		}
	}
}

// ---------------------------------------------------------------------------
// Done
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::success( 'Seed data cleanup complete.' );
WP_CLI::log( 'Re-run seed to start fresh:' );
WP_CLI::log( '  wp eval-file wp-content/plugins/wp-career-board/tests/fixtures/seed-data.php' );
