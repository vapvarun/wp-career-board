( function () {
	var el = wp.element.createElement;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var BLOCK_NAME = 'wp-career-board/candidate-dashboard';

	wp.blocks.registerBlockType( BLOCK_NAME, {
		edit: function () {
			// Title + description come from block.json, which core already
			// translates through the block's `textdomain`. Reading them back
			// keeps the editor placeholder localized without duplicating the
			// literals (and their POT entries) here.
			var meta = wp.blocks.getBlockType( BLOCK_NAME ) || {};

			return el(
				'div',
				useBlockProps( { style: { padding: '12px 16px', background: '#f0f6fc', border: '1px dashed #93c5fd', borderRadius: '4px' } } ),
				el( 'strong', { style: { color: '#1e40af', display: 'block' } }, meta.title ),
				el( 'span', { style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } }, meta.description )
			);
		},
	} );
} )();
