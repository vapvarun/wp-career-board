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

/**
 * Tell the member a request failed. One toast for every block, so a failed
 * save, remove or status change never passes silently.
 *
 * @param {string} message Already-translated text (from the block's state.i18n).
 */
export function showError( message ) {
	let toast = document.querySelector( '.wcb-toast' );
	if ( ! toast ) {
		toast = document.createElement( 'div' );
		toast.className = 'wcb-toast';
		toast.setAttribute( 'role', 'alert' );
		document.body.appendChild( toast );
	}
	toast.textContent = message;
	toast.hidden = false;
	window.clearTimeout( toast.wcbTimer );
	toast.wcbTimer = window.setTimeout( () => {
		toast.hidden = true;
	}, 5000 );
}

/**
 * "5 minutes ago" for the last week, then a medium date, in the SITE locale.
 *
 * @param {string} siteTag  BCP 47 tag from PHP (state.locale).
 * @param {string} mysqlUtc UTC datetime as stored (current_time( 'mysql', true )).
 * @return {string} Relative or absolute time, '' when unparsable.
 */
export function relativeTime( siteTag, mysqlUtc ) {
	if ( ! mysqlUtc ) {
		return '';
	}
	const then = new Date( mysqlUtc.replace( ' ', 'T' ) + 'Z' );
	if ( Number.isNaN( then.getTime() ) ) {
		return '';
	}
	const locale  = siteTag || 'en-US';
	const seconds = ( Date.now() - then.getTime() ) / 1000;
	if ( seconds < 7 * 86400 ) {
		// Each step divides into the NEXT unit (60 seconds -> minutes ...); the
		// copies this replaced labelled the value with the unit it had just
		// divided away, so 5 minutes read as "5 seconds ago".
		const units = [ [ 60, 'minute' ], [ 60, 'hour' ], [ 24, 'day' ] ];
		let value = seconds;
		let unit  = 'second';
		for ( const [ size, name ] of units ) {
			if ( Math.abs( value ) < size ) {
				break;
			}
			value /= size;
			unit   = name;
		}
		try {
			return new Intl.RelativeTimeFormat( locale, { numeric: 'auto' } ).format( -Math.round( value ), unit );
		} catch ( _e ) {
			return new Intl.RelativeTimeFormat( 'en-US', { numeric: 'auto' } ).format( -Math.round( value ), unit );
		}
	}
	try {
		return new Intl.DateTimeFormat( locale, { dateStyle: 'medium' } ).format( then );
	} catch ( _e ) {
		return new Intl.DateTimeFormat( 'en-US', { dateStyle: 'medium' } ).format( then );
	}
}

/**
 * Ticked state of a nested category list, shared by the category picker and
 * the job listings sidebar so both tick the same way.
 *
 * A ticked parent ticks every category under it, and a parent stays ticked
 * only while all of its children are, so unticking one child leaves the
 * parent partly ticked and unticking the last one unticks it. The URL keeps
 * the short form: a ticked parent stands for its children (the server
 * includes them).
 *
 * @param {{slug: string, parent: string}[]} items Categories in tree order; parent is '' at the top.
 * @return {{expand: function(string[]): Set<string>, toggle: function(Set<string>, string, boolean): Set<string>, compress: function(Set<string>): string[], mixed: function(Set<string>, string): boolean}} Tree helpers.
 */
export function categoryTree( items ) {
	const parentOf = new Map( items.map( ( item ) => [ item.slug, item.parent || '' ] ) );
	const children = new Map();
	items.forEach( ( item ) => {
		if ( item.parent ) {
			children.set( item.parent, [ ...( children.get( item.parent ) || [] ), item.slug ] );
		}
	} );
	const descendants = ( slug ) => ( children.get( slug ) || [] ).flatMap( ( child ) => [ child, ...descendants( child ) ] );
	const normalise   = ( set ) => {
		// Deepest first, so a parent sees its children's final state.
		[ ...items ].reverse().forEach( ( { slug } ) => {
			const kids = children.get( slug );
			if ( kids && kids.every( ( kid ) => set.has( kid ) ) ) {
				set.add( slug );
			} else if ( kids ) {
				set.delete( slug );
			}
		} );
		return set;
	};
	return {
		expand( slugs ) {
			return normalise( new Set( slugs.flatMap( ( slug ) => [ slug, ...descendants( slug ) ] ) ) );
		},
		toggle( set, slug, on ) {
			const next = new Set( set );
			[ slug, ...descendants( slug ) ].forEach( ( s ) => next[ on ? 'add' : 'delete' ]( s ) );
			return normalise( next );
		},
		compress( set ) {
			return [ ...set ].filter( ( slug ) => ! set.has( parentOf.get( slug ) ) );
		},
		mixed( set, slug ) {
			return ! set.has( slug ) && descendants( slug ).some( ( s ) => set.has( s ) );
		},
	};
}

/**
 * Narrow a long checkbox list to the labels containing what was typed.
 *
 * @param {string}      needle Typed text.
 * @param {HTMLElement} list   The list whose <li> rows to hide or show.
 */
export function filterList( needle, list ) {
	const term = needle.trim().toLowerCase();
	let shown  = 0;
	list.querySelectorAll( 'li' ).forEach( ( li ) => {
		li.hidden = '' !== term && ! li.textContent.toLowerCase().includes( term );
		shown    += li.hidden ? 0 : 1;
	} );
	// While searching, rows show their parent ("Management in Retail").
	list.classList.toggle( 'is-searching', '' !== term );
	const empty = list.parentElement.querySelector( ':scope > .wcb-filter-empty' );
	if ( empty ) {
		empty.textContent = shown ? '' : empty.dataset.message;
	}
}
