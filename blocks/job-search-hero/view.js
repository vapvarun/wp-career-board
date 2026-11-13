/**
 * WP Career Board — job-search-hero: keep the filters already chosen.
 *
 * The hero is a plain GET form, so a submit sent only its own four fields and
 * dropped every other filter in the URL (experience, tag, remote, sort ...).
 * Its selects are also drawn by the server, so they go stale once the filters
 * block changes the URL. On submit this builds the URL from the current one and
 * overrides only the fields the visitor changed here. Without JS the form still
 * submits as before.
 *
 * @package WP_Career_Board
 */

// Form field name -> the canonical URL name the listing reads and writes.
const FIELDS = {
	wcb_search: 'search',
	category: 'category',
	wcb_location: 'location',
	wcb_job_type: 'type',
};

// Every spelling the listing accepts for each canonical name (JobSearch::ALIASES).
const ALIASES = {
	search: [ 'wcb_search', 's' ],
	category: [ 'wcb_category' ],
	location: [ 'wcb_location' ],
	type: [ 'wcb_job_type' ],
};

const isUntouched = ( field ) =>
	field.tagName === 'SELECT'
		? Array.from( field.options ).every( ( o ) => o.selected === o.defaultSelected )
		: field.value === field.defaultValue;

document.addEventListener( 'submit', ( event ) => {
	const form = event.target;
	if ( ! ( form instanceof HTMLFormElement ) || ! form.classList.contains( 'wcb-search-hero__form' ) ) {
		return;
	}

	const target = new URL( form.action, window.location.href );
	const here = new URL( window.location.href );
	// Carry the current filters only when the hero submits to the page already open.
	const sameListing = target.origin === here.origin && target.pathname === here.pathname;
	const params = sameListing ? new URLSearchParams( here.search ) : new URLSearchParams();

	for ( const [ name, canonical ] of Object.entries( FIELDS ) ) {
		const field = form.elements.namedItem( name );
		if ( ! field || ( sameListing && isUntouched( field ) ) ) {
			continue;
		}
		params.delete( canonical );
		ALIASES[ canonical ].forEach( ( alias ) => params.delete( alias ) );
		if ( field.value ) {
			params.set( canonical, field.value );
		}
	}
	// A new search starts on the first page.
	[ 'paged' ].forEach( ( key ) => params.delete( key ) );

	event.preventDefault();
	target.search = params.toString();
	window.location.assign( target.toString() );
} );
