/**
 * Term lists: put the term's Order into the Quick Edit field.
 *
 * Core's Quick Edit copies only the name and slug into its form. This runs
 * after core's handler on the same click (core listens on #the-list, this on
 * the document) and fills the Order field from the row's Order cell.
 */
document.addEventListener( 'click', function ( event ) {
	var link = event.target.closest( '#the-list .editinline' );
	if ( ! link ) { return; }

	var row   = link.closest( 'tr' );
	var cell  = row ? row.querySelector( 'td.column-wcb_order' ) : null;
	var input = document.querySelector( '#the-list tr.inline-edit-row input[name="wcb_order"]' );
	if ( ! cell || ! input ) { return; }

	var value   = cell.textContent.trim();
	input.value = /^\d+$/.test( value ) ? value : '';
} );
