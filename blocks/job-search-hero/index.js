( function () {
	var el                = wp.element.createElement;
	var __                = wp.i18n.__;
	var sprintf           = wp.i18n.sprintf;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody         = wp.components.PanelBody;
	var SelectControl     = wp.components.SelectControl;
	var TextControl       = wp.components.TextControl;
	var ToggleControl     = wp.components.ToggleControl;

	wp.blocks.registerBlockType( 'wp-career-board/job-search-hero', {
		edit: function ( props ) {
			var attr    = props.attributes;
			var setAttr = props.setAttributes;

			// Title/description live in block.json; core translates them via its
			// "textdomain" key. Read them back instead of duplicating the literals
			// here, which would add redundant (and drift-prone) POT entries.
			var meta = wp.blocks.getBlockType( 'wp-career-board/job-search-hero' ) || {};

			var layoutLabels = {
				horizontal: __( 'Horizontal', 'wp-career-board' ),
				vertical:   __( 'Vertical', 'wp-career-board' ),
			};
			var layoutLabel = layoutLabels[ attr.layout ] || layoutLabels.horizontal;

			return [
				el( InspectorControls, { key: 'inspector' },
					el( PanelBody, { title: __( 'Layout', 'wp-career-board' ), initialOpen: true },
						el( SelectControl, {
							label:    __( 'Layout', 'wp-career-board' ),
							value:    attr.layout,
							options:  [ { label: layoutLabels.horizontal, value: 'horizontal' }, { label: layoutLabels.vertical, value: 'vertical' } ],
							onChange: function ( val ) { setAttr( { layout: val } ); },
						} )
					),
					el( PanelBody, { title: __( 'Labels', 'wp-career-board' ), initialOpen: false },
						el( TextControl, { label: __( 'Search placeholder', 'wp-career-board' ),    value: attr.placeholder,  onChange: function ( v ) { setAttr( { placeholder: v } ); } } ),
						el( TextControl, { label: __( 'Button label', 'wp-career-board' ),          value: attr.buttonLabel,  onChange: function ( v ) { setAttr( { buttonLabel: v } ); } } )
					),
					el( PanelBody, { title: __( 'Filters', 'wp-career-board' ), initialOpen: true },
						el( ToggleControl, { label: __( 'Show category filter', 'wp-career-board' ), checked: attr.showCategoryFilter, onChange: function ( v ) { setAttr( { showCategoryFilter: v } ); } } ),
						el( ToggleControl, { label: __( 'Show location filter', 'wp-career-board' ), checked: attr.showLocationFilter, onChange: function ( v ) { setAttr( { showLocationFilter: v } ); } } ),
						el( ToggleControl, { label: __( 'Show job type filter', 'wp-career-board' ),  checked: attr.showJobTypeFilter,  onChange: function ( v ) { setAttr( { showJobTypeFilter: v } ); } } )
					)
				),
				el( 'div', { key: 'preview', style: { padding: '12px 16px', background: '#f0f6fc', border: '1px dashed #93c5fd', borderRadius: '4px' } },
					// Block title comes from block.json, which core already translates
					// via its "textdomain" key — read it back instead of composing a
					// "Title (Layout)" sentence. A composite reused the generic
					// "%1$s (%2$s)" msgid (which collides with an unrelated currency
					// string) and glued the layout onto the title in fixed English
					// word order. The title and the layout are shown as separate,
					// standalone translated pieces instead.
					el( 'strong', { style: { color: '#1e40af', display: 'block' } }, meta.title || '' ),
					el(
						'span',
						{ style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } },
						sprintf(
							/* translators: %s: selected layout name, e.g. "Horizontal". */
							__( 'Layout: %s', 'wp-career-board' ),
							layoutLabel
						)
					),
					el( 'span', { style: { color: '#64748b', fontSize: '12px', display: 'block' } }, meta.description || '' )
				),
			];
		},
	} );
} )();
