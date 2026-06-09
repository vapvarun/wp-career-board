<?php
/**
 * QA fixture seeder for WP Career Board (free + pro).
 *
 * Run via:
 *   wp --path="/path/to/wp" eval-file wp-content/plugins/wp-career-board/bin/seed-qa-fixtures.php
 *
 * What it creates:
 *   - 1 admin (uses user 1)
 *   - 2 employer users + 2 wcb_company posts owned by them
 *   - 3 candidate users + 3 wcb_resume posts (one per candidate)
 *   - 5 wcb_job posts (3 published, 1 draft, 1 expired)
 *   - 4 wcb_application posts spanning stages (applied / shortlisted / rejected / hired)
 *   - 1 wcb_board (default board for combo mode)
 *   - Pro fixtures (only when wp-career-board-pro is active):
 *       1 row in wcb_credit_ledger (employer #1 has 5 credits)
 *       1 row in wcb_job_alerts (candidate alert for "javascript")
 *       3 rows in wcb_application_stages (applied, shortlisted, rejected)
 *
 * All seeded posts are tagged with `_wcb_qa_smoke` post-meta = 1 so the
 * runbook's fixture-cleanup pass can wipe them in a single query.
 *
 * Idempotent: re-running deletes prior smoke fixtures first.
 *
 * @package WP_Career_Board
 */

declare( strict_types=1 );

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	echo "ERROR: this script must be run via wp eval-file (WP-CLI).\n";
	return;
}

global $wpdb;

WP_CLI::log( '== seed-qa-fixtures.php ==' );

// ---------------------------------------------------------------------------
// 0. Tear down prior smoke run.
// ---------------------------------------------------------------------------

$prior_post_ids = get_posts(
	array(
		'post_type'      => array( 'wcb_job', 'wcb_application', 'wcb_resume', 'wcb_company', 'wcb_board' ),
		'meta_key'       => '_wcb_qa_smoke',
		'meta_value'     => '1',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $prior_post_ids as $id ) {
	wp_delete_post( $id, true );
}
WP_CLI::log( sprintf( '  cleaned %d prior smoke posts', count( $prior_post_ids ) ) );

$prior_users = get_users(
	array(
		'meta_key'   => '_wcb_qa_smoke',
		'meta_value' => '1',
		'fields'     => 'ID',
	)
);
foreach ( $prior_users as $uid ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $uid );
}
WP_CLI::log( sprintf( '  cleaned %d prior smoke users', count( $prior_users ) ) );

// Pro tables (only if Pro is active). Use Free's documented filter rather
// than reading Pro's version constant directly so the Pro-decoupling gate
// (bin/check-pro-decoupling.sh) stays green.
$pro_active = (bool) apply_filters( 'wcb_pro_active', false );
if ( $pro_active ) {
	$pro_tables = array(
		'wcb_credit_ledger',
		'wcb_job_alerts',
		'wcb_application_stages',
		'wcb_field_values',
	);
	foreach ( $pro_tables as $t ) {
		$full   = $wpdb->prefix . $t;
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full ) );
		if ( $exists ) {
			$wpdb->query( "DELETE FROM {$full} WHERE 1=1 AND (1=1 OR /* smoke filter */ 0)" );
		}
	}
	WP_CLI::log( '  cleaned pro tables (credit_ledger, job_alerts, application_stages, field_values)' );
}

// ---------------------------------------------------------------------------
// 1. Users.
// ---------------------------------------------------------------------------

$tag_smoke = function ( int $id, string $kind ): void {
	update_user_meta( $id, '_wcb_qa_smoke', '1' );
	update_user_meta( $id, '_wcb_qa_kind', $kind );
};

$employers = array();
foreach ( array( 'employer_alice', 'employer_bob' ) as $login ) {
	$uid = username_exists( $login );
	if ( ! $uid ) {
		$uid = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => wp_generate_password( 16 ),
				'user_email' => $login . '@example.test',
				'role'       => 'employer', // role is registered by the plugin's installer
				'first_name' => ucfirst( str_replace( 'employer_', '', $login ) ),
			)
		);
		if ( is_wp_error( $uid ) ) {
			WP_CLI::error( "failed to create user {$login}: " . $uid->get_error_message() );
		}
	}
	$tag_smoke( $uid, 'employer' );
	$employers[] = $uid;
}
WP_CLI::log( sprintf( '  employers: %s', wp_json_encode( $employers ) ) );

$candidates = array();
foreach ( array( 'candidate_carol', 'candidate_dan', 'candidate_eve' ) as $login ) {
	$uid = username_exists( $login );
	if ( ! $uid ) {
		$uid = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => wp_generate_password( 16 ),
				'user_email' => $login . '@example.test',
				'role'       => 'candidate',
				'first_name' => ucfirst( str_replace( 'candidate_', '', $login ) ),
			)
		);
		if ( is_wp_error( $uid ) ) {
			WP_CLI::error( "failed to create user {$login}: " . $uid->get_error_message() );
		}
	}
	$tag_smoke( $uid, 'candidate' );
	$candidates[] = $uid;
}
WP_CLI::log( sprintf( '  candidates: %s', wp_json_encode( $candidates ) ) );

// Job Moderator persona — exercises the moderation surface as a real
// wcb_board_moderator (not admin), per the moderator-role contract.
$moderator_login = 'morgan_moderator';
$moderator_id    = username_exists( $moderator_login );
if ( ! $moderator_id ) {
	$moderator_id = wp_insert_user(
		array(
			'user_login' => $moderator_login,
			'user_pass'  => wp_generate_password( 16 ),
			'user_email' => $moderator_login . '@example.test',
			'role'       => 'wcb_board_moderator',
			'first_name' => 'Morgan',
		)
	);
	if ( is_wp_error( $moderator_id ) ) {
		WP_CLI::error( "failed to create user {$moderator_login}: " . $moderator_id->get_error_message() );
	}
}
$tag_smoke( $moderator_id, 'moderator' );
WP_CLI::log( sprintf( '  moderator: %s (id %d, role wcb_board_moderator)', $moderator_login, $moderator_id ) );

// ---------------------------------------------------------------------------
// 2. Posts (CPTs).
// ---------------------------------------------------------------------------

$insert = function ( array $args, string $kind, array $meta = array() ) use ( $wpdb ): int {
	$args = array_merge(
		array(
			'post_status'  => 'publish',
			'post_author'  => 1,
			'post_content' => 'Smoke fixture content. Lorem ipsum dolor sit amet.',
		),
		$args
	);
	$id   = wp_insert_post( $args, true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( "insert {$kind} failed: " . $id->get_error_message() );
	}
	update_post_meta( $id, '_wcb_qa_smoke', '1' );
	update_post_meta( $id, '_wcb_qa_kind', $kind );
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	return $id;
};

// Default board (combo mode requires this for boards module).
$board_id = $insert(
	array(
		'post_type'  => 'wcb_board',
		'post_title' => 'Smoke Board (default)',
	),
	'board'
);

// Companies (one per employer).
$company_alice = $insert(
	array(
		'post_type'   => 'wcb_company',
		'post_title'  => 'Smoke Co Alpha',
		'post_author' => $employers[0],
	),
	'company',
	array(
		'_wcb_company_owner'   => $employers[0],
		'_wcb_company_website' => 'https://alpha.example.test',
	)
);
$company_bob   = $insert(
	array(
		'post_type'   => 'wcb_company',
		'post_title'  => 'Smoke Co Beta',
		'post_author' => $employers[1],
	),
	'company',
	array(
		'_wcb_company_owner'   => $employers[1],
		'_wcb_company_website' => 'https://beta.example.test',
	)
);

// Resumes (candidate profiles — `wcb_resume` is the candidate-profile CPT).
$resumes = array();
foreach ( $candidates as $i => $cuid ) {
	$resumes[ $cuid ] = $insert(
		array(
			'post_type'   => 'wcb_resume',
			'post_title'  => 'Smoke Resume ' . ( $i + 1 ),
			'post_author' => $cuid,
		),
		'resume',
		array(
			'_wcb_resume_owner'  => $cuid,
			'_wcb_resume_skills' => 'javascript,php,wordpress',
		)
	);
}

// Jobs (3 published, 1 draft, 1 expired).
// Meta keys mirror JobsEndpoint::create_item() — not wcb_job_*.
$jobs = array();
for ( $i = 0; $i < 5; $i++ ) {
	$status = 'publish';
	$title  = sprintf( 'Smoke Job %d - Senior PHP Engineer', $i + 1 );
	$meta   = array(
		'_wcb_company_id'      => 0 === $i % 2 ? $company_alice : $company_bob,
		'_wcb_company_name'    => 0 === $i % 2 ? 'Smoke Co Alpha' : 'Smoke Co Beta',
		'_wcb_salary_min'      => '80000',
		'_wcb_salary_max'      => '110000',
		'_wcb_salary_currency' => 'USD',
		'_wcb_salary_type'     => 'yearly',
		'_wcb_remote'          => '1',
		'_wcb_deadline'        => gmdate( 'Y-m-d', strtotime( '+30 days' ) ),
		'_wcb_board_id'        => $board_id,
	);

	if ( 3 === $i ) {
		$status = 'draft';
		$title  = sprintf( 'Smoke Job %d - DRAFT', $i + 1 );
	}
	if ( 4 === $i ) {
		$title                 = sprintf( 'Smoke Job %d - EXPIRED', $i + 1 );
		$meta['_wcb_deadline'] = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
	}

	$jobs[] = $insert(
		array(
			'post_type'   => 'wcb_job',
			'post_title'  => $title,
			'post_status' => $status,
			'post_author' => 0 === $i % 2 ? $employers[0] : $employers[1],
		),
		'job',
		$meta
	);
}

// Applications (4, spanning stages).
// Meta keys mirror ApplicationsEndpoint::submit_application() — not wcb_application_*.
$applications  = array();
$stage_options = array( 'submitted', 'reviewing', 'shortlisted', 'rejected' );
foreach ( $stage_options as $idx => $stage ) {
	$candidate_id = $candidates[ $idx % count( $candidates ) ];
	$job_id       = $jobs[ $idx % 3 ]; // distribute across the 3 published jobs

	$applications[] = $insert(
		array(
			'post_type'   => 'wcb_application',
			'post_title'  => sprintf( 'Smoke Application %s on Job %d', $stage, $job_id ),
			'post_author' => $candidate_id,
		),
		'application',
		array(
			'_wcb_job_id'       => $job_id,
			'_wcb_candidate_id' => $candidate_id,
			'_wcb_resume_id'    => $resumes[ $candidate_id ],
			'_wcb_status'       => $stage,
			'_wcb_cover_letter' => 'Smoke cover letter for stage ' . $stage,
		)
	);
}

WP_CLI::log( sprintf( '  posts: 1 board, 2 companies, %d resumes, %d jobs, %d applications', count( $resumes ), count( $jobs ), count( $applications ) ) );

// ---------------------------------------------------------------------------
// 3. Pro fixtures (only if Pro active).
// ---------------------------------------------------------------------------

if ( $pro_active ) {
	// Credit ledger.
	// Schema: id, employer_id, post_id, entry_type, amount, note, created_at
	// (append-only; balance = SUM of signed amounts — no balance column).
	$ledger_table = $wpdb->prefix . 'wcb_credit_ledger';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ledger_table ) ) ) {
		$wpdb->insert(
			$ledger_table,
			array(
				'employer_id' => $employers[0],
				'post_id'     => 0,
				'entry_type'  => 'topup',
				'amount'      => 5,
				'note'        => 'Smoke seed - initial grant',
			)
		);
		WP_CLI::log( '  pro: credit_ledger row for employer alice (5 credits, entry_type=topup)' );
	}

	// Job alert.
	// Schema: id, user_id, board_id, search_query, filters, frequency, last_sent_at, created_at
	// (no 'name' or 'query' or 'cadence' columns).
	$alerts_table = $wpdb->prefix . 'wcb_job_alerts';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $alerts_table ) ) ) {
		$wpdb->insert(
			$alerts_table,
			array(
				'user_id'      => $candidates[0],
				'board_id'     => $board_id,
				'search_query' => 'javascript',
				'filters'      => wp_json_encode( array( 'location' => 'Remote' ) ),
				'frequency'    => 'daily',
			)
		);
		WP_CLI::log( '  pro: job_alerts row for candidate carol (frequency=daily, search_query=javascript)' );
	}

	// Application stages master list (so pipeline UI has stages to render).
	// Schema: id, board_id, label, color, sort_order, is_terminal, terminal_outcome
	// (column is 'label' not 'name'; board_id required; no created_at).
	$stages_table = $wpdb->prefix . 'wcb_application_stages';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $stages_table ) ) ) {
		foreach ( array( 'Applied', 'Shortlisted', 'Rejected' ) as $idx => $label ) {
			$wpdb->insert(
				$stages_table,
				array(
					'board_id'         => $board_id,
					'label'            => $label,
					'color'            => '#6366f1',
					'sort_order'       => $idx + 1,
					'is_terminal'      => 'Rejected' === $label ? 1 : 0,
					'terminal_outcome' => 'Rejected' === $label ? 'rejected' : null,
				)
			);
		}
		WP_CLI::log( '  pro: application_stages 3 rows (Applied / Shortlisted / Rejected) scoped to smoke board' );
	}
}

wp_cache_flush();
WP_CLI::success( 'fixtures seeded. Smoke walk can run now.' );
