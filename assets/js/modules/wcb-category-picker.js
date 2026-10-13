/**
 * WP Career Board — category picker (templates/parts/category-picker.php).
 *
 * A button that opens a list of nested category checkboxes, used by the job
 * filters bar and the search hero. Ticking follows `categoryTree()`, the same
 * rule as the job listings sidebar. The chosen categories live in a hidden
 * field as a comma list (`category=a,b`): the hero form submits it, and the
 * filters bar hears its `change` event when the panel closes.
 *
 * Listens for `wcb:categories` ({ detail: { slugs } }), which the job listings
 * fires after every filter change, so the picker shows what the results use.
 *
 * @module @wcb/category-picker
 */
import { store, getContext, getElement, withScope } from '@wordpress/interactivity';
import { categoryTree, filterList } from '@wcb/ui';

const boxes = ( root ) => [ ...root.querySelectorAll( '.wcb-filter-panel__list input' ) ];
const treeOf = ( root ) => categoryTree( boxes( root ).map( ( box ) => ( { slug: box.value, parent: box.dataset.parent || '' } ) ) );
const ticked = ( root ) => new Set( boxes( root ).filter( ( box ) => box.checked ).map( ( box ) => box.value ) );
const rootOf = () => getElement().ref.closest( '.wcb-cat-picker' );
const fieldOf = ( root ) => root.querySelector( ':scope > input[type="hidden"]' );

/**
 * Show a selection: tick the boxes, write the field, update the button label.
 *
 * @param {HTMLElement} root Picker element.
 * @param {Object}      ctx  Picker context.
 * @param {Set<string>} set  Every ticked slug (parents with their children).
 */
function paint( root, ctx, set ) {
	const tree = treeOf( root );
	boxes( root ).forEach( ( box ) => {
		box.checked       = set.has( box.value );
		box.indeterminate = tree.mixed( set, box.value );
	} );
	const short = tree.compress( set );
	fieldOf( root ).value = short.join( ',' );
	// Count like the URL: a fully ticked parent is one choice, named after itself.
	if ( short.length > 1 ) {
		ctx.label = ctx.counts[ short.length ] || String( short.length );
	} else {
		const only = boxes( root ).find( ( box ) => box.value === short[ 0 ] );
		ctx.label  = only ? only.nextElementSibling.textContent.trim() : ctx.any;
	}
}

/**
 * Close the panel; tell the page when the choice changed while it was open.
 *
 * @param {HTMLElement} root    Picker element.
 * @param {Object}      ctx     Picker context.
 * @param {boolean}     refocus Return focus to the button (Escape, Done).
 */
function close( root, ctx, refocus ) {
	ctx.open    = false;
	const field = fieldOf( root );
	if ( field.value !== ctx.openedWith ) {
		field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}
	if ( refocus ) {
		root.querySelector( '.wcb-cat-picker__toggle' ).focus();
	}
}

store( 'wcb-category-picker', {
	actions: {
		toggleOpen() {
			const ctx  = getContext();
			const root = rootOf();
			if ( ctx.open ) {
				close( root, ctx, false );
				return;
			}
			ctx.openedWith = fieldOf( root ).value;
			ctx.open       = true;
		},
		toggle( event ) {
			const box  = event.target;
			const root = rootOf();
			if ( 'checkbox' === box.type ) {
				paint( root, getContext(), treeOf( root ).toggle( ticked( root ), box.value, box.checked ) );
			}
		},
		clear() {
			paint( rootOf(), getContext(), new Set() );
		},
		done() {
			close( rootOf(), getContext(), true );
		},
		search( event ) {
			filterList( event.target.value, rootOf().querySelector( '.wcb-filter-panel__list' ) );
		},
		onKeydown( event ) {
			const ctx = getContext();
			if ( 'Escape' === event.key && ctx.open ) {
				event.preventDefault();
				close( rootOf(), ctx, true );
			}
		},
		// Tabbing out of the panel closes it like a click elsewhere does.
		onFocusout( event ) {
			const ctx  = getContext();
			const root = rootOf();
			if ( ctx.open && event.relatedTarget && ! root.contains( event.relatedTarget ) ) {
				close( root, ctx, false );
			}
		},
		onDocumentClick( event ) {
			const ctx  = getContext();
			const root = rootOf();
			if ( ctx.open && ! root.contains( event.target ) ) {
				close( root, ctx, false );
			}
		},
	},
	callbacks: {
		init() {
			const ctx  = getContext();
			const root = getElement().ref;
			// A shared link may name every child instead of the parent: settle the tree.
			paint( root, ctx, treeOf( root ).expand( [ ...ticked( root ) ] ) );
			document.addEventListener(
				'wcb:categories',
				withScope( ( event ) => paint( root, ctx, treeOf( root ).expand( event.detail?.slugs || [] ) ) )
			);
		},
		focusPanel() {
			if ( getContext().open ) {
				const root = rootOf();
				( root.querySelector( '.wcb-filter-panel__search' ) || boxes( root )[ 0 ] )?.focus();
			}
		},
	},
} );
