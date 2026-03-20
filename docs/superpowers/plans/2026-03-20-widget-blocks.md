# Widget Blocks + Hero Search Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add 4 static sidebar-widget blocks + 1 hero search block to the free plugin, 3 sidebar-widget blocks + 1 hero search block to the pro plugin, extend the existing featured-jobs block, add Featured meta to company/resume admin screens, audit i18n, and rename dashboard pages.

**Architecture:** All new blocks are fully static (no Interactivity API). PHP `render.php` queries data and outputs HTML using design-system tokens. `index.js` registers the block with InspectorControls using global `wp.*` — no build step required. BuddyPress is optional throughout; `get_avatar_url()` handles BP automatically via hooks.

**Tech Stack:** PHP 8.1+, WordPress 6.9+, Gutenberg blocks (apiVersion 3), `wp.*` globals for editor scripts, CSS custom properties from `theme.json`, WP-CLI for page/menu updates.

---

## File Map

### Free plugin — wp-career-board

| Action | File |
|--------|------|
| Modify | `admin/class-admin-meta-boxes.php` |
| Modify | `blocks/featured-jobs/block.json` |
| Modify | `blocks/featured-jobs/render.php` |
| Modify | `blocks/featured-jobs/index.js` |
| Create | `blocks/recent-jobs/block.json` |
| Create | `blocks/recent-jobs/render.php` |
| Create | `blocks/recent-jobs/index.js` |
| Create | `blocks/recent-jobs/style.css` |
| Create | `blocks/job-stats/block.json` |
| Create | `blocks/job-stats/render.php` |
| Create | `blocks/job-stats/index.js` |
| Create | `blocks/job-stats/style.css` |
| Create | `blocks/job-search-hero/block.json` |
| Create | `blocks/job-search-hero/render.php` |
| Create | `blocks/job-search-hero/index.js` |
| Create | `blocks/job-search-hero/style.css` |
| Modify | `core/class-plugin.php` |

### Pro plugin — wp-career-board-pro

| Action | File |
|--------|------|
| Modify | `modules/resume/class-resume-module.php` |
| Create | `blocks/open-to-work/block.json` |
| Create | `blocks/open-to-work/render.php` |
| Create | `blocks/open-to-work/index.js` |
| Create | `blocks/open-to-work/style.css` |
| Create | `blocks/featured-companies/block.json` |
| Create | `blocks/featured-companies/render.php` |
| Create | `blocks/featured-companies/index.js` |
| Create | `blocks/featured-companies/style.css` |
| Create | `blocks/featured-candidates/block.json` |
| Create | `blocks/featured-candidates/render.php` |
| Create | `blocks/featured-candidates/index.js` |
| Create | `blocks/featured-candidates/style.css` |
| Create | `blocks/resume-search-hero/block.json` |
| Create | `blocks/resume-search-hero/render.php` |
| Create | `blocks/resume-search-hero/index.js` |
| Create | `blocks/resume-search-hero/style.css` |
| Modify | `core/class-pro-plugin.php` |

---

## Task 1 — Add Featured checkbox to company admin meta box

**Plugin:** wp-career-board
**Files:** Modify `admin/class-admin-meta-boxes.php`

The `_wcb_featured` meta key exists on `wcb_job` posts but not on `wcb_company`. Without this, `wcb/featured-companies` will always return empty. Add a "Featured company" checkbox in `render_company_details_box()` and save it in `save_company_meta()`.

- [ ] **Read the file** to understand the exact insertion points before editing:
  ```bash
  grep -n "wcb_tagline\|wcb_website\|save_company_meta\|render_company_details" \
    admin/class-admin-meta-boxes.php
  ```

- [ ] **Add the `_wcb_featured` read** in `render_company_details_box()`, right after the nonce field (the very first `get_post_meta` call in that method):
  ```php
  $wcb_company_featured = '1' === (string) get_post_meta( $post->ID, '_wcb_featured', true );
  ```

- [ ] **Add the checkbox HTML** inside `render_company_details_box()`, as the first field before the website/tagline fields (matching the job meta box pattern):
  ```php
  <p>
  	<label>
  		<input type="checkbox" name="wcb_company_featured" value="1"
  			<?php checked( $wcb_company_featured ); ?> />
  		<?php esc_html_e( 'Featured company', 'wp-career-board' ); ?>
  	</label>
  </p>
  ```

- [ ] **Save the checkbox** in `save_company_meta()`, before the closing brace of the method (after the `$wcb_trust` block):
  ```php
  $wcb_featured = isset( $_POST['wcb_company_featured'] ) && '1' === $_POST['wcb_company_featured'] ? '1' : '0';
  update_post_meta( $post_id, '_wcb_featured', $wcb_featured );
  ```

- [ ] **WPCS fix:**
  ```bash
  cd /Users/varundubey/Local\ Sites/job-portal/app/public/wp-content/plugins/wp-career-board
  # Run via MCP: mcp__wpcs__wpcs_fix_file on admin/class-admin-meta-boxes.php
  # Then: mcp__wpcs__wpcs_check_staged
  ```

- [ ] **Browser verify:** Open `http://job-portal.local/wp-admin/post.php?post=160&action=edit` (Stripe company). Confirm "Featured company" checkbox appears in the company meta box. Check it, save, reopen — confirm it persists.

- [ ] **Commit:**
  ```bash
  git add admin/class-admin-meta-boxes.php
  git commit -m "feat(wcb): add Featured checkbox to company meta box"
  ```

---

## Task 2 — Extend featured-jobs block with title / view-all attributes

**Plugin:** wp-career-board
**Files:** Modify `blocks/featured-jobs/block.json`, `render.php`, `index.js`

The block exists but has no title override, no "View all" toggle, and no custom URL. Adding these three attributes makes it consistent with all other sidebar widgets.

- [ ] **Add attributes to `block.json`** — add after the existing `perPage` attribute:
  ```json
  "title": {
    "type": "string",
    "default": ""
  },
  "showViewAll": {
    "type": "boolean",
    "default": true
  },
  "viewAllUrl": {
    "type": "string",
    "default": ""
  }
  ```

- [ ] **Update `render.php`** — read new attributes and render them. After `$wcb_per_page`:
  ```php
  $wcb_title        = trim( (string) ( $attributes['title'] ?? '' ) );
  $wcb_show_all     = (bool) ( $attributes['showViewAll'] ?? true );
  $wcb_view_all_url = trim( (string) ( $attributes['viewAllUrl'] ?? '' ) );

  if ( ! $wcb_view_all_url ) {
  	$wcb_settings     = (array) get_option( 'wcb_settings', array() );
  	$wcb_view_all_url = ! empty( $wcb_settings['jobs_archive_page'] )
  		? (string) get_permalink( (int) $wcb_settings['jobs_archive_page'] )
  		: '';
  }
  ```

  Replace the existing heading in the wrapper:
  ```php
  <h2 class="wcb-featured-title">
  	<?php echo esc_html( $wcb_title ?: __( 'Featured Jobs', 'wp-career-board' ) ); ?>
  </h2>
  ```

  Add after the closing `</div>` of `.wcb-featured-grid`:
  ```php
  <?php if ( $wcb_show_all && $wcb_view_all_url ) : ?>
  	<a class="wcb-widget-view-all" href="<?php echo esc_url( $wcb_view_all_url ); ?>">
  		<?php esc_html_e( 'View all jobs →', 'wp-career-board' ); ?>
  	</a>
  <?php endif; ?>
  ```

- [ ] **Update `index.js`** — add InspectorControls with the three new fields (replace the entire file):
  ```js
  ( function () {
  	var el                = wp.element.createElement;
  	var InspectorControls = wp.blockEditor.InspectorControls;
  	var PanelBody         = wp.components.PanelBody;
  	var TextControl       = wp.components.TextControl;
  	var ToggleControl     = wp.components.ToggleControl;
  	var SelectControl     = wp.components.SelectControl;

  	wp.blocks.registerBlockType( 'wp-career-board/featured-jobs', {
  		edit: function ( props ) {
  			var attr    = props.attributes;
  			var setAttr = props.setAttributes;

  			return [
  				el( InspectorControls, { key: 'inspector' },
  					el( PanelBody, { title: 'Settings', initialOpen: true },
  						el( SelectControl, {
  							label:    'Number of jobs',
  							value:    attr.perPage,
  							options:  [ { label: '3', value: 3 }, { label: '5', value: 5 }, { label: '10', value: 10 } ],
  							onChange: function ( val ) { setAttr( { perPage: parseInt( val, 10 ) } ); },
  						} ),
  						el( TextControl, {
  							label:    'Section title',
  							value:    attr.title,
  							onChange: function ( val ) { setAttr( { title: val } ); },
  						} ),
  						el( ToggleControl, {
  							label:    'Show "View all" link',
  							checked:  attr.showViewAll,
  							onChange: function ( val ) { setAttr( { showViewAll: val } ); },
  						} ),
  						attr.showViewAll && el( TextControl, {
  							label:    '"View all" URL (leave blank to auto-detect)',
  							value:    attr.viewAllUrl,
  							onChange: function ( val ) { setAttr( { viewAllUrl: val } ); },
  						} )
  					)
  				),
  				el( 'div', { key: 'preview', style: { padding: '12px 16px', background: '#f0f6fc', border: '1px dashed #93c5fd', borderRadius: '4px' } },
  					el( 'strong', { style: { color: '#1e40af', display: 'block' } }, 'WCB: Featured Jobs' ),
  					el( 'span', { style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } },
  						'Static featured job grid. Edit in inspector →' )
  				),
  			];
  		},
  	} );
  } )();
  ```

- [ ] **WPCS fix + check** (PHP files only):
  ```bash
  # mcp__wpcs__wpcs_fix_file on blocks/featured-jobs/render.php
  # mcp__wpcs__wpcs_check_staged
  ```

- [ ] **Browser verify:** Add the block to a test page, set a custom title and enable "View all". Save. Visit the page — confirm title and link appear.

- [ ] **Commit:**
  ```bash
  git add blocks/featured-jobs/block.json blocks/featured-jobs/render.php blocks/featured-jobs/index.js
  git commit -m "feat(wcb): extend featured-jobs block with title and view-all attributes"
  ```

---

## Task 3 — Build `wp-career-board/recent-jobs` block

**Plugin:** wp-career-board
**Files:** Create `blocks/recent-jobs/` (4 files)

- [ ] **Create `block.json`:**
  ```json
  {
    "$schema": "https://schemas.wp.org/trunk/block.json",
    "apiVersion": 3,
    "name": "wp-career-board/recent-jobs",
    "version": "0.1.0",
    "title": "Recent Jobs",
    "category": "widgets",
    "description": "Static sidebar widget listing the most recently published jobs.",
    "editorScript": "file:./index.js",
    "textdomain": "wp-career-board",
    "attributes": {
      "count": { "type": "integer", "default": 5 },
      "title": { "type": "string", "default": "" },
      "showViewAll": { "type": "boolean", "default": true },
      "viewAllUrl": { "type": "string", "default": "" }
    },
    "style": "file:./style.css",
    "render": "file:./render.php"
  }
  ```

- [ ] **Create `render.php`:**
  ```php
  <?php
  /**
   * Block render: wp-career-board/recent-jobs — static sidebar widget.
   *
   * @package WP_Career_Board
   * @since   1.0.0
   */

  declare( strict_types=1 );

  defined( 'ABSPATH' ) || exit;

  $wcb_count        = max( 1, (int) ( $attributes['count'] ?? 5 ) );
  $wcb_title        = trim( (string) ( $attributes['title'] ?? '' ) );
  $wcb_show_all     = (bool) ( $attributes['showViewAll'] ?? true );
  $wcb_view_all_url = trim( (string) ( $attributes['viewAllUrl'] ?? '' ) );

  if ( ! $wcb_view_all_url ) {
  	$wcb_settings     = (array) get_option( 'wcb_settings', array() );
  	$wcb_view_all_url = ! empty( $wcb_settings['jobs_archive_page'] )
  		? (string) get_permalink( (int) $wcb_settings['jobs_archive_page'] )
  		: '';
  }

  $wcb_jobs = get_posts(
  	array(
  		'post_type'   => 'wcb_job',
  		'post_status' => 'publish',
  		'numberposts' => $wcb_count,
  		'orderby'     => 'date',
  		'order'       => 'DESC',
  	)
  );

  if ( empty( $wcb_jobs ) ) {
  	return;
  }

  // Pre-fetch company thumbnails to avoid N+1 per card.
  $wcb_author_ids   = array_unique( array_map( fn( $p ) => (int) $p->post_author, $wcb_jobs ) );
  $wcb_company_map  = array(); // author_id => thumbnail_url|''
  foreach ( $wcb_author_ids as $wcb_aid ) {
  	$wcb_cid                   = (int) get_user_meta( $wcb_aid, '_wcb_company_id', true );
  	$wcb_thumb                 = $wcb_cid ? (string) get_the_post_thumbnail_url( $wcb_cid, 'thumbnail' ) : '';
  	$wcb_company_map[ $wcb_aid ] = $wcb_thumb;
  }
  ?>
  <div <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-recent-jobs' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

  	<div class="wcb-widget-header">
  		<h2 class="wcb-widget-title">
  			<?php echo esc_html( $wcb_title ?: __( 'Recent Jobs', 'wp-career-board' ) ); ?>
  		</h2>
  		<?php if ( $wcb_show_all && $wcb_view_all_url ) : ?>
  			<a class="wcb-widget-view-all" href="<?php echo esc_url( $wcb_view_all_url ); ?>">
  				<?php esc_html_e( 'View all →', 'wp-career-board' ); ?>
  			</a>
  		<?php endif; ?>
  	</div>

  	<ul class="wcb-job-widget-list">
  		<?php foreach ( $wcb_jobs as $wcb_job ) : ?>
  			<?php
  			$wcb_company_name  = (string) get_post_meta( $wcb_job->ID, '_wcb_company_name', true );
  			$wcb_thumb_url     = $wcb_company_map[ (int) $wcb_job->post_author ] ?? '';
  			$wcb_initial       = $wcb_company_name ? strtoupper( mb_substr( $wcb_company_name, 0, 1 ) ) : '?';
  			$wcb_loc_terms     = wp_get_object_terms( $wcb_job->ID, 'wcb_location', array( 'fields' => 'names' ) );
  			$wcb_location      = is_wp_error( $wcb_loc_terms ) ? '' : implode( ', ', $wcb_loc_terms );
  			$wcb_type_terms    = wp_get_object_terms( $wcb_job->ID, 'wcb_job_type', array( 'fields' => 'names' ) );
  			$wcb_job_type      = is_wp_error( $wcb_type_terms ) ? '' : ( $wcb_type_terms[0] ?? '' );
  			$wcb_posted_ago    = human_time_diff( (int) get_post_time( 'U', false, $wcb_job ), time() );
  			?>
  			<li class="wcb-job-widget-item">
  				<a class="wcb-job-widget-link" href="<?php echo esc_url( get_permalink( $wcb_job->ID ) ); ?>">
  					<span class="wcb-job-widget-logo" aria-hidden="true">
  						<?php if ( $wcb_thumb_url ) : ?>
  							<img src="<?php echo esc_url( $wcb_thumb_url ); ?>" alt="" width="16" height="16" loading="lazy" />
  						<?php else : ?>
  							<span class="wcb-job-widget-initial"><?php echo esc_html( $wcb_initial ); ?></span>
  						<?php endif; ?>
  					</span>
  					<span class="wcb-job-widget-body">
  						<span class="wcb-job-widget-name"><?php echo esc_html( $wcb_job->post_title ); ?></span>
  						<?php if ( $wcb_company_name ) : ?>
  							<span class="wcb-job-widget-company"><?php echo esc_html( $wcb_company_name ); ?></span>
  						<?php endif; ?>
  						<span class="wcb-job-widget-meta">
  							<?php if ( $wcb_location ) : ?>
  								<span class="wcb-badge wcb-badge--location"><?php echo esc_html( $wcb_location ); ?></span>
  							<?php endif; ?>
  							<?php if ( $wcb_job_type ) : ?>
  								<span class="wcb-badge wcb-badge--type"><?php echo esc_html( $wcb_job_type ); ?></span>
  							<?php endif; ?>
  							<span class="wcb-job-widget-age">
  								<?php
  								printf(
  									/* translators: %s: human-readable time difference e.g. "3 days" */
  									esc_html__( '%s ago', 'wp-career-board' ),
  									esc_html( $wcb_posted_ago )
  								);
  								?>
  							</span>
  						</span>
  					</span>
  				</a>
  			</li>
  		<?php endforeach; ?>
  	</ul>

  </div>
  ```

- [ ] **Create `index.js`:**
  ```js
  ( function () {
  	var el                = wp.element.createElement;
  	var InspectorControls = wp.blockEditor.InspectorControls;
  	var PanelBody         = wp.components.PanelBody;
  	var TextControl       = wp.components.TextControl;
  	var ToggleControl     = wp.components.ToggleControl;
  	var SelectControl     = wp.components.SelectControl;

  	wp.blocks.registerBlockType( 'wp-career-board/recent-jobs', {
  		edit: function ( props ) {
  			var attr    = props.attributes;
  			var setAttr = props.setAttributes;

  			return [
  				el( InspectorControls, { key: 'inspector' },
  					el( PanelBody, { title: 'Settings', initialOpen: true },
  						el( SelectControl, {
  							label:    'Number of jobs',
  							value:    attr.count,
  							options:  [ { label: '3', value: 3 }, { label: '5', value: 5 }, { label: '10', value: 10 } ],
  							onChange: function ( val ) { setAttr( { count: parseInt( val, 10 ) } ); },
  						} ),
  						el( TextControl, {
  							label:    'Section title',
  							value:    attr.title,
  							onChange: function ( val ) { setAttr( { title: val } ); },
  						} ),
  						el( ToggleControl, {
  							label:    'Show "View all" link',
  							checked:  attr.showViewAll,
  							onChange: function ( val ) { setAttr( { showViewAll: val } ); },
  						} ),
  						attr.showViewAll && el( TextControl, {
  							label:    '"View all" URL (leave blank to auto-detect)',
  							value:    attr.viewAllUrl,
  							onChange: function ( val ) { setAttr( { viewAllUrl: val } ); },
  						} )
  					)
  				),
  				el( 'div', { key: 'preview', style: { padding: '12px 16px', background: '#f0f6fc', border: '1px dashed #93c5fd', borderRadius: '4px' } },
  					el( 'strong', { style: { color: '#1e40af', display: 'block' } }, 'WCB: Recent Jobs' ),
  					el( 'span', { style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } },
  						'Static sidebar widget. Configure in inspector →' )
  				),
  			];
  		},
  	} );
  } )();
  ```

- [ ] **Create `style.css`:**
  ```css
  .wcb-recent-jobs {
  	padding: 0;
  }

  .wcb-widget-header {
  	display: flex;
  	align-items: baseline;
  	justify-content: space-between;
  	margin-bottom: 1rem;
  	gap: 0.5rem;
  }

  .wcb-widget-title {
  	font-size: 1rem;
  	font-weight: 600;
  	margin: 0;
  	color: var( --wcb-text, #1e293b );
  }

  .wcb-widget-view-all {
  	font-size: 0.8125rem;
  	color: var( --wcb-primary, #2563eb );
  	text-decoration: none;
  	white-space: nowrap;
  	flex-shrink: 0;
  }

  .wcb-widget-view-all:hover {
  	text-decoration: underline;
  }

  .wcb-job-widget-list {
  	list-style: none;
  	margin: 0;
  	padding: 0;
  	display: flex;
  	flex-direction: column;
  	gap: 0.625rem;
  }

  .wcb-job-widget-item {
  	margin: 0;
  }

  .wcb-job-widget-link {
  	display: flex;
  	align-items: flex-start;
  	gap: 0.625rem;
  	padding: 0.75rem;
  	border: 1px solid var( --wcb-border, #e2e8f0 );
  	border-radius: var( --wcb-radius, 8px );
  	background: var( --wcb-bg-subtle, #f8fafc );
  	text-decoration: none;
  	color: inherit;
  	transition: transform 0.15s ease, box-shadow 0.15s ease;
  }

  .wcb-job-widget-link:hover {
  	transform: translateY( -1px );
  	box-shadow: var( --wcb-shadow, 0 4px 12px rgba( 0, 0, 0, 0.08 ) );
  }

  .wcb-job-widget-logo {
  	flex-shrink: 0;
  	width: 16px;
  	height: 16px;
  	margin-top: 2px;
  	display: flex;
  	align-items: center;
  	justify-content: center;
  }

  .wcb-job-widget-logo img {
  	width: 16px;
  	height: 16px;
  	object-fit: contain;
  	border-radius: 2px;
  }

  .wcb-job-widget-initial {
  	display: flex;
  	align-items: center;
  	justify-content: center;
  	width: 16px;
  	height: 16px;
  	background: var( --wcb-primary, #2563eb );
  	color: #fff;
  	font-size: 9px;
  	font-weight: 700;
  	border-radius: 2px;
  }

  .wcb-job-widget-body {
  	display: flex;
  	flex-direction: column;
  	gap: 0.2rem;
  	min-width: 0;
  }

  .wcb-job-widget-name {
  	font-size: 0.875rem;
  	font-weight: 600;
  	color: var( --wcb-text, #1e293b );
  	white-space: nowrap;
  	overflow: hidden;
  	text-overflow: ellipsis;
  }

  .wcb-job-widget-company {
  	font-size: 0.75rem;
  	color: var( --wcb-text-muted, #64748b );
  }

  .wcb-job-widget-meta {
  	display: flex;
  	flex-wrap: wrap;
  	align-items: center;
  	gap: 0.25rem;
  	margin-top: 0.125rem;
  }

  .wcb-badge {
  	display: inline-block;
  	padding: 0.125rem 0.5rem;
  	border-radius: 999px;
  	font-size: 0.6875rem;
  	font-weight: 500;
  	line-height: 1.4;
  }

  .wcb-badge--location {
  	background: var( --wcb-bg-hover, #eff6ff );
  	color: var( --wcb-primary, #2563eb );
  }

  .wcb-badge--type {
  	background: #f0fdf4;
  	color: #16a34a;
  }

  .wcb-job-widget-age {
  	font-size: 0.6875rem;
  	color: var( --wcb-text-muted, #64748b );
  }
  ```

- [ ] **WPCS fix + check** on `render.php`.

- [ ] **Browser verify:** Add block to a page, visit frontend — confirm job list renders with logos/initials, badges, time ago.

- [ ] **Commit:**
  ```bash
  git add blocks/recent-jobs/
  git commit -m "feat(wcb): add recent-jobs sidebar widget block"
  ```

---

## Task 4 — Build `wp-career-board/job-stats` block

**Plugin:** wp-career-board
**Files:** Create `blocks/job-stats/` (4 files)

- [ ] **Create `block.json`:**
  ```json
  {
    "$schema": "https://schemas.wp.org/trunk/block.json",
    "apiVersion": 3,
    "name": "wp-career-board/job-stats",
    "version": "0.1.0",
    "title": "Job Stats",
    "category": "widgets",
    "description": "Horizontal stat strip showing total jobs, companies, and candidates.",
    "editorScript": "file:./index.js",
    "textdomain": "wp-career-board",
    "attributes": {
      "showJobs":       { "type": "boolean", "default": true },
      "showCompanies":  { "type": "boolean", "default": true },
      "showCandidates": { "type": "boolean", "default": true }
    },
    "style": "file:./style.css",
    "render": "file:./render.php"
  }
  ```

- [ ] **Create `render.php`:**
  ```php
  <?php
  /**
   * Block render: wp-career-board/job-stats — stat strip.
   *
   * @package WP_Career_Board
   * @since   1.0.0
   */

  declare( strict_types=1 );

  defined( 'ABSPATH' ) || exit;

  $wcb_show_jobs       = (bool) ( $attributes['showJobs']       ?? true );
  $wcb_show_companies  = (bool) ( $attributes['showCompanies']  ?? true );
  $wcb_show_candidates = (bool) ( $attributes['showCandidates'] ?? true );

  $wcb_stats = array();

  if ( $wcb_show_jobs ) {
  	$wcb_stats[] = array(
  		'count' => (int) wp_count_posts( 'wcb_job' )->publish,
  		'label' => __( 'Jobs', 'wp-career-board' ),
  		'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>',
  	);
  }

  if ( $wcb_show_companies ) {
  	$wcb_stats[] = array(
  		'count' => (int) wp_count_posts( 'wcb_company' )->publish,
  		'label' => __( 'Companies', 'wp-career-board' ),
  		'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
  	);
  }

  if ( $wcb_show_candidates ) {
  	$wcb_stats[] = array(
  		'count' => (int) wp_count_posts( 'wcb_resume' )->publish,
  		'label' => __( 'Candidates', 'wp-career-board' ),
  		'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  	);
  }

  if ( empty( $wcb_stats ) ) {
  	return;
  }
  ?>
  <div <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-job-stats' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
  	<?php foreach ( $wcb_stats as $wcb_stat ) : ?>
  		<div class="wcb-stat-item">
  			<span class="wcb-stat-icon">
  				<?php echo $wcb_stat['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  			</span>
  			<span class="wcb-stat-count"><?php echo esc_html( number_format_i18n( $wcb_stat['count'] ) ); ?></span>
  			<span class="wcb-stat-label"><?php echo esc_html( $wcb_stat['label'] ); ?></span>
  		</div>
  	<?php endforeach; ?>
  </div>
  ```

- [ ] **Create `index.js`** — three toggle controls:
  ```js
  ( function () {
  	var el                = wp.element.createElement;
  	var InspectorControls = wp.blockEditor.InspectorControls;
  	var PanelBody         = wp.components.PanelBody;
  	var ToggleControl     = wp.components.ToggleControl;

  	wp.blocks.registerBlockType( 'wp-career-board/job-stats', {
  		edit: function ( props ) {
  			var attr    = props.attributes;
  			var setAttr = props.setAttributes;

  			return [
  				el( InspectorControls, { key: 'inspector' },
  					el( PanelBody, { title: 'Visible stats', initialOpen: true },
  						el( ToggleControl, { label: 'Show Jobs count',       checked: attr.showJobs,       onChange: function ( v ) { setAttr( { showJobs: v } ); } } ),
  						el( ToggleControl, { label: 'Show Companies count',  checked: attr.showCompanies,  onChange: function ( v ) { setAttr( { showCompanies: v } ); } } ),
  						el( ToggleControl, { label: 'Show Candidates count', checked: attr.showCandidates, onChange: function ( v ) { setAttr( { showCandidates: v } ); } } )
  					)
  				),
  				el( 'div', { key: 'preview', style: { padding: '12px 16px', background: '#f0f6fc', border: '1px dashed #93c5fd', borderRadius: '4px' } },
  					el( 'strong', { style: { color: '#1e40af', display: 'block' } }, 'WCB: Job Stats' ),
  					el( 'span', { style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } }, 'Stat strip — Jobs · Companies · Candidates' )
  				),
  			];
  		},
  	} );
  } )();
  ```

- [ ] **Create `style.css`:**
  ```css
  .wcb-job-stats {
  	display: flex;
  	gap: 1rem;
  }

  .wcb-stat-item {
  	flex: 1;
  	display: flex;
  	flex-direction: column;
  	align-items: center;
  	gap: 0.25rem;
  	padding: 1.25rem 0.75rem;
  	border: 1px solid var( --wcb-border, #e2e8f0 );
  	border-radius: var( --wcb-radius, 8px );
  	background: var( --wcb-bg-subtle, #f8fafc );
  	text-align: center;
  }

  .wcb-stat-icon {
  	color: var( --wcb-primary, #2563eb );
  	display: flex;
  	align-items: center;
  }

  .wcb-stat-count {
  	font-size: 2rem;
  	font-weight: 700;
  	line-height: 1;
  	color: var( --wcb-text, #1e293b );
  }

  .wcb-stat-label {
  	font-size: 0.8125rem;
  	color: var( --wcb-text-muted, #64748b );
  }

  @media ( max-width: 640px ) {
  	.wcb-job-stats {
  		flex-direction: column;
  	}
  }
  ```

- [ ] **WPCS fix + check** on `render.php`.

- [ ] **Browser verify:** Add to a page — confirm 3 stat cards render with icons, formatted numbers, labels.

- [ ] **Commit:**
  ```bash
  git add blocks/job-stats/
  git commit -m "feat(wcb): add job-stats sidebar widget block"
  ```

---

## Task 5 — Build `wp-career-board/job-search-hero` block

**Plugin:** wp-career-board
**Files:** Create `blocks/job-search-hero/` (4 files)

- [ ] **Create `block.json`:**
  ```json
  {
    "$schema": "https://schemas.wp.org/trunk/block.json",
    "apiVersion": 3,
    "name": "wp-career-board/job-search-hero",
    "version": "0.1.0",
    "title": "Job Search Hero",
    "category": "widgets",
    "description": "Full-width job search form with optional filters. Horizontal or vertical layout.",
    "editorScript": "file:./index.js",
    "textdomain": "wp-career-board",
    "attributes": {
      "layout":             { "type": "string",  "default": "horizontal", "enum": ["horizontal","vertical"] },
      "placeholder":        { "type": "string",  "default": "" },
      "buttonLabel":        { "type": "string",  "default": "" },
      "showCategoryFilter": { "type": "boolean", "default": true },
      "showLocationFilter": { "type": "boolean", "default": true },
      "showJobTypeFilter":  { "type": "boolean", "default": true }
    },
    "style": "file:./style.css",
    "render": "file:./render.php"
  }
  ```

- [ ] **Create `render.php`:**
  ```php
  <?php
  /**
   * Block render: wp-career-board/job-search-hero — hero search form.
   *
   * Submits a plain GET form to the jobs archive page. Params consumed by
   * wp-career-board/job-listings and wp-career-board/job-filters:
   *   wcb_search, wcb_category, wcb_location, wcb_job_type
   *
   * @package WP_Career_Board
   * @since   1.0.0
   */

  declare( strict_types=1 );

  defined( 'ABSPATH' ) || exit;

  // phpcs:disable WordPress.Security.NonceVerification.Recommended
  $wcb_layout        = in_array( (string) ( $attributes['layout'] ?? 'horizontal' ), array( 'horizontal', 'vertical' ), true )
  	? (string) $attributes['layout']
  	: 'horizontal';
  $wcb_placeholder   = trim( (string) ( $attributes['placeholder'] ?? '' ) ) ?: __( 'Search jobs…', 'wp-career-board' );
  $wcb_button_label  = trim( (string) ( $attributes['buttonLabel'] ?? '' ) ) ?: __( 'Search', 'wp-career-board' );
  $wcb_show_category = (bool) ( $attributes['showCategoryFilter'] ?? true );
  $wcb_show_location = (bool) ( $attributes['showLocationFilter'] ?? true );
  $wcb_show_type     = (bool) ( $attributes['showJobTypeFilter']  ?? true );

  $wcb_settings     = (array) get_option( 'wcb_settings', array() );
  $wcb_action_url   = ! empty( $wcb_settings['jobs_archive_page'] )
  	? (string) get_permalink( (int) $wcb_settings['jobs_archive_page'] )
  	: home_url( '/' );

  // Pre-populate from current GET params for coexistence with job-filters block.
  $wcb_current_search   = isset( $_GET['wcb_search'] )   ? sanitize_text_field( wp_unslash( $_GET['wcb_search'] ) )   : '';
  $wcb_current_category = isset( $_GET['wcb_category'] ) ? sanitize_text_field( wp_unslash( $_GET['wcb_category'] ) ) : '';
  $wcb_current_location = isset( $_GET['wcb_location'] ) ? sanitize_text_field( wp_unslash( $_GET['wcb_location'] ) ) : '';
  $wcb_current_type     = isset( $_GET['wcb_job_type'] ) ? sanitize_text_field( wp_unslash( $_GET['wcb_job_type'] ) ) : '';
  // phpcs:enable WordPress.Security.NonceVerification.Recommended

  // Load taxonomy terms for filter dropdowns.
  // Registered taxonomy slug is 'wcb_category' (confirmed in core/class-plugin.php).
  $wcb_categories = $wcb_show_category ? get_terms( array( 'taxonomy' => 'wcb_category', 'hide_empty' => true ) ) : array();
  $wcb_locations  = $wcb_show_location ? get_terms( array( 'taxonomy' => 'wcb_location', 'hide_empty' => true ) ) : array();
  $wcb_job_types  = $wcb_show_type     ? get_terms( array( 'taxonomy' => 'wcb_job_type', 'hide_empty' => true ) ) : array();

  $wcb_categories = is_wp_error( $wcb_categories ) ? array() : $wcb_categories;
  $wcb_locations  = is_wp_error( $wcb_locations )  ? array() : $wcb_locations;
  $wcb_job_types  = is_wp_error( $wcb_job_types )  ? array() : $wcb_job_types;
  ?>
  <div <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-search-hero wcb-search-hero--' . esc_attr( $wcb_layout ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
  	<form class="wcb-search-hero__form" method="GET" action="<?php echo esc_url( $wcb_action_url ); ?>" role="search">

  		<div class="wcb-search-hero__field wcb-search-hero__field--keyword">
  			<label class="screen-reader-text" for="wcb-hero-search">
  				<?php esc_html_e( 'Search jobs', 'wp-career-board' ); ?>
  			</label>
  			<input
  				id="wcb-hero-search"
  				type="search"
  				name="wcb_search"
  				class="wcb-search-hero__input"
  				placeholder="<?php echo esc_attr( $wcb_placeholder ); ?>"
  				value="<?php echo esc_attr( $wcb_current_search ); ?>"
  			/>
  		</div>

  		<?php if ( $wcb_show_category && ! empty( $wcb_categories ) ) : ?>
  			<div class="wcb-search-hero__field wcb-search-hero__field--select">
  				<label class="screen-reader-text" for="wcb-hero-category">
  					<?php esc_html_e( 'Job category', 'wp-career-board' ); ?>
  				</label>
  				<select id="wcb-hero-category" name="wcb_category" class="wcb-search-hero__select">
  					<option value=""><?php esc_html_e( 'All Categories', 'wp-career-board' ); ?></option>
  					<?php foreach ( $wcb_categories as $wcb_term ) : ?>
  						<option value="<?php echo esc_attr( $wcb_term->slug ); ?>" <?php selected( $wcb_current_category, $wcb_term->slug ); ?>>
  							<?php echo esc_html( $wcb_term->name ); ?>
  						</option>
  					<?php endforeach; ?>
  				</select>
  			</div>
  		<?php endif; ?>

  		<?php if ( $wcb_show_location && ! empty( $wcb_locations ) ) : ?>
  			<div class="wcb-search-hero__field wcb-search-hero__field--select">
  				<label class="screen-reader-text" for="wcb-hero-location">
  					<?php esc_html_e( 'Location', 'wp-career-board' ); ?>
  				</label>
  				<select id="wcb-hero-location" name="wcb_location" class="wcb-search-hero__select">
  					<option value=""><?php esc_html_e( 'All Locations', 'wp-career-board' ); ?></option>
  					<?php foreach ( $wcb_locations as $wcb_term ) : ?>
  						<option value="<?php echo esc_attr( $wcb_term->slug ); ?>" <?php selected( $wcb_current_location, $wcb_term->slug ); ?>>
  							<?php echo esc_html( $wcb_term->name ); ?>
  						</option>
  					<?php endforeach; ?>
  				</select>
  			</div>
  		<?php endif; ?>

  		<?php if ( $wcb_show_type && ! empty( $wcb_job_types ) ) : ?>
  			<div class="wcb-search-hero__field wcb-search-hero__field--select">
  				<label class="screen-reader-text" for="wcb-hero-type">
  					<?php esc_html_e( 'Job type', 'wp-career-board' ); ?>
  				</label>
  				<select id="wcb-hero-type" name="wcb_job_type" class="wcb-search-hero__select">
  					<option value=""><?php esc_html_e( 'All Types', 'wp-career-board' ); ?></option>
  					<?php foreach ( $wcb_job_types as $wcb_term ) : ?>
  						<option value="<?php echo esc_attr( $wcb_term->slug ); ?>" <?php selected( $wcb_current_type, $wcb_term->slug ); ?>>
  							<?php echo esc_html( $wcb_term->name ); ?>
  						</option>
  					<?php endforeach; ?>
  				</select>
  			</div>
  		<?php endif; ?>

  		<div class="wcb-search-hero__field wcb-search-hero__field--submit">
  			<button type="submit" class="wcb-search-hero__button">
  				<?php echo esc_html( $wcb_button_label ); ?>
  			</button>
  		</div>

  	</form>
  </div>
  ```

- [ ] **Create `index.js`:**
  ```js
  ( function () {
  	var el                = wp.element.createElement;
  	var InspectorControls = wp.blockEditor.InspectorControls;
  	var PanelBody         = wp.components.PanelBody;
  	var SelectControl     = wp.components.SelectControl;
  	var TextControl       = wp.components.TextControl;
  	var ToggleControl     = wp.components.ToggleControl;

  	wp.blocks.registerBlockType( 'wp-career-board/job-search-hero', {
  		edit: function ( props ) {
  			var attr    = props.attributes;
  			var setAttr = props.setAttributes;

  			return [
  				el( InspectorControls, { key: 'inspector' },
  					el( PanelBody, { title: 'Layout', initialOpen: true },
  						el( SelectControl, {
  							label:    'Layout',
  							value:    attr.layout,
  							options:  [ { label: 'Horizontal', value: 'horizontal' }, { label: 'Vertical', value: 'vertical' } ],
  							onChange: function ( val ) { setAttr( { layout: val } ); },
  						} )
  					),
  					el( PanelBody, { title: 'Labels', initialOpen: false },
  						el( TextControl, { label: 'Search placeholder',    value: attr.placeholder,  onChange: function ( v ) { setAttr( { placeholder: v } ); } } ),
  						el( TextControl, { label: 'Button label',          value: attr.buttonLabel,  onChange: function ( v ) { setAttr( { buttonLabel: v } ); } } )
  					),
  					el( PanelBody, { title: 'Filters', initialOpen: true },
  						el( ToggleControl, { label: 'Show category filter', checked: attr.showCategoryFilter, onChange: function ( v ) { setAttr( { showCategoryFilter: v } ); } } ),
  						el( ToggleControl, { label: 'Show location filter', checked: attr.showLocationFilter, onChange: function ( v ) { setAttr( { showLocationFilter: v } ); } } ),
  						el( ToggleControl, { label: 'Show job type filter', checked: attr.showJobTypeFilter,  onChange: function ( v ) { setAttr( { showJobTypeFilter: v } ); } } )
  					)
  				),
  				el( 'div', { key: 'preview', style: { padding: '12px 16px', background: '#f0f6fc', border: '1px dashed #93c5fd', borderRadius: '4px' } },
  					el( 'strong', { style: { color: '#1e40af', display: 'block' } }, 'WCB: Job Search Hero (' + attr.layout + ')' ),
  					el( 'span', { style: { color: '#64748b', fontSize: '12px', marginTop: '4px', display: 'block' } }, 'Renders as a GET search form on the frontend.' )
  				),
  			];
  		},
  	} );
  } )();
  ```

- [ ] **Create `style.css`:**
  ```css
  .wcb-search-hero__form {
  	display: flex;
  	gap: 0.625rem;
  	align-items: stretch;
  }

  .wcb-search-hero--vertical .wcb-search-hero__form {
  	flex-direction: column;
  }

  .wcb-search-hero__field--keyword {
  	flex: 1;
  	min-width: 0;
  }

  .wcb-search-hero__field--select {
  	flex-shrink: 0;
  	width: 160px;
  }

  .wcb-search-hero--vertical .wcb-search-hero__field--keyword,
  .wcb-search-hero--vertical .wcb-search-hero__field--select,
  .wcb-search-hero--vertical .wcb-search-hero__field--submit {
  	width: 100%;
  }

  .wcb-search-hero__input,
  .wcb-search-hero__select {
  	width: 100%;
  	height: 48px;
  	padding: 0 1rem;
  	border: 1px solid var( --wcb-border, #e2e8f0 );
  	border-radius: var( --wcb-radius, 8px );
  	font-size: 0.9375rem;
  	background: #fff;
  	color: var( --wcb-text, #1e293b );
  	appearance: auto;
  }

  .wcb-search-hero__input:focus,
  .wcb-search-hero__select:focus {
  	outline: 2px solid var( --wcb-primary, #2563eb );
  	outline-offset: 1px;
  }

  .wcb-search-hero__button {
  	height: 48px;
  	padding: 0 1.5rem;
  	background: var( --wcb-primary, #2563eb );
  	color: #fff;
  	border: none;
  	border-radius: var( --wcb-radius, 8px );
  	font-size: 0.9375rem;
  	font-weight: 600;
  	cursor: pointer;
  	white-space: nowrap;
  	transition: opacity 0.15s ease;
  }

  .wcb-search-hero__button:hover {
  	opacity: 0.9;
  }

  .wcb-search-hero--vertical .wcb-search-hero__button {
  	width: 100%;
  }

  @media ( max-width: 640px ) {
  	.wcb-search-hero__form {
  		flex-direction: column;
  	}

  	.wcb-search-hero__field--select {
  		width: 100%;
  	}
  }
  ```

- [ ] **WPCS fix + check** on `render.php`.

- [ ] **Browser verify:** Add block to a test page in horizontal mode. Submit form — confirm redirect to jobs archive with correct `?wcb_search=` param. Switch to vertical in inspector — confirm layout changes.

- [ ] **Commit:**
  ```bash
  git add blocks/job-search-hero/
  git commit -m "feat(wcb): add job-search-hero block with horizontal/vertical layout"
  ```

---

## Task 6 — Register new free blocks in class-plugin.php

**Plugin:** wp-career-board
**Files:** Modify `core/class-plugin.php`

- [ ] **Add three entries** to the `$blocks` array in `register_blocks()`:
  ```php
  'recent-jobs',
  'job-stats',
  'job-search-hero',
  ```

- [ ] **WPCS fix + check** on `core/class-plugin.php`.

- [ ] **Browser verify:** Visit `http://job-portal.local/wp-admin/` → new post → Block inserter → confirm all 3 new blocks appear in the Widgets category alongside Featured Jobs.

- [ ] **Commit:**
  ```bash
  git add core/class-plugin.php
  git commit -m "feat(wcb): register recent-jobs, job-stats, job-search-hero blocks"
  ```

---

## Task 7 — Add Featured checkbox to resume admin meta box (Pro)

**Plugin:** wp-career-board-pro
**Files:** Modify `modules/resume/class-resume-module.php`

The `_wcb_featured` meta key does not exist on `wcb_resume` posts. Without this the `wcb/featured-candidates` block will always return empty.

- [ ] **Add the `_wcb_featured` read** inside `render_details_meta_box()`, after the `$owner` and `$summary` reads:
  ```php
  $featured = '1' === (string) get_post_meta( $post->ID, '_wcb_featured', true );
  ```

- [ ] **Add the checkbox HTML** inside `render_details_meta_box()`, after the existing summary textarea:
  ```php
  echo '<p><label>';
  echo '<input type="checkbox" name="wcbp_resume_featured" value="1" ' . checked( $featured, true, false ) . ' /> ';
  echo esc_html__( 'Featured candidate', 'wp-career-board-pro' );
  echo '</label></p>';
  ```

- [ ] **Save in `save_meta_box_data()`**, before the closing brace of the method, after the existing summary save block:
  ```php
  // Save featured flag.
  $featured_val = isset( $_POST['wcbp_resume_featured'] ) && '1' === $_POST['wcbp_resume_featured'] ? '1' : '0';
  update_post_meta( $post_id, '_wcb_featured', $featured_val );
  ```

- [ ] **WPCS fix + check** on `modules/resume/class-resume-module.php` (in pro plugin directory).

- [ ] **Browser verify:** Open any `wcb_resume` admin edit screen. Confirm "Featured candidate" checkbox renders and persists after save.

- [ ] **Commit (in pro plugin repo):**
  ```bash
  cd /Users/varundubey/Local\ Sites/job-portal/app/public/wp-content/plugins/wp-career-board-pro
  git add modules/resume/class-resume-module.php
  git commit -m "feat(wcbp): add Featured checkbox to resume meta box"
  ```

---

## Task 8 — Build `wcb/open-to-work` block (Pro)

**Plugin:** wp-career-board-pro
**Files:** Create `blocks/open-to-work/` (4 files)

- [ ] **Create `block.json`:**
  ```json
  {
    "$schema": "https://schemas.wp.org/trunk/block.json",
    "apiVersion": 3,
    "name": "wcb/open-to-work",
    "version": "1.0.0",
    "title": "Open to Work",
    "category": "widgets",
    "description": "Static sidebar widget listing candidates who are open to work.",
    "editorScript": "file:./index.js",
    "textdomain": "wp-career-board-pro",
    "attributes": {
      "count":       { "type": "integer", "default": 5 },
      "title":       { "type": "string",  "default": "" },
      "showViewAll": { "type": "boolean", "default": true },
      "viewAllUrl":  { "type": "string",  "default": "" }
    },
    "style": "file:./style.css",
    "render": "file:./render.php"
  }
  ```

- [ ] **Create `render.php`:**
  ```php
  <?php
  /**
   * Block render: wcb/open-to-work — static sidebar widget.
   *
   * _wcb_open_to_work is stored in wp_usermeta (not post meta).
   * Query pattern: get user IDs from usermeta → author__in to WP_Query.
   *
   * @package WP_Career_Board_Pro
   * @since   1.0.0
   */

  declare( strict_types=1 );

  defined( 'ABSPATH' ) || exit;

  global $wpdb;

  $wcbp_count        = max( 1, (int) ( $attributes['count'] ?? 5 ) );
  $wcbp_title        = trim( (string) ( $attributes['title'] ?? '' ) );
  $wcbp_show_all     = (bool) ( $attributes['showViewAll'] ?? true );
  $wcbp_view_all_url = trim( (string) ( $attributes['viewAllUrl'] ?? '' ) );

  if ( ! $wcbp_view_all_url ) {
  	$wcbp_settings     = (array) get_option( 'wcb_settings', array() );
  	$wcbp_view_all_url = ! empty( $wcbp_settings['resume_archive_page'] )
  		? (string) get_permalink( (int) $wcbp_settings['resume_archive_page'] )
  		: '';
  }

  // Step 1: get user IDs with _wcb_open_to_work = 1 from usermeta.
  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
  $wcbp_user_ids = $wpdb->get_col(
  	$wpdb->prepare(
  		"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
  		'_wcb_open_to_work',
  		'1'
  	)
  );

  if ( empty( $wcbp_user_ids ) ) {
  	return;
  }

  $wcbp_user_ids = array_map( 'intval', $wcbp_user_ids );

  // Step 2: query resumes authored by those users.
  $wcbp_resumes = get_posts(
  	array(
  		'post_type'      => 'wcb_resume',
  		'post_status'    => 'publish',
  		'numberposts'    => $wcbp_count,
  		'author__in'     => $wcbp_user_ids,
  		'orderby'        => 'date',
  		'order'          => 'DESC',
  		'meta_key'       => '_wcb_resume_public', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
  		'meta_value'     => '1',                  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
  	)
  );

  if ( empty( $wcbp_resumes ) ) {
  	return;
  }
  ?>
  <div <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-open-to-work' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

  	<div class="wcb-widget-header">
  		<h2 class="wcb-widget-title">
  			<?php echo esc_html( $wcbp_title ?: __( 'Open to Work', 'wp-career-board-pro' ) ); ?>
  		</h2>
  		<?php if ( $wcbp_show_all && $wcbp_view_all_url ) : ?>
  			<a class="wcb-widget-view-all" href="<?php echo esc_url( $wcbp_view_all_url ); ?>">
  				<?php esc_html_e( 'View all →', 'wp-career-board-pro' ); ?>
  			</a>
  		<?php endif; ?>
  	</div>

  	<ul class="wcb-candidate-widget-list">
  		<?php foreach ( $wcbp_resumes as $wcbp_resume ) : ?>
  			<?php
  			$wcbp_uid        = (int) $wcbp_resume->post_author;
  			$wcbp_user       = get_userdata( $wcbp_uid );
  			$wcbp_name       = $wcbp_user ? $wcbp_user->display_name : __( 'Candidate', 'wp-career-board-pro' );
  			$wcbp_avatar_url = get_avatar_url( $wcbp_uid, array( 'size' => 40 ) );
  			$wcbp_initial    = $wcbp_user ? strtoupper( mb_substr( $wcbp_user->display_name, 0, 1 ) ) : '?';
  			$wcbp_exp        = get_post_meta( $wcbp_resume->ID, '_wcb_resume_experience', true );
  			$wcbp_headline   = is_array( $wcbp_exp ) && ! empty( $wcbp_exp[0]['job_title'] )
  				? (string) $wcbp_exp[0]['job_title']
  				: '';
  			$wcbp_skill_terms = wp_get_object_terms( $wcbp_resume->ID, 'wcb_resume_skill', array( 'fields' => 'names', 'number' => 3 ) );
  			$wcbp_skills      = is_wp_error( $wcbp_skill_terms ) ? array() : $wcbp_skill_terms;
  			?>
  			<li class="wcb-candidate-widget-item">
  				<a class="wcb-candidate-widget-link" href="<?php echo esc_url( get_permalink( $wcbp_resume->ID ) ); ?>">
  					<span class="wcb-candidate-widget-avatar" aria-hidden="true">
  						<?php if ( $wcbp_avatar_url ) : ?>
  							<img src="<?php echo esc_url( $wcbp_avatar_url ); ?>" alt="" width="40" height="40" loading="lazy" />
  						<?php else : ?>
  							<span class="wcb-candidate-widget-initial"><?php echo esc_html( $wcbp_initial ); ?></span>
  						<?php endif; ?>
  						<span class="wcb-otw-dot" title="<?php esc_attr_e( 'Open to work', 'wp-career-board-pro' ); ?>"></span>
  					</span>
  					<span class="wcb-candidate-widget-body">
  						<span class="wcb-candidate-widget-name"><?php echo esc_html( $wcbp_name ); ?></span>
  						<?php if ( $wcbp_headline ) : ?>
  							<span class="wcb-candidate-widget-headline"><?php echo esc_html( $wcbp_headline ); ?></span>
  						<?php endif; ?>
  						<?php if ( ! empty( $wcbp_skills ) ) : ?>
  							<span class="wcb-skill-pills">
  								<?php foreach ( $wcbp_skills as $wcbp_skill ) : ?>
  									<span class="wcb-skill-pill"><?php echo esc_html( $wcbp_skill ); ?></span>
  								<?php endforeach; ?>
  							</span>
  						<?php endif; ?>
  					</span>
  				</a>
  			</li>
  		<?php endforeach; ?>
  	</ul>

  </div>
  ```

- [ ] **Create `index.js`** — same pattern as `recent-jobs/index.js` but with block name `wcb/open-to-work` and label `'WCB Pro: Open to Work'`.

- [ ] **Create `style.css`** — copy `.wcb-candidate-widget-*` styles:
  ```css
  .wcb-open-to-work { padding: 0; }

  /* Reuse shared widget-header styles from recent-jobs if loaded — add
     standalone definitions here in case this block loads without that one. */
  .wcb-open-to-work .wcb-widget-header {
  	display: flex;
  	align-items: baseline;
  	justify-content: space-between;
  	margin-bottom: 1rem;
  	gap: 0.5rem;
  }

  .wcb-open-to-work .wcb-widget-title {
  	font-size: 1rem;
  	font-weight: 600;
  	margin: 0;
  	color: var( --wcb-text, #1e293b );
  }

  .wcb-open-to-work .wcb-widget-view-all {
  	font-size: 0.8125rem;
  	color: var( --wcb-primary, #2563eb );
  	text-decoration: none;
  	white-space: nowrap;
  	flex-shrink: 0;
  }

  .wcb-open-to-work .wcb-widget-view-all:hover { text-decoration: underline; }

  .wcb-candidate-widget-list {
  	list-style: none;
  	margin: 0;
  	padding: 0;
  	display: flex;
  	flex-direction: column;
  	gap: 0.625rem;
  }

  .wcb-candidate-widget-link {
  	display: flex;
  	align-items: flex-start;
  	gap: 0.75rem;
  	padding: 0.75rem;
  	border: 1px solid var( --wcb-border, #e2e8f0 );
  	border-radius: var( --wcb-radius, 8px );
  	background: var( --wcb-bg-subtle, #f8fafc );
  	text-decoration: none;
  	color: inherit;
  	transition: transform 0.15s ease, box-shadow 0.15s ease;
  }

  .wcb-candidate-widget-link:hover {
  	transform: translateY( -1px );
  	box-shadow: var( --wcb-shadow, 0 4px 12px rgba( 0, 0, 0, 0.08 ) );
  }

  .wcb-candidate-widget-avatar {
  	position: relative;
  	flex-shrink: 0;
  }

  .wcb-candidate-widget-avatar img {
  	width: 40px;
  	height: 40px;
  	border-radius: 50%;
  	object-fit: cover;
  }

  .wcb-candidate-widget-initial {
  	display: flex;
  	align-items: center;
  	justify-content: center;
  	width: 40px;
  	height: 40px;
  	border-radius: 50%;
  	background: var( --wcb-primary, #2563eb );
  	color: #fff;
  	font-size: 1rem;
  	font-weight: 700;
  }

  .wcb-otw-dot {
  	position: absolute;
  	bottom: 1px;
  	right: 1px;
  	width: 10px;
  	height: 10px;
  	background: #16a34a;
  	border: 2px solid #fff;
  	border-radius: 50%;
  }

  .wcb-candidate-widget-body {
  	display: flex;
  	flex-direction: column;
  	gap: 0.2rem;
  	min-width: 0;
  }

  .wcb-candidate-widget-name {
  	font-size: 0.875rem;
  	font-weight: 600;
  	color: var( --wcb-text, #1e293b );
  }

  .wcb-candidate-widget-headline {
  	font-size: 0.75rem;
  	color: var( --wcb-text-muted, #64748b );
  	white-space: nowrap;
  	overflow: hidden;
  	text-overflow: ellipsis;
  }

  .wcb-skill-pills {
  	display: flex;
  	flex-wrap: wrap;
  	gap: 0.25rem;
  	margin-top: 0.25rem;
  }

  .wcb-skill-pill {
  	display: inline-block;
  	padding: 0.125rem 0.5rem;
  	border-radius: 999px;
  	font-size: 0.6875rem;
  	font-weight: 500;
  	background: var( --wcb-bg-hover, #eff6ff );
  	color: var( --wcb-primary, #2563eb );
  }
  ```

- [ ] **WPCS fix + check** on `render.php` (pro plugin).

- [ ] **Browser verify:** Mark a candidate user as open-to-work in their profile, add block to a page, confirm card renders.

- [ ] **Commit (pro):**
  ```bash
  git add blocks/open-to-work/
  git commit -m "feat(wcbp): add open-to-work sidebar widget block"
  ```

---

## Task 9 — Build `wcb/featured-companies` block (Pro)

**Plugin:** wp-career-board-pro
**Files:** Create `blocks/featured-companies/` (4 files)

- [ ] **Create `block.json`** — same structure, name `wcb/featured-companies`, title `Featured Companies`.

- [ ] **Create `render.php`:**
  ```php
  <?php
  /**
   * Block render: wcb/featured-companies — static sidebar widget.
   *
   * Open roles count: one scoped get_posts() + PHP grouping — no N+1.
   * Company logo: get_the_post_thumbnail_url() (no _wcb_logo meta key).
   * Tagline: _wcb_tagline post meta.
   *
   * @package WP_Career_Board_Pro
   * @since   1.0.0
   */

  declare( strict_types=1 );

  defined( 'ABSPATH' ) || exit;

  $wcbp_count        = max( 1, (int) ( $attributes['count'] ?? 5 ) );
  $wcbp_title        = trim( (string) ( $attributes['title'] ?? '' ) );
  $wcbp_show_all     = (bool) ( $attributes['showViewAll'] ?? true );
  $wcbp_view_all_url = trim( (string) ( $attributes['viewAllUrl'] ?? '' ) );

  $wcbp_companies = get_posts(
  	array(
  		'post_type'   => 'wcb_company',
  		'post_status' => 'publish',
  		'numberposts' => $wcbp_count,
  		'orderby'     => 'date',
  		'order'       => 'DESC',
  		'meta_key'    => '_wcb_featured', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
  		'meta_value'  => '1',             // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
  	)
  );

  if ( empty( $wcbp_companies ) ) {
  	return;
  }

  // Pre-fetch open roles scoped to these companies' authors (no N+1).
  $wcbp_author_ids = array_unique( array_map( fn( $p ) => (int) $p->post_author, $wcbp_companies ) );
  $wcbp_all_jobs   = get_posts(
  	array(
  		'post_type'   => 'wcb_job',
  		'post_status' => 'publish',
  		'numberposts' => -1,
  		'fields'      => 'all',
  		'author__in'  => $wcbp_author_ids,
  	)
  );

  $wcbp_jobs_by_author = array();
  foreach ( $wcbp_all_jobs as $wcbp_job ) {
  	$wcbp_jobs_by_author[ (int) $wcbp_job->post_author ] = ( $wcbp_jobs_by_author[ (int) $wcbp_job->post_author ] ?? 0 ) + 1;
  }
  ?>
  <div <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-featured-companies' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

  	<div class="wcb-widget-header">
  		<h2 class="wcb-widget-title">
  			<?php echo esc_html( $wcbp_title ?: __( 'Featured Companies', 'wp-career-board-pro' ) ); ?>
  		</h2>
  		<?php if ( $wcbp_show_all && $wcbp_view_all_url ) : ?>
  			<a class="wcb-widget-view-all" href="<?php echo esc_url( $wcbp_view_all_url ); ?>">
  				<?php esc_html_e( 'View all →', 'wp-career-board-pro' ); ?>
  			</a>
  		<?php endif; ?>
  	</div>

  	<ul class="wcb-company-widget-list">
  		<?php foreach ( $wcbp_companies as $wcbp_company ) : ?>
  			<?php
  			$wcbp_logo    = (string) get_the_post_thumbnail_url( $wcbp_company->ID, 'thumbnail' );
  			$wcbp_initial = strtoupper( mb_substr( $wcbp_company->post_title, 0, 1 ) );
  			$wcbp_tagline = (string) get_post_meta( $wcbp_company->ID, '_wcb_tagline', true );
  			$wcbp_roles   = $wcbp_jobs_by_author[ (int) $wcbp_company->post_author ] ?? 0;
  			?>
  			<li class="wcb-company-widget-item">
  				<a class="wcb-company-widget-link" href="<?php echo esc_url( get_permalink( $wcbp_company->ID ) ); ?>">
  					<span class="wcb-company-widget-logo" aria-hidden="true">
  						<?php if ( $wcbp_logo ) : ?>
  							<img src="<?php echo esc_url( $wcbp_logo ); ?>" alt="" width="40" height="40" loading="lazy" />
  						<?php else : ?>
  							<span class="wcb-company-widget-initial"><?php echo esc_html( $wcbp_initial ); ?></span>
  						<?php endif; ?>
  					</span>
  					<span class="wcb-company-widget-body">
  						<span class="wcb-company-widget-name"><?php echo esc_html( $wcbp_company->post_title ); ?></span>
  						<?php if ( $wcbp_tagline ) : ?>
  							<span class="wcb-company-widget-tagline"><?php echo esc_html( $wcbp_tagline ); ?></span>
  						<?php endif; ?>
  						<span class="wcb-company-widget-roles">
  							<?php
  							printf(
  								/* translators: %d: number of open roles */
  								esc_html( _n( '%d open role', '%d open roles', $wcbp_roles, 'wp-career-board-pro' ) ),
  								(int) $wcbp_roles
  							);
  							?>
  						</span>
  					</span>
  				</a>
  			</li>
  		<?php endforeach; ?>
  	</ul>

  </div>
  ```

- [ ] **Create `index.js`** — same pattern, name `wcb/featured-companies`, label `'WCB Pro: Featured Companies'`.

- [ ] **Create `style.css`** — `.wcb-featured-companies`, `.wcb-company-widget-*` styles following the same card pattern as `open-to-work` but with 40px square logos.

- [ ] **WPCS fix + check**.

- [ ] **Browser verify:** Mark Stripe company as featured (Task 1 checkbox). Add block to a page. Confirm card shows logo, tagline, open roles count.

- [ ] **Commit (pro):**
  ```bash
  git add blocks/featured-companies/
  git commit -m "feat(wcbp): add featured-companies sidebar widget block"
  ```

---

## Task 10 — Build `wcb/featured-candidates` block (Pro)

**Plugin:** wp-career-board-pro
**Files:** Create `blocks/featured-candidates/` (4 files)

- [ ] **Create `block.json`** — name `wcb/featured-candidates`, title `Featured Candidates`.

- [ ] **Create `render.php`:**
  ```php
  <?php
  /**
   * Block render: wcb/featured-candidates — static sidebar widget.
   *
   * Queries wcb_resume posts where _wcb_featured = 1 AND _wcb_resume_public = 1.
   * Uses compound meta_query (RELATION AND) — do NOT collapse to a single meta_key.
   *
   * @package WP_Career_Board_Pro
   * @since   1.0.0
   */

  declare( strict_types=1 );

  defined( 'ABSPATH' ) || exit;

  $wcbp_count        = max( 1, (int) ( $attributes['count'] ?? 5 ) );
  $wcbp_title        = trim( (string) ( $attributes['title'] ?? '' ) );
  $wcbp_show_all     = (bool) ( $attributes['showViewAll'] ?? true );
  $wcbp_view_all_url = trim( (string) ( $attributes['viewAllUrl'] ?? '' ) );

  if ( ! $wcbp_view_all_url ) {
  	$wcbp_settings     = (array) get_option( 'wcb_settings', array() );
  	$wcbp_view_all_url = ! empty( $wcbp_settings['resume_archive_page'] )
  		? (string) get_permalink( (int) $wcbp_settings['resume_archive_page'] )
  		: '';
  }

  $wcbp_resumes = get_posts(
  	array(
  		'post_type'   => 'wcb_resume',
  		'post_status' => 'publish',
  		'numberposts' => $wcbp_count,
  		'orderby'     => 'date',
  		'order'       => 'DESC',
  		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
  		'meta_query'  => array(
  			'relation' => 'AND',
  			array(
  				'key'     => '_wcb_featured',
  				'value'   => '1',
  				'compare' => '=',
  			),
  			array(
  				'key'     => '_wcb_resume_public',
  				'value'   => '1',
  				'compare' => '=',
  			),
  		),
  	)
  );

  if ( empty( $wcbp_resumes ) ) {
  	return;
  }
  ?>
  <div <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-featured-candidates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

  	<div class="wcb-widget-header">
  		<h2 class="wcb-widget-title">
  			<?php echo esc_html( $wcbp_title ?: __( 'Featured Candidates', 'wp-career-board-pro' ) ); ?>
  		</h2>
  		<?php if ( $wcbp_show_all && $wcbp_view_all_url ) : ?>
  			<a class="wcb-widget-view-all" href="<?php echo esc_url( $wcbp_view_all_url ); ?>">
  				<?php esc_html_e( 'View all →', 'wp-career-board-pro' ); ?>
  			</a>
  		<?php endif; ?>
  	</div>

  	<ul class="wcb-candidate-widget-list">
  		<?php foreach ( $wcbp_resumes as $wcbp_resume ) : ?>
  			<?php
  			$wcbp_uid        = (int) $wcbp_resume->post_author;
  			$wcbp_user       = get_userdata( $wcbp_uid );
  			$wcbp_name       = $wcbp_user ? $wcbp_user->display_name : __( 'Candidate', 'wp-career-board-pro' );
  			$wcbp_avatar_url = get_avatar_url( $wcbp_uid, array( 'size' => 40 ) );
  			$wcbp_initial    = $wcbp_user ? strtoupper( mb_substr( $wcbp_user->display_name, 0, 1 ) ) : '?';
  			$wcbp_exp        = get_post_meta( $wcbp_resume->ID, '_wcb_resume_experience', true );
  			$wcbp_headline   = is_array( $wcbp_exp ) && ! empty( $wcbp_exp[0]['job_title'] )
  				? (string) $wcbp_exp[0]['job_title']
  				: '';
  			$wcbp_skill_terms = wp_get_object_terms( $wcbp_resume->ID, 'wcb_resume_skill', array( 'fields' => 'names', 'number' => 3 ) );
  			$wcbp_skills      = is_wp_error( $wcbp_skill_terms ) ? array() : $wcbp_skill_terms;
  			?>
  			<li class="wcb-candidate-widget-item">
  				<a class="wcb-candidate-widget-link" href="<?php echo esc_url( get_permalink( $wcbp_resume->ID ) ); ?>">
  					<span class="wcb-candidate-widget-avatar" aria-hidden="true">
  						<?php if ( $wcbp_avatar_url ) : ?>
  							<img src="<?php echo esc_url( $wcbp_avatar_url ); ?>" alt="" width="40" height="40" loading="lazy" />
  						<?php else : ?>
  							<span class="wcb-candidate-widget-initial"><?php echo esc_html( $wcbp_initial ); ?></span>
  						<?php endif; ?>
  					</span>
  					<span class="wcb-candidate-widget-body">
  						<span class="wcb-candidate-widget-name"><?php echo esc_html( $wcbp_name ); ?></span>
  						<?php if ( $wcbp_headline ) : ?>
  							<span class="wcb-candidate-widget-headline"><?php echo esc_html( $wcbp_headline ); ?></span>
  						<?php endif; ?>
  						<?php if ( ! empty( $wcbp_skills ) ) : ?>
  							<span class="wcb-skill-pills">
  								<?php foreach ( $wcbp_skills as $wcbp_skill ) : ?>
  									<span class="wcb-skill-pill"><?php echo esc_html( $wcbp_skill ); ?></span>
  								<?php endforeach; ?>
  							</span>
  						<?php endif; ?>
  					</span>
  				</a>
  			</li>
  		<?php endforeach; ?>
  	</ul>

  </div>
  ```

- [ ] **Create `index.js`** — name `wcb/featured-candidates`, label `'WCB Pro: Featured Candidates'`.

- [ ] **Create `style.css`** — `.wcb-featured-candidates` wrapper; reuse `.wcb-candidate-widget-*` classes (defined in `open-to-work/style.css`); omit `.wcb-otw-dot` rule.

- [ ] **WPCS fix + check**.

- [ ] **Browser verify:** Mark a resume as featured (Task 7 checkbox) and public. Add block. Confirm card renders.

- [ ] **Commit (pro):**
  ```bash
  git add blocks/featured-candidates/
  git commit -m "feat(wcbp): add featured-candidates sidebar widget block"
  ```

---

## Task 11 — Build `wcb/resume-search-hero` block (Pro)

**Plugin:** wp-career-board-pro
**Files:** Create `blocks/resume-search-hero/` (4 files)

- [ ] **Create `block.json`** — name `wcb/resume-search-hero`, attributes: `layout`, `placeholder`, `buttonLabel`, `showSkillFilter` (boolean, default true), `showOpenToWorkFilter` (boolean, default false).

- [ ] **Create `render.php`:**
  ```php
  <?php
  /**
   * Block render: wcb/resume-search-hero — hero search form for resumes.
   *
   * Submits a plain GET form to the resumes archive page. Params consumed by
   * the resume listing block: wcb_resume_search, wcb_resume_skill, wcb_open_to_work.
   *
   * @package WP_Career_Board_Pro
   * @since   1.0.0
   */

  declare( strict_types=1 );

  defined( 'ABSPATH' ) || exit;

  // phpcs:disable WordPress.Security.NonceVerification.Recommended
  $wcbp_layout           = in_array( (string) ( $attributes['layout'] ?? 'horizontal' ), array( 'horizontal', 'vertical' ), true )
  	? (string) $attributes['layout']
  	: 'horizontal';
  $wcbp_placeholder      = trim( (string) ( $attributes['placeholder'] ?? '' ) ) ?: __( 'Search candidates…', 'wp-career-board-pro' );
  $wcbp_button_label     = trim( (string) ( $attributes['buttonLabel'] ?? '' ) ) ?: __( 'Search', 'wp-career-board-pro' );
  $wcbp_show_skill       = (bool) ( $attributes['showSkillFilter']       ?? true );
  $wcbp_show_open_to_work = (bool) ( $attributes['showOpenToWorkFilter'] ?? false );

  $wcbp_settings    = (array) get_option( 'wcb_settings', array() );
  $wcbp_action_url  = ! empty( $wcbp_settings['resume_archive_page'] )
  	? (string) get_permalink( (int) $wcbp_settings['resume_archive_page'] )
  	: home_url( '/' );

  // Pre-populate from current GET params.
  $wcbp_current_search       = isset( $_GET['wcb_resume_search'] ) ? sanitize_text_field( wp_unslash( $_GET['wcb_resume_search'] ) ) : '';
  $wcbp_current_skill        = isset( $_GET['wcb_resume_skill'] )  ? sanitize_text_field( wp_unslash( $_GET['wcb_resume_skill'] ) )  : '';
  $wcbp_current_open_to_work = ! empty( $_GET['wcb_open_to_work'] );
  // phpcs:enable WordPress.Security.NonceVerification.Recommended

  $wcbp_skills = $wcbp_show_skill ? get_terms( array( 'taxonomy' => 'wcb_resume_skill', 'hide_empty' => true ) ) : array();
  $wcbp_skills = is_wp_error( $wcbp_skills ) ? array() : $wcbp_skills;
  ?>
  <div <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-resume-search-hero wcb-resume-search-hero--' . esc_attr( $wcbp_layout ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
  	<form class="wcb-search-hero__form" method="GET" action="<?php echo esc_url( $wcbp_action_url ); ?>" role="search">

  		<div class="wcb-search-hero__field wcb-search-hero__field--keyword">
  			<label class="screen-reader-text" for="wcb-resume-search">
  				<?php esc_html_e( 'Search candidates', 'wp-career-board-pro' ); ?>
  			</label>
  			<input
  				id="wcb-resume-search"
  				type="search"
  				name="wcb_resume_search"
  				class="wcb-search-hero__input"
  				placeholder="<?php echo esc_attr( $wcbp_placeholder ); ?>"
  				value="<?php echo esc_attr( $wcbp_current_search ); ?>"
  			/>
  		</div>

  		<?php if ( $wcbp_show_skill && ! empty( $wcbp_skills ) ) : ?>
  			<div class="wcb-search-hero__field wcb-search-hero__field--select">
  				<label class="screen-reader-text" for="wcb-resume-skill">
  					<?php esc_html_e( 'Skill', 'wp-career-board-pro' ); ?>
  				</label>
  				<select id="wcb-resume-skill" name="wcb_resume_skill" class="wcb-search-hero__select">
  					<option value=""><?php esc_html_e( 'All Skills', 'wp-career-board-pro' ); ?></option>
  					<?php foreach ( $wcbp_skills as $wcbp_term ) : ?>
  						<option value="<?php echo esc_attr( $wcbp_term->slug ); ?>" <?php selected( $wcbp_current_skill, $wcbp_term->slug ); ?>>
  							<?php echo esc_html( $wcbp_term->name ); ?>
  						</option>
  					<?php endforeach; ?>
  				</select>
  			</div>
  		<?php endif; ?>

  		<?php if ( $wcbp_show_open_to_work ) : ?>
  			<div class="wcb-search-hero__field wcb-search-hero__field--checkbox">
  				<label class="wcb-search-hero__checkbox-label">
  					<input
  						type="checkbox"
  						name="wcb_open_to_work"
  						value="1"
  						<?php checked( $wcbp_current_open_to_work ); ?>
  					/>
  					<?php esc_html_e( 'Open to work', 'wp-career-board-pro' ); ?>
  				</label>
  			</div>
  		<?php endif; ?>

  		<div class="wcb-search-hero__field wcb-search-hero__field--submit">
  			<button type="submit" class="wcb-search-hero__button">
  				<?php echo esc_html( $wcbp_button_label ); ?>
  			</button>
  		</div>

  	</form>
  </div>
  ```

- [ ] **Create `index.js`** — three InspectorControls panels: Layout (SelectControl), Labels (two TextControls), Filters (ToggleControl for skill, ToggleControl for open-to-work).

- [ ] **Create `style.css`** — identical to `job-search-hero/style.css`, scoped to `.wcb-resume-search-hero`. (Can `@import` or duplicate — duplicate is safer to avoid load-order issues.)

- [ ] **WPCS fix + check**.

- [ ] **Browser verify:** Add to a page. Submit with a skill — confirm redirect to `/find-resumes/?wcb_resume_skill=php`. Enable open-to-work checkbox in inspector, submit — confirm `?wcb_open_to_work=1` appears.

- [ ] **Commit (pro):**
  ```bash
  git add blocks/resume-search-hero/
  git commit -m "feat(wcbp): add resume-search-hero block with horizontal/vertical layout"
  ```

---

## Task 12 — Register new pro blocks in class-pro-plugin.php

**Plugin:** wp-career-board-pro
**Files:** Modify `core/class-pro-plugin.php`

- [ ] **Add four entries** to the `$blocks` array in `register_blocks()`:
  ```php
  'open-to-work',
  'featured-companies',
  'featured-candidates',
  'resume-search-hero',
  ```

- [ ] **WPCS fix + check**.

- [ ] **Browser verify:** Block inserter shows all 4 new pro blocks in Widgets category.

- [ ] **Commit (pro):**
  ```bash
  git add core/class-pro-plugin.php
  git commit -m "feat(wcbp): register open-to-work, featured-companies, featured-candidates, resume-search-hero blocks"
  ```

---

## Task 13 — i18n audit (both plugins)

**Files:** Any PHP/JS files with findings

- [ ] **Run the Grunt text domain checker** in both plugins:
  ```bash
  cd /Users/varundubey/Local\ Sites/job-portal/app/public/wp-content/plugins/wp-career-board
  npx grunt textdomain

  cd /Users/varundubey/Local\ Sites/job-portal/app/public/wp-content/plugins/wp-career-board-pro
  npx grunt textdomain 2>/dev/null || echo "no grunt in pro, use grep fallback"
  ```

- [ ] **Grep for bare `__(` without text domain** in both plugins:
  ```bash
  grep -rn "__( '" blocks/ modules/ admin/ api/ core/ --include="*.php" | grep -v ", 'wp-career-board" | grep -v vendor
  ```

- [ ] **Grep for `_e(` (should be `esc_html_e(`)** in both plugins:
  ```bash
  grep -rn "[^_]_e( " blocks/ modules/ admin/ --include="*.php"
  ```

- [ ] **Grep for hardcoded English strings in JS** (view.js / index.js files not using `@wordpress/i18n`):
  ```bash
  grep -rn "Could not\|Please try\|Connection error\|No jobs\|No saved" blocks/ --include="*.js"
  ```
  For any found: import `__` from `@wordpress/i18n` and wrap (only applicable to `view.js` files that use `wp-scripts` build — static block `index.js` files don't need i18n since they show editor labels only).

- [ ] **Fix all findings in-place**. Commit per plugin:
  ```bash
  # Free plugin
  git add -u
  git commit -m "fix(wcb): i18n audit — wrap missing strings, fix text domains"

  # Pro plugin (if any findings)
  git add -u
  git commit -m "fix(wcbp): i18n audit — wrap missing strings, fix text domains"
  ```

---

## Task 14 — Rename dashboard pages + update navigation menu

**No code changes** — WP-CLI only. Run on local site.

- [ ] **Verify page IDs before updating** — confirm IDs 10 and 11 are the correct dashboard pages:
  ```bash
  cd /Users/varundubey/Local\ Sites/job-portal/app/public
  wp --allow-root post list --post_type=page --fields=ID,post_title,post_status --format=table | grep -i "dashboard\|employer\|candidate\|career\|hiring"
  ```
  Confirm the returned IDs match before proceeding. If the IDs differ, use the IDs from this output in the commands below.

- [ ] **Update page titles:**
  ```bash
  wp --allow-root post update 10 --post_title="Hiring"
  wp --allow-root post update 11 --post_title="Career"
  ```
  Expected output: `Success: Updated post 10.` / `Success: Updated post 11.`

- [ ] **Find the Reign nav menu name:**
  ```bash
  wp --allow-root menu list --fields=term_id,name --format=table
  ```

- [ ] **List menu items to get the db_id of the two dashboard items:**
  ```bash
  wp --allow-root menu item list <menu-name-from-above> --fields=db_id,title --format=table
  ```

- [ ] **Update menu item labels:**
  ```bash
  wp --allow-root menu item update <db_id_employer> --title="Hiring"
  wp --allow-root menu item update <db_id_candidate> --title="Career"
  ```

- [ ] **Browser verify:**
  ```
  http://job-portal.local/?autologin=1
  ```
  Confirm primary navigation shows "Hiring" and "Career" instead of the old long names.

---

## Quality Gates (run after every task)

1. `mcp__wpcs__wpcs_fix_file` — on every modified PHP file
2. `mcp__wpcs__wpcs_check_staged` — must return 0 errors
3. `mcp__wpcs__wpcs_phpstan_check` — must return 0 errors
4. Browser verify via Playwright (`mcp__plugin_playwright_playwright__browser_navigate` + `browser_take_screenshot`)
5. Commit only after all pass

---

## Verification Checklist (end of all tasks)

- [ ] Block inserter shows all 7 new blocks (3 free + 4 pro) in Widgets category
- [ ] Featured Jobs block has working title/view-all inspector controls
- [ ] Company edit screen has "Featured company" checkbox
- [ ] Resume edit screen has "Featured candidate" checkbox
- [ ] `wcb/featured-companies` returns results after marking a company as featured
- [ ] `wcb/featured-candidates` returns results after marking a resume as featured
- [ ] `wcb/open-to-work` returns candidates with open-to-work flag set
- [ ] Both hero search blocks submit to correct archive pages with correct GET params
- [ ] Navigation shows "Hiring" and "Career"
- [ ] `npx grunt textdomain` passes with 0 errors in free plugin
