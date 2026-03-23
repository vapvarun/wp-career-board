( function () {
	var el                = wp.element.createElement;
	var __                = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody         = wp.components.PanelBody;
	var TextControl       = wp.components.TextControl;
	var ToggleControl     = wp.components.ToggleControl;
	var SelectControl     = wp.components.SelectControl;

	wp.blocks.registerBlockType( 'wp-career-board/recent-jobs', {
		edit: function ( props ) {
			var attr    = props.attributes;
			var setAttr = props.setAttributes;

			return [
				el( InspectorControls, { key: 'inspector' },
					el( PanelBody, { title: __( 'Settings', 'wp-career-board' ), initialOpen: true },
						el( SelectControl, {
							label:    __( 'Number of jobs', 'wp-career-board' ),
							value:    String( attr.count ),
							options:  [ { label: '3', value: '3' }, { label: '5', value: '5' }, { label: '10', value: '10' } ],
							onChange: function ( val ) { setAttr( { count: parseInt( val, 10 ) } ); },
						} ),
						el( TextControl, {
							label:    __( 'Section title', 'wp-career-board' ),
							value:    attr.title,
							onChange: function ( val ) { setAttr( { title: val } ); },
						} ),
						el( ToggleControl, {
							label:    __( 'Show "View all" link', 'wp-career-board' ),
							checked:  attr.showViewAll,
							onChange: function ( val ) { setAttr( { showViewAll: val } ); },
						} ),
						attr.showViewAll && el( TextControl, {
							label:    __( '"View all" URL (leave blank to auto-detect)', 'wp-career-board' ),
							value:    attr.viewAllUrl,
							onChange: function ( val ) { setAttr( { viewAllUrl: val } ); },
						} )
					)
				),
				el( 'div', { key: 'preview', style: { padding: '12px 16px', background: '#f0f6fc', border: '1px dashed #93c5fd', borderRadius: '4px' } },
					el( 'strong', { style: { color: '#1e40af', display: 'block' } }, 'WCB: Recent Jobs' ),
					el( 'span', { style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } },
						'Static sidebar widget. Configure in inspector →' )
				),
			];
		},
	} );
} )();
