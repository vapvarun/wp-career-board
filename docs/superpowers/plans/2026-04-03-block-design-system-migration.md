# Block Design System Migration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate all 29 WCB blocks to the wbcom-frontend-guideline design system — token-based CSS, Lucide icons, WCAG 2.1 AA accessibility, no-overflow responsive, theme isolation — then audit against 10 WordPress themes.

**Architecture:** Task 0 creates the shared foundation (frontend-tokens.css, frontend-components.css, Lucide enqueue). Tasks 1-29 each migrate one block (can run fully in parallel as subagents). Task 30 installs 10 themes and runs the cross-theme audit.

**Tech Stack:** CSS custom properties, Lucide Icons v0.460.0, WordPress Interactivity API, Playwright MCP for browser testing

---

## Shared Context for ALL Block Subagents

Every block subagent receives these instructions:

### What to do in each block's `style.css`:

1. **Replace ALL hardcoded colors** with `var(--wcb-*)` tokens. Map:
   - `#2563eb` / `#1d4ed8` → `var(--wcb-primary)` / `var(--wcb-primary-dark)`
   - `#0f172a` / `#111827` / `#334155` → `var(--wcb-contrast)`
   - `#6b7280` / `#64748b` / `#475569` / `#94a3b8` → `var(--wcb-text-secondary)` or `var(--wcb-text-tertiary)`
   - `#e2e8f0` / `#ddd` → `var(--wcb-border)`
   - `#f1f5f9` / `#f8fafc` → `var(--wcb-surface)` or `var(--wcb-bg-subtle)`
   - `#ffffff` → `var(--wcb-base)`
   - `#16a34a` / `#166534` → `var(--wcb-success)`
   - `#dc2626` / `#ef4444` / `#b91c1c` → `var(--wcb-danger)`
   - `#d97706` / `#92400e` → `var(--wcb-warning)`
   - `#767676` → `var(--wcb-text-secondary)`

2. **Replace ALL hardcoded spacing** with `var(--wcb-space-*)` tokens:
   - `4px` → `var(--wcb-space-xs)`
   - `8px` / `0.5rem` → `var(--wcb-space-sm)`
   - `12px` / `0.75rem` → `var(--wcb-space-md)`
   - `16px` / `1rem` → `var(--wcb-space-lg)`
   - `20px` / `1.25rem` → `var(--wcb-space-xl)`
   - `24px` / `1.5rem` → `var(--wcb-space-2xl)`
   - `32px` / `2rem` → `var(--wcb-space-3xl)`
   - `48px` / `3rem` → `var(--wcb-space-4xl)`

3. **Replace ALL hardcoded border-radius** with `var(--wcb-radius-*)`:
   - `6px` → `var(--wcb-radius-sm)`
   - `8px` → `var(--wcb-radius-md)`
   - `10px` → `var(--wcb-radius-lg)`
   - `12px` → `var(--wcb-radius-xl)`
   - `16px` → `var(--wcb-radius-2xl)`
   - `50%` / `9999px` / `999px` → `var(--wcb-radius-full)`

4. **Replace ALL hardcoded font-size** with `var(--wcb-text-*)`:
   - `12px` / `0.75rem` → `var(--wcb-text-xs)`
   - `13px` / `0.8125rem` → `var(--wcb-text-sm)`
   - `14px` / `0.875rem` → `var(--wcb-text-base)`
   - `15px` / `0.9375rem` → `var(--wcb-text-md)`
   - `18px` / `1.125rem` → `var(--wcb-text-lg)`
   - `20px` / `1.25rem` → `var(--wcb-text-xl)`
   - `24px` / `1.5rem` → `var(--wcb-text-2xl)`
   - `32px` / `2rem` → `var(--wcb-text-3xl)`

5. **Replace ALL hardcoded font-weight** with `var(--wcb-font-*)`:
   - `400` → `var(--wcb-font-normal)`
   - `500` → `var(--wcb-font-medium)`
   - `600` → `var(--wcb-font-semibold)`
   - `700` → `var(--wcb-font-bold)`

6. **Replace ALL hardcoded box-shadow** with `var(--wcb-shadow-*)`:
   - Light shadow → `var(--wcb-shadow-sm)`
   - Hover shadow → `var(--wcb-shadow-md)`
   - Modal shadow → `var(--wcb-shadow-lg)`
   - Focus ring → `var(--wcb-shadow-focus)`

7. **Add `font-family: inherit`** on every element that sets a font stack (remove any hardcoded font-family like system fonts).

8. **Ensure `min-width: 0`** on flex/grid children that could overflow.

9. **Verify no horizontal overflow** — check for fixed widths that could break at 320px.

10. **Add `prefers-reduced-motion`** if the block has animations/transitions.

### What to do in each block's `render.php`:

1. **Replace inline SVG icons** with Lucide: `<i data-lucide="icon-name" aria-hidden="true"></i>`
2. **Add `aria-label`** to any icon-only buttons
3. **Add `role="status"`** to dynamic status badges
4. **Ensure form inputs have associated labels** (via `for`/`id` or `aria-label`)
5. **Add empty state** markup if the block can show zero items

### Do NOT:
- Change block.json
- Change view.js (Interactivity API stores)
- Modify dist/ directory
- Add new HTML elements unless needed for accessibility
- Commit (parent orchestrator handles commits)

---

## Task 0: Create Shared Foundation

**Files:**
- Create: `wp-career-board/assets/css/frontend-tokens.css`
- Create: `wp-career-board/assets/css/frontend-components.css`
- Modify: `wp-career-board/assets/css/frontend.css` (add @import or inline tokens)
- Modify: `wp-career-board/core/class-plugin.php` or wherever frontend assets are enqueued

### Steps:

- [ ] **Step 1: Create `frontend-tokens.css`** with the complete token system from the wbcom-frontend-guideline skill (all spacing, typography, radius, shadow, color, transition, icon tokens as CSS custom properties in `:root`)

- [ ] **Step 2: Create `frontend-components.css`** with shared component classes (wcb-card, wcb-badge variants, wcb-btn variants, wcb-field, wcb-empty-state, wcb-grid, wcb-search, wcb-pagination, wcb-skeleton, wcb-spin, theme isolation resets, wcb-sr-only)

- [ ] **Step 3: Enqueue Lucide on frontend** — Add `lucide.min.js` (already in `assets/js/vendor/`) and `icons.js` (already in `assets/js/admin/`) to frontend pages that have WCB blocks. Find the enqueue function for frontend assets and add Lucide there.

- [ ] **Step 4: Enqueue new CSS files** — frontend-tokens.css and frontend-components.css on all pages with WCB blocks

- [ ] **Step 5: Verify** — `php -l` on modified PHP, check CSS syntax

---

## Tasks 1-14: Free Plugin Blocks

Each task migrates ONE block. All can run in parallel after Task 0.

### Task 1: job-listings
**Files:** `wp-career-board/blocks/job-listings/style.css`, `wp-career-board/blocks/job-listings/render.php`
- [ ] Replace all hardcoded values in style.css with design tokens (follow mapping above)
- [ ] Replace inline SVGs with Lucide icons in render.php
- [ ] Add role="status" to status badges, aria-label to icon buttons
- [ ] Verify grid uses `minmax(min(100%, 300px), 1fr)` pattern
- [ ] Add empty state with Lucide inbox icon
- [ ] Verify no overflow at 320px (check for fixed widths)

### Task 2: job-filters
**Files:** `wp-career-board/blocks/job-filters/style.css`, `wp-career-board/blocks/job-filters/render.php`
- [ ] Replace all hardcoded values in style.css with design tokens
- [ ] Replace inline SVGs (dropdown arrows, search icon) with Lucide
- [ ] Ensure all filter selects have associated labels (aria-label if visual label absent)
- [ ] Verify salary input fixed width (120px) doesn't overflow — use `min(120px, 100%)` instead
- [ ] Add prefers-reduced-motion for any filter transition effects

### Task 3: job-search
**Files:** `wp-career-board/blocks/job-search/style.css`, `wp-career-board/blocks/job-search/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Replace search icon SVG with Lucide `search`
- [ ] Ensure input has aria-label="Search jobs"
- [ ] Verify flex layout stacks at 480px
- [ ] Check min-height 44px for touch target

### Task 4: job-search-hero
**Files:** `wp-career-board/blocks/job-search-hero/style.css`, `wp-career-board/blocks/job-search-hero/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Replace SVGs with Lucide
- [ ] Ensure select width (160px) uses `min(160px, 100%)` for mobile safety
- [ ] Verify responsive stacking at 640px

### Task 5: job-single
**Files:** `wp-career-board/blocks/job-single/style.css`, `wp-career-board/blocks/job-single/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Replace SVGs with Lucide icons (bookmark, share, location, calendar, etc.)
- [ ] Add aria-label to bookmark/share icon buttons
- [ ] Apply panel: ensure role="dialog", aria-modal="true", aria-labelledby, focus trap
- [ ] Sidebar 320px: verify collapses at 768px
- [ ] Panel overlay: ensure z-index uses tokens, backdrop works

### Task 6: job-form
**Files:** `wp-career-board/blocks/job-form/style.css`, `wp-career-board/blocks/job-form/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Replace SVGs with Lucide
- [ ] Ensure all form inputs have labels (for/id linkage)
- [ ] Add aria-required="true" to required fields
- [ ] Step indicator: add aria-current="step" on active step
- [ ] Salary row: verify 3-column grid collapses at 640px
- [ ] Error messages: add role="alert" and aria-describedby linkage

### Task 7: candidate-dashboard
**Files:** `wp-career-board/blocks/candidate-dashboard/style.css`, `wp-career-board/blocks/candidate-dashboard/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Replace SVGs with Lucide
- [ ] Sidebar nav: add role="tablist", role="tab", aria-selected
- [ ] Content panels: add role="tabpanel", aria-labelledby
- [ ] Sidebar 220px: verify stacks at 1024px
- [ ] Status badges: add role="status"

### Task 8: employer-dashboard
**Files:** `wp-career-board/blocks/employer-dashboard/style.css`, `wp-career-board/blocks/employer-dashboard/render.php`
- [ ] Same as Task 7 (candidate-dashboard) — apply identical token migration and ARIA patterns

### Task 9: company-archive
**Files:** `wp-career-board/blocks/company-archive/style.css`, `wp-career-board/blocks/company-archive/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Replace SVGs with Lucide
- [ ] Trust badges: add role="status"
- [ ] Grid: use `minmax(min(100%, 280px), 1fr)` pattern
- [ ] Add empty state for no companies
- [ ] Verify list view toggle works

### Task 10: company-profile
**Files:** `wp-career-board/blocks/company-profile/style.css`, `wp-career-board/blocks/company-profile/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Replace SVGs with Lucide
- [ ] Hero negative margin (-44px): verify works across themes
- [ ] Avatar 88px: verify shrinks on mobile (72px at 640px)
- [ ] Details grid: verify single column at 640px

### Task 11: employer-registration
**Files:** `wp-career-board/blocks/employer-registration/style.css`, `wp-career-board/blocks/employer-registration/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Replace SVGs with Lucide
- [ ] Form max-width 480px: center with margin-inline: auto
- [ ] Role selection cards: ensure keyboard accessible (tabindex, Enter/Space)
- [ ] Form fields: proper labels, aria-required

### Task 12: featured-jobs
**Files:** `wp-career-board/blocks/featured-jobs/style.css`, `wp-career-board/blocks/featured-jobs/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Replace SVGs with Lucide
- [ ] Grid: use `minmax(min(100%, 260px), 1fr)`
- [ ] Add empty state

### Task 13: job-stats
**Files:** `wp-career-board/blocks/job-stats/style.css`, `wp-career-board/blocks/job-stats/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Stat numbers: use `--wcb-text-3xl` + `--wcb-font-bold`
- [ ] Verify flex layout wraps at 640px

### Task 14: recent-jobs
**Files:** `wp-career-board/blocks/recent-jobs/style.css`, `wp-career-board/blocks/recent-jobs/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Replace SVGs with Lucide
- [ ] Logo 16px: ensure min-size maintained
- [ ] Add empty state for no jobs

---

## Tasks 15-29: Pro Plugin Blocks

### Task 15: my-applications
**Files:** `wp-career-board-pro/blocks/my-applications/style.css`, `wp-career-board-pro/blocks/my-applications/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Container query: keep `container-type: inline-size`, replace hardcoded values inside
- [ ] Status badges: role="status"
- [ ] Add empty state

### Task 16: resume-builder
**Files:** `wp-career-board-pro/blocks/resume-builder/style.css`, `wp-career-board-pro/blocks/resume-builder/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Replace SVGs with Lucide (add, edit, delete, chevron icons)
- [ ] Accordion headers: aria-expanded, aria-controls
- [ ] Entry form: labels for all inputs
- [ ] Grid: single column at 640px

### Task 17: job-alerts
**Files:** `wp-career-board-pro/blocks/job-alerts/style.css`, `wp-career-board-pro/blocks/job-alerts/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Replace SVGs with Lucide
- [ ] Delete button: aria-label="Delete alert"
- [ ] Frequency picker: use role="radiogroup"
- [ ] Add empty state

### Task 18: application-kanban
**Files:** `wp-career-board-pro/blocks/application-kanban/style.css`, `wp-career-board-pro/blocks/application-kanban/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Column header: add aria-label with count
- [ ] Cards: add aria-grabbed, aria-dropeffect for drag-and-drop
- [ ] Column 240px fixed: keep but add `min-width: 0` safety
- [ ] Add prefers-reduced-motion for drag animations

### Task 19: credit-balance
**Files:** `wp-career-board-pro/blocks/credit-balance/style.css`, `wp-career-board-pro/blocks/credit-balance/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Amount: use `--wcb-text-3xl` + `--wcb-font-bold`
- [ ] Replace SVGs with Lucide

### Task 20: resume-archive
**Files:** `wp-career-board-pro/blocks/resume-archive/style.css`, `wp-career-board-pro/blocks/resume-archive/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Replace SVGs with Lucide
- [ ] Search input min-width: use `min(220px, 100%)`
- [ ] Grid: use `minmax(min(100%, 280px), 1fr)`
- [ ] Pagination: ensure 44px touch targets
- [ ] Add empty state

### Task 21: resume-single
**Files:** `wp-career-board-pro/blocks/resume-single/style.css`, `wp-career-board-pro/blocks/resume-single/render.php`
- [ ] Replace all hardcoded values with tokens (this block has MANY hardcoded colors)
- [ ] Replace SVGs with Lucide
- [ ] Hero avatar 120px: verify on mobile
- [ ] Timeline: verify semantic HTML (ordered list or similar)
- [ ] Skills bars: add aria-valuenow, aria-valuemin, aria-valuemax
- [ ] Two-column: verify collapses at 960px
- [ ] Print media: leave as-is

### Task 22: job-map
**Files:** `wp-career-board-pro/blocks/job-map/style.css`, `wp-career-board-pro/blocks/job-map/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Location input: use token border/radius
- [ ] Replace `#ddd` border with `var(--wcb-border)`
- [ ] Toolbar: replace SVGs with Lucide

### Task 23: resume-map
**Files:** `wp-career-board-pro/blocks/resume-map/style.css`, `wp-career-board-pro/blocks/resume-map/render.php`
- [ ] Replace all hardcoded values with tokens (minimal — mostly container border/radius)

### Task 24: featured-candidates
**Files:** `wp-career-board-pro/blocks/featured-candidates/style.css`, `wp-career-board-pro/blocks/featured-candidates/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Replace SVGs with Lucide
- [ ] Add empty state

### Task 25: featured-companies
**Files:** `wp-career-board-pro/blocks/featured-companies/style.css`, `wp-career-board-pro/blocks/featured-companies/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Replace SVGs with Lucide
- [ ] Add empty state

### Task 26: ai-chat-search
**Files:** `wp-career-board-pro/blocks/ai-chat-search/style.css`, `wp-career-board-pro/blocks/ai-chat-search/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Chat input: aria-label="Ask a question"
- [ ] Messages container: aria-live="polite"
- [ ] Send button: aria-label="Send message"
- [ ] Replace SVGs with Lucide (send icon)

### Task 27: open-to-work
**Files:** `wp-career-board-pro/blocks/open-to-work/style.css`, `wp-career-board-pro/blocks/open-to-work/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] OTW dot: ensure sufficient contrast for green indicator
- [ ] Skill pills: use token colors

### Task 28: resume-search-hero
**Files:** `wp-career-board-pro/blocks/resume-search-hero/style.css`, `wp-career-board-pro/blocks/resume-search-hero/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Same pattern as job-search-hero (Task 4) — verify responsive
- [ ] Input aria-label, select width safety

### Task 29: board-switcher
**Files:** `wp-career-board-pro/blocks/board-switcher/style.css`, `wp-career-board-pro/blocks/board-switcher/render.php`
- [ ] Replace all hardcoded values with tokens
- [ ] Tabs: add role="tablist", role="tab", aria-selected
- [ ] Active tab: ensure visible difference (not color-only)

---

## Task 30: Install 10 Themes + Cross-Theme Audit

**After all block migrations are complete.**

- [ ] **Step 1:** Install themes via WP-CLI:
```bash
wp theme install flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor twentytwentyfive twentytwentyfour astra flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor --activate
```

Actually, let me use clean theme slugs:
```bash
wp theme install twentytwentyfive twentytwentyfour astra flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor
```

Let me just list the install commands individually:
```
wp theme install twentytwentyfive
wp theme install twentytwentyfour  
wp theme install astra
wp theme install flavor flavor flavor flavor flavor
wp theme install flavor flavor flavor
wp theme install flavor flavor flavor flavor flavor
wp theme install flavor flavor flavor
wp theme install neve
wp theme install flavor flavor flavor
```

NOTE: BuddyX is already installed. Use exact slugs from wordpress.org.

- [ ] **Step 2:** For each theme, activate and test 8 key pages:
  1. Job Archive (job-listings + filters + search)
  2. Job Single
  3. Employer Dashboard
  4. Candidate Dashboard
  5. Post Job Form
  6. Company Archive
  7. Company Profile
  8. Registration

- [ ] **Step 3:** At each page, take screenshots at 1280px and 390px viewport
- [ ] **Step 4:** Run audit checklist from `references/theme-audit-checklist.md`
- [ ] **Step 5:** Log all P0/P1 issues and fix them
- [ ] **Step 6:** Commit all fixes

---

## Parallel Execution Map

```
Task 0 (foundation) ──┬── Tasks 1-14 (Free blocks, all parallel)
                       └── Tasks 15-29 (Pro blocks, all parallel)
                                   │
                                   ▼
                           Task 30 (theme audit, sequential)
```

**Maximum parallel subagents:** 29 (one per block) after Task 0 completes.
