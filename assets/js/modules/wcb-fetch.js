/**
 * WP Career Board — shared fetch helper for Interactivity API view modules.
 *
 * ONE implementation, imported uniformly by every Free and Pro block. Free is
 * always active alongside Pro, so this module lives in Free and Pro imports the
 * same `@wcb/fetch` id (no duplicate fetch logic per plugin).
 *
 * `wcbFetch` is a drop-in for the native `fetch`: same signature, same
 * `Promise<Response>` return — call sites only swap the function name. It adds
 * an AbortController timeout so a stalled network can never hang the UI,
 * composes any caller-supplied `signal` with that timeout, and renews an
 * expired REST nonce once.
 *
 * @module @wcb/fetch
 */

/**
 * Default request timeout in milliseconds. Same-origin REST calls that take
 * longer than this are almost certainly a stalled connection, not real work.
 */
const WCB_FETCH_TIMEOUT_MS = 15000;

/**
 * REST nonce renewed during this page view. Once one call has renewed it, later
 * calls send it instead of the stale copy the page was rendered with.
 */
let renewedNonce = '';

/**
 * Data core prints for this module (`script_module_data_@wcb/fetch`).
 *
 * @return {Object} Module data, `{ ajaxUrl }`.
 */
function moduleData() {
	try {
		return JSON.parse( document.getElementById( 'wp-script-module-data-@wcb/fetch' )?.textContent || '{}' );
	} catch {
		return {};
	}
}

/**
 * A fresh `wp_rest` nonce from core's `rest-nonce` admin-ajax action.
 * Core registers it for signed-in members only, so a signed-out member gets
 * no nonce and the caller sees the original 403.
 *
 * @return {Promise<string>} The nonce, or '' when the member is signed out.
 */
async function renewNonce() {
	const { ajaxUrl } = moduleData();
	if ( ! ajaxUrl ) {
		return '';
	}
	try {
		const response = await fetch( ajaxUrl + '?action=rest-nonce', { credentials: 'same-origin' } );
		const nonce    = response.ok ? ( await response.text() ).trim() : '';
		return /^[a-f0-9]{10}$/.test( nonce ) ? nonce : '';
	} catch {
		return '';
	}
}

/**
 * The init's headers with X-WP-Nonce replaced, or null when it sends none.
 *
 * @param {HeadersInit} headers Caller headers.
 * @param {string}      nonce   Replacement nonce.
 * @return {Headers|null} New headers, or null.
 */
function withNonce( headers, nonce ) {
	const copy = new Headers( headers || {} );
	if ( ! copy.has( 'X-WP-Nonce' ) ) {
		return null;
	}
	copy.set( 'X-WP-Nonce', nonce );
	return copy;
}

/**
 * Fetch with an automatic abort-on-timeout. Drop-in replacement for `fetch`.
 *
 * A REST nonce lasts 12-24 hours, so a form left open past that (or kept
 * across a new sign-in in another tab) was refused with
 * `rest_cookie_invalid_nonce` while the member was still signed in. On that
 * refusal the nonce is renewed once and the request sent again, so the member
 * never loses what they typed. A member who really is signed out gets the
 * original 403 back for the caller to explain.
 *
 * @param {string}      url                 Request URL.
 * @param {Object}      [options]           Standard fetch init, plus:
 * @param {number}      [options.timeout]   Abort after N ms (default 15000).
 * @param {AbortSignal} [options.signal]    Caller signal, composed with the timeout.
 * @return {Promise<Response>} The fetch Response (identical to native fetch).
 */
export async function wcbFetch( url, options = {} ) {
	const headers = renewedNonce ? withNonce( options.headers, renewedNonce ) : null;
	const init    = headers ? { ...options, headers } : options;
	const response = await send( url, init );

	if ( 403 !== response.status || ! withNonce( init.headers, '' ) ) {
		return response;
	}
	const error = await response.clone().json().catch( () => null );
	if ( ! error || 'rest_cookie_invalid_nonce' !== error.code ) {
		return response;
	}
	const nonce = await renewNonce();
	if ( ! nonce ) {
		return response;
	}
	renewedNonce = nonce;
	return send( url, { ...options, headers: withNonce( options.headers, nonce ) } );
}

/**
 * One fetch with the timeout and caller signal composed.
 *
 * @param {string} url     Request URL.
 * @param {Object} options Fetch init plus `timeout`.
 * @return {Promise<Response>} The fetch Response.
 */
function send( url, options ) {
	const { timeout = WCB_FETCH_TIMEOUT_MS, signal, ...rest } = options;

	const controller = new AbortController();
	const timer = setTimeout( () => controller.abort(), timeout );

	// Compose the caller's signal (if any) with our timeout signal so either
	// can abort the request.
	if ( signal ) {
		if ( signal.aborted ) {
			controller.abort();
		} else {
			signal.addEventListener( 'abort', () => controller.abort(), { once: true } );
		}
	}

	return fetch( url, { ...rest, signal: controller.signal } ).finally( () => {
		clearTimeout( timer );
	} );
}
