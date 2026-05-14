/**
 * WP Career Board — job-form block Interactivity API store.
 *
 * Actions:
 *   updateField  — generic field updater via data-wcb-field attribute.
 *   toggleRemote — flip the remote boolean.
 *   nextStep     — advance step; validates title on step 1.
 *   prevStep     — go back one step.
 *   submitJob    — POST job data to /wcb/v1/jobs.
 *   resetForm    — Clear success state and return to step 1.
 *
 * State getters:
 *   isStep1 … isStep4          — drive wcb-form-step--show CSS class on each step panel.
 *   step1Active … step4Active  — drive wcb-step--active CSS class on the step indicator.
 *   step1Done   … step3Done    — drive wcb-step--done CSS class on the step indicator.
 *   salaryDisplay              — formatted salary string for preview.
 *   hasCompany, isRemote, hasType, hasExp, hasLocation, hasCategory — preview card conditionals.
 *   hasSalary, hasDeadline, hasApplyUrl, hasApplyEmail              — preview meta conditionals.
 *   hasError, hasValidation                                          — error banner conditionals.
 *
 * @package WP_Career_Board
 */
import { store } from '@wordpress/interactivity';

store(
	'wcb-job-form',
	{
		state: {
			// ── Step panel visibility ─────────────────────────────────────────
			get isStep1() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 1;
			},
			get isStep2() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 2;
			},
			get isStep3() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 3;
			},
			get isStep4() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 4;
			},

			// ── Step indicator: active state ──────────────────────────────────
			get step1Active() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 1;
			},
			get step2Active() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 2;
			},
			get step3Active() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 3;
			},
			get step4Active() {
				const { state } = store( 'wcb-job-form' );
				return state.step === 4;
			},

			// ── Step indicator: done (completed) state ────────────────────────
			get step1Done() {
				const { state } = store( 'wcb-job-form' );
				return state.step > 1;
			},
			get step2Done() {
				const { state } = store( 'wcb-job-form' );
				return state.step > 2;
			},
			get step3Done() {
				const { state } = store( 'wcb-job-form' );
				return state.step > 3;
			},

			// ── Preview computed ──────────────────────────────────────────────
			get salaryDisplay() {
				const { state } = store( 'wcb-job-form' );
				const min       = state.salaryMin ? Number( state.salaryMin ) : 0;
				const max       = state.salaryMax ? Number( state.salaryMax ) : 0;
				if ( ! min && ! max ) {
					return '';
				}
				const cur    = state.currencyCode || 'USD';
				const suffix = state.salaryType === 'monthly' ? '/mo' : state.salaryType === 'hourly' ? '/hr' : '/yr';
				const fmt    = ( v ) => new Intl.NumberFormat( 'en-US' ).format( v );
				if ( min && max ) {
					return `${ cur } ${ fmt( min ) } – ${ fmt( max ) }${ suffix }`;
				}
				return `${ cur } ${ fmt( min || max ) }${ suffix }`;
			},

			// ── Preview card conditionals (all use data-wp-class) ─────────────
			get hasCompany() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.companyName;
			},
			get isRemote() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.remote;
			},
			get hasType() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.typeSlug;
			},
			get hasExp() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.expSlug;
			},
			get hasLocation() {
				const { state } = store( 'wcb-job-form' );
				return state.locationSlug === '__custom__'
					? !! ( state.locationCustom || '' ).trim()
					: !! state.locationSlug;
			},
			get locationIsCustom() {
				const { state } = store( 'wcb-job-form' );
				return state.locationSlug === '__custom__';
			},
			get hasCategory() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.categorySlug;
			},
			get hasSalary() {
				const { state } = store( 'wcb-job-form' );
				const min       = state.salaryMin ? Number( state.salaryMin ) : 0;
				const max       = state.salaryMax ? Number( state.salaryMax ) : 0;
				return ! ! ( min || max );
			},
			get hasDeadline() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.deadline;
			},
			get hasApplyUrl() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.applyUrl;
			},
			get hasApplyEmail() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.applyEmail;
			},
			get hasError() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.error;
			},
			get hasValidation() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.validationError;
			},

			// ── Edit mode ─────────────────────────────────────────────────────────
			get isEditing() {
				const { state } = store( 'wcb-job-form' );
				return state.editJobId > 0;
			},
			get submitLabel() {
				const { state } = store( 'wcb-job-form' );
				return state.editJobId > 0 ? 'Update Job' : 'Post Job';
			},
			get jobPending() {
				const { state } = store( 'wcb-job-form' );
				return state.jobStatus === 'pending';
			},

			// ── Credit getters ────────────────────────────────────────────────
			get hasCreditCost() {
				const { state } = store( 'wcb-job-form' );
				return state.creditCost > 0;
			},
			// Banner visibility — true for paid boards AND for free boards
			// that have the free-posting template seeded. Keeps the
			// employer informed when switching to a zero-cost board.
			get hasCreditBanner() {
				const { state } = store( 'wcb-job-form' );
				return state.creditCost > 0 || !! state.creditFreeTemplate;
			},
			get insufficientCredits() {
				const { state } = store( 'wcb-job-form' );
				return state.creditCost > 0 && state.creditBalance < state.creditCost;
			},
			// Dynamic message — every number is pulled live from the store.
			//   cost    ← state.creditCost    (wcb_board_credit_cost filter; Pro per-board pricing)
			//   balance ← state.creditBalance (wcb_employer_credit_balance filter; SDK get_balance for current user)
			// Both seed server-side in render.php on page load and update
			// reactively when the employer switches boards in the picker.
			// Templates with %1$d/%2$d/%3$d placeholders are pre-translated
			// PHP-side and pushed via wp_interactivity_state so the literal
			// English strings live in the .pot file, not in this module.
			get creditMessage() {
				const { state } = store( 'wcb-job-form' );
				const cost    = state.creditCost;
				const balance = state.creditBalance;
				if ( ! cost ) {
					return ( state.creditFreeTemplate || '' ).replace( '%d', balance );
				}
				if ( balance < cost ) {
					return ( state.creditInsufficientTemplate || '' )
						.replace( '%1$d', cost )
						.replace( '%2$d', balance );
				}
				const noun = 1 === cost
					? ( state.creditNounSingular || '' )
					: ( state.creditNounPlural || '' );
				const balanceAfter = balance - cost;
				return ( state.creditDeductionTemplate || '' )
					.replace( '%1$s', noun.replace( '%d', cost ) )
					.replace( '%2$d', balanceAfter )
					.replace( '%3$d', balance );
			},

			// Pre-publish: tell the employer when their listing will expire so
			// the deadline isn't a surprise. Pairs with the read-only deadline
			// field (admin policy, not employer-editable).
			get listingWindowMessage() {
				const { state } = store( 'wcb-job-form' );
				if ( ! state.deadline ) {
					return '';
				}
				let formatted = state.deadline;
				try {
					const d = new Date( state.deadline + 'T00:00:00' );
					if ( ! isNaN( d.getTime() ) ) {
						formatted = d.toLocaleDateString( undefined, { year: 'numeric', month: 'long', day: 'numeric' } );
					}
				} catch ( _e ) {
					// Fall back to the raw ISO date.
				}
				return `Listing runs until ${ formatted }. Reopen on the dashboard to extend (counts as a republish).`;
			},

			get hasListingWindow() {
				const { state } = store( 'wcb-job-form' );
				return ! ! state.deadline;
			},

			// ── AI generation ─────────────────────────────────────────────
			get aiGenerating() {
				const { state } = store( 'wcb-job-form' );
				return !! state._aiGenerating;
			},

			// ── Preview badge display names (slug → term name via PHP-injected map) ──
			get typeDisplay() {
				const { state } = store( 'wcb-job-form' );
				return state.typeSlug ? ( state.typeNames[ state.typeSlug ] || state.typeSlug ) : '';
			},
			get expDisplay() {
				const { state } = store( 'wcb-job-form' );
				return state.expSlug ? ( state.expNames[ state.expSlug ] || state.expSlug ) : '';
			},
			get locationDisplay() {
				const { state } = store( 'wcb-job-form' );
				if ( state.locationSlug === '__custom__' ) {
					return ( state.locationCustom || '' ).trim();
				}
				return state.locationSlug ? ( state.locationNames[ state.locationSlug ] || state.locationSlug ) : '';
			},
			get categoryDisplay() {
				const { state } = store( 'wcb-job-form' );
				return state.categorySlug ? ( state.categoryNames[ state.categorySlug ] || state.categorySlug ) : '';
			},
		},

		actions: {
			updateField( event ) {
				const { state } = store( 'wcb-job-form' );
				const field     = event.target.dataset.wcbField;
				if ( field ) {
					state[ field ] = event.target.value;
					if ( field === 'title' && state.validationError ) {
						state.validationError = '';
					}
					// When the employer switches boards, re-derive the
					// credit cost AND currency from the seeded per-board
					// maps so the banner ("Posting deducts X credits") and
					// the salary currency dropdown update without a REST
					// round-trip. Maps are keyed by stringified ID.
					if ( field === 'boardId' ) {
						const costMap = state.boardCreditCosts || {};
						const curMap  = state.boardCurrencies || {};
						const key     = String( state.boardId || 0 );
						const cost    = costMap[ key ];
						const cur     = curMap[ key ];
						state.creditCost = Number.isFinite( cost ) ? cost : 0;
						if ( cur ) {
							state.currencyCode = cur;
						}
					}
				}
			},

			updateCustomField( event ) {
				const { state } = store( 'wcb-job-form' );
				const key       = event.target.getAttribute( 'data-wcb-field' );
				if ( ! key ) {
					return;
				}
				const target  = event.target;
				const value   = ( target.type === 'checkbox' )
					? target.checked
					: target.value;
				state.customFields = { ...state.customFields, [ key ]: value };
			},

			toggleRemote() {
				const { state } = store( 'wcb-job-form' );
				state.remote    = ! state.remote;
			},

			* generateDescription() {
				const { state } = store( 'wcb-job-form' );
				if ( state._aiGenerating || ! state.title ) {
					if ( ! state.title ) {
						state.validationError = 'Enter a job title first so AI can generate a description.';
					}
					return;
				}
				state._aiGenerating = true;
				try {
					const response = yield fetch( state.apiBase + '/jobs/ai-description', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': state.nonce,
						},
						body: JSON.stringify( {
							title: state.title,
							company_type: state.companyName || '',
							location: state.locationSlug || 'remote',
						} ),
					} );
					const data = yield response.json();
					if ( data.description ) {
						state.description = data.description;
					} else if ( data.message ) {
						state.error = data.message;
					}
				} catch {
					state.error = 'Failed to generate description. Please try again.';
				} finally {
					state._aiGenerating = false;
				}
			},

			nextStep() {
				const { state } = store( 'wcb-job-form' );

				if ( state.step === 1 ) {
					if ( ! state.title.trim() ) {
						state.validationError = 'Job title is required before you can continue.';
						return;
					}
					if ( ! state.description.trim() ) {
						state.validationError = 'Job description is required before you can continue.';
						return;
					}
				}

				state.validationError = '';
				if ( state.step < 4 ) {
					state.step++;
				}
			},

			prevStep() {
				const { state }       = store( 'wcb-job-form' );
				state.validationError = '';
				if ( state.step > 1 ) {
					state.step--;
				}
			},

			* submitJob() {
				const { state } = store( 'wcb-job-form' );

				if ( state.submitting ) {
					return;
				}

				// Honeypot check — bots filling all fields get a fake success response.
				const hpEl = document.getElementById( 'wcb-hp' );
				if ( hpEl && hpEl.value ) {
					state.submitted = true;
					return;
				}

				// Credit gate — block submission if balance is insufficient.
				if ( state.creditCost > 0 && state.creditBalance < state.creditCost ) {
					state.error = `Insufficient credits. This board requires ${ state.creditCost } credits but your balance is ${ state.creditBalance }.`;
					return;
				}

				// Optional CAPTCHA token (Turnstile / reCAPTCHA). Empty when provider is 'none'.
				const captchaToken = window.wcbCaptchaGetToken
					? yield window.wcbCaptchaGetToken()
					: '';

				state.submitting = true;
				state.error      = '';

				try {
					// Parse comma-separated tags into a slug array.
					const tagSlugs = state.tags
					? state.tags.split( ',' ).map( ( t ) => t.trim() ).filter( Boolean )
					: [];

					const body = {
						title:           state.title,
						description:     state.description,
						salary_min:      state.salaryMin,
						salary_max:      state.salaryMax,
						salary_currency: state.currencyCode || 'USD',
						salary_type:     state.salaryType || 'yearly',
						remote:          state.remote,
						deadline:        state.deadline,
						apply_url:       state.applyUrl,
						apply_email:     state.applyEmail,
						categories:      state.categorySlug ? [ state.categorySlug ] : [],
						job_types:       state.typeSlug ? [ state.typeSlug ] : [],
						locations:       state.locationSlug && state.locationSlug !== '__custom__'
							? [ state.locationSlug ]
							: [],
						location_custom: state.locationSlug === '__custom__'
							? ( state.locationCustom || '' ).trim()
							: '',
						experience:      state.expSlug ? [ state.expSlug ] : [],
						tags:              tagSlugs,
						board_id:          state.boardId ? Number( state.boardId ) : 0,
						custom_fields:     state.customFields || {},
						hp:                hpEl ? hpEl.value : '',
						wcb_captcha_token: captchaToken,
					};

					const isEdit   = state.editJobId > 0;
				const response = yield fetch(
						isEdit
							? state.apiBase + '/jobs/' + String( state.editJobId )
							: state.apiBase + '/jobs',
						{
							method:  isEdit ? 'PATCH' : 'POST',
							headers: {
								'X-WP-Nonce':   state.nonce,
								'Content-Type': 'application/json',
							},
							body: JSON.stringify( body ),
						}
					);

					if ( ! response.ok ) {
						const err = yield response.json().catch( () => null );
						if ( err && err.code === 'rest_cookie_invalid_nonce' ) {
							state.error = state.strings.errorSessionExpired;
						} else {
							state.error = ( err && err.message ) ? err.message : 'Job could not be posted. Please try again.';
						}
						return;
					}

					const data      = yield response.json();
					state.jobUrl    = data.permalink || '';
					state.jobStatus = data.status    || 'publish';
					state.submitted = true;

					// Auto-reset the success state after 8 seconds so a returning
					// user sees a fresh form instead of a stuck success banner —
					// matches the spec on Basecamp card 9817915492.
					setTimeout( () => {
						const live = store( 'wcb-job-form' );
						if ( live && live.state && live.state.submitted ) {
							if ( typeof live.actions?.resetForm === 'function' ) {
								live.actions.resetForm();
							} else {
								live.state.submitted = false;
							}
						}
					}, 8000 );
				} catch {
					state.error = state.strings.errorConnection;
				} finally {
					state.submitting = false;
				}
			},

			resetForm() {
				// Clear success-state flags AND all volatile form values so the
				// user is not editing a copy of their last submission.
				const { state } = store( 'wcb-job-form' );
				state.submitted        = false;
				state.step             = 1;
				state.error            = '';
				state.validationError  = '';
				state.jobUrl           = '';
				state.jobStatus        = '';
				state.editJobId        = 0;
				state.title            = '';
				state.description      = '';
				state.salaryMin        = '';
				state.salaryMax        = '';
				state.deadline         = '';
				state.applyUrl         = '';
				state.applyEmail       = '';
				state.locationSlug     = '';
				state.locationCustom   = '';
				state.typeSlug         = '';
				state.categorySlug     = '';
				state.expSlug          = '';
				state.tags             = '';
				state.remote           = false;
				state.customFields     = {};
			},
		},
	}
);
