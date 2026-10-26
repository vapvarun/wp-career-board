/**
 * WP Career Board — shared custom-field value reader for Interactivity API forms.
 *
 * Every form that renders FormCustomFields markup (apply, post-a-job, the
 * simple job form, the company profile) binds its inputs to an
 * updateCustomField action. That action needs the field's value in the shape
 * the server expects, which depends on the control:
 * - a multi-choice field (checkboxes marked data-wcb-multi): the checked values;
 * - a single checkbox: its checked state;
 * - anything else: its value.
 *
 * @module @wcb/fields
 */

/**
 * Read a custom field's value from the element that changed.
 *
 * @param {HTMLElement} target The input, select or textarea that changed.
 * @return {string|boolean|string[]} The value to store under its field key.
 */
export function customFieldValue( target ) {
	if ( target.dataset.wcbMulti ) {
		const key   = target.getAttribute( 'data-wcb-field' );
		const scope = target.form || document;
		return Array.from( scope.querySelectorAll( '[data-wcb-field="' + key + '"][data-wcb-multi]' ) )
			.filter( ( el ) => el.checked )
			.map( ( el ) => el.value );
	}
	if ( 'checkbox' === target.type ) {
		return target.checked;
	}
	return target.value;
}

/**
 * The location part of a job's REST body, shared by both job forms so they
 * cannot drift: "Other (enter manually)" sends the typed text as
 * location_custom and never the '__custom__' placeholder as a term
 * (Basecamp 10440004221).
 *
 * @param {string} slug   Selected wcb_location slug, '__custom__' or ''.
 * @param {string} custom Typed location when slug is '__custom__'.
 * @return {{locations: string[], location_custom: string}} Body fields.
 */
export function jobLocation( slug, custom ) {
	const isCustom = '__custom__' === slug;
	return {
		locations:       slug && ! isCustom ? [ slug ] : [],
		location_custom: isCustom ? ( custom || '' ).trim() : '',
	};
}

/**
 * The one location word for the AI job-description prompt: the chosen term,
 * the typed "Other" text, or 'remote' when neither is set.
 *
 * @param {string} slug   Selected wcb_location slug, '__custom__' or ''.
 * @param {string} custom Typed location when slug is '__custom__'.
 * @return {string} Location for the prompt.
 */
export function jobLocationLabel( slug, custom ) {
	const where = jobLocation( slug, custom );
	return where.locations[ 0 ] || where.location_custom || 'remote';
}

/**
 * Whether a typed salary range runs backwards (both ends set, min above max).
 *
 * @param {string|number} min Minimum salary.
 * @param {string|number} max Maximum salary.
 * @return {boolean} True when the range is inverted.
 */
export function salaryInverted( min, max ) {
	return Number( min ) > 0 && Number( max ) > 0 && Number( min ) > Number( max );
}

/**
 * Whether a custom-field group shows on the chosen board.
 *
 * @param {number[]}      groupBoards Board IDs the group is used on.
 * @param {string|number} boardId     Selected board ID.
 * @return {boolean} True when the group belongs on that board.
 */
export function groupOnBoard( groupBoards, boardId ) {
	return groupBoards.includes( Number( boardId ) );
}
