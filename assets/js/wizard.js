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
		maxReached = Math.max( maxReached, step );
		updateProgress( step );
	}

	var progress   = document.getElementById( 'wcb-wizard-progress' );
	var maxReached = 1;

	/**
	 * Mark done / current steps in the stepper; reached steps can be revisited.
	 *
	 * @param {number} step 1-indexed current step.
	 */
	function updateProgress( step ) {
		if ( ! progress ) {
			return;
		}
		var items = progress.querySelectorAll( 'li' );
		var i;
		var n;
		for ( i = 0; i < items.length; i++ ) {
			n = parseInt( items[ i ].getAttribute( 'data-step' ), 10 );
			items[ i ].classList.toggle( 'is-current', n === step );
			items[ i ].classList.toggle( 'is-done', n < maxReached && n !== step );
			if ( n === step ) {
				items[ i ].setAttribute( 'aria-current', 'step' );
			} else {
				items[ i ].removeAttribute( 'aria-current' );
			}
			items[ i ].querySelector( 'button' ).disabled = n > maxReached;
		}
	}

	if ( progress ) {
		progress.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-wcb-wizard-goto]' );
			if ( btn && ! btn.disabled ) {
				showStep( parseInt( btn.getAttribute( 'data-wcb-wizard-goto' ), 10 ) );
			}
		} );
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
			} ).catch( function ( err ) {
				createPagesBtn.disabled = false;
				showError( createPagesBtn, ( err && err.message ) || wcbWizard.i18n.saveFailed );
			} );
		} );
	}

	/**
	 * Show an error under the step's buttons.
	 *
	 * @param {Element} el      Any element inside the step.
	 * @param {string}  message Message; empty clears it.
	 */
	function showError( el, message ) {
		var slot = el.closest( '.wcb-wizard-step' ).querySelector( '.wcb-wizard-error' );
		if ( slot ) {
			slot.textContent = message;
		}
	}

	/**
	 * Collect a step's answers: checkboxes as 1/0, everything else as typed.
	 *
	 * @param {Element} stepEl The .wcb-wizard-step element.
	 * @return {Object}
	 */
	function collect( stepEl ) {
		var data   = {};
		var fields = stepEl.querySelectorAll( 'input[name], select[name], textarea[name]' );
		var i;
		for ( i = 0; i < fields.length; i++ ) {
			data[ fields[ i ].name ] = 'checkbox' === fields[ i ].type ? ( fields[ i ].checked ? 1 : 0 ) : fields[ i ].value;
		}
		return data;
	}

	// Settings steps: Save & Continue / Skip for now.
	container.addEventListener( 'click', function ( e ) {
		var save = e.target.closest( '[data-wcb-wizard-save]' );
		var skip = e.target.closest( '[data-wcb-wizard-skip]' );

		if ( skip ) {
			dispatchComplete( getStepNum( skip ) );
			return;
		}
		if ( ! save ) {
			return;
		}

		var stepNum = getStepNum( save );
		save.disabled = true;
		showError( save, '' );

		wp.apiFetch( {
			url: wcbWizard.restUrl + '/settings',
			method: 'POST',
			data: { settings: collect( save.closest( '.wcb-wizard-step' ) ) },
		} ).then( function () {
			save.disabled = false;
			dispatchComplete( stepNum );
		} ).catch( function ( err ) {
			save.disabled = false;
			showError( save, ( err && err.message ) || wcbWizard.i18n.saveFailed );
		} );
	} );

	// CAPTCHA step: show only the chosen provider's key fields.
	container.addEventListener( 'change', function ( e ) {
		if ( ! e.target.matches( '[data-wcb-captcha-provider]' ) ) {
			return;
		}
		var groups = container.querySelectorAll( '[data-wcb-provider-fields]' );
		var i;
		for ( i = 0; i < groups.length; i++ ) {
			groups[ i ].hidden = groups[ i ].getAttribute( 'data-wcb-provider-fields' ) !== e.target.value;
		}
	} );

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
