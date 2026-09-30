/**
 * WP Career Board - Google reCAPTCHA v2 (invisible badge) integration.
 *
 * Defines window.wcbCaptchaGetToken() and window.wcbCaptchaReset(), the same
 * contract as the Turnstile and v3 shims. The widget is rendered once with
 * the bottom-right badge Google's terms require; each submit executes it,
 * and Google shows a picture challenge only to suspicious visitors.
 *
 * @package WP_Career_Board
 */
( function () {
	if ( typeof window.wcbAntispam === 'undefined' || window.wcbAntispam.provider !== 'recaptcha_v2' ) {
		return;
	}

	var siteKey  = window.wcbAntispam.siteKey;
	var widgetId = null;
	var pending  = null;

	function settle( token ) {
		if ( pending ) {
			pending( token );
			pending = null;
		}
	}

	function init() {
		if ( typeof grecaptcha === 'undefined' || ! siteKey || widgetId !== null ) {
			return;
		}
		var container = document.createElement( 'div' );
		document.body.appendChild( container );

		widgetId = grecaptcha.render( container, {
			sitekey: siteKey,
			size: 'invisible',
			badge: 'bottomright',
			callback: settle,
			'error-callback': function () {
				settle( '' );
			},
			'expired-callback': function () {
				grecaptcha.reset( widgetId );
			},
		} );
	}

	/**
	 * Run the challenge; resolves with the token ('' on error or before init).
	 * A visitor who closes the picture challenge never gets a token, so the
	 * submit simply waits until they complete it or try again.
	 *
	 * @returns {Promise<string>}
	 */
	window.wcbCaptchaGetToken = function () {
		return new Promise( function ( resolve ) {
			if ( widgetId === null ) {
				resolve( '' );
				return;
			}
			pending = resolve;
			// A v2 token is single-use; reset so a retry after a failed submit
			// gets a fresh one instead of no callback at all.
			grecaptcha.reset( widgetId );
			grecaptcha.execute( widgetId );
		} );
	};

	window.wcbCaptchaReset = function () {
		if ( widgetId !== null ) {
			grecaptcha.reset( widgetId );
		}
	};

	var tries = 0;

	// The API script is deferred; wait for it for up to ~10s (blocked by an
	// ad blocker = give up, and submits go out without a token).
	function boot() {
		if ( typeof grecaptcha !== 'undefined' && grecaptcha.render ) {
			init();
		} else if ( ++tries < 50 ) {
			window.setTimeout( boot, 200 );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
