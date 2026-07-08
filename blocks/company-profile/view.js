/**
 * WP Career Board — company-profile block Interactivity API store.
 *
 * Actions:
 *   loadMore       — fetch the next page of jobs for this company.
 *   toggleBookmark — Save / unsave the company. Mirrors the Find Jobs
 *                    single hero so the customer's save action carries
 *                    the same affordance across all 3 single pages.
 *
 * @package WP_Career_Board
 */
import { store } from '@wordpress/interactivity';
import { wcbFetch } from '@wcb/fetch';

/**
 * Translation lookup. Script modules cannot load JED files, so render.php
 * seeds every user-facing string under `state.i18n`. The fallback is the
 * English source string and must stay identical to the PHP __() literal.
 *
 * @param {string} key      Key seeded in render.php's `i18n` array.
 * @param {string} fallback English source string.
 * @return {string} Translated string, or the English fallback.
 */
const t = ( key, fallback ) => ( state.i18n && state.i18n[ key ] ) || fallback;

const { state } = store( 'wcb-company-profile', {
	actions: {
		*toggleBookmark() {
			if ( state.bookmarking ) {
				return;
			}
			state.bookmarking = true;

			const wasBookmarked = !! state.bookmarked;
			state.bookmarked = ! wasBookmarked;
			state.bookmarkLabel = state.bookmarked ? t( 'saved', 'Saved' ) : t( 'save', 'Save' );

			try {
				const url = state.apiBase + '/' + state.companyId + '/bookmark';
				const response = yield wcbFetch( url, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce':   state.restNonce,
					},
				} );
				if ( ! response.ok ) {
					state.bookmarked = wasBookmarked;
					state.bookmarkLabel = wasBookmarked ? t( 'saved', 'Saved' ) : t( 'save', 'Save' );
				}
			} catch {
				state.bookmarked = wasBookmarked;
				state.bookmarkLabel = wasBookmarked ? t( 'saved', 'Saved' ) : t( 'save', 'Save' );
			} finally {
				state.bookmarking = false;
			}
		},

		*loadMore() {
			if ( state.loading ) {
				return;
			}
			state.loading = true;
			state.page++;

			try {
				const url = new URL( state.jobsApiBase );
				url.searchParams.set( 'author', String( state.author ) );
				url.searchParams.set( 'page', String( state.page ) );
				url.searchParams.set( 'per_page', String( state.perPage ) );

				const response = yield wcbFetch( url.toString() );

				if ( ! response.ok ) {
					state.page--;
					return;
				}

				const data = yield response.json();
				// /wcb/v1/jobs since 1.1.0 returns { jobs, total, has_more }.
				// Fall back to bare-array + per_page heuristic for old proxies,
				// but prefer the authoritative value when available.
				const jobs = Array.isArray( data ) ? data : ( data?.jobs ?? [] );
				const total = Array.isArray( data )
					? parseInt( response.headers.get( 'X-WCB-Total' ) ?? '0', 10 )
					: ( data?.total ?? 0 );
				state.jobs.push( ...jobs );
				if ( ! Array.isArray( data ) && typeof data?.has_more === 'boolean' ) {
					state.hasMore = data.has_more && state.jobs.length < total;
				} else {
					state.hasMore = state.jobs.length < total;
				}
			} catch {
				state.page--;
			} finally {
				state.loading = false;
			}
		},
	},
} );
