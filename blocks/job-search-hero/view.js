/**
 * WP Career Board — job-search-hero: keep the filters already chosen.
 *
 * @package WP_Career_Board
 */
import { bindHeroSearch } from '@wcb/hero-search';

// `param` is the name the listing reads and writes; `aliases` are the other
// spellings it accepts (JobSearch::ALIASES), cleared so two spellings never both count.
bindHeroSearch( '.wcb-search-hero:not(.wcb-resume-search-hero)', [
	{ name: 'wcb_search', param: 'search', aliases: [ 'wcb_search', 's' ] },
	{ name: 'category', param: 'category', aliases: [ 'wcb_category' ] },
	{ name: 'wcb_location', param: 'location', aliases: [ 'wcb_location' ] },
	{ name: 'wcb_job_type', param: 'type', aliases: [ 'wcb_job_type' ] },
] );
