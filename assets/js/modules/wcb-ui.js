/**
 * WP Career Board — shared UI helpers for Interactivity API view modules.
 *
 * One implementation each, imported by Free and Pro blocks as `@wcb/ui`
 * (same pattern as `@wcb/fetch`), so a fix lands everywhere at once.
 *
 * @module @wcb/ui
 */

/**
 * Format an ISO (Y-m-d) date against the SITE locale, never the browser's.
 *
 * @param {string} iso       ISO date string.
 * @param {string} [siteTag] BCP 47 tag from PHP (state.locale), e.g. "de-DE".
 * @return {string} Localised date, or the raw ISO string when unparseable.
 */
export function formatSiteDate( iso, siteTag = '' ) {
	if ( ! iso ) {
		return '';
	}
	const d = new Date( iso + 'T00:00:00' );
	if ( isNaN( d.getTime() ) ) {
		return iso;
	}
	try {
		return new Intl.DateTimeFormat( siteTag || undefined, { year: 'numeric', month: 'long', day: 'numeric' } ).format( d );
	} catch ( _e ) {
		return iso;
	}
}

/**
 * Copy text to the clipboard. The Clipboard API only exists on secure
 * origins, so plain-http sites fall back to a hidden textarea.
 *
 * @param {string} text Text to copy.
 * @return {Promise<boolean>} Whether the copy succeeded.
 */
export async function copyText( text ) {
	try {
		if ( window.navigator.clipboard && window.isSecureContext ) {
			await window.navigator.clipboard.writeText( text );
			return true;
		}
	} catch ( _e ) {
		// Permission denied: try the fallback.
	}
	const area = document.createElement( 'textarea' );
	area.value = text;
	area.setAttribute( 'readonly', '' );
	area.style.position = 'fixed';
	area.style.opacity = '0';
	document.body.appendChild( area );
	area.select();
	let ok = false;
	try {
		ok = document.execCommand( 'copy' );
	} catch ( _e ) {
		ok = false;
	}
	area.remove();
	return ok;
}

/**
 * One tab router for the dashboards: the URL hash and this session's last tab
 * are written by a single setter, so a new tab cannot forget either.
 *
 * Pairs with DashboardTabs::print_guard(), which hides the panels on first
 * paint while a non-default tab is being restored; go() lifts that guard.
 *
 * @param {string[]} valid      Allowed tab slugs; the first is the default.
 * @param {string}   storageKey sessionStorage key for the last tab.
 * @return {{read: function(): ?string, initial: function(string): string, go: function(string): void}} Router.
 */
export function tabRouter( valid, storageKey ) {
	const read = () => {
		const raw = ( window.location.hash || '' ).replace( /^#/, '' );
		return valid.includes( raw ) ? raw : null;
	};
	return {
		read,
		// Hash wins (deep links, back/forward), then the session's last tab,
		// unless the server already chose a specific tab.
		initial( serverDefault ) {
			const fromHash = read();
			if ( fromHash ) {
				return fromHash;
			}
			if ( serverDefault !== valid[ 0 ] ) {
				return serverDefault;
			}
			let saved = null;
			try {
				saved = window.sessionStorage.getItem( storageKey );
			} catch ( _e ) {
				saved = null;
			}
			return valid.includes( saved ) ? saved : serverDefault;
		},
		go( tab ) {
			document.documentElement.removeAttribute( 'data-wcb-tab-pending' );
			if ( ! valid.includes( tab ) ) {
				return;
			}
			try {
				window.sessionStorage.setItem( storageKey, tab );
			} catch ( _e ) {
				// Storage blocked (private mode): the hash still carries the tab.
			}
			if ( window.location.hash !== '#' + tab ) {
				window.history.replaceState( null, '', window.location.pathname + window.location.search + '#' + tab );
			}
		},
	};
}
