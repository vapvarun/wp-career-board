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
