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
	var useSelect         = wp.data.useSelect;

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
