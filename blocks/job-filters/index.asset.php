<?php
defined( 'ABSPATH' ) || exit;
return array(
	// wp-blocks      -> registerBlockType(), getBlockType() (reads the server-bootstrapped,
	//                   already-translated block.json title/description).
	// wp-block-editor -> useBlockProps().
	'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element' ),
	'version'      => '1.0.1',
);
