# WP Career Board — Session Plan (2026-04-07)

## What was done

### 1. Pro Setup Wizard (Free + Pro)
- Free wizard refactored to support dynamic steps via `wcb_wizard_steps` filter
- Step templates extracted into `admin/views/wizard-steps/` partials
- `wizard.js` made step-count-agnostic with `CustomEvent` pattern
- Added `wcb_wizard_force_render`, `wcb_wizard_completed`, `wcb_wizard_complete_redirect` hooks
- **Commits:** `df466ec` (Free), `2e87132` (Pro)

### 2. Pro Upsells in Free Plugin
- Resume import card gated behind Pro check (shows "Requires Pro" teaser)
- 6 locked Pro feature teasers in settings sidebar (Pipeline, Credits, Fields, AI, Feed, Boards)
- Dismissible "Upgrade to Pro" dashboard card with REST dismiss endpoint
- Plugin URI updated to store page
- readme.txt updated with Pro Features section
- **Commit:** `3afd716`

### 3. Documentation Coverage
- Free: All 31 docs validated against codebase, fixed inaccuracies (setup wizard, blocks list, settings tabs, troubleshooting)
- Free: New `admin-guide/05-import.md` created
- Pro: 6 new feature docs (AI, Feed, Analytics, Notifications, PWA, Migration)
- Pro: 13 existing docs validated + expanded (blocks ref 9→15, field types +5, resume groups, map providers, multi-board settings)
- Pro: `docs_config.json` updated (1→15 sections)
- **Commits:** `3afd716` (Free docs), `4951d64` (Pro docs)

---

## What remains

### Phase 1: Browser Verification (Playwright MCP)

**Free standalone (Pro deactivated):**
- [ ] Settings sidebar: PRO group with 6 locked teasers → each links to store
- [ ] Dashboard: Upgrade to Pro card with dismiss
- [ ] Import page: Resume card locked with PRO badge
- [ ] All above at 390px viewport

**Pro Setup Wizard (both active):**
- [ ] Fresh install: 4 steps (Create Pages → Sample Data → License → Credits)
- [ ] Mini-wizard: `?wcbp_only=1` shows Pro-only steps
- [ ] License step: activate, skip, status badge
- [ ] Credits step: threshold, URL, WooCommerce warning

### Phase 2: Screenshots

**Prep model site:**
- Clean blank/duplicate jobs and resumes
- Ensure sample data is realistic
- Use `?autologin=1` for admin access

**Free plugin screenshots (`docs/website/images/`):**
- [ ] Setup wizard (both steps)
- [ ] Settings page (all tabs)
- [ ] Settings sidebar with Pro teasers
- [ ] Dashboard with upgrade banner
- [ ] Import page (locked resume card)
- [ ] Find Jobs page (frontend)
- [ ] Job single page
- [ ] Employer Dashboard
- [ ] Candidate Dashboard

**Pro plugin screenshots (`docs/website/images/`):**
- [ ] Pro Setup Wizard (License + Credits steps)
- [ ] Settings tabs (Pipeline, Credits, Field Builder, AI, Feed, Boards, License)
- [ ] Application Pipeline (Kanban)
- [ ] Resume Builder (candidate view)
- [ ] Find Candidates page
- [ ] Credit Balance, Job Map, Job Alerts blocks

**After screenshots:** Update image references in markdown docs.

### Phase 3: Quick Tasks
- [ ] Move Basecamp card #9758570697 from Scope → Ready for Testing
- [ ] Store pages: bump version from v0.1.0 → v1.0.0

---

## Key Files Reference

### Free Plugin (`wp-career-board`)
- Wizard: `admin/class-setup-wizard.php`, `admin/views/wizard-steps/`, `assets/js/wizard.js`
- Upsells: `admin/class-admin-settings.php` (Pro teasers), `admin/class-admin.php` (dashboard banner), `admin/class-admin-import.php` (resume gating)
- Dismiss endpoint: `api/endpoints/class-admin-endpoint.php`
- Docs: `docs/website/` (31 files across 6 sections)

### Pro Plugin (`wp-career-board-pro`)
- Wizard: `admin/class-pro-setup-wizard.php`, `admin/views/wizard-steps/`, `assets/js/pro-wizard.js`
- Boot: `core/class-pro-plugin.php` (ProSetupWizard boot)
- Redirect: `admin/class-pro-admin.php` (mini-wizard redirect)
- Docs: `docs/website/` (26 files across 15 sections)

### Store URLs
- Free: https://store.wbcomdesigns.com/wp-career-board/
- Pro: https://store.wbcomdesigns.com/wp-career-board-pro/
- Docs: https://store.wbcomdesigns.com/wp-career-board/docs/
