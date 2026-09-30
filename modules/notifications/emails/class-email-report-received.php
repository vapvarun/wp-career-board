<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated email class name is intentional.
/**
 * Email: a job or a member was reported.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Notifications\Emails;

use WCB\Modules\Moderation\HiddenContent;
use WCB\Modules\Moderation\ModerationModule;
use WCB\Modules\Notifications\AbstractEmail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells the site owner about a report: on the first one, and again when a
 * job reaches the auto-hide threshold.
 *
 * @since 1.8.0
 */
class EmailReportReceived extends AbstractEmail {

	/**
	 * Unique email ID used as settings key and template slug.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'report-received';
	}

	/**
	 * Human-readable title shown in the Emails settings page.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Report Received', 'wp-career-board' );
	}

	/**
	 * Recipient type.
	 *
	 * @return string
	 */
	public function get_recipient(): string {
		return 'admin';
	}

	/**
	 * Default subject line.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( '[Report] {item} was reported', 'wp-career-board' );
	}

	/**
	 * Default message body.
	 *
	 * @return string
	 */
	public function get_default_body(): string {
		return self::heading( __( 'Something was reported', 'wp-career-board' ) )
			. '<p>' . sprintf(
				/* translators: 1: reported item (bold), 2: reason, 3: number of reports */
				esc_html__( '%1$s was reported as "%2$s". Open reports: %3$s.', 'wp-career-board' ),
				'<strong>{item}</strong>',
				'{reason}',
				'{report_count}'
			) . '</p>'
			. '<p>{hidden_note}</p>'
			. self::button( __( 'Review reports', 'wp-career-board' ), '{review_url}' );
	}

	/**
	 * Merge tags available to this email's subject and body.
	 *
	 * @return array<string, string>
	 */
	public function get_merge_tags(): array {
		return array(
			'item'         => __( 'Reported job or member', 'wp-career-board' ),
			'reason'       => __( 'Reason given', 'wp-career-board' ),
			'report_count' => __( 'Open reports', 'wp-career-board' ),
			'hidden_note'  => __( 'Whether the job was hidden', 'wp-career-board' ),
			'review_url'   => __( 'Admin list to review it', 'wp-career-board' ),
		);
	}

	/**
	 * Registers action hooks that trigger this email.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'wcb_job_reported', array( $this, 'on_job_reported' ), 20, 2 );
		add_action( 'wcb_member_reported', array( $this, 'on_member_reported' ), 20, 2 );
	}

	/**
	 * A job was reported.
	 *
	 * @param int    $job_id Job.
	 * @param string $reason Reason slug.
	 * @return void
	 */
	public function on_job_reported( int $job_id, string $reason ): void {
		$count = (int) get_post_meta( $job_id, '_wcb_flag_count', true );
		if ( ! ModerationModule::alert_due( $count ) ) {
			return;
		}
		$this->notify(
			get_the_title( $job_id ),
			ModerationModule::report_reasons()[ $reason ] ?? $reason,
			$count,
			'reports' === HiddenContent::reason( $job_id ) ? __( 'The job has been taken off the site until you review it. Dismissing the reports puts it back.', 'wp-career-board' ) : '',
			admin_url( 'admin.php?page=wcb-jobs&wcb_flag=open' )
		);
	}

	/**
	 * A member was reported.
	 *
	 * @param int    $user_id Reported member.
	 * @param string $reason  Reason slug.
	 * @return void
	 */
	public function on_member_reported( int $user_id, string $reason ): void {
		$count = ModerationModule::open_member_reports( $user_id );
		$user  = get_userdata( $user_id );
		if ( 1 !== $count || ! $user instanceof \WP_User ) {
			return;
		}
		$page = in_array( 'wcb_candidate', (array) $user->roles, true ) ? 'wcb-candidates' : 'wcb-employers';
		$this->notify(
			$user->display_name,
			\WCB\Api\Endpoints\MembersEndpoint::report_reasons()[ $reason ] ?? $reason,
			$count,
			'',
			admin_url( 'admin.php?page=' . $page . '&wcb_reported=1' )
		);
	}

	/**
	 * Send to the notification address (or the site admin email).
	 *
	 * @param string $item   What was reported.
	 * @param string $reason Reason label.
	 * @param int    $count  Open reports.
	 * @param string $note   Hidden note.
	 * @param string $url    Review URL.
	 * @return void
	 */
	private function notify( string $item, string $reason, int $count, string $note, string $url ): void {
		$notification_email = \WCB\Admin\Settings::string( 'notification_email', '' );
		$this->send(
			'' !== $notification_email ? $notification_email : (string) get_option( 'admin_email', '' ),
			array(
				'item'         => $item,
				'reason'       => $reason,
				'report_count' => (string) $count,
				'hidden_note'  => $note,
				'review_url'   => $url,
			),
			0
		);
	}
}
