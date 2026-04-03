# WCB Block Design System + Cross-Theme Audit Spec

## Goal

Create a universal frontend block design system for WP Career Board that ensures every block renders with Notion-like premium UX across any WordPress theme. This becomes the standard for all 29 current blocks and all future blocks.

## Key Decisions

- **Density:** Comfortable (Notion-like generous whitespace)
- **Icons:** Full Lucide on frontend, matching backend admin
- **Typography:** Inherit font-family from theme, enforce own weight/size scale
- **Accessibility:** WCAG 2.1 AA minimum
- **Responsive:** 100% at all viewports down to 320px
- **Cross-browser:** Chrome, Firefox, Safari, Edge (last 2 versions) + iOS Safari + Chrome Android

---

## Part 1: Design Token System

### File: `assets/css/frontend-tokens.css`

All blocks reference these tokens. No hardcoded values in individual block CSS.

### Spacing Scale

| Token | Value | Usage |
|---|---|---|
| `--wcb-space-xs` | 4px | Inline gaps, icon margins |
| `--wcb-space-sm` | 8px | Badge padding, tight gaps |
| `--wcb-space-md` | 12px | Form field padding, list gaps |
| `--wcb-space-lg` | 16px | Card inner padding (compact areas) |
| `--wcb-space-xl` | 20px | Card padding (standard) |
| `--wcb-space-2xl` | 24px | Section padding |
| `--wcb-space-3xl` | 32px | Page-level section gaps |
| `--wcb-space-4xl` | 48px | Hero/empty state vertical padding |

### Typography Scale

Font family is always `inherit` (theme domain). We control weight and size.

| Token | Value | Usage |
|---|---|---|
| `--wcb-text-xs` | 0.75rem (12px) | Timestamps, meta, captions |
| `--wcb-text-sm` | 0.8125rem (13px) | Secondary text, badge labels |
| `--wcb-text-base` | 0.875rem (14px) | Body text, form labels |
| `--wcb-text-md` | 0.9375rem (15px) | Card titles, nav items |
| `--wcb-text-lg` | 1.125rem (18px) | Section headings |
| `--wcb-text-xl` | 1.25rem (20px) | Page section titles |
| `--wcb-text-2xl` | 1.5rem (24px) | Page headings |
| `--wcb-text-3xl` | 2rem (32px) | Hero stat numbers |
| `--wcb-font-normal` | 400 | Body text |
| `--wcb-font-medium` | 500 | Labels, nav items |
| `--wcb-font-semibold` | 600 | Card titles, section headings |
| `--wcb-font-bold` | 700 | Page headings, stat numbers |
| `--wcb-leading-tight` | 1.25 | Headings |
| `--wcb-leading-normal` | 1.5 | Body text |
| `--wcb-leading-relaxed` | 1.625 | Long-form content |

### Border Radius

| Token | Value | Usage |
|---|---|---|
| `--wcb-radius-sm` | 6px | Badges, small buttons, inputs |
| `--wcb-radius-md` | 8px | Buttons, filter pills |
| `--wcb-radius-lg` | 10px | Search bars, form groups |
| `--wcb-radius-xl` | 12px | Cards, panels |
| `--wcb-radius-2xl` | 16px | Hero sections, company profiles |
| `--wcb-radius-full` | 9999px | Avatars, pills, dots |

### Shadows

| Token | Value | Usage |
|---|---|---|
| `--wcb-shadow-xs` | 0 1px 2px rgba(0,0,0,0.04) | Subtle card rest state |
| `--wcb-shadow-sm` | 0 1px 3px rgba(0,0,0,0.08) | Cards at rest |
| `--wcb-shadow-md` | 0 4px 12px rgba(0,0,0,0.08) | Card hover, dropdowns |
| `--wcb-shadow-lg` | 0 8px 24px rgba(0,0,0,0.12) | Modals, floating panels |
| `--wcb-shadow-focus` | 0 0 0 3px rgba(37,99,235,0.12) | Focus rings |

### Colors (extend existing)

Keep existing `--wcb-primary`, `--wcb-border`, etc. Add:

| Token | Value | Usage |
|---|---|---|
| `--wcb-text-primary` | inherit | Headings (theme domain) |
| `--wcb-text-secondary` | #6b7280 | Muted text, timestamps |
| `--wcb-text-tertiary` | #9ca3af | Placeholders, disabled |
| `--wcb-bg-subtle` | #f8fafc | Form backgrounds, alt rows |
| `--wcb-bg-hover` | #f1f5f9 | Hover states |
| `--wcb-success` | #16a34a | Published, hired, active |
| `--wcb-success-bg` | #dcfce7 | Success badge background |
| `--wcb-warning` | #d97706 | Pending, reviewing |
| `--wcb-warning-bg` | #fef3c7 | Warning badge background |
| `--wcb-danger` | #dc2626 | Rejected, error |
| `--wcb-danger-bg` | #fee2e2 | Danger badge background |
| `--wcb-info` | #2563eb | Info, links |
| `--wcb-info-bg` | #dbeafe | Info badge background |

### Transitions

| Token | Value | Usage |
|---|---|---|
| `--wcb-transition-fast` | 0.12s ease | Hover states, toggles |
| `--wcb-transition-normal` | 0.2s ease | Panel slides, fades |
| `--wcb-transition-slow` | 0.3s ease | Modal open/close |

### Lucide Icon Sizes

| Token | Value | Usage |
|---|---|---|
| `--wcb-icon-xs` | 14px | Inline with text |
| `--wcb-icon-sm` | 16px | Badge icons, nav |
| `--wcb-icon-md` | 18px | Default icon size |
| `--wcb-icon-lg` | 24px | Section headers |
| `--wcb-icon-xl` | 32px | Empty states |
| `--wcb-icon-2xl` | 48px | Hero empty states |
| `--wcb-icon-stroke` | 1.75 | Default stroke width |

---

## Part 2: Component Standards

### Cards

Every card across all blocks follows this pattern:

```css
.wcb-card {
    background: var(--wcb-base);
    border: 1px solid var(--wcb-border);
    border-radius: var(--wcb-radius-xl);
    padding: var(--wcb-space-xl);
    transition: box-shadow var(--wcb-transition-fast), border-color var(--wcb-transition-fast);
}
.wcb-card:hover {
    box-shadow: var(--wcb-shadow-md);
    border-color: color-mix(in srgb, var(--wcb-border) 50%, var(--wcb-primary) 15%);
}
```

**Rules:**
- No `!important` on card styles
- Cards never have hardcoded widths
- Cards use CSS Grid or Flexbox for internal layout
- Card content uses the spacing scale exclusively

### Badges

Standardized status badge system:

```css
.wcb-badge {
    display: inline-flex;
    align-items: center;
    gap: var(--wcb-space-xs);
    padding: 2px 8px;
    font-size: var(--wcb-text-xs);
    font-weight: var(--wcb-font-semibold);
    border-radius: var(--wcb-radius-sm);
    line-height: var(--wcb-leading-normal);
    white-space: nowrap;
}
```

| Variant | Background | Color | Usage |
|---|---|---|---|
| `--success` | `var(--wcb-success-bg)` | `var(--wcb-success)` | Published, Hired, Active |
| `--warning` | `var(--wcb-warning-bg)` | `var(--wcb-warning)` | Pending, Reviewing |
| `--danger` | `var(--wcb-danger-bg)` | `var(--wcb-danger)` | Rejected, Expired |
| `--info` | `var(--wcb-info-bg)` | `var(--wcb-info)` | Submitted, Applied |
| `--muted` | `#f3f4f6` | `#6b7280` | Draft, Archived |
| `--featured` | `#fef3c7` | `#92400e` | Featured Job |

### Buttons

```css
.wcb-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--wcb-space-sm);
    height: 40px;
    padding: 0 var(--wcb-space-lg);
    font-family: inherit;
    font-size: var(--wcb-text-base);
    font-weight: var(--wcb-font-medium);
    border-radius: var(--wcb-radius-md);
    border: 1px solid transparent;
    cursor: pointer;
    transition: all var(--wcb-transition-fast);
    min-width: 44px; /* touch target */
}
```

| Variant | Styles |
|---|---|
| `--primary` | `bg: var(--wcb-primary); color: white; border-color: var(--wcb-primary)` |
| `--secondary` | `bg: white; color: var(--wcb-contrast); border-color: var(--wcb-border)` |
| `--ghost` | `bg: transparent; color: var(--wcb-primary); border: none` |
| `--danger` | `bg: white; color: var(--wcb-danger); border-color: var(--wcb-danger)` |
| `--sm` | `height: 32px; font-size: var(--wcb-text-sm); padding: 0 12px` |
| `--lg` | `height: 48px; font-size: var(--wcb-text-md); padding: 0 24px` |

### Form Fields

```css
.wcb-field {
    width: 100%;
    height: 44px; /* touch target */
    padding: var(--wcb-space-md) var(--wcb-space-lg);
    font-family: inherit;
    font-size: var(--wcb-text-base);
    color: var(--wcb-contrast);
    background: var(--wcb-base);
    border: 1.5px solid var(--wcb-border);
    border-radius: var(--wcb-radius-md);
    transition: border-color var(--wcb-transition-fast), box-shadow var(--wcb-transition-fast);
    appearance: none;
}
.wcb-field:focus {
    outline: none;
    border-color: var(--wcb-primary);
    box-shadow: var(--wcb-shadow-focus);
}
.wcb-field::placeholder {
    color: var(--wcb-text-tertiary);
}
```

### Empty States

Every list/grid block must have an empty state:

```html
<div class="wcb-empty-state">
    <i data-lucide="inbox" class="wcb-empty-state__icon"></i>
    <h3 class="wcb-empty-state__title">No jobs found</h3>
    <p class="wcb-empty-state__desc">Try adjusting your filters or search terms.</p>
    <a href="..." class="wcb-btn wcb-btn--primary">Browse All Jobs</a>
</div>
```

### Grid Layouts

Standard grid for card collections:

```css
.wcb-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr));
    gap: var(--wcb-space-xl);
}
```

The `min(100%, 300px)` ensures single-column on narrow viewports without media queries.

### Lucide Icons (Frontend)

Enqueue `lucide.min.js` on all pages with WCB blocks. Initialize via:

```javascript
document.addEventListener('DOMContentLoaded', function() {
    if (window.lucide) lucide.createIcons();
});
```

Icon HTML pattern: `<i data-lucide="icon-name" class="wcb-icon--{size}"></i>`

All icon-only buttons MUST have `aria-label`:
```html
<button class="wcb-btn wcb-btn--ghost" aria-label="Bookmark this job">
    <i data-lucide="bookmark"></i>
</button>
```

---

## Part 3: Accessibility Requirements

### Focus Management

- All interactive elements: `outline: 2px solid var(--wcb-primary); outline-offset: 2px`
- Never use `outline: none` without a replacement
- Tab order must follow visual order
- Modal/panel trap focus when open, restore on close

### Color Contrast

- Text on white: minimum 4.5:1 ratio
- Large text (18px+): minimum 3:1 ratio
- UI components (borders, icons): minimum 3:1 against adjacent colors
- All status badges meet contrast requirements

### Screen Readers

- Status badges: `<span class="wcb-badge" role="status">Published</span>`
- Icon-only buttons: always `aria-label`
- Loading states: `aria-live="polite"` on containers that update
- Form errors: `aria-describedby` linking error message to field
- Skip link: `.wcb-skip-link` for dashboard sidebar navigation

### Motion

```css
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}
```

### Keyboard Navigation

- All cards: focusable with visible focus ring
- Card links: primary action (title) is the link, not the entire card
- Filters: operable with keyboard (Enter to apply, Escape to clear)
- Modals: Escape to close, Tab to cycle through controls
- Kanban: arrow keys for card movement (with ARIA live announcements)

---

## Part 4: Responsive Rules

### Breakpoints

| Token | Value | Target |
|---|---|---|
| `--wcb-bp-xs` | 480px | Compact mobile |
| `--wcb-bp-sm` | 640px | Mobile |
| `--wcb-bp-md` | 768px | Tablet |
| `--wcb-bp-lg` | 1024px | Desktop |
| `--wcb-bp-xl` | 1280px | Wide desktop |

### Rules

1. **No horizontal overflow** at 320px viewport width
2. **All grids** collapse to single column at 640px
3. **All sidebars** (dashboards) stack below content at 1024px
4. **Touch targets** minimum 44x44px on mobile
5. **Font sizes** never below 12px
6. **Cards** have min-width 0 (prevent overflow)
7. **Images** use `max-width: 100%; height: auto`
8. **Tables** use horizontal scroll wrapper below 640px

### Container Strategy

Every WCB block wrapper:
```css
.wp-block-wp-career-board-{name} {
    max-width: 1200px;
    margin-inline: auto;
    padding-inline: var(--wcb-space-lg);
    box-sizing: border-box;
}
```

This ensures blocks are centered and padded regardless of theme content width.

---

## Part 5: Cross-Browser Requirements

### Support Matrix

| Browser | Version | Priority |
|---|---|---|
| Chrome | Last 2 | P0 |
| Firefox | Last 2 | P0 |
| Safari | Last 2 | P0 |
| Edge | Last 2 | P0 |
| iOS Safari | Last 2 | P0 |
| Chrome Android | Last 2 | P0 |
| Samsung Internet | Last 1 | P1 |

### Progressive Enhancement

- `color-mix()` — fallback to static values
- `container-type: inline-size` — fallback to media queries
- `backdrop-filter` — graceful degradation (solid bg)
- `:has()` — not used for critical layout
- `min()` in grid — supported in all target browsers

---

## Part 6: Theme Compatibility Audit

### Test Themes (10)

1. **Twenty Twenty-Five** — FSE block theme (WP default 2025)
2. **Twenty Twenty-Four** — FSE block theme (WP default 2024)
3. **Astra** — Classic + FSE hybrid (#1 popular, 1M+ installs)
4. **GeneratePress** — Classic, lightweight, minimal CSS
5. **Kadence** — Classic + FSE, opinionated typography/spacing
6. **OceanWP** — Classic, feature-heavy, lots of CSS overrides
7. **Hello Elementor** — Bare minimum classic theme
8. **Neve** — Classic, lightweight, popular (ThemeIsle)
9. **Blocksy** — Modern classic theme, opinionated design
10. **BuddyX** — Community/BuddyPress theme (existing WCB integration)

### Audit Checklist Per Theme

For each theme, test these pages/blocks:

**Page 1: Job Archive** (job-listings + job-filters + job-search)
- [ ] Grid layout: cards fill available width, no overflow
- [ ] Card spacing: consistent gaps between cards
- [ ] Card text: readable, no clipping, proper line-height
- [ ] Badges: visible, correct colors, contrast passes
- [ ] Search bar: full width, focus ring visible
- [ ] Filters: dropdowns render correctly, no theme override
- [ ] Pagination: aligned, functional
- [ ] Empty state: centered, icon renders
- [ ] Mobile (390px): single column, no overflow

**Page 2: Job Single** (job-single)
- [ ] Hero layout: title, company, meta aligned
- [ ] Sidebar: sticky on desktop, stacked on mobile
- [ ] Apply panel: slides in, overlay works, close button
- [ ] Section cards: proper spacing, border visible
- [ ] Badges: status, job type, salary
- [ ] Mobile: sidebar below content, panel full-width

**Page 3: Employer Dashboard** (employer-dashboard)
- [ ] Sidebar nav: visible, active state works
- [ ] Content area: proper padding, no theme margins
- [ ] Tables: readable, proper borders
- [ ] Buttons: styled correctly, not overridden by theme
- [ ] Mobile: sidebar stacks above content

**Page 4: Candidate Dashboard** (candidate-dashboard)
- [ ] Same checks as employer dashboard
- [ ] Resume section: form fields, upload area
- [ ] Application list: status badges, dates

**Page 5: Post Job Form** (job-form)
- [ ] Step indicator: visible, active state
- [ ] Form fields: full width, proper focus
- [ ] Grid layout: 2-column on desktop, 1-column mobile
- [ ] Salary row: 3-column grid, responsive
- [ ] Submit button: styled, not theme-overridden

**Page 6: Company Archive** (company-archive)
- [ ] Grid/list toggle works
- [ ] Company cards: logo, name, trust badges
- [ ] Search bar: renders correctly
- [ ] Mobile: single column

**Page 7: Company Profile** (company-profile)
- [ ] Hero: cover image, logo overlay, name
- [ ] Details grid: 2-column, responsive
- [ ] Job list within company: proper cards
- [ ] Mobile: single column, smaller avatar

**Page 8: Registration** (employer-registration)
- [ ] Centered form (max 480px)
- [ ] Role selection cards
- [ ] Form fields: proper styling
- [ ] Mobile: full width

**Pro Blocks (if active):**
- [ ] Resume Builder: accordion, form fields, entries
- [ ] Application Kanban: columns, drag cards, scroll
- [ ] AI Chat Search: message bubbles, input
- [ ] Resume Archive: grid, search, pagination
- [ ] Resume Single: hero, timeline, skills

### Issue Categories

| Severity | Description | Action |
|---|---|---|
| **P0 Critical** | Content overflow, broken layout, invisible text | Fix immediately |
| **P1 Major** | Misaligned elements, wrong spacing, theme overrides | Fix before release |
| **P2 Minor** | Subtle color difference, slight spacing variance | Fix if time allows |
| **P3 Cosmetic** | Theme-specific visual preference | Document, defer |

---

## Part 7: Theme Isolation CSS

To prevent theme CSS from bleeding into WCB blocks:

```css
/* Reset theme styles on WCB block wrappers */
[class*="wp-block-wp-career-board"] {
    font-family: inherit;
    font-size: var(--wcb-text-base);
    line-height: var(--wcb-leading-normal);
    color: var(--wcb-contrast);
    box-sizing: border-box;
}
[class*="wp-block-wp-career-board"] *,
[class*="wp-block-wp-career-board"] *::before,
[class*="wp-block-wp-career-board"] *::after {
    box-sizing: inherit;
}

/* Prevent theme link styles from overriding WCB buttons */
[class*="wp-block-wp-career-board"] .wcb-btn {
    text-decoration: none;
    color: inherit;
}
[class*="wp-block-wp-career-board"] .wcb-btn:hover {
    text-decoration: none;
}

/* Prevent theme list styles */
[class*="wp-block-wp-career-board"] ul,
[class*="wp-block-wp-career-board"] ol {
    list-style: none;
    margin: 0;
    padding: 0;
}
```

---

## Part 8: Implementation Strategy

### Phase 1: Create Design Token System
- Create `assets/css/frontend-tokens.css` with all token definitions
- Create `assets/css/frontend-components.css` with shared component classes
- Enqueue Lucide on frontend for all WCB block pages
- Update `frontend.css` to reference tokens instead of hardcoded values

### Phase 2: Migrate All 29 Blocks
- Update each block's `style.css` to reference design tokens
- Replace hardcoded colors, spacing, radius, shadows with token references
- Replace inline SVGs with Lucide `<i data-lucide="...">` in render.php
- Add missing accessibility attributes (aria-label, role, focus management)
- Add empty states to all list/grid blocks
- Ensure all blocks have `min-width: 0` to prevent overflow

### Phase 3: Install Themes + Audit
- Install all 10 themes via WP-CLI
- For each theme: activate, navigate to each test page, screenshot, check against audit checklist
- Log issues by severity (P0/P1/P2/P3)
- Fix P0 and P1 issues
- Document P2/P3 for follow-up

### Phase 4: Responsive + Accessibility Verification
- Test all pages at 320px, 390px, 768px, 1024px, 1280px viewports
- Run aXe/WAVE accessibility checks on each page
- Verify keyboard navigation on all interactive elements
- Test focus management on modals/panels

---

## Deliverables

1. `docs/BLOCK-DESIGN-SYSTEM.md` — Permanent guideline for all blocks
2. `assets/css/frontend-tokens.css` — Design token definitions
3. `assets/css/frontend-components.css` — Shared component classes
4. Updated `style.css` for all 29 blocks — Token-based, accessible
5. Updated `render.php` for blocks needing Lucide icons
6. Theme audit report — Issues found per theme with screenshots
7. All P0/P1 issues fixed
