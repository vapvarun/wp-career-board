( function () {
	var el                = wp.element.createElement;
	var __                = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody         = wp.components.PanelBody;
	var TextControl       = wp.components.TextControl;
	var ToggleControl     = wp.components.ToggleControl;
	var SelectControl     = wp.components.SelectControl;

	// Digit-shape the count choices against the SITE/admin locale, never the
	// browser locale. wp-admin emits <html lang="de-DE"> via language_attributes(),
	// which mirrors \WCB\Core\SalaryFormat::locale() — both derive from
	// get_user_locale(), so this reads the canonical site locale rather than
	// reinventing detection. When the attribute is absent we render plain ASCII
	// digits instead of letting Intl.NumberFormat( undefined ) fall back to the
	// visitor's BROWSER locale.
	var formatNumber = ( function () {
		var localeTag = ( document.documentElement.lang || '' ).trim();
		if ( ! localeTag ) {
			return function ( n ) { return String( n ); };
		}
		try {
			var nf = new Intl.NumberFormat( localeTag );
			return function ( n ) { return nf.format( n ); };
		} catch ( e ) {
			return function ( n ) { return String( n ); };
		}
	} )();

	// Values stay ASCII (machine-facing, persisted in post content); only labels are localised.
	var COUNT_CHOICES = [ 3, 5, 10 ];

	wp.blocks.registerBlockType( 'wp-career-board/recent-jobs', {
		edit: function ( props ) {
			var attr    = props.attributes;
			var setAttr = props.setAttributes;
			// Read the translated block metadata; core translates block.json title/description via its textdomain.
			var meta    = wp.blocks.getBlockType( 'wp-career-board/recent-jobs' ) || {};

			return [
				el( InspectorControls, { key: 'inspector' },
					el( PanelBody, { title: __( 'Settings', 'wp-career-board' ), initialOpen: true },
						el( SelectControl, {
							label:    __( 'Number of jobs', 'wp-career-board' ),
							value:    String( attr.count ),
							options:  COUNT_CHOICES.map( function ( n ) {
								return { label: formatNumber( n ), value: String( n ) };
							} ),
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
					el( 'strong', { style: { color: '#1e40af', display: 'block' } }, meta.title ),
					el( 'span', { style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } },
						meta.description )
				),
			];
		},
	} );
} )();
