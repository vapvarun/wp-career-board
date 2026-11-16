/**
 * Remove this site's job form drafts after an explicit logout.
 *
 * JobsModule::flag_draft_cleanup() sets a one-minute cookie whose value is the
 * site (blog) ID. The drafts are keyed wcb-job-draft-{blog}-{user} and
 * wcb-job-draft-simple-{blog}-{user}, so a shared browser keeps no job text
 * once its user signs out.
 *
 * @package WP_Career_Board
 * @since   1.8.4
 */
( function () {
	const match = document.cookie.match( /(?:^|;\s*)wcb_clear_drafts=(\d+)/ );
	if ( ! match ) {
		return;
	}
	const prefixes = [ 'wcb-job-draft-' + match[ 1 ] + '-', 'wcb-job-draft-simple-' + match[ 1 ] + '-' ];
	try {
		Object.keys( window.localStorage )
			.filter( ( key ) => prefixes.some( ( prefix ) => key.startsWith( prefix ) ) )
			.forEach( ( key ) => window.localStorage.removeItem( key ) );
	} catch ( _e ) {
		// Storage blocked: nothing was saved either.
	}
	document.cookie = 'wcb_clear_drafts=; Max-Age=0; path=/; SameSite=Lax';
} )();
