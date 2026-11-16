/**
 * WP Career Board — shared search-hero submit, imported as '@wcb/hero-search'.
 *
 * A hero is a plain GET form, so a submit sent only its own fields and dropped
 * every other filter already in the URL. Its controls are also drawn by the
 * server, so they go stale once the filter controls change the URL. On submit
 * this builds the URL from the current one and overrides only the fields the
 * visitor changed in the hero. Without JS the form still submits as before.
 *
 * @package WP_Career_Board
 */

// A hidden input's value and defaultValue move together, and a select whose
// "All" option is drawn without a selected attribute reads as changed. So each
// value as drawn is kept and compared on submit. Selects are kept at load; the
// category picker's hidden input only when the visitor first touches the form,
// because the picker may set it while the page starts up.
const drawn = new WeakMap();
const keep = ( form, selector ) => form.querySelectorAll( selector ).forEach( ( f ) => drawn.has( f ) || drawn.set( f, f.value ) );
const FORM = 'form.wcb-search-hero__form';
document.querySelectorAll( FORM ).forEach( ( form ) => keep( form, 'select' ) );
document.addEventListener( 'focusin', ( event ) => {
	const form = event.target instanceof Element ? event.target.closest( FORM ) : null;
	if ( form ) {
		keep( form, 'input[type="hidden"]' );
	}
} );

const isUntouched = ( field ) => {
	if ( field.type === 'hidden' || field.tagName === 'SELECT' ) {
		return ! drawn.has( field ) || drawn.get( field ) === field.value;
	}
	if ( field.type === 'checkbox' ) {
		return field.checked === field.defaultChecked;
	}
	return field.value === field.defaultValue;
};

/**
 * @param {string}   scope  Selector of the hero wrapper this call owns.
 * @param {Object[]} fields One per control: { name, param, aliases?: string[] }.
 *                          `param` is the name the listing reads and writes.
 */
export function bindHeroSearch( scope, fields ) {
	document.addEventListener( 'submit', ( event ) => {
		const form = event.target;
		if ( ! ( form instanceof HTMLFormElement ) || ! form.classList.contains( 'wcb-search-hero__form' ) || ! form.closest( scope ) ) {
			return;
		}

		const target = new URL( form.action, window.location.href );
		const here = new URL( window.location.href );
		// Carry the current filters only when the hero submits to the page already open.
		const trim = ( path ) => path.replace( /\/page\/\d+\/?$/, '/' );
		const sameListing = target.origin === here.origin && trim( target.pathname ) === trim( here.pathname );
		const params = sameListing ? new URLSearchParams( here.search ) : new URLSearchParams();

		for ( const { name, param, aliases = [] } of fields ) {
			const field = form.elements.namedItem( name );
			if ( ! field || ( sameListing && isUntouched( field ) ) ) {
				continue;
			}
			[ param, ...aliases ].forEach( ( key ) => params.delete( key ) );
			const value = field.type === 'checkbox' ? ( field.checked ? field.value : '' ) : field.value;
			if ( value ) {
				params.set( param, value );
			}
		}
		// A new search starts on the first page.
		params.delete( 'paged' );

		event.preventDefault();
		target.search = params.toString();
		window.location.assign( target.toString() );
	} );
}
