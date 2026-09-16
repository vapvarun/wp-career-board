/**
 * Settings sidebar hash-based navigation.
 *
 * Switches visible .wcb-settings-section panels and updates the sidebar
 * active state. Supports #hash routing, ?tab= query-param fallback (for
 * server-side redirects after form saves), and preserves the hash across
 * form submissions.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */
(function () {
	var NAV     = '.wcb-settings-nav-item[data-section]';
	var SECTION = '.wcb-settings-section';
	var ACTIVE  = 'is-active';

	/**
	 * Activate a section by its slug.
	 *
	 * @param {string} id Section slug (e.g. "listings").
	 */
	function activate( id ) {
		document.querySelectorAll( NAV ).forEach( function ( el ) {
			el.classList.remove( ACTIVE );
		});
		document.querySelectorAll( SECTION ).forEach( function ( el ) {
			el.classList.remove( ACTIVE );
		});

		var nav = document.querySelector( NAV + '[data-section="' + id + '"]' );
		var sec = document.getElementById( 'section-' + id );

		if ( nav && sec ) {
			nav.classList.add( ACTIVE );
			sec.classList.add( ACTIVE );
		} else {
			var firstNav = document.querySelector( NAV );
			var firstSec = document.querySelector( SECTION );
			if ( firstNav ) {
				firstNav.classList.add( ACTIVE );
			}
			if ( firstSec ) {
				firstSec.classList.add( ACTIVE );
			}
		}

		if ( window.lucide ) {
			lucide.createIcons();
		}
	}

	/**
	 * Determine the initial section from the URL.
	 *
	 * Priority: #hash > ?tab= query param > first section.
	 *
	 * @return {string}
	 */
	function getInitialSection() {
		var hash = location.hash.replace( '#', '' );
		if ( hash ) {
			return hash;
		}

		var params = new URLSearchParams( location.search );
		var tab    = params.get( 'tab' );
		if ( tab ) {
			return tab;
		}

		return '';
	}

	/**
	 * Point the address bar at a section, keeping the hash and ?tab= in step.
	 *
	 * Writing only the hash left ?tab= on whatever section loaded the page, so
	 * the two halves of the URL disagreed. That is invisible until the query
	 * param is the half that gets read: a save redirects to ?tab=<section> with
	 * no hash, and a copied URL may lose the fragment. Either way the visitor
	 * lands on the section they were on BEFORE the one they clicked.
	 *
	 * @param {string} id Section slug.
	 */
	function syncUrl( id ) {
		if ( ! window.URL || ! history.replaceState ) {
			return;
		}
		var url = new URL( location.href );
		url.searchParams.set( 'tab', id );

		// The Settings API redirects to _wp_http_referer after options.php
		// writes, NOT to the address bar. Leaving it at the URL the page
		// loaded with is why saving used to dump the owner back on the panel
		// they started from: the address bar said Listings, the hidden field
		// still said Industries, and the hidden field is the one that wins.
		// Path + query only — a fragment is never sent to the server and
		// wp_safe_redirect() would carry it into the Location header.
		var referer = url.pathname + url.search;
		document.querySelectorAll( 'input[name="_wp_http_referer"]' ).forEach( function ( input ) {
			input.value = referer;
		});

		url.hash = id;
		history.replaceState( null, '', url.toString() );
	}

	// Click handler.
	document.querySelectorAll( NAV ).forEach( function ( item ) {
		item.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var section = this.getAttribute( 'data-section' );
			activate( section );
			syncUrl( section );
		});
	});

	// Preserve hash on form submit so the user returns to the same section.
	document.querySelectorAll( SECTION + ' form' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function () {
			var hash = location.hash;
			if ( ! hash ) {
				return;
			}
			// getAttribute('action'), never this.action: settings_fields()
			// emits <input type="hidden" name="action" value="update">, and a
			// named control shadows the form property of the same name — so
			// this.action is that INPUT ELEMENT, and calling .split() on it
			// threw "(this.action || '').split is not a function" on every
			// settings save. The attribute is the only reliable read here.
			var base = ( this.getAttribute( 'action' ) || '' ).split( '#' )[0];
			this.setAttribute( 'action', base + hash );
		});
	});

	// Activate from URL hash / ?tab= / default.
	activate( getInitialSection() );
})();
