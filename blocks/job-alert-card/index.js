( function () {
	var el                = wp.element.createElement;
	var __                = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody         = wp.components.PanelBody;
	var TextControl       = wp.components.TextControl;
	var TextareaControl   = wp.components.TextareaControl;

	wp.blocks.registerBlockType( 'wp-career-board/job-alert-card', {
		edit: function ( props ) {
			var attr    = props.attributes;
			var setAttr = props.setAttributes;

			return [
				el( InspectorControls, { key: 'inspector' },
					el( PanelBody, { title: __( 'Card content', 'wp-career-board' ), initialOpen: true },
						el( TextControl, {
							label:    __( 'Title', 'wp-career-board' ),
							value:    attr.title,
							onChange: function ( val ) { setAttr( { title: val } ); },
						} ),
						el( TextareaControl, {
							label:    __( 'Body', 'wp-career-board' ),
							value:    attr.body,
							onChange: function ( val ) { setAttr( { body: val } ); },
						} ),
						el( TextControl, {
							label:    __( 'Button text', 'wp-career-board' ),
							value:    attr.cta,
							onChange: function ( val ) { setAttr( { cta: val } ); },
						} ),
						el( TextControl, {
							label:    __( 'Button URL (blank = candidate dashboard alerts tab)', 'wp-career-board' ),
							value:    attr.url,
							onChange: function ( val ) { setAttr( { url: val } ); },
						} )
					)
				),
				el( 'div', { key: 'preview', style: { padding: '12px 16px', background: '#f0f6fc', border: '1px dashed #93c5fd', borderRadius: '4px' } },
					el( 'strong', { style: { color: '#1e40af', display: 'block' } }, 'WCB: Job Alerts CTA' ),
					el( 'span', { style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } },
						'Sidebar CTA card. Configure copy in the inspector.' )
				),
			];
		},
	} );
} )();
