<?php
/**
 * The card both job forms show to a visitor who can't post: signed out, a
 * candidate account, or a member who isn't an employer yet.
 *
 * Same card as the dashboards (templates/parts/dashboard-gate.php), so a
 * guest never meets a leftover sentence on a blank page.
 *
 * @package WP_Career_Board
 * @since   1.8.1
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcb_emp_reg_page = \WCB\Admin\Pages::get_id( 'employer_registration_page' );
$wcb_queue_url    = \WCB\Core\Roles::moderation_queue_url();
$wcb_gate         = array(
	'icon'    => 'briefcase',
	'title'   => __( 'Post a job', 'wp-career-board' ),
	'actions' => array(),
);

if ( ! is_user_logged_in() ) {
	$wcb_gate['message']   = __( 'Sign in with your employer account to post a job.', 'wp-career-board' );
	$wcb_gate['signup']    = $wcb_emp_reg_page;
	$wcb_gate['actions'][] = array(
		'label'   => __( 'Sign in', 'wp-career-board' ),
		'url'     => wp_login_url( (string) get_permalink() ),
		'primary' => true,
	);
} elseif ( in_array( 'wcb_candidate', (array) wp_get_current_user()->roles, true ) ) {
	// A candidate account can't become an employer (registration refuses it).
	$wcb_gate['message'] = __( 'This is a candidate account. To post jobs, sign in with an employer account.', 'wp-career-board' );
} else {
	$wcb_gate['message'] = __( 'Posting a job is for employers. Register as an employer to start hiring - it only takes a minute.', 'wp-career-board' );
	if ( $wcb_emp_reg_page > 0 ) {
		$wcb_gate['actions'][] = array(
			'label'   => __( 'Register as an employer', 'wp-career-board' ),
			'url'     => (string) get_permalink( $wcb_emp_reg_page ),
			'primary' => '' === $wcb_queue_url,
		);
	}
}

if ( '' !== $wcb_queue_url ) {
	array_unshift(
		$wcb_gate['actions'],
		array(
			'label'   => __( 'Review jobs', 'wp-career-board' ),
			'url'     => $wcb_queue_url,
			'primary' => true,
		)
	);
}

require WCB_DIR . 'templates/parts/dashboard-gate.php';
