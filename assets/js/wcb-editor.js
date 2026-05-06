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

	// First-edit migration of legacy HTML-only descriptions: drop the value as
	// a single paragraph block so Editor.js has something coherent to render.
	// Re-formatting on save produces a clean blocks structure on subsequent edits.
	const htmlToBlocks = ( html ) => {
		const trimmed = String( html || '' ).trim();
		if ( ! trimmed ) {
			return { blocks: [] };
		}
		return {
			blocks: [
				{
					type: 'paragraph',
					data: { text: trimmed.replace( /<\/?(p|div)[^>]*>/gi, '' ) },
				},
			],
		};
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

		const initial = htmlToBlocks( textarea.value );
		let isSaving  = false;

		const editor = new window.EditorJS( {
			holder: holder.id,
			tools: buildTools(),
			data: initial,
			placeholder: editorEl.dataset.placeholder || 'Describe the role, responsibilities and requirements...',
			minHeight: 200,
			onChange: () => {
				if ( isSaving ) {
					return;
				}
				isSaving = true;
				editor.save().then( ( saved ) => {
					textarea.value = blocksToHtml( saved );
					textarea.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				} ).finally( () => {
					isSaving = false;
				} );
			},
		} );

		// Allow producers (AI generate, autosave) to force a re-render by
		// dispatching `wcb:editor:hydrate` on the textarea after they update
		// its value externally.
		textarea.addEventListener( 'wcb:editor:hydrate', () => {
			editor.isReady.then( () => editor.render( htmlToBlocks( textarea.value ) ) );
		} );

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
