( function () {
	var el = wp.element.createElement;
	var useBlockProps = wp.blockEditor.useBlockProps;

	wp.blocks.registerBlockType( 'wp-career-board/job-form', {
		edit: function () {
			// Title and description come from block.json, which core already
			// translates via its `textdomain` field. Do NOT re-declare them
			// here (or wrap them in __()) — that would duplicate the strings
			// and add redundant POT entries.
			var meta = wp.blocks.getBlockType( 'wp-career-board/job-form' ) || {};

			return el(
				'div',
				useBlockProps( { style: { padding: '12px 16px', background: '#f0f6fc', border: '1px dashed #93c5fd', borderRadius: '4px' } } ),
				el( 'strong', { style: { color: '#1e40af', display: 'block' } }, meta.title ),
				el( 'span', { style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } }, meta.description )
			);
		},
	} );
} )();
