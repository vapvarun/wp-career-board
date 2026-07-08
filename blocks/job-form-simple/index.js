/**
 * Job Form Simple block — editor registration.
 *
 * The editor placeholder shows the block's title and description straight from
 * block.json. Core already translates both (block.json declares `textdomain`)
 * and hands them to the JS registry, so re-declaring them here as literals
 * would ship untranslatable duplicates and pollute the .pot file.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */
( function () {
	var el = wp.element.createElement;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var BLOCK_NAME = 'wp-career-board/job-form-simple';

	wp.blocks.registerBlockType( BLOCK_NAME, {
		edit: function () {
			// Translated block.json metadata — never hardcode title/description.
			var meta = wp.blocks.getBlockType( BLOCK_NAME ) || {};

			return el(
				'div',
				useBlockProps( { style: { padding: '12px 16px', background: '#f0fdf4', border: '1px dashed #86efac', borderRadius: '4px' } } ),
				el( 'strong', { style: { color: '#166534', display: 'block' } }, meta.title || '' ),
				el( 'span', { style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } }, meta.description || '' )
			);
		},
	} );
} )();
