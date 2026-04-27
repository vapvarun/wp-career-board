( function () {
	var el = wp.element.createElement;
	var useBlockProps = wp.blockEditor.useBlockProps;

	wp.blocks.registerBlockType( 'wp-career-board/job-form-simple', {
		edit: function () {
			return el(
				'div',
				useBlockProps( { style: { padding: '12px 16px', background: '#f0fdf4', border: '1px dashed #86efac', borderRadius: '4px' } } ),
				el( 'strong', { style: { color: '#166534', display: 'block' } }, 'WCB: Job Form (Single-Page)' ),
				el( 'span', { style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } }, 'All fields on one page — for sidebars, modals, partner pages, single-page sites.' )
			);
		},
	} );
} )();
