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
			cancelBtn.className = 'wcb-btn wcb-modal-cancel';
			cancelBtn.textContent = wcbAdmin.i18n.cancel;

			var confirmBtn = document.createElement( 'button' );
			confirmBtn.type = 'button';
			confirmBtn.className = 'wcb-btn wcb-btn--primary wcb-modal-confirm';
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

					var badge = select.closest( 'tr' ).querySelector( '.wcb-badge' );
					if ( badge ) {
						var badgeMap = { submitted: 'info', reviewing: 'warn', shortlisted: 'success', rejected: 'danger', hired: 'success' };
						badge.className  = 'wcb-badge wcb-badge--' + ( badgeMap[ status ] || 'default' );
						badge.textContent = status.charAt( 0 ).toUpperCase() + status.slice( 1 );
					}
				} ).catch( function () {
					select.disabled = false;
					select.value = select.dataset.original;
					wcbToast( wcbAdmin.i18n.saveFailed, 'error' );
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
				wcbToast( wcbAdmin.i18n.saveFailed, 'error' );
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
				wcbToast( wcbAdmin.i18n.saveFailed, 'error' );
			} );

			select.dataset.original = level;
		} );
	}

	// -------------------------------------------------------------------------
	// Dashboard panel toggles — persist collapsed state to localStorage
	// -------------------------------------------------------------------------

	function initPanelToggles() {
		var STORAGE_KEY = 'wcb_panel_state';

		function getState() {
			try { return JSON.parse( localStorage.getItem( STORAGE_KEY ) || '{}' ); } catch ( e ) { return {}; }
		}

		function saveState( state ) {
			localStorage.setItem( STORAGE_KEY, JSON.stringify( state ) );
		}

		document.querySelectorAll( '.wcb-panel-header[data-panel]' ).forEach( function ( header ) {
			var panelId = header.dataset.panel;
			var panel   = header.closest( '.wcb-dashboard-panel' );
			var toggle  = header.querySelector( '.wcb-panel-toggle' );

			if ( getState()[ panelId ] === 'collapsed' ) {
				panel.classList.add( 'is-collapsed' );
				if ( toggle ) { toggle.setAttribute( 'aria-expanded', 'false' ); }
			} else if ( toggle ) {
				toggle.setAttribute( 'aria-expanded', 'true' );
			}

			header.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( 'a' ) ) { return; }
				panel.classList.toggle( 'is-collapsed' );
				var collapsed = panel.classList.contains( 'is-collapsed' );
				if ( toggle ) { toggle.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' ); }
				var s = getState();
				s[ panelId ] = collapsed ? 'collapsed' : 'open';
				saveState( s );
			} );
		} );
	}

	// -------------------------------------------------------------------------
	// Import page — batch migration with live progress bar
	// -------------------------------------------------------------------------

	function initImportPage() {
		var page = document.querySelector( '.wcb-admin-import' );
		if ( ! page ) {
			return;
		}

		page.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.wcb-import-start' );
			if ( ! btn || btn.disabled ) {
				return;
			}

			var type         = btn.dataset.type;
			var card         = btn.closest( '.wcb-import-card' );
			if ( ! card ) { return; }

			var total        = parseInt( card.dataset.total, 10 ) || 0;
			var progressWrap = card.querySelector( '.wcb-import-progress-wrap' );
			var fill         = card.querySelector( '.wcb-import-progress-bar-fill' );
			var label        = card.querySelector( '.wcb-import-progress-label' );
			var log          = card.querySelector( '.wcb-import-log' );

			btn.disabled    = true;
			btn.textContent = 'Importing\u2026';

			if ( progressWrap ) { progressWrap.style.display = 'block'; }
			if ( log )          { log.style.display = 'block'; while ( log.firstChild ) { log.removeChild( log.firstChild ); } }

			var imported = 0;
			var skipped  = 0;
			var errors   = 0;
			var offset   = 0;
			var limit    = 20;

			function appendLog( text, extraClass ) {
				if ( ! log ) { return; }
				var line = document.createElement( 'p' );
				line.className = 'wcb-import-log-line' + ( extraClass ? ' ' + extraClass : '' );
				line.textContent = text;
				log.appendChild( line );
				log.scrollTop = log.scrollHeight;
			}

			function updateBar( pct ) {
				if ( fill )  { fill.style.width = pct + '%'; }
				if ( label ) { label.textContent = pct + '%'; }
			}

			function runBatch() {
				wp.apiFetch( {
					path:   '/wcb/v1/import/run',
					method: 'POST',
					data:   { type: type, offset: offset, limit: limit },
				} ).then( function ( res ) {
					imported += res.imported || 0;
					skipped  += res.skipped  || 0;

					if ( res.errors && res.errors.length ) {
						errors += res.errors.length;
						res.errors.forEach( function ( err ) {
							appendLog( err, 'wcb-import-log-line--error' );
						} );
					}

					offset = res.next || ( offset + limit );

					var processed = imported + skipped + errors;
					var pct = total > 0 ? Math.min( 100, Math.round( ( processed / total ) * 100 ) ) : 100;
					updateBar( pct );
					if ( label ) {
						label.textContent = pct + '% \u2014 ' + imported + ' imported, ' + skipped + ' skipped';
					}

					if ( res.done ) {
						appendLog(
							'Done. Imported: ' + imported + '  Skipped: ' + skipped + '  Errors: ' + errors,
							'wcb-import-log-done'
						);
						updateBar( 100 );
						if ( label ) { label.textContent = '100% \u2014 complete'; }

						var remaining = card.querySelector( '.wcb-import-stat-remaining' );
						var migrated  = card.querySelector( '.wcb-import-stat-migrated' );
						if ( remaining ) { remaining.textContent = String( Math.max( 0, parseInt( remaining.textContent, 10 ) - imported ) ); }
						if ( migrated )  { migrated.textContent  = String( parseInt( migrated.textContent, 10 ) + imported ); }
					} else {
						runBatch();
					}
				} ).catch( function ( err ) {
					var msg = ( err && err.message ) ? err.message : 'Request failed.';
					appendLog( 'Error: ' + msg, 'wcb-import-log-line--error' );
					btn.disabled    = false;
					btn.textContent = 'Import';
				} );
			}

			runBatch();
		} );
	}

	// -------------------------------------------------------------------------
	// Pro upgrade banner dismiss
	// -------------------------------------------------------------------------

	function initProBannerDismiss() {
		var btn = document.querySelector( '.wcb-pro-banner-dismiss' );
		if ( ! btn ) {
			return;
		}

		btn.addEventListener( 'click', function () {
			var banner = document.getElementById( 'wcb-pro-upgrade-banner' );
			if ( banner ) {
				banner.style.display = 'none';
			}

			wp.apiFetch( {
				path:   '/wcb/v1/admin/dismiss-banner',
				method: 'POST',
				data:   { banner: 'pro_banner' },
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initStatusSelects();
		initJobModeration();
		initTrustSelects();
		initPanelToggles();
		initImportPage();
		initProBannerDismiss();
	} );
}() );
