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
