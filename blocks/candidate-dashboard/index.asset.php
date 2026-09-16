<?php
defined( 'ABSPATH' ) || exit;
return array(
	// wp-block-editor is required: index.js dereferences
	// wp.blockEditor.useBlockProps. It resolved only because the editor
	// happens to load that handle anyway — a load-order change would have
	// broken the block (Basecamp 10074197007, item 1).
	'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element' ),
	'version'      => '1.0.0',
);
