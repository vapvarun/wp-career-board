( function () {
	var el                = wp.element.createElement;
	var __                = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody         = wp.components.PanelBody;
	var ToggleControl     = wp.components.ToggleControl;

	wp.blocks.registerBlockType( 'wp-career-board/job-stats', {
		edit: function ( props ) {
			var attr    = props.attributes;
			var setAttr = props.setAttributes;
			// Title/description come from block.json and are already translated by core.
			var meta    = wp.blocks.getBlockType( 'wp-career-board/job-stats' ) || {};

			return [
				el( InspectorControls, { key: 'inspector' },
					el( PanelBody, { title: __( 'Visible stats', 'wp-career-board' ), initialOpen: true },
						el( ToggleControl, { label: __( 'Show Jobs count', 'wp-career-board' ),       checked: attr.showJobs,       onChange: function ( v ) { setAttr( { showJobs: v } ); } } ),
						el( ToggleControl, { label: __( 'Show Companies count', 'wp-career-board' ),  checked: attr.showCompanies,  onChange: function ( v ) { setAttr( { showCompanies: v } ); } } ),
						el( ToggleControl, { label: __( 'Show Candidates count', 'wp-career-board' ), checked: attr.showCandidates, onChange: function ( v ) { setAttr( { showCandidates: v } ); } } )
					)
				),
				el( 'div', { key: 'preview', style: { padding: '12px 16px', background: '#f0f6fc', border: '1px dashed #93c5fd', borderRadius: '4px' } },
					el( 'strong', { style: { color: '#1e40af', display: 'block' } }, meta.title ),
					el( 'span', { style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } }, meta.description )
				),
			];
		},
	} );
} )();
