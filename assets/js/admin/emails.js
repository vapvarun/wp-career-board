/**
 * WP Career Board — Emails admin tab behaviour.
 *
 * Handles:
 *   - Test-send buttons next to each email template
 *   - Activity Log filtering / pagination / refresh
 *
 * Localized config arrives via the wcbAdminEmails global (see wp_localize_script):
 *   { restBase, nonce, i18n: { sending, sent, failed, empty, fail, page, records } }
 *
 * @package WP_Career_Board
 * @since   1.1.1
 */
(function () {
	'use strict';

	if ( typeof window.wcbAdminEmails === 'undefined' ) {
		return;
	}

	var cfg  = window.wcbAdminEmails;
	var i18n = cfg.i18n || {};

	/* ── Test-send wiring ─────────────────────────────────────────────────── */

	function bindTestButtons() {
		document.querySelectorAll( '.wcb-email-test-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var emailId  = btn.getAttribute( 'data-email-id' );
				var label    = btn.querySelector( 'span' );
				var origText = label ? label.textContent : btn.textContent;

				btn.disabled = true;
				if ( label ) {
					label.textContent = i18n.sending || 'Sending…';
				}

				fetch( cfg.restBase + '/admin/emails/test', {
					method:      'POST',
					credentials: 'same-origin',
					headers:     {
						'X-WP-Nonce':   cfg.nonce,
						'Content-Type': 'application/json'
					},
					body: JSON.stringify( { email_id: emailId } )
				} )
					.then( function ( r ) { return r.json(); } )
					.then( function ( data ) {
						btn.disabled = false;
						if ( data && data.sent ) {
							btn.classList.add( 'wcb-email-test-btn--ok' );
							if ( label ) {
								label.textContent = i18n.sent || 'Sent';
							}
							setTimeout( function () {
								btn.classList.remove( 'wcb-email-test-btn--ok' );
								if ( label ) {
									label.textContent = origText;
								}
								if ( typeof window.wcbReloadEmailLog === 'function' ) {
									window.wcbReloadEmailLog();
								}
							}, 2500 );
						} else {
							btn.classList.add( 'wcb-email-test-btn--err' );
							if ( label ) {
								label.textContent = i18n.failed || 'Failed';
							}
							setTimeout( function () {
								btn.classList.remove( 'wcb-email-test-btn--err' );
								if ( label ) {
									label.textContent = origText;
								}
							}, 3500 );
						}
					} )
					.catch( function () {
						btn.disabled = false;
						btn.classList.add( 'wcb-email-test-btn--err' );
						if ( label ) {
							label.textContent = i18n.failed || 'Failed';
						}
						setTimeout( function () {
							btn.classList.remove( 'wcb-email-test-btn--err' );
							if ( label ) {
								label.textContent = origText;
							}
						}, 3500 );
					} );
			} );
		} );
	}

	/* ── Activity log ─────────────────────────────────────────────────────── */

	var page    = 1;
	var perPage = 20;

	function formatWhen( iso ) {
		if ( ! iso ) {
			return '—';
		}
		var d = new Date( String( iso ).replace( ' ', 'T' ) + 'Z' );
		if ( isNaN( d.getTime() ) ) {
			return String( iso );
		}
		return d.toLocaleString();
	}

	function makeRow( row ) {
		var tr     = document.createElement( 'tr' );
		var status = ( row.status || 'unknown' ).toLowerCase();
		var pill   = ( [ 'sent', 'failed' ].indexOf( status ) >= 0 ) ? status : 'unknown';

		var cells = [
			{ cls: 'wcb-email-log-col-when',      text: formatWhen( row.sent_at ) },
			{ cls: 'wcb-email-log-col-template',  inner: 'code', text: row.event_type || '—' },
			{ cls: 'wcb-email-log-col-recipient', text: row.recipient || '—' },
			{ cls: 'wcb-email-log-col-subject',   text: row.subject || '—' },
			{
				cls:      'wcb-email-log-col-status',
				inner:    'span',
				innerCls: 'wcb-log-status-pill wcb-log-status-pill--' + pill,
				text:     status
			}
		];

		cells.forEach( function ( c ) {
			var td = document.createElement( 'td' );
			if ( c.cls ) {
				td.className = c.cls;
			}
			if ( c.inner ) {
				var inner = document.createElement( c.inner );
				if ( c.innerCls ) {
					inner.className = c.innerCls;
				}
				inner.textContent = c.text;
				td.appendChild( inner );
			} else {
				td.textContent = c.text;
			}
			tr.appendChild( td );
		} );

		return tr;
	}

	function setBodyMessage( text, color ) {
		var tbody = document.querySelector( '#wcb-email-log-table tbody' );
		if ( ! tbody ) {
			return;
		}
		while ( tbody.firstChild ) {
			tbody.removeChild( tbody.firstChild );
		}
		var tr = document.createElement( 'tr' );
		var td = document.createElement( 'td' );
		td.colSpan   = 5;
		td.className = 'wcb-email-log-empty';
		if ( color ) {
			td.style.color = color;
		}
		td.textContent = text;
		tr.appendChild( td );
		tbody.appendChild( tr );
	}

	function load() {
		var eventEl  = document.getElementById( 'wcb-log-filter-event' );
		var statusEl = document.getElementById( 'wcb-log-filter-status' );
		var tableEl  = document.getElementById( 'wcb-email-log-table' );

		if ( ! eventEl || ! statusEl || ! tableEl ) {
			return;
		}

		var eventType = eventEl.value || '';
		var status    = statusEl.value || '';
		var url       = cfg.restBase + '/admin/emails/log?per_page=' + perPage + '&page=' + page;

		if ( eventType ) {
			url += '&event_type=' + encodeURIComponent( eventType );
		}
		if ( status ) {
			url += '&status=' + encodeURIComponent( status );
		}

		fetch( url, {
			credentials: 'same-origin',
			headers:     { 'X-WP-Nonce': cfg.nonce }
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				var tbody = document.querySelector( '#wcb-email-log-table tbody' );
				while ( tbody.firstChild ) {
					tbody.removeChild( tbody.firstChild );
				}
				if ( ! data || ! data.items || data.items.length === 0 ) {
					setBodyMessage( i18n.empty || 'No emails logged.' );
				} else {
					data.items.forEach( function ( row ) { tbody.appendChild( makeRow( row ) ); } );
				}
				var info = document.getElementById( 'wcb-log-pageinfo' );
				if ( info ) {
					info.textContent = ( i18n.page || 'Page' ) + ' ' + ( data.page || 1 ) +
						' / ' + ( data.pages || 1 ) +
						' — ' + ( data.total || 0 ) + ' ' + ( i18n.records || 'records' );
				}
				var prevBtn = document.getElementById( 'wcb-log-prev' );
				var nextBtn = document.getElementById( 'wcb-log-next' );
				if ( prevBtn ) {
					prevBtn.disabled = ( data.page || 1 ) <= 1;
				}
				if ( nextBtn ) {
					nextBtn.disabled = ( data.page || 1 ) >= ( data.pages || 1 );
				}
			} )
			.catch( function () {
				setBodyMessage( i18n.fail || 'Failed to load activity log.', '#991b1b' );
			} );
	}

	function bindLog() {
		var refreshBtn = document.getElementById( 'wcb-log-refresh' );
		var prevBtn    = document.getElementById( 'wcb-log-prev' );
		var nextBtn    = document.getElementById( 'wcb-log-next' );
		var eventEl    = document.getElementById( 'wcb-log-filter-event' );
		var statusEl   = document.getElementById( 'wcb-log-filter-status' );

		if ( ! refreshBtn || ! prevBtn || ! nextBtn || ! eventEl || ! statusEl ) {
			return;
		}

		window.wcbReloadEmailLog = function () { page = 1; load(); };

		refreshBtn.addEventListener( 'click', function () { page = 1; load(); } );
		prevBtn.addEventListener( 'click', function () { if ( page > 1 ) { page--; load(); } } );
		nextBtn.addEventListener( 'click', function () { page++; load(); } );
		eventEl.addEventListener( 'change', function () { page = 1; load(); } );
		statusEl.addEventListener( 'change', function () { page = 1; load(); } );

		load();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			bindTestButtons();
			bindLog();
		} );
	} else {
		bindTestButtons();
		bindLog();
	}
}());
