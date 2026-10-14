/**
 * "Privacy & My Data" panel (both dashboards): data export through WordPress's
 * privacy requests, and the plugin's own account deletion (grace period,
 * self-cancel). Markup: templates/parts/privacy-settings.php.
 */
import { store } from '@wordpress/interactivity';
import { wcbFetch } from '@wcb/fetch';

const { state } = store( 'wcb-privacy', {
	state: {
		get deletionPending() {
			return 'scheduled' === state.deletionStatus;
		},
		get deleteButtonHidden() {
			return state.deletionPending || state.deleteFormOpen;
		},
		get deleteFormHidden() {
			return ! state.deleteFormOpen || state.deletionPending;
		},
		// Scheduling signs the member out everywhere, so right after it a
		// "Keep my account" button would only 403: explain how to cancel instead.
		get showSignedOutNotice() {
			return state.deletionPending && state.deletionJustScheduled;
		},
		get showCancelControl() {
			return state.deletionPending && ! state.deletionJustScheduled;
		},
	},
	actions: {
		*requestExport() {
			if ( state.busy ) {
				return;
			}
			state.busy  = true;
			state.error = '';
			try {
				const response = yield wcbFetch( state.apiBase + '/candidates/me/privacy/export', {
					method:  'POST',
					headers: { 'X-WP-Nonce': state.nonce },
				} );
				if ( response.ok ) {
					state.exportRequested = true;
				} else {
					state.error = state.msgPrivacy;
				}
			} catch {
				state.error = state.msgConnection;
			} finally {
				state.busy = false;
			}
		},
		openDeleteForm() {
			state.deleteFormOpen = true;
			state.error          = '';
		},
		closeDeleteForm() {
			state.deleteFormOpen = false;
			state.deletePassword = '';
			state.deleteConfirm  = '';
			state.error          = '';
		},
		setPassword( event ) {
			state.deletePassword = event.target.value;
		},
		setConfirm( event ) {
			state.deleteConfirm = event.target.value;
		},
		*confirmDeletion( event ) {
			// Submitted from its <form> (so password managers see the fields): stay on the page.
			event?.preventDefault();
			if ( state.busy ) {
				return;
			}
			state.busy  = true;
			state.error = '';
			try {
				const response = yield wcbFetch( state.apiBase + '/me', {
					method:  'DELETE',
					headers: { 'X-WP-Nonce': state.nonce, 'Content-Type': 'application/json' },
					body:    JSON.stringify( { password: state.deletePassword, confirm: state.deleteConfirm } ),
				} );
				const body = yield response.json().catch( () => ( {} ) );
				if ( ! response.ok ) {
					// The route tells a wrong password from a missing confirmation.
					state.error = ( body && body.message ) || state.msgDelete;
					return;
				}
				state.deletionStatus        = body.status || 'scheduled';
				state.deletionJustScheduled = true;
				if ( body.scheduled_for ) {
					// ISO 8601 from the route: format it in the site locale.
					const when = new Date( body.scheduled_for );
					state.deletionScheduledFor = Number.isNaN( when.getTime() )
						? String( body.scheduled_for )
						: when.toLocaleDateString( state.locale || undefined, { year: 'numeric', month: 'long', day: 'numeric' } );
				}
				state.deleteFormOpen = false;
				state.deletePassword = '';
				state.deleteConfirm  = '';
			} catch {
				state.error = state.msgConnection;
			} finally {
				state.busy = false;
			}
		},
		*cancelDeletion() {
			if ( state.busy ) {
				return;
			}
			state.busy  = true;
			state.error = '';
			try {
				const response = yield wcbFetch( state.apiBase + '/me/deletion', {
					method:  'DELETE',
					headers: { 'X-WP-Nonce': state.nonce },
				} );
				if ( response.ok ) {
					state.deletionStatus       = 'active';
					state.deletionScheduledFor = '';
				} else {
					state.error = state.msgDeleteCancel;
				}
			} catch {
				state.error = state.msgConnection;
			} finally {
				state.busy = false;
			}
		},
	},
} );
