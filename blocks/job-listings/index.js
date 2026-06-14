( function () {
	var el                = wp.element.createElement;
	var Fragment          = wp.element.Fragment;
	var __                = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var PanelBody         = wp.components.PanelBody;
	var SelectControl     = wp.components.SelectControl;
	var RangeControl      = wp.components.RangeControl;
	var ToggleControl     = wp.components.ToggleControl;
	var Button            = wp.components.Button;
	var useSelect         = wp.data.useSelect;

	// Filter sidebar groups, in default order. Keys must match the buffered
	// groups in render.php ($wcb_default_filter_order).
	var DEFAULT_FILTERS = [
		{ key: 'type',       label: __( 'Job type', 'wp-career-board' ) },
		{ key: 'experience', label: __( 'Experience', 'wp-career-board' ) },
		{ key: 'category',   label: __( 'Category', 'wp-career-board' ) },
		{ key: 'tags',       label: __( 'Tags', 'wp-career-board' ) },
		{ key: 'location',   label: __( 'Location', 'wp-career-board' ) },
		{ key: 'board',      label: __( 'Job board', 'wp-career-board' ) },
		{ key: 'salary',     label: __( 'Salary', 'wp-career-board' ) },
	];
	var DEFAULT_FILTER_KEYS = DEFAULT_FILTERS.map( function ( f ) { return f.key; } );
	var FILTER_LABELS = {};
	DEFAULT_FILTERS.forEach( function ( f ) { FILTER_LABELS[ f.key ] = f.label; } );

	wp.blocks.registerBlockType( 'wp-career-board/job-listings', {
		edit: function ( props ) {
			var attr    = props.attributes;
			var setAttr = props.setAttributes;

			// wcb_board is a Free CPT registered with show_in_rest, so the
			// core entity store resolves it directly. A Free-only site has a
			// single "Main Board"; the list fills out when Pro adds boards.
			var boards = useSelect( function ( select ) {
				return select( 'core' ).getEntityRecords( 'postType', 'wcb_board', {
					per_page: 100,
					orderby:  'title',
					order:    'asc',
				} );
			}, [] );

			var boardOptions = [ { label: __( 'All boards', 'wp-career-board' ), value: '0' } ];
			if ( boards ) {
				boards.forEach( function ( board ) {
					var title = board.title && board.title.rendered ? board.title.rendered : ( '#' + board.id );
					boardOptions.push( { label: title, value: String( board.id ) } );
				} );
			}

			var selectedBoardLabel = __( 'All boards', 'wp-career-board' );
			boardOptions.forEach( function ( opt ) {
				if ( opt.value === String( attr.boardId || 0 ) ) {
					selectedBoardLabel = opt.label;
				}
			} );

			// Effective filter order: saved order first, then any default key the
			// saved order is missing (mirrors render.php so editor matches front end).
			var wcbSavedOrder  = ( attr.filterOrder || [] ).filter( function ( k ) { return DEFAULT_FILTER_KEYS.indexOf( k ) !== -1; } );
			var wcbOrderedKeys = wcbSavedOrder.concat( DEFAULT_FILTER_KEYS.filter( function ( k ) { return wcbSavedOrder.indexOf( k ) === -1; } ) );
			var wcbHidden      = attr.hiddenFilters || [];

			function wcbMoveFilter( index, delta ) {
				var next = index + delta;
				if ( next < 0 || next >= wcbOrderedKeys.length ) { return; }
				var arr = wcbOrderedKeys.slice();
				var tmp = arr[ index ];
				arr[ index ] = arr[ next ];
				arr[ next ] = tmp;
				setAttr( { filterOrder: arr } );
			}
			function wcbToggleHidden( key ) {
				var arr = wcbHidden.slice();
				var i = arr.indexOf( key );
				if ( -1 === i ) { arr.push( key ); } else { arr.splice( i, 1 ); }
				setAttr( { hiddenFilters: arr } );
			}

			return el( Fragment, {},
				el( InspectorControls, {},
					el( PanelBody, { title: __( 'Job Listings settings', 'wp-career-board' ), initialOpen: true },
						el( SelectControl, {
							label:    __( 'Job board', 'wp-career-board' ),
							value:    String( attr.boardId || 0 ),
							options:  boardOptions,
							help:     __( 'Show only jobs assigned to this board. "All boards" shows every job.', 'wp-career-board' ),
							onChange: function ( val ) { setAttr( { boardId: parseInt( val, 10 ) || 0 } ); },
						} ),
						el( SelectControl, {
							label:    __( 'Layout', 'wp-career-board' ),
							value:    attr.layout,
							options:  [
								{ label: __( 'Grid', 'wp-career-board' ), value: 'grid' },
								{ label: __( 'List', 'wp-career-board' ), value: 'list' },
							],
							onChange: function ( val ) { setAttr( { layout: val } ); },
						} ),
						'grid' === attr.layout && el( SelectControl, {
							label:    __( 'Grid columns', 'wp-career-board' ),
							value:    String( attr.columns || 3 ),
							options:  [
								{ label: __( '3 columns', 'wp-career-board' ), value: '3' },
								{ label: __( '4 columns', 'wp-career-board' ), value: '4' },
							],
							onChange: function ( val ) { setAttr( { columns: parseInt( val, 10 ) === 4 ? 4 : 3 } ); },
						} ),
						el( ToggleControl, {
							label:    __( 'Show filter sidebar', 'wp-career-board' ),
							checked:  !! attr.showFilters,
							help:     attr.showFilters
								? __( 'Visitors can filter and search the listing.', 'wp-career-board' )
								: __( 'Listing only - no search box or filter sidebar.', 'wp-career-board' ),
							onChange: function ( val ) { setAttr( { showFilters: !! val } ); },
						} ),
						el( ToggleControl, {
							label:    __( 'Show page heading', 'wp-career-board' ),
							checked:  !! attr.showHeading,
							help:     attr.showHeading
								? __( 'The block prints the archive title. Turn off when the page already has a heading.', 'wp-career-board' )
								: __( 'No title printed - the page or theme provides the heading (recommended).', 'wp-career-board' ),
							onChange: function ( val ) { setAttr( { showHeading: !! val } ); },
						} ),
						el( RangeControl, {
							label:    __( 'Jobs per page (0 uses the site default)', 'wp-career-board' ),
							value:    attr.perPage,
							min:      0,
							max:      48,
							onChange: function ( val ) { setAttr( { perPage: val || 0 } ); },
						} )
					),
					attr.showFilters && el( PanelBody, { title: __( 'Filter sidebar', 'wp-career-board' ), initialOpen: false },
						el( 'p', { style: { color: '#64748b', fontSize: '12px', margin: '0 0 8px' } },
							__( 'Reorder filters with the arrows, or hide ones you do not need.', 'wp-career-board' )
						),
						wcbOrderedKeys.map( function ( fkey, index ) {
							var isHidden = wcbHidden.indexOf( fkey ) !== -1;
							return el( 'div', { key: fkey, style: { display: 'flex', alignItems: 'center', gap: '4px', padding: '4px 0' } },
								el( Button, { icon: 'arrow-up-alt2', label: __( 'Move up', 'wp-career-board' ), disabled: 0 === index, size: 'small', onClick: function () { wcbMoveFilter( index, -1 ); } } ),
								el( Button, { icon: 'arrow-down-alt2', label: __( 'Move down', 'wp-career-board' ), disabled: index === wcbOrderedKeys.length - 1, size: 'small', onClick: function () { wcbMoveFilter( index, 1 ); } } ),
								el( 'span', { style: { flex: '1 1 auto', fontSize: '13px', textDecoration: isHidden ? 'line-through' : 'none', color: isHidden ? '#94a3b8' : 'inherit' } }, FILTER_LABELS[ fkey ] ),
								el( Button, { icon: isHidden ? 'hidden' : 'visibility', label: isHidden ? __( 'Show filter', 'wp-career-board' ) : __( 'Hide filter', 'wp-career-board' ), isPressed: isHidden, size: 'small', onClick: function () { wcbToggleHidden( fkey ); } } )
							);
						} )
					)
				),
				el( 'div', useBlockProps( { style: { padding: '12px 16px', background: '#f0f6fc', border: '1px dashed #93c5fd', borderRadius: '4px' } } ),
					el( 'strong', { style: { color: '#1e40af', display: 'block' } }, 'WCB: Job Listings' ),
					el( 'span', { style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } },
						__( 'Board: ', 'wp-career-board' ) + selectedBoardLabel
							+ '  ·  ' + ( 'list' === attr.layout
								? __( 'List', 'wp-career-board' )
								: __( 'Grid', 'wp-career-board' ) + ' ' + ( attr.columns || 3 ) + __( '-col', 'wp-career-board' ) )
							+ '  ·  ' + ( attr.showFilters ? __( 'Filters on', 'wp-career-board' ) : __( 'Filters off', 'wp-career-board' ) )
							+ ( attr.showHeading ? '  ·  ' + __( 'Heading on', 'wp-career-board' ) : '' )
					)
				)
			);
		},
	} );
} )();
