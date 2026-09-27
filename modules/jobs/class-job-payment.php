<?php
/**
 * The one place a job's price is collected.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Free has no prices; Pro's credits answer the `wcb_job_payment` filter.
 * Every place a job starts costing calls {@see self::charge()}: a new job
 * (after it is inserted, before anyone is told about it), a rejected job
 * sent back for review, an expired or closed job brought back, and a job
 * moved to another board, and a Featured upgrade. A charge that fails
 * returns the 402 built by
 * {@see self::insufficient()}, which carries what the job costs, the
 * balance and where to buy credits, so the website and the app can both
 * send the employer straight to a purchase.
 *
 * A job a moderator approves that its employer cannot pay for stays pending
 * with {@see self::AWAITING} set, instead of going live unpaid.
 *
 * @since 1.8.0
 */
final class JobPayment {

	/**
	 * Post meta set while an approved job waits for its employer's payment.
	 *
	 * @since 1.8.0
	 * @var string
	 */
	public const AWAITING = '_wcb_awaiting_payment';

	/**
	 * Collect what the job costs for this event.
	 *
	 * @since 1.8.0
	 * @param int    $job_id Job post ID.
	 * @param string $event  create | resubmit | republish | board_change | feature.
	 * @return bool|\WP_Error True when paid for or free.
	 */
	public static function charge( int $job_id, string $event ): bool|\WP_Error {
		/**
		 * Filter - collect a job's price.
		 *
		 * Return true when the job is paid for (or free), a WP_Error (402,
		 * see JobPayment::insufficient()) when it can't be.
		 *
		 * @since 1.8.0
		 *
		 * @param true|\WP_Error $paid   True so far.
		 * @param int            $job_id Job post ID.
		 * @param string         $event  create | resubmit | republish | board_change,
		 *                               or feature (the paid Featured upgrade; the handler
		 *                               that takes payment also sets the flag).
		 */
		$paid = apply_filters( 'wcb_job_payment', true, $job_id, $event );
		return is_wp_error( $paid ) ? $paid : true;
	}

	/**
	 * The 402 for a job its employer can't pay for.
	 *
	 * @since 1.8.0
	 * @param int $cost    What the job costs, in credits.
	 * @param int $balance The employer's balance.
	 * @return \WP_Error
	 */
	public static function insufficient( int $cost, int $balance ): \WP_Error {
		return new \WP_Error(
			'wcb_insufficient_credits',
			sprintf(
				/* translators: 1: credit cost, 2: current balance */
				__( 'This job costs %1$d credits. Your balance: %2$d credits.', 'wp-career-board' ),
				$cost,
				$balance
			),
			array(
				'status'       => 402,
				'cost'         => $cost,
				'balance'      => $balance,
				'purchase_url' => (string) apply_filters( 'wcb_credit_purchase_url', '' ),
			)
		);
	}

	/**
	 * Whether an approved job is waiting for its employer to pay.
	 *
	 * @since 1.8.0
	 * @param int $job_id Job post ID.
	 * @return bool
	 */
	public static function is_awaiting( int $job_id ): bool {
		return '1' === (string) get_post_meta( $job_id, self::AWAITING, true );
	}
}
