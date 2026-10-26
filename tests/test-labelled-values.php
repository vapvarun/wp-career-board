<?php
/**
 * Custom-field display value tests (1.8.2).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-labelled-values.php
 *
 * Pins: FormCustomFields::labelled_values() shows a choice answer by its
 * label, and a multi-choice answer (stored comma-joined) as each label
 * joined with the list separator, never the raw "Laptop,Monitor" string
 * (Basecamp 10440256553). Every display surface reads this: the job page
 * Additional details, application answers in admin, the CSV, REST and the
 * privacy export.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_lv_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

$wcb_post = wp_insert_post(
	array(
		'post_type'   => 'post',
		'post_status' => 'draft',
		'post_title'  => 'QA-RFT labelled values',
	)
);

$wcb_groups = array(
	array(
		'fields' => array(
			array(
				'key'     => 'qa_rft_kit',
				'type'    => 'multi_select',
				'label'   => 'Equipment',
				'options' => array( 'Laptop', 'Monitor', 'Headset' ),
			),
			array(
				'key'     => 'qa_rft_shift',
				'type'    => 'select',
				'label'   => 'Shift',
				'options' => array(
					'day'   => 'Day shift',
					'night' => 'Night shift',
				),
			),
			array(
				'key'     => 'qa_rft_days',
				'type'    => 'checkbox',
				'label'   => 'Days',
				'options' => array(
					'mon' => 'Monday',
					'tue' => 'Tuesday',
				),
			),
			array(
				'key'   => 'qa_rft_note',
				'type'  => 'text',
				'label' => 'Note',
			),
		),
	),
);

update_post_meta( $wcb_post, 'qa_rft_kit', 'Laptop,Monitor' );
update_post_meta( $wcb_post, 'qa_rft_shift', 'night' );
update_post_meta( $wcb_post, 'qa_rft_days', 'mon,tue' );
update_post_meta( $wcb_post, 'qa_rft_note', 'a,b' );

$wcb_rows = array_column( \WCB\Core\FormCustomFields::labelled_values( $wcb_groups, $wcb_post ), null, 'key' );
$wcb_sep  = wp_get_list_item_separator();

wcb_lv_assert( 'Laptop' . $wcb_sep . 'Monitor' === ( $wcb_rows['qa_rft_kit']['value'] ?? '' ), 'multi-select answer lists each item, not "Laptop,Monitor"' );
wcb_lv_assert( array( 'Laptop', 'Monitor' ) === ( $wcb_rows['qa_rft_kit']['items'] ?? array() ), 'multi-select items are the chosen labels' );
wcb_lv_assert( 'Night shift' === ( $wcb_rows['qa_rft_shift']['value'] ?? '' ), 'select answer shows its label, not its stored key' );
wcb_lv_assert( 'Monday' . $wcb_sep . 'Tuesday' === ( $wcb_rows['qa_rft_days']['value'] ?? '' ), 'checkbox with choices lists its labels' );
wcb_lv_assert( 'a,b' === ( $wcb_rows['qa_rft_note']['value'] ?? '' ) && array() === $wcb_rows['qa_rft_note']['items'], 'a text answer with a comma is left as typed' );

wp_delete_post( $wcb_post, true );

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All labelled value tests passed.' );
}
