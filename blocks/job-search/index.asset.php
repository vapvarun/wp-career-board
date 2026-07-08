<?php
defined( 'ABSPATH' ) || exit;
return array(
	// wp-blocks       -> registerBlockType(), getBlockType() (reads the server-bootstrapped,
	//                    already-translated block.json title/description).
	// wp-block-editor  -> wp.blockEditor.useBlockProps() (dereferenced at IIFE load time in index.js).
	'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element' ),
	'version'      => '1.0.1',
);
