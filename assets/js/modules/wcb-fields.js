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

const HOST_LABEL = '[a-z0-9\u0080-￿](?:[a-z0-9\u0080-￿-]*[a-z0-9\u0080-￿])?';
const WEB_HOST   = new RegExp( '^' + HOST_LABEL + '(?:\\.' + HOST_LABEL + ')+$', 'i' );

/**
 * Whether a typed web address passes the server rule (\WCB\Core\Text::web_url()).
 *
 * An http(s) URL or a bare domain ("northwind.example/careers", saved with
 * https) with a dotted host. Empty is fine; whether a field is required is
 * checked elsewhere. Keep in step with Text::web_url().
 *
 * @param {string} raw Typed value.
 * @return {boolean} True when the server would keep it.
 */
export function isWebAddress( raw ) {
	const value = String( raw ?? '' ).trim();
	if ( '' === value ) {
		return true;
	}
	const scheme = /^([a-z][a-z0-9+.-]*):\/\//i.exec( value );
	if ( scheme ? ! /^https?$/i.test( scheme[ 1 ] ) : value.includes( ':' ) ) {
		return false;
	}
	const rest = scheme ? value.slice( scheme[ 0 ].length ) : value;
	const host = rest.split( /[/?#]/ )[ 0 ].replace( /:\d+$/, '' );
	return WEB_HOST.test( host );
}

/*
 * Web-address inputs (inputmode="url" with a .wcb-field-error--url after
 * them): marked aria-invalid and pointed at the error when the field is
 * left with an address the server would drop; cleared as soon as it is fixed.
 */
const markWebAddress = ( event ) => {
	const el    = event.target;
	const error = el.nextElementSibling;
	if ( ! el.matches?.( 'input[inputmode="url"]' ) || ! error?.classList.contains( 'wcb-field-error--url' ) ) {
		return;
	}
	if ( isWebAddress( el.value ) ) {
		el.removeAttribute( 'aria-invalid' );
		el.removeAttribute( 'aria-describedby' );
	} else if ( 'focusout' === event.type || el.hasAttribute( 'aria-invalid' ) ) {
		el.setAttribute( 'aria-invalid', 'true' );
		el.setAttribute( 'aria-describedby', error.id );
	}
};
document.addEventListener( 'focusout', markWebAddress );
document.addEventListener( 'input', markWebAddress );

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

/**
 * Hold a job form's deadline inside the range the server keeps on save.
 *
 * Reads the selected board's maximum from the per-board map seeded by
 * JobDeadline::max_days() (empty for moderators: no maximum), then clamps the
 * day to [deadlineMin, deadlineMax]. Days are `Y-m-d` in the site's timezone,
 * so they compare as strings. Sets deadlineClamped when the day was moved so
 * the form can say why.
 *
 * @param {Object} state Job form state (deadline, deadlineMin, deadlineMax, boardDeadlineMax, boardId).
 * @param {string} day   Picked day; defaults to the current deadline.
 * @return {string} The day kept.
 */
export function fitDeadline( state, day = state.deadline ) {
	const maxes = state.boardDeadlineMax || {};
	const board = String( state.boardId || 0 );
	if ( board in maxes ) {
		state.deadlineMax = maxes[ board ];
	}
	let kept = day;
	if ( kept && state.deadlineMin && kept < state.deadlineMin ) {
		kept = state.deadlineMin;
	}
	if ( kept && state.deadlineMax && kept > state.deadlineMax ) {
		kept = state.deadlineMax;
	}
	state.deadlineClamped = kept !== day;
	state.deadline = kept;
	return kept;
}
