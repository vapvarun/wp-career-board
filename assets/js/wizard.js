/* global wp, wcbWizard */
/**
 * WP Career Board — setup wizard frontend.
 *
 * Drives a dynamic multi-step wizard. Step count is read from wcbWizard.totalSteps
 * (localized by SetupWizard::enqueue_wizard_assets).
 *
 * Step handlers (in this file or in add-on scripts like pro-wizard.js) perform
 * their work and then dispatch a 'wcb-wizard-step-complete' CustomEvent on
 * #wcb-wizard-steps. This script catches the event, advances to the next step,
 * and calls /wcb/v1/wizard/complete on the final step.
 *
 * @since 1.0.0
 */
( function () {
	'use strict';

	var container  = document.getElementById( 'wcb-wizard-steps' );
	var totalSteps = wcbWizard.totalSteps || 1;

	/* ------------------------------------------------------------------ */
	/* Generic step navigation                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Show the given step and hide all others.
	 *
	 * @param {number} step 1-indexed step number to show.
	 */
	function showStep( step ) {
		var all = container.querySelectorAll( '.wcb-wizard-step' );
		var i;
		for ( i = 0; i < all.length; i++ ) {
			all[ i ].classList.remove( 'active' );
		}
		var target = container.querySelector( '.wcb-wizard-step[data-step="' + step + '"]' );
		if ( target ) {
			target.classList.add( 'active' );
		}
	}

	/**
	 * Complete the wizard — POST /complete and redirect.
	 */
	function completeWizard() {
		wp.apiFetch( {
			url: wcbWizard.restUrl + '/complete',
			method: 'POST',
		} ).then( function ( response ) {
			if ( response && response.redirect ) {
				window.location.href = response.redirect;
			}
		} );
	}

	// Listen for step-complete events from any step handler.
	if ( container ) {
		container.addEventListener( 'wcb-wizard-step-complete', function ( e ) {
			var completedStep = e.detail && e.detail.step ? e.detail.step : 1;
			var nextStep      = completedStep + 1;

			if ( nextStep > totalSteps ) {
				completeWizard();
			} else {
				showStep( nextStep );
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Free step handlers                                                  */
	/* ------------------------------------------------------------------ */

	var createPagesBtn = document.getElementById( 'wcb-create-pages' );
	var finishBtn      = document.getElementById( 'wcb-finish-wizard' );
	var installSample  = document.getElementById( 'wcb-install-sample' );

	/**
	 * Dispatch the step-complete event.
	 *
	 * @param {number} step 1-indexed step number that just finished.
	 */
	function dispatchComplete( step ) {
		container.dispatchEvent(
			new CustomEvent( 'wcb-wizard-step-complete', { detail: { step: step } } )
		);
	}

	/**
	 * Get the 1-indexed step number for the element's parent wizard step.
	 *
	 * @param {Element} el Any element inside a .wcb-wizard-step.
	 * @return {number}
	 */
	function getStepNum( el ) {
		var stepEl = el.closest( '.wcb-wizard-step' );
		return stepEl ? parseInt( stepEl.getAttribute( 'data-step' ), 10 ) : 1;
	}

	// Step: Create Pages.
	if ( createPagesBtn ) {
		createPagesBtn.addEventListener( 'click', function () {
			var stepNum = getStepNum( createPagesBtn );
			createPagesBtn.disabled = true;

			wp.apiFetch( {
				url: wcbWizard.restUrl + '/create-pages',
				method: 'POST',
			} ).then( function () {
				dispatchComplete( stepNum );
			} ).catch( function () {
				createPagesBtn.disabled = false;
			} );
		} );
	}

	// Step: Sample Data.
	if ( finishBtn ) {
		finishBtn.addEventListener( 'click', function () {
			var stepNum   = getStepNum( finishBtn );
			var doInstall = installSample && installSample.checked ? 1 : 0;
			finishBtn.disabled = true;

			wp.apiFetch( {
				url: wcbWizard.restUrl + '/sample-data',
				method: 'POST',
				data: { install_sample: doInstall },
			} ).then( function () {
				dispatchComplete( stepNum );
			} ).catch( function () {
				finishBtn.disabled = false;
			} );
		} );
	}
}() );
