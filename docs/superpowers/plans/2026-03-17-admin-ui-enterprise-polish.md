# Admin UI Enterprise Polish — Implementation Plan

**Goal:** Bring every admin page in Free and Pro to a consistent enterprise SaaS standard: structured page headers, informative intro descriptions, proper empty states, and contextual column data.

**Architecture:** Pure PHP + CSS changes only. No JS, no new REST endpoints. All changes are additive and backward-compatible. No new files — all changes are in existing admin PHP files and the two admin CSS files.

**Tech Stack:** PHP 8.1, WordPress WP_List_Table, WordPress admin CSS primitives (form-table, notice, nav-tab, wp-header-end), existing `.wcb-*` and `.wcbp-*` CSS classes.

---

## Design System (Reference Before Every Change)

### Page Header Standard
Every admin page must follow this pattern:
```php
<h1 class="wp-heading-inline">Title</h1>
[<a href="..." class="page-title-action">CTA</a>]
<hr class="wp-header-end">
<p class="description wcbp-page-intro">One sentence describing this page.</p>
```

### Styled Empty State
Used when a list/table has no items. Uses existing `.wcbp-empty-state` CSS:
```php
<div class="wcbp-empty-state">
    <span class="wcbp-empty-state__icon dashicons dashicons-{icon}"></span>
    <p><strong>Title</strong></p>
    <p>Explanation sentence.</p>
    <a href="..." class="button button-primary">CTA</a>
</div>
```

### Info Card (new — for Field Builder idle state)
```php
<div class="wcbp-info-card">
    <h3>...</h3>
    <p>...</p>
    <ul>...</ul>
</div>
```

### Form-table Row with Description
```php
<tr>
    <th scope="row"><label for="id">Label</label></th>
    <td>
        <input .../>
        <p class="description">What this setting does.</p>
    </td>
</tr>
```

---

## Audit Results

| Page | Plugin | Status | Issues |
|------|--------|--------|--------|
| Dashboard | Free | ✅ Pass | None — stats grid, panels, quick actions |
| Jobs list | Free | ⚠️ Minor | `no_items()` = bare text |
| Applications list | Free | ⚠️ Minor | `no_items()` = bare text |
| Companies list | Free | ⚠️ Minor | `no_items()` = bare text |
| Employers list | Free | ⚠️ Minor | `no_items()` = bare text |
| Candidates list | Free | ⚠️ Minor | `no_items()` = bare text |
| Settings | Free | ✅ Pass | None — 4-tab layout, form-table, descriptions |
| Boards | Pro | ❌ Fail | No intro, bare empty state, raw 0s in columns |
| Credits | Pro | ⚠️ Minor | No intro, packages empty state bare |
| Field Builder | Pro | ❌ Fail | No idle state description, no boards state |
| AI Settings | Pro | ⚠️ Minor | Missing `<hr class="wp-header-end">`, no intro |
| Job Feed | Pro | ⚠️ Minor | Missing `<hr class="wp-header-end">`, no intro |
| Migration | Pro | ⚠️ Minor | Missing `<hr class="wp-header-end">`, no intro |

---

## Files Changed

| # | File | What Changes |
|---|------|-------------|
| 1 | `wp-career-board/admin/class-admin-jobs.php` | `no_items()` → styled empty state HTML |
| 2 | `wp-career-board/admin/class-admin-applications.php` | `no_items()` → styled empty state HTML |
| 3 | `wp-career-board/admin/class-admin-companies.php` | `no_items()` → styled empty state HTML |
| 4 | `wp-career-board/admin/class-admin-employers.php` | `no_items()` → styled empty state HTML |
| 5 | `wp-career-board/admin/class-admin-candidates.php` | `no_items()` → styled empty state HTML |
| 6 | `wp-career-board/assets/css/admin.css` | Add `.wcb-no-items-state` styled empty state CSS |
| 7 | `wp-career-board-pro/admin/class-admin-boards.php` | Page intro, styled empty state, contextual column rendering |
| 8 | `wp-career-board-pro/admin/class-admin-credits.php` | Page intro, styled packages empty state |
| 9 | `wp-career-board-pro/admin/class-admin-field-builder.php` | No-boards state, idle info card with field types |
| 10 | `wp-career-board-pro/admin/class-pro-admin.php` | Add `<hr class="wp-header-end">` + intro to AI Settings, Job Feed, Migration |
| 11 | `wp-career-board-pro/assets/admin.css` | Add `.wcbp-page-intro`, `.wcbp-info-card`, `.wcbp-info-card-types` |

---

## Task 1: Free — CSS for styled list empty states

**Files:** `wp-career-board/assets/css/admin.css`

Add below the existing `.wcb-no-items` rule:

```css
/* Styled empty state inside WP_List_Table */
.wcb-no-items-state {
	text-align: center;
	padding: 40px 24px;
	color: #646970;
}

.wcb-no-items-state .dashicons {
	font-size: 40px;
	width: 40px;
	height: 40px;
	color: #c3c4c7;
	margin-bottom: 12px;
}

.wcb-no-items-state p {
	margin: 0 0 8px;
	font-size: 14px;
}

.wcb-no-items-state .wcb-no-items-title {
	font-size: 15px;
	font-weight: 600;
	color: #1d2327;
	display: block;
	margin-bottom: 6px;
}

.wcb-no-items-state a.button {
	margin-top: 12px;
}
```

---

## Task 2: Free — Jobs list `no_items()`

**File:** `wp-career-board/admin/class-admin-jobs.php` — `no_items()` method

Replace:
```php
public function no_items(): void {
	esc_html_e( 'No jobs found.', 'wp-career-board' );
}
```

With:
```php
public function no_items(): void {
	?>
	<div class="wcb-no-items-state">
		<span class="dashicons dashicons-portfolio"></span>
		<span class="wcb-no-items-title"><?php esc_html_e( 'No jobs found', 'wp-career-board' ); ?></span>
		<p><?php esc_html_e( 'Post your first job listing or adjust the filters above.', 'wp-career-board' ); ?></p>
		<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcb_job' ) ); ?>" class="button button-primary">
			<?php esc_html_e( 'Add New Job', 'wp-career-board' ); ?>
		</a>
	</div>
	<?php
}
```

---

## Task 3: Free — Applications list `no_items()`

**File:** `wp-career-board/admin/class-admin-applications.php` — `no_items()` method

Replace:
```php
public function no_items(): void {
	esc_html_e( 'No applications found.', 'wp-career-board' );
}
```

With:
```php
public function no_items(): void {
	?>
	<div class="wcb-no-items-state">
		<span class="dashicons dashicons-email-alt"></span>
		<span class="wcb-no-items-title"><?php esc_html_e( 'No applications found', 'wp-career-board' ); ?></span>
		<p><?php esc_html_e( 'Applications appear here once candidates apply to your jobs.', 'wp-career-board' ); ?></p>
	</div>
	<?php
}
```

---

## Task 4: Free — Companies list `no_items()`

**File:** `wp-career-board/admin/class-admin-companies.php` — `no_items()` method

Replace:
```php
public function no_items(): void {
	esc_html_e( 'No companies found.', 'wp-career-board' );
}
```

With:
```php
public function no_items(): void {
	?>
	<div class="wcb-no-items-state">
		<span class="dashicons dashicons-building"></span>
		<span class="wcb-no-items-title"><?php esc_html_e( 'No companies yet', 'wp-career-board' ); ?></span>
		<p><?php esc_html_e( 'Company profiles are created when employers complete their profile.', 'wp-career-board' ); ?></p>
		<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcb_company' ) ); ?>" class="button button-primary">
			<?php esc_html_e( 'Add Company', 'wp-career-board' ); ?>
		</a>
	</div>
	<?php
}
```

---

## Task 5: Free — Employers list `no_items()`

**File:** `wp-career-board/admin/class-admin-employers.php` — `no_items()` method

Replace:
```php
public function no_items(): void {
	esc_html_e( 'No employers found.', 'wp-career-board' );
}
```

With:
```php
public function no_items(): void {
	?>
	<div class="wcb-no-items-state">
		<span class="dashicons dashicons-businessman"></span>
		<span class="wcb-no-items-title"><?php esc_html_e( 'No employers yet', 'wp-career-board' ); ?></span>
		<p><?php esc_html_e( 'Employers are users with the Employer role. Invite them via the Add New button above.', 'wp-career-board' ); ?></p>
		<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="button button-primary">
			<?php esc_html_e( 'Add Employer', 'wp-career-board' ); ?>
		</a>
	</div>
	<?php
}
```

---

## Task 6: Free — Candidates list `no_items()`

**File:** `wp-career-board/admin/class-admin-candidates.php` — `no_items()` method

Same pattern as employers but with `dashicons-groups` icon and candidates-specific copy.

---

## Task 7: Pro CSS additions

**File:** `wp-career-board-pro/assets/admin.css`

Append at end of file:

```css
/* ── Page intro description ──────────────────────────────────────────────── */
.wcbp-page-intro {
	color: #646970;
	font-size: 13px;
	margin: 8px 0 20px;
	max-width: 760px;
}

/* ── Info card (Field Builder idle state) ────────────────────────────────── */
.wcbp-info-card {
	background: #f6f7f7;
	border: 1px solid #dcdcde;
	border-radius: 4px;
	padding: 24px 28px;
	max-width: 760px;
	margin-top: 8px;
}

.wcbp-info-card h3 {
	margin: 0 0 8px;
	font-size: 14px;
	color: #1d2327;
}

.wcbp-info-card > p {
	color: #646970;
	margin: 0 0 16px;
	font-size: 13px;
}

/* ── Field types grid ────────────────────────────────────────────────────── */
.wcbp-field-types-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
	gap: 8px;
	margin-top: 4px;
}

.wcbp-field-type-pill {
	display: flex;
	align-items: center;
	gap: 6px;
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 4px;
	padding: 6px 10px;
	font-size: 12px;
	color: #1d2327;
}

.wcbp-field-type-pill .dashicons {
	font-size: 14px;
	width: 14px;
	height: 14px;
	color: #2271b1;
	flex-shrink: 0;
}

/* ── Boards table — column widths ────────────────────────────────────────── */
.wcbp-boards-table .column-jobs        { width: 80px; }
.wcbp-boards-table .column-stages      { width: 80px; }
.wcbp-boards-table .column-credit_cost { width: 110px; }
.wcbp-boards-table .column-actions     { width: 120px; }

/* ── Credits page — balance form inline ──────────────────────────────────── */
.wcbp-balance-lookup-row {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-top: 12px;
}
```

---

## Task 8: Pro — Boards page overhaul

**File:** `wp-career-board-pro/admin/class-admin-boards.php`

**Changes:**
1. Add `<hr class="wp-header-end">` after the Add Board button (already has it — confirm at line 53)
2. Add page intro paragraph after `<hr>`
3. Styled empty state when no boards
4. Column data: show "N stages" / "—" and "N credit(s)" / "Free" instead of raw integers

**Revised `render()` body:**

```php
// After <hr class="wp-header-end">:
<p class="description wcbp-page-intro">
    <?php esc_html_e( 'Boards let you segment your job marketplace by industry, region, or brand. Each board has its own application pipeline, credit cost, and custom field schema.', 'wp-career-board-pro' ); ?>
</p>

// Empty state — replace bare <p> with:
<div class="wcbp-empty-state">
    <span class="wcbp-empty-state__icon dashicons dashicons-category"></span>
    <p><strong><?php esc_html_e( 'No boards yet', 'wp-career-board-pro' ); ?></strong></p>
    <p><?php esc_html_e( 'Create your first board to segment the job marketplace by industry, region, or brand.', 'wp-career-board-pro' ); ?></p>
    <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcb_board' ) ); ?>" class="button button-primary">
        <?php esc_html_e( 'Add Your First Board', 'wp-career-board-pro' ); ?>
    </a>
</div>

// Stage count cell — replace raw (int) $wcbp_stage_count:
<td>
    <?php
    if ( $wcbp_stage_count > 0 ) {
        printf(
            /* translators: %d: number of stages */
            esc_html( _n( '%d stage', '%d stages', $wcbp_stage_count, 'wp-career-board-pro' ) ),
            (int) $wcbp_stage_count
        );
    } else {
        echo '—';
    }
    ?>
</td>

// Credit cost cell — replace raw (int) $wcbp_credit_cost:
<td>
    <?php
    if ( $wcbp_credit_cost > 0 ) {
        printf(
            /* translators: %d: credit cost */
            esc_html( _n( '%d credit', '%d credits', $wcbp_credit_cost, 'wp-career-board-pro' ) ),
            (int) $wcbp_credit_cost
        );
    } else {
        esc_html_e( 'Free', 'wp-career-board-pro' );
    }
    ?>
</td>
```

---

## Task 9: Pro — Credits page intro + empty state

**File:** `wp-career-board-pro/admin/class-admin-credits.php`

**Changes:**
1. Add page intro description after `<hr class="wp-header-end">`
2. Replace bare packages empty state `<p>` with styled `.wcbp-empty-state`

**Intro paragraph** (after `<hr class="wp-header-end">`):
```php
<p class="description wcbp-page-intro">
    <?php esc_html_e( 'Manage credit packages employers purchase to post jobs, configure Stripe payment settings, and look up individual employer balances.', 'wp-career-board-pro' ); ?>
</p>
```

**Packages empty state:**
```php
<div class="wcbp-empty-state">
    <span class="wcbp-empty-state__icon dashicons dashicons-tickets-alt"></span>
    <p><strong><?php esc_html_e( 'No credit packages yet', 'wp-career-board-pro' ); ?></strong></p>
    <p><?php esc_html_e( 'Create packages with a credit amount and Stripe Price ID so employers can purchase posting credits.', 'wp-career-board-pro' ); ?></p>
    <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcb_credit_package' ) ); ?>" class="button button-primary">
        <?php esc_html_e( 'Add Credit Package', 'wp-career-board-pro' ); ?>
    </a>
</div>
```

---

## Task 10: Pro — Field Builder complete overhaul

**File:** `wp-career-board-pro/admin/class-admin-field-builder.php`

This page needs the most work. Three distinct states:

### State A: No boards exist

```php
if ( empty( $wcbp_boards ) ) {
    ?>
    <div class="wcbp-empty-state">
        <span class="wcbp-empty-state__icon dashicons dashicons-forms"></span>
        <p><strong><?php esc_html_e( 'No boards found', 'wp-career-board-pro' ); ?></strong></p>
        <p><?php esc_html_e( 'Custom fields are organised by board. Create a board first, then return here to define its field schema.', 'wp-career-board-pro' ); ?></p>
        <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcb_board' ) ); ?>" class="button button-primary">
            <?php esc_html_e( 'Create a Board', 'wp-career-board-pro' ); ?>
        </a>
    </div>
    <?php
    return; // Early return — nothing else to show
}
```

### State B: Board selector + idle info card (no board selected)

Replace the single bare `<p class="description">`:
```php
// Show board selector (existing form markup stays)
// Below selector, when $wcbp_active_board === 0:
<div class="wcbp-info-card">
    <h3><?php esc_html_e( 'Custom Fields — 17 supported types', 'wp-career-board-pro' ); ?></h3>
    <p>
        <?php esc_html_e( 'Custom fields extend job application forms with board-specific questions. Select a board above to view and edit its field schema.', 'wp-career-board-pro' ); ?>
    </p>
    <div class="wcbp-field-types-grid">
        <?php
        $wcbp_type_icons = array(
            'text'         => array( 'dashicons-editor-textcolor', 'Text' ),
            'textarea'     => array( 'dashicons-editor-paragraph', 'Textarea' ),
            'number'       => array( 'dashicons-calculator', 'Number' ),
            'email'        => array( 'dashicons-email', 'Email' ),
            'url'          => array( 'dashicons-admin-links', 'URL' ),
            'date'         => array( 'dashicons-calendar-alt', 'Date' ),
            'date_range'   => array( 'dashicons-calendar', 'Date Range' ),
            'select'       => array( 'dashicons-menu', 'Dropdown' ),
            'multi_select' => array( 'dashicons-list-view', 'Multi-Select' ),
            'checkbox'     => array( 'dashicons-yes-alt', 'Checkbox' ),
            'radio'        => array( 'dashicons-marker', 'Radio' ),
            'file'         => array( 'dashicons-upload', 'File Upload' ),
            'video_url'    => array( 'dashicons-video-alt3', 'Video URL' ),
            'location'     => array( 'dashicons-location', 'Location' ),
            'salary_range' => array( 'dashicons-money-alt', 'Salary Range' ),
            'repeater'     => array( 'dashicons-controls-repeat', 'Repeater' ),
            'conditional'  => array( 'dashicons-randomize', 'Conditional' ),
        );
        foreach ( $wcbp_type_icons as $wcbp_slug => $wcbp_meta ) :
            ?>
            <div class="wcbp-field-type-pill">
                <span class="dashicons <?php echo esc_attr( $wcbp_meta[0] ); ?>"></span>
                <?php echo esc_html( $wcbp_meta[1] ); ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
```

### State C: Board selected — React mount point (unchanged)

```php
<div id="wcbp-field-builder-root" class="wcbp-field-builder">
    <p class="wcbp-fb-loading"><?php esc_html_e( 'Loading field builder…', 'wp-career-board-pro' ); ?></p>
</div>
```

---

## Task 11: Pro — AI Settings, Job Feed, Migration page structure

**File:** `wp-career-board-pro/admin/class-pro-admin.php`

### AI Settings (`render_ai_settings()`)

Change `<h1>` to `<h1 class="wp-heading-inline">` and add `<hr>` + intro:

```php
<h1 class="wp-heading-inline"><?php esc_html_e( 'AI Settings', 'wp-career-board-pro' ); ?></h1>
<hr class="wp-header-end">
<p class="description wcbp-page-intro">
    <?php esc_html_e( 'Configure the AI provider used for job-to-candidate matching and resume analysis. Supports OpenAI, Anthropic Claude, and local Ollama models.', 'wp-career-board-pro' ); ?>
</p>
```

Also add a `<p class="description">` to the AI Provider row:
```php
<p class="description"><?php esc_html_e( 'Select the AI service that powers candidate matching and AI-generated job descriptions.', 'wp-career-board-pro' ); ?></p>
```

### Job Feed (`render_feed_settings()`)

```php
<h1 class="wp-heading-inline"><?php esc_html_e( 'Job Feed', 'wp-career-board-pro' ); ?></h1>
<hr class="wp-header-end">
<p class="description wcbp-page-intro">
    <?php esc_html_e( 'Publish an XML job feed compatible with Indeed, LinkedIn, and other aggregators. Submit the feed URL to your chosen aggregator\'s employer portal.', 'wp-career-board-pro' ); ?>
</p>
```

### Migration (`render_migration()`)

```php
<h1 class="wp-heading-inline"><?php esc_html_e( 'Migration', 'wp-career-board-pro' ); ?></h1>
<hr class="wp-header-end">
<p class="description wcbp-page-intro">
    <?php esc_html_e( 'Import jobs from WP Job Manager or a CSV file. Already-imported posts are detected and skipped automatically.', 'wp-career-board-pro' ); ?>
</p>
```

---

## Implementation Order

Work sequentially to keep each commit small and reviewable:

1. **Free CSS** — add `.wcb-no-items-state` to `admin.css`
2. **Free list pages** — update all five `no_items()` methods (Tasks 2–6), single commit
3. **Pro CSS** — add new rules to `assets/admin.css` (Task 7)
4. **Pro Boards** — full page overhaul (Task 8)
5. **Pro Credits** — intro + empty state (Task 9)
6. **Pro Field Builder** — three-state redesign (Task 10)
7. **Pro page structure** — AI Settings, Job Feed, Migration (Task 11), single commit

**WPCS gate before each commit:**
```
mcp__wpcs__wpcs_fix_file → mcp__wpcs__wpcs_check_staged → mcp__wpcs__wpcs_phpstan_check → commit
```

**Commit format:**
- Free: `feat(wcb): T{N} — enterprise admin UI polish`
- Pro: `feat(wcbp): P{N} — enterprise admin UI polish`

---

## Verification Checklist

After all changes, verify in browser:

- [ ] Free: Jobs list empty state shows icon + title + description + CTA
- [ ] Free: Applications list empty state shows icon + explanation (no CTA — candidates submit)
- [ ] Free: Companies list empty state shows icon + explanation + CTA
- [ ] Free: Employers list empty state shows icon + explanation + CTA
- [ ] Pro: Boards page has intro para under header
- [ ] Pro: Boards empty state shows styled card + "Add Your First Board" CTA
- [ ] Pro: Boards table — stages column shows "2 stages" or "—", not raw "2" or "0"
- [ ] Pro: Boards table — credit cost shows "5 credits" or "Free", not "5" or "0"
- [ ] Pro: Credits page has intro para
- [ ] Pro: Credits empty state shows styled card with Add Package CTA
- [ ] Pro: Field Builder with no boards → styled empty state + "Create a Board" CTA
- [ ] Pro: Field Builder with boards but none selected → info card with 17 field types grid
- [ ] Pro: Field Builder board selected → React app loads normally
- [ ] Pro: AI Settings has `<hr class="wp-header-end">` + intro para
- [ ] Pro: Job Feed has `<hr class="wp-header-end">` + intro para
- [ ] Pro: Migration has `<hr class="wp-header-end">` + intro para
- [ ] Zero WPCS errors across all changed files
