/* WP Career Board — Admin JS */
( function () {
	'use strict';

	// -------------------------------------------------------------------------
	// Modal utility — promise-based, replaces window.confirm / window.prompt
	// -------------------------------------------------------------------------

	/**
	 * Open a styled confirm modal.
	 *
	 * @param {Object}  opts
	 * @param {string}  opts.title        Modal heading.
	 * @param {string}  opts.message      Body text.
	 * @param {string}  [opts.confirmText] Label for the confirm button.
	 * @param {boolean} [opts.withReason]  Show a textarea; resolves with its value.
	 * @param {string}  [opts.reasonLabel] Label above the textarea.
	 * @returns {Promise<string|boolean>} Resolves on confirm, rejects on cancel.
	 */
	function openModal( opts ) {
		return new Promise( function ( resolve, reject ) {
			var overlay = document.createElement( 'div' );
			overlay.className = 'wcb-modal-overlay';

			var box = document.createElement( 'div' );
			box.className = 'wcb-modal';
			box.setAttribute( 'role', 'dialog' );
			box.setAttribute( 'aria-modal', 'true' );
			box.setAttribute( 'aria-labelledby', 'wcb-modal-title' );

			var title = document.createElement( 'h2' );
			title.id = 'wcb-modal-title';
			title.className = 'wcb-modal-title';
			title.textContent = opts.title || '';

			var msg = document.createElement( 'p' );
			msg.className = 'wcb-modal-msg';
			msg.textContent = opts.message || '';

			box.appendChild( title );
			box.appendChild( msg );

			var textarea;
			if ( opts.withReason ) {
				var lbl = document.createElement( 'label' );
				lbl.className = 'wcb-modal-label';
				lbl.textContent = opts.reasonLabel || wcbAdmin.i18n.reasonLabel;
				textarea = document.createElement( 'textarea' );
				textarea.className = 'wcb-modal-textarea';
				textarea.rows = 3;
				box.appendChild( lbl );
				box.appendChild( textarea );
			}

			var actions = document.createElement( 'div' );
			actions.className = 'wcb-modal-actions';

			var cancelBtn = document.createElement( 'button' );
			cancelBtn.type = 'button';
			cancelBtn.className = 'button wcb-modal-cancel';
			cancelBtn.textContent = wcbAdmin.i18n.cancel;

			var confirmBtn = document.createElement( 'button' );
			confirmBtn.type = 'button';
			confirmBtn.className = 'button button-primary wcb-modal-confirm';
			confirmBtn.textContent = opts.confirmText || wcbAdmin.i18n.confirm;

			actions.appendChild( cancelBtn );
			actions.appendChild( confirmBtn );
			box.appendChild( actions );
			overlay.appendChild( box );
			document.body.appendChild( overlay );

			( textarea || confirmBtn ).focus();

			function close() {
				document.body.removeChild( overlay );
			}

			function handleKey( e ) {
				if ( e.key === 'Escape' ) {
					document.removeEventListener( 'keydown', handleKey );
					close();
					reject();
				}
			}
			document.addEventListener( 'keydown', handleKey );

			cancelBtn.addEventListener( 'click', function () {
				document.removeEventListener( 'keydown', handleKey );
				close();
				reject();
			} );

			overlay.addEventListener( 'click', function ( e ) {
				if ( e.target === overlay ) {
					document.removeEventListener( 'keydown', handleKey );
					close();
					reject();
				}
			} );

			confirmBtn.addEventListener( 'click', function () {
				document.removeEventListener( 'keydown', handleKey );
				close();
				resolve( opts.withReason ? ( textarea ? textarea.value.trim() : '' ) : true );
			} );
		} );
	}

	// -------------------------------------------------------------------------
	// Application status selects — T22d: loading state during REST call
	// -------------------------------------------------------------------------

	function initStatusSelects() {
		var selects = document.querySelectorAll( '.wcb-status-select[data-app-id]' );
		selects.forEach( function ( select ) {
			select.dataset.original = select.value;

			select.addEventListener( 'change', function () {
				var appId  = select.dataset.appId;
				var status = select.value;

				select.disabled = true;

				wp.apiFetch( {
					path:   '/wcb/v1/applications/' + appId + '/status',
					method: 'PATCH',
					data:   { status: status },
				} ).then( function () {
					select.disabled = false;
					select.dataset.original = status;

					var badge = select.closest( 'tr' ).querySelector( '.wcb-status-badge' );
					if ( badge ) {
						badge.className  = 'wcb-status-badge wcb-status-' + status;
						badge.textContent = status.charAt( 0 ).toUpperCase() + status.slice( 1 );
					}
				} ).catch( function () {
					select.disabled = false;
					select.value = select.dataset.original;
					alert( wcbAdmin.i18n.saveFailed );
				} );
			} );
		} );
	}

	// -------------------------------------------------------------------------
	// Job moderation — T22a: styled modal + rejection reason
	// -------------------------------------------------------------------------

	function initJobModeration() {
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.wcb-approve-job, .wcb-reject-job' );
			if ( ! btn ) {
				return;
			}

			var jobId    = btn.dataset.jobId;
			var isReject = btn.classList.contains( 'wcb-reject-job' );
			var action   = isReject ? 'reject' : 'approve';

			var modalOpts = isReject
				? {
					title:       wcbAdmin.i18n.rejectTitle,
					message:     wcbAdmin.i18n.rejectMsg,
					confirmText: wcbAdmin.i18n.rejectBtn,
					withReason:  true,
					reasonLabel: wcbAdmin.i18n.reasonLabel,
				}
				: {
					title:       wcbAdmin.i18n.approveTitle,
					message:     wcbAdmin.i18n.approveMsg,
					confirmText: wcbAdmin.i18n.approveBtn,
				};

			openModal( modalOpts ).then( function ( result ) {
				btn.disabled = true;

				var data = {};
				if ( isReject && result ) {
					data.reason = result;
				}

				return wp.apiFetch( {
					path:   '/wcb/v1/jobs/' + jobId + '/' + action,
					method: 'POST',
					data:   data,
				} );
			} ).then( function () {
				var row = btn.closest( 'tr' );
				if ( row ) {
					row.style.opacity = '0.4';
					setTimeout( function () { row.remove(); }, 600 );
				}
			} ).catch( function ( err ) {
				if ( err === undefined ) {
					return; // User cancelled.
				}
				btn.disabled = false;
				alert( wcbAdmin.i18n.saveFailed );
			} );
		} );
	}

	// -------------------------------------------------------------------------
	// Company trust level selects — T22c
	// -------------------------------------------------------------------------

	function initTrustSelects() {
		document.addEventListener( 'change', function ( e ) {
			var select = e.target.closest( '.wcb-trust-select[data-company-id]' );
			if ( ! select ) {
				return;
			}

			var companyId = select.dataset.companyId;
			var level     = select.value;

			select.disabled = true;

			wp.apiFetch( {
				path:   '/wcb/v1/companies/' + companyId + '/trust',
				method: 'POST',
				data:   { trust_level: level },
			} ).then( function () {
				select.disabled = false;
				select.dataset.original = level;
			} ).catch( function () {
				select.disabled = false;
				select.value = select.dataset.original || '';
				alert( wcbAdmin.i18n.saveFailed );
			} );

			select.dataset.original = level;
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initStatusSelects();
		initJobModeration();
		initTrustSelects();
	} );
}() );
