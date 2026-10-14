/**
 * Job form category field (templates/parts/job-category-field.php).
 *
 * The chosen slugs are the host form's own `state.categorySlugs`, so its
 * preview and submit read them with no hand-off. The field root's context
 * names that store (`form`); each option carries its `slug`.
 *
 * @module @wcb/job-category-field
 */
import { store, getContext } from '@wordpress/interactivity';
import { filterList } from '@wcb/ui';

const chosen = () => store( getContext().form ).state.categorySlugs || [];
const choose = ( slugs ) => {
	store( getContext().form ).state.categorySlugs = slugs;
};
const nameOf = ( slug ) => state.names[ slug ] || slug;

const { state } = store( 'wcb-job-category-field', {
	state: {
		get chosen() {
			return chosen().map( ( slug ) => ( { slug, name: nameOf( slug ) } ) );
		},
		get first() {
			return chosen()[ 0 ] || '';
		},
		get isChosen() {
			return chosen().includes( getContext().slug );
		},
		// At the limit the rest are disabled; a job saved over it (wp-admin) can still drop some.
		get isLocked() {
			return chosen().length >= state.limit && ! chosen().includes( getContext().slug );
		},
		get counterText() {
			return state.counterFormat
				.replace( '%1$s', () => String( chosen().length ) )
				.replace( '%2$s', () => String( state.limit ) );
		},
		get limitText() {
			return chosen().length >= state.limit ? state.limitMessage : '';
		},
		get removeLabel() {
			return state.removeFormat.replace( '%s', () => getContext().cat.name );
		},
		// One-category select on a job that already has several: say so instead of hiding them.
		get manyNote() {
			return chosen().length > 1
				? state.manyFormat.replace( '%s', () => chosen().map( nameOf ).join( state.separator ) )
				: '';
		},
	},
	actions: {
		pick( event ) {
			choose( event.target.value ? [ event.target.value ] : [] );
		},
		toggle( event ) {
			const { slug } = getContext();
			const rest     = chosen().filter( ( item ) => item !== slug );
			choose( event.target.checked ? [ ...rest, slug ] : rest );
		},
		remove() {
			const { cat } = getContext();
			choose( chosen().filter( ( item ) => item !== cat.slug ) );
		},
		search( event ) {
			filterList( event.target.value, event.target.closest( '.wcb-job-cats' ).querySelector( '.wcb-job-cats__list' ) );
		},
	},
} );
