/**
 * WP Career Board — Cloudflare Turnstile integration.
 *
 * Defines window.wcbCaptchaGetToken() and window.wcbCaptchaReset(), the same
 * contract as the reCAPTCHA shims, used by the job forms, the apply panel and
 * employer registration before their fetch submissions.
 *
 * Turnstile has no invisible size: the widget mode (Managed, Non-interactive,
 * Invisible) is chosen on the site key in the Cloudflare dashboard. So the
 * widget renders with appearance "interaction-only" inside a small panel that
 * stays hidden while Cloudflare passes the visitor silently, and opens only
 * when Cloudflare asks for a click. A visitor who never gets a token (closed
 * panel, blocked script, silent stall) is let go with an empty token, so the
 * server answers "CAPTCHA verification failed" instead of the button
 * spinning forever.
 *
 * @package WP_Career_Board
 */
( function () {
	if ( typeof window.wcbAntispam === 'undefined' || window.wcbAntispam.provider !== 'turnstile' ) {
		return;
	}

	var config   = window.wcbAntispam;
	var i18n     = config.i18n || {};
	var widgetId = null;
	var pending  = null;
	var timer    = null;
	var panel    = null;

	// How long a silent check may take before the submit gives up. A visible
	// challenge has no limit of ours: Turnstile's own timeout-callback ends it.
	var SILENT_WAIT_MS = 30000;

	function show( visible ) {
		if ( panel ) {
			panel.hidden = ! visible;
		}
	}

	function settle( token ) {
		if ( timer ) {
			clearTimeout( timer );
			timer = null;
		}
		show( false );
		if ( pending ) {
			pending( token );
			pending = null;
		}
	}

	function buildPanel() {
		panel           = document.createElement( 'div' );
		panel.className = 'wcb-card wcb-captcha-challenge';
		panel.hidden    = true;
		panel.setAttribute( 'role', 'dialog' );
		panel.setAttribute( 'aria-labelledby', 'wcb-captcha-challenge-title' );

		var title         = document.createElement( 'p' );
		title.id          = 'wcb-captcha-challenge-title';
		title.className   = 'wcb-captcha-challenge__title';
		title.textContent = i18n.title || 'Please confirm you are human to continue.';

		var widget       = document.createElement( 'div' );
		widget.className = 'wcb-captcha-challenge__widget';

		var cancel         = document.createElement( 'button' );
		cancel.type        = 'button';
		cancel.className   = 'wcb-btn wcb-btn--secondary wcb-captcha-challenge__cancel';
		cancel.textContent = i18n.cancel || 'Cancel';
		cancel.addEventListener( 'click', function () {
			settle( '' );
			if ( widgetId !== null ) {
				turnstile.reset( widgetId );
			}
		} );

		panel.appendChild( title );
		panel.appendChild( widget );
		panel.appendChild( cancel );
		document.body.appendChild( panel );

		return widget;
	}

	function init() {
		if ( typeof turnstile === 'undefined' || ! config.siteKey || widgetId !== null ) {
			return;
		}

		widgetId = turnstile.render( buildPanel(), {
			sitekey:                       config.siteKey,
			appearance:                    'interaction-only',
			execution:                     'execute',
			size:                          'flexible',
			callback:                      settle,
			'error-callback':              function () {
				settle( '' );
			},
			'timeout-callback':            function () {
				settle( '' );
			},
			'before-interactive-callback': function () {
				// The visitor is now looking at the challenge: no silent limit.
				if ( timer ) {
					clearTimeout( timer );
					timer = null;
				}
				show( true );
			},
			'after-interactive-callback':  function () {
				show( false );
			},
		} );
	}

	/**
	 * Run the challenge and resolve with the token ('' on error, cancel,
	 * timeout or before init).
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
			timer   = setTimeout( function () {
				settle( '' );
			}, SILENT_WAIT_MS );
			// A token is single-use; reset so a retry after a failed submit
			// runs a fresh check instead of never calling back.
			turnstile.reset( widgetId );
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
