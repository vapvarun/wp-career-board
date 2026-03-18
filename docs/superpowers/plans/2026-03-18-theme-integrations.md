# Theme Integrations Implementation Plan (T18/T19)

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete theme integration for BuddyX (free), BuddyX Pro, and Reign — Kirki/Customizer color bridge (maps theme primary color to WCB CSS variables on page load) and BuddyX nav items (parity with Reign's existing nav).

**Architecture:** All three theme integration classes already exist for Reign and BuddyX Pro (templates, compatibility styles, and hooks). BuddyX (free) is missing entirely. The Kirki color bridge adds a `wp_head` hook in each integration to output a `<style>:root{ --wcb-primary: {color}; }</style>` tag derived from `get_theme_mod()` — no Kirki API required, no live-preview JavaScript. BuddyX Pro already has a member badge but is missing nav items — we add them to match Reign's pattern.

**Tech Stack:** PHP 8.1+, `get_theme_mod()`, WordPress Customizer (`customize_register`), `wp_head`

---

## File Structure

```
integrations/
├── buddyx/
│   ├── class-buddyx-integration.php    CREATE — BuddyX (free) full integration
│   ├── assets/
│   │   └── buddyx-compat.css           CREATE — compat styles
│   └── templates/
│       ├── single-wcb_job.php          CREATE — BuddyX-compatible single template
│       └── archive-wcb_job.php         CREATE — BuddyX-compatible archive template
├── buddyx-pro/
│   └── class-buddyx-pro-integration.php  MODIFY — add color bridge + nav items
└── reign/
    └── class-reign-integration.php       MODIFY — output --wcb-primary CSS var on wp_head

core/class-plugin.php    MODIFY — register buddyx (free) integration loader
```

---

## Task 1: Kirki color bridge — Reign

**Files:**
- Modify: `integrations/reign/class-reign-integration.php`

Reign already has a `wcb_reign_primary_color` Customizer control. It just needs to output the value as a CSS variable on `wp_head`.

- [ ] Add to `boot()`:

```php
add_action( 'wp_head', array( $this, 'output_color_bridge' ) );
```

- [ ] Add method:

```php
/**
 * Output WCB CSS variables derived from the Reign Customizer color control.
 *
 * Fires on wp_head — page load only, no live preview.
 *
 * @since 1.0.0
 */
public function output_color_bridge(): void {
    $primary = (string) get_theme_mod( 'wcb_reign_primary_color', '#4f46e5' );
    $primary = sanitize_hex_color( $primary ) ?: '#4f46e5';
    echo '<style id="wcb-reign-colors">:root{--wcb-primary:' . esc_attr( $primary ) . ';}</style>' . "\n";
}
```

- [ ] Commit: `feat(wcb): T18 — Reign color bridge outputs --wcb-primary CSS var`

---

## Task 2: Kirki color bridge + nav items — BuddyX Pro

**Files:**
- Modify: `integrations/buddyx-pro/class-buddyx-pro-integration.php`

BuddyX Pro uses Kirki; its primary color is stored as `buddyx_pro_primary_color` in the Customizer.

- [ ] Add to `boot()`:

```php
add_action( 'wp_head', array( $this, 'output_color_bridge' ) );
add_filter( 'buddyx_pro_nav_items', array( $this, 'add_nav_items' ) );
```

- [ ] Add color bridge method:

```php
/**
 * Output WCB CSS variables derived from BuddyX Pro's Kirki primary color.
 *
 * @since 1.0.0
 */
public function output_color_bridge(): void {
    $primary = (string) get_theme_mod( 'buddyx_pro_primary_color', '#4f46e5' );
    $primary = sanitize_hex_color( $primary ) ?: '#4f46e5';
    echo '<style id="wcb-buddyx-pro-colors">:root{--wcb-primary:' . esc_attr( $primary ) . ';}</style>' . "\n";
}
```

- [ ] Add nav items method (mirrors Reign's `add_nav_items()`):

```php
/**
 * Append WP Career Board links to BuddyX Pro's navigation.
 *
 * @since 1.0.0
 *
 * @param array<int,array<string,string>> $items Existing nav items.
 * @return array<int,array<string,string>>
 */
public function add_nav_items( array $items ): array {
    $settings = (array) get_option( 'wcb_settings', array() );

    $jobs_url = ! empty( $settings['jobs_archive_page'] )
        ? (string) get_permalink( (int) $settings['jobs_archive_page'] )
        : home_url( '/jobs/' );

    $items[] = array(
        'label' => __( 'Browse Jobs', 'wp-career-board' ),
        'url'   => $jobs_url,
        'icon'  => 'dashicons-portfolio',
    );

    $wcb_can_post = function_exists( 'wp_is_ability_granted' )
        ? wp_is_ability_granted( 'wcb_post_jobs' )
        : current_user_can( 'wcb_post_jobs' );

    if ( $wcb_can_post ) {
        $employer_url = ! empty( $settings['employer_dashboard_page'] )
            ? (string) get_permalink( (int) $settings['employer_dashboard_page'] )
            : '#';
        $items[] = array(
            'label' => __( 'Employer Dashboard', 'wp-career-board' ),
            'url'   => $employer_url,
            'icon'  => 'dashicons-building',
        );
    }

    $wcb_can_apply = function_exists( 'wp_is_ability_granted' )
        ? wp_is_ability_granted( 'wcb_apply_jobs' )
        : current_user_can( 'wcb_apply_jobs' );

    if ( $wcb_can_apply ) {
        $candidate_url = ! empty( $settings['candidate_dashboard_page'] )
            ? (string) get_permalink( (int) $settings['candidate_dashboard_page'] )
            : '#';
        $items[] = array(
            'label' => __( 'My Applications', 'wp-career-board' ),
            'url'   => $candidate_url,
            'icon'  => 'dashicons-id-alt',
        );
    }

    return $items;
}
```

- [ ] Commit: `feat(wcb): T19 — BuddyX Pro color bridge + nav items`

---

## Task 3: BuddyX (free) integration — full class

**Files:**
- Create: `integrations/buddyx/class-buddyx-integration.php`
- Create: `integrations/buddyx/assets/buddyx-compat.css`
- Create: `integrations/buddyx/templates/single-wcb_job.php`
- Create: `integrations/buddyx/templates/archive-wcb_job.php`

BuddyX (free) template slug is `buddyx`. Kirki mod key for primary color: `buddyx_primary_color`.

- [ ] Create `integrations/buddyx/class-buddyx-integration.php`:

```php
<?php
/**
 * BuddyX theme integration for WP Career Board.
 *
 * Activated automatically when the active theme slug is 'buddyx'.
 * Provides:
 *  - Template overrides for single and archive wcb_job pages
 *  - #OpenToWork badge on BuddyX member profiles
 *  - Kirki primary color mapped to --wcb-primary CSS variable
 *  - Nav items via buddyx_nav_items filter
 *  - A lightweight compatibility stylesheet for WCB blocks inside BuddyX
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Integrations\Buddyx;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BuddyX (free) theme integration.
 *
 * @since 1.0.0
 */
class BuddyxIntegration {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function boot(): void {
		add_filter( 'single_template',  array( $this, 'single_template' ) );
		add_filter( 'archive_template', array( $this, 'archive_template' ) );
		add_action( 'wp_head',          array( $this, 'output_color_bridge' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'buddyx_after_member_name', array( $this, 'show_job_seeking_badge' ) );
		add_filter( 'buddyx_nav_items', array( $this, 'add_nav_items' ) );
	}

	/**
	 * Return BuddyX-compatible single job template.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template Default template path.
	 * @return string
	 */
	public function single_template( string $template ): string {
		if ( is_singular( 'wcb_job' ) ) {
			$tpl = WCB_DIR . 'integrations/buddyx/templates/single-wcb_job.php';
			if ( file_exists( $tpl ) ) {
				return $tpl;
			}
		}
		return $template;
	}

	/**
	 * Return BuddyX-compatible archive template for wcb_job archives.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template Default template path.
	 * @return string
	 */
	public function archive_template( string $template ): string {
		if ( is_post_type_archive( 'wcb_job' ) ) {
			$tpl = WCB_DIR . 'integrations/buddyx/templates/archive-wcb_job.php';
			if ( file_exists( $tpl ) ) {
				return $tpl;
			}
		}
		return $template;
	}

	/**
	 * Output WCB CSS variables derived from BuddyX's Kirki primary color.
	 *
	 * @since 1.0.0
	 */
	public function output_color_bridge(): void {
		$primary = (string) get_theme_mod( 'buddyx_primary_color', '#4f46e5' );
		$primary = sanitize_hex_color( $primary ) ?: '#4f46e5';
		echo '<style id="wcb-buddyx-colors">:root{--wcb-primary:' . esc_attr( $primary ) . ';}</style>' . "\n";
	}

	/**
	 * Output an #OpenToWork badge on BuddyX member profiles for job-seeking candidates.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The displayed member's user ID.
	 */
	public function show_job_seeking_badge( int $user_id ): void {
		$seeking = get_user_meta( $user_id, '_wcb_open_to_work', true );
		if ( $seeking ) {
			echo '<span class="wcb-open-badge">' . esc_html__( '#OpenToWork', 'wp-career-board' ) . '</span>';
		}
	}

	/**
	 * Append WP Career Board links to BuddyX navigation.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int,array<string,string>> $items Existing nav items.
	 * @return array<int,array<string,string>>
	 */
	public function add_nav_items( array $items ): array {
		$settings = (array) get_option( 'wcb_settings', array() );

		$jobs_url = ! empty( $settings['jobs_archive_page'] )
			? (string) get_permalink( (int) $settings['jobs_archive_page'] )
			: home_url( '/jobs/' );

		$items[] = array(
			'label' => __( 'Browse Jobs', 'wp-career-board' ),
			'url'   => $jobs_url,
			'icon'  => 'dashicons-portfolio',
		);

		$wcb_can_post = function_exists( 'wp_is_ability_granted' )
			? wp_is_ability_granted( 'wcb_post_jobs' )
			: current_user_can( 'wcb_post_jobs' );

		if ( $wcb_can_post ) {
			$employer_url = ! empty( $settings['employer_dashboard_page'] )
				? (string) get_permalink( (int) $settings['employer_dashboard_page'] )
				: '#';
			$items[] = array(
				'label' => __( 'Employer Dashboard', 'wp-career-board' ),
				'url'   => $employer_url,
				'icon'  => 'dashicons-building',
			);
		}

		$wcb_can_apply = function_exists( 'wp_is_ability_granted' )
			? wp_is_ability_granted( 'wcb_apply_jobs' )
			: current_user_can( 'wcb_apply_jobs' );

		if ( $wcb_can_apply ) {
			$candidate_url = ! empty( $settings['candidate_dashboard_page'] )
				? (string) get_permalink( (int) $settings['candidate_dashboard_page'] )
				: '#';
			$items[] = array(
				'label' => __( 'My Applications', 'wp-career-board' ),
				'url'   => $candidate_url,
				'icon'  => 'dashicons-id-alt',
			);
		}

		return $items;
	}

	/**
	 * Enqueue BuddyX compatibility stylesheet on WCB job pages.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles(): void {
		$wcb_is_tax = is_tax( array( 'wcb_category', 'wcb_job_type', 'wcb_tag', 'wcb_location', 'wcb_experience' ) );
		if ( ! is_singular( 'wcb_job' ) && ! is_post_type_archive( 'wcb_job' ) && ! $wcb_is_tax ) {
			return;
		}
		wp_enqueue_style(
			'wcb-buddyx-compat',
			WCB_URL . 'integrations/buddyx/assets/buddyx-compat.css',
			array(),
			WCB_VERSION
		);
	}
}
```

- [ ] Create `integrations/buddyx/assets/buddyx-compat.css` (mirrors buddyx-pro version):

```css
/**
 * WP Career Board — BuddyX theme compatibility styles.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

.wcb-job-single,
.wcb-job-listings {
	max-width: var( --buddyx-content-width, 100% );
	margin-inline: auto;
}

.wcb-open-badge {
	display: inline-flex;
	align-items: center;
	gap: 0.25rem;
	padding: 0.2em 0.6em;
	font-size: 0.75rem;
	font-weight: 600;
	color: #fff;
	background-color: var( --wcb-primary, #4f46e5 );
	border-radius: 9999px;
	vertical-align: middle;
}
```

- [ ] Create `integrations/buddyx/templates/single-wcb_job.php` — copy from `integrations/buddyx-pro/templates/single-wcb_job.php` and adjust the template wrapper class from `buddyx-pro` to `buddyx` if needed.

- [ ] Create `integrations/buddyx/templates/archive-wcb_job.php` — same approach.

- [ ] Commit: `feat(wcb): T18 — BuddyX (free) integration — color bridge, nav items, badge, templates`

---

## Task 4: Register BuddyX (free) in plugin loader

**Files:**
- Modify: `core/class-plugin.php`

- [ ] In `load_integrations()`, add BuddyX (free) loader alongside BuddyX Pro:

```php
// Inside the after_setup_theme closure, add:
if ( 'buddyx' === $theme && class_exists( \WCB\Integrations\Buddyx\BuddyxIntegration::class ) ) {
    ( new \WCB\Integrations\Buddyx\BuddyxIntegration() )->boot();
}
```

- [ ] Commit: `feat(wcb): T18 — register BuddyX (free) integration in plugin loader`

---

## Verification

**Reign:**
1. Activate Reign theme → set a custom hex color in Customizer → WP Career Board section → Primary Color
2. View any job page — inspect `<head>`, verify `<style id="wcb-reign-colors">:root{--wcb-primary:#yourcolor;}</style>` is present
3. WCB buttons and active states use the custom color

**BuddyX Pro:**
1. Activate BuddyX Pro → set primary color in Customizer (Kirki control)
2. View a job page — verify `<style id="wcb-buddyx-pro-colors">` outputs the correct hex
3. Check BuddyX Pro nav — Browse Jobs / Employer Dashboard / My Applications appear for appropriate user roles

**BuddyX (free):**
1. Activate BuddyX → set primary color in Customizer
2. View a job page — verify `<style id="wcb-buddyx-colors">` outputs correct hex
3. Job single and archive pages use `integrations/buddyx/templates/`
4. Candidate with `_wcb_open_to_work = 1` shows badge on BuddyX member profile
5. Nav items appear for correct roles
