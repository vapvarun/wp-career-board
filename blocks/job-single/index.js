( function () {
	var el = wp.element.createElement;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var BLOCK_NAME = 'wp-career-board/job-single';

	wp.blocks.registerBlockType( BLOCK_NAME, {
		edit: function () {
			// Read the title/description from the registered block metadata.
			// block.json declares `textdomain`, so core already translates them —
			// hardcoding the English here would duplicate those strings and add
			// redundant POT entries that drift from block.json.
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
