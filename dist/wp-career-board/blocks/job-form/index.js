( function () {
	var el = wp.element.createElement;
	var useBlockProps = wp.blockEditor.useBlockProps;

	wp.blocks.registerBlockType( 'wp-career-board/job-form', {
		edit: function () {
			return el(
				'div',
				useBlockProps( { style: { padding: '12px 16px', background: '#f0f6fc', border: '1px dashed #93c5fd', borderRadius: '4px' } } ),
				el( 'strong', { style: { color: '#1e40af', display: 'block' } }, 'WCB: Job Form' ),
				el( 'span', { style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } }, 'Multi-step form for employers to post new jobs.' )
			);
		},
	} );
} )();
