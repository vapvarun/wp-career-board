/**
 * Settings > Advanced: remove the setup wizard's sample data.
 *
 * Loaded on the settings page (wcb-admin-sample-data); strings arrive as
 * `wcbSampleData.i18n`, the REST base and nonce as `wcbAdmin`.
 */
( function () {
	var btn = document.getElementById( 'wcb-remove-sample-data' );
	if ( ! btn ) { return; }

	var labelDefault  = btn.textContent;
	var i18n = wcbSampleData.i18n;

	function toast( message, type ) {
		if ( 'function' === typeof window.wcbToast ) {
			window.wcbToast( message, type || 'info' );
		}
	}

	function openConfirm() {
		return window.wcbConfirm( {
			title:       i18n.confirmTitle,
			message:     i18n.confirmMessage,
			confirmText: i18n.confirmCta,
			cancelText:  i18n.cancel,
			destructive: true,
		} );
	}

	btn.addEventListener( 'click', function () {
		openConfirm().then( function () {
			var status = document.getElementById( 'wcb-remove-sample-status' );
			btn.disabled = true;
			btn.textContent = i18n.removing;
			status.textContent = '';

			return fetch( wcbAdmin.restUrl + '/wizard/remove-sample-data', {
				method:  'POST',
				headers: {
					'X-WP-Nonce':   wcbAdmin.restNonce,
					'Content-Type': 'application/json',
				},
			} ).then( function ( r ) {
				if ( ! r.ok ) { throw new Error( 'http ' + r.status ); }
				return r.json();
			} ).then( function ( data ) {
				var jobs       = parseInt( data && data.jobs, 10 ) || 0;
				var companies  = parseInt( data && data.companies, 10 ) || 0;
				var candidates = parseInt( data && data.candidates, 10 ) || 0;
				var terms      = parseInt( data && data.terms, 10 ) || 0;
				var total      = jobs + companies + candidates + terms;

				if ( total > 0 ) {
					var msg = i18n.success
						.replace( '%JOBS%', String( jobs ) )
						.replace( '%COMPANIES%', String( companies ) )
						.replace( '%CANDIDATES%', String( candidates ) )
						.replace( '%TERMS%', String( terms ) );
					status.textContent = msg;
					toast( msg, 'success' );
					setTimeout( function () {
						var block = document.getElementById( 'wcb-sample-data-block' );
						if ( block ) { block.style.display = 'none'; }
					}, 2500 );
				} else {
					status.textContent = i18n.emptyNotice;
					toast( i18n.emptyNotice, 'info' );
					btn.disabled = false;
					btn.textContent = labelDefault;
				}
			} );
		} ).catch( function ( err ) {
			// User cancelled the modal: do nothing.
			if ( ! err || err.cancelled ) { return; }
			var status = document.getElementById( 'wcb-remove-sample-status' );
			if ( status ) { status.textContent = i18n.error; }
			toast( i18n.error, 'error' );
			btn.disabled = false;
			btn.textContent = labelDefault;
		} );
	} );
} )();
