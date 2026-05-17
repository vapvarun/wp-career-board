( function () {
	var el                = wp.element.createElement;
	var __                = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody         = wp.components.PanelBody;
	var TextControl       = wp.components.TextControl;
	var SelectControl     = wp.components.SelectControl;

	wp.blocks.registerBlockType( 'wp-career-board/similar-companies-card', {
		edit: function ( props ) {
			var attr    = props.attributes;
			var setAttr = props.setAttributes;

			return [
				el( InspectorControls, { key: 'inspector' },
					el( PanelBody, { title: __( 'Settings', 'wp-career-board' ), initialOpen: true },
						el( TextControl, {
							label:    __( 'Title', 'wp-career-board' ),
							value:    attr.title,
							onChange: function ( val ) { setAttr( { title: val } ); },
						} ),
						el( SelectControl, {
							label:    __( 'Number of companies', 'wp-career-board' ),
							value:    String( attr.count ),
							options:  [ { label: '3', value: '3' }, { label: '5', value: '5' }, { label: '10', value: '10' } ],
							onChange: function ( val ) { setAttr( { count: parseInt( val, 10 ) } ); },
						} ),
						el( TextControl, {
							label:    __( 'Company ID (blank = auto on company pages)', 'wp-career-board' ),
							help:     __( 'Leave blank when used in the Company Profile Sidebar. Set a company ID to anchor the block on a standalone page.', 'wp-career-board' ),
							value:    attr.companyId ? String( attr.companyId ) : '',
							onChange: function ( val ) { setAttr( { companyId: parseInt( val, 10 ) || 0 } ); },
						} )
					)
				),
				el( 'div', { key: 'preview', style: { padding: '12px 16px', background: '#f0f6fc', border: '1px dashed #93c5fd', borderRadius: '4px' } },
					el( 'strong', { style: { color: '#1e40af', display: 'block' } }, 'WCB: Similar Companies' ),
					el( 'span', { style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } },
						attr.companyId
							? __( 'Anchored to company ID ', 'wp-career-board' ) + attr.companyId
							: __( 'Auto-resolves on company-single pages.', 'wp-career-board' ) )
				),
			];
		},
	} );
} )();
