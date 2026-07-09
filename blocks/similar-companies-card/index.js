( function () {
	var el                = wp.element.createElement;
	var __                = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody         = wp.components.PanelBody;
	var TextControl       = wp.components.TextControl;
	var SelectControl     = wp.components.SelectControl;

	// Count options are shown to a human, so the digits must render in the
	// site's locale (ar_AR wants "٣", not "3"). The stored `value` stays the
	// ASCII numeral - it is machine-facing and feeds parseInt().
	var COUNT_CHOICES = [ 3, 5, 10 ];

	// The admin <html lang> attribute is set server-side by WordPress
	// (language_attributes()) to the current admin locale, so it is the correct
	// locale source for editor chrome. Fall back to a *defined* locale ( 'en' ),
	// never `undefined`: passing undefined makes Intl.NumberFormat use the
	// browser's locale instead of the site's.
	function formatCount( n ) {
		try {
			var locale = document.documentElement.lang || 'en';
			return new Intl.NumberFormat( locale ).format( n );
		} catch ( e ) {
			return String( n );
		}
	}

	var countOptions = COUNT_CHOICES.map( function ( n ) {
		return { label: formatCount( n ), value: String( n ) };
	} );

	wp.blocks.registerBlockType( 'wp-career-board/similar-companies-card', {
		edit: function ( props ) {
			var attr    = props.attributes;
			var setAttr = props.setAttributes;

			// Block title/description come from block.json and are translated by
			// core via the block's textdomain - never duplicate them here.
			var meta = wp.blocks.getBlockType( 'wp-career-board/similar-companies-card' ) || {};

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
							options:  countOptions,
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
					el( 'strong', { style: { color: '#1e40af', display: 'block' } }, meta.title || '' ),
					el( 'span', { style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } },
						attr.companyId
							// The label is a translated, catalog-registered msgid (note
							// the trailing space, which the translations carry); the ID is
							// appended raw. A post ID is a machine-facing identifier the
							// admin retypes, not a quantity, so it is NOT run through
							// formatCount()/Intl.
							? wp.i18n.sprintf(
								/* translators: %s: company ID the block is anchored to. */
								__( 'Anchored to company ID %s', 'wp-career-board' ),
								String( attr.companyId )
						  )
							: __( 'Auto-resolves on company-single pages.', 'wp-career-board' ) )
				),
			];
		},
	} );
} )();
