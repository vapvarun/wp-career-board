/**
 * WP Career Board — Cloudflare Turnstile integration.
 *
 * Defines window.wcbCaptchaGetToken() and window.wcbCaptchaReset() used by
 * the job-form and job-single Interactivity API stores before fetch submissions.
 *
 * Uses the invisible execution model: the widget is rendered off-screen once
 * on page load and executed programmatically on each submit. After consuming
 * a token the widget is reset so it can issue a fresh token on the next submit.
 *
 * @package WP_Career_Board
 */
( function () {
	if ( typeof window.wcbAntispam === 'undefined' || window.wcbAntispam.provider !== 'turnstile' ) {
		return;
	}

	var siteKey  = window.wcbAntispam.siteKey;
	var widgetId = null;
	var pending  = null;

	function init() {
		if ( typeof turnstile === 'undefined' || ! siteKey ) {
			return;
		}

		var container            = document.createElement( 'div' );
		container.style.cssText  = 'position:absolute;left:-9999px;top:-9999px;';
		document.body.appendChild( container );

		widgetId = turnstile.render( container, {
			sitekey:            siteKey,
			size:               'invisible',
			execution:          'execute',
			callback:           function ( token ) {
				if ( pending ) {
					pending( token );
					pending = null;
				}
			},
			'error-callback':   function () {
				if ( pending ) {
					pending( '' );
					pending = null;
				}
			},
			'expired-callback': function () {
				if ( widgetId !== null ) {
					turnstile.reset( widgetId );
				}
			},
		} );
	}

	/**
	 * Execute the Turnstile challenge and return a Promise that resolves with
	 * the token string (empty string on error or if not initialised).
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
			turnstile.execute( widgetId );
		} );
	};

	/**
	 * Reset the widget after a token has been consumed so the next submit can
	 * obtain a fresh token.
	 */
	window.wcbCaptchaReset = function () {
		if ( widgetId !== null ) {
			turnstile.reset( widgetId );
		}
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			if ( typeof turnstile !== 'undefined' ) {
				turnstile.ready( init );
			}
		} );
	} else if ( typeof turnstile !== 'undefined' ) {
		turnstile.ready( init );
	}
} )();
