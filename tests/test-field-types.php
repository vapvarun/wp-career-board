<?php
/**
 * Custom field types read the same everywhere (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-field-types.php
 *
 * Pins: a File link answer is checked as a web address and shown as a link;
 * a choice that contains a comma ("Dallas, TX") saves, re-saves and reads
 * back whole; a Checkbox works as one yes/no box and, with choices, as a
 * tick-any list.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Core\FormCustomFields;

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

$groups = array(
	array(
		'id'     => 'hiring',
		'label'  => 'Hiring details',
		'fields' => array(
			array(
				'key'      => 'wcbf_role_brief',
				'type'     => 'file',
				'label'    => 'Role brief',
				'required' => true,
			),
			array(
				'key'      => 'wcbf_office',
				'type'     => 'multi_select',
				'label'    => 'Office location',
				'required' => true,
				'options'  => array( 'Dallas, TX', 'Austin, TX', 'Denver, CO' ),
			),
			array(
				'key'     => 'wcbf_shift',
				'type'    => 'checkbox',
				'label'   => 'Shift pattern',
				'options' => array( 'Day', 'Night' ),
			),
			array(
				'key'   => 'wcbf_relocation',
				'type'  => 'checkbox',
				'label' => 'Relocation support',
			),
			array(
				'key'     => 'wcbf_equipment',
				'type'    => 'multi_select',
				'label'   => 'Equipment',
				'options' => array( 'Laptop', 'Monitor' ),
			),
		),
	),
);

// Card 10516961685: a File link answer that is not a web address is refused, not saved empty.
$err = FormCustomFields::required_error( $groups, array( 'wcbf_role_brief' => 'not a valid url', 'wcbf_office' => array( 'Austin, TX' ) ) );
$ok( $err instanceof WP_Error && array( 'wcbf_role_brief' ) === ( $err->get_error_data()['fields'] ?? array() ), 'a File link answer that is not a web address is refused and names the field' );
$ok( null === FormCustomFields::required_error( $groups, array( 'wcbf_role_brief' => 'https://northwind.example/briefs/platform-engineer.pdf', 'wcbf_office' => array( 'Austin, TX' ) ) ), 'a File link with a real address passes' );

$post = (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'draft',
		'post_title'  => 'Senior Platform Engineer',
	)
);

// Card 10516961609: a choice with a comma saves, re-saves and reads back whole.
FormCustomFields::save_values(
	$groups,
	$post,
	array(
		'wcbf_role_brief' => 'https://northwind.example/briefs/platform-engineer.pdf',
		'wcbf_office'     => array( 'Dallas, TX', 'Austin, TX' ),
		'wcbf_shift'      => array( 'Day' ),
		'wcbf_relocation' => true,
	)
);
$ok( 'Dallas, TX,Austin, TX' === get_post_meta( $post, 'wcbf_office', true ), 'two comma choices keep the stored comma-joined format' );
$ok( null === FormCustomFields::required_error( $groups, array(), $post ), 'a saved "Dallas, TX" answer passes the required check on the next save' );
$seeded = FormCustomFields::load_values( $groups, $post );
$ok( null === FormCustomFields::required_error( $groups, array( 'wcbf_office' => $seeded['wcbf_office'] ), $post ), 'an untouched form re-submitting the seeded value passes' );
FormCustomFields::save_values( $groups, $post, array( 'wcbf_office' => $seeded['wcbf_office'] ) );
$ok( 'Dallas, TX,Austin, TX' === get_post_meta( $post, 'wcbf_office', true ), 're-saving the seeded value keeps both choices' );

$rows = array_column( FormCustomFields::labelled_values( $groups, $post ), null, 'key' );
$ok( array( 'Dallas, TX', 'Austin, TX' ) === ( $rows['wcbf_office']['items'] ?? array() ), 'the answer reads back as two choices, not four fragments' );

// A stored value from before the fix (no comma inside a choice) still splits.
update_post_meta( $post, 'wcbf_equipment', 'Laptop,Monitor' );
$rows = array_column( FormCustomFields::labelled_values( $groups, $post ), null, 'key' );
$ok( array( 'Laptop', 'Monitor' ) === ( $rows['wcbf_equipment']['items'] ?? array() ), 'an existing comma-joined answer still reads as its choices' );

// Card 10516961478: Checkbox with choices is a tick-any list; without, one yes/no box.
$ok( 'Day' === get_post_meta( $post, 'wcbf_shift', true ) && array( 'Day' ) === ( $rows['wcbf_shift']['items'] ?? array() ), 'a Checkbox with choices stores and shows the ticked choice' );
$ok( '1' === get_post_meta( $post, 'wcbf_relocation', true ) && 'Yes' === ( $rows['wcbf_relocation']['value'] ?? '' ), 'a Checkbox without choices stores 1 and reads "Yes" on every surface' );

// Card 10516961764: a File link answer is shown as a link.
$has_api = method_exists( FormCustomFields::class, 'render_answer' ) && method_exists( FormCustomFields::class, 'is_link_type' );
$ok( $has_api && FormCustomFields::is_link_type( 'file' ) && FormCustomFields::is_link_type( 'video_url' ) && ! FormCustomFields::is_link_type( 'text' ), 'File link, Video and URL are the link types' );
ob_start();
$has_api && FormCustomFields::render_answer( $rows['wcbf_role_brief'] ?? array( 'type' => '', 'value' => '', 'items' => array() ) );
$html = (string) ob_get_clean();
$ok( str_contains( $html, 'href="https://northwind.example/briefs/platform-engineer.pdf"' ), 'a File link answer renders as a link' );
ob_start();
$has_api && FormCustomFields::render_answer( $rows['wcbf_office'] );
$html = (string) ob_get_clean();
$ok( 2 === substr_count( $html, 'class="wcb-tag"' ) && str_contains( $html, 'Dallas, TX' ), 'a multi-choice answer renders one tag per choice' );

wp_delete_post( $post, true );

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'All field type tests passed.' );
