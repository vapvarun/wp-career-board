<?php
/**
 * WP Career Board — seed data fixture.
 *
 * Creates realistic test data:
 *   Free:  3 employer users, 5 companies, 17 jobs (15 published + 2 pending),
 *          5 candidate users (wcb_candidate role), bookmarks, 13 applications
 *          (11 user + 2 guest) with varied statuses.
 *   Pro:   5 wcb_resume CPT posts with full section data + wcb_resume_skill
 *          taxonomy terms.
 *
 * Usage:
 *   wp eval-file wp-content/plugins/wp-career-board/tests/fixtures/seed-data.php
 *
 * Safe to run multiple times — skips records that already exist by slug/email.
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
 * Return a term ID, creating the term if it does not exist.
 *
 * @param string $name     Term name.
 * @param string $taxonomy Taxonomy slug.
 * @return int Term ID.
 */
function wcb_seed_term( string $name, string $taxonomy ): int {
	$existing = get_term_by( 'name', $name, $taxonomy );
	if ( $existing instanceof WP_Term ) {
		return $existing->term_id;
	}
	$result = wp_insert_term( $name, $taxonomy );
	return is_wp_error( $result ) ? 0 : (int) $result['term_id'];
}

/**
 * Create a post if no post with the same slug + type exists.
 *
 * @param array<string,mixed> $args wp_insert_post args.
 * @return int Post ID (0 on skip).
 */
function wcb_seed_post( array $args ): int {
	if ( ! empty( $args['post_name'] ) ) {
		$existing = get_page_by_path( $args['post_name'], OBJECT, $args['post_type'] ?? 'post' );
		if ( $existing ) {
			WP_CLI::log( '  → skip (exists): ' . $args['post_title'] );
			return (int) $existing->ID;
		}
	}
	$id = wp_insert_post( $args, true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( 'Could not create post "' . $args['post_title'] . '": ' . $id->get_error_message() );
		return 0;
	}
	WP_CLI::log( '  → created: ' . $args['post_title'] . ' (ID ' . $id . ')' );
	return $id;
}

/**
 * Create a user if the email is not already registered.
 *
 * @param array<string,mixed> $args wp_insert_user args.
 * @return int User ID (0 on skip).
 */
function wcb_seed_user( array $args ): int {
	if ( email_exists( $args['user_email'] ) ) {
		$user = get_user_by( 'email', $args['user_email'] );
		WP_CLI::log( '  → skip (exists): ' . $args['user_email'] );
		return $user ? (int) $user->ID : 0;
	}
	$id = wp_insert_user( $args );
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( 'Could not create user "' . $args['user_login'] . '": ' . $id->get_error_message() );
		return 0;
	}
	WP_CLI::log( '  → created user: ' . $args['display_name'] . ' (ID ' . $id . ')' );
	return $id;
}

/**
 * Look up a post ID by slug and post type.
 *
 * @param string $slug      Post slug.
 * @param string $post_type Post type.
 * @return int Post ID (0 if not found).
 */
function wcb_seed_get_post_id( string $slug, string $post_type ): int {
	$post = get_page_by_path( $slug, OBJECT, $post_type );
	return $post ? (int) $post->ID : 0;
}

$admin_id = (int) ( get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
)[0]->ID ?? 1 );

// ---------------------------------------------------------------------------
// 1. Taxonomy terms
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '=== Creating taxonomy terms ===' );

$terms = array(
	'wcb_category'   => array( 'Engineering', 'Design', 'Marketing', 'Product', 'Data', 'Customer Success', 'DevOps' ),
	'wcb_job_type'   => array( 'Full-time', 'Part-time', 'Contract', 'Internship' ),
	'wcb_location'   => array( 'Remote', 'San Francisco, CA', 'Ottawa, ON', 'New York, NY', 'Austin, TX' ),
	'wcb_experience' => array( 'Entry Level', 'Mid Level', 'Senior', 'Lead', 'Principal' ),
	'wcb_tag'        => array( 'Remote-first', 'Series C+', 'Open Source', 'Scale-up', 'Startup', 'SaaS', 'Fintech', 'E-commerce', 'Design Tools', 'DevTools' ),
);

$term_ids = array();
foreach ( $terms as $tax_slug => $term_names ) {
	$term_ids[ $tax_slug ] = array();
	foreach ( $term_names as $name ) {
		$term_id                        = wcb_seed_term( $name, $tax_slug );
		$term_ids[ $tax_slug ][ $name ] = $term_id;
		WP_CLI::log( '  ' . $tax_slug . ': ' . $name . ' (term ' . $term_id . ')' );
	}
}

// ---------------------------------------------------------------------------
// 2. Employer users
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '=== Creating employer users ===' );

$employers_data = array(
	array(
		'login'        => 'employer.stripe',
		'email'        => 'hr@example-stripe.com',
		'display_name' => 'Stripe Recruiting',
		'first_name'   => 'Stripe',
		'last_name'    => 'HR',
		'companies'    => array( 'stripe', 'linear' ),
	),
	array(
		'login'        => 'employer.vercel',
		'email'        => 'talent@example-vercel.com',
		'display_name' => 'Vercel Talent',
		'first_name'   => 'Vercel',
		'last_name'    => 'Talent',
		'companies'    => array( 'vercel', 'shopify' ),
	),
	array(
		'login'        => 'employer.figma',
		'email'        => 'jobs@example-figma.com',
		'display_name' => 'Figma Recruiting',
		'first_name'   => 'Figma',
		'last_name'    => 'Recruiting',
		'companies'    => array( 'figma' ),
	),
);

$employer_ids = array();
foreach ( $employers_data as $emp ) {
	$uid = wcb_seed_user(
		array(
			'user_login'   => $emp['login'],
			'user_email'   => $emp['email'],
			'user_pass'    => 'password',
			'display_name' => $emp['display_name'],
			'first_name'   => $emp['first_name'],
			'last_name'    => $emp['last_name'],
			'role'         => 'wcb_employer',
		)
	);

	if ( $uid ) {
		foreach ( $emp['companies'] as $co_slug ) {
			$employer_ids[ $co_slug ] = $uid;
		}
	}
}

// ---------------------------------------------------------------------------
// 3. Companies
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '=== Creating companies ===' );

$companies_data = array(
	array(
		'slug'    => 'stripe',
		'title'   => 'Stripe',
		'content' => 'Stripe is a technology company that builds economic infrastructure for the internet. Businesses of every size—from new startups to public companies—use our software to accept payments and manage their businesses online.',
		'meta'    => array(
			'_wcb_tagline'      => 'Financial infrastructure for the internet',
			'_wcb_website'      => 'https://stripe.com',
			'_wcb_industry'     => 'finance',
			'_wcb_company_size' => '5000+',
			'_wcb_hq_location'  => 'San Francisco, CA',
			'_wcb_founded'      => '2010',
			'_wcb_company_type' => 'Private',
			'_wcb_linkedin'     => 'https://linkedin.com/company/stripe',
			'_wcb_twitter'      => 'https://twitter.com/stripe',
		),
	),
	array(
		'slug'    => 'linear',
		'title'   => 'Linear',
		'content' => 'Linear is a purpose-built tool for planning and building products. Thousands of high-performance teams use Linear to streamline software projects, sprints, tasks, and bug tracking.',
		'meta'    => array(
			'_wcb_tagline'      => 'The issue tracker built for high-performance teams',
			'_wcb_website'      => 'https://linear.app',
			'_wcb_industry'     => 'technology',
			'_wcb_company_size' => '51-200',
			'_wcb_hq_location'  => 'Remote',
			'_wcb_founded'      => '2019',
			'_wcb_company_type' => 'Private',
			'_wcb_linkedin'     => 'https://linkedin.com/company/linear-app',
			'_wcb_twitter'      => 'https://twitter.com/linear',
		),
	),
	array(
		'slug'    => 'vercel',
		'title'   => 'Vercel',
		'content' => 'Vercel provides the developer tools and cloud infrastructure to build, scale, and secure a faster, more personalized web.',
		'meta'    => array(
			'_wcb_tagline'      => 'Develop. Preview. Ship.',
			'_wcb_website'      => 'https://vercel.com',
			'_wcb_industry'     => 'technology',
			'_wcb_company_size' => '201-500',
			'_wcb_hq_location'  => 'Remote',
			'_wcb_founded'      => '2015',
			'_wcb_company_type' => 'Private',
			'_wcb_linkedin'     => 'https://linkedin.com/company/vercel',
			'_wcb_twitter'      => 'https://twitter.com/vercel',
		),
	),
	array(
		'slug'    => 'shopify',
		'title'   => 'Shopify',
		'content' => 'Shopify is a leading global commerce company, providing trusted tools to start, grow, market, and manage a retail business of any size.',
		'meta'    => array(
			'_wcb_tagline'      => 'Making commerce better for everyone',
			'_wcb_website'      => 'https://shopify.com',
			'_wcb_industry'     => 'retail',
			'_wcb_company_size' => '5000+',
			'_wcb_hq_location'  => 'Ottawa, ON',
			'_wcb_founded'      => '2006',
			'_wcb_company_type' => 'Public',
			'_wcb_linkedin'     => 'https://linkedin.com/company/shopify',
			'_wcb_twitter'      => 'https://twitter.com/shopify',
		),
	),
	array(
		'slug'    => 'figma',
		'title'   => 'Figma',
		'content' => 'Figma is a collaborative web application for interface design. Figma helps teams create, test, and ship better designs from start to finish—all in one platform.',
		'meta'    => array(
			'_wcb_tagline'      => 'Design together, build together',
			'_wcb_website'      => 'https://figma.com',
			'_wcb_industry'     => 'design',
			'_wcb_company_size' => '501-1000',
			'_wcb_hq_location'  => 'San Francisco, CA',
			'_wcb_founded'      => '2012',
			'_wcb_company_type' => 'Private',
			'_wcb_linkedin'     => 'https://linkedin.com/company/figma',
			'_wcb_twitter'      => 'https://twitter.com/figma',
		),
	),
);

$company_ids = array();
foreach ( $companies_data as $co ) {
	$employer_uid = $employer_ids[ $co['slug'] ] ?? $admin_id;

	$wcb_id = wcb_seed_post(
		array(
			'post_type'    => 'wcb_company',
			'post_title'   => $co['title'],
			'post_name'    => $co['slug'],
			'post_content' => $co['content'],
			'post_status'  => 'publish',
			'post_author'  => $employer_uid,
		)
	);

	if ( $wcb_id ) {
		foreach ( $co['meta'] as $key => $value ) {
			update_post_meta( $wcb_id, $key, $value );
		}
		// Link company to its employer user (both directions).
		update_post_meta( $wcb_id, '_wcb_user_id', $employer_uid );
		// Only set _wcb_company_id if not already set (first company = primary).
		if ( ! get_user_meta( $employer_uid, '_wcb_company_id', true ) ) {
			update_user_meta( $employer_uid, '_wcb_company_id', $wcb_id );
		}
		$company_ids[ $co['slug'] ] = $wcb_id;
	}
}

// ---------------------------------------------------------------------------
// 4. Jobs
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '=== Creating jobs ===' );

$deadline_3m = gmdate( 'Y-m-d', strtotime( '+3 months' ) );
$deadline_6w = gmdate( 'Y-m-d', strtotime( '+6 weeks' ) );
$deadline_2m = gmdate( 'Y-m-d', strtotime( '+2 months' ) );

$jobs_data = array(

	// ---- Stripe --------------------------------------------------------
	array(
		'slug'       => 'senior-frontend-engineer-stripe',
		'title'      => 'Senior Frontend Engineer',
		'company'    => 'stripe',
		'status'     => 'publish',
		'content'    => "We are looking for a Senior Frontend Engineer to join our Payments UI team.\n\n**What you'll do:**\n- Build and maintain high-performance, accessible React components used by millions of merchants worldwide\n- Partner with designers and product managers to translate specs into pixel-perfect implementations\n- Own end-to-end features from design review through production release\n- Champion frontend quality by writing thorough unit and integration tests\n\n**You'll love this role if:**\n- You care deeply about web performance and Core Web Vitals\n- You think in design systems and component APIs\n- You enjoy debugging gnarly browser compatibility issues",
		'meta'       => array(
			'_wcb_salary_min'      => '170000',
			'_wcb_salary_max'      => '220000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_3m,
			'_wcb_featured'        => '1',
			'_wcb_apply_email'     => 'jobs@stripe.com',
		),
		'taxonomies' => array(
			'wcb_category'   => array( 'Engineering' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote', 'San Francisco, CA' ),
			'wcb_experience' => array( 'Senior' ),
			'wcb_tag'        => array( 'Remote-first', 'Fintech', 'Series C+' ),
		),
	),
	array(
		'slug'       => 'backend-engineer-golang-stripe',
		'title'      => 'Backend Engineer — Golang',
		'company'    => 'stripe',
		'status'     => 'publish',
		'content'    => "Join our Payouts infrastructure team and help process billions of dollars for merchants globally.\n\n**What you'll do:**\n- Design and build distributed systems that process financial transactions at massive scale\n- Work with Go, Ruby, and Scala in a services-oriented architecture\n- Drive reliability improvements — we care deeply about 99.999% uptime\n\n**Requirements:**\n- 4+ years of backend engineering experience\n- Strong understanding of distributed systems, CAP theorem, and consistency guarantees",
		'meta'       => array(
			'_wcb_salary_min'      => '180000',
			'_wcb_salary_max'      => '240000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '0',
			'_wcb_deadline'        => $deadline_2m,
			'_wcb_featured'        => '0',
			'_wcb_apply_email'     => 'jobs@stripe.com',
		),
		'taxonomies' => array(
			'wcb_category'   => array( 'Engineering' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'San Francisco, CA' ),
			'wcb_experience' => array( 'Mid Level', 'Senior' ),
			'wcb_tag'        => array( 'Fintech' ),
		),
	),
	array(
		'slug'       => 'data-analyst-stripe',
		'title'      => 'Data Analyst — Revenue Insights',
		'company'    => 'stripe',
		'status'     => 'publish',
		'content'    => "Help Stripe understand its business by building the dashboards and analyses leadership uses every week.\n\n**Responsibilities:**\n- Own recurring business reports for executive leadership\n- Explore large datasets using SQL and Python to surface actionable insights\n- Partner with Finance and GTM teams to answer complex revenue questions\n- Build and maintain self-serve analytics dashboards in Looker",
		'meta'       => array(
			'_wcb_salary_min'      => '120000',
			'_wcb_salary_max'      => '155000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_6w,
			'_wcb_featured'        => '0',
			'_wcb_apply_email'     => 'jobs@stripe.com',
		),
		'taxonomies' => array(
			'wcb_category'   => array( 'Data' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote' ),
			'wcb_experience' => array( 'Mid Level' ),
			'wcb_tag'        => array( 'Remote-first', 'Fintech' ),
		),
	),
	// Pending — tests moderation queue.
	array(
		'slug'       => 'staff-security-engineer-stripe',
		'title'      => 'Staff Security Engineer',
		'company'    => 'stripe',
		'status'     => 'pending',
		'content'    => "Lead Stripe's product security programme and protect financial infrastructure at scale.\n\n**Responsibilities:**\n- Define security architecture for new payment products\n- Drive threat modelling across engineering teams\n- Lead incident response for critical security events\n- Build internal security tools and detection infrastructure",
		'meta'       => array(
			'_wcb_salary_min'      => '210000',
			'_wcb_salary_max'      => '275000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_3m,
			'_wcb_featured'        => '0',
			'_wcb_apply_email'     => 'jobs@stripe.com',
		),
		'taxonomies' => array(
			'wcb_category'   => array( 'Engineering' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote' ),
			'wcb_experience' => array( 'Lead', 'Principal' ),
			'wcb_tag'        => array( 'Fintech', 'Series C+' ),
		),
	),

	// ---- Linear --------------------------------------------------------
	array(
		'slug'       => 'product-designer-linear',
		'title'      => 'Product Designer',
		'company'    => 'linear',
		'status'     => 'publish',
		'content'    => "Linear is hiring a Product Designer who is obsessed with craft and deeply technical.\n\n**What you'll work on:**\n- Own end-to-end design for core product areas — from discovery through polish\n- Shape the visual language of Linear's desktop and web apps\n- Work closely with engineers who care as much about design quality as you do",
		'meta'       => array(
			'_wcb_salary_min'      => '150000',
			'_wcb_salary_max'      => '190000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_3m,
			'_wcb_featured'        => '1',
			'_wcb_apply_email'     => 'careers@linear.app',
		),
		'taxonomies' => array(
			'wcb_category'   => array( 'Design' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote' ),
			'wcb_experience' => array( 'Senior' ),
			'wcb_tag'        => array( 'Remote-first', 'SaaS', 'Startup' ),
		),
	),
	array(
		'slug'       => 'senior-ios-engineer-linear',
		'title'      => 'Senior iOS Engineer',
		'company'    => 'linear',
		'status'     => 'publish',
		'content'    => "Build Linear's iOS app used by thousands of engineering teams daily.\n\n**Requirements:**\n- 5+ years of iOS development with Swift\n- Deep knowledge of UIKit, SwiftUI, and Combine\n- Experience shipping and maintaining apps with 100k+ users",
		'meta'       => array(
			'_wcb_salary_min'      => '160000',
			'_wcb_salary_max'      => '210000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_2m,
			'_wcb_featured'        => '0',
			'_wcb_apply_email'     => 'careers@linear.app',
		),
		'taxonomies' => array(
			'wcb_category'   => array( 'Engineering' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote' ),
			'wcb_experience' => array( 'Senior' ),
			'wcb_tag'        => array( 'Remote-first', 'SaaS' ),
		),
	),
	array(
		'slug'       => 'developer-advocate-linear',
		'title'      => 'Developer Advocate',
		'company'    => 'linear',
		'status'     => 'publish',
		'content'    => "Be the bridge between Linear's engineering team and the developer community.\n\n**Responsibilities:**\n- Create technical content (blog posts, tutorials, videos) showing how engineering teams use Linear\n- Speak at conferences and developer meetups\n- Build integrations and demo projects that showcase Linear's API",
		'meta'       => array(
			'_wcb_salary_min'      => '130000',
			'_wcb_salary_max'      => '165000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_6w,
			'_wcb_featured'        => '0',
			'_wcb_apply_email'     => 'careers@linear.app',
		),
		'taxonomies' => array(
			'wcb_category'   => array( 'Marketing' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote' ),
			'wcb_experience' => array( 'Mid Level' ),
			'wcb_tag'        => array( 'Remote-first', 'DevTools' ),
		),
	),
	// Pending — tests moderation queue.
	array(
		'slug'       => 'founding-engineer-linear',
		'title'      => 'Founding Engineer — Infrastructure',
		'company'    => 'linear',
		'status'     => 'pending',
		'content'    => "Help Linear scale its backend infrastructure to support the next 10x of users.\n\n**What you'll do:**\n- Own database performance, replication, and sharding strategy\n- Build observability tooling used by all engineers\n- Drive incident response culture and SLO definitions",
		'meta'       => array(
			'_wcb_salary_min'      => '180000',
			'_wcb_salary_max'      => '240000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_3m,
			'_wcb_featured'        => '0',
			'_wcb_apply_email'     => 'careers@linear.app',
		),
		'taxonomies' => array(
			'wcb_category'   => array( 'Engineering', 'DevOps' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote' ),
			'wcb_experience' => array( 'Senior', 'Lead' ),
			'wcb_tag'        => array( 'Remote-first', 'Startup' ),
		),
	),

	// ---- Vercel --------------------------------------------------------
	array(
		'slug'       => 'staff-platform-engineer-vercel',
		'title'      => 'Staff Platform Engineer',
		'company'    => 'vercel',
		'status'     => 'publish',
		'content'    => "Help build the infrastructure that powers millions of deploys per day at Vercel.\n\n**You are:**\n- A technical leader with 8+ years of engineering experience\n- Expert in Kubernetes, distributed systems, and cloud infrastructure (AWS/GCP)\n- Comfortable with Go, Rust, or TypeScript at the systems level",
		'meta'       => array(
			'_wcb_salary_min'      => '220000',
			'_wcb_salary_max'      => '300000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_3m,
			'_wcb_featured'        => '1',
			'_wcb_apply_email'     => 'careers@vercel.com',
		),
		'taxonomies' => array(
			'wcb_category'   => array( 'Engineering', 'DevOps' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote' ),
			'wcb_experience' => array( 'Lead', 'Principal' ),
			'wcb_tag'        => array( 'Remote-first', 'DevTools', 'Series C+' ),
		),
	),
	array(
		'slug'       => 'head-of-marketing-vercel',
		'title'      => 'Head of Marketing',
		'company'    => 'vercel',
		'status'     => 'publish',
		'content'    => "Own and evolve Vercel's marketing strategy as we scale to the next phase of growth.\n\n**You bring:**\n- 8+ years in B2B/developer marketing, 3+ years in a leadership role\n- Deep experience with developer-first brands\n- Data-driven decision making backed by attribution and funnel analytics",
		'meta'       => array(
			'_wcb_salary_min'      => '190000',
			'_wcb_salary_max'      => '250000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_2m,
			'_wcb_featured'        => '1',
			'_wcb_apply_email'     => 'careers@vercel.com',
		),
		'taxonomies' => array(
			'wcb_category'   => array( 'Marketing' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote', 'New York, NY' ),
			'wcb_experience' => array( 'Lead' ),
			'wcb_tag'        => array( 'Remote-first', 'DevTools' ),
		),
	),
	array(
		'slug'       => 'enterprise-customer-success-vercel',
		'title'      => 'Enterprise Customer Success Manager',
		'company'    => 'vercel',
		'status'     => 'publish',
		'content'    => "Work with Vercel's largest enterprise customers to drive adoption, renewals, and expansion.\n\n**Requirements:**\n- 4+ years in enterprise customer success at a SaaS company\n- Technical fluency — comfortable discussing CDN, DNS, CI/CD pipelines\n- Experience managing \$500K+ ARR accounts",
		'meta'       => array(
			'_wcb_salary_min'      => '120000',
			'_wcb_salary_max'      => '160000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_6w,
			'_wcb_featured'        => '0',
			'_wcb_apply_email'     => 'careers@vercel.com',
		),
		'taxonomies' => array(
			'wcb_category'   => array( 'Customer Success' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote' ),
			'wcb_experience' => array( 'Senior' ),
			'wcb_tag'        => array( 'Remote-first', 'SaaS' ),
		),
	),

	// ---- Shopify -------------------------------------------------------
	array(
		'slug'       => 'senior-product-manager-shopify',
		'title'      => 'Senior Product Manager — Checkout',
		'company'    => 'shopify',
		'status'     => 'publish',
		'content'    => "Own the checkout experience for one of the highest-volume e-commerce platforms in the world.\n\n**You have:**\n- 6+ years of product management experience, ideally in payments or e-commerce\n- Strong quantitative skills — comfortable with A/B tests, conversion analysis\n- Experience shipping products used by millions of consumers",
		'meta'       => array(
			'_wcb_salary_min'      => '160000',
			'_wcb_salary_max'      => '200000',
			'_wcb_salary_currency' => 'CAD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_3m,
			'_wcb_featured'        => '1',
			'_wcb_apply_email'     => 'jobs@shopify.com',
		),
		'taxonomies' => array(
			'wcb_category'   => array( 'Product' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote', 'Ottawa, ON' ),
			'wcb_experience' => array( 'Senior' ),
			'wcb_tag'        => array( 'Remote-first', 'E-commerce', 'Scale-up' ),
		),
	),
	array(
		'slug'       => 'react-native-engineer-shopify',
		'title'      => 'React Native Engineer — Shop App',
		'company'    => 'shopify',
		'status'     => 'publish',
		'content'    => "Build the Shop app, used by 100M+ shoppers to track orders and discover products.\n\n**Requirements:**\n- 3+ years building production React Native applications\n- Strong JavaScript/TypeScript skills\n- Comfortable with native modules (Objective-C/Swift or Kotlin)",
		'meta'       => array(
			'_wcb_salary_min'      => '140000',
			'_wcb_salary_max'      => '185000',
			'_wcb_salary_currency' => 'CAD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_2m,
			'_wcb_featured'        => '0',
			'_wcb_apply_email'     => 'jobs@shopify.com',
		),
		'taxonomies' => array(
			'wcb_category'   => array( 'Engineering' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote' ),
			'wcb_experience' => array( 'Mid Level' ),
			'wcb_tag'        => array( 'Remote-first', 'E-commerce' ),
		),
	),
	array(
		'slug'       => 'site-reliability-engineer-shopify',
		'title'      => 'Site Reliability Engineer',
		'company'    => 'shopify',
		'status'     => 'publish',
		'content'    => "Keep Shopify reliable during Black Friday and every day in between.\n\n**You have:**\n- 4+ years of SRE or DevOps experience at scale\n- Deep experience with Kubernetes, Terraform, and GCP or AWS\n- Strong observability background (Prometheus, Grafana, OpenTelemetry)",
		'meta'       => array(
			'_wcb_salary_min'      => '150000',
			'_wcb_salary_max'      => '195000',
			'_wcb_salary_currency' => 'CAD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_6w,
			'_wcb_featured'        => '0',
			'_wcb_apply_email'     => 'jobs@shopify.com',
		),
		'taxonomies' => array(
			'wcb_category'   => array( 'DevOps', 'Engineering' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote', 'Ottawa, ON' ),
			'wcb_experience' => array( 'Senior' ),
			'wcb_tag'        => array( 'Remote-first', 'E-commerce', 'Scale-up' ),
		),
	),

	// ---- Figma ---------------------------------------------------------
	array(
		'slug'       => 'principal-ux-researcher-figma',
		'title'      => 'Principal UX Researcher',
		'company'    => 'figma',
		'status'     => 'publish',
		'content'    => "Lead research that shapes Figma's product direction for millions of designers worldwide.\n\n**You have:**\n- 8+ years of UX research experience, 3+ at a Principal or equivalent level\n- Deep expertise in both qualitative and quantitative methods\n- Experience working with design-tools or creative products is a strong plus",
		'meta'       => array(
			'_wcb_salary_min'      => '195000',
			'_wcb_salary_max'      => '260000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '0',
			'_wcb_deadline'        => $deadline_3m,
			'_wcb_featured'        => '1',
			'_wcb_apply_email'     => 'careers@figma.com',
		),
		'taxonomies' => array(
			'wcb_category'   => array( 'Design', 'Product' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'San Francisco, CA' ),
			'wcb_experience' => array( 'Principal' ),
			'wcb_tag'        => array( 'Design Tools', 'SaaS' ),
		),
	),
	array(
		'slug'       => 'growth-marketing-manager-figma',
		'title'      => 'Growth Marketing Manager',
		'company'    => 'figma',
		'status'     => 'publish',
		'content'    => "Drive Figma's product-led growth across activation, retention, and expansion motions.\n\n**Requirements:**\n- 4+ years in growth marketing or product-led growth roles\n- Strong data skills — SQL, Amplitude/Mixpanel, and cohort analysis\n- Experience with PLG SaaS products is strongly preferred",
		'meta'       => array(
			'_wcb_salary_min'      => '140000',
			'_wcb_salary_max'      => '175000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '0',
			'_wcb_deadline'        => $deadline_2m,
			'_wcb_featured'        => '0',
			'_wcb_apply_email'     => 'careers@figma.com',
		),
		'taxonomies' => array(
			'wcb_category'   => array( 'Marketing' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'San Francisco, CA' ),
			'wcb_experience' => array( 'Senior' ),
			'wcb_tag'        => array( 'Design Tools', 'SaaS' ),
		),
	),
	array(
		'slug'       => 'software-engineer-editor-figma',
		'title'      => 'Software Engineer — Editor',
		'company'    => 'figma',
		'status'     => 'publish',
		'content'    => "Work on Figma's canvas editor — one of the most technically ambitious web applications ever built.\n\n**Requirements:**\n- 4+ years of software engineering experience\n- Strong C++ or Rust skills; TypeScript experience strongly preferred\n- Deep interest in graphics, rendering, or collaborative systems",
		'meta'       => array(
			'_wcb_salary_min'      => '185000',
			'_wcb_salary_max'      => '245000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '0',
			'_wcb_deadline'        => $deadline_6w,
			'_wcb_featured'        => '1',
			'_wcb_apply_email'     => 'careers@figma.com',
		),
		'taxonomies' => array(
			'wcb_category'   => array( 'Engineering' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'San Francisco, CA' ),
			'wcb_experience' => array( 'Senior' ),
			'wcb_tag'        => array( 'Design Tools', 'Open Source' ),
		),
	),
);

$job_ids_by_slug = array();
$published_count = 0;
$pending_count   = 0;

foreach ( $jobs_data as $job ) {
	$company_id   = $company_ids[ $job['company'] ] ?? 0;
	$company_post = $company_id ? get_post( $company_id ) : null;
	$employer_uid = $employer_ids[ $job['company'] ] ?? $admin_id;

	$wcb_id = wcb_seed_post(
		array(
			'post_type'    => 'wcb_job',
			'post_title'   => $job['title'],
			'post_name'    => $job['slug'],
			'post_content' => $job['content'],
			'post_status'  => $job['status'],
			'post_author'  => $employer_uid,
		)
	);

	if ( $wcb_id ) {
		foreach ( $job['meta'] as $key => $value ) {
			update_post_meta( $wcb_id, $key, $value );
		}
		if ( $company_id ) {
			update_post_meta( $wcb_id, '_wcb_company_id', $company_id );
			update_post_meta( $wcb_id, '_wcb_company_name', $company_post ? $company_post->post_title : '' );
		}
		foreach ( $job['taxonomies'] as $tax_slug => $tax_names ) {
			$tax_term_ids = array();
			foreach ( $tax_names as $name ) {
				if ( isset( $term_ids[ $tax_slug ][ $name ] ) ) {
					$tax_term_ids[] = $term_ids[ $tax_slug ][ $name ];
				}
			}
			if ( $tax_term_ids ) {
				wp_set_post_terms( $wcb_id, $tax_term_ids, $tax_slug );
			}
		}
		$job_ids_by_slug[ $job['slug'] ] = $wcb_id;
		'publish' === $job['status'] ? $published_count++ : $pending_count++;
	}
}

// ---------------------------------------------------------------------------
// 5. Candidate users
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '=== Creating candidate users ===' );

$candidates_data = array(
	array(
		'login'        => 'sarah.chen',
		'email'        => 'sarah.chen@example.com',
		'display_name' => 'Sarah Chen',
		'first_name'   => 'Sarah',
		'last_name'    => 'Chen',
		'description'  => 'Senior frontend engineer with 7 years of experience building design-system–driven React applications. Passionate about accessibility, web performance, and developer experience.',
		'bookmarks'    => array( 'backend-engineer-golang-stripe', 'staff-platform-engineer-vercel' ),
		'user_meta'    => array(
			'_wcb_open_to_work'       => '1',
			'_wcb_profile_visibility' => 'public',
			'_wcb_resume_data'        => array(
				'headline' => 'Senior Frontend Engineer · React · TypeScript · a11y',
				'location' => 'San Francisco, CA',
				'skills'   => 'React, TypeScript, Next.js, Tailwind CSS, Accessibility (WCAG 2.1), Web Performance, GraphQL, Figma',
				'linkedin' => 'https://linkedin.com/in/sarah-chen-dev',
				'github'   => 'https://github.com/sarahchen',
				'website'  => 'https://sarahchen.dev',
			),
		),
		'resume'       => array(
			'summary'           => 'Frontend engineer with 7 years of experience building performant, accessible React applications at scale. Led the design-system migration at my previous role from a legacy jQuery codebase to a modern React component library used by 12 product teams.',
			'experience'        => array(
				array(
					'company'         => 'Notion',
					'job_title'       => 'Senior Frontend Engineer',
					'employment_type' => 'Full-time',
					'location'        => 'San Francisco, CA',
					'start_date'      => '2021-06',
					'end_date'        => '',
					'current_role'    => 'true',
					'description'     => 'Lead engineer on the Notion design system team. Built and maintained 80+ components used across web and mobile. Reduced bundle size by 40% through code-splitting and lazy loading.',
					'skills_used'     => 'React, TypeScript, Storybook, Webpack, CSS-in-JS',
				),
				array(
					'company'         => 'Intercom',
					'job_title'       => 'Frontend Engineer',
					'employment_type' => 'Full-time',
					'location'        => 'San Francisco, CA',
					'start_date'      => '2018-03',
					'end_date'        => '2021-05',
					'current_role'    => 'false',
					'description'     => 'Built core features in the Intercom Messenger product. Shipped dark mode and improved Lighthouse accessibility score from 62 to 97 across the product.',
					'skills_used'     => 'Ember.js, React, SCSS, Jest',
				),
			),
			'skills'            => array(
				array(
					'skill_name'  => 'React',
					'proficiency' => 'Expert',
				),
				array(
					'skill_name'  => 'TypeScript',
					'proficiency' => 'Expert',
				),
				array(
					'skill_name'  => 'Next.js',
					'proficiency' => 'Advanced',
				),
				array(
					'skill_name'  => 'GraphQL',
					'proficiency' => 'Intermediate',
				),
				array(
					'skill_name'  => 'Figma',
					'proficiency' => 'Intermediate',
				),
			),
			'education_college' => array(
				array(
					'institution'    => 'UC Berkeley',
					'degree_type'    => 'Bachelor of Science',
					'field_of_study' => 'Computer Science',
					'start_date'     => '2013-08',
					'end_date'       => '2017-05',
					'gpa'            => '3.8',
					'description'    => "Dean's List. Teaching assistant for CS61B Data Structures.",
					'achievements'   => 'HackMIT winner 2016. Women in Tech Leadership Award.',
				),
			),
			'languages'         => array(
				array(
					'language'    => 'English',
					'proficiency' => 'Native',
				),
				array(
					'language'    => 'Mandarin',
					'proficiency' => 'Fluent',
				),
			),
		),
	),
	array(
		'login'        => 'marcus.williams',
		'email'        => 'marcus.williams@example.com',
		'display_name' => 'Marcus Williams',
		'first_name'   => 'Marcus',
		'last_name'    => 'Williams',
		'description'  => 'Product designer with a background in visual design and user research. I care about design systems, motion, and the tiny details that make products feel alive.',
		'bookmarks'    => array( 'senior-frontend-engineer-stripe', 'software-engineer-editor-figma' ),
		'user_meta'    => array(
			'_wcb_open_to_work'       => '1',
			'_wcb_profile_visibility' => 'public',
			'_wcb_resume_data'        => array(
				'headline' => 'Product Designer · Design Systems · Motion · Figma',
				'location' => 'New York, NY',
				'skills'   => 'Figma, Principle, Framer, After Effects, User Research, Prototyping, Design Systems',
				'linkedin' => 'https://linkedin.com/in/marcuswilliamsdesign',
				'website'  => 'https://marcuswilliams.design',
			),
		),
		'resume'       => array(
			'summary'           => 'Product designer with 6 years of experience shipping consumer and B2B products. Specialize in design systems and motion design. Previously led design for the core editor experience at a creative SaaS with 2M+ users.',
			'experience'        => array(
				array(
					'company'         => 'Framer',
					'job_title'       => 'Senior Product Designer',
					'employment_type' => 'Full-time',
					'location'        => 'Remote',
					'start_date'      => '2022-01',
					'end_date'        => '',
					'current_role'    => 'true',
					'description'     => "Own design for Framer's component and animation editor. Shipped the Framer Motion plugin integration and redesigned the layer panel.",
					'skills_used'     => 'Figma, Framer, user research, prototyping',
				),
				array(
					'company'         => 'InVision',
					'job_title'       => 'Product Designer',
					'employment_type' => 'Full-time',
					'location'        => 'New York, NY',
					'start_date'      => '2019-07',
					'end_date'        => '2021-12',
					'current_role'    => 'false',
					'description'     => 'Designed the handoff and inspect experience in InVision Cloud. Conducted 40+ user interviews that drove a complete information architecture overhaul.',
					'skills_used'     => 'Sketch, InVision, user research, Principle',
				),
			),
			'skills'            => array(
				array(
					'skill_name'  => 'Figma',
					'proficiency' => 'Expert',
				),
				array(
					'skill_name'  => 'Motion Design',
					'proficiency' => 'Advanced',
				),
				array(
					'skill_name'  => 'User Research',
					'proficiency' => 'Advanced',
				),
				array(
					'skill_name'  => 'Prototyping',
					'proficiency' => 'Expert',
				),
			),
			'education_college' => array(
				array(
					'institution'    => 'Parsons School of Design',
					'degree_type'    => 'Bachelor of Fine Arts',
					'field_of_study' => 'Communication Design',
					'start_date'     => '2014-09',
					'end_date'       => '2018-05',
					'gpa'            => '3.6',
					'description'    => 'Thesis: "Designing for Motion Accessibility in UI Animation".',
					'achievements'   => 'AIGA Student Design Competition — 2nd place.',
				),
			),
			'languages'         => array(
				array(
					'language'    => 'English',
					'proficiency' => 'Native',
				),
				array(
					'language'    => 'French',
					'proficiency' => 'Conversational',
				),
			),
		),
	),
	array(
		'login'        => 'priya.patel',
		'email'        => 'priya.patel@example.com',
		'display_name' => 'Priya Patel',
		'first_name'   => 'Priya',
		'last_name'    => 'Patel',
		'description'  => 'Product manager with 8 years of experience scaling B2B SaaS products from 0-to-1 and through Series C. Background in computer science gives me the technical depth to partner closely with engineering.',
		'bookmarks'    => array( 'head-of-marketing-vercel', 'enterprise-customer-success-vercel' ),
		'user_meta'    => array(
			'_wcb_open_to_work'       => '0',
			'_wcb_profile_visibility' => 'public',
			'_wcb_resume_data'        => array(
				'headline' => 'Senior Product Manager · B2B SaaS · 0→1 · Growth',
				'location' => 'Austin, TX',
				'skills'   => 'Product Strategy, Roadmapping, A/B Testing, SQL, Figma, Amplitude, JIRA',
				'linkedin' => 'https://linkedin.com/in/priyapatelpm',
			),
		),
		'resume'       => array(
			'summary'           => 'Senior PM with 8 years of experience building B2B SaaS products. CS background lets me work as a true partner with engineering. Shipped 3 zero-to-one products that collectively reached $40M ARR within 2 years of launch.',
			'experience'        => array(
				array(
					'company'         => 'Rippling',
					'job_title'       => 'Senior Product Manager',
					'employment_type' => 'Full-time',
					'location'        => 'San Francisco, CA',
					'start_date'      => '2020-09',
					'end_date'        => '',
					'current_role'    => 'true',
					'description'     => "PM for Rippling's Benefits product area. Launched ACA compliance automation and open enrollment — grew from 0 to \$12M ARR in 18 months.",
					'skills_used'     => 'Roadmapping, stakeholder management, SQL, Amplitude, user research',
				),
				array(
					'company'         => 'Gusto',
					'job_title'       => 'Product Manager',
					'employment_type' => 'Full-time',
					'location'        => 'San Francisco, CA',
					'start_date'      => '2017-02',
					'end_date'        => '2020-08',
					'current_role'    => 'false',
					'description'     => 'Owned payroll tax filings product. Led the state expansion to all 50 US states. Reduced tax filing errors by 78% through automated reconciliation.',
					'skills_used'     => 'Product specs, A/B testing, SQL, customer interviews',
				),
			),
			'skills'            => array(
				array(
					'skill_name'  => 'Product Strategy',
					'proficiency' => 'Expert',
				),
				array(
					'skill_name'  => 'Data Analysis (SQL)',
					'proficiency' => 'Advanced',
				),
				array(
					'skill_name'  => 'A/B Testing',
					'proficiency' => 'Advanced',
				),
				array(
					'skill_name'  => 'Figma',
					'proficiency' => 'Intermediate',
				),
			),
			'education_college' => array(
				array(
					'institution'    => 'Carnegie Mellon University',
					'degree_type'    => 'Bachelor of Science',
					'field_of_study' => 'Information Systems',
					'start_date'     => '2011-08',
					'end_date'       => '2015-05',
					'gpa'            => '3.9',
					'description'    => 'Minor in Human-Computer Interaction.',
					'achievements'   => 'Phi Beta Kappa.',
				),
			),
			'certifications'    => array(
				array(
					'cert_name'      => 'Pragmatic Marketing Certified',
					'issuing_body'   => 'Pragmatic Institute',
					'issue_date'     => '2019-03',
					'expiry_date'    => '',
					'credential_id'  => 'PMC-2019-3847',
					'credential_url' => 'https://pragmaticinstitute.com/verify',
				),
			),
			'languages'         => array(
				array(
					'language'    => 'English',
					'proficiency' => 'Native',
				),
				array(
					'language'    => 'Gujarati',
					'proficiency' => 'Fluent',
				),
				array(
					'language'    => 'Hindi',
					'proficiency' => 'Fluent',
				),
			),
		),
	),
	array(
		'login'        => 'jordan.lee',
		'email'        => 'jordan.lee@example.com',
		'display_name' => 'Jordan Lee',
		'first_name'   => 'Jordan',
		'last_name'    => 'Lee',
		'description'  => 'Marketing leader with a track record growing developer-first brands through content, community, and PLG. I write, I code a little, and I speak at conferences.',
		'bookmarks'    => array( 'developer-advocate-linear', 'growth-marketing-manager-figma' ),
		'user_meta'    => array(
			'_wcb_open_to_work'       => '1',
			'_wcb_profile_visibility' => 'public',
			'_wcb_resume_data'        => array(
				'headline' => 'Head of Marketing · Developer Brands · Content · PLG',
				'location' => 'Remote',
				'skills'   => 'Content Strategy, Developer Marketing, SEO, Community Building, Demand Generation, HubSpot',
				'linkedin' => 'https://linkedin.com/in/jordanlee-mktg',
				'twitter'  => 'https://twitter.com/jordanleemktg',
				'website'  => 'https://jordanlee.co',
			),
		),
		'resume'       => array(
			'summary'           => 'Marketing leader with 9 years growing developer and B2B SaaS brands. Grew a developer tool from 10K to 500K monthly active developers through content-led growth. Deep experience in community-building, SEO, and PLG motions.',
			'experience'        => array(
				array(
					'company'         => 'Planetscale',
					'job_title'       => 'Head of Marketing',
					'employment_type' => 'Full-time',
					'location'        => 'Remote',
					'start_date'      => '2021-04',
					'end_date'        => '2024-02',
					'current_role'    => 'false',
					'description'     => 'Built the marketing function from scratch. Grew organic traffic 8x through technical content strategy. Launched developer community that reached 25K members in 12 months.',
					'skills_used'     => 'Content strategy, SEO, community management, developer relations, HubSpot',
				),
				array(
					'company'         => 'Netlify',
					'job_title'       => 'Senior Content Marketing Manager',
					'employment_type' => 'Full-time',
					'location'        => 'Remote',
					'start_date'      => '2018-06',
					'end_date'        => '2021-03',
					'current_role'    => 'false',
					'description'     => 'Owned the Netlify blog — grew from 50K to 400K monthly readers. Managed 15 guest contributors and produced Jamstack Conf content.',
					'skills_used'     => 'Editorial strategy, SEO, technical writing, event production',
				),
			),
			'skills'            => array(
				array(
					'skill_name'  => 'Content Strategy',
					'proficiency' => 'Expert',
				),
				array(
					'skill_name'  => 'SEO',
					'proficiency' => 'Expert',
				),
				array(
					'skill_name'  => 'Developer Marketing',
					'proficiency' => 'Advanced',
				),
				array(
					'skill_name'  => 'Community Building',
					'proficiency' => 'Advanced',
				),
				array(
					'skill_name'  => 'Demand Generation',
					'proficiency' => 'Intermediate',
				),
			),
			'education_college' => array(
				array(
					'institution'    => 'Northwestern University',
					'degree_type'    => 'Bachelor of Arts',
					'field_of_study' => 'Communications',
					'start_date'     => '2011-09',
					'end_date'       => '2015-06',
					'gpa'            => '3.7',
					'description'    => 'Journalism concentration. Editor of The Daily Northwestern.',
					'achievements'   => '',
				),
			),
			'languages'         => array(
				array(
					'language'    => 'English',
					'proficiency' => 'Native',
				),
				array(
					'language'    => 'Korean',
					'proficiency' => 'Conversational',
				),
			),
			'portfolio'         => array(
				array(
					'label' => 'Jamstack Conf 2022 Talk',
					'url'   => 'https://www.youtube.com/watch?v=example1',
				),
				array(
					'label' => 'Case study: Netlify blog growth',
					'url'   => 'https://jordanlee.co/netlify-case-study',
				),
			),
		),
	),
	array(
		'login'        => 'alex.kumar',
		'email'        => 'alex.kumar@example.com',
		'display_name' => 'Alex Kumar',
		'first_name'   => 'Alex',
		'last_name'    => 'Kumar',
		'description'  => 'DevOps and platform engineer focused on Kubernetes, observability, and building developer-friendly internal platforms. Open to SRE and Platform Engineering roles.',
		'bookmarks'    => array( 'site-reliability-engineer-shopify', 'senior-frontend-engineer-stripe' ),
		'user_meta'    => array(
			'_wcb_open_to_work'       => '1',
			'_wcb_profile_visibility' => 'public',
			'_wcb_resume_data'        => array(
				'headline' => 'Platform Engineer · Kubernetes · Terraform · Observability',
				'location' => 'Remote',
				'skills'   => 'Kubernetes, Terraform, Go, Prometheus, Grafana, AWS, GCP, Helm, ArgoCD, OpenTelemetry',
				'linkedin' => 'https://linkedin.com/in/alexkumar-devops',
				'github'   => 'https://github.com/alexkumar',
			),
		),
		'resume'       => array(
			'summary'           => 'Platform engineer with 6 years of experience building and operating Kubernetes-based infrastructure at scale. Reduced cloud spend by $2M annually through resource optimization. Passionate about developer experience and building self-service internal platforms.',
			'experience'        => array(
				array(
					'company'         => 'Datadog',
					'job_title'       => 'Senior Platform Engineer',
					'employment_type' => 'Full-time',
					'location'        => 'Remote',
					'start_date'      => '2021-02',
					'end_date'        => '',
					'current_role'    => 'true',
					'description'     => "Built and maintain Datadog's internal developer platform serving 3000+ engineers. Migrated 200+ services to a GitOps model with ArgoCD. Reduced average deploy time from 45 minutes to 4 minutes.",
					'skills_used'     => 'Kubernetes, Terraform, Go, ArgoCD, Prometheus, Grafana',
				),
				array(
					'company'         => 'Cloudflare',
					'job_title'       => 'Infrastructure Engineer',
					'employment_type' => 'Full-time',
					'location'        => 'Austin, TX',
					'start_date'      => '2018-08',
					'end_date'        => '2021-01',
					'current_role'    => 'false',
					'description'     => "Operated Cloudflare's Kubernetes fleet across 200+ data centres. Owned the internal DNS infrastructure and certificate management automation.",
					'skills_used'     => 'Kubernetes, Ansible, Python, Salt, Nginx',
				),
			),
			'skills'            => array(
				array(
					'skill_name'  => 'Kubernetes',
					'proficiency' => 'Expert',
				),
				array(
					'skill_name'  => 'Terraform',
					'proficiency' => 'Expert',
				),
				array(
					'skill_name'  => 'Go',
					'proficiency' => 'Advanced',
				),
				array(
					'skill_name'  => 'AWS / GCP',
					'proficiency' => 'Advanced',
				),
				array(
					'skill_name'  => 'Observability',
					'proficiency' => 'Advanced',
				),
				array(
					'skill_name'  => 'Python',
					'proficiency' => 'Intermediate',
				),
			),
			'education_college' => array(
				array(
					'institution'    => 'University of Texas at Austin',
					'degree_type'    => 'Bachelor of Science',
					'field_of_study' => 'Electrical and Computer Engineering',
					'start_date'     => '2014-08',
					'end_date'       => '2018-05',
					'gpa'            => '3.5',
					'description'    => '',
					'achievements'   => 'IEEE student chapter — chapter president 2017.',
				),
			),
			'certifications'    => array(
				array(
					'cert_name'      => 'Certified Kubernetes Administrator (CKA)',
					'issuing_body'   => 'Cloud Native Computing Foundation',
					'issue_date'     => '2020-11',
					'expiry_date'    => '2023-11',
					'credential_id'  => 'CKA-2020-AK1234',
					'credential_url' => 'https://www.credly.com/badges/example',
				),
				array(
					'cert_name'      => 'AWS Solutions Architect – Associate',
					'issuing_body'   => 'Amazon Web Services',
					'issue_date'     => '2021-06',
					'expiry_date'    => '2024-06',
					'credential_id'  => 'AWS-SAA-AK5678',
					'credential_url' => 'https://www.credly.com/badges/example2',
				),
			),
			'languages'         => array(
				array(
					'language'    => 'English',
					'proficiency' => 'Fluent',
				),
				array(
					'language'    => 'Hindi',
					'proficiency' => 'Native',
				),
			),
		),
	),
);

$user_ids = array();
foreach ( $candidates_data as $candidate ) {
	$uid = wcb_seed_user(
		array(
			'user_login'   => $candidate['login'],
			'user_email'   => $candidate['email'],
			'user_pass'    => 'password',
			'display_name' => $candidate['display_name'],
			'first_name'   => $candidate['first_name'],
			'last_name'    => $candidate['last_name'],
			'description'  => $candidate['description'],
			'role'         => 'wcb_candidate',
		)
	);

	if ( $uid ) {
		foreach ( $candidate['user_meta'] as $key => $value ) {
			update_user_meta( $uid, $key, $value );
		}

		// Add bookmarks (non-unique usermeta — one row per bookmarked job).
		foreach ( $candidate['bookmarks'] as $job_slug ) {
			$job_id = $job_ids_by_slug[ $job_slug ] ?? wcb_seed_get_post_id( $job_slug, 'wcb_job' );
			if ( $job_id && ! in_array( $job_id, array_map( 'intval', (array) get_user_meta( $uid, '_wcb_bookmark', false ) ), true ) ) {
				add_user_meta( $uid, '_wcb_bookmark', $job_id, false );
			}
		}

		$user_ids[ $candidate['login'] ] = $uid;
	}
}

// ---------------------------------------------------------------------------
// 6. wcb_resume CPT posts (Pro) — one per candidate
// ---------------------------------------------------------------------------

WP_CLI::log( '' );

if ( post_type_exists( 'wcb_resume' ) ) {
	WP_CLI::log( '=== Creating wcb_resume posts (Pro) ===' );

	foreach ( $candidates_data as $candidate ) {
		$user = get_user_by( 'email', $candidate['email'] );
		if ( ! $user ) {
			continue;
		}
		$uid    = $user->ID;
		$resume = $candidate['resume'];

		$wcb_id = wcb_seed_post(
			array(
				'post_type'   => 'wcb_resume',
				'post_title'  => $candidate['display_name'] . ' — Resume',
				'post_name'   => sanitize_title( $candidate['login'] . '-resume' ),
				'post_status' => 'publish',
				'post_author' => $uid,
			)
		);

		if ( $wcb_id ) {
			update_post_meta( $wcb_id, '_wcb_resume_summary', $resume['summary'] );
			update_post_meta( $id, '_wcb_resume_public', '1' );

			$section_keys = array( 'experience', 'skills', 'education_college', 'education_school', 'certifications', 'languages', 'portfolio' );
			foreach ( $section_keys as $key ) {
				if ( ! empty( $resume[ $key ] ) ) {
					update_post_meta( $wcb_id, '_wcb_resume_' . $key, $resume[ $key ] );
				}
			}

			// Sync skill names into wcb_resume_skill taxonomy (queried by resume archive filter).
			if ( taxonomy_exists( 'wcb_resume_skill' ) && ! empty( $resume['skills'] ) ) {
				$skill_terms = array_values(
					array_filter(
						array_map(
							static fn( array $s ): string => (string) ( $s['skill_name'] ?? '' ),
							$resume['skills']
						)
					)
				);
				wp_set_object_terms( $wcb_id, $skill_terms, 'wcb_resume_skill' );
			}
		}
	}
} else {
	WP_CLI::log( '=== Skipping wcb_resume posts — Pro plugin not active ===' );
}

// ---------------------------------------------------------------------------
// 7. Applications
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '=== Creating applications ===' );

/**
 * Resolve a user ID from a login handle, falling back to the $user_ids map.
 *
 * @param string            $login    User login.
 * @param array<string,int> $user_ids Seed user map.
 * @return int User ID or 0.
 */
function wcb_seed_uid( string $login, array $user_ids ): int {
	if ( isset( $user_ids[ $login ] ) ) {
		return $user_ids[ $login ];
	}
	$u = get_user_by( 'login', $login );
	return $u ? (int) $u->ID : 0;
}

$applications_data = array(
	// sarah.chen applied to Senior Frontend Engineer @ Stripe — now being reviewed.
	array(
		'candidate' => 'sarah.chen',
		'job_slug'  => 'senior-frontend-engineer-stripe',
		'status'    => 'reviewing',
		'cover'     => 'I have been following Stripe for years and deeply admire your engineering culture. My seven years building React applications, including a complete design-system migration at Notion, maps directly to what you need. I would love to bring that experience to the Payments UI team.',
	),
	// sarah.chen applied to Product Designer @ Linear — just submitted.
	array(
		'candidate' => 'sarah.chen',
		'job_slug'  => 'product-designer-linear',
		'status'    => 'submitted',
		'cover'     => 'My work sits at the intersection of engineering and design — I collaborate daily with designers and have contributed to design system tokens and component APIs. Linear feels like the natural next step.',
	),
	// sarah.chen applied to Software Engineer @ Figma — rejected.
	array(
		'candidate' => 'sarah.chen',
		'job_slug'  => 'software-engineer-editor-figma',
		'status'    => 'rejected',
		'cover'     => 'I am deeply interested in graphics rendering challenges. While my background is primarily React/TypeScript rather than C++/Rust, I believe my performance optimisation experience transfers well.',
	),
	// marcus.williams applied to Product Designer @ Linear — shortlisted.
	array(
		'candidate' => 'marcus.williams',
		'job_slug'  => 'product-designer-linear',
		'status'    => 'shortlisted',
		'cover'     => "Linear's commitment to craft matches my own. I have shipped design systems and motion work at Framer and InVision and would bring the same obsessive attention to detail to Linear's product.",
	),
	// marcus.williams applied to Principal UX Researcher @ Figma — hired!
	array(
		'candidate' => 'marcus.williams',
		'job_slug'  => 'principal-ux-researcher-figma',
		'status'    => 'hired',
		'cover'     => 'Eight years of mixed-methods research, two of which leading a research practice at a 2M-user SaaS. I have worked closely with design-tool teams and understand the unique challenges of researching creative workflows.',
	),
	// priya.patel applied to Senior PM @ Shopify — submitted.
	array(
		'candidate' => 'priya.patel',
		'job_slug'  => 'senior-product-manager-shopify',
		'status'    => 'submitted',
		'cover'     => 'I have spent the last 4 years working on payments and benefits products where conversion and compliance are the two competing forces — exactly the tension you face at Shopify Checkout. I would love to bring that perspective.',
	),
	// priya.patel applied to Head of Marketing @ Vercel — reviewing.
	array(
		'candidate' => 'priya.patel',
		'job_slug'  => 'head-of-marketing-vercel',
		'status'    => 'reviewing',
		'cover'     => 'My CS background means I can go deep with developer audiences while still thinking in funnels and pipeline. I grew a B2B product to $40M ARR and would bring the same rigour to Vercel.',
	),
	// jordan.lee applied to Head of Marketing @ Vercel — submitted.
	array(
		'candidate' => 'jordan.lee',
		'job_slug'  => 'head-of-marketing-vercel',
		'status'    => 'submitted',
		'cover'     => 'Developer-first marketing is where I have spent my entire career. At Planetscale I grew organic traffic 8x in 18 months. Vercel has the brand recognition to do even more with the right content and community strategy.',
	),
	// jordan.lee applied to Developer Advocate @ Linear — reviewing.
	array(
		'candidate' => 'jordan.lee',
		'job_slug'  => 'developer-advocate-linear',
		'status'    => 'reviewing',
		'cover'     => 'I write code, I write about code, and I speak at conferences. Linear is the tool I recommend to every engineering team I work with. Becoming its advocate feels like a natural fit.',
	),
	// alex.kumar applied to Staff Platform Engineer @ Vercel — submitted.
	array(
		'candidate' => 'alex.kumar',
		'job_slug'  => 'staff-platform-engineer-vercel',
		'status'    => 'submitted',
		'cover'     => "I currently operate a Kubernetes developer platform for 3,000 engineers at Datadog. Vercel's edge infrastructure challenges are exactly the kind of problem I want to work on at the next level.",
	),
	// alex.kumar applied to SRE @ Shopify — shortlisted.
	array(
		'candidate' => 'alex.kumar',
		'job_slug'  => 'site-reliability-engineer-shopify',
		'status'    => 'shortlisted',
		'cover'     => 'High-volume, latency-sensitive systems under unpredictable load — that is my day job. I have built SLO frameworks and error-budget policies from scratch and would bring the same rigour to Shopify.',
	),
	// Guest application — Software Engineer @ Figma.
	array(
		'candidate'   => null,
		'job_slug'    => 'software-engineer-editor-figma',
		'status'      => 'submitted',
		'guest_name'  => 'Lena Müller',
		'guest_email' => 'lena.muller@example-guest.com',
		'cover'       => 'I am a systems engineer with 5 years of C++ and WebAssembly experience in real-time graphics applications. Figma is the product I use every day and I would love to work on its rendering engine.',
	),
	// Guest application — Backend Engineer @ Stripe.
	array(
		'candidate'   => null,
		'job_slug'    => 'backend-engineer-golang-stripe',
		'status'      => 'submitted',
		'guest_name'  => 'Tobias Hartmann',
		'guest_email' => 'tobias@example-guest.com',
		'cover'       => 'I have 6 years of high-throughput Go microservices experience at a European fintech. Processing financial transactions at Stripe scale is exactly the challenge I am looking for.',
	),
);

$application_count = 0;
foreach ( $applications_data as $app ) {
	$job_id = $job_ids_by_slug[ $app['job_slug'] ] ?? wcb_seed_get_post_id( $app['job_slug'], 'wcb_job' );
	if ( ! $job_id ) {
		WP_CLI::warning( '  → skip application: job not found (' . $app['job_slug'] . ')' );
		continue;
	}

	$job_post = get_post( $job_id );

	if ( null === $app['candidate'] ) {
		// Guest application.
		$post_author = 0;
	} else {
		$post_author = wcb_seed_uid( $app['candidate'], $user_ids );
		if ( ! $post_author ) {
			WP_CLI::warning( '  → skip application: candidate not found (' . $app['candidate'] . ')' );
			continue;
		}
	}

	// Avoid duplicate applications for the same candidate + job.
	$existing_args = array(
		'post_type'   => 'wcb_application',
		'post_status' => 'any',
		'author'      => $post_author > 0 ? $post_author : -1,
		'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'   => '_wcb_job_id',
				'value' => $job_id,
			),
		),
		'numberposts' => 1,
		'fields'      => 'ids',
	);

	// For guest applications, check by guest email instead of author.
	if ( 0 === $post_author ) {
		unset( $existing_args['author'] );
		$existing_args['meta_query'][]           = array(
			'key'   => '_wcb_guest_email',
			'value' => $app['guest_email'],
		);
		$existing_args['meta_query']['relation'] = 'AND';
	}

	$existing = get_posts( $existing_args );
	if ( $existing ) {
		WP_CLI::log( '  → skip application (exists): ' . $app['job_slug'] . ' / ' . ( $app['candidate'] ?? $app['guest_email'] ) );
		continue;
	}

	$app_title = 'Application for ' . ( $job_post ? $job_post->post_title : $app['job_slug'] );
	$app_id    = wp_insert_post(
		array(
			'post_type'   => 'wcb_application',
			'post_title'  => $app_title,
			'post_status' => 'publish',
			'post_author' => $post_author,
		),
		true
	);

	if ( is_wp_error( $app_id ) ) {
		WP_CLI::warning( '  → could not create application: ' . $app_id->get_error_message() );
		continue;
	}

	update_post_meta( $app_id, '_wcb_job_id', $job_id );
	update_post_meta( $app_id, '_wcb_cover_letter', $app['cover'] );
	update_post_meta( $app_id, '_wcb_status', $app['status'] );

	if ( $post_author > 0 ) {
		update_post_meta( $app_id, '_wcb_candidate_id', $post_author );
	} else {
		update_post_meta( $app_id, '_wcb_guest_name', $app['guest_name'] );
		update_post_meta( $app_id, '_wcb_guest_email', $app['guest_email'] );
	}

	WP_CLI::log( '  → created application: ' . $title . ' — ' . $app['status'] . ' (ID ' . $app_id . ')' );
	++$application_count;
}

// ---------------------------------------------------------------------------
// Done
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::success( 'Seed data imported:' );
WP_CLI::log( '  • ' . count( $employers_data ) . ' employer users (wcb_employer)' );
WP_CLI::log( '  • ' . count( $companies_data ) . ' companies (wcb_company)' );
WP_CLI::log( '  • ' . $published_count . ' published jobs + ' . $pending_count . ' pending jobs (wcb_job)' );
WP_CLI::log( '  • ' . count( $candidates_data ) . ' candidate users (wcb_candidate)' );
WP_CLI::log( '  • ' . count( $candidates_data ) . ' resumes (wcb_resume, if Pro active)' );
WP_CLI::log( '  • ' . $application_count . ' applications (wcb_application, ' . ( count( $applications_data ) - 2 ) . ' user + 2 guest)' );
WP_CLI::log( '' );
WP_CLI::log( 'Login credentials (password: password):' );
WP_CLI::log( '  Employers:' );
foreach ( $employers_data as $emp ) {
	WP_CLI::log( '    ' . home_url( '/?autologin=' . $emp['login'] ) );
}
WP_CLI::log( '  Candidates:' );
foreach ( $candidates_data as $c ) {
	WP_CLI::log( '    ' . home_url( '/?autologin=' . $c['login'] ) );
}
