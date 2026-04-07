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

	// Click handler.
	document.querySelectorAll( NAV ).forEach( function ( item ) {
		item.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var section = this.getAttribute( 'data-section' );
			activate( section );
			history.replaceState( null, '', '#' + section );
		});
	});

	// Preserve hash on form submit so the user returns to the same section.
	document.querySelectorAll( SECTION + ' form' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function () {
			var hash = location.hash;
			if ( hash ) {
				var base = ( this.action || '' ).split( '#' )[0];
				this.action = base + hash;
			}
		});
	});

	// Activate from URL hash / ?tab= / default.
	activate( getInitialSection() );
})();
