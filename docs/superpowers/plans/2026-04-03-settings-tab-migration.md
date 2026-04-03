# Settings Tab Migration — Boards, Field Builder, Import

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move Boards, Field Builder, and Import from standalone admin pages into Settings sidebar tabs, following the existing Credits/AI Settings/Job Feed pattern.

**Architecture:** Each page becomes a settings tab rendered via `wcb_settings_tab_{slug}` action. The submenu registrations are removed entirely (no backward compat — v1.0.0). The render methods are adapted to output card content without page headers (the settings page owns the header). JS/CSS assets continue to load on the settings page.

**Tech Stack:** PHP (WP Settings API hooks), CSS (existing wcb-settings-* classes), JS (existing admin.js hash routing)

---

## File Structure

| File | Action | Responsibility |
|------|--------|----------------|
| `wp-career-board/admin/class-admin-settings.php` | Modify | Add `import` tab + icon + group assignment |
| `wp-career-board/admin/class-admin.php` | Modify | Remove Import submenu registration |
| `wp-career-board/admin/class-admin-import.php` | Modify | Change `render()` to output settings-card content (no wrap/header) |
| `wp-career-board-pro/admin/class-pro-admin.php` | Modify | Add `boards` + `field-builder` tabs to filter; remove submenu registrations; add render actions |
| `wp-career-board-pro/admin/class-admin-boards.php` | Modify | Change `render()` to output settings-card content (no wrap/header) |
| `wp-career-board-pro/admin/class-admin-field-builder.php` | Modify | Change `render()` to output settings-card content (no wrap/header) |
| `wp-career-board-pro/admin/class-pro-admin.php` | Modify | Update `enqueue_admin_assets()` to load Boards/Field Builder JS on settings page |

---

### Task 1: Add Import tab to Free settings

**Files:**
- Modify: `wp-career-board/admin/class-admin-settings.php:293-353`
- Modify: `wp-career-board/admin/class-admin.php:135-144`
- Modify: `wp-career-board/admin/class-admin-import.php:30-90`

- [ ] **Step 1: Register the Import tab in settings**

In `class-admin-settings.php`, add `import` to the base tabs in `get_tabs()`:

```php
// In get_tabs(), add after 'emails':
'import' => __( 'Import', 'wp-career-board' ),
```

Add the icon in `get_tab_icons()`:

```php
'import' => 'upload',
```

Add the group assignment in `get_free_tab_slugs()`:

```php
'import' => 'general',
```

- [ ] **Step 2: Add the settings tab render action**

In `class-admin-settings.php`, add a new method and hook it in `__construct()` or at the class level. Since the Emails tab pattern uses a dedicated render method, follow the same:

```php
// Add to the class — after render_emails_tab()
public function render_import_tab(): void {
    ( new AdminImport() )->render_settings_tab();
}
```

Register the action in the constructor or init (check existing pattern — Emails tab is rendered inline via `do_action('wcb_settings_tab_emails')` in the template, so just add the hook):

```php
add_action( 'wcb_settings_tab_import', array( $this, 'render_import_tab' ) );
```

- [ ] **Step 3: Remove the standalone Import submenu**

In `class-admin.php`, remove the `register_import_submenu()` method (lines 135-144) and remove its `add_action('admin_menu', ...)` call. Search for `register_import_submenu` to find the hook registration.

- [ ] **Step 4: Adapt AdminImport to render as settings tab content**

In `class-admin-import.php`, add a `render_settings_tab()` method that outputs the migration cards WITHOUT the `<div class="wrap">`, page header, or intro paragraph — those belong to the settings page. Keep the existing `render()` method if needed for backward compat, or replace it entirely:

```php
public function render_settings_tab(): void {
    $importer         = new WpjmImporter();
    $wpjm_jobs        = post_type_exists( 'job_listing' );
    $wpjm_resumes     = post_type_exists( 'resume' );
    $jobs_total       = $importer->wpjm_jobs_total();
    $jobs_migrated    = $importer->wcb_jobs_migrated();
    $resumes_total    = $importer->wpjm_resumes_total();
    $resumes_migrated = $importer->wcb_resumes_migrated();

    // Output the import cards directly — no wrap, no page header.
    // Reuse the same card markup from the existing render() but
    // wrapped in a wcb-card instead of wcb-import-card.
    require WCB_DIR . 'admin/views/import-tab.php';
}
```

Create `admin/views/import-tab.php` that contains the migration card markup from the current `render()` method, but using `wcb-settings-card` / `wcb-card` wrapper pattern instead of standalone page layout.

- [ ] **Step 5: Verify import tab loads in settings and commit**

Navigate to Settings > Import tab. Verify migration cards render correctly inside the settings content area.

```bash
git add admin/class-admin-settings.php admin/class-admin.php admin/class-admin-import.php admin/views/import-tab.php
git commit -m "fix(wcb): move Import page into Settings sidebar tab"
```

---

### Task 2: Add Boards tab to Pro settings

**Files:**
- Modify: `wp-career-board-pro/admin/class-pro-admin.php:39-51,125-151,239-246`
- Modify: `wp-career-board-pro/admin/class-admin-boards.php`

- [ ] **Step 1: Register Boards tab in Pro settings filter**

In `class-pro-admin.php`, update `add_settings_tabs()` to include boards:

```php
$tabs['boards'] = __( 'Boards', 'wp-career-board-pro' );
```

Add the render action in `boot()`:

```php
add_action( 'wcb_settings_tab_boards', array( $this, 'render_boards_tab' ) );
```

Add the icon — in `class-admin-settings.php` (Free plugin) `get_tab_icons()`:

```php
'boards' => 'layout-grid',
```

- [ ] **Step 2: Remove Boards standalone submenu**

In `class-pro-admin.php` `register_submenus()`, remove the Boards `add_submenu_page()` block (lines 135-142).

- [ ] **Step 3: Create render_boards_tab() method**

In `class-pro-admin.php`, add:

```php
public function render_boards_tab(): void {
    ( new AdminBoards() )->render_settings_tab();
}
```

- [ ] **Step 4: Adapt AdminBoards to render as settings tab**

In `class-admin-boards.php`, add `render_settings_tab()` that outputs the boards table inside a `wcb-settings-card` — without the wrap, page header, or empty state (settings tabs don't need standalone empty states, the card itself can show "no boards yet"):

The content should be:
- A `wcb-settings-card` with header "Boards" + "Add Board" link
- The boards table (or empty state message) inside `wcb-card__body`

- [ ] **Step 5: Commit**

```bash
cd wp-career-board-pro
git add admin/class-pro-admin.php admin/class-admin-boards.php
git commit -m "fix(wcbp): move Boards page into Settings sidebar tab"
```

Also commit the icon addition in the Free plugin:

```bash
cd wp-career-board
git add admin/class-admin-settings.php
git commit -m "fix(wcb): add boards + field-builder icons to settings tab map"
```

---

### Task 3: Add Field Builder tab to Pro settings

**Files:**
- Modify: `wp-career-board-pro/admin/class-pro-admin.php`
- Modify: `wp-career-board-pro/admin/class-admin-field-builder.php`

- [ ] **Step 1: Register Field Builder tab**

In `class-pro-admin.php`, update `add_settings_tabs()`:

```php
$tabs['field-builder'] = __( 'Field Builder', 'wp-career-board-pro' );
```

Add render action in `boot()`:

```php
add_action( 'wcb_settings_tab_field-builder', array( $this, 'render_field_builder_tab' ) );
```

Add icon in Free plugin's `get_tab_icons()`:

```php
'field-builder' => 'wrench',
```

- [ ] **Step 2: Remove Field Builder standalone submenu**

In `class-pro-admin.php` `register_submenus()`, remove the Field Builder `add_submenu_page()` block (lines 144-151).

- [ ] **Step 3: Create render_field_builder_tab() method**

```php
public function render_field_builder_tab(): void {
    ( new AdminFieldBuilder() )->render_settings_tab();
}
```

- [ ] **Step 4: Adapt AdminFieldBuilder to render as settings tab**

In `class-admin-field-builder.php`, add `render_settings_tab()` that outputs the board selector + field management UI inside settings cards — without wrap or page header.

Key concern: Field Builder uses complex JS (board selector dropdown triggers AJAX to load fields, sortable drag-and-drop, inline forms). The JS currently targets selectors like `#wcbp-board-selector`, `.wcbp-field-builder`, etc. These will still work inside the settings tab as long as the HTML IDs and classes remain the same. Verify the JS doesn't depend on the page slug `wcbp-field-builder` for any functionality.

- [ ] **Step 5: Update asset enqueuing**

In `class-pro-admin.php` `enqueue_admin_assets()`, the Boards and Field Builder JS/CSS currently only loads on their standalone pages (check the `$hook_suffix` whitelist). Update to also load on the settings page (`career-board_page_wcb-settings`).

- [ ] **Step 6: Commit**

```bash
cd wp-career-board-pro
git add admin/class-pro-admin.php admin/class-admin-field-builder.php
git commit -m "fix(wcbp): move Field Builder page into Settings sidebar tab"
```

---

### Task 4: Clean up removed submenu references

**Files:**
- Modify: `wp-career-board-pro/admin/class-pro-admin.php` (asset enqueue whitelist)
- Modify: `wp-career-board/admin/class-admin.php` (any Import references)

- [ ] **Step 1: Remove stale page slugs from asset enqueue lists**

In Pro's `enqueue_admin_assets()`, remove `career-board_page_wcbp-boards` and `career-board_page_wcbp-field-builder` from the page whitelist since those pages no longer exist. The settings page (`career-board_page_wcb-settings`) should already be in the list.

In Free's `enqueue_assets()`, remove `career-board_page_wcb-import` if it's in the whitelist.

- [ ] **Step 2: Remove the Import sidebar menu item registration**

Verify the Import `add_action('admin_menu', ...)` is fully removed from Free plugin. Search for any remaining references to `wcb-import` as a page slug.

- [ ] **Step 3: Run WPCS on all changed files**

```bash
mcp__wpcs__wpcs_fix_file → each changed file
mcp__wpcs__wpcs_check_file → each changed file
```

- [ ] **Step 4: Final commit**

```bash
git commit -m "fix(wcb): clean up removed submenu references and asset enqueue lists"
```

---

## Verification

After all tasks:

1. Navigate to Settings page — verify sidebar shows: General (Job Listings, Pages, Import), Notifications (Notifications, Emails), Pro (Resumes, AI Settings, Job Feed, Credits, Boards, Field Builder, Integrations, License)
2. Click each new tab — verify content renders inside settings card area
3. Verify Import migration cards work (progress bars, AJAX calls)
4. Verify Boards table loads, "Add Board" link works
5. Verify Field Builder board selector loads fields, drag-and-drop works
6. Verify old direct URLs (`admin.php?page=wcb-import`, `admin.php?page=wcbp-boards`, `admin.php?page=wcbp-field-builder`) return 404 or redirect
7. Verify no JS console errors on Settings page
8. Test at 782px viewport width — sidebar collapses to horizontal nav
