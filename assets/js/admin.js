/* WP Career Board — Admin JS */
( function () {
	'use strict';

	/**
	 * Handle application status change dropdowns.
	 * Sends a PATCH request to /wcb/v1/applications/{id}/status.
	 */
	function initStatusSelects() {
		var selects = document.querySelectorAll( '.wcb-status-select[data-app-id]' );
		selects.forEach( function ( select ) {
			select.addEventListener( 'change', function () {
				var appId  = select.dataset.appId;
				var status = select.value;

				wp.apiFetch( {
					path:   '/wcb/v1/applications/' + appId + '/status',
					method: 'PATCH',
					data:   { status: status },
				} ).then( function () {
					var badge = select.closest( 'tr' ).querySelector( '.wcb-status-badge' );
					if ( badge ) {
						badge.className = 'wcb-status-badge wcb-status-' + status;
						badge.textContent = status.charAt( 0 ).toUpperCase() + status.slice( 1 );
					}
				} ).catch( function () {
					alert( wcbAdmin.i18n.saveFailed || 'Could not update status.' );
					select.value = select.dataset.original;
				} );

				select.dataset.original = status;
			} );

			/* Record initial value for rollback on error */
			select.dataset.original = select.value;
		} );
	}

	document.addEventListener( 'DOMContentLoaded', initStatusSelects );
}() );
