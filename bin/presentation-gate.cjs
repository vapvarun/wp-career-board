#!/usr/bin/env node
/**
 * Presentation gate: opens every frontend view in docs/qa/presentation-views.json
 * at each width x colour scheme and fails on layout defects code gates can't see.
 *
 *   overflow  page or block wider than the viewport
 *   edges     more than 2 left edges for headings/labels/fields/help in one panel
 *   midword   a title or name whose longest word is wider than its box
 *   clipped   a control or heading cut by an overflow:hidden/clip ancestor
 *   contrast  text or placeholder under 4.5:1 (3:1 for large text)
 *   wpadmin   wp-admin classes (.form-table, .wp-list-table ...) in frontend markup
 *
 * Usage:
 *   node bin/presentation-gate.cjs               run against this install's site
 *   node bin/presentation-gate.cjs --self-test   prove every check fails on tests/presentation/planted.html
 *   node bin/presentation-gate.cjs --view=emp-settings --width=390 --scheme=dark
 *
 * Env: WCB_PRESENTATION_URL (default: `wp option get home` of this install), WCB_PRESENTATION_OUT (screenshot dir;
 * default <site>/qa-artifacts/presentation-gate, outside the web root).
 * Exit: 0 clean, 1 findings, 2 setup error.
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );

const ROOT = path.resolve( __dirname, '..' );

/**
 * Runs inside the page. Returns findings for every visible block of ours.
 * Kept free of closures so page.evaluate can serialise it.
 */
function inPageChecks() {
	const SCOPE = '[class*="wp-block-wp-career-board"], [class*="wp-block-wcb-"], [data-wcb-gate-scope]';
	const out = [];
	const add = ( check, el, detail ) => {
		const cls = ( el.className && el.className.baseVal === undefined ? String( el.className ) : '' ).trim().split( /\s+/ ).slice( 0, 2 ).join( '.' );
		out.push( { check, el: el.tagName.toLowerCase() + ( cls ? '.' + cls : '' ), text: ( el.innerText || el.placeholder || '' ).trim().slice( 0, 40 ), detail } );
	};
	const visible = ( el ) => {
		const r = el.getBoundingClientRect();
		if ( r.width < 1 || r.height < 1 ) {
			return false;
		}
		const cs = getComputedStyle( el );
		return 'hidden' !== cs.visibility && '0' !== cs.opacity && ! el.closest( '[hidden], [aria-hidden="true"], details:not([open]) > :not(summary)' );
	};
	const roots = [ ...document.querySelectorAll( SCOPE ) ].filter( ( r ) => visible( r ) && ! r.parentElement.closest( SCOPE ) );

	// overflow
	if ( document.documentElement.scrollWidth > window.innerWidth + 1 ) {
		add( 'overflow', document.documentElement, `page ${ document.documentElement.scrollWidth } > ${ window.innerWidth }` );
	}
	roots.forEach( ( r ) => {
		const b = r.getBoundingClientRect();
		if ( b.right > window.innerWidth + 1 || b.left < -1 ) {
			add( 'overflow', r, `block spans ${ Math.round( b.left ) }..${ Math.round( b.right ) } of ${ window.innerWidth }` );
		}
	} );

	// colour helpers
	const parse = ( c ) => {
		const m = ( c || '' ).match( /[\d.]+/g );
		return m ? { r: +m[ 0 ], g: +m[ 1 ], b: +m[ 2 ], a: undefined === m[ 3 ] ? 1 : +m[ 3 ] } : null;
	};
	const lum = ( c ) => [ c.r, c.g, c.b ].map( ( v ) => {
		v /= 255;
		return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
	} ).reduce( ( s, v, i ) => s + v * [ 0.2126, 0.7152, 0.0722 ][ i ], 0 );
	const ratio = ( a, b ) => {
		const x = lum( a ), y = lum( b );
		return ( Math.max( x, y ) + 0.05 ) / ( Math.min( x, y ) + 0.05 );
	};
	// Effective background; null when an image/gradient sits behind (can't judge).
	const bgOf = ( el ) => {
		for ( let n = el; n; n = n.parentElement ) {
			const cs = getComputedStyle( n );
			if ( 'none' !== cs.backgroundImage ) {
				return null;
			}
			const c = parse( cs.backgroundColor );
			if ( c && c.a > 0.5 ) {
				return c;
			}
		}
		return parse( getComputedStyle( document.body ).backgroundColor ) || { r: 255, g: 255, b: 255, a: 1 };
	};
	const blend = ( fg, bg ) => ( { r: fg.r * fg.a + bg.r * ( 1 - fg.a ), g: fg.g * fg.a + bg.g * ( 1 - fg.a ), b: fg.b * fg.a + bg.b * ( 1 - fg.a ), a: 1 } );

	roots.forEach( ( root ) => {
		// edges: per panel (dashboard tab panel, else the block)
		const tabPanels = [ ...root.querySelectorAll( '.wcb-panel' ) ].filter( visible );
		const panels = tabPanels.length ? tabPanels : [ root ];
		panels.forEach( ( p ) => {
			[ ...p.querySelectorAll( 'form, fieldset, .wcb-form-section, .wcb-settings-section' ) ].concat( [ p ] ).forEach( ( group ) => {
				const items = [ ...group.querySelectorAll( 'label, legend, .wcb-form-help, .wcb-field-help, .description, input:not([type=checkbox]):not([type=radio]):not([type=hidden]), select, textarea' ) ]
					.filter( ( e ) => visible( e ) && e.closest( 'form, fieldset, .wcb-form-section, .wcb-settings-section, .wcb-panel' ) === ( group === p ? e.closest( '.wcb-panel' ) || p : group ) );
				if ( items.length < 3 ) {
					return;
				}
				const gRect = group.getBoundingClientRect();
				// Fields side by side in a grid row are columns, not stray edges: only count
				// edges of items that start a row (no item to their left on the same line).
				const starts = items.filter( ( e ) => {
					const r = e.getBoundingClientRect();
					return ! items.some( ( o ) => {
						const q = o.getBoundingClientRect();
						return o !== e && q.right <= r.left + 1 && q.bottom > r.top + 1 && q.top < r.bottom - 1;
					} );
				} ).map( ( e ) => Math.round( e.getBoundingClientRect().left - gRect.left ) );
				const edges = [ ...new Set( starts.map( ( x ) => Math.round( x / 4 ) * 4 ) ) ];
				if ( edges.length > 2 ) {
					add( 'edges', group, `${ edges.length } left edges: ${ edges.sort( ( a, b ) => a - b ).join( ',' ) }` );
				}
			} );
		} );

		// midword: titles and names whose longest word doesn't fit their box
		const ctx = document.createElement( 'canvas' ).getContext( '2d' );
		root.querySelectorAll( 'h1, h2, h3, h4, [class*="-title"], [class*="__title"], [class*="-name"], [class*="__name"]' ).forEach( ( el ) => {
			if ( ! visible( el ) || el.children.length > 2 ) {
				return;
			}
			const text = ( el.innerText || '' ).trim();
			if ( ! text ) {
				return;
			}
			const cs = getComputedStyle( el );
			if ( 'nowrap' === cs.whiteSpace ) {
				return;
			}
			ctx.font = `${ cs.fontStyle } ${ cs.fontWeight } ${ cs.fontSize } ${ cs.fontFamily }`;
			const longest = text.split( /\s+/ ).reduce( ( m, w ) => Math.max( m, ctx.measureText( w ).width ), 0 );
			const box = el.clientWidth - parseFloat( cs.paddingLeft ) - parseFloat( cs.paddingRight );
			if ( box > 0 && longest > box + 1 ) {
				add( 'midword', el, `longest word ${ Math.round( longest ) }px in a ${ Math.round( box ) }px box` );
			}
		} );

		// clipped: controls/headings cut by an overflow:hidden|clip ancestor
		root.querySelectorAll( 'a, button, input, select, textarea, summary, h1, h2, h3, h4, .wcb-badge, [class*="wcb-btn"]' ).forEach( ( el ) => {
			if ( ! visible( el ) ) {
				return;
			}
			const r = el.getBoundingClientRect();
			for ( let n = el.parentElement; n && n !== document.body; n = n.parentElement ) {
				const cs = getComputedStyle( n );
				if ( /auto|scroll/.test( cs.overflowX + cs.overflowY ) ) {
					return; // scroll affordance
				}
				if ( /hidden|clip/.test( cs.overflowX + cs.overflowY ) && ! /\bwcb-(sr|visually-hidden|screen-reader)/.test( el.className ) ) {
					const p = n.getBoundingClientRect();
					if ( r.left < p.left - 1 || r.right > p.right + 1 || r.top < p.top - 1 || r.bottom > p.bottom + 1 ) {
						if ( p.width > 2 && p.height > 2 ) {
							add( 'clipped', el, `cut by ${ n.tagName.toLowerCase() }.${ String( n.className ).split( ' ' )[ 0 ] }` );
						}
						return;
					}
				}
			}
		} );

		// contrast: text nodes and placeholders
		const seen = new Set();
		const walker = document.createTreeWalker( root, NodeFilter.SHOW_TEXT, { acceptNode: ( t ) => ( t.nodeValue.trim() ? 1 : 2 ) } );
		for ( let t = walker.nextNode(); t; t = walker.nextNode() ) {
			const el = t.parentElement;
			if ( seen.has( el ) || ! visible( el ) || el.closest( 'button:disabled, [aria-disabled="true"], .screen-reader-text, svg' ) ) {
				continue;
			}
			seen.add( el );
			const cs = getComputedStyle( el );
			const fg = parse( cs.color ), bg = bgOf( el );
			if ( ! fg || ! bg ) {
				continue;
			}
			const size = parseFloat( cs.fontSize ), bold = parseInt( cs.fontWeight, 10 ) >= 700;
			const need = size >= 24 || ( bold && size >= 18.66 ) ? 3 : 4.5;
			const r = ratio( blend( fg, bg ), bg );
			if ( r < need - 0.05 ) {
				add( 'contrast', el, `${ r.toFixed( 2 ) }:1 < ${ need }` );
			}
		}
		root.querySelectorAll( 'input[placeholder], textarea[placeholder]' ).forEach( ( el ) => {
			if ( ! visible( el ) || ! el.placeholder ) {
				return;
			}
			const fg = parse( getComputedStyle( el, '::placeholder' ).color ), bg = bgOf( el );
			if ( fg && bg ) {
				const r = ratio( blend( fg, bg ), bg );
				if ( r < 4.45 ) {
					add( 'contrast', el, `placeholder ${ r.toFixed( 2 ) }:1 < 4.5` );
				}
			}
		} );

		// wpadmin
		root.querySelectorAll( '.form-table, .wp-list-table, .button-primary, .button-secondary, .notice, .postbox, .widefat' ).forEach( ( el ) => add( 'wpadmin', el, 'wp-admin class in frontend markup' ) );
	} );

	return { roots: roots.length, findings: out };
}

function arg( name ) {
	const hit = process.argv.find( ( a ) => a === `--${ name }` || a.startsWith( `--${ name }=` ) );
	return hit ? ( hit.split( '=' )[ 1 ] ?? true ) : undefined;
}

async function applyScheme( page, scheme ) {
	await page.emulateMedia( { colorScheme: scheme } );
	await page.evaluate( ( dark ) => {
		const h = document.documentElement;
		h.classList.toggle( 'dark-mode', dark );
		h.setAttribute( 'data-bx-mode', dark ? 'dark' : 'light' );
	}, 'dark' === scheme );
	await page.waitForTimeout( 150 );
}

async function selfTest( browser ) {
	const page = await browser.newPage( { viewport: { width: 390, height: 900 } } );
	await page.goto( 'file://' + path.join( ROOT, 'tests/presentation/planted.html' ) );
	const { findings } = await page.evaluate( inPageChecks );
	const want = [ 'overflow', 'edges', 'midword', 'clipped', 'contrast', 'wpadmin' ];
	const got = new Set( findings.map( ( f ) => f.check ) );
	const missing = want.filter( ( c ) => ! got.has( c ) );
	// The clean sample must stay clean: no check may fire inside it.
	const falsePos = findings.filter( ( f ) => /clean/i.test( f.el + f.text ) );
	want.forEach( ( c ) => console.log( `  ${ got.has( c ) ? 'fires' : 'MISSED' }  ${ c }` ) );
	falsePos.forEach( ( f ) => console.log( `  FALSE POSITIVE ${ f.check } on ${ f.el } "${ f.text }"` ) );
	await page.close();
	return 0 === missing.length && 0 === falsePos.length;
}

async function main() {
	let chromium;
	try {
		( { chromium } = require( 'playwright' ) );
	} catch ( e ) {
		console.error( 'presentation-gate: playwright not installed (npm install).' );
		return 2;
	}
	// Playwright's own browser when installed, else the machine's Chrome.
	const browser = await chromium.launch().catch( () => chromium.launch( { channel: 'chrome' } ) );
	try {
		const okSelf = await selfTest( browser );
		console.log( okSelf ? 'self-test: every check fires on its planted sample' : 'self-test FAILED' );
		if ( ! okSelf || arg( 'self-test' ) ) {
			return okSelf ? 0 : 1;
		}

		const cfg = JSON.parse( fs.readFileSync( path.join( ROOT, 'docs/qa/presentation-views.json' ), 'utf8' ) );
		// The site this checkout lives in (qa-config keeps base_url null on purpose).
		let base = process.env.WCB_PRESENTATION_URL || '';
		if ( ! base ) {
			try {
				base = require( 'child_process' ).execSync( `wp --path="${ path.resolve( ROOT, '../../..' ) }" option get home`, { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ], env: { ...process.env, WP_CLI_PHP_ARGS: '-d error_reporting=0' } } ).trim();
			} catch ( e ) {}
		}
		base = base.replace( /\/$/, '' );
		if ( ! base ) {
			console.error( 'presentation-gate: no site URL (set WCB_PRESENTATION_URL or run inside a WP install).' );
			return 2;
		}
		const outDir = process.env.WCB_PRESENTATION_OUT || path.resolve( ROOT, '../../../../qa-artifacts/presentation-gate', new Date().toISOString().replace( /[:.]/g, '-' ) );
		fs.mkdirSync( outDir, { recursive: true } );

		const views = cfg.views.filter( ( v ) => ! arg( 'view' ) || v.id === arg( 'view' ) );
		const widths = arg( 'width' ) ? [ +arg( 'width' ) ] : cfg.widths;
		const schemes = arg( 'scheme' ) ? [ arg( 'scheme' ) ] : cfg.schemes;
		const contexts = {};
		let total = 0;
		const report = [];

		for ( const v of views ) {
			const who = v.as || 'visitor';
			if ( ! contexts[ who ] ) {
				contexts[ who ] = await browser.newContext();
				if ( v.as ) {
					const p = await contexts[ who ].newPage();
					await p.goto( `${ base }/?autologin=${ encodeURIComponent( v.as ) }` );
					await p.close();
				}
			}
			const page = await contexts[ who ].newPage();
			for ( const w of widths ) {
				await page.setViewportSize( { width: w, height: 900 } );
				await page.goto( base + v.path.replace( '#', `${ v.path.includes( '?' ) ? '&' : '?' }pg=${ w }#` ), { waitUntil: 'networkidle' } ).catch( () => {} );
				for ( const s of schemes ) {
					await applyScheme( page, s );
					const { roots, findings } = await page.evaluate( inPageChecks );
					const tag = `${ v.id } ${ w } ${ s }`;
					await page.screenshot( { path: path.join( outDir, `${ v.id }-${ w }-${ s }.png` ), fullPage: true } );
					if ( ! roots ) {
						findings.push( { check: 'empty', el: 'page', text: '', detail: 'no plugin block rendered' } );
					}
					total += findings.length;
					report.push( { view: v.id, width: w, scheme: s, findings } );
					console.log( `  ${ findings.length ? 'FAIL' : 'ok  ' } ${ tag }${ findings.length ? ` (${ findings.length })` : '' }` );
					findings.slice( 0, 8 ).forEach( ( f ) => console.log( `        ${ f.check }: ${ f.el } "${ f.text }" ${ f.detail }` ) );
				}
			}
			await page.close();
		}
		fs.writeFileSync( path.join( outDir, 'report.json' ), JSON.stringify( report, null, '\t' ) );
		console.log( `\n${ total } finding(s); screenshots + report.json in ${ outDir }` );
		return total ? 1 : 0;
	} finally {
		await browser.close();
	}
}

main().then( ( code ) => process.exit( code ), ( e ) => {
	console.error( e );
	process.exit( 2 );
} );
