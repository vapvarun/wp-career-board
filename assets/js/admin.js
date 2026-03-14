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

	/**
	 * Handle job approve/reject buttons.
	 * Sends POST to /wcb/v1/jobs/{id}/approve or /reject.
	 */
	function initJobModeration() {
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.wcb-approve-job, .wcb-reject-job' );
			if ( ! btn ) {
				return;
			}

			var jobId    = btn.dataset.jobId;
			var isApprove = btn.classList.contains( 'wcb-approve-job' );
			var action   = isApprove ? 'approve' : 'reject';
			var confirm  = isApprove
				? ( wcbAdmin.i18n.confirmApprove || 'Approve this job?' )
				: ( wcbAdmin.i18n.confirmReject  || 'Reject this job?' );

			if ( ! window.confirm( confirm ) ) {
				return;
			}

			btn.disabled = true;

			wp.apiFetch( {
				path:   '/wcb/v1/jobs/' + jobId + '/' + action,
				method: 'POST',
			} ).then( function () {
				var row = btn.closest( 'tr' );
				if ( row ) {
					row.style.opacity = '0.4';
					setTimeout( function () {
						row.remove();
					}, 600 );
				}
			} ).catch( function () {
				alert( 'Could not ' + action + ' job. Please try again.' );
				btn.disabled = false;
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initStatusSelects();
		initJobModeration();
	} );
}() );
