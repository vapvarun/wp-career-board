/**
 * WP Career Board — Editor.js Bootstrap
 *
 * Initialises Editor.js inside any `.wcb-editor` element on the page.
 * Mirrors the integration pattern used in Learnomy's lesson-editor.js
 * (same vendor bundles, same tool subset) so the rich-text experience
 * is consistent across the Wbcom plugin family.
 *
 * Each `.wcb-editor` element must contain:
 *   - `.wcb-editor-holder`              — Editor.js render target.
 *   - `<textarea class="wcb-editor-source" ...>` — hidden mirror that
 *     carries the original Interactivity API bindings
 *     (`data-wp-bind--value="state.description"` /
 *     `data-wp-on--input="actions.updateField"`). On every Editor.js
 *     change we serialise the editor to HTML, write it to the textarea,
 *     and dispatch an `input` event so the Interactivity store updates
 *     exactly as if the user had typed into a plain textarea.
 *
 * The hidden textarea also keeps the existing REST contract intact
 * (server still receives `description` as HTML) so this change is
 * backwards compatible with custom Pro forms, shortcode embeds, and
 * the existing job CRUD flow.
 *
 * @package WP_Career_Board
 */

( function () {
	'use strict';

	const EDITOR_VERSION = 'editorjs-2.30.8';

	const escapeHtml = ( raw ) => String( raw ).replace( /[&<>"']/g, ( ch ) => ( {
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#39;',
	}[ ch ] ) );

	const blocksToHtml = ( saved ) => {
		if ( ! saved || ! Array.isArray( saved.blocks ) ) {
			return '';
		}
		const html = saved.blocks.map( ( block ) => {
			const data = block.data || {};
			switch ( block.type ) {
				case 'paragraph':
					return data.text ? '<p>' + data.text + '</p>' : '';
				case 'header': {
					const level = [ 2, 3, 4 ].includes( Number( data.level ) ) ? Number( data.level ) : 2;
					return data.text ? `<h${ level }>${ data.text }</h${ level }>` : '';
				}
				case 'list': {
					const tag = 'ordered' === data.style ? 'ol' : 'ul';
					const items = ( data.items || [] ).map( ( item ) => {
						const text = typeof item === 'string' ? item : ( item.content || item.text || '' );
						return text ? '<li>' + text + '</li>' : '';
					} ).join( '' );
					return items ? `<${ tag }>${ items }</${ tag }>` : '';
				}
				case 'quote':
					return data.text ? '<blockquote>' + data.text + ( data.caption ? '<cite>' + data.caption + '</cite>' : '' ) + '</blockquote>' : '';
				case 'delimiter':
					return '<hr />';
				default:
					return '';
			}
		} ).filter( Boolean ).join( '\n' );
		return html;
	};

	// Parse a stored description into Editor.js blocks. Accepts the editor's own
	// saved HTML, AI-generated HTML, or AI-generated markdown / plain text, and
	// preserves headings, paragraphs, and lists — so an AI description never
	// collapses into one wall-of-text paragraph. Falls back to a paragraph when
	// the Header / List tools aren't loaded so render never throws.
	const htmlToBlocks = ( html ) => {
		const trimmed = String( html || '' ).trim();
		if ( ! trimmed ) {
			return { blocks: [] };
		}

		const hasHeader = typeof window.Header !== 'undefined';
		const hasList   = typeof window.List !== 'undefined';
		const inline    = ( s ) =>
			s.replace( /\*\*([^*]+)\*\*/g, '<b>$1</b>' ).replace( /(^|\s)\*([^*\n]+)\*/g, '$1<i>$2</i>' );

		const container = document.createElement( 'div' );
		if ( /<(p|h[1-6]|ul|ol|li|br)\b/i.test( trimmed ) ) {
			container.innerHTML = trimmed;
		} else {
			// Blank lines separate blocks; "# " => heading, "- "/"* "/"• " => list.
			container.innerHTML = trimmed
				.split( /\n{2,}/ )
				.map( ( block ) => {
					const lines = block.split( /\n/ ).map( ( l ) => l.trim() ).filter( Boolean );
					if ( ! lines.length ) {
						return '';
					}
					if ( lines.length === 1 && /^#{1,6}\s+/.test( lines[ 0 ] ) ) {
						return '<h3>' + inline( lines[ 0 ].replace( /^#{1,6}\s+/, '' ) ) + '</h3>';
					}
					if ( lines.every( ( l ) => /^[-*•]\s+/.test( l ) ) ) {
						return (
							'<ul>' +
							lines.map( ( l ) => '<li>' + inline( l.replace( /^[-*•]\s+/, '' ) ) + '</li>' ).join( '' ) +
							'</ul>'
						);
					}
					return '<p>' + inline( lines.join( ' ' ) ) + '</p>';
				} )
				.filter( Boolean )
				.join( '' );
		}

		const blocks = [];
		container.childNodes.forEach( ( node ) => {
			if ( node.nodeType === 3 ) {
				const text = ( node.textContent || '' ).trim();
				if ( text ) {
					blocks.push( { type: 'paragraph', data: { text } } );
				}
				return;
			}
			if ( node.nodeType !== 1 ) {
				return;
			}
			const tag = node.tagName.toLowerCase();
			if ( /^h[1-6]$/.test( tag ) ) {
				const text = node.textContent.trim();
				if ( ! text ) {
					return;
				}
				if ( hasHeader ) {
					const level = Math.min( 4, Math.max( 2, parseInt( tag.charAt( 1 ), 10 ) ) );
					blocks.push( { type: 'header', data: { text, level } } );
				} else {
					blocks.push( { type: 'paragraph', data: { text: '<b>' + text + '</b>' } } );
				}
			} else if ( 'ul' === tag || 'ol' === tag ) {
				const items = Array.from( node.querySelectorAll( 'li' ) )
					.map( ( li ) => li.innerHTML.trim() )
					.filter( Boolean );
				if ( ! items.length ) {
					return;
				}
				if ( hasList ) {
					blocks.push( { type: 'list', data: { style: 'ol' === tag ? 'ordered' : 'unordered', items } } );
				} else {
					blocks.push( { type: 'paragraph', data: { text: items.map( ( i ) => '• ' + i ).join( '<br>' ) } } );
				}
			} else {
				const text = node.innerHTML.trim();
				if ( text ) {
					blocks.push( { type: 'paragraph', data: { text } } );
				}
			}
		} );

		if ( ! blocks.length ) {
			blocks.push( { type: 'paragraph', data: { text: trimmed.replace( /<\/?[^>]+>/g, '' ) } } );
		}
		return { blocks };
	};

	const buildTools = () => {
		const tools = {};
		if ( typeof window.Header !== 'undefined' ) {
			tools.header = {
				class: window.Header,
				config: { levels: [ 2, 3 ], defaultLevel: 3 },
				inlineToolbar: true,
			};
		}
		if ( typeof window.List !== 'undefined' ) {
			tools.list = { class: window.List, inlineToolbar: true };
		}
		if ( typeof window.Quote !== 'undefined' ) {
			tools.quote = { class: window.Quote, inlineToolbar: true };
		}
		if ( typeof window.Delimiter !== 'undefined' ) {
			tools.delimiter = window.Delimiter;
		}
		if ( typeof window.Marker !== 'undefined' ) {
			tools.marker = { class: window.Marker, shortcut: 'CMD+SHIFT+M' };
		}
		if ( typeof window.InlineCode !== 'undefined' ) {
			tools.inlineCode = { class: window.InlineCode, shortcut: 'CMD+SHIFT+C' };
		}
		return tools;
	};

	function init( editorEl ) {
		if ( editorEl.dataset.wcbEditorInit === '1' ) {
			return;
		}
		if ( typeof window.EditorJS === 'undefined' ) {
			return;
		}
		editorEl.dataset.wcbEditorInit = '1';

		const holder   = editorEl.querySelector( '.wcb-editor-holder' );
		const textarea = editorEl.querySelector( 'textarea.wcb-editor-source' );

		if ( ! holder || ! textarea ) {
			return;
		}

		// Editor.js needs a unique ID on its holder.
		if ( ! holder.id ) {
			holder.id = 'wcb-editor-' + Math.random().toString( 36 ).slice( 2, 10 );
		}

		let isSaving       = false;
		let lastRendered   = textarea.value;
		let isUserEditing  = false;

		const editor = new window.EditorJS( {
			holder: holder.id,
			tools: buildTools(),
			data: htmlToBlocks( textarea.value ),
			placeholder: editorEl.dataset.placeholder || 'Describe the role, responsibilities and requirements...',
			minHeight: 200,
			onChange: () => {
				if ( isSaving ) {
					return;
				}
				isUserEditing = true;
				isSaving      = true;
				editor.save().then( ( saved ) => {
					const html     = blocksToHtml( saved );
					textarea.value = html;
					lastRendered   = html;
					textarea.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				} ).finally( () => {
					isSaving = false;
				} );
			},
		} );

		const rerenderFromTextarea = () => {
			isSaving = true;
			editor.isReady.then( () => editor.render( htmlToBlocks( textarea.value ) ) ).finally( () => {
				lastRendered = textarea.value;
				isSaving     = false;
			} );
		};

		// Late hydration: Interactivity API binds `data-wp-bind--value` after
		// our init runs, so the textarea may pick up its real value (existing
		// post_content, autosave, AI-generated, etc.) several hundred ms after
		// Editor.js mounted with an empty document. Poll briefly and re-render
		// once when the value diverges. Stops after 2s OR as soon as the user
		// starts typing, so we never clobber active editing.
		let polls = 0;
		const pollId = setInterval( () => {
			if ( isUserEditing || polls > 10 ) {
				clearInterval( pollId );
				return;
			}
			if ( textarea.value && textarea.value !== lastRendered ) {
				rerenderFromTextarea();
				clearInterval( pollId );
				return;
			}
			polls++;
		}, 200 );

		// Producers (AI generate, autosave restore, programmatic value pushes)
		// can dispatch `wcb:editor:hydrate` after writing textarea.value to
		// force an immediate re-render — bypasses the polling window.
		textarea.addEventListener( 'wcb:editor:hydrate', rerenderFromTextarea );

		editorEl.wcbEditor = editor;
		editorEl.dataset.wcbEditorVersion = EDITOR_VERSION;
	}

	function initAll( root ) {
		( root || document ).querySelectorAll( '.wcb-editor' ).forEach( init );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => initAll() );
	} else {
		initAll();
	}

	// Late mount — Interactivity API can render conditional steps after first paint.
	const observer = new MutationObserver( ( muts ) => {
		muts.forEach( ( m ) => m.addedNodes.forEach( ( n ) => {
			if ( n.nodeType !== 1 ) {
				return;
			}
			if ( n.matches && n.matches( '.wcb-editor' ) ) {
				init( n );
			}
			if ( n.querySelectorAll ) {
				n.querySelectorAll( '.wcb-editor' ).forEach( init );
			}
		} ) );
	} );
	observer.observe( document.body, { childList: true, subtree: true } );
}() );
