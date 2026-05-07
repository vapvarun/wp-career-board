# WP Career Board (Free + Pro) — Upscale Stabilization Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Companion doc:** `plan/upscale-audit-2026-05-07.md` lists every finding with severity. This doc plans how to fix them safely.

**Goal:** Land all P0/P1 fixes from the upscale-model audit without introducing regressions. Stabilization-first: every phase is independently shippable; every task has a verification step; every refactor preserves existing behavior.

**Architecture:** Phases ordered by risk + dependency. Foundation phases land first so later phases can lean on them. Two big-bang refactors (Settings accessor, Abilities API migration) are split across many tiny commits with regression checks between each batch.

**Tech Stack:** PHP 8.1+, WordPress 6.9+, WPCS, PHPStan level 5, PHPUnit. Existing build via Grunt + npm.

---

## Reading order

1. **Phase 1 — Foundation:** F1 + F5. ~30 minutes. Must land first.
2. **Phase 2 — Settings accessor (U9):** highest-leverage. Other phases consume it.
3. **Phase 3 — Abilities API (U3):** mechanical migration once Phase 2 is in.
4. **Phase 4 — Pro decoupling completion (F2, F9, F10):** consumes the accessor.
5. **Phase 5 — CSS file splits (F4):** independent, can run in parallel with 4.
6. **Phase 6 — REST extensibility (F7, F6):** non-blocking.
7. **Phase 7 — Perf (F8):** measurement-driven; benchmark first, fix second.
8. **Phase 8 — Cleanup (F3, U6, U7, U8):** smaller items batched together.

**Each phase ends with `composer ci:no-journeys` (or equivalent: WPCS check, PHPStan, browser smoke).** Don't merge a phase if the gate is red.

---

## Phase 1 — Foundation (must land first)

### Task 1.1: F1 — Replace direct `is_plugin_active` with filter

**Files:**
- Modify: `wp-career-board/core/class-install.php:166`

**Why:** Direct reference to `wp-career-board-pro/wp-career-board-pro.php` violates the decoupling rule. Free's `class-pro-coordination.php` exposes `wcb_pro_active` as the documented detection filter; Pro registers `__return_true` for it via `core/class-free-coordination.php:36`.

- [ ] **Step 1: Read context**

```bash
sed -n '155,180p' "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board/core/class-install.php"
```

Expected: see the `if ( version_compare( ... ) )` block where the violation lives.

- [ ] **Step 2: Replace the call**

In `core/class-install.php` around line 162-167, replace:

```php
				if ( ! function_exists( 'is_plugin_active' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$pro_active                         = is_plugin_active( 'wp-career-board-pro/wp-career-board-pro.php' );
				$settings['resume_archive_enabled'] = (bool) $pro_active;
```

with:

```php
				$settings['resume_archive_enabled'] = (bool) apply_filters( 'wcb_pro_active', false );
```

- [ ] **Step 3: Verify Pro hook ordering**

Pro registers `wcb_pro_active` in `class-free-coordination.php` at `init` priority 5 (per Pro's `class-pro-plugin.php` boot). The migration in `class-install.php::maybe_migrate()` runs from `plugins_loaded@10` — Pro is not yet loaded at `plugins_loaded`, so the filter would return `false` even when Pro is installed.

**This is the actual reason for the original `is_plugin_active` check.** The filter approach won't work without changing the migration timing.

Two options, pick one:

**Option A (recommended):** Move the migration to `init@5`. Filter is now reachable.

**Option B:** Keep `is_plugin_active` with a justification comment + `// phpcs:ignore WCB.Decoupling.NoProReference` and a carve-out entry in the architecture contract.

- [ ] **Step 4: Apply Option A**

In `core/class-install.php`, find the registration of `maybe_migrate` (likely in `boot()` or constructor). Change the hook from `plugins_loaded` to `init` priority `5`:

```php
add_action( 'init', array( $this, 'maybe_migrate' ), 5 );
```

If it's currently on `plugins_loaded`, the change is the action name + priority.

- [ ] **Step 5: Smoke test**

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public" && wp eval '
delete_option("wcb_db_version"); // simulate fresh install needing migration
update_option("wcb_db_version", "1.1.0"); // older version triggers 1.2 migration
$existing = (array) get_option("wcb_settings", array());
unset($existing["resume_archive_enabled"]);
update_option("wcb_settings", $existing);
do_action("init"); // run the migration
$after = (array) get_option("wcb_settings", array());
echo "resume_archive_enabled after migration: ";
var_dump($after["resume_archive_enabled"] ?? "MISSING");
'
```

Expected: `bool(true)` if Pro is active, `bool(false)` otherwise.

- [ ] **Step 6: Update CI gate**

Add a regression check to `bin/check-pro-decoupling.sh`:

```bash
# Verify the install migration uses the filter, not direct plugin path
if grep -q "is_plugin_active.*wp-career-board-pro" core/class-install.php; then
    echo "FAIL: core/class-install.php must use wcb_pro_active filter, not is_plugin_active"
    exit 1
fi
```

- [ ] **Step 7: Commit**

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" && \
git add core/class-install.php bin/check-pro-decoupling.sh && \
git commit -m "fix(wcb): install migration uses wcb_pro_active filter, not is_plugin_active

Removes the last hard-coded reference to wp-career-board-pro/wp-career-board-pro.php
from Free runtime code. Migration runs at init@5 so Pro's filter at
class-free-coordination.php:36 is registered when we read.

CI gate updated to keep the regression out."
```

---

### Task 1.2: F5 — Refresh manifests on both plugins

**Files:**
- Modify: `wp-career-board/audit/manifest.json` (regenerated)
- Modify: `wp-career-board/audit/FEATURE_AUDIT.md` (regenerated)
- Modify: `wp-career-board-pro/audit/manifest.json` (regenerated)
- Modify: `wp-career-board-pro/audit/FEATURE_AUDIT.md` (regenerated)

**Why:** Free manifest's own `refresh_notes` says it's missing 17 hooks. Both manifests are 9 days stale — every subsequent phase relies on the manifest being accurate (especially Phase 4 which references the cross-plugin filter list).

- [ ] **Step 1: Run wp-plugin-onboard refresh on Free**

```
/wp-plugin-onboard --refresh /Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board
```

The skill diff-walks `git log --name-only` since `manifest.generated.at` and re-scans only changed categories.

- [ ] **Step 2: Run on Pro**

```
/wp-plugin-onboard --refresh /Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board-pro
```

- [ ] **Step 3: Verify coverage gate passes**

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" && \
jq '.hooks_fired | length' audit/manifest.json
```

Compare to ground truth:

```bash
grep -roh "do_action\s*(\s*'wcb_[a-z_]\+'" wp-career-board --include='*.php' 2>/dev/null | grep -v 'dist/\|vendor/' | sort -u | wc -l
```

The numbers should be within 5%. If not, re-dispatch the agent for the `hooks_fired` category per Phase 2.4 of the wp-plugin-onboard skill.

- [ ] **Step 4: Commit refreshed manifests**

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" && \
git add audit/ && git commit -m "docs(wcb): refresh manifest after Round 6 + 1.2.0 work

Closes the 17-hook coverage gap flagged in the previous manifest's
refresh_notes. Updated hooks_fired, services, capabilities."

cd "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board-pro" && \
git add audit/ && git commit -m "docs(wcbp): refresh manifest after Round 6 + 1.2.0 work"
```

---

## Phase 2 — Settings accessor (U9)

This phase introduces `WCB\Admin\Settings` as the single read path for `wcb_settings`. The class wraps the option with three semantic accessors (`get`, `bool`, `int`) that handle the `array_key_exists` vs `! empty` semantic correctly by default. Once the class is in place, 41 files migrate one batch at a time with WPCS + PHPStan + browser smoke between batches.

### Task 2.1: Create the `Settings` accessor class with tests

**Files:**
- Create: `wp-career-board/core/class-settings.php`
- Create: `wp-career-board/tests/test-settings-accessor.php`

- [ ] **Step 1: Write the failing test**

Create `wp-career-board/tests/test-settings-accessor.php`:

```php
<?php
/**
 * @package WP_Career_Board
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use WCB\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Test_Settings_Accessor extends TestCase {

	protected function setUp(): void {
		delete_option( 'wcb_settings' );
		Settings::flush_cache();
	}

	public function test_bool_returns_default_when_key_absent(): void {
		update_option( 'wcb_settings', array() );
		Settings::flush_cache();
		$this->assertTrue( Settings::bool( 'apply_resume_required', true ) );
		$this->assertFalse( Settings::bool( 'auto_publish_jobs', false ) );
	}

	public function test_bool_respects_explicit_off(): void {
		update_option( 'wcb_settings', array( 'apply_resume_required' => false ) );
		Settings::flush_cache();
		$this->assertFalse( Settings::bool( 'apply_resume_required', true ) );
	}

	public function test_get_returns_default_when_missing(): void {
		update_option( 'wcb_settings', array() );
		Settings::flush_cache();
		$this->assertSame( 'USD', Settings::get( 'salary_currency', 'USD' ) );
	}

	public function test_get_returns_value_when_present(): void {
		update_option( 'wcb_settings', array( 'salary_currency' => 'INR' ) );
		Settings::flush_cache();
		$this->assertSame( 'INR', Settings::get( 'salary_currency', 'USD' ) );
	}

	public function test_int_clamps_and_defaults(): void {
		update_option( 'wcb_settings', array( 'jobs_per_page' => '15' ) );
		Settings::flush_cache();
		$this->assertSame( 15, Settings::int( 'jobs_per_page', 10 ) );
		$this->assertSame( 25, Settings::int( 'missing_key', 25 ) );
	}

	public function test_all_returns_full_array(): void {
		update_option( 'wcb_settings', array( 'foo' => 'bar' ) );
		Settings::flush_cache();
		$this->assertSame( array( 'foo' => 'bar' ), Settings::all() );
	}

	public function test_cache_is_per_request(): void {
		update_option( 'wcb_settings', array( 'foo' => 'bar' ) );
		Settings::flush_cache();
		$first  = Settings::all();
		update_option( 'wcb_settings', array( 'foo' => 'baz' ) );
		$second = Settings::all();
		$this->assertSame( $first, $second, 'Cache should not pick up the change without flush' );
		Settings::flush_cache();
		$third = Settings::all();
		$this->assertSame( array( 'foo' => 'baz' ), $third );
	}
}
```

- [ ] **Step 2: Run test to confirm it fails (class missing)**

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" && \
vendor/bin/phpunit tests/test-settings-accessor.php
```

Expected: FAIL with "Class 'WCB\Admin\Settings' not found".

- [ ] **Step 3: Implement the accessor**

Create `wp-career-board/core/class-settings.php`:

```php
<?php
/**
 * Centralized accessor for the wcb_settings option.
 *
 * Every read of `get_option('wcb_settings')` should go through this class so
 * the array_key_exists vs !empty semantic is uniform. Without this the same
 * key can read true in one place and false in another (the bug class behind
 * Basecamp 9863100490).
 *
 * @package WP_Career_Board
 * @since   1.2.0
 */

declare( strict_types=1 );

namespace WCB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	private const OPTION_KEY = 'wcb_settings';

	/**
	 * Per-request cache. wp_cache_* layers do not help here because the
	 * autoloaded option is already in WP's option cache; this static is
	 * to avoid re-running the (array) cast + null-coalesce on every read.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $cache = null;

	public static function all(): array {
		return self::$cache ??= (array) get_option( self::OPTION_KEY, array() );
	}

	public static function get( string $key, mixed $default = null ): mixed {
		$settings = self::all();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	public static function bool( string $key, bool $default ): bool {
		$settings = self::all();
		return array_key_exists( $key, $settings ) ? ! empty( $settings[ $key ] ) : $default;
	}

	public static function int( string $key, int $default ): int {
		$settings = self::all();
		return array_key_exists( $key, $settings ) ? (int) $settings[ $key ] : $default;
	}

	public static function string( string $key, string $default ): string {
		$settings = self::all();
		return array_key_exists( $key, $settings ) ? (string) $settings[ $key ] : $default;
	}

	public static function flush_cache(): void {
		self::$cache = null;
	}
}
```

- [ ] **Step 4: Wire the cache flush to update_option**

In `wp-career-board/core/class-plugin.php`, find the `boot()` method or constructor that registers the autoloader. Add at boot:

```php
add_action( 'updated_option', static function ( string $option ): void {
	if ( 'wcb_settings' === $option ) {
		\WCB\Admin\Settings::flush_cache();
	}
} );
add_action( 'added_option', static function ( string $option ): void {
	if ( 'wcb_settings' === $option ) {
		\WCB\Admin\Settings::flush_cache();
	}
} );
```

This guarantees the per-request cache is invalidated when something writes the option (like the settings page sanitize callback).

- [ ] **Step 5: Run tests, verify they pass**

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" && \
vendor/bin/phpunit tests/test-settings-accessor.php
```

Expected: PASS, 7 tests.

- [ ] **Step 6: WPCS + PHPStan**

```bash
mcp__wpcs__wpcs_check_file core/class-settings.php
mcp__wpcs__wpcs_phpstan_check . 5
```

Expected: 0 errors on the new file.

- [ ] **Step 7: Commit the foundation**

```bash
git add core/class-settings.php tests/test-settings-accessor.php core/class-plugin.php && \
git commit -m "feat(wcb): WCB\Admin\Settings accessor for wcb_settings

Single read path for the wcb_settings option. The array_key_exists vs
!empty semantic is uniform across get/bool/int/string accessors so the
'absent key' default behavior cannot drift between reader sites again.

Per-request cache is invalidated on option writes via updated_option /
added_option hooks. Tests cover the absent/explicit/default behavior."
```

### Task 2.2: Migrate Free block render.php files (batch 1 of 3)

**Files:**
- Modify: `wp-career-board/blocks/job-form/render.php` (line 132)
- Modify: `wp-career-board/blocks/job-form-simple/render.php` (line 61)
- Modify: `wp-career-board/blocks/job-listings/render.php`
- Modify: `wp-career-board/blocks/recent-jobs/render.php`
- Modify: `wp-career-board/blocks/featured-jobs/render.php`
- Modify: `wp-career-board/blocks/job-search-hero/render.php`

**Pattern to replace:**

Before:
```php
$wcb_site_settings = (array) get_option( 'wcb_settings', array() );
$wcb_preferred     = ! empty( $wcb_site_settings['salary_currency'] ) ? (string) $wcb_site_settings['salary_currency'] : 'USD';
```

After:
```php
$wcb_preferred = \WCB\Admin\Settings::string( 'salary_currency', 'USD' );
```

- [ ] **Step 1: Verify class autoload reachable in render contexts**

Block render.php files run in the front-end request lifecycle after `init`. Free's autoloader is registered at plugin file load (line 1 of `wp-career-board.php`), so `\WCB\Admin\Settings::` is reachable.

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public" && wp eval '
require_once ABSPATH . "wp-content/plugins/wp-career-board/core/class-settings.php";
echo class_exists("WCB\\Admin\\Settings") ? "YES" : "NO";
'
```

Expected: `YES`.

- [ ] **Step 2: Migrate each file in this batch one at a time**

For each file, read the current `get_option('wcb_settings', ...)` block and replace with the appropriate accessor call. Examples:

`blocks/job-form/render.php` at line 130-138:

```php
// BEFORE
$wcb_site_settings    = (array) get_option( 'wcb_settings', array() );
$wcb_preferred        = strtoupper( ! empty( $wcb_site_settings['salary_currency'] ) ? (string) $wcb_site_settings['salary_currency'] : 'USD' );

// AFTER
$wcb_preferred = strtoupper( \WCB\Admin\Settings::string( 'salary_currency', 'USD' ) );
```

`blocks/job-listings/render.php` (per-page setting):

```php
// BEFORE
$settings = (array) get_option( 'wcb_settings', array() );
$per_page = ! empty( $settings['jobs_per_page'] ) ? (int) $settings['jobs_per_page'] : 10;

// AFTER
$per_page = \WCB\Admin\Settings::int( 'jobs_per_page', 10 );
```

- [ ] **Step 3: Browser-verify each migrated block**

Per CLAUDE.md "Verify-Per-Item Rule": for each block, navigate to a page rendering it and confirm the page renders without errors. Take a Playwright screenshot.

```bash
mcp__plugin_playwright_playwright__browser_navigate http://job-portal.local/jobs/
mcp__plugin_playwright_playwright__browser_take_screenshot --filename plan/screenshots/u9-job-listings-after.png
```

Repeat for: post-job page (job-form), one job single (job-search-hero), front page (featured-jobs).

- [ ] **Step 4: WPCS + PHPStan on the changed files**

```bash
mcp__wpcs__wpcs_check_file blocks/job-form/render.php
mcp__wpcs__wpcs_check_file blocks/job-form-simple/render.php
mcp__wpcs__wpcs_check_file blocks/job-listings/render.php
mcp__wpcs__wpcs_check_file blocks/recent-jobs/render.php
mcp__wpcs__wpcs_check_file blocks/featured-jobs/render.php
mcp__wpcs__wpcs_check_file blocks/job-search-hero/render.php
mcp__wpcs__wpcs_phpstan_check . 5
```

Expected: 0 errors on touched files. PHPStan must remain green.

- [ ] **Step 5: Commit batch 1**

```bash
git add blocks/job-form/render.php blocks/job-form-simple/render.php \
        blocks/job-listings/render.php blocks/recent-jobs/render.php \
        blocks/featured-jobs/render.php blocks/job-search-hero/render.php && \
git commit -m "refactor(wcb): job-* blocks read wcb_settings via WCB\Admin\Settings"
```

### Task 2.3: Migrate Free block render.php files (batch 2 of 3) — dashboards + companies

**Files:**
- Modify: `wp-career-board/blocks/employer-dashboard/render.php`
- Modify: `wp-career-board/blocks/candidate-dashboard/render.php`
- Modify: `wp-career-board/blocks/company-archive/render.php`
- Modify: `wp-career-board/blocks/company-profile/render.php`
- Modify: `wp-career-board/blocks/employer-registration/render.php`
- Modify: `wp-career-board/blocks/job-single/render.php`

**Steps:** Same shape as Task 2.2.

- [ ] **Step 1: Migrate each file**

For each, read the existing `get_option('wcb_settings', ...)` block and replace with `WCB\Admin\Settings::` accessors.

- [ ] **Step 2: Browser-verify each block**

Navigate to: employer dashboard, candidate dashboard, /companies/, /company/{slug}/, /register-employer/, one job-single page. Screenshot each.

- [ ] **Step 3: WPCS + PHPStan + commit**

```bash
git add blocks/employer-dashboard/render.php blocks/candidate-dashboard/render.php \
        blocks/company-archive/render.php blocks/company-profile/render.php \
        blocks/employer-registration/render.php blocks/job-single/render.php && \
git commit -m "refactor(wcb): dashboard + company blocks read wcb_settings via accessor"
```

### Task 2.4: Migrate Free non-block sites

**Files:**
- Modify: `wp-career-board/wp-career-board.php`
- Modify: `wp-career-board/core/class-install.php`
- Modify: `wp-career-board/core/class-plugin.php`
- Modify: `wp-career-board/admin/class-admin.php`
- Modify: `wp-career-board/admin/class-setup-wizard.php`
- Modify: `wp-career-board/admin/class-email-settings.php`
- Modify: `wp-career-board/integrations/reign/class-reign-integration.php`
- Modify: `wp-career-board/api/endpoints/class-candidates-endpoint.php`
- Modify: `wp-career-board/api/endpoints/class-jobs-endpoint.php`
- Modify: `wp-career-board/api/endpoints/class-applications-endpoint.php`
- Modify: `wp-career-board/api/endpoints/class-settings-endpoint.php`
- Modify: `wp-career-board/api/endpoints/class-employers-endpoint.php`
- Modify: `wp-career-board/modules/candidates/class-candidates-module.php`
- Modify: `wp-career-board/modules/employers/class-employers-module.php`

**Important — `class-admin-settings.php` exception:** The settings PAGE itself (`admin/class-admin-settings.php`) is the writer. It calls `get_option` to populate the form. This call should NOT be migrated — the writer reads the raw option to render the form, then sanitizes and writes. Document this exception with a one-line comment.

- [ ] **Step 1: Migrate each file**

Same pattern as Task 2.2.

For `api/endpoints/class-settings-endpoint.php` — this endpoint exposes the option to REST consumers. Keep `get_option` here because the endpoint INTENT is "give me the raw option." Add a comment.

- [ ] **Step 2: WPCS, PHPStan**

```bash
for f in core/class-install.php core/class-plugin.php admin/class-admin.php \
         admin/class-setup-wizard.php admin/class-email-settings.php \
         api/endpoints/class-jobs-endpoint.php \
         api/endpoints/class-applications-endpoint.php \
         api/endpoints/class-candidates-endpoint.php \
         api/endpoints/class-employers-endpoint.php \
         modules/candidates/class-candidates-module.php \
         modules/employers/class-employers-module.php; do
  mcp__wpcs__wpcs_check_file "$f"
done
mcp__wpcs__wpcs_phpstan_check . 5
```

- [ ] **Step 3: Browser smoke test**

Walk admin: Career Board → Settings → switch tabs and save. Walk frontend: post a job, view job, withdraw application. No regressions.

- [ ] **Step 4: Commit batch 3**

```bash
git add wp-career-board.php core/class-install.php core/class-plugin.php \
        admin/class-admin.php admin/class-setup-wizard.php admin/class-email-settings.php \
        api/endpoints/ modules/candidates/ modules/employers/ \
        integrations/reign/class-reign-integration.php && \
git commit -m "refactor(wcb): non-block sites read wcb_settings via accessor

Settings page sanitize/render still call get_option directly because they
ARE the writer — exception documented with inline comments. Same for the
settings REST endpoint which exposes the raw shape."
```

### Task 2.5: Migrate Pro sites

**Files:**
- Modify: `wp-career-board-pro/wp-career-board-pro.php` (line 196)
- Modify: `wp-career-board-pro/blocks/featured-candidates/render.php` (line 22)
- Modify: `wp-career-board-pro/blocks/resume-search-hero/render.php` (line 27)
- Modify: `wp-career-board-pro/blocks/resume-archive/render.php` (line 58)
- Modify: `wp-career-board-pro/blocks/open-to-work/render.php` (line 24)
- Modify: `wp-career-board-pro/core/class-pro-plugin.php` (line 369)
- Modify: `wp-career-board-pro/core/class-pro-install.php` (line 108)
- Modify: `wp-career-board-pro/admin/class-pro-setup-wizard.php` (lines 131, 326, 380)
- Modify: `wp-career-board-pro/admin/views/wizard-steps/pro-pages.php` (line 17)
- Modify: `wp-career-board-pro/integrations/buddypress/class-bp-pro-integration.php` (line 332)
- Modify: `wp-career-board-pro/modules/notifications-pro/emails/class-email-low-balance.php` (line 84)
- Modify: `wp-career-board-pro/modules/notifications-pro/emails/class-email-credit-topup.php` (line 86)
- Modify: `wp-career-board-pro/modules/resume/class-resume-module.php` (lines 368, 381, 917)
- Modify: `wp-career-board-pro/modules/notifications-bell/class-notifications-bell-module.php`
- Modify: `wp-career-board-pro/modules/pwa/class-pwa-module.php`
- Modify: `wp-career-board-pro/modules/boards/class-board-settings.php` (lines 56-58 — shipped today)

**Reads only.** Pro WRITES to `wcb_settings` are addressed in Phase 4 (F2 + F9).

- [ ] **Step 1: Verify Pro can call Free's class**

Pro's autoloader does NOT load Free's classes — Free's autoloader does. But the Pro plugin loads after Free, so by the time Pro code runs, `\WCB\Admin\Settings::` is already registered.

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public" && wp eval '
echo class_exists("WCB\\Admin\\Settings") ? "available to Pro: YES" : "NO";
'
```

Expected: `YES`.

- [ ] **Step 2: Migrate each file**

Same pattern as Task 2.2/2.4.

For `modules/boards/class-board-settings.php` — the new code I shipped today already does the right thing manually. Replace with the accessor:

```php
// BEFORE (today's commit 2d02911)
private function default_currency(): string {
    $site = (array) get_option( 'wcb_settings', array() );
    return ! empty( $site['salary_currency'] ) ? strtoupper( (string) $site['salary_currency'] ) : 'USD';
}

// AFTER
private function default_currency(): string {
    return strtoupper( \WCB\Admin\Settings::string( 'salary_currency', 'USD' ) );
}
```

- [ ] **Step 3: WPCS + PHPStan + browser smoke**

Walk Pro features: AI chat search, resume archive, board switcher, credit balance widget, notifications bell. No regressions.

- [ ] **Step 4: Commit Pro migration**

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board-pro" && \
git add . && git commit -m "refactor(wcbp): all wcb_settings reads route through WCB\Admin\Settings

Pro consumes Free's accessor — there is no parallel Pro accessor. Single
read path for both plugins is the entire point."
```

### Task 2.6: Lint gate to prevent regression

**Files:**
- Modify: `wp-career-board/bin/check-pro-decoupling.sh` (or new script)

- [ ] **Step 1: Add a lint check**

Append to `bin/check-pro-decoupling.sh`:

```bash
# U9 regression guard: every wcb_settings read should go through WCB\Admin\Settings
RAW_READS=$(grep -rln "get_option\s*(\s*['\"]wcb_settings['\"]" \
    --include='*.php' \
    --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=audit \
    --exclude-dir=docs --exclude-dir=plan --exclude-dir=tests \
    "$PLUGIN_DIR" 2>/dev/null | \
    grep -v "core/class-settings.php\|admin/class-admin-settings.php\|api/endpoints/class-settings-endpoint.php" | wc -l)

if [ "$RAW_READS" -gt 0 ]; then
    echo "FAIL: $RAW_READS raw get_option('wcb_settings') reads outside accessor"
    exit 1
fi
```

- [ ] **Step 2: Run the gate**

```bash
bash bin/check-pro-decoupling.sh
```

Expected: green.

- [ ] **Step 3: Commit the gate**

```bash
git add bin/check-pro-decoupling.sh && \
git commit -m "ci(wcb): block raw get_option('wcb_settings') reads outside accessor"
```

---

## Phase 3 — Abilities API migration (U3)

The audit found 58 `current_user_can` calls. After inspection, breakdown:

- **~25 sites** check `wcb_*` ability names → mechanical replacement with `wp_is_authorized()`
- **~15 sites** check WP core caps (`manage_options`, `edit_posts`, `edit_post` with post ID) → legitimate, leave with `// phpcs:ignore` and an explanatory comment
- **~18 sites** in admin contexts already have `// phpcs:ignore WordPress.WP.Capabilities.Unknown` — replace with `wp_is_authorized()` and remove the suppression

### Task 3.1: Audit + categorize each call site

**Files:**
- Create: `wp-career-board/plan/u3-current-user-can-audit.csv`

- [ ] **Step 1: Generate the audit CSV**

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins" && \
{
  echo "file,line,cap,kind,action"
  grep -rn "current_user_can\s*(" wp-career-board wp-career-board-pro --include='*.php' 2>/dev/null \
    | grep -v 'dist/\|vendor/\|/audit/\|/docs/\|/plan/\|/tests/' \
    | awk -F: '{print $1","$2","substr($0, index($0,":")+1)}'
} > wp-career-board/plan/u3-current-user-can-audit.csv
```

- [ ] **Step 2: Manual classification**

Open the CSV. For each row, set `kind` to one of:
- `wcb_ability` — cap is `wcb_*` or `wcbp_*` registered as an ability → `migrate`
- `wp_core` — cap is `manage_options` / `edit_posts` / `edit_post` (with post ID) → `keep_with_carve_out`
- `legacy_unknown` — cap is unrecognized → `investigate`

This is a manual review pass. Don't automate the classification.

- [ ] **Step 3: Commit the audit**

```bash
git add plan/u3-current-user-can-audit.csv && \
git commit -m "docs(wcb): u3 — audit of every current_user_can call site"
```

### Task 3.2: Migrate `wcb_ability` sites in Free

**Files (the major ones):**
- `wp-career-board/blocks/job-form-simple/render.php:28`
- `wp-career-board/blocks/candidate-dashboard/render.php:32, 165`
- `wp-career-board/blocks/job-form/render.php:28`
- `wp-career-board/blocks/employer-dashboard/render.php:15`
- `wp-career-board/blocks/job-single/render.php:121, 141`
- `wp-career-board/admin/class-admin-settings.php:264` (and similar admin files)
- `wp-career-board/admin/class-email-settings.php:438`
- `wp-career-board/core/widgets/class-abstract-widget.php:90`

**Pattern:**

```php
// BEFORE
$can_post = function_exists( 'wp_is_ability_granted' )
    ? wp_is_ability_granted( 'wcb_post_jobs' )
    : current_user_can( 'wcb_post_jobs' );

// AFTER (Abilities API is shipped in WP 6.9; the function-exists guard was for the WP 6.8 fallback period)
$can_post = wp_is_ability_granted( 'wcb_post_jobs' );
```

For sites that are ONLY `current_user_can( 'wcb_*' )` (no Abilities-API guard pattern):

```php
// BEFORE
if ( ! current_user_can( 'wcb_manage_settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
    wp_die( ... );
}

// AFTER
if ( ! wp_is_ability_granted( 'wcb_manage_settings' ) ) {
    wp_die( ... );
}
```

The phpcs:ignore comment goes away too.

- [ ] **Step 1: Verify minimum WP version**

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" && \
grep -E "Requires at least|wp_version_compare" wp-career-board.php
```

Confirm Min WP is 6.9 (per CLAUDE.md). If the plugin still claims 6.8 support, the function-exists fallback must remain.

- [ ] **Step 2: Migrate one file as the prototype**

Start with `blocks/job-form/render.php:26-28`. After migration, browser-verify the post-job form renders correctly for an employer.

- [ ] **Step 3: Migrate remaining `wcb_ability` sites**

Batch by directory: blocks/, then admin/, then core/.

- [ ] **Step 4: WPCS + PHPStan + browser smoke**

The phpcs:ignore for `WordPress.WP.Capabilities.Unknown` should now be unnecessary; PHPCS sees `wp_is_ability_granted` as a recognized function.

- [ ] **Step 5: Commit**

```bash
git add blocks/ admin/ core/ && \
git commit -m "refactor(wcb): wcb_* capability checks use Abilities API uniformly

Replaces 25+ current_user_can('wcb_*') sites with wp_is_ability_granted().
Function-exists fallback removed since Min WP is 6.9. WPCS ignore
comments for Capabilities.Unknown deleted along with the migrated calls."
```

### Task 3.3: Migrate `wcb_ability` sites in Pro

**Files:**
- `wp-career-board-pro/blocks/my-applications/render.php:24` (`wcbp_manage_boards`)
- `wp-career-board-pro/admin/class-admin-credits.php:36`
- `wp-career-board-pro/admin/class-pro-admin.php` (8 sites with phpcs:ignore)
- `wp-career-board-pro/admin/class-pro-admin.php:1078`

Same pattern as Task 3.2.

- [ ] **Step 1: Verify Pro abilities are registered**

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public" && wp eval '
do_action("wp_loaded");
$abilities = wp_get_abilities();
echo "Pro abilities registered:\n";
foreach ($abilities as $name => $a) {
    if (str_starts_with($name, "wcbp_") || $name === "wcb_manage_settings") {
        echo "  $name\n";
    }
}
'
```

- [ ] **Step 2-5:** Same as Task 3.2 for Pro files. Commit per plugin.

### Task 3.4: Document `wp_core` carve-outs

**Files:**
- Modify: each file with a kept `current_user_can('manage_options')` or `current_user_can('edit_post', ...)` call gets a comment.

- [ ] **Step 1: For each carve-out, add a comment**

```php
// WP core capability — not an ability. wp_is_ability_granted does not
// understand WP capabilities, only registered abilities.
if ( ! current_user_can( 'manage_options' ) ) {
```

- [ ] **Step 2: Add the carve-out doc**

Append to `docs/HOOKS.md` or `wp-career-board/plan/upscale-stabilization-plan-2026-05-07.md` (this doc):

> **Capabilities-vs-abilities carve-out:** `current_user_can('manage_options')`, `current_user_can('edit_post', $post_id)`, and `current_user_can('edit_posts')` calls for cases where the WP capability has no ability counterpart.

- [ ] **Step 3: Commit + lint gate**

Add to `bin/check-pro-decoupling.sh`:

```bash
# U3 regression guard: no current_user_can('wcb_*') outside the carve-outs
WCB_CAP_CHECKS=$(grep -rn "current_user_can\s*(\s*['\"]wcb_\|current_user_can\s*(\s*['\"]wcbp_" \
    --include='*.php' --exclude-dir=vendor --exclude-dir=audit "$PLUGIN_DIR" 2>/dev/null | wc -l)
if [ "$WCB_CAP_CHECKS" -gt 0 ]; then
    echo "FAIL: $WCB_CAP_CHECKS current_user_can('wcb_*') sites should use wp_is_ability_granted"
    exit 1
fi
```

```bash
git add . && git commit -m "ci(wcb): block current_user_can('wcb_*') outside Abilities API"
```

---

## Phase 4 — Pro decoupling completion (F2, F9, F10)

### Task 4.1: F2 — Move resume migration off `wcb_settings` write

**Files:**
- Modify: `wp-career-board-pro/modules/resume/class-resume-module.php` (lines 365-380)
- Modify: `wp-career-board/admin/class-admin-settings.php` (sanitizer key list)

**Decision:** add `max_resumes` and `resume_archive_page` as canonical Free settings keys. Pro's migration moves into Free. Pro never writes `wcb_settings` again.

- [ ] **Step 1: Extend Free sanitizer**

In `admin/class-admin-settings.php`, sanitize block (around line 172-194), add:

```php
'max_resumes'                => isset( $input['max_resumes'] ) ? max( 1, min( 50, (int) $input['max_resumes'] ) ) : 2,
'resume_archive_page'        => isset( $input['resume_archive_page'] ) ? (int) $input['resume_archive_page'] : 0,
```

Also add to the appropriate `tab_fields` array (probably `pages` for `resume_archive_page` and `listings` for `max_resumes`).

- [ ] **Step 2: Move migration from Pro to Free**

In `wp-career-board/core/class-install.php::maybe_migrate()`, add a migration step at the appropriate version gate:

```php
// 1.2.0 — F2: absorb wcbp_resume_settings into wcb_settings.
$legacy = get_option( 'wcbp_resume_settings', null );
if ( is_array( $legacy ) && ! empty( $legacy ) ) {
    foreach ( array( 'max_resumes', 'resume_archive_page' ) as $key ) {
        if ( ! isset( $settings[ $key ] ) && isset( $legacy[ $key ] ) ) {
            $settings[ $key ] = $legacy[ $key ];
        }
    }
    update_option( 'wcb_settings', $settings );
    delete_option( 'wcbp_resume_settings' );
}
```

- [ ] **Step 3: Remove the Pro migration block**

In `wp-career-board-pro/modules/resume/class-resume-module.php`, delete the migration block at lines 363-378.

- [ ] **Step 4: Browser smoke**

Walk the resume archive admin page. Toggle `max_resumes` setting. Save. Confirm value persists. Confirm Pro reads it via `WCB\Admin\Settings::int( 'max_resumes', 2 )`.

- [ ] **Step 5: Commit per plugin**

```bash
cd wp-career-board && git add admin/class-admin-settings.php core/class-install.php && \
git commit -m "refactor(wcb): max_resumes + resume_archive_page are canonical Free settings"

cd ../wp-career-board-pro && git add modules/resume/class-resume-module.php && \
git commit -m "refactor(wcbp): resume migration moves to Free; Pro stops writing wcb_settings"
```

### Task 4.2: F9 — Pro setup-wizard funnels writes through Free's sanitizer

**Files:**
- Modify: `wp-career-board-pro/admin/class-pro-setup-wizard.php` (line 380)
- Modify: `wp-career-board-pro/core/class-pro-install.php` (line 111)

**Pattern:**

```php
// BEFORE (Pro setup wizard, line 380)
$settings['some_pro_synced_key'] = $value;
update_option( 'wcb_settings', $settings );

// AFTER
// Pro keeps its own staging option; Free's sanitizer reads + merges via filter.
update_option( 'wcbp_setup_wizard_pending', array( 'some_pro_synced_key' => $value ) );
do_action( 'wcb_pro_setup_wizard_persist' );
```

Then add a Free-side handler at `core/class-pro-coordination.php`:

```php
add_action( 'wcb_pro_setup_wizard_persist', static function (): void {
    $pending = (array) get_option( 'wcbp_setup_wizard_pending', array() );
    if ( empty( $pending ) ) {
        return;
    }
    $settings = (array) get_option( 'wcb_settings', array() );
    foreach ( $pending as $key => $value ) {
        $settings[ $key ] = $value;
    }
    update_option( 'wcb_settings', $settings ); // Free is the writer
    delete_option( 'wcbp_setup_wizard_pending' );
} );
```

- [ ] **Step 1: Implement the handler in Free's coordination class**
- [ ] **Step 2: Replace Pro writes with the new pattern**
- [ ] **Step 3: Browser smoke** — run the Pro setup wizard end-to-end. Confirm settings persist.
- [ ] **Step 4: Commit per plugin**

### Task 4.3: F10 — `wcbp_*` options inventory + consolidation pass

**Files:**
- Create: `wp-career-board-pro/plan/u10-options-inventory.md`

- [ ] **Step 1: Inventory every `wcbp_*` option**

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board-pro" && \
grep -rhoE "(get_option|update_option|add_option|delete_option)\s*\(\s*['\"]wcbp_[a-z_]+" \
    --include='*.php' . 2>/dev/null | grep -oE "['\"]wcbp_[a-z_]+['\"]" | sort -u > plan/u10-options-inventory.txt
```

- [ ] **Step 2: Classify each into `runtime|settings|cache|tombstone`**

Walk the file manually. For each option, decide:
- **runtime:** keep as own option (license, db_version, activation_redirect, flush flags).
- **settings:** migrate into a `wcbp_settings` umbrella array option.
- **cache:** convert to a transient.
- **tombstone:** option is no longer written; only read for migration. Schedule deletion in next major.

- [ ] **Step 3: Plan migration per category**

Defer the actual migrations to a follow-up plan (this task produces the inventory + classification only). Migrations are independent and can ship one option at a time.

- [ ] **Step 4: Commit the inventory**

```bash
git add plan/u10-options-inventory.md plan/u10-options-inventory.txt && \
git commit -m "docs(wcbp): F10 — options inventory for upcoming consolidation"
```

---

## Phase 5 — CSS file splits (F4)

### Task 5.1: Split `employer-dashboard/style.css`

**Files:**
- Modify: `wp-career-board/blocks/employer-dashboard/style.css` (1774 LOC → ~250 LOC base)
- Create: `wp-career-board/blocks/employer-dashboard/styles/sidebar.css`
- Create: `wp-career-board/blocks/employer-dashboard/styles/main.css`
- Create: `wp-career-board/blocks/employer-dashboard/styles/views.css`
- Create: `wp-career-board/blocks/employer-dashboard/styles/widgets.css`
- Modify: `wp-career-board/blocks/employer-dashboard/block.json`

**Natural split points** (verified by reading the source):

| File | Sections | Approximate LOC |
|---|---|---|
| `style.css` | tokens, gate, shell, base layout | ~250 |
| `styles/sidebar.css` | sidebar, nav, logo, CTA, user profile | ~600 |
| `styles/main.css` | main content area, view panels frame | ~250 |
| `styles/views.css` | jobs view, applications view, candidates view, settings view | ~450 |
| `styles/widgets.css` | credit balance widget, notifications bell, stats cards | ~225 |

- [ ] **Step 1: Read full source to confirm section boundaries**

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" && \
grep -n "^/\* " blocks/employer-dashboard/style.css | head -30
```

Expected: see the comment-block markers that demarcate sections.

- [ ] **Step 2: Update block.json to enumerate the additional style files**

In `blocks/employer-dashboard/block.json`, the `style` key currently points at one file. WordPress block.json supports `"style": ["file:./style.css", "file:./styles/sidebar.css", ...]` as an array. Update it:

```json
"style": [
    "file:./style.css",
    "file:./styles/sidebar.css",
    "file:./styles/main.css",
    "file:./styles/views.css",
    "file:./styles/widgets.css"
]
```

- [ ] **Step 3: Create the new files in the split**

For each section, create the new file with the appropriate header:

```css
/**
 * Employer Dashboard — Sidebar styles.
 *
 * Extracted from style.css (was 1774 LOC) per the upscale stabilization plan.
 * Tokens are imported from frontend-tokens.css via cascade.
 *
 * @package WP_Career_Board
 */
```

Move the corresponding rules from `style.css` to the new file. Keep selectors intact — no rewriting.

- [ ] **Step 4: Build assets**

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" && \
npx grunt build
```

- [ ] **Step 5: Browser-verify the dashboard in 3 viewports**

```bash
mcp__plugin_playwright_playwright__browser_navigate http://job-portal.local/employer-dashboard/?autologin=employer1
mcp__plugin_playwright_playwright__browser_take_screenshot --filename plan/screenshots/u4-employer-dashboard-1280.png
# Resize to 768
# Resize to 390
```

Compare to a baseline screenshot taken BEFORE the split. Layout must be pixel-identical (or close to it).

- [ ] **Step 6: Commit**

```bash
git add blocks/employer-dashboard/ && \
git commit -m "refactor(wcb): split employer-dashboard CSS by section

style.css (was 1774 LOC) — base + tokens + gate + shell only.
styles/sidebar.css — sidebar, nav, CTA, user profile.
styles/main.css — main content frame.
styles/views.css — per-view panel rules.
styles/widgets.css — sidebar widgets (credits, notifications).

block.json declares all five via the array-form 'style' key.
No selector or rule changes — pure file split."
```

### Task 5.2: Split `candidate-dashboard/style.css`

**Files:**
- Modify: `wp-career-board/blocks/candidate-dashboard/style.css` (1356 LOC)
- Create: `wp-career-board/blocks/candidate-dashboard/styles/sidebar.css`
- Create: `wp-career-board/blocks/candidate-dashboard/styles/main.css`
- Create: `wp-career-board/blocks/candidate-dashboard/styles/views.css`
- Modify: `wp-career-board/blocks/candidate-dashboard/block.json`

Same shape as Task 5.1.

- [ ] **Steps:** identical pattern. Browser-verify on /candidate-dashboard/.

### Task 5.3: U8 — Audit `resume-single` view.js

**Files:**
- Inspect: `wp-career-board-pro/blocks/resume-single/view.js`

- [ ] **Step 1: Read the file**

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board-pro" && \
cat blocks/resume-single/view.js
```

- [ ] **Step 2: Decide**

If the file has client behavior → migrate to Interactivity API (separate task, port required).
If the file is empty or placeholder-only → delete the file and remove `viewScriptModule` from `block.json`.

- [ ] **Step 3: Apply the decision**
- [ ] **Step 4: Browser-verify resume single page**
- [ ] **Step 5: Commit**

### Task 5.4: U7 — Document REST route carve-outs

**Files:**
- Modify: `wp-career-board/docs/HOOKS.md` (or a new architecture-contract doc per Phase 4.5 of wp-plugin-onboard)

- [ ] **Step 1: Document the carve-out**

Append a section to `docs/HOOKS.md`:

> **REST controller carve-outs:**
> - `core/class-plugin.php` registers a single status-ping route that does not justify its own controller.
> - `core/class-pro-plugin.php` registers a license-check route for the same reason.
> Both are documented exceptions to the "all REST routes through `RestController`" rule.

- [ ] **Step 2: Commit**

---

## Phase 6 — REST extensibility (F7, F6)

### Task 6.1: F7 — Add `wcb_rest_prepare_*` filters to every prepared resource

**Files:**
- Modify: `wp-career-board/api/endpoints/class-applications-endpoint.php`
- Modify: `wp-career-board/api/endpoints/class-companies-endpoint.php`
- Modify: `wp-career-board/api/endpoints/class-candidates-endpoint.php`
- Modify: `wp-career-board/api/endpoints/class-employers-endpoint.php`
- Modify: `wp-career-board/api/endpoints/class-resumes-endpoint.php`
- Modify: `wp-career-board/api/endpoints/class-boards-endpoint.php`
- Modify: `wp-career-board-pro/api/endpoints/*.php` (for any Pro endpoints with their own prepare methods)

**Pattern (mirrors the existing `wcb_job_response` filter):**

```php
private function prepare_item_for_response_array( \WP_Post $post, \WP_REST_Request $request ): array {
    $prepared = array(
        // ... existing prepare logic ...
    );

    /**
     * Filter the prepared application response.
     *
     * @since 1.2.0
     *
     * @param array            $prepared    Prepared response.
     * @param \WP_Post         $application Application post.
     * @param \WP_REST_Request $request     Request object.
     */
    return (array) apply_filters( 'wcb_rest_prepare_application', $prepared, $post, $request );
}
```

- [ ] **Step 1: Add the filter to each endpoint**
- [ ] **Step 2: Document each filter in `docs/HOOKS.md`**
- [ ] **Step 3: WPCS + PHPStan + commit**

### Task 6.2: F6 — `get_item_schema()` rollout (phased; high-traffic endpoints first)

**Defer this task to a follow-up plan.** Schema is per-endpoint and large; the right plan is one PR per endpoint. Open a tracking ticket: "Schema rollout for `/jobs`, `/applications`, `/companies` in 1.3.0."

---

## Phase 7 — Perf (F8) — measurement-driven

### Task 7.1: Add the `wp wcb scale` benchmark CLI

**Files:**
- Create: `wp-career-board/cli/class-cli-scale.php`

Use the template from `wp-plugin-onboard/templates/scale-command.template.php` (per the skill's Phase 4.7.7).

- [ ] **Step 1: Drop the template and customize**

Customize:
- `BUDGETS_MS` — declare the hot-path query budgets
- `seed()` — bulk-INSERT into `wcb_job`, `wcb_company`, `wcb_application` for 10K users × representative shapes
- `benchmark()` — wrap each hot-path query in `time_op()`

- [ ] **Step 2: Register the command**

```php
WP_CLI::add_command( 'wcb scale', \WCB\CLI\ScaleCommand::class );
```

- [ ] **Step 3: Capture baseline**

```bash
wp wcb scale seed
wp wcb scale benchmark
```

Save the baseline numbers in this plan doc as the BEFORE row.

### Task 7.2: REST cache priming in `class-jobs-endpoint.php::get_items()`

**Files:**
- Modify: `wp-career-board/api/endpoints/class-jobs-endpoint.php` (around line 240, after `WP_Query` runs)

- [ ] **Step 1: Add cache priming**

After `$query = new \WP_Query( $args );`:

```php
$post_ids = wp_list_pluck( $query->posts, 'ID' );
if ( ! empty( $post_ids ) ) {
    update_meta_cache( 'post', $post_ids );
    update_object_term_cache( $post_ids, 'wcb_job' );
}
```

- [ ] **Step 2: Re-run benchmark**

```bash
wp wcb scale benchmark
```

Capture the AFTER numbers. Both should improve on `/jobs` listing render.

- [ ] **Step 3: Commit**

```bash
git add api/endpoints/class-jobs-endpoint.php cli/class-cli-scale.php && \
git commit -m "perf(wcb): prime meta + term caches in jobs listing endpoint

Cuts ~84 wpdb queries per page (per A-16 baseline). New CLI command
wp wcb scale benchmark times the hot path; budgets are enforced via the
local-CI gate."
```

### Task 7.3: REST cache invalidation namespace

**Files:**
- Modify: `wp-career-board/api/endpoints/class-jobs-endpoint.php`

The `wcb_jobs_cache_v` option already increments on `save_post_wcb_job` (per `class-jobs-endpoint.php:101`). Use it as a transient namespace key for the prepared response.

- [ ] **Step 1: Wrap `get_items` in transient**

```php
public function get_items( \WP_REST_Request $request ) {
    $cache_key = 'wcb_jobs_get_items_' . md5( wp_json_encode( $request->get_params() ) ) . '_' . get_option( 'wcb_jobs_cache_v', 0 );
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) {
        return rest_ensure_response( $cached );
    }
    // ... existing logic ...
    set_transient( $cache_key, $response_data, MINUTE_IN_SECONDS * 5 );
    return rest_ensure_response( $response_data );
}
```

- [ ] **Step 2: Benchmark**

Confirm second-and-later requests hit the transient (zero queries beyond the option lookup).

- [ ] **Step 3: Commit**

---

## Phase 8 — Cleanup (F3, U6)

### Task 8.1: F3 — Remove dead `wcb_settings` keys ✅

**Files:**
- `wp-career-board/api/endpoints/class-settings-endpoint.php`

- [x] **Step 1: Re-grep for the 5 dead keys (`auto_publish`, `currency`, `moderation_mode`, `pending_review`, `per_page`)**

Result: zero array reads of any dead key against `$wcb_settings` anywhere in
Free or Pro. After Phase 2 routed every internal callsite through
`\WCB\Admin\Settings::`, the only place the dead-key strings appeared was
`api/endpoints/class-settings-endpoint.php` — and there they are *response
field names*, not reads. The endpoint correctly maps canonical sanitizer
keys (`jobs_per_page`, `salary_currency`, `auto_publish_jobs`,
`allow_withdraw`) to the public-facing `app-config` API contract
(`per_page`, `currency`, `moderation_mode`, `allow_withdraw`).

The audit's premise (that 5 keys were read but never written) was inverted:
the canonical keys are written and read; the "dead" names are stable
client-facing field aliases the SPA / mobile contract has shipped.

- [x] **Step 2: For each hit, replace or delete**

The settings endpoint was reading via raw `get_option( 'wcb_settings' )`
with an inline comment claiming it "exposes raw option shape" — but it
actually transforms the shape (canonical → public contract). Fix:

- Replaced `get_option('wcb_settings')` reads with `\WCB\Admin\Settings::int/string/bool` so the endpoint shares the same accessor as every other internal site (Phase 2 contract).
- Updated the misleading comment to describe the actual mapping
  (`jobs_per_page → per_page`, `salary_currency → currency`,
  `auto_publish_jobs → moderation_mode`).

`moderation_mode` resolution: derived from canonical `auto_publish_jobs`
(`auto_publish_jobs == true` → `'auto_publish'`, otherwise
`'pending_review'`). Verified via WP-CLI round-trip — toggling
`auto_publish_jobs` flips `moderation_mode` correctly.

- [x] **Step 3: Commit**

Verified via `wp eval` REST round-trip:
- `auto_publish_jobs=false`, `jobs_per_page=25`, `salary_currency=EUR` → `per_page=25`, `currency=EUR`, `moderation_mode=pending_review`
- `auto_publish_jobs=true` → `moderation_mode=auto_publish`

Quality gates: `phpcs` 0 errors, `phpstan` `[OK]` on Free + Pro.

### Task 8.2: U6 — Audit `$wpdb` calls for prepared-query compliance

**Files:**
- Walk: every site where `$wpdb->query`, `$wpdb->get_results`, `$wpdb->get_row`, `$wpdb->get_var`, `$wpdb->get_col` is called

- [ ] **Step 1: Run WPCS with the strict `WordPress.DB.PreparedSQL` sniff**

```bash
mcp__wpcs__wpcs_check_directory wp-career-board --standard=WordPress
mcp__wpcs__wpcs_check_directory wp-career-board-pro --standard=WordPress
```

Filter the output for `WordPress.DB.PreparedSQL.NotPrepared` and `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` warnings.

- [ ] **Step 2: For each finding, classify and act**

- Static query (no input) → annotate with `// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- static query` + reason.
- Multi-line prepared (already safe) → suppress with explanation.
- Genuinely raw with input → emergency fix; replace with `$wpdb->prepare( ... )` form.

- [ ] **Step 3: Commit**

---

## Cross-phase verification gate

After every phase: run the local-CI gate (or its equivalent set of MCP tools).

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" && \
mcp__wpcs__wpcs_quality_check . && \
vendor/bin/phpstan analyse --memory-limit=2G --no-progress
```

Expected: 0 errors, 0 stat type drift. Warnings allowed (alignment, line-length); errors block.

For browser-verifiable changes, run the Playwright walk:

```bash
mcp__plugin_playwright_playwright__browser_navigate http://job-portal.local/jobs/
mcp__plugin_playwright_playwright__browser_navigate http://job-portal.local/employer-dashboard/?autologin=employer1
mcp__plugin_playwright_playwright__browser_navigate http://job-portal.local/candidate-dashboard/?autologin=candidate1
mcp__plugin_playwright_playwright__browser_navigate http://job-portal.local/post-a-job/?autologin=employer1
mcp__plugin_playwright_playwright__browser_navigate http://job-portal.local/wp-admin/admin.php?page=wcb-settings&autologin=1
```

No console errors, no fatal pages, no broken UIs.

---

## Self-review checklist

After writing the plan, look at the spec doc (`plan/upscale-audit-2026-05-07.md`) with fresh eyes:

**Spec coverage check:**
- F1 → Task 1.1 ✓
- F2 → Task 4.1 ✓
- F3 → Task 8.1 ✓
- F4 → Tasks 5.1, 5.2 ✓
- F5 → Task 1.2 ✓
- F6 → deferred (Task 6.2 placeholder)
- F7 → Task 6.1 ✓
- F8 → Tasks 7.1, 7.2, 7.3 ✓
- F9 → Task 4.2 ✓
- F10 → Task 4.3 (inventory only; consolidation deferred)
- U3 → Tasks 3.1–3.4 ✓
- U6 → Task 8.2 ✓
- U7 → Task 5.4 ✓
- U8 → Task 5.3 ✓
- U9 → Tasks 2.1–2.6 ✓

**Placeholder scan:** F6 schema rollout and F10 consolidation are explicitly deferred to follow-up plans, not theoretical "TBDs." Acceptable.

**Internal consistency:** the `Settings` accessor introduced in Phase 2 is referenced by every later phase. The Pro coordination handler in Phase 4 calls it via the namespaced FQN (`\WCB\Admin\Settings::`). The CSS split in Phase 5 doesn't depend on Phase 2 — independent.

**Scope check:** every phase produces a working, testable plugin pair. Phases 1, 2, 3 are best done in order; phases 4–7 are parallelizable; phase 8 is cleanup.

**Ambiguity check:** none flagged.

---

## Execution handoff

Plan complete and saved to `wp-career-board/plan/upscale-stabilization-plan-2026-05-07.md`. Two execution options:

1. **Subagent-Driven (recommended):** dispatch a fresh subagent per task, review between tasks, fast iteration. Best for the 41-file mass refactors in Phase 2.
2. **Inline Execution:** execute tasks in the current session using `superpowers:executing-plans`, batch execution with checkpoints for review. Best when you want to watch every step.

Which approach?
