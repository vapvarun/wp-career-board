<?php
/**
 * Realistic demo dataset for QA, screenshots and UX review (free + pro).
 *
 * Run via:
 *   wp eval-file wp-content/plugins/wp-career-board/bin/seed-demo-data.php
 *   wp eval-file wp-content/plugins/wp-career-board/bin/seed-demo-data.php teardown
 *
 * Reads bin/demo-data/dataset.json: 24 invented companies (logos in
 * bin/demo-data/logos), ~130 jobs with full descriptions, 42 candidates with
 * CC0 stock photos (downloaded from Openverse at seed time, credit kept on
 * each attachment), Pro resumes and ~120 applications across every status.
 *
 * Everything created is tagged `_wcb_demo` = 1 (posts, users, attachments) so
 * `teardown` removes exactly this dataset. Re-running tears down first.
 * Dev tooling only: bin/ is excluded from the release zip.
 *
 * @package WP_Career_Board
 */

// Dev tooling, not shipped: runs from the command line, never over the web.
if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	echo "ERROR: run via wp eval-file (WP-CLI).\n";
	return;
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$wcb_demo_dir = __DIR__ . '/demo-data';
$wcb_data     = json_decode( (string) file_get_contents( $wcb_demo_dir . '/dataset.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
if ( ! is_array( $wcb_data ) ) {
	WP_CLI::error( 'bin/demo-data/dataset.json is missing or invalid.' );
}

// ---------------------------------------------------------------------------
// Teardown: every post, attachment and user tagged _wcb_demo.
// ---------------------------------------------------------------------------
$wcb_teardown = static function (): void {
	$ids = get_posts(
		array(
			'post_type'      => array( 'wcb_application', 'wcb_job', 'wcb_resume', 'wcb_company', 'attachment' ),
			'post_status'    => 'any',
			'meta_key'       => '_wcb_demo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => '1',         // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $ids as $id ) {
		'attachment' === get_post_type( $id ) ? wp_delete_attachment( (int) $id, true ) : wp_delete_post( (int) $id, true );
	}
	$users = get_users(
		array(
			'meta_key'   => '_wcb_demo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => '1',         // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'     => 'ID',
		)
	);
	foreach ( $users as $uid ) {
		wp_delete_user( (int) $uid );
	}
	WP_CLI::log( sprintf( 'Removed %d posts/attachments and %d users from the demo dataset.', count( $ids ), count( $users ) ) );
};

$wcb_teardown();
if ( in_array( 'teardown', (array) ( $args ?? array() ), true ) ) {
	WP_CLI::success( 'Demo dataset removed.' );
	return;
}

$wcb_pro = defined( 'WCBP_VERSION' );

// Mark a post or user as demo data.
$wcb_tag = static function ( int $id, string $kind = 'post' ): void {
	'user' === $kind ? update_user_meta( $id, '_wcb_demo', '1' ) : update_post_meta( $id, '_wcb_demo', '1' );
};

// Sideload a local or remote image as an attachment owned by $owner.
$wcb_attach = static function ( string $file, string $name, int $owner, string $title, string $caption = '' ) use ( $wcb_tag ): int {
	$tmp = wp_tempnam( $name );
	copy( $file, $tmp );
	$id = media_handle_sideload(
		array(
			'name'     => $name,
			'tmp_name' => $tmp,
		),
		0,
		$title,
		array(
			'post_author'  => $owner,
			'post_excerpt' => $caption,
		)
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( $name . ': ' . $id->get_error_message() );
		return 0;
	}
	$wcb_tag( (int) $id );
	return (int) $id;
};

// Term id by name, created when missing (names match the setup wizard vocabulary).
$wcb_term = static function ( string $name, string $taxonomy ): int {
	$term = get_term_by( 'name', $name, $taxonomy );
	if ( $term instanceof \WP_Term ) {
		return (int) $term->term_id;
	}
	$made = wp_insert_term( $name, $taxonomy );
	return is_wp_error( $made ) ? 0 : (int) $made['term_id'];
};

// Subcategories, so the demo shows a real category tree. First keyword in the title wins.
$wcb_subcategories = array(
	'Engineering' => array(
		'Frontend Development'  => array( 'Frontend' ),
		'Backend Development'   => array( 'Backend' ),
		'Full-Stack Development' => array( 'Full-Stack' ),
		'Mobile Development'    => array( 'Mobile', 'React Native' ),
		'Quality Assurance'     => array( 'QA' ),
	),
	'Design'      => array(
		'Product Design' => array( 'Product Designer' ),
		'Brand Design'   => array( 'Brand Designer' ),
		'UX Research'    => array( 'UX Researcher' ),
	),
	'Data'        => array(
		'Data Analysis'    => array( 'Data Analyst' ),
		'Data Engineering' => array( 'Data Engineer' ),
		'Data Science'     => array( 'Data Scientist' ),
	),
);
$wcb_category = static function ( string $parent, string $title ) use ( $wcb_term, $wcb_subcategories ): int {
	$parent_id = $wcb_term( $parent, 'wcb_category' );
	foreach ( $wcb_subcategories[ $parent ] ?? array() as $child => $keywords ) {
		foreach ( $keywords as $keyword ) {
			if ( false !== stripos( $title, $keyword ) ) {
				$term = get_term_by( 'name', $child, 'wcb_category' );
				if ( $term instanceof \WP_Term ) {
					return (int) $term->term_id;
				}
				$made = wp_insert_term( $child, 'wcb_category', array( 'parent' => $parent_id ) );
				return is_wp_error( $made ) ? $parent_id : (int) $made['term_id'];
			}
		}
	}
	return $parent_id;
};

$wcb_user = static function ( array $p, string $role, string $display ) use ( $wcb_tag ): int {
	$uid = wp_insert_user(
		array(
			'user_login'   => $p['login'],
			'user_email'   => $p['email'],
			'user_pass'    => wp_generate_password( 24 ),
			'first_name'   => $p['first'],
			'last_name'    => $p['last'],
			'display_name' => $display,
			'nickname'     => $display,
			'role'         => $role,
		)
	);
	if ( is_wp_error( $uid ) ) {
		WP_CLI::warning( $p['login'] . ': ' . $uid->get_error_message() );
		return 0;
	}
	$wcb_tag( (int) $uid, 'user' );
	return (int) $uid;
};

// ---------------------------------------------------------------------------
// Companies + their employer accounts.
// ---------------------------------------------------------------------------
$wcb_companies = array();
foreach ( $wcb_data['companies'] as $c ) {
	$emp = $wcb_user( $c['employer'], 'wcb_employer', $c['employer']['first'] . ' ' . $c['employer']['last'] );
	$cid = (int) wp_insert_post(
		array(
			'post_type'    => 'wcb_company',
			'post_status'  => 'publish',
			'post_title'   => $c['name'],
			'post_name'    => $c['slug'],
			'post_content' => '<p>' . esc_html( $c['about'] ) . '</p>',
			'post_author'  => $emp,
			'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( '-' . wp_rand( 60, 400 ) . ' days' ) ),
		)
	);
	$wcb_tag( $cid );
	$meta = array(
		'_wcb_tagline'       => $c['tagline'],
		'_wcb_website'       => $c['website'],
		'_wcb_linkedin'      => $c['linkedin'],
		'_wcb_industry'      => $c['industry'],
		'_wcb_company_size'  => $c['size'],
		'_wcb_founded'       => $c['founded'],
		'_wcb_hq_location'   => $c['hq'],
		'_wcb_company_owner' => $emp,
	);
	foreach ( $meta as $k => $v ) {
		update_post_meta( $cid, $k, $v );
	}
	\WCB\Core\Locations::sync_company_hq( $cid, $c['hq'] );
	update_user_meta( $emp, '_wcb_company_id', $cid );
	update_user_meta( $emp, '_wcb_job_title', $c['employer']['title'] );

	$logo = $wcb_attach( $wcb_demo_dir . '/' . $c['logo'], $c['slug'] . '-logo.webp', $emp, $c['name'] . ' logo' );
	if ( $logo ) {
		set_post_thumbnail( $cid, $logo );
	}
	$wcb_companies[ $c['slug'] ] = array(
		'id'       => $cid,
		'employer' => $emp,
		'name'     => $c['name'],
	);
}
WP_CLI::log( sprintf( 'Companies: %d (with logos and employer accounts).', count( $wcb_companies ) ) );

// ---------------------------------------------------------------------------
// Jobs.
// ---------------------------------------------------------------------------
$wcb_job_ids = array();
foreach ( $wcb_data['jobs'] as $j ) {
	$co   = $wcb_companies[ $j['company'] ];
	$html = '<p>' . esc_html( $j['summary'] ) . '</p>'
		. '<h3>' . esc_html__( 'What you will do', 'wp-career-board' ) . '</h3><ul><li>' . implode( '</li><li>', array_map( 'esc_html', $j['do'] ) ) . '</li></ul>'
		. '<h3>' . esc_html__( 'What we are looking for', 'wp-career-board' ) . '</h3><ul><li>' . implode( '</li><li>', array_map( 'esc_html', $j['need'] ) ) . '</li></ul>'
		. '<h3>' . esc_html__( 'Benefits', 'wp-career-board' ) . '</h3><ul><li>' . implode( '</li><li>', array_map( 'esc_html', $j['benefits'] ) ) . '</li></ul>';

	$posted = strtotime( '-' . (int) $j['posted_days_ago'] . ' days -' . wp_rand( 0, 600 ) . ' minutes' );
	$status = array(
		'open'    => 'publish',
		'expired' => 'wcb_expired',
		'closed'  => 'wcb_closed',
	)[ $j['state'] ];
	$jid    = (int) wp_insert_post(
		array(
			'post_type'     => 'wcb_job',
			'post_status'   => $status,
			'post_title'    => $j['title'],
			'post_content'  => $html,
			'post_author'   => $co['employer'],
			'post_date'     => wp_date( 'Y-m-d H:i:s', $posted ),
			'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $posted ),
		)
	);
	$wcb_tag( $jid );
	$meta = array(
		'_wcb_company_id'      => $co['id'],
		'_wcb_company_name'    => $co['name'],
		'_wcb_salary_min'      => (string) $j['salary_min'],
		'_wcb_salary_max'      => (string) $j['salary_max'],
		'_wcb_salary_currency' => $j['currency'],
		'_wcb_salary_type'     => $j['salary_type'],
		'_wcb_remote'          => $j['remote'] ? '1' : '0',
		'_wcb_featured'        => $j['featured'] ? '1' : '0',
		'_wcb_deadline'        => gmdate( 'Y-m-d', strtotime( ( $j['deadline_days'] >= 0 ? '+' : '' ) . (int) $j['deadline_days'] . ' days' ) ),
	);
	foreach ( $meta as $k => $v ) {
		update_post_meta( $jid, $k, $v );
	}
	$locations = array( $wcb_term( $j['location'], 'wcb_location' ) );
	if ( $j['remote'] && 'Remote' !== $j['location'] ) {
		$locations[] = $wcb_term( 'Remote', 'wcb_location' );
	}
	wp_set_object_terms( $jid, array( $wcb_category( $j['category'], $j['title'] ) ), 'wcb_category' );
	wp_set_object_terms( $jid, array( $wcb_term( $j['type'], 'wcb_job_type' ) ), 'wcb_job_type' );
	wp_set_object_terms( $jid, array( $wcb_term( $j['experience'], 'wcb_experience' ) ), 'wcb_experience' );
	wp_set_object_terms( $jid, $locations, 'wcb_location' );
	wp_set_object_terms( $jid, array_map( static fn( $t ) => $wcb_term( $t, 'wcb_tag' ), $j['tags'] ), 'wcb_tag' );
	$wcb_job_ids[] = $jid;
}
WP_CLI::log( sprintf( 'Jobs: %d.', count( $wcb_job_ids ) ) );

// ---------------------------------------------------------------------------
// Candidates: account, cropped CC0 photo, Pro resume.
// ---------------------------------------------------------------------------
$wcb_people = array();
foreach ( $wcb_data['people'] as $i => $p ) {
	$uid = $wcb_user( $p, 'wcb_candidate', $p['first'] . ' ' . $p['last'] );
	if ( ! $uid ) {
		$wcb_people[ $i ] = array(
			'user'   => 0,
			'resume' => 0,
		);
		continue;
	}
	update_user_meta( $uid, 'description', $p['summary'] );
	if ( $p['open_to_work'] ) {
		update_user_meta( $uid, '_wcb_open_to_work', '1' );
	}

	// Photo: download the Openverse copy, crop to a square headshot, keep the credit.
	$photo = 0;
	$src   = download_url( $p['photo']['thumbnail'], 60 );
	if ( is_wp_error( $src ) ) {
		$src = download_url( $p['photo']['url'], 120 );
	}
	if ( ! is_wp_error( $src ) ) {
		$editor = wp_get_image_editor( $src );
		if ( ! is_wp_error( $editor ) ) {
			$size  = $editor->get_size();
			$crop  = $p['photo']['crop'];
			$side  = (int) ( min( $size['width'], $size['height'] ) * (float) $crop['zoom'] );
			$x     = (int) max( 0, min( $size['width'] - $side, $crop['cx'] * $size['width'] - $side / 2 ) );
			$y     = (float) $crop['zoom'] < 1 ? (int) max( 0, min( $size['height'] - $side, $crop['cy'] * $size['height'] - $side / 2 ) ) : 0;
			$editor->crop( $x, $y, $side, $side, 480, 480 );
			$saved = $editor->save( $src . '.jpg', 'image/jpeg' );
			if ( ! is_wp_error( $saved ) ) {
				$credit = sprintf( 'Photo: %s, %s, via %s (%s)', $p['photo']['creator'] ? $p['photo']['creator'] : 'unknown', $p['photo']['license'], $p['photo']['source'], $p['photo']['landing'] );
				$photo  = $wcb_attach( $saved['path'], $p['login'] . '.jpg', $uid, $p['first'] . ' ' . $p['last'], $credit );
				wp_delete_file( $saved['path'] );
			}
		}
		wp_delete_file( $src );
	}
	if ( ! $photo ) {
		WP_CLI::warning( 'No photo for ' . $p['login'] );
	}

	$resume = 0;
	if ( $wcb_pro ) {
		$resume = (int) wp_insert_post(
			array(
				'post_type'   => 'wcb_resume',
				'post_status' => 'publish',
				'post_title'  => $p['first'] . ' ' . $p['last'],
				'post_author' => $uid,
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-' . wp_rand( 20, 200 ) . ' days' ) ),
			)
		);
		$wcb_tag( $resume );
		$meta = array(
			'_wcb_resume_headline'          => $p['headline'],
			'_wcb_resume_summary'           => $p['summary'],
			'_wcb_resume_location'          => $p['location'],
			'_wcb_resume_years_exp'         => $p['years'],
			'_wcb_resume_public'            => '1',
			'_wcb_resume_open_to_work'      => $p['open_to_work'] ? '1' : '0',
			'_wcb_resume_experience'        => $p['experience'],
			'_wcb_resume_education_college' => $p['education'],
			'_wcb_resume_skills'            => $p['skills'],
			'_wcb_resume_photo_id'          => $photo,
		);
		foreach ( $meta as $k => $v ) {
			update_post_meta( $resume, $k, $v );
		}
		// Let Pro derive its search/taxonomy data the way a real save does.
		do_action( 'save_post_wcb_resume', $resume, get_post( $resume ), true );
	}
	$wcb_people[ $i ] = array(
		'user'   => $uid,
		'resume' => $resume,
	);
}
WP_CLI::log( sprintf( 'Candidates: %d%s.', count( array_filter( array_column( $wcb_people, 'user' ) ) ), $wcb_pro ? ' (with resumes)' : '' ) );

// ---------------------------------------------------------------------------
// Applications.
// ---------------------------------------------------------------------------
$wcb_apps = 0;
foreach ( $wcb_data['applications'] as $a ) {
	$who = $wcb_people[ $a['person'] ];
	$job = $wcb_job_ids[ $a['job'] ] ?? 0;
	if ( ! $who['user'] || ! $job ) {
		continue;
	}
	$when = strtotime( '-' . (int) $a['days_ago'] . ' days -' . wp_rand( 0, 900 ) . ' minutes' );
	$app  = (int) wp_insert_post(
		array(
			'post_type'     => 'wcb_application',
			'post_status'   => 'publish',
			/* translators: 1: candidate user ID, 2: job ID. Same format the apply endpoint writes. */
			'post_title'    => sprintf( __( 'Application: User %1$d → Job %2$d', 'wp-career-board' ), $who['user'], $job ),
			'post_author'   => $who['user'],
			'post_date'     => wp_date( 'Y-m-d H:i:s', $when ),
			'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $when ),
		)
	);
	$wcb_tag( $app );
	$meta = array(
		'_wcb_job_id'                => $job,
		'_wcb_candidate_id'          => $who['user'],
		'_wcb_status'                => $a['status'],
		'_wcb_cover_letter'          => $a['cover'],
		'_wcb_job_title_snapshot'    => get_the_title( $job ),
		'_wcb_company_name_snapshot' => (string) get_post_meta( $job, '_wcb_company_name', true ),
	);
	if ( $who['resume'] ) {
		$meta['_wcb_resume_id'] = $who['resume'];
	}
	foreach ( $meta as $k => $v ) {
		update_post_meta( $app, $k, $v );
	}
	++$wcb_apps;
}
WP_CLI::log( sprintf( 'Applications: %d.', $wcb_apps ) );

// Pipeline: give every application its stage through the product's own sweeps.
if ( $wcb_pro && class_exists( '\WCB\Pro\Modules\Pipeline\PipelineModule' ) ) {
	\WCB\Pro\Modules\Pipeline\PipelineModule::schedule_backfill();
	for ( $wcb_i = 0; $wcb_i < 10 && false !== get_option( \WCB\Pro\Modules\Pipeline\PipelineModule::BACKFILL_CURSOR_OPTION, false ); $wcb_i++ ) {
		do_action( \WCB\Pro\Modules\Pipeline\PipelineModule::BACKFILL_HOOK );
	}
	update_option( \WCB\Pro\Modules\Pipeline\PipelineModule::RECONCILE_CURSOR_OPTION, 0, false );
	for ( $wcb_i = 0; $wcb_i < 10; $wcb_i++ ) {
		do_action( \WCB\Pro\Modules\Pipeline\PipelineModule::RECONCILE_HOOK );
	}
}

update_option( 'wcb_jobs_cache_v', (int) get_option( 'wcb_jobs_cache_v', 0 ) + 1, false );
wp_cache_flush();
WP_CLI::success( 'Demo dataset ready.' );
