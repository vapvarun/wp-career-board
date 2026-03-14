# WP Career Board — Claude Code Rules

## MANDATORY: WPCS MCP Before Every Commit

Use MCP tools — do NOT run phpcs manually:

```
1. mcp__wpcs__wpcs_fix_file        → auto-fix each changed file
2. mcp__wpcs__wpcs_check_staged    → gate: must return 0 errors
3. mcp__wpcs__wpcs_phpstan_check   → must return 0 errors
4. mcp__wpcs__wpcs_quality_check   → full quality pass
5. git commit only after all pass
```

**Zero WPCS errors = required to close any task. No exceptions.**

---

## Plan Adherence

- Plan: `docs/PLAN.md`
- Build only what the current task defines — nothing more
- Every file created/modified must appear in the task's `Files:` section
- Mark task `🔄` on start, `✅` when both spec + quality reviews pass
- Update progress tracker table in `docs/PLAN.md` on completion

---

## Architecture Rules

### Prefix
All globals use `wcb_` — functions, options, hooks, meta keys, CPTs, taxonomies, DB tables.
Namespace: `WCB\` (maps via `spl_autoload_register`).
REST namespace: `wcb/v1`.

### Permissions — Abilities API only
```php
// CORRECT
wp_register_ability( 'wcb_post_job', __( 'Post a Job', 'wp-career-board' ) );
if ( ! wp_is_authorized( 'wcb_post_job' ) ) { ... }

// FORBIDDEN
current_user_can( 'manage_options' );  // never
current_user_can( 'edit_posts' );      // never
```

### REST API
- All endpoints extend `WCB\Api\REST_Controller`
- Every route: `permission_callback`, `validate_callback`, `sanitize_callback`
- Return `WP_Error` — never throw exceptions
- Schema in `get_item_schema()` — no ad-hoc arrays

### Database
- Table creation via `dbDelta()` only
- All queries via `$wpdb->prepare()`

### Frontend — Interactivity API only
- `@wordpress/interactivity` store + directives (`data-wp-*`)
- Zero jQuery, zero `admin-ajax.php`, zero page reloads
- All data from `/wp-json/wcb/v1/` REST endpoints

### Blocks
```
blocks/{name}/
├── block.json    # apiVersion: 3, interactivity support
├── render.php    # wp_interactivity_state()
├── view.js       # Interactivity API store
└── style.css
```
- `register_block_type_from_metadata()` only

### Escaping
```php
esc_html_e( 'string', 'wp-career-board' );  // output
esc_attr( $value );                          // attributes
wp_kses_post( $html );                       // rich content
// Never: echo $raw; or _e() without esc_
```

### PHP
- PHP 8.1+ — typed properties, enums, named args, match expressions
- Type declarations on all public methods
- `declare( strict_types=1 )` in every file

### File Header (every PHP file)
```php
<?php
/**
 * {Description}
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\{Sub\Namespace};

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

---

## Do NOT Build

- Anything beyond current task scope
- jQuery or classic AJAX (`admin-ajax.php`)
- Inline `<style>` or `<script>` in PHP — use `wp_enqueue_*`
- Hardcoded strings without i18n wrapper (text domain: `wp-career-board`)
- Raw SQL without `$wpdb->prepare()`
- Pro features — those live in `wp-career-board-pro/`

---

## Commit Format

```
feat(wcb): T{N} — {description}
fix(wcb): T{N} — {description}
```

---

## Plugin Info

- **Slug:** `wp-career-board`  |  **Prefix:** `wcb_`  |  **Namespace:** `WCB\`
- **Min WP:** 6.9  |  **Min PHP:** 8.1
- **Plan:** `docs/PLAN.md`  |  **Spec:** `docs/DESIGN-SPEC.md`
