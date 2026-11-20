/**
 * Application detail screen interactions — status changer + quick actions.
 *
 * Reads the REST URL from data attributes set by the StatusChanger
 * and QuickActions widgets. Same script powers the [wcb_widget] shortcode
 * placement on the front end.
 */
( () => {
	const update = ( endpoint, status, feedbackEl ) => {
		feedbackEl.textContent = '';
		feedbackEl.classList.remove( 'wcb-app-changer__feedback--ok', 'wcb-app-changer__feedback--err', 'wcb-app-actions__feedback--ok', 'wcb-app-actions__feedback--err' );

		const isAction = feedbackEl.classList.contains( 'wcb-app-actions__feedback' );

		// wp.apiFetch adds the REST nonce, renews it once when it expires, and rejects with the error body.
		return wp.apiFetch( { url: endpoint, method: 'POST', data: { status } } )
			.then( ( body ) => {
				feedbackEl.classList.add( isAction ? 'wcb-app-actions__feedback--ok' : 'wcb-app-changer__feedback--ok' );
				feedbackEl.textContent = wcbAppDetail?.savedLabel || 'Saved';
				return body;
			} )
			.catch( ( err ) => {
				feedbackEl.classList.add( isAction ? 'wcb-app-actions__feedback--err' : 'wcb-app-changer__feedback--err' );
				feedbackEl.textContent = ( err && err.message ) || wcbAppDetail?.errorLabel || 'Could not save.';
				return null;
			} );
	};

	const refreshStatusPills = ( newStatus ) => {
		document.querySelectorAll( '.wcb-app-status' ).forEach( ( pill ) => {
			const labels = wcbAppDetail?.labels || {};
			pill.className = 'wcb-app-status wcb-app-status--' + newStatus;
			if ( labels[ newStatus ] ) {
				pill.textContent = labels[ newStatus ];
			}
		} );
	};

	const wireChanger = ( root ) => {
		const select   = root.querySelector( '.wcb-app-changer__select' );
		const button   = root.querySelector( '.wcb-app-changer__save' );
		const feedback = root.querySelector( '.wcb-app-changer__feedback' );
		if ( ! select || ! button || ! feedback ) return;

		const url = root.getAttribute( 'data-rest-url' );

		button.addEventListener( 'click', async () => {
			const status = select.value;
			button.disabled = true;
			const result = await update( url, status, feedback );
			button.disabled = false;
			if ( result ) {
				refreshStatusPills( status );
			}
		} );
	};

	const wireActions = ( root ) => {
		const feedback = root.querySelector( '.wcb-app-actions__feedback' );
		if ( ! feedback ) return;

		const url = root.getAttribute( 'data-rest-url' );

		root.querySelectorAll( '.wcb-app-actions__btn' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', async () => {
				const status = btn.getAttribute( 'data-status' );
				if ( ! status ) return;
				btn.disabled = true;
				const result = await update( url, status, feedback );
				btn.disabled = false;
				if ( result ) {
					refreshStatusPills( status );
					const select = document.querySelector( '.wcb-app-changer__select' );
					if ( select ) {
						select.value = status;
					}
				}
			} );
		} );
	};

	const init = () => {
		document.querySelectorAll( '.wcb-app-changer' ).forEach( wireChanger );
		document.querySelectorAll( '.wcb-app-actions' ).forEach( wireActions );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
