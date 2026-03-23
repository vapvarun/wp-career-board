/**
 * WP Career Board — Google reCAPTCHA v3 integration.
 *
 * Defines window.wcbCaptchaGetToken() used by the job-form and job-single
 * Interactivity API stores before fetch submissions.
 *
 * Each call to wcbCaptchaGetToken() executes a fresh challenge — reCAPTCHA v3
 * tokens are single-use, so no reset is needed.
 *
 * @package WP_Career_Board
 */
( function () {
	if ( typeof window.wcbAntispam === 'undefined' || window.wcbAntispam.provider !== 'recaptcha' ) {
		return;
	}

	var siteKey = window.wcbAntispam.siteKey;

	/**
	 * Execute a reCAPTCHA v3 challenge and return a Promise that resolves with
	 * the token string (empty string on error or if grecaptcha is unavailable).
	 *
	 * @returns {Promise<string>}
	 */
	window.wcbCaptchaGetToken = function () {
		return new Promise( function ( resolve ) {
			if ( typeof grecaptcha === 'undefined' || ! siteKey ) {
				resolve( '' );
				return;
			}
			grecaptcha.ready( function () {
				grecaptcha
					.execute( siteKey, { action: 'wcb_submit' } )
					.then( resolve )
					.catch( function () {
						resolve( '' );
					} );
			} );
		} );
	};

	// No-op — reCAPTCHA v3 tokens are single-use; each execute() call gets a fresh one.
	window.wcbCaptchaReset = function () {};
} )();
