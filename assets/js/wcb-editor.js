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

	// A plain-text field (data-wcb-editor-format="text": cover letter, bio) is
	// saved as text with its paragraphs and lists kept (Text::from_editor), so
	// its editor offers only those: no headings, quotes or inline formatting
	// that the save would drop. Tool configs deep-merge with Editor.js's
	// internal paragraph, so it needs no class here.
	const buildTools = ( textOnly ) => {
		const tools = {};
		if ( textOnly ) {
			tools.paragraph = { inlineToolbar: false };
			if ( typeof window.List !== 'undefined' ) {
				// Bullets: the text form keeps "- " items, not numbers.
				tools.list = { class: window.List, inlineToolbar: false, config: { defaultStyle: 'unordered' } };
			}
			return tools;
		}
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

	// Always-visible formatting toolbar (rendered by blocks/job-form/editor-toolbar.php).
	// Editor.js only shows its inline toolbar on a text selection, so authors never
	// found it. Buttons act on the last caret/selection inside the editor; Editor.js
	// sees the DOM change through its own observer, so blocksToHtml is unchanged.
	function wireToolbar( editorEl, holder, editor ) {
		const toolbar = editorEl.querySelector( '.wcb-editor-toolbar' );
		if ( ! toolbar ) {
			return;
		}
		const linkRow   = editorEl.querySelector( '.wcb-editor-link' );
		const linkInput = linkRow.querySelector( 'input' );
		const linkBtn   = toolbar.querySelector( '[data-wcb-format="link"]' );
		let range       = null;

		const syncPressed = () => {
			toolbar.querySelectorAll( '[data-wcb-format="bold"], [data-wcb-format="italic"]' ).forEach( ( btn ) => {
				btn.setAttribute( 'aria-pressed', String( document.queryCommandState( btn.dataset.wcbFormat ) ) );
			} );
			const block = range && blockOf( range.startContainer );
			toolbar.querySelector( '[data-wcb-format="list"]' )
				.setAttribute( 'aria-pressed', String( !! ( block && block.querySelector( '.cdx-list' ) ) ) );
		};

		const blockOf = ( node ) => {
			const el = node && ( node.nodeType === 1 ? node : node.parentElement );
			return el && holder.contains( el ) ? el.closest( '.ce-block' ) : null;
		};

		// Remember where the author was. selectionchange does not fire for every
		// keystroke, so the caret is also taken as focus leaves the editor (a Tab to
		// the toolbar) and from the live selection when a button is clicked.
		const capture = () => {
			const sel = window.getSelection();
			if ( sel.rangeCount && holder.contains( sel.anchorNode ) ) {
				range = sel.getRangeAt( 0 ).cloneRange();
				return true;
			}
			return false;
		};
		document.addEventListener( 'selectionchange', () => capture() && syncPressed() );
		holder.addEventListener( 'focusout', capture );

		// Put the caret back where the author left it (a toolbar click or the link
		// field took focus); with no caret yet, start at the end of the text.
		const restore = () => {
			if ( capture() ) {
				return;
			}
			const block    = range && blockOf( range.startContainer );
			const editable = block && block.querySelector( '[contenteditable="true"]' );
			if ( ! editable ) {
				editor.caret.setToLastBlock( 'end' );
				const sel = window.getSelection();
				range     = sel.rangeCount ? sel.getRangeAt( 0 ).cloneRange() : null;
				return;
			}
			editable.focus();
			const sel = window.getSelection();
			sel.removeAllRanges();
			sel.addRange( range );
		};

		const toggleList = () => {
			restore();
			const block = range && blockOf( range.startContainer );
			if ( ! block ) {
				editor.blocks.insert( 'list', { style: 'unordered', items: [ '' ] } );
				editor.caret.setToLastBlock( 'end' );
				return;
			}
			const index  = Array.prototype.indexOf.call( holder.querySelectorAll( '.ce-block' ), block );
			const isList = !! block.querySelector( '.cdx-list' );
			const done   = () => editor.caret.setToBlock( index, 'end' );
			if ( ! isList && ! block.textContent.trim() ) {
				// An empty line has nothing to convert; swap it for an empty list.
				editor.blocks.insert( 'list', { style: 'unordered', items: [ '' ] }, undefined, index, true, true );
				done();
				return;
			}
			editor.blocks.convert( editor.blocks.getBlockByIndex( index ).id, isList ? 'paragraph' : 'list' ).then( done, done );
		};

		const closeLink = ( refocus ) => {
			linkRow.hidden = true;
			linkInput.value = '';
			linkInput.removeAttribute( 'aria-invalid' );
			linkBtn.setAttribute( 'aria-expanded', 'false' );
			if ( refocus ) {
				restore();
			}
		};

		const applyLink = () => {
			let url = linkInput.value.trim();
			if ( ! url ) {
				closeLink( true );
				return;
			}
			if ( ! /^[a-z][a-z0-9+.-]*:/i.test( url ) ) {
				url = ( /^[^\s@/]+@[^\s@/]+\.[^\s@/]+$/.test( url ) ? 'mailto:' : 'https://' ) + url;
			}
			if ( ! /^(https?|mailto):/i.test( url ) ) {
				linkInput.setAttribute( 'aria-invalid', 'true' );
				linkInput.focus();
				return;
			}
			restore();
			if ( range && ! range.collapsed ) {
				document.execCommand( 'createLink', false, url );
			} else {
				const before = range ? range.startContainer.textContent.charAt( range.startOffset - 1 ) : '';
				document.execCommand( 'insertHTML', false, ( before && ! /\s/.test( before ) ? ' ' : '' ) + '<a href="' + escapeHtml( url ) + '">' + escapeHtml( url.replace( /^mailto:/i, '' ) ) + '</a>&nbsp;' );
			}
			closeLink( false );
		};

		// Keep the editor's selection when a button is pressed with the mouse.
		toolbar.addEventListener( 'mousedown', ( e ) => {
			if ( e.target.closest( 'button' ) ) {
				e.preventDefault();
			}
		} );

		toolbar.addEventListener( 'click', ( e ) => {
			const btn = e.target.closest( '[data-wcb-format]' );
			if ( ! btn ) {
				return;
			}
			const format = btn.dataset.wcbFormat;
			editor.isReady.then( () => {
				if ( 'list' === format ) {
					toggleList();
				} else if ( 'link' === format ) {
					if ( ! linkRow.hidden ) {
						closeLink( true );
						return;
					}
					capture();
					linkRow.hidden = false;
					linkBtn.setAttribute( 'aria-expanded', 'true' );
					linkInput.focus();
				} else {
					restore();
					document.execCommand( format );
					syncPressed();
				}
			} );
		} );

		linkRow.addEventListener( 'click', ( e ) => {
			const btn = e.target.closest( '[data-wcb-link]' );
			if ( btn && 'apply' === btn.dataset.wcbLink ) {
				applyLink();
			} else if ( btn ) {
				closeLink( true );
			}
		} );

		linkInput.addEventListener( 'keydown', ( e ) => {
			if ( 'Enter' === e.key ) {
				e.preventDefault();
				e.stopPropagation();
				applyLink();
			} else if ( 'Escape' === e.key ) {
				e.preventDefault();
				e.stopPropagation();
				closeLink( true );
			}
		} );
	}

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
		let syncing        = false;
		let syncAgain      = false;
		let lastRendered   = textarea.value;
		let isUserEditing  = false;

		// Write the editor's content to the textarea and tell the form (Interactivity
		// state listens for `input`). Editor.js saves asynchronously.
		const writeBack = () => editor.save().then( ( saved ) => {
			const html     = blocksToHtml( saved );
			textarea.value = html;
			lastRendered   = html;
			textarea.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );

		// A change made while a save is running is queued, not dropped, so the last
		// keystrokes always reach the textarea.
		const queueSync = () => {
			if ( syncing ) {
				syncAgain = true;
				return;
			}
			syncing = true;
			writeBack().finally( () => {
				syncing = false;
				if ( syncAgain ) {
					syncAgain = false;
					queueSync();
				}
			} );
		};

		const editor = new window.EditorJS( {
			holder: holder.id,
			tools: buildTools( 'text' === editorEl.dataset.wcbEditorFormat ),
			data: htmlToBlocks( textarea.value ),
			placeholder: editorEl.dataset.placeholder || '',
			i18n: { messages: window.wcbEditorI18n || {} },
			minHeight: 200,
			onChange: () => {
				if ( isSaving ) {
					return;
				}
				isUserEditing = true;
				queueSync();
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

		wireToolbar( editorEl, holder, editor );

		editorEl.wcbEditor = editor;
		// Store actions yield this (flushEditors in @wcb/ui) before they read the field.
		editorEl.wcbFlush = () => editor.isReady.then( writeBack );
		editorEl.dataset.wcbEditorVersion = EDITOR_VERSION;
		flushOnNativeSubmit( editorEl.closest( 'form' ) );
	}

	/**
	 * A native form (the wp-admin job editor) posts the textarea the moment it
	 * submits, before Editor.js has finished saving the last keystrokes. Hold the
	 * first submit, flush every editor in the form, then submit again with the
	 * same button. One listener per form, so two editors never submit twice.
	 *
	 * @param {?HTMLFormElement} form Form around the editor.
	 */
	function flushOnNativeSubmit( form ) {
		if ( ! form || form.dataset.wcbEditorSubmit === '1' ) {
			return;
		}
		form.dataset.wcbEditorSubmit = '1';
		let flushed = false;
		form.addEventListener( 'submit', ( event ) => {
			if ( flushed ) {
				flushed = false;
				return;
			}
			event.preventDefault();
			event.stopImmediatePropagation();
			const flushes = Array.from( form.querySelectorAll( '.wcb-editor' ) )
				.filter( ( el ) => typeof el.wcbFlush === 'function' )
				.map( ( el ) => el.wcbFlush().catch( () => {} ) );
			// The flush can settle on microtasks while the browser is still
			// dispatching this submit, when requestSubmit() is silently ignored;
			// a task later it is not.
			Promise.all( flushes ).then( () => setTimeout( () => {
				flushed = true;
				form.requestSubmit( event.submitter || undefined );
			} ) );
		}, true );
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
