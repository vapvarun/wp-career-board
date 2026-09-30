/**
 * Settings > Companies: edit the industry list.
 *
 * Loaded on the settings page (wcb-admin-industries); the translated strings
 * arrive as `wcbIndustries.i18n`, the REST base and nonce as `wcbAdmin`.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	var root = document.getElementById( 'wcb-industries-card' );
	if ( ! root || 'undefined' === typeof wcbAdmin ) { return; }

	var listEl    = document.getElementById( 'wcb-industries-list' );
	var orphanBox = document.getElementById( 'wcb-industries-orphans' );
	var orphanEl  = document.getElementById( 'wcb-industries-orphan-list' );
	var statusEl  = document.getElementById( 'wcb-industries-status' );
	var saveBtn   = document.getElementById( 'wcb-industries-save' );
	var addBtn    = document.getElementById( 'wcb-industry-add' );
	var newLabel  = document.getElementById( 'wcb-industry-new-label' );

	var i18n = wcbIndustries.i18n;

	var rows     = [];
	var orphans  = [];
	var removals = {};

	function toast( message, type ) {
		if ( 'function' === typeof window.wcbToast ) { window.wcbToast( message, type || 'info' ); }
	}

	function api( path, options ) {
		var opts = options || {};
		opts.headers = { 'X-WP-Nonce': wcbAdmin.restNonce, 'Content-Type': 'application/json' };
		return fetch( wcbAdmin.restUrl + path, opts ).then( function ( r ) {
			return r.json().then( function ( body ) {
				if ( ! r.ok ) { throw body; }
				return body;
			} );
		} );
	}

	function slugify( value ) {
		return String( value ).toLowerCase().trim()
			.replace( /[^a-z0-9]+/g, '-' )
			.replace( /^-+|-+$/g, '' );
	}

	function countText( count ) {
		if ( ! count ) { return i18n.unused; }
		if ( 1 === count ) { return i18n.usedOne; }
		return i18n.used.replace( '%d', String( count ) );
	}

	function targetOptions( exceptSlug ) {
		return rows.filter( function ( row ) {
			return row.slug !== exceptSlug && ! removals[ row.slug ];
		} );
	}

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) { node.className = className; }
		if ( undefined !== text ) { node.textContent = text; }
		return node;
	}

	function renderSettle( row, container ) {
		var settle = el( 'div', 'wcb-ind-settle' );
		var choice = document.createElement( 'select' );
		choice.className = 'regular-text';
		choice.setAttribute( 'aria-label', i18n.settle );

		targetOptions( row.slug ).forEach( function ( option ) {
			var opt = document.createElement( 'option' );
			opt.value = option.slug;
			opt.textContent = option.label;
			choice.appendChild( opt );
		} );
		var clearOpt = document.createElement( 'option' );
		clearOpt.value = '';
		clearOpt.textContent = i18n.clear;
		choice.appendChild( clearOpt );

		choice.value = removals[ row.slug ].target;
		choice.addEventListener( 'change', function () {
			removals[ row.slug ].target = choice.value;
			removals[ row.slug ].action = choice.value ? 'reassign' : 'clear';
		} );

		var keep = el( 'button', 'wcb-btn wcb-btn--ghost', i18n.keep );
		keep.type = 'button';
		keep.addEventListener( 'click', function () {
			delete removals[ row.slug ];
			render();
		} );

		settle.appendChild( el( 'span', 'description', i18n.settle ) );
		settle.appendChild( choice );
		settle.appendChild( keep );
		container.appendChild( settle );
	}

	function renderRow( row, isOrphan ) {
		var pending = !! removals[ row.slug ];
		var node    = el( 'div', 'wcb-ind-row' + ( pending ? ' is-removing' : '' ) );

		var labelWrap = el( 'div', 'wcb-ind-row__label' );
		if ( isOrphan || pending ) {
			labelWrap.appendChild( el( 'strong', '', row.label ) );
		} else {
			var input = document.createElement( 'input' );
			input.type = 'text';
			input.className = 'regular-text';
			input.value = row.label;
			input.setAttribute( 'aria-label', row.label );
			input.addEventListener( 'input', function () { row.label = input.value; } );
			labelWrap.appendChild( input );
		}
		node.appendChild( labelWrap );
		node.appendChild( el( 'code', 'wcb-ind-row__slug', row.slug ) );
		node.appendChild( el( 'span', 'wcb-ind-row__count', countText( row.count ) ) );

		if ( pending ) {
			node.appendChild( el( 'span', 'description', i18n.pendingKeep ) );
		} else {
			var remove = el( 'button', 'wcb-btn wcb-btn--ghost wcb-ind-row__remove', i18n.remove );
			remove.type = 'button';
			remove.setAttribute( 'aria-label', i18n.removeAria.replace( '%s', row.label ) );
			remove.addEventListener( 'click', function () {
				if ( ! row.count ) {
					// Nothing stored against it — drop it outright.
					rows = rows.filter( function ( r ) { return r.slug !== row.slug; } );
					orphans = orphans.filter( function ( r ) { return r.slug !== row.slug; } );
					render();
					return;
				}
				var fallback = targetOptions( row.slug )[ 0 ];
				removals[ row.slug ] = {
					action: fallback ? 'reassign' : 'clear',
					target: fallback ? fallback.slug : ''
				};
				render();
			} );
			node.appendChild( remove );
		}

		if ( pending ) { renderSettle( row, node ); }
		return node;
	}

	function render() {
		listEl.textContent = '';
		rows.forEach( function ( row ) { listEl.appendChild( renderRow( row, false ) ); } );

		orphanEl.textContent = '';
		var live = orphans.filter( function ( row ) {
			return ! rows.some( function ( r ) { return r.slug === row.slug; } );
		} );
		live.forEach( function ( row ) { orphanEl.appendChild( renderRow( row, true ) ); } );
		orphanBox.hidden = 0 === live.length;
	}

	addBtn.addEventListener( 'click', function () {
		var label = newLabel.value.trim();
		if ( ! label ) { toast( i18n.addFirst, 'error' ); newLabel.focus(); return; }
		var slug = slugify( label );
		if ( ! slug || rows.some( function ( r ) { return r.slug === slug; } ) ) {
			toast( i18n.duplicate, 'error' );
			return;
		}
		rows.push( { slug: slug, label: label, count: 0 } );
		delete removals[ slug ];
		newLabel.value = '';
		render();
		newLabel.focus();
	} );

	newLabel.addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' === event.key ) { event.preventDefault(); addBtn.click(); }
	} );

	saveBtn.addEventListener( 'click', function () {
		var keep = rows.filter( function ( row ) { return ! removals[ row.slug ]; } );
		if ( ! keep.length ) { toast( i18n.emptyList, 'error' ); return; }

		var payload = {
			industries: keep.map( function ( row ) {
				return { slug: row.slug, label: row.label };
			} ),
			removals: Object.keys( removals ).map( function ( slug ) {
				return {
					slug: slug,
					action: removals[ slug ].action,
					target: removals[ slug ].target
				};
			} )
		};

		saveBtn.disabled = true;
		statusEl.textContent = i18n.saving;

		api( '/admin/industries', { method: 'POST', body: JSON.stringify( payload ) } )
			.then( function ( data ) {
				rows     = data.industries || [];
				orphans  = data.orphans || [];
				removals = {};
				render();
				statusEl.textContent = '';
				toast( data.moved ? i18n.savedMoved.replace( '%d', String( data.moved ) ) : i18n.saved, 'success' );
			} )
			.catch( function ( body ) {
				statusEl.textContent = '';
				toast( ( body && body.message ) || i18n.error, 'error' );
			} )
			.then( function () { saveBtn.disabled = false; } );
	} );

	api( '/admin/industries', { method: 'GET' } )
		.then( function ( data ) {
			rows    = data.industries || [];
			orphans = data.orphans || [];
			render();
		} )
		.catch( function () {
			listEl.textContent = i18n.loadError;
		} );
} );
