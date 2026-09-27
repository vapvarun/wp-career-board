/* global wp, wcbBrand */
/**
 * Settings > Brand: pick the logo from the media library.
 *
 * @package WP_Career_Board
 */
( function () {
	'use strict';

	var root = document.querySelector( '[data-wcb-brand-logo]' );
	if ( ! root || ! window.wp || ! wp.media ) {
		return;
	}

	var idField = root.querySelector( '[data-wcb-brand-logo-id]' );
	var preview = root.querySelector( '[data-wcb-brand-logo-preview]' );
	var remove  = root.querySelector( '[data-wcb-brand-logo-remove]' );
	var frame;

	root.querySelector( '[data-wcb-brand-logo-choose]' ).addEventListener( 'click', function () {
		if ( ! frame ) {
			frame = wp.media( { title: wcbBrand.i18n.selectLogo, multiple: false, library: { type: 'image' } } );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				idField.value  = att.id;
				preview.src    = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
				preview.hidden = false;
				remove.hidden  = false;
			} );
		}
		frame.open();
	} );

	remove.addEventListener( 'click', function () {
		idField.value  = '0';
		preview.hidden = true;
		remove.hidden  = true;
	} );
}() );
