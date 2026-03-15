<?php
/**
 * Email notification driver — wp_mail wrappers for all job board events.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Listens to WCB action hooks and sends wp_mail notifications.
 *
 * All sent emails are logged to the wcb_notifications_log DB table.
 * Templates live in modules/notifications/templates/{name}.php — if a template
 * file is missing, a plain-text fallback is used so delivery never silently drops.
 *
 * @since 1.0.0
 */
final class NotificationsEmail {

	/**
	 * Register all notification hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'wcb_job_created', array( $this, 'on_job_created' ), 10, 1 );
		add_action( 'wcb_application_submitted', array( $this, 'on_application_submitted' ), 10, 3 );
		add_action( 'wcb_application_status_changed', array( $this, 'on_status_changed' ), 10, 3 );
		add_action( 'wcb_job_approved', array( $this, 'on_job_approved' ), 10, 1 );
		add_action( 'wcb_job_rejected', array( $this, 'on_job_rejected' ), 10, 2 );
		add_action( 'wcb_job_expired', array( $this, 'on_job_expired' ), 10, 1 );
	}

	// --- Handlers ---------------------------------------------------------------

	/**
	 * Notify admin when a new job is pending approval.
	 *
	 * Fires only in approval-required mode (post_status = pending).
	 * Auto-published jobs need no admin review email.
	 *
	 * @since 1.0.0
	 *
	 * @param int $job_id Newly created job post ID.
	 * @return void
	 */
	public function on_job_created( int $job_id ): void {
		$job = get_post( $job_id );
		if ( ! $job instanceof \WP_Post || 'pending' !== $job->post_status ) {
			return;
		}

		$wcb_s    = (array) get_option( 'wcb_settings', array() );
		$wcb_to   = ! empty( $wcb_s['notification_email'] ) ? $wcb_s['notification_email'] : (string) get_option( 'admin_email', '' );
		$this->send(
			$wcb_to,
			/* translators: %s: job title */
			sprintf( __( '[Action Required] New job pending approval: %s', 'wp-career-board' ), $job->post_title ),
			$this->render(
				'job-pending-review',
				array(
					'job_title'   => $job->post_title,
					'approve_url' => admin_url( 'post.php?post=' . $job_id . '&action=edit' ),
				)
			),
			0
		);
	}

	/**
	 * Notify employer (new application) and candidate (confirmation) on submission.
	 *
	 * @since 1.0.0
	 *
	 * @param int $app_id       Application post ID.
	 * @param int $job_id       Job post ID.
	 * @param int $candidate_id Candidate user ID.
	 * @return void
	 */
	public function on_application_submitted( int $app_id, int $job_id, int $candidate_id ): void {
		$job       = get_post( $job_id );
		$employer  = $job instanceof \WP_Post ? get_user_by( 'ID', (int) $job->post_author ) : false;
		$candidate = get_user_by( 'ID', $candidate_id );
		$wcb_s     = (array) get_option( 'wcb_settings', array() );

		if ( $job instanceof \WP_Post && $employer instanceof \WP_User ) {
			$dashboard_employer = get_permalink( isset( $wcb_s['employer_dashboard_page'] ) ? (int) $wcb_s['employer_dashboard_page'] : 0 );
			$this->send(
				$employer->user_email,
				/* translators: %s: job title */
				sprintf( __( 'New application for: %s', 'wp-career-board' ), $job->post_title ),
				$this->render(
					'application-received',
					array(
						'job_title'      => $job->post_title,
						'candidate_name' => $candidate instanceof \WP_User ? $candidate->display_name : '',
						'dashboard_url'  => $dashboard_employer ? $dashboard_employer : admin_url(),
					)
				),
				$employer->ID
			);
		}

		if ( $job instanceof \WP_Post && $candidate instanceof \WP_User ) {
			$dashboard_candidate = get_permalink( isset( $wcb_s['candidate_dashboard_page'] ) ? (int) $wcb_s['candidate_dashboard_page'] : 0 );
			$this->send(
				$candidate->user_email,
				/* translators: %s: job title */
				sprintf( __( 'Application submitted: %s', 'wp-career-board' ), $job->post_title ),
				$this->render(
					'application-confirmation',
					array(
						'job_title'     => $job->post_title,
						'dashboard_url' => $dashboard_candidate ? $dashboard_candidate : home_url(),
					)
				),
				$candidate->ID
			);
		}
	}

	/**
	 * Notify candidate when their application status changes.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $app_id     Application post ID.
	 * @param string $old_status Previous status slug.
	 * @param string $new_status New status slug.
	 * @return void
	 */
	public function on_status_changed( int $app_id, string $old_status, string $new_status ): void {
		$candidate_id = (int) get_post_meta( $app_id, '_wcb_candidate_id', true );
		$job_id       = (int) get_post_meta( $app_id, '_wcb_job_id', true );
		$candidate    = get_user_by( 'ID', $candidate_id );
		$job          = get_post( $job_id );

		if ( ! $candidate instanceof \WP_User || ! $job instanceof \WP_Post ) {
			return;
		}

		$wcb_s         = (array) get_option( 'wcb_settings', array() );
		$dashboard_url = get_permalink( isset( $wcb_s['candidate_dashboard_page'] ) ? (int) $wcb_s['candidate_dashboard_page'] : 0 );
		$this->send(
			$candidate->user_email,
			/* translators: %s: job title */
			sprintf( __( 'Your application status updated: %s', 'wp-career-board' ), $job->post_title ),
			$this->render(
				'application-status-changed',
				array(
					'job_title'     => $job->post_title,
					'new_status'    => ucfirst( $new_status ),
					'dashboard_url' => $dashboard_url ? $dashboard_url : home_url(),
				)
			),
			$candidate->ID
		);
	}

	/**
	 * Notify employer when their job is approved by an admin.
	 *
	 * @since 1.0.0
	 *
	 * @param int $job_id Job post ID.
	 * @return void
	 */
	public function on_job_approved( int $job_id ): void {
		$job      = get_post( $job_id );
		$employer = $job instanceof \WP_Post ? get_user_by( 'ID', (int) $job->post_author ) : false;

		if ( ! $job instanceof \WP_Post || ! $employer instanceof \WP_User ) {
			return;
		}

		$this->send(
			$employer->user_email,
			/* translators: %s: job title */
			sprintf( __( 'Your job has been approved: %s', 'wp-career-board' ), $job->post_title ),
			$this->render(
				'job-approved',
				array(
					'job_title' => $job->post_title,
					'job_url'   => get_permalink( $job_id ),
				)
			),
			$employer->ID
		);
	}

	/**
	 * Notify employer when their job is rejected.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $job_id Job post ID.
	 * @param string $reason Rejection reason from admin.
	 * @return void
	 */
	public function on_job_rejected( int $job_id, string $reason ): void {
		$job      = get_post( $job_id );
		$employer = $job instanceof \WP_Post ? get_user_by( 'ID', (int) $job->post_author ) : false;

		if ( ! $job instanceof \WP_Post || ! $employer instanceof \WP_User ) {
			return;
		}

		$this->send(
			$employer->user_email,
			/* translators: %s: job title */
			sprintf( __( 'Your job was not approved: %s', 'wp-career-board' ), $job->post_title ),
			$this->render(
				'job-rejected',
				array(
					'job_title' => $job->post_title,
					'reason'    => $reason,
				)
			),
			$employer->ID
		);
	}

	/**
	 * Notify employer when their job listing expires.
	 *
	 * @since 1.0.0
	 *
	 * @param int $job_id Job post ID.
	 * @return void
	 */
	public function on_job_expired( int $job_id ): void {
		$job      = get_post( $job_id );
		$employer = $job instanceof \WP_Post ? get_user_by( 'ID', (int) $job->post_author ) : false;

		if ( ! $job instanceof \WP_Post || ! $employer instanceof \WP_User ) {
			return;
		}

		$wcb_s      = (array) get_option( 'wcb_settings', array() );
		$repost_url = get_permalink( isset( $wcb_s['employer_dashboard_page'] ) ? (int) $wcb_s['employer_dashboard_page'] : 0 );
		$this->send(
			$employer->user_email,
			/* translators: %s: job title */
			sprintf( __( 'Your job has expired: %s', 'wp-career-board' ), $job->post_title ),
			$this->render(
				'job-expired',
				array(
					'job_title'  => $job->post_title,
					'repost_url' => $repost_url ? $repost_url : admin_url(),
				)
			),
			$employer->ID
		);
	}

	// --- Internals --------------------------------------------------------------

	/**
	 * Send an HTML email and log the result to wcb_notifications_log.
	 *
	 * @since 1.0.0
	 *
	 * @param string $to      Recipient email address.
	 * @param string $subject Email subject.
	 * @param string $body    HTML email body.
	 * @param int    $user_id WP user ID associated with this notification (0 = admin).
	 * @return void
	 */
	private function send( string $to, string $subject, string $body, int $user_id ): void {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$sent    = wp_mail( $to, $subject, $body, $headers );

		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'wcb_notifications_log',
			array(
				'user_id'    => $user_id,
				'event_type' => (string) current_action(),
				'channel'    => 'email',
				'payload'    => (string) wp_json_encode(
					array(
						'to'      => $to,
						'subject' => $subject,
					)
				),
				'status'     => $sent ? 'sent' : 'failed',
				'sent_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Render an email template, falling back to plain text if the file is missing.
	 *
	 * Templates are PHP files in modules/notifications/templates/ that output HTML.
	 * Variables are extracted into template scope via extract().
	 *
	 * @since 1.0.0
	 *
	 * @param string               $template Template slug (without .php extension).
	 * @param array<string, mixed> $vars     Variables to pass into the template.
	 * @return string Rendered HTML (or plain-text fallback).
	 */
	private function render( string $template, array $vars ): string {
		$file = WCB_DIR . 'modules/notifications/templates/' . $template . '.php';
		if ( ! file_exists( $file ) ) {
			// Plain-text fallback so delivery never silently drops.
			$lines = array();
			foreach ( $vars as $key => $value ) {
				$lines[] = ucfirst( (string) $key ) . ': ' . (string) $value;
			}
			return implode( "\n", $lines );
		}
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- intentional template pattern.
		extract( $vars, EXTR_SKIP );
		ob_start();
		include $file;
		return (string) ob_get_clean();
	}
}
