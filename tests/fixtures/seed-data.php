<?php
/**
 * WP Career Board — seed data fixture.
 *
 * Creates realistic test data: 5 companies, 15 jobs, 5 candidate users,
 * 5 wcb_resume (Pro CPT) posts, and candidate user-meta profiles.
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
 * Create a post if no published post with the same slug exists.
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

$admin_id = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID ?? 1 );

// ---------------------------------------------------------------------------
// 1. Taxonomy terms
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '=== Creating taxonomy terms ===' );

$terms = array(
	'wcb_category' => array( 'Engineering', 'Design', 'Marketing', 'Product', 'Data', 'Customer Success', 'DevOps' ),
	'wcb_job_type' => array( 'Full-time', 'Part-time', 'Contract', 'Internship' ),
	'wcb_location' => array( 'Remote', 'San Francisco, CA', 'Ottawa, ON', 'New York, NY', 'Austin, TX' ),
	'wcb_experience' => array( 'Entry Level', 'Mid Level', 'Senior', 'Lead', 'Principal' ),
);

$term_ids = array();
foreach ( $terms as $taxonomy => $names ) {
	$term_ids[ $taxonomy ] = array();
	foreach ( $names as $name ) {
		$id = wcb_seed_term( $name, $taxonomy );
		$term_ids[ $taxonomy ][ $name ] = $id;
		WP_CLI::log( '  ' . $taxonomy . ': ' . $name . ' (term ' . $id . ')' );
	}
}

// ---------------------------------------------------------------------------
// 2. Companies
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '=== Creating companies ===' );

$companies_data = array(
	array(
		'slug'        => 'stripe',
		'title'       => 'Stripe',
		'content'     => 'Stripe is a technology company that builds economic infrastructure for the internet. Businesses of every size—from new startups to public companies—use our software to accept payments and manage their businesses online.',
		'meta'        => array(
			'_wcb_tagline'      => 'Financial infrastructure for the internet',
			'_wcb_website'      => 'https://stripe.com',
			'_wcb_industry'     => 'Fintech',
			'_wcb_company_size' => '5000+',
			'_wcb_hq_location'  => 'San Francisco, CA',
			'_wcb_founded'      => '2010',
			'_wcb_company_type' => 'Private',
			'_wcb_linkedin'     => 'https://linkedin.com/company/stripe',
			'_wcb_twitter'      => 'https://twitter.com/stripe',
		),
	),
	array(
		'slug'        => 'linear',
		'title'       => 'Linear',
		'content'     => 'Linear is a purpose-built tool for planning and building products. Thousands of high-performance teams use Linear to streamline software projects, sprints, tasks, and bug tracking.',
		'meta'        => array(
			'_wcb_tagline'      => 'The issue tracker built for high-performance teams',
			'_wcb_website'      => 'https://linear.app',
			'_wcb_industry'     => 'SaaS / Productivity',
			'_wcb_company_size' => '51-200',
			'_wcb_hq_location'  => 'Remote',
			'_wcb_founded'      => '2019',
			'_wcb_company_type' => 'Private',
			'_wcb_linkedin'     => 'https://linkedin.com/company/linear-app',
			'_wcb_twitter'      => 'https://twitter.com/linear',
		),
	),
	array(
		'slug'        => 'vercel',
		'title'       => 'Vercel',
		'content'     => 'Vercel provides the developer tools and cloud infrastructure to build, scale, and secure a faster, more personalized web. Vercel is the platform for frontend developers, providing the speed and reliability innovators need to create at the moment of inspiration.',
		'meta'        => array(
			'_wcb_tagline'      => 'Develop. Preview. Ship.',
			'_wcb_website'      => 'https://vercel.com',
			'_wcb_industry'     => 'Developer Tools / Cloud',
			'_wcb_company_size' => '201-500',
			'_wcb_hq_location'  => 'Remote',
			'_wcb_founded'      => '2015',
			'_wcb_company_type' => 'Private',
			'_wcb_linkedin'     => 'https://linkedin.com/company/vercel',
			'_wcb_twitter'      => 'https://twitter.com/vercel',
		),
	),
	array(
		'slug'        => 'shopify',
		'title'       => 'Shopify',
		'content'     => 'Shopify is a leading global commerce company, providing trusted tools to start, grow, market, and manage a retail business of any size. Shopify makes commerce better for everyone with a platform and services that are engineered for reliability.',
		'meta'        => array(
			'_wcb_tagline'      => 'Making commerce better for everyone',
			'_wcb_website'      => 'https://shopify.com',
			'_wcb_industry'     => 'E-commerce',
			'_wcb_company_size' => '5000+',
			'_wcb_hq_location'  => 'Ottawa, ON',
			'_wcb_founded'      => '2006',
			'_wcb_company_type' => 'Public',
			'_wcb_linkedin'     => 'https://linkedin.com/company/shopify',
			'_wcb_twitter'      => 'https://twitter.com/shopify',
		),
	),
	array(
		'slug'        => 'figma',
		'title'       => 'Figma',
		'content'     => 'Figma is a collaborative web application for interface design. Figma helps teams create, test, and ship better designs from start to finish—all in one platform.',
		'meta'        => array(
			'_wcb_tagline'      => 'Design together, build together',
			'_wcb_website'      => 'https://figma.com',
			'_wcb_industry'     => 'Design Tools / SaaS',
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
	$id = wcb_seed_post( array(
		'post_type'    => 'wcb_company',
		'post_title'   => $co['title'],
		'post_name'    => $co['slug'],
		'post_content' => $co['content'],
		'post_status'  => 'publish',
		'post_author'  => $admin_id,
	) );

	if ( $id ) {
		foreach ( $co['meta'] as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		$company_ids[ $co['slug'] ] = $id;
	}
}

// ---------------------------------------------------------------------------
// 3. Jobs
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '=== Creating jobs ===' );

$deadline_3m = gmdate( 'Y-m-d', strtotime( '+3 months' ) );
$deadline_6w = gmdate( 'Y-m-d', strtotime( '+6 weeks' ) );
$deadline_2m = gmdate( 'Y-m-d', strtotime( '+2 months' ) );

$jobs_data = array(

	// ---- Stripe --------------------------------------------------------
	array(
		'slug'        => 'senior-frontend-engineer-stripe',
		'title'       => 'Senior Frontend Engineer',
		'company'     => 'stripe',
		'content'     => "We are looking for a Senior Frontend Engineer to join our Payments UI team.\n\n**What you'll do:**\n- Build and maintain high-performance, accessible React components used by millions of merchants worldwide\n- Partner with designers and product managers to translate specs into pixel-perfect implementations\n- Own end-to-end features from design review through production release\n- Champion frontend quality by writing thorough unit and integration tests\n\n**You'll love this role if:**\n- You care deeply about web performance and Core Web Vitals\n- You think in design systems and component APIs\n- You enjoy debugging gnarly browser compatibility issues",
		'meta'        => array(
			'_wcb_salary_min'      => '170000',
			'_wcb_salary_max'      => '220000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_3m,
			'_wcb_featured'        => '1',
			'_wcb_apply_email'     => 'jobs@stripe.com',
		),
		'taxonomies'  => array(
			'wcb_category'   => array( 'Engineering' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote', 'San Francisco, CA' ),
			'wcb_experience' => array( 'Senior' ),
		),
	),
	array(
		'slug'        => 'backend-engineer-golang-stripe',
		'title'       => 'Backend Engineer — Golang',
		'company'     => 'stripe',
		'content'     => "Join our Payouts infrastructure team and help process billions of dollars for merchants globally.\n\n**What you'll do:**\n- Design and build distributed systems that process financial transactions at massive scale\n- Work with Go, Ruby, and Scala in a services-oriented architecture\n- Drive reliability improvements — we care deeply about 99.999% uptime\n- Collaborate with oncall rotations and improve observability\n\n**Requirements:**\n- 4+ years of backend engineering experience\n- Strong understanding of distributed systems, CAP theorem, and consistency guarantees\n- Experience with high-throughput, low-latency services",
		'meta'        => array(
			'_wcb_salary_min'      => '180000',
			'_wcb_salary_max'      => '240000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '0',
			'_wcb_deadline'        => $deadline_2m,
			'_wcb_featured'        => '0',
			'_wcb_apply_email'     => 'jobs@stripe.com',
		),
		'taxonomies'  => array(
			'wcb_category'   => array( 'Engineering' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'San Francisco, CA' ),
			'wcb_experience' => array( 'Mid Level', 'Senior' ),
		),
	),
	array(
		'slug'        => 'data-analyst-stripe',
		'title'       => 'Data Analyst — Revenue Insights',
		'company'     => 'stripe',
		'content'     => "Help Stripe understand its business by building the dashboards and analyses leadership uses every week.\n\n**Responsibilities:**\n- Own recurring business reports for executive leadership\n- Explore large datasets using SQL and Python to surface actionable insights\n- Partner with Finance and GTM teams to answer complex revenue questions\n- Build and maintain self-serve analytics dashboards in Looker\n\n**What we're looking for:**\n- 2–4 years of data analysis or business intelligence experience\n- Expert-level SQL; Python (pandas, numpy) is a strong plus\n- Experience with BI tools (Looker, Tableau, Metabase)",
		'meta'        => array(
			'_wcb_salary_min'      => '120000',
			'_wcb_salary_max'      => '155000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_6w,
			'_wcb_featured'        => '0',
			'_wcb_apply_email'     => 'jobs@stripe.com',
		),
		'taxonomies'  => array(
			'wcb_category'   => array( 'Data' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote' ),
			'wcb_experience' => array( 'Mid Level' ),
		),
	),

	// ---- Linear --------------------------------------------------------
	array(
		'slug'        => 'product-designer-linear',
		'title'       => 'Product Designer',
		'company'     => 'linear',
		'content'     => "Linear is hiring a Product Designer who is obsessed with craft and deeply technical.\n\n**What you'll work on:**\n- Own end-to-end design for core product areas — from discovery through polish\n- Shape the visual language of Linear's desktop and web apps\n- Work closely with engineers who care as much about design quality as you do\n- Run user research, usability sessions, and beta testing with power users\n\n**You:**\n- Have 4+ years of product design experience at a B2B SaaS company\n- Are proficient in Figma with experience building and maintaining design systems\n- Ship fast and iterate — you ship weekly, not quarterly",
		'meta'        => array(
			'_wcb_salary_min'      => '150000',
			'_wcb_salary_max'      => '190000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_3m,
			'_wcb_featured'        => '1',
			'_wcb_apply_email'     => 'careers@linear.app',
		),
		'taxonomies'  => array(
			'wcb_category'   => array( 'Design' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote' ),
			'wcb_experience' => array( 'Senior' ),
		),
	),
	array(
		'slug'        => 'senior-ios-engineer-linear',
		'title'       => 'Senior iOS Engineer',
		'company'     => 'linear',
		'content'     => "Build Linear's iOS app used by thousands of engineering teams daily.\n\n**What you'll do:**\n- Own and evolve the Linear iOS app — currently best-in-class on the App Store\n- Implement new features with a relentless focus on performance and reliability\n- Collaborate directly with design to push the boundaries of what native apps can be\n- Write Swift that other engineers enjoy reading\n\n**Requirements:**\n- 5+ years of iOS development with Swift\n- Deep knowledge of UIKit, SwiftUI, and Combine\n- Experience shipping and maintaining apps with 100k+ users",
		'meta'        => array(
			'_wcb_salary_min'      => '160000',
			'_wcb_salary_max'      => '210000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_2m,
			'_wcb_featured'        => '0',
			'_wcb_apply_email'     => 'careers@linear.app',
		),
		'taxonomies'  => array(
			'wcb_category'   => array( 'Engineering' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote' ),
			'wcb_experience' => array( 'Senior' ),
		),
	),
	array(
		'slug'        => 'developer-advocate-linear',
		'title'       => 'Developer Advocate',
		'company'     => 'linear',
		'content'     => "Be the bridge between Linear's engineering team and the developer community.\n\n**Responsibilities:**\n- Create technical content (blog posts, tutorials, videos) showing how engineering teams use Linear\n- Speak at conferences and developer meetups\n- Build integrations and demo projects that showcase Linear's API and webhooks\n- Collect developer feedback and route it to the product team\n\n**You have:**\n- 3+ years of software engineering experience\n- Strong technical writing and communication skills\n- Experience with developer-facing communities or open source",
		'meta'        => array(
			'_wcb_salary_min'      => '130000',
			'_wcb_salary_max'      => '165000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_6w,
			'_wcb_featured'        => '0',
			'_wcb_apply_email'     => 'careers@linear.app',
		),
		'taxonomies'  => array(
			'wcb_category'   => array( 'Marketing' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote' ),
			'wcb_experience' => array( 'Mid Level' ),
		),
	),

	// ---- Vercel --------------------------------------------------------
	array(
		'slug'        => 'staff-platform-engineer-vercel',
		'title'       => 'Staff Platform Engineer',
		'company'     => 'vercel',
		'content'     => "Help build the infrastructure that powers millions of deploys per day at Vercel.\n\n**What you'll do:**\n- Lead cross-functional initiatives improving build pipeline reliability and performance\n- Design systems that handle unpredictable traffic spikes across global edge nodes\n- Mentor engineers and drive architectural decision-making\n- Own complex production incidents end-to-end\n\n**You are:**\n- A technical leader with 8+ years of engineering experience\n- Expert in Kubernetes, distributed systems, and cloud infrastructure (AWS/GCP)\n- Comfortable with Go, Rust, or TypeScript at the systems level",
		'meta'        => array(
			'_wcb_salary_min'      => '220000',
			'_wcb_salary_max'      => '300000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_3m,
			'_wcb_featured'        => '1',
			'_wcb_apply_email'     => 'careers@vercel.com',
		),
		'taxonomies'  => array(
			'wcb_category'   => array( 'Engineering', 'DevOps' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote' ),
			'wcb_experience' => array( 'Lead', 'Principal' ),
		),
	),
	array(
		'slug'        => 'head-of-marketing-vercel',
		'title'       => 'Head of Marketing',
		'company'     => 'vercel',
		'content'     => "Own and evolve Vercel's marketing strategy as we scale to the next phase of growth.\n\n**What you'll do:**\n- Define and execute Vercel's brand, demand generation, and developer marketing strategy\n- Lead a team of marketers across growth, content, and events\n- Partner with Sales and Product to launch new features and enterprise offerings\n- Drive pipeline growth through product-led and community-led channels\n\n**You bring:**\n- 8+ years in B2B/developer marketing, 3+ years in a leadership role\n- Deep experience with developer-first brands\n- Data-driven decision making backed by attribution and funnel analytics",
		'meta'        => array(
			'_wcb_salary_min'      => '190000',
			'_wcb_salary_max'      => '250000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_2m,
			'_wcb_featured'        => '1',
			'_wcb_apply_email'     => 'careers@vercel.com',
		),
		'taxonomies'  => array(
			'wcb_category'   => array( 'Marketing' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote', 'New York, NY' ),
			'wcb_experience' => array( 'Lead' ),
		),
	),
	array(
		'slug'        => 'enterprise-customer-success-vercel',
		'title'       => 'Enterprise Customer Success Manager',
		'company'     => 'vercel',
		'content'     => "Work with Vercel's largest enterprise customers to drive adoption, renewals, and expansion.\n\n**Responsibilities:**\n- Own a portfolio of strategic enterprise accounts (Fortune 500, scale-ups)\n- Conduct executive business reviews and quarterly check-ins\n- Partner with Solutions Engineering to solve customer technical challenges\n- Identify expansion opportunities and work with Account Executives\n\n**Requirements:**\n- 4+ years in enterprise customer success at a SaaS company\n- Technical fluency — comfortable discussing CDN, DNS, CI/CD pipelines\n- Experience managing $500K+ ARR accounts",
		'meta'        => array(
			'_wcb_salary_min'      => '120000',
			'_wcb_salary_max'      => '160000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_6w,
			'_wcb_featured'        => '0',
			'_wcb_apply_email'     => 'careers@vercel.com',
		),
		'taxonomies'  => array(
			'wcb_category'   => array( 'Customer Success' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote' ),
			'wcb_experience' => array( 'Senior' ),
		),
	),

	// ---- Shopify -------------------------------------------------------
	array(
		'slug'        => 'senior-product-manager-shopify',
		'title'       => 'Senior Product Manager — Checkout',
		'company'     => 'shopify',
		'content'     => "Own the checkout experience for one of the highest-volume e-commerce platforms in the world.\n\n**What you'll do:**\n- Define the product vision and roadmap for Shopify Checkout across web and mobile\n- Run continuous customer discovery with merchants and shoppers\n- Work with engineering, design, and data to ship improvements that move the revenue needle\n- Navigate complex merchant requirements against a simple, cohesive UX\n\n**You have:**\n- 6+ years of product management experience, ideally in payments or e-commerce\n- Strong quantitative skills — comfortable with A/B tests, conversion analysis\n- Experience shipping products used by millions of consumers",
		'meta'        => array(
			'_wcb_salary_min'      => '160000',
			'_wcb_salary_max'      => '200000',
			'_wcb_salary_currency' => 'CAD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_3m,
			'_wcb_featured'        => '1',
			'_wcb_apply_email'     => 'jobs@shopify.com',
		),
		'taxonomies'  => array(
			'wcb_category'   => array( 'Product' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote', 'Ottawa, ON' ),
			'wcb_experience' => array( 'Senior' ),
		),
	),
	array(
		'slug'        => 'react-native-engineer-shopify',
		'title'       => 'React Native Engineer — Shop App',
		'company'     => 'shopify',
		'content'     => "Build the Shop app, used by 100M+ shoppers to track orders and discover products.\n\n**What you'll do:**\n- Develop high-quality React Native features for iOS and Android\n- Collaborate with product and design to craft exceptional shopping experiences\n- Optimize performance for devices across the entire Android fragmentation spectrum\n- Write thorough unit and snapshot tests\n\n**Requirements:**\n- 3+ years building production React Native applications\n- Strong JavaScript/TypeScript skills\n- Comfortable with native modules (Objective-C/Swift or Kotlin)",
		'meta'        => array(
			'_wcb_salary_min'      => '140000',
			'_wcb_salary_max'      => '185000',
			'_wcb_salary_currency' => 'CAD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_2m,
			'_wcb_featured'        => '0',
			'_wcb_apply_email'     => 'jobs@shopify.com',
		),
		'taxonomies'  => array(
			'wcb_category'   => array( 'Engineering' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote' ),
			'wcb_experience' => array( 'Mid Level' ),
		),
	),
	array(
		'slug'        => 'site-reliability-engineer-shopify',
		'title'       => 'Site Reliability Engineer',
		'company'     => 'shopify',
		'content'     => "Keep Shopify reliable during Black Friday and every day in between.\n\n**Responsibilities:**\n- Maintain and improve the reliability of Shopify's platform across hundreds of services\n- Build runbooks, SLOs, and error-budget policies with product engineering teams\n- Drive capacity planning and infrastructure cost optimisation\n- Participate in an on-call rotation for critical services\n\n**You have:**\n- 4+ years of SRE or DevOps experience at scale\n- Deep experience with Kubernetes, Terraform, and GCP or AWS\n- Strong observability background (Prometheus, Grafana, OpenTelemetry)",
		'meta'        => array(
			'_wcb_salary_min'      => '150000',
			'_wcb_salary_max'      => '195000',
			'_wcb_salary_currency' => 'CAD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '1',
			'_wcb_deadline'        => $deadline_6w,
			'_wcb_featured'        => '0',
			'_wcb_apply_email'     => 'jobs@shopify.com',
		),
		'taxonomies'  => array(
			'wcb_category'   => array( 'DevOps', 'Engineering' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'Remote', 'Ottawa, ON' ),
			'wcb_experience' => array( 'Senior' ),
		),
	),

	// ---- Figma ---------------------------------------------------------
	array(
		'slug'        => 'principal-ux-researcher-figma',
		'title'       => 'Principal UX Researcher',
		'company'     => 'figma',
		'content'     => "Lead research that shapes Figma's product direction for millions of designers worldwide.\n\n**What you'll do:**\n- Drive mixed-methods research (interviews, surveys, usability tests, diary studies) across core product areas\n- Synthesise findings into clear, actionable insights for product and design leadership\n- Build Figma's research practice — tooling, methodology, and researcher mentorship\n- Influence the long-term product roadmap through strategic research programmes\n\n**You have:**\n- 8+ years of UX research experience, 3+ at a Principal or equivalent level\n- Deep expertise in both qualitative and quantitative methods\n- Experience working with design-tools or creative products is a strong plus",
		'meta'        => array(
			'_wcb_salary_min'      => '195000',
			'_wcb_salary_max'      => '260000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '0',
			'_wcb_deadline'        => $deadline_3m,
			'_wcb_featured'        => '1',
			'_wcb_apply_email'     => 'careers@figma.com',
		),
		'taxonomies'  => array(
			'wcb_category'   => array( 'Design', 'Product' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'San Francisco, CA' ),
			'wcb_experience' => array( 'Principal' ),
		),
	),
	array(
		'slug'        => 'growth-marketing-manager-figma',
		'title'       => 'Growth Marketing Manager',
		'company'     => 'figma',
		'content'     => "Drive Figma's product-led growth across activation, retention, and expansion motions.\n\n**What you'll do:**\n- Own growth experiments across onboarding, referral, and in-product upgrade flows\n- Partner with Product and Engineering to run A/B tests and analyse results\n- Build automated lifecycle campaigns with email and in-app messaging\n- Report weekly on growth metrics and experiment pipeline to leadership\n\n**Requirements:**\n- 4+ years in growth marketing or product-led growth roles\n- Strong data skills — SQL, Amplitude/Mixpanel, and cohort analysis\n- Experience with PLG SaaS products is strongly preferred",
		'meta'        => array(
			'_wcb_salary_min'      => '140000',
			'_wcb_salary_max'      => '175000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '0',
			'_wcb_deadline'        => $deadline_2m,
			'_wcb_featured'        => '0',
			'_wcb_apply_email'     => 'careers@figma.com',
		),
		'taxonomies'  => array(
			'wcb_category'   => array( 'Marketing' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'San Francisco, CA' ),
			'wcb_experience' => array( 'Senior' ),
		),
	),
	array(
		'slug'        => 'software-engineer-editor-figma',
		'title'       => 'Software Engineer — Editor',
		'company'     => 'figma',
		'content'     => "Work on Figma's canvas editor — one of the most technically ambitious web applications ever built.\n\n**What you'll do:**\n- Build features in Figma's collaborative, WebAssembly-powered editor\n- Optimise rendering performance and memory usage for complex design files\n- Contribute to the real-time collaboration engine (CRDTs, operational transforms)\n- Collaborate with design and product to ship features that delight designers\n\n**Requirements:**\n- 4+ years of software engineering experience\n- Strong C++ or Rust skills; TypeScript experience strongly preferred\n- Deep interest in graphics, rendering, or collaborative systems",
		'meta'        => array(
			'_wcb_salary_min'      => '185000',
			'_wcb_salary_max'      => '245000',
			'_wcb_salary_currency' => 'USD',
			'_wcb_salary_type'     => 'annual',
			'_wcb_remote'          => '0',
			'_wcb_deadline'        => $deadline_6w,
			'_wcb_featured'        => '1',
			'_wcb_apply_email'     => 'careers@figma.com',
		),
		'taxonomies'  => array(
			'wcb_category'   => array( 'Engineering' ),
			'wcb_job_type'   => array( 'Full-time' ),
			'wcb_location'   => array( 'San Francisco, CA' ),
			'wcb_experience' => array( 'Senior' ),
		),
	),
);

foreach ( $jobs_data as $job ) {
	$company_id   = $company_ids[ $job['company'] ] ?? 0;
	$company_post = $company_id ? get_post( $company_id ) : null;

	$id = wcb_seed_post( array(
		'post_type'    => 'wcb_job',
		'post_title'   => $job['title'],
		'post_name'    => $job['slug'],
		'post_content' => $job['content'],
		'post_status'  => 'publish',
		'post_author'  => $admin_id,
	) );

	if ( $id ) {
		foreach ( $job['meta'] as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		if ( $company_id ) {
			update_post_meta( $id, '_wcb_company_id', $company_id );
			update_post_meta( $id, '_wcb_company_name', $company_post ? $company_post->post_title : '' );
		}
		foreach ( $job['taxonomies'] as $taxonomy => $names ) {
			$ids = array();
			foreach ( $names as $name ) {
				if ( isset( $term_ids[ $taxonomy ][ $name ] ) ) {
					$ids[] = $term_ids[ $taxonomy ][ $name ];
				}
			}
			if ( $ids ) {
				wp_set_post_terms( $id, $ids, $taxonomy );
			}
		}
	}
}

// ---------------------------------------------------------------------------
// 4. Candidate users
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
		'user_meta'    => array(
			'_wcb_open_to_work'      => '1',
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
		'resume' => array(
			'summary'    => 'Frontend engineer with 7 years of experience building performant, accessible React applications at scale. Led the design-system migration at my previous role from a legacy jQuery codebase to a modern React component library used by 12 product teams.',
			'experience' => array(
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
			'skills' => array(
				array( 'skill_name' => 'React',      'proficiency' => 'Expert' ),
				array( 'skill_name' => 'TypeScript', 'proficiency' => 'Expert' ),
				array( 'skill_name' => 'Next.js',    'proficiency' => 'Advanced' ),
				array( 'skill_name' => 'GraphQL',    'proficiency' => 'Intermediate' ),
				array( 'skill_name' => 'Figma',      'proficiency' => 'Intermediate' ),
			),
			'education_college' => array(
				array(
					'institution'    => 'UC Berkeley',
					'degree_type'    => 'Bachelor of Science',
					'field_of_study' => 'Computer Science',
					'start_date'     => '2013-08',
					'end_date'       => '2017-05',
					'gpa'            => '3.8',
					'description'    => 'Dean\'s List. Teaching assistant for CS61B Data Structures.',
					'achievements'   => 'HackMIT winner 2016. Women in Tech Leadership Award.',
				),
			),
			'languages' => array(
				array( 'language' => 'English',  'proficiency' => 'Native' ),
				array( 'language' => 'Mandarin', 'proficiency' => 'Fluent' ),
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
		'resume' => array(
			'summary'    => 'Product designer with 6 years of experience shipping consumer and B2B products. Specialize in design systems and motion design. Previously led design for the core editor experience at a creative SaaS with 2M+ users.',
			'experience' => array(
				array(
					'company'         => 'Framer',
					'job_title'       => 'Senior Product Designer',
					'employment_type' => 'Full-time',
					'location'        => 'Remote',
					'start_date'      => '2022-01',
					'end_date'        => '',
					'current_role'    => 'true',
					'description'     => 'Own design for Framer\'s component and animation editor. Shipped the Framer Motion plugin integration and redesigned the layer panel. Part of the design system working group.',
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
			'skills' => array(
				array( 'skill_name' => 'Figma',         'proficiency' => 'Expert' ),
				array( 'skill_name' => 'Motion Design', 'proficiency' => 'Advanced' ),
				array( 'skill_name' => 'User Research', 'proficiency' => 'Advanced' ),
				array( 'skill_name' => 'Prototyping',   'proficiency' => 'Expert' ),
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
			'languages' => array(
				array( 'language' => 'English', 'proficiency' => 'Native' ),
				array( 'language' => 'French',  'proficiency' => 'Conversational' ),
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
		'resume' => array(
			'summary'    => 'Senior PM with 8 years of experience building B2B SaaS products. CS background lets me work as a true partner with engineering. Shipped 3 zero-to-one products that collectively reached $40M ARR within 2 years of launch.',
			'experience' => array(
				array(
					'company'         => 'Rippling',
					'job_title'       => 'Senior Product Manager',
					'employment_type' => 'Full-time',
					'location'        => 'San Francisco, CA',
					'start_date'      => '2020-09',
					'end_date'        => '',
					'current_role'    => 'true',
					'description'     => 'PM for Rippling\'s Benefits product area. Launched ACA compliance automation and open enrollment — grew from 0 to $12M ARR in 18 months.',
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
			'skills' => array(
				array( 'skill_name' => 'Product Strategy',    'proficiency' => 'Expert' ),
				array( 'skill_name' => 'Data Analysis (SQL)', 'proficiency' => 'Advanced' ),
				array( 'skill_name' => 'A/B Testing',         'proficiency' => 'Advanced' ),
				array( 'skill_name' => 'Figma',               'proficiency' => 'Intermediate' ),
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
			'certifications' => array(
				array(
					'cert_name'      => 'Pragmatic Marketing Certified',
					'issuing_body'   => 'Pragmatic Institute',
					'issue_date'     => '2019-03',
					'expiry_date'    => '',
					'credential_id'  => 'PMC-2019-3847',
					'credential_url' => 'https://pragmaticinstitute.com/verify',
				),
			),
			'languages' => array(
				array( 'language' => 'English',  'proficiency' => 'Native' ),
				array( 'language' => 'Gujarati', 'proficiency' => 'Fluent' ),
				array( 'language' => 'Hindi',    'proficiency' => 'Fluent' ),
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
		'resume' => array(
			'summary'    => 'Marketing leader with 9 years growing developer and B2B SaaS brands. Grew a developer tool from 10K to 500K monthly active developers through content-led growth. Deep experience in community-building, SEO, and PLG motions.',
			'experience' => array(
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
			'skills' => array(
				array( 'skill_name' => 'Content Strategy',     'proficiency' => 'Expert' ),
				array( 'skill_name' => 'SEO',                  'proficiency' => 'Expert' ),
				array( 'skill_name' => 'Developer Marketing',  'proficiency' => 'Advanced' ),
				array( 'skill_name' => 'Community Building',   'proficiency' => 'Advanced' ),
				array( 'skill_name' => 'Demand Generation',    'proficiency' => 'Intermediate' ),
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
			'languages' => array(
				array( 'language' => 'English', 'proficiency' => 'Native' ),
				array( 'language' => 'Korean',  'proficiency' => 'Conversational' ),
			),
			'portfolio' => array(
				array( 'label' => 'Jamstack Conf 2022 Talk', 'url' => 'https://www.youtube.com/watch?v=example1' ),
				array( 'label' => 'Case study: Netlify blog growth', 'url' => 'https://jordanlee.co/netlify-case-study' ),
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
		'resume' => array(
			'summary'    => 'Platform engineer with 6 years of experience building and operating Kubernetes-based infrastructure at scale. Reduced cloud spend by $2M annually through resource optimization. Passionate about developer experience and building self-service internal platforms.',
			'experience' => array(
				array(
					'company'         => 'Datadog',
					'job_title'       => 'Senior Platform Engineer',
					'employment_type' => 'Full-time',
					'location'        => 'Remote',
					'start_date'      => '2021-02',
					'end_date'        => '',
					'current_role'    => 'true',
					'description'     => 'Built and maintain Datadog\'s internal developer platform serving 3000+ engineers. Migrated 200+ services to a GitOps model with ArgoCD. Reduced average deploy time from 45 minutes to 4 minutes.',
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
					'description'     => 'Operated Cloudflare\'s Kubernetes fleet across 200+ data centres. Owned the internal DNS infrastructure and certificate management automation.',
					'skills_used'     => 'Kubernetes, Ansible, Python, Salt, Nginx',
				),
			),
			'skills' => array(
				array( 'skill_name' => 'Kubernetes',       'proficiency' => 'Expert' ),
				array( 'skill_name' => 'Terraform',        'proficiency' => 'Expert' ),
				array( 'skill_name' => 'Go',               'proficiency' => 'Advanced' ),
				array( 'skill_name' => 'AWS / GCP',        'proficiency' => 'Advanced' ),
				array( 'skill_name' => 'Observability',    'proficiency' => 'Advanced' ),
				array( 'skill_name' => 'Python',           'proficiency' => 'Intermediate' ),
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
			'certifications' => array(
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
			'languages' => array(
				array( 'language' => 'English', 'proficiency' => 'Fluent' ),
				array( 'language' => 'Hindi',   'proficiency' => 'Native' ),
			),
		),
	),
);

$user_ids = array();
foreach ( $candidates_data as $candidate ) {
	$uid = wcb_seed_user( array(
		'user_login'   => $candidate['login'],
		'user_email'   => $candidate['email'],
		'user_pass'    => wp_generate_password( 16 ),
		'display_name' => $candidate['display_name'],
		'first_name'   => $candidate['first_name'],
		'last_name'    => $candidate['last_name'],
		'description'  => $candidate['description'],
		'role'         => 'subscriber',
	) );

	if ( $uid ) {
		foreach ( $candidate['user_meta'] as $key => $value ) {
			update_user_meta( $uid, $key, $value );
		}
		$user_ids[ $candidate['login'] ] = array(
			'id'     => $uid,
			'resume' => $candidate['resume'],
		);
	}
}

// ---------------------------------------------------------------------------
// 5. wcb_resume CPT posts (Pro) — one per candidate
// ---------------------------------------------------------------------------

WP_CLI::log( '' );

if ( post_type_exists( 'wcb_resume' ) ) {
	WP_CLI::log( '=== Creating wcb_resume posts (Pro) ===' );

	foreach ( $candidates_data as $candidate ) {
		$user = get_user_by( 'email', $candidate['email'] );
		if ( ! $user ) {
			continue;
		}
		$uid     = $user->ID;
		$resume  = $candidate['resume'];

		$resume_title = $candidate['display_name'] . ' — Resume';
		$id = wcb_seed_post( array(
			'post_type'    => 'wcb_resume',
			'post_title'   => $resume_title,
			'post_name'    => sanitize_title( $candidate['login'] . '-resume' ),
			'post_status'  => 'publish',
			'post_author'  => $uid,
		) );

		if ( $id ) {
			update_post_meta( $id, '_wcb_resume_summary', $resume['summary'] );
			update_post_meta( $id, '_wcb_resume_public', '1' );

			$section_keys = array( 'experience', 'skills', 'education_college', 'education_school', 'certifications', 'languages', 'portfolio' );
			foreach ( $section_keys as $key ) {
				if ( ! empty( $resume[ $key ] ) ) {
					update_post_meta( $id, '_wcb_resume_' . $key, $resume[ $key ] );
				}
			}

			// Sync skill names into the wcb_resume_skill taxonomy (Pro feature).
			if ( taxonomy_exists( 'wcb_resume_skill' ) && ! empty( $resume['skills'] ) ) {
				$skill_terms = array_filter(
					array_map(
						static fn( array $s ): string => (string) ( $s['skill_name'] ?? '' ),
						$resume['skills']
					)
				);
				wp_set_object_terms( $id, array_values( $skill_terms ), 'wcb_resume_skill' );
			}
		}
	}
} else {
	WP_CLI::log( '=== Skipping wcb_resume posts — Pro plugin not active ===' );
}

// ---------------------------------------------------------------------------
// Done
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::success( 'Seed data imported:' );
WP_CLI::log( '  • ' . count( $companies_data ) . ' companies (wcb_company)' );
WP_CLI::log( '  • ' . count( $jobs_data ) . ' jobs (wcb_job)' );
WP_CLI::log( '  • ' . count( $candidates_data ) . ' candidate users' );
WP_CLI::log( '  • ' . count( $candidates_data ) . ' resumes (wcb_resume, if Pro active)' );
WP_CLI::log( '' );
WP_CLI::log( 'Login with any test candidate:' );
foreach ( $candidates_data as $c ) {
	WP_CLI::log( '  http://job-portal.local/?autologin=' . $c['login'] );
}
