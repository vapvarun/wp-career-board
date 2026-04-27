/**
 * WP Career Board — shared frontend confirm-modal.
 *
 * Promise-based replacement for window.confirm() that frontend
 * Interactivity API blocks can call. Mirrors the admin-side modal
 * already shipped in assets/js/admin.js.
 *
 * Exposed as window.wcbConfirm({ title, message, confirmText, cancelText })
 * — resolves on confirm, rejects on cancel/ESC/overlay-click.
 *
 * Skill rule (admin-ux-rulebook §10): no browser confirm()/alert().
 *
 * @since 1.1.1
 */
( function () {
	'use strict';

	if ( window.wcbConfirm ) {
		return;
	}

	function defaultStrings() {
		var i18n = ( window.wcbConfirmI18n || {} );
		return {
			confirmText: i18n.confirm || 'Confirm',
			cancelText:  i18n.cancel  || 'Cancel',
		};
	}

	/**
	 * Open a styled confirm modal.
	 *
	 * @param {Object} opts
	 * @param {string} opts.title        Modal heading.
	 * @param {string} opts.message      Body text.
	 * @param {string} [opts.confirmText] Confirm button label.
	 * @param {string} [opts.cancelText]  Cancel button label.
	 * @param {boolean} [opts.destructive] Style confirm button as destructive (red).
	 * @returns {Promise<true>}
	 */
	window.wcbConfirm = function ( opts ) {
		opts = opts || {};
		var defaults = defaultStrings();

		return new Promise( function ( resolve, reject ) {
			var overlay = document.createElement( 'div' );
			overlay.className = 'wcb-modal-overlay';

			var box = document.createElement( 'div' );
			box.className = 'wcb-modal';
			box.setAttribute( 'role', 'dialog' );
			box.setAttribute( 'aria-modal', 'true' );
			box.setAttribute( 'aria-labelledby', 'wcb-confirm-title' );

			var title = document.createElement( 'h2' );
			title.id = 'wcb-confirm-title';
			title.className = 'wcb-modal-title';
			title.textContent = opts.title || '';

			var msg = document.createElement( 'p' );
			msg.className = 'wcb-modal-msg';
			msg.textContent = opts.message || '';

			box.appendChild( title );
			box.appendChild( msg );

			var actions = document.createElement( 'div' );
			actions.className = 'wcb-modal-actions';

			var cancelBtn = document.createElement( 'button' );
			cancelBtn.type = 'button';
			cancelBtn.className = 'wcb-btn wcb-modal-cancel';
			cancelBtn.textContent = opts.cancelText || defaults.cancelText;

			var confirmBtn = document.createElement( 'button' );
			confirmBtn.type = 'button';
			confirmBtn.className = 'wcb-btn ' + ( opts.destructive ? 'wcb-btn--danger' : 'wcb-btn--primary' ) + ' wcb-modal-confirm';
			confirmBtn.textContent = opts.confirmText || defaults.confirmText;

			actions.appendChild( cancelBtn );
			actions.appendChild( confirmBtn );
			box.appendChild( actions );
			overlay.appendChild( box );
			document.body.appendChild( overlay );

			// Focus trap — focus confirm initially, cycle between cancel/confirm only.
			confirmBtn.focus();

			function close() {
				document.removeEventListener( 'keydown', onKey );
				if ( overlay.parentNode ) {
					overlay.parentNode.removeChild( overlay );
				}
			}

			function onKey( e ) {
				if ( 'Escape' === e.key ) {
					close();
					reject();
					return;
				}
				if ( 'Tab' === e.key ) {
					var first   = cancelBtn;
					var last    = confirmBtn;
					var current = document.activeElement;
					if ( e.shiftKey && current === first ) {
						e.preventDefault();
						last.focus();
					} else if ( ! e.shiftKey && current === last ) {
						e.preventDefault();
						first.focus();
					}
				}
			}
			document.addEventListener( 'keydown', onKey );

			cancelBtn.addEventListener( 'click', function () {
				close();
				reject();
			} );

			overlay.addEventListener( 'click', function ( e ) {
				if ( e.target === overlay ) {
					close();
					reject();
				}
			} );

			confirmBtn.addEventListener( 'click', function () {
				close();
				resolve( true );
			} );
		} );
	};
} )();
