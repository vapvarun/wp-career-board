( function () {
	var el = wp.element.createElement;
	var useBlockProps = wp.blockEditor.useBlockProps;

	wp.blocks.registerBlockType( 'wp-career-board/company-archive', {
		edit: function () {
			// Title + description live in block.json and are already translated
			// by core via the block's `textdomain`. Read them back off the
			// registered block type instead of duplicating the literals here —
			// duplicates would add redundant POT entries and drift over time.
			var meta = wp.blocks.getBlockType( 'wp-career-board/company-archive' ) || {};

			return el(
				'div',
				useBlockProps( { style: { padding: '12px 16px', background: '#f0f6fc', border: '1px dashed #93c5fd', borderRadius: '4px' } } ),
				el( 'strong', { style: { color: '#1e40af', display: 'block' } }, meta.title ),
				el( 'span', { style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } }, meta.description )
			);
		},
	} );
} )();
