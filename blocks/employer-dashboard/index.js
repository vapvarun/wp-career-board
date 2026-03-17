( function () {
	var el = wp.element.createElement;

	wp.blocks.registerBlockType( 'wp-career-board/employer-dashboard', {
		edit: function () {
			return el(
				'div',
				{ style: { padding: '12px 16px', background: '#f0f6fc', border: '1px dashed #93c5fd', borderRadius: '4px' } },
				el( 'strong', { style: { color: '#1e40af', display: 'block' } }, 'WCB: Employer Dashboard' ),
				el( 'span', { style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } }, 'Tabbed employer dashboard: My Jobs and Company Profile.' )
			);
		},
	} );
} )();
