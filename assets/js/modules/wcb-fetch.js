/**
 * WP Career Board — shared fetch helper for Interactivity API view modules.
 *
 * ONE implementation, imported uniformly by every Free and Pro block. Free is
 * always active alongside Pro, so this module lives in Free and Pro imports the
 * same `@wcb/fetch` id (no duplicate fetch logic per plugin).
 *
 * `wcbFetch` is a drop-in for the native `fetch`: same signature, same
 * `Promise<Response>` return — call sites only swap the function name. It adds
 * an AbortController timeout so a stalled network can never hang the UI, and
 * composes any caller-supplied `signal` with that timeout.
 *
 * @module @wcb/fetch
 */

/**
 * Default request timeout in milliseconds. Same-origin REST calls that take
 * longer than this are almost certainly a stalled connection, not real work.
 */
const WCB_FETCH_TIMEOUT_MS = 15000;

/**
 * Fetch with an automatic abort-on-timeout. Drop-in replacement for `fetch`.
 *
 * @param {string}      url                 Request URL.
 * @param {Object}      [options]           Standard fetch init, plus:
 * @param {number}      [options.timeout]   Abort after N ms (default 15000).
 * @param {AbortSignal} [options.signal]    Caller signal, composed with the timeout.
 * @return {Promise<Response>} The fetch Response (identical to native fetch).
 */
export function wcbFetch( url, options = {} ) {
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
