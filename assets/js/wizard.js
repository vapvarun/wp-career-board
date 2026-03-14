/* global wp, wcbWizard */
/**
 * WP Career Board — setup wizard frontend.
 *
 * Uses wp.apiFetch (wp-api-fetch) to call:
 *   POST /wcb/v1/wizard/create-pages
 *   POST /wcb/v1/wizard/sample-data
 *   POST /wcb/v1/wizard/complete
 *
 * wcbWizard.restUrl is localized by SetupWizard::enqueue_wizard_assets().
 */
( function () {
	'use strict';

	var createPagesBtn = document.getElementById( 'wcb-create-pages' );
	var finishBtn      = document.getElementById( 'wcb-finish-wizard' );
	var installSample  = document.getElementById( 'wcb-install-sample' );

	/**
	 * Show the given step and hide all others.
	 *
	 * @param {number} step Step number to show.
	 */
	function showStep( step ) {
		var all = document.querySelectorAll( '.wcb-wizard-step' );
		var i;
		for ( i = 0; i < all.length; i++ ) {
			all[ i ].classList.remove( 'active' );
		}
		var target = document.querySelector( '.wcb-wizard-step[data-step="' + step + '"]' );
		if ( target ) {
			target.classList.add( 'active' );
		}
	}

	if ( createPagesBtn ) {
		createPagesBtn.addEventListener( 'click', function () {
			createPagesBtn.disabled = true;

			wp.apiFetch( {
				url: wcbWizard.restUrl + 'create-pages',
				method: 'POST',
			} ).then( function () {
				showStep( 2 );
			} ).catch( function () {
				createPagesBtn.disabled = false;
			} );
		} );
	}

	if ( finishBtn ) {
		finishBtn.addEventListener( 'click', function () {
			finishBtn.disabled = true;
			var doInstall = installSample && installSample.checked ? 1 : 0;

			wp.apiFetch( {
				url: wcbWizard.restUrl + 'sample-data',
				method: 'POST',
				data: { install_sample: doInstall },
			} ).then( function () {
				return wp.apiFetch( {
					url: wcbWizard.restUrl + 'complete',
					method: 'POST',
				} );
			} ).then( function ( response ) {
				if ( response && response.redirect ) {
					window.location.href = response.redirect;
				}
			} ).catch( function () {
				finishBtn.disabled = false;
			} );
		} );
	}
}() );
