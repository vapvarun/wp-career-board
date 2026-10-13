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
 *   navgroup  a visible menu-group heading over fewer than 2 items (sidebar rule, 10400673345)
 *   caret     a select whose drawn arrow shares space with its label (10401140048)
 *
 * Usage:
 *   node bin/presentation-gate.cjs               run against this install's site
 *   node bin/presentation-gate.cjs --self-test   prove every check fails on tests/presentation/planted.html
 *   node bin/presentation-gate.cjs --view=emp-settings --width=390 --scheme=dark
 *   node bin/presentation-gate.cjs --theme=buddyx   (needs a local qa_theme cookie switcher)
 *   node bin/presentation-gate.cjs --brand=#38bdf8  (an owner's light accent; text must still pass)
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
		// Screen-reader-only text is clipped away (WordPress: clip rect(1px,1px,1px,1px)),
		// whatever width the theme leaves on it.
		if ( /^rect\((0|1)px,? (0|1)px,? (0|1)px,? (0|1)px\)$/.test( cs.clip ) || 'inset(50%)' === cs.clipPath ) {
			return false;
		}
		return 'hidden' !== cs.visibility && '0' !== cs.opacity && ! el.closest( '[hidden], [aria-hidden="true"], details:not([open]) > :not(summary)' );
	};
	const roots = [ ...document.querySelectorAll( SCOPE ) ].filter( ( r ) => visible( r ) && ! r.parentElement.closest( SCOPE ) );

	// A 404 is a broken fixture or a broken page, never a clean pass.
	if ( document.body.classList.contains( 'error404' ) ) {
		add( 'missing', document.body, 'page returned 404' );
	}

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
	// Any computed colour (rgb(), color(srgb …) from color-mix(), oklch() from relative
	// colours) goes through a 1px canvas, which hands back plain sRGB 0-255 + alpha.
	const px = document.createElement( 'canvas' ).getContext( '2d', { willReadFrequently: true } );
	const parse = ( c ) => {
		if ( ! c ) {
			return null;
		}
		px.clearRect( 0, 0, 1, 1 );
		px.fillStyle = '#000';
		px.fillStyle = c;
		px.fillRect( 0, 0, 1, 1 );
		const [ r, g, b, a ] = px.getImageData( 0, 0, 1, 1 ).data;
		return { r, g, b, a: a / 255 };
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
		// A dashboard tab (role=region) is one reading column; its cards are not.
		const tabPanels = [ ...root.querySelectorAll( '[role="region"]' ) ].filter( ( r ) => visible( r ) && ! r.parentElement.closest( '[role="region"]' ) );
		// Outside a dashboard tab, each form is its own column (listing toolbars
		// sit beside their results, which is layout, not a stray edge).
		const panels = tabPanels.length ? tabPanels : [ ...root.querySelectorAll( 'form' ) ].filter( visible );
		panels.forEach( ( p ) => {
			// The whole panel is one reading column: a heading, its labels, fields
			// and help share at most two edges (section title + card content),
			// across every form in it. Measuring form by form missed the
			// pre-1.8.1 Settings panel (headings 0, fields 20, row labels 40).
			[ p ].forEach( ( group ) => {
				const items = [ ...group.querySelectorAll( 'h2, h3, h4, label, legend, th, dt, [class*="row-label"], [class*="-hint"], .wcb-form-help, .wcb-field-help, .description, input:not([type=checkbox]):not([type=radio]):not([type=hidden]), select, textarea' ) ]
					// > 2px: screen-reader-only labels are 1x1 and are not a visible edge.
					.filter( ( e ) => visible( e ) && e.getBoundingClientRect().width > 2 && e.getBoundingClientRect().height > 2 );
				if ( items.length < 3 ) {
					return;
				}
				const gRect = group.getBoundingClientRect();
				// A row starts where nothing visible sits to the item's left on the
				// same line (a field beside a field, a button beside a logo are not
				// edges). Items inside another item (a hint in its label) don't count.
				const startsRow = ( e ) => {
					let r = e.getBoundingClientRect();
					// elementFromPoint only sees the viewport.
					if ( r.top < 0 || r.bottom > window.innerHeight ) {
						e.scrollIntoView( { block: 'center' } );
						r = e.getBoundingClientRect();
					}
					const y = r.top + Math.min( r.height / 2, 12 );
					for ( let x = r.left - 4; x > gRect.left + 1; x -= 8 ) {
						const hit = document.elementFromPoint( x, y );
						if ( hit && group.contains( hit ) && ! hit.contains( e ) && hit.getBoundingClientRect().right <= r.left + 1 ) {
							return false;
						}
					}
					return true;
				};
				const starts = items.filter( ( e ) => ! items.some( ( o ) => o !== e && o.contains( e ) ) && startsRow( e ) );
				const byEdge = new Map();
				starts.forEach( ( e ) => {
					const x = Math.round( ( e.getBoundingClientRect().left - gRect.left ) / 4 ) * 4;
					if ( ! byEdge.has( x ) ) {
						byEdge.set( x, ( e.innerText || e.placeholder || e.tagName ).trim().slice( 0, 24 ) );
					}
				} );
				if ( byEdge.size > 2 ) {
					const list = [ ...byEdge ].sort( ( a, b ) => a[ 0 ] - b[ 0 ] ).map( ( [ x, t ] ) => `${ x } "${ t }"` );
					add( 'edges', group, `${ byEdge.size } left edges: ${ list.join( ', ' ) }` );
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
			// 2px: canvas measuring and layout round differently (a word in a
			// box shrink-wrapped to it measures 1px over).
			if ( box > 0 && longest > box + 2 ) {
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
					// Inline text overflows its line box vertically by design (line-height,
					// descenders); only a horizontal cut hides it.
					const inline = 'inline' === getComputedStyle( el ).display;
					if ( r.left < p.left - 2 || r.right > p.right + 2 || ( ! inline && ( r.top < p.top - 2 || r.bottom > p.bottom + 2 ) ) ) {
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

		// caret: an appearance:none select draws its arrow as a background image;
		// the end padding must clear it or the arrow sits on the label.
		root.querySelectorAll( 'select:not([multiple]):not([size])' ).forEach( ( s ) => {
			const cs = getComputedStyle( s );
			if ( ! visible( s ) || 'none' === cs.backgroundImage || ! /none/.test( cs.appearance + cs.webkitAppearance ) ) {
				return;
			}
			const end = parseFloat( 'rtl' === cs.direction ? cs.paddingLeft : cs.paddingRight );
			if ( end < 28 ) {
				add( 'caret', s, `end padding ${ Math.round( end ) }px; the drawn arrow needs about 28px` );
			}
		} );

		// navgroup: a heading earns its place over 2+ items; one item gets a divider.
		root.querySelectorAll( '[role="group"][aria-labelledby]' ).forEach( ( g ) => {
			const label = document.getElementById( g.getAttribute( 'aria-labelledby' ) );
			if ( ! visible( g ) || ! label || ! visible( label ) || label.getBoundingClientRect().width <= 2 ) {
				return;
			}
			const items = [ ...g.querySelectorAll( 'a, button' ) ].filter( visible );
			if ( items.length < 2 ) {
				add( 'navgroup', g, `heading "${ label.innerText.trim() }" over ${ items.length } item` );
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
	const want = [ 'overflow', 'edges', 'midword', 'clipped', 'contrast', 'wpadmin', 'navgroup', 'caret' ];
	const got = new Set( findings.map( ( f ) => f.check ) );
	const missing = want.filter( ( c ) => ! got.has( c ) );
	// Regressions guarded one by one: a fix for a false positive must not blind the check.
	if ( ! findings.some( ( f ) => 'contrast' === f.check && f.el.includes( 'bad-colormix-text' ) ) ) {
		missing.push( 'contrast on a color-mix() background' );
		console.log( '  MISSED  contrast on a color-mix() background' );
	}
	if ( ! findings.some( ( f ) => 'contrast' === f.check && f.el.includes( 'bad-oklch-text' ) ) ) {
		missing.push( 'contrast on oklch() text' );
		console.log( '  MISSED  contrast on oklch() text' );
	}
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
				// --theme=<slug>: the site renders that theme for this run when a local
				// per-request switcher reads the qa_theme cookie (theme matrix checks).
				if ( arg( 'theme' ) ) {
					await contexts[ who ].addCookies( [ { name: 'qa_theme', value: String( arg( 'theme' ) ), url: base } ] );
				}
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
				// --brand=<hex>: an owner's own accent (a light one is the hard case for text contrast).
				if ( arg( 'brand' ) ) {
					await page.addStyleTag( { content: `:root, body { --wcb-primary: ${ String( arg( 'brand' ) ).replace( /[^#0-9a-fA-F]/g, '' ) } !important; }` } );
				}
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
		// System Chrome can hang on close after a long run; the report is written by now.
		await Promise.race( [ browser.close(), new Promise( ( r ) => setTimeout( r, 5000 ) ) ] );
	}
}

main().then( ( code ) => process.exit( code ), ( e ) => {
	console.error( e );
	process.exit( 2 );
} );
