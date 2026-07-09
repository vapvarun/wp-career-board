( function () {
	var el                = wp.element.createElement;
	var __                = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody         = wp.components.PanelBody;
	var TextControl       = wp.components.TextControl;
	var ToggleControl     = wp.components.ToggleControl;
	var SelectControl     = wp.components.SelectControl;

	var PER_PAGE_CHOICES = [ 3, 5, 10 ];

	// Locale-aware digit formatter for the option labels. The locale is read from
	// the admin document's <html lang> (emitted by language_attributes() from the
	// site/user locale), NOT left to Intl's default — a bare Intl.NumberFormat()
	// follows navigator.language, so an admin running WP in fa/ar with an English
	// browser would get ASCII numerals that mismatch the admin UI. Reading the
	// document locale renders non-ASCII numerals (ar, fa) with their own glyphs
	// while tracking the WP admin language rather than the browser.
	var wcbAdminLocale =
		( typeof document !== 'undefined' &&
			document.documentElement &&
			document.documentElement.lang ) ||
		undefined;
	var numberFormat = new Intl.NumberFormat( wcbAdminLocale );

	/**
	 * Build the "number of jobs" option list.
	 *
	 * The option labels are bare counts (3 / 5 / 10). The noun is already carried
	 * by the SelectControl's own "Number of jobs" label, so the options need no
	 * gettext string of their own — this deliberately keeps an editor-only,
	 * purely-numeric value out of the translation catalog and removes the
	 * unseeded `%s job`/`%s jobs` plural key that had no home in the .pot.
	 *
	 * @return {Array<{label: string, value: number}>} SelectControl options.
	 */
	function perPageOptions() {
		return PER_PAGE_CHOICES.map( function ( n ) {
			return {
				label: numberFormat.format( n ),
				value: n,
			};
		} );
	}

	wp.blocks.registerBlockType( 'wp-career-board/featured-jobs', {
		edit: function ( props ) {
			var attr    = props.attributes;
			var setAttr = props.setAttributes;

			// Title/description come from block.json, which core already translates
			// via its `textdomain` field. Never duplicate them as literals here.
			var meta = wp.blocks.getBlockType( 'wp-career-board/featured-jobs' ) || {};

			return [
				el( InspectorControls, { key: 'inspector' },
					el( PanelBody, { title: __( 'Settings', 'wp-career-board' ), initialOpen: true },
						el( SelectControl, {
							label:    __( 'Number of jobs', 'wp-career-board' ),
							value:    attr.perPage,
							options:  perPageOptions(),
							onChange: function ( val ) { setAttr( { perPage: parseInt( val, 10 ) } ); },
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
