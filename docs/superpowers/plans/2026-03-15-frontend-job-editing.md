# Frontend Job Editing Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow employers and admins to edit existing job listings from the frontend `/post-a-job/?edit=<id>` URL, bypassing wp-admin entirely.

**Architecture:** The existing `wcb/job-form` block doubles as an edit form when `?edit=<id>` is in the URL. `render.php` detects the param, verifies ownership (post_author with `wcb_post_jobs` OR admin with `wcb_manage_settings`), pre-populates `$wcb_initial_state` with the existing job's data, and sets `jobId`. `view.js` sends `PATCH /wcb/v1/jobs/{jobId}` when `state.jobId` is non-zero, `POST /wcb/v1/jobs` otherwise. The employer dashboard's `editUrl` changes from wp-admin to the frontend page URL.

**Tech Stack:** PHP 8.1, WordPress Interactivity API, WordPress REST API (PATCH endpoint already exists and enforces ownership at `wcb/v1/jobs/{id}`)

**Security model (two layers):**
1. `render.php` — denies page render if user is not owner or admin
2. `PATCH /wcb/v1/jobs/{id}` — `update_item_permissions_check()` already checks `post_author === current_user || wcb_manage_settings`. No changes needed to the API.

---

## File Map

| File | Change |
|------|--------|
| `blocks/job-form/render.php` | Detect `?edit=<id>`, verify ownership, pre-populate state, add `data-wp-bind--value` to selects/textarea, dynamic submit/success labels |
| `blocks/job-form/view.js` | Branch `submitJob` to PATCH vs POST on `state.jobId` |
| `api/endpoints/class-employers-endpoint.php` | Change `editUrl` from wp-admin to frontend job-form page with `?edit={id}` |

---

## Task 1: Pre-populate state in render.php for edit mode

**Files:**
- Modify: `blocks/job-form/render.php`

- [ ] **Step 1: Add `jobId: 0` to the default initial state array**

In the `apply_filters( 'wcb_job_form_initial_state', array( ... ) )` call (around line 92–121), add these three keys to the default array:

```php
'jobId'          => 0,
'submitLabel'    => __( 'Post Job', 'wp-career-board' ),
'submittingLabel'=> __( 'Posting\u2026', 'wp-career-board' ),
'successMessage' => __( 'Job posted successfully!', 'wp-career-board' ),
```

(Unicode escape for `…` so PHP string stays ASCII-safe.)

- [ ] **Step 2: Add edit-mode detection block after the ability check (after line ~32)**

Insert this block immediately after the closing `}` of the existing `if ( ! is_user_logged_in() || ! $wcb_can_post_job )` guard:

```php
// ── Edit-mode detection ─────────────────────────────────────────────────────
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only render, no state change.
$wcb_edit_id  = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
$wcb_edit_job = null;

if ( $wcb_edit_id > 0 ) {
	$wcb_candidate = get_post( $wcb_edit_id );
	if ( $wcb_candidate instanceof \WP_Post && 'wcb_job' === $wcb_candidate->post_type ) {
		$wcb_is_owner = ( (int) $wcb_candidate->post_author === $wcb_user_id ) && $wcb_can_post_job;
		$wcb_is_admin = function_exists( 'wp_is_ability_granted' )
			? wp_is_ability_granted( 'wcb_manage_settings' )
			: current_user_can( 'manage_options' );

		if ( $wcb_is_owner || $wcb_is_admin ) {
			$wcb_edit_job = $wcb_candidate;
		} else {
			echo '<p>' . esc_html__( 'You do not have permission to edit this job.', 'wp-career-board' ) . '</p>';
			return;
		}
	}
}
```

Note: `$wcb_user_id` and `$wcb_can_post_job` are already defined just above this point in the file.

- [ ] **Step 3: Merge edit-mode state after the `apply_filters` call**

After the `$wcb_initial_state = apply_filters(...)` block (after line ~121), add:

```php
// ── Edit mode: merge existing job data into state ───────────────────────────
if ( null !== $wcb_edit_job ) {
	$wcb_tag_terms  = wp_get_object_terms( $wcb_edit_job->ID, 'wcb_tag', array( 'fields' => 'slugs' ) );
	$wcb_cat_terms  = wp_get_object_terms( $wcb_edit_job->ID, 'wcb_category', array( 'fields' => 'slugs' ) );
	$wcb_type_terms = wp_get_object_terms( $wcb_edit_job->ID, 'wcb_job_type', array( 'fields' => 'slugs' ) );
	$wcb_loc_terms  = wp_get_object_terms( $wcb_edit_job->ID, 'wcb_location', array( 'fields' => 'slugs' ) );
	$wcb_exp_terms  = wp_get_object_terms( $wcb_edit_job->ID, 'wcb_experience', array( 'fields' => 'slugs' ) );

	$wcb_initial_state = array_merge(
		$wcb_initial_state,
		array(
			'jobId'          => $wcb_edit_job->ID,
			'title'          => $wcb_edit_job->post_title,
			'description'    => $wcb_edit_job->post_content,
			'salaryMin'      => (string) get_post_meta( $wcb_edit_job->ID, '_wcb_salary_min', true ),
			'salaryMax'      => (string) get_post_meta( $wcb_edit_job->ID, '_wcb_salary_max', true ),
			'currencyCode'   => get_post_meta( $wcb_edit_job->ID, '_wcb_salary_currency', true ) ?: 'USD',
			'remote'         => '1' === (string) get_post_meta( $wcb_edit_job->ID, '_wcb_remote', true ),
			'deadline'       => (string) get_post_meta( $wcb_edit_job->ID, '_wcb_deadline', true ),
			'applyUrl'       => (string) get_post_meta( $wcb_edit_job->ID, '_wcb_apply_url', true ),
			'applyEmail'     => (string) get_post_meta( $wcb_edit_job->ID, '_wcb_apply_email', true ),
			'categorySlug'   => ( ! is_wp_error( $wcb_cat_terms ) && ! empty( $wcb_cat_terms ) ) ? $wcb_cat_terms[0] : '',
			'typeSlug'       => ( ! is_wp_error( $wcb_type_terms ) && ! empty( $wcb_type_terms ) ) ? $wcb_type_terms[0] : '',
			'locationSlug'   => ( ! is_wp_error( $wcb_loc_terms ) && ! empty( $wcb_loc_terms ) ) ? $wcb_loc_terms[0] : '',
			'expSlug'        => ( ! is_wp_error( $wcb_exp_terms ) && ! empty( $wcb_exp_terms ) ) ? $wcb_exp_terms[0] : '',
			'tags'           => ( ! is_wp_error( $wcb_tag_terms ) ) ? implode( ', ', $wcb_tag_terms ) : '',
			'submitLabel'    => __( 'Update Job', 'wp-career-board' ),
			'submittingLabel'=> __( 'Updating\u2026', 'wp-career-board' ),
			'successMessage' => __( 'Job updated successfully!', 'wp-career-board' ),
		)
	);
}
```

- [ ] **Step 4: Add `data-wp-bind--value` to selects and textarea so pre-populated state renders**

Add the attribute to each element:

| Element ID | Add attribute |
|-----------|---------------|
| `#wcb-currency` select | `data-wp-bind--value="state.currencyCode"` |
| `#wcb-job-desc` textarea | `data-wp-bind--value="state.description"` |
| `#wcb-category` select | `data-wp-bind--value="state.categorySlug"` |
| `#wcb-job-type` select | `data-wp-bind--value="state.typeSlug"` |
| `#wcb-location` select | `data-wp-bind--value="state.locationSlug"` |
| `#wcb-experience` select | `data-wp-bind--value="state.expSlug"` |

Title (`#wcb-job-title`) and the date/url/email inputs already have `data-wp-bind--value`.

- [ ] **Step 5: Make submit button label and success message JS-driven**

In step 4 submit button, replace the static PHP text with `data-wp-text` bindings:

**Before (button label span):**
```html
<span class="wcb-btn__label"><?php esc_html_e( 'Post Job', 'wp-career-board' ); ?></span>
<span class="wcb-btn__spinner">
    <svg ...></svg>
    <?php esc_html_e( 'Posting\u2026', 'wp-career-board' ); ?>
</span>
```

**After:**
```html
<span class="wcb-btn__label" data-wp-text="state.submitLabel"></span>
<span class="wcb-btn__spinner">
    <svg ...></svg>
    <span data-wp-text="state.submittingLabel"></span>
</span>
```

In the success banner, replace the static PHP title:

**Before:**
```html
<p class="wcb-form-success__title">
    <?php esc_html_e( 'Job posted successfully!', 'wp-career-board' ); ?>
</p>
```

**After:**
```html
<p class="wcb-form-success__title" data-wp-text="state.successMessage"></p>
```

- [ ] **Step 6: Commit**

```bash
git add blocks/job-form/render.php
git commit -m "feat(wcb): T8 — render.php edit mode: ownership check, state pre-population, dynamic labels"
```

---

## Task 2: Branch submitJob in view.js to PATCH vs POST

**Files:**
- Modify: `blocks/job-form/view.js`

- [ ] **Step 1: Update the `submitJob` generator to use PATCH when editing**

Find the `fetch(` call in `submitJob` (currently around lines 213–223). Replace:

```js
const response = yield fetch(
    state.apiBase + '/jobs',
    {
        method:  'POST',
        headers: { ... },
        body: JSON.stringify( body ),
    }
);
```

With:

```js
const endpoint = state.jobId
    ? state.apiBase + '/jobs/' + state.jobId
    : state.apiBase + '/jobs';

const response = yield fetch(
    endpoint,
    {
        method:  state.jobId ? 'PATCH' : 'POST',
        headers: {
            'X-WP-Nonce':   state.nonce,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify( body ),
    }
);
```

Everything after the fetch (error handling, `state.jobUrl = data.permalink`, `state.submitted = true`) stays identical — the PATCH endpoint returns the same shape via `prepare_item_for_response_array()`.

- [ ] **Step 2: Commit**

```bash
git add blocks/job-form/view.js
git commit -m "feat(wcb): T8 — view.js: PATCH existing job vs POST new job based on state.jobId"
```

---

## Task 3: Change employer dashboard editUrl to frontend

**Files:**
- Modify: `api/endpoints/class-employers-endpoint.php`

- [ ] **Step 1: Add a private helper method `get_job_form_page_url()`**

Add this method to the `Employers_Endpoint` class (find a suitable place near the other private helpers):

```php
/**
 * Resolve the permalink of the page containing the wcb/job-form block.
 *
 * Searches published pages for the block markup. Falls back to home_url()
 * if no page is found. Result is used to build frontend edit links.
 *
 * @since 1.0.0
 * @return string Absolute URL, trailing-slash-normalized by get_permalink().
 */
private function get_job_form_page_url(): string {
    $pages = get_posts(
        array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            's'              => '<!-- wp:wcb/job-form',
        )
    );
    return ! empty( $pages ) ? (string) get_permalink( $pages[0]->ID ) : home_url( '/' );
}
```

- [ ] **Step 2: Pre-resolve the URL once outside the `array_map` closure**

In the method that builds the jobs list (around line 250), before the `$items = array_map(` call, add:

```php
$wcb_job_form_url = $this->get_job_form_page_url();
```

Then update the `array_map` closure signature to capture it:

```php
$items = array_map(
    static function ( \WP_Post $p ) use ( $app_counts, $wcb_job_form_url ): array {
```

- [ ] **Step 3: Replace the `editUrl` value in the closure**

Find line ~269:
```php
'editUrl' => admin_url( 'post.php?post=' . $p->ID . '&action=edit' ),
```

Replace with:
```php
'editUrl' => add_query_arg( 'edit', $p->ID, $wcb_job_form_url ),
```

- [ ] **Step 4: Commit**

```bash
git add api/endpoints/class-employers-endpoint.php
git commit -m "feat(wcb): T8 — employers endpoint: editUrl now points to frontend job-form page"
```

---

## Task 4: WPCS and browser verification

- [ ] **Step 1: Run WPCS auto-fix on all changed files**

```
mcp__wpcs__wpcs_fix_file → blocks/job-form/render.php
mcp__wpcs__wpcs_fix_file → blocks/job-form/view.js
mcp__wpcs__wpcs_fix_file → api/endpoints/class-employers-endpoint.php
```

- [ ] **Step 2: Verify zero errors with real phpcs check**

```bash
phpcs --standard=phpcs.xml \
  blocks/job-form/render.php \
  api/endpoints/class-employers-endpoint.php
```

Must report `0 errors | 0 warnings`.

- [ ] **Step 3: Browser test — happy path (owner edits own job)**

1. Log in as employer → employer dashboard → "Edit" on an existing job
2. Confirm URL is `/post-a-job/?edit=<id>` (not wp-admin)
3. Confirm form pre-fills: title, description, salary values, remote checkbox, deadline, apply URL, apply email, and all four select dropdowns show the correct options
4. Step through to step 4 — confirm preview card reflects existing values
5. Change the job title, submit → confirm success banner shows "Job updated successfully!"
6. Visit the job listing permalink → confirm title updated

- [ ] **Step 4: Browser test — unauthorized access**

Log in as a different employer (or a candidate). Navigate to `/post-a-job/?edit=<other-users-job-id>`. Confirm the block shows "You do not have permission to edit this job." and no form is rendered.

- [ ] **Step 5: Browser test — admin can edit any job**

Log in as admin, navigate to `/post-a-job/?edit=<any-job-id>`. Confirm form pre-fills and update succeeds.

- [ ] **Step 6: Commit any WPCS auto-fix changes if not already committed**

```bash
git add -p
git commit -m "fix(wcb): T8 — WPCS formatting corrections after auto-fix"
```
