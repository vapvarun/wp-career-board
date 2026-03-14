# WP Career Board Free — Core Alpha Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a genuinely feature-complete Free version of WP Career Board — employer posts jobs, candidate applies, admin manages, emails fire, search works — end-to-end on a single board with no credits required.

**Architecture:** API-First Modular Monolith. Modules expose REST endpoints consumed by Interactivity API blocks. Abilities API governs all permissions. BuddyPress/Reign/BuddyX Pro are optional integrations activated on detection.

**Tech Stack:** PHP 8.1+, WP 6.9+, WordPress Interactivity API, WordPress Abilities API, Gutenberg blocks, REST API v1 (`/wp-json/wcb/v1/`), wp_mail, WP-Cron

---

## File Structure

```
wp-career-board/
├── wp-career-board.php                   # Bootstrap: constants, autoload, init
├── uninstall.php                         # Clean DB tables + options on delete
├── core/
│   ├── class-plugin.php                  # Singleton. Loads modules in order.
│   ├── class-install.php                 # Creates DB tables, registers roles
│   ├── class-roles.php                   # Registers wcb_employer, wcb_candidate, wcb_board_moderator
│   └── class-abilities.php               # wp_register_ability() calls
├── modules/
│   ├── jobs/
│   │   ├── class-jobs-module.php         # Registers wcb_job CPT + taxonomies
│   │   ├── class-jobs-meta.php           # Postmeta helpers
│   │   └── class-jobs-expiry.php         # WP-Cron auto-expiry
│   ├── employers/
│   │   ├── class-employers-module.php    # Registers wcb_company CPT
│   │   └── class-employers-meta.php      # Trust level, domain verification helpers
│   ├── candidates/
│   │   ├── class-candidates-module.php   # Registers wcb_resume CPT
│   │   └── class-candidates-meta.php     # Profile visibility, bookmarks helpers
│   ├── applications/
│   │   ├── class-applications-module.php # Registers wcb_application CPT
│   │   └── class-applications-meta.php   # Status, stage, custom fields helpers
│   ├── search/
│   │   └── class-search-module.php       # WP_Query builder + URL param sync
│   ├── notifications/
│   │   └── class-notifications-email.php # wp_mail driver for all events
│   ├── moderation/
│   │   └── class-moderation-module.php   # Approval queue logic
│   ├── seo/
│   │   └── class-seo-module.php          # JobPosting schema, OG tags, meta
│   └── gdpr/
│       └── class-gdpr-module.php         # WP privacy API: export + erase
├── api/
│   ├── class-rest-controller.php         # Base controller: auth + ability checks
│   └── endpoints/
│       ├── class-jobs-endpoint.php       # /wcb/v1/jobs
│       ├── class-applications-endpoint.php # /wcb/v1/jobs/{id}/apply, /applications
│       ├── class-candidates-endpoint.php # /wcb/v1/candidates
│       ├── class-employers-endpoint.php  # /wcb/v1/employers
│       └── class-search-endpoint.php     # /wcb/v1/search
├── blocks/
│   ├── job-listings/                     # wcb/job-listings
│   │   ├── block.json
│   │   ├── render.php
│   │   ├── view.js
│   │   └── style.css
│   ├── job-search/                       # wcb/job-search
│   │   ├── block.json
│   │   ├── render.php
│   │   ├── view.js
│   │   └── style.css
│   ├── job-filters/                      # wcb/job-filters
│   │   ├── block.json
│   │   ├── render.php
│   │   ├── view.js
│   │   └── style.css
│   ├── job-single/                       # wcb/job-single
│   │   ├── block.json
│   │   ├── render.php
│   │   ├── view.js
│   │   └── style.css
│   ├── job-form/                         # wcb/job-form (post a job)
│   │   ├── block.json
│   │   ├── render.php
│   │   ├── view.js
│   │   └── style.css
│   ├── employer-dashboard/               # wcb/employer-dashboard
│   │   ├── block.json
│   │   ├── render.php
│   │   ├── view.js
│   │   └── style.css
│   ├── candidate-dashboard/              # wcb/candidate-dashboard
│   │   ├── block.json
│   │   ├── render.php
│   │   ├── view.js
│   │   └── style.css
│   ├── company-profile/                  # wcb/company-profile
│   │   ├── block.json
│   │   ├── render.php
│   │   ├── view.js
│   │   └── style.css
│   └── featured-jobs/                    # wcb/featured-jobs
│       ├── block.json
│       ├── render.php
│       └── style.css
├── integrations/
│   ├── buddypress/
│   │   └── class-bp-integration.php
│   ├── reign/
│   │   ├── class-reign-integration.php
│   │   └── templates/
│   └── buddyx-pro/
│       ├── class-buddyx-pro-integration.php
│       └── templates/
├── admin/
│   ├── class-admin.php                   # Top-level menu + submenus
│   ├── class-admin-jobs.php              # Jobs list table + meta box
│   ├── class-admin-employers.php         # Employers list
│   ├── class-admin-applications.php      # Applications list + status change
│   ├── class-admin-settings.php          # General settings page
│   └── class-setup-wizard.php            # Onboarding wizard
├── assets/
│   ├── css/
│   │   ├── frontend.css                  # Base public-facing styles
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── languages/
│   └── wp-career-board.pot
└── docs/
    ├── DESIGN-SPEC.md
    ├── PLAN.md                            ← this file
    └── CHANGELOG.md
```

---

## Chunk 1: Foundation

### Task 1: Plugin Main File + Autoloader

**Files:**
- Create: `wp-career-board.php`
- Create: `core/class-plugin.php`
- Create: `uninstall.php`

- [ ] **Step 1: Create the main plugin file**

```php
<?php
/**
 * Plugin Name: WP Career Board
 * Plugin URI:  https://wpcareerboard.com
 * Description: The community-powered job board for WordPress.
 * Version:     0.1.0
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * Author:      Wbcom Designs
 * Author URI:  https://wbcomdesigns.com
 * License:     GPL-2.0-or-later
 * Text Domain: wp-career-board
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'WCB_VERSION',   '0.1.0' );
define( 'WCB_FILE',      __FILE__ );
define( 'WCB_DIR',       plugin_dir_path( __FILE__ ) );
define( 'WCB_URL',       plugin_dir_url( __FILE__ ) );
define( 'WCB_BASENAME',  plugin_basename( __FILE__ ) );

// Autoloader: maps WCB\ namespace to /core, /modules, /api, /integrations, /admin
spl_autoload_register( function ( string $class ) {
    if ( 0 !== strpos( $class, 'WCB\\' ) ) {
        return;
    }
    $relative = str_replace( [ 'WCB\\', '\\' ], [ '', '/' ], $class );
    $parts     = explode( '/', $relative );
    // Convert CamelCase class name to kebab-case filename
    $class_name = array_pop( $parts );
    $filename   = 'class-' . strtolower( preg_replace( '/([A-Z])/', '-$1', lcfirst( $class_name ) ) ) . '.php';
    $file       = WCB_DIR . implode( '/', array_map( 'strtolower', $parts ) ) . '/' . $filename;
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

register_activation_hook( WCB_FILE, [ 'WCB\\Core\\Install', 'activate' ] );
register_deactivation_hook( WCB_FILE, [ 'WCB\\Core\\Install', 'deactivate' ] );

add_action( 'plugins_loaded', [ 'WCB\\Core\\Plugin', 'instance' ] );
```

- [ ] **Step 2: Create `core/class-plugin.php`**

```php
<?php
namespace WCB\Core;

final class Plugin {
    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    private function init(): void {
        load_plugin_textdomain( 'wp-career-board', false, WCB_BASENAME . '/languages' );

        // Core: roles + abilities registered early (init priority 5)
        add_action( 'init', [ new Roles(), 'register' ], 5 );
        add_action( 'init', [ new Abilities(), 'register' ], 5 );

        // Modules
        $modules = [
            new \WCB\Modules\Jobs\JobsModule(),
            new \WCB\Modules\Employers\EmployersModule(),
            new \WCB\Modules\Candidates\CandidatesModule(),
            new \WCB\Modules\Applications\ApplicationsModule(),
            new \WCB\Modules\Search\SearchModule(),
            new \WCB\Modules\Notifications\NotificationsEmail(),
            new \WCB\Modules\Moderation\ModerationModule(),
            new \WCB\Modules\Seo\SeoModule(),
            new \WCB\Modules\Gdpr\GdprModule(),
        ];
        foreach ( $modules as $module ) {
            $module->boot();
        }

        // REST API
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        // Blocks
        add_action( 'init', [ $this, 'register_blocks' ] );

        // Admin
        if ( is_admin() ) {
            ( new \WCB\Admin\Admin() )->boot();
        }

        // Integrations (auto-detected)
        $this->load_integrations();
    }

    public function register_rest_routes(): void {
        $endpoints = [
            new \WCB\Api\Endpoints\JobsEndpoint(),
            new \WCB\Api\Endpoints\ApplicationsEndpoint(),
            new \WCB\Api\Endpoints\CandidatesEndpoint(),
            new \WCB\Api\Endpoints\EmployersEndpoint(),
            new \WCB\Api\Endpoints\SearchEndpoint(),
        ];
        foreach ( $endpoints as $endpoint ) {
            $endpoint->register_routes();
        }
    }

    public function register_blocks(): void {
        $blocks = [
            'job-listings', 'job-search', 'job-filters', 'job-single',
            'job-form', 'employer-dashboard', 'candidate-dashboard',
            'company-profile', 'featured-jobs',
        ];
        foreach ( $blocks as $block ) {
            register_block_type( WCB_DIR . 'blocks/' . $block );
        }
    }

    private function load_integrations(): void {
        if ( function_exists( 'buddypress' ) ) {
            ( new \WCB\Integrations\Buddypress\BpIntegration() )->boot();
        }
        add_action( 'after_setup_theme', function () {
            $theme = wp_get_theme()->get_template();
            if ( 'reign-theme' === $theme ) {
                ( new \WCB\Integrations\Reign\ReignIntegration() )->boot();
            }
            if ( 'buddyx-pro' === $theme ) {
                ( new \WCB\Integrations\BuddyxPro\BuddyxProIntegration() )->boot();
            }
        } );
    }
}
```

- [ ] **Step 3: Create `uninstall.php`**

```php
<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$tables = [
    $wpdb->prefix . 'wcb_notifications_log',
    $wpdb->prefix . 'wcb_job_views',
    $wpdb->prefix . 'wcb_gdpr_log',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore
}

delete_option( 'wcb_version' );
delete_option( 'wcb_settings' );
delete_option( 'wcb_db_version' );

// Remove roles
remove_role( 'wcb_employer' );
remove_role( 'wcb_candidate' );
remove_role( 'wcb_board_moderator' );
```

- [ ] **Step 4: Activate on local site via WP-CLI**

```bash
wp plugin activate wp-career-board --path="/Users/varundubey/Local Sites/job-portal/app/public"
```
Expected: `Plugin 'wp-career-board' activated.`

- [ ] **Step 5: Verify no PHP fatal errors**

Check debug log at `wp-content/debug.log`. Expected: empty or only unrelated notices.

- [ ] **Step 6: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" add -A
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" commit -m "feat: plugin bootstrap, autoloader, main plugin file"
```

---

### Task 2: DB Install + User Roles + Abilities API

**Files:**
- Create: `core/class-install.php`
- Create: `core/class-roles.php`
- Create: `core/class-abilities.php`

- [ ] **Step 1: Create `core/class-install.php`**

```php
<?php
namespace WCB\Core;

class Install {
    const DB_VERSION = '1.0';

    public static function activate(): void {
        self::create_tables();
        self::maybe_upgrade();
        ( new Roles() )->register();
        flush_rewrite_rules();
        update_option( 'wcb_db_version', self::DB_VERSION );
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    private static function check_requirements(): void {
        global $wp_version;
        if ( version_compare( PHP_VERSION, '8.1', '<' ) || version_compare( $wp_version, '6.9', '<' ) ) {
            deactivate_plugins( WCB_BASENAME );
            wp_die( esc_html__( 'WP Career Board requires PHP 8.1+ and WordPress 6.9+.', 'wp-career-board' ) );
        }
    }

    public static function activate(): void {
        self::check_requirements(); // ← added
        // ... rest unchanged

    private static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( "CREATE TABLE {$wpdb->prefix}wcb_notifications_log (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id      BIGINT UNSIGNED NOT NULL,
            event_type   VARCHAR(80)     NOT NULL,
            channel      VARCHAR(20)     NOT NULL DEFAULT 'email',
            payload      LONGTEXT,
            status       VARCHAR(20)     NOT NULL DEFAULT 'sent',
            sent_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id  (user_id),
            KEY event_type (event_type)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}wcb_job_views (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id     BIGINT UNSIGNED NOT NULL,
            viewed_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_hash    VARCHAR(64),
            PRIMARY KEY (id),
            KEY job_id  (job_id)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}wcb_gdpr_log (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id    BIGINT UNSIGNED NOT NULL,
            action     VARCHAR(20)     NOT NULL,
            metadata   LONGTEXT,
            ip_hash    VARCHAR(64),
            created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset;" );
    }

    private static function maybe_upgrade(): void {
        $installed = get_option( 'wcb_db_version', '0' );
        if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
            self::create_tables();
            update_option( 'wcb_db_version', self::DB_VERSION );
        }
    }
}
```

- [ ] **Step 2: Create `core/class-roles.php`**

```php
<?php
namespace WCB\Core;

class Roles {
    public function register(): void {
        // Employer
        if ( ! get_role( 'wcb_employer' ) ) {
            add_role( 'wcb_employer', __( 'Employer', 'wp-career-board' ), [
                'read'                         => true,
                'wcb_post_jobs'                => true,
                'wcb_manage_company'           => true,
                'wcb_view_applications'        => true,
                'wcb_access_employer_dashboard' => true,
            ] );
        }

        // Candidate
        if ( ! get_role( 'wcb_candidate' ) ) {
            add_role( 'wcb_candidate', __( 'Candidate', 'wp-career-board' ), [
                'read'                    => true,
                'wcb_apply_jobs'          => true,
                'wcb_manage_resume'       => true,
                'wcb_bookmark_jobs'       => true,
            ] );
        }

        // Board Moderator
        if ( ! get_role( 'wcb_board_moderator' ) ) {
            add_role( 'wcb_board_moderator', __( 'Board Moderator', 'wp-career-board' ), [
                'read'                    => true,
                'wcb_moderate_jobs'       => true,
            ] );
        }

        // Give administrator all WCB caps
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( [
                'wcb_post_jobs', 'wcb_manage_company', 'wcb_view_applications',
                'wcb_apply_jobs', 'wcb_manage_resume', 'wcb_bookmark_jobs',
                'wcb_moderate_jobs', 'wcb_manage_settings', 'wcb_view_analytics',
            ] as $cap ) {
                $admin->add_cap( $cap );
            }
        }
    }
}
```

- [ ] **Step 3: Create `core/class-abilities.php`**

```php
<?php
namespace WCB\Core;

class Abilities {
    public function register(): void {
        if ( ! function_exists( 'wp_register_ability' ) ) {
            return; // Abilities API not available — graceful degradation
        }

        wp_register_ability_category( 'wcb', [
            'label' => __( 'WP Career Board', 'wp-career-board' ),
        ] );

        wp_register_ability( 'wcb_post_jobs', [
            'category' => 'wcb',
            'label'    => __( 'Post Jobs', 'wp-career-board' ),
            'callback' => fn( $user ) => $user && ( $user->has_cap( 'wcb_post_jobs' ) || $user->has_cap( 'manage_options' ) ),
        ] );

        wp_register_ability( 'wcb_manage_company', [
            'category' => 'wcb',
            'label'    => __( 'Manage Company Profile', 'wp-career-board' ),
            'callback' => fn( $user ) => $user && ( $user->has_cap( 'wcb_manage_company' ) || $user->has_cap( 'manage_options' ) ),
        ] );

        wp_register_ability( 'wcb_view_applications', [
            'category' => 'wcb',
            'label'    => __( 'View Applications', 'wp-career-board' ),
            'callback' => fn( $user ) => $user && ( $user->has_cap( 'wcb_view_applications' ) || $user->has_cap( 'manage_options' ) ),
        ] );

        wp_register_ability( 'wcb_apply_jobs', [
            'category' => 'wcb',
            'label'    => __( 'Apply to Jobs', 'wp-career-board' ),
            'callback' => fn( $user ) => $user && ( $user->has_cap( 'wcb_apply_jobs' ) || $user->has_cap( 'manage_options' ) ),
        ] );

        wp_register_ability( 'wcb_moderate_jobs', [
            'category' => 'wcb',
            'label'    => __( 'Moderate Jobs', 'wp-career-board' ),
            'callback' => fn( $user ) => $user && ( $user->has_cap( 'wcb_moderate_jobs' ) || $user->has_cap( 'manage_options' ) ),
        ] );

        wp_register_ability( 'wcb_manage_settings', [
            'category' => 'wcb',
            'label'    => __( 'Manage Settings', 'wp-career-board' ),
            'callback' => fn( $user ) => $user && $user->has_cap( 'manage_options' ),
        ] );

        wp_register_ability( 'wcb_access_employer_dashboard', [
            'category' => 'wcb',
            'label'    => __( 'Access Employer Dashboard', 'wp-career-board' ),
            'callback' => fn( $user ) => $user && ( $user->has_cap( 'wcb_access_employer_dashboard' ) || $user->has_cap( 'manage_options' ) ),
        ] );

        // Board moderator: scoped to assigned boards only.
        // _wcb_assigned_boards usermeta holds array of board post IDs.
        // Context-aware: when $args['board_id'] is provided, checks assignment.
        wp_register_ability( 'wcb_moderate_jobs', [
            'category' => 'wcb',
            'label'    => __( 'Moderate Jobs', 'wp-career-board' ),
            'callback' => function ( $user, array $args = [] ) {
                if ( ! $user ) {
                    return false;
                }
                if ( $user->has_cap( 'manage_options' ) ) {
                    return true;
                }
                if ( ! $user->has_cap( 'wcb_moderate_jobs' ) ) {
                    return false;
                }
                // If a board_id context is provided, check assignment.
                if ( ! empty( $args['board_id'] ) ) {
                    $assigned = (array) get_user_meta( $user->ID, '_wcb_assigned_boards', true );
                    return in_array( (int) $args['board_id'], array_map( 'intval', $assigned ), true );
                }
                return true; // No board context = allow (list view)
            },
        ] );
    }
}
```

- [ ] **Step 4: Re-activate plugin to run `Install::activate()`**

```bash
wp plugin deactivate wp-career-board && wp plugin activate wp-career-board \
  --path="/Users/varundubey/Local Sites/job-portal/app/public"
```

- [ ] **Step 5: Verify roles exist**

```bash
wp role list --path="/Users/varundubey/Local Sites/job-portal/app/public" | grep wcb
```
Expected: `wcb_employer`, `wcb_candidate`, `wcb_board_moderator` in output.

- [ ] **Step 6: Verify DB tables exist**

```bash
wp db query "SHOW TABLES LIKE 'wp_wcb%'" \
  --path="/Users/varundubey/Local Sites/job-portal/app/public"
```
Expected: 3 tables listed.

- [ ] **Step 7: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add core/ && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "feat: DB install, user roles, Abilities API registration"
```

---

### Task 3: CPTs + Taxonomies

**Files:**
- Create: `modules/jobs/class-jobs-module.php`
- Create: `modules/employers/class-employers-module.php`
- Create: `modules/candidates/class-candidates-module.php`
- Create: `modules/applications/class-applications-module.php`

- [ ] **Step 1: Create `modules/jobs/class-jobs-module.php`**

```php
<?php
namespace WCB\Modules\Jobs;

class JobsModule {
    public function boot(): void {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'init', [ $this, 'register_taxonomies' ] );
    }

    public function register_post_type(): void {
        register_post_type( 'wcb_job', [
            'labels'              => [
                'name'          => __( 'Jobs', 'wp-career-board' ),
                'singular_name' => __( 'Job', 'wp-career-board' ),
                'add_new_item'  => __( 'Add New Job', 'wp-career-board' ),
            ],
            'public'              => true,
            'show_in_rest'        => true,
            'show_in_menu'        => false, // shown under WCB menu
            'supports'            => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
            'rewrite'             => [ 'slug' => 'jobs', 'with_front' => false ],
            'has_archive'         => 'jobs',
            'menu_icon'           => 'dashicons-portfolio',
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        ] );
    }

    public function register_taxonomies(): void {
        // Job Category (hierarchical)
        register_taxonomy( 'wcb_category', 'wcb_job', [
            'label'             => __( 'Job Categories', 'wp-career-board' ),
            'hierarchical'      => true,
            'show_in_rest'      => true,
            'rewrite'           => [ 'slug' => 'job-category' ],
            'show_admin_column' => true,
        ] );

        // Job Type (flat)
        register_taxonomy( 'wcb_job_type', 'wcb_job', [
            'label'             => __( 'Job Types', 'wp-career-board' ),
            'hierarchical'      => false,
            'show_in_rest'      => true,
            'rewrite'           => [ 'slug' => 'job-type' ],
            'show_admin_column' => true,
        ] );

        // Job Tag (flat)
        register_taxonomy( 'wcb_tag', 'wcb_job', [
            'label'        => __( 'Job Tags', 'wp-career-board' ),
            'hierarchical' => false,
            'show_in_rest' => true,
            'rewrite'      => [ 'slug' => 'job-tag' ],
        ] );

        // Job Location (hierarchical: Country → State → City)
        register_taxonomy( 'wcb_location', 'wcb_job', [
            'label'             => __( 'Locations', 'wp-career-board' ),
            'hierarchical'      => true,
            'show_in_rest'      => true,
            'rewrite'           => [ 'slug' => 'job-location' ],
            'show_admin_column' => true,
        ] );

        // Experience Level (flat)
        register_taxonomy( 'wcb_experience', 'wcb_job', [
            'label'        => __( 'Experience Levels', 'wp-career-board' ),
            'hierarchical' => false,
            'show_in_rest' => true,
            'rewrite'      => [ 'slug' => 'job-experience' ],
        ] );
    }
}
```

- [ ] **Step 2: Create `modules/employers/class-employers-module.php`**

```php
<?php
namespace WCB\Modules\Employers;

class EmployersModule {
    public function boot(): void {
        add_action( 'init', [ $this, 'register_post_type' ] );
    }

    public function register_post_type(): void {
        register_post_type( 'wcb_company', [
            'labels'          => [
                'name'          => __( 'Companies', 'wp-career-board' ),
                'singular_name' => __( 'Company', 'wp-career-board' ),
            ],
            'public'          => true,
            'show_in_rest'    => true,
            'show_in_menu'    => false,
            'supports'        => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
            'rewrite'         => [ 'slug' => 'companies', 'with_front' => false ],
            'has_archive'     => 'companies',
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ] );
    }
}
```

- [ ] **Step 3: Create `modules/candidates/class-candidates-module.php`**

```php
<?php
namespace WCB\Modules\Candidates;

class CandidatesModule {
    public function boot(): void {
        add_action( 'init', [ $this, 'register_post_type' ] );
    }

    public function register_post_type(): void {
        register_post_type( 'wcb_resume', [
            'labels'          => [
                'name'          => __( 'Resumes', 'wp-career-board' ),
                'singular_name' => __( 'Resume', 'wp-career-board' ),
            ],
            'public'          => false,
            'show_ui'         => true,
            'show_in_rest'    => true,
            'show_in_menu'    => false,
            'supports'        => [ 'title', 'custom-fields' ],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ] );
    }
}
```

- [ ] **Step 4: Create `modules/applications/class-applications-module.php`**

```php
<?php
namespace WCB\Modules\Applications;

class ApplicationsModule {
    public function boot(): void {
        add_action( 'init', [ $this, 'register_post_type' ] );
    }

    public function register_post_type(): void {
        register_post_type( 'wcb_application', [
            'labels'          => [
                'name'          => __( 'Applications', 'wp-career-board' ),
                'singular_name' => __( 'Application', 'wp-career-board' ),
            ],
            'public'          => false,
            'show_ui'         => true,
            'show_in_rest'    => true,
            'show_in_menu'    => false,
            'supports'        => [ 'title', 'custom-fields' ],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ] );
    }
}
```

- [ ] **Step 4b: Create `modules/boards/class-boards-module.php`**

`wcb_board` is the structural backbone every module uses for `board_id` foreign keys. The multi-board engine UI is Pro, but the CPT must exist in Free.

```php
<?php
namespace WCB\Modules\Boards;

class BoardsModule {
    public function boot(): void {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'init', [ $this, 'ensure_default_board' ], 20 );
    }

    public function register_post_type(): void {
        register_post_type( 'wcb_board', [
            'labels'          => [
                'name'          => __( 'Job Boards', 'wp-career-board' ),
                'singular_name' => __( 'Job Board', 'wp-career-board' ),
            ],
            'public'          => false,
            'show_ui'         => true,
            'show_in_rest'    => true,
            'show_in_menu'    => false,
            'supports'        => [ 'title', 'custom-fields' ],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ] );
    }

    /**
     * Create a "Default Board" on first run so every job has a board_id.
     * Free plugin always has exactly one board.
     */
    public function ensure_default_board(): void {
        if ( get_option( 'wcb_default_board_id' ) ) {
            return;
        }
        $board_id = wp_insert_post( [
            'post_type'   => 'wcb_board',
            'post_title'  => __( 'Main Board', 'wp-career-board' ),
            'post_status' => 'publish',
        ] );
        if ( $board_id && ! is_wp_error( $board_id ) ) {
            update_option( 'wcb_default_board_id', $board_id );
        }
    }

    public static function get_default_board_id(): int {
        return (int) get_option( 'wcb_default_board_id', 0 );
    }
}
```

Also add `BoardsModule` to the module list in `core/class-plugin.php`:
```php
new \WCB\Modules\Boards\BoardsModule(),
```

- [ ] **Step 5: Verify CPTs + taxonomies are registered**

```bash
wp post-type list --path="/Users/varundubey/Local Sites/job-portal/app/public" | grep wcb
wp taxonomy list --path="/Users/varundubey/Local Sites/job-portal/app/public" | grep wcb
```
Expected: `wcb_job`, `wcb_company`, `wcb_resume`, `wcb_application` and all 5 taxonomies.

- [ ] **Step 6: Verify `/jobs` archive is accessible**

Navigate to `http://job-portal.local/jobs/` — should show empty archive (not 404).

- [ ] **Step 7: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add modules/ && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "feat: register CPTs (wcb_job, wcb_company, wcb_resume, wcb_application) + taxonomies"
```

---
## Chunk 2: REST API Foundation + Core Modules

### Task 4: REST API Base Controller

**Files:**
- Create: `api/class-rest-controller.php`

- [ ] **Step 1: Create base controller**

```php
<?php
namespace WCB\Api;

abstract class RestController extends \WP_REST_Controller {
    protected string $namespace = 'wcb/v1';

    /**
     * Check ability via Abilities API with fallback to capability check.
     */
    protected function check_ability( string $ability, array $args = [] ): bool {
        if ( function_exists( 'wp_is_ability_granted' ) ) {
            return wp_is_ability_granted( $ability, wp_get_current_user(), $args );
        }
        // Fallback: map ability to cap
        return current_user_can( $ability );
    }

    protected function current_user_id(): int {
        return get_current_user_id();
    }

    /**
     * Standard permission denied response.
     */
    protected function permission_error(): \WP_Error {
        return new \WP_Error(
            'wcb_forbidden',
            __( 'You do not have permission to perform this action.', 'wp-career-board' ),
            [ 'status' => 403 ]
        );
    }

    /**
     * Increment job view count.
     */
    protected function record_job_view( int $job_id ): void {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'wcb_job_views',
            [
                'job_id'    => $job_id,
                'viewed_at' => current_time( 'mysql' ),
                'ip_hash'   => hash( 'sha256', $_SERVER['REMOTE_ADDR'] ?? '' ),
            ],
            [ '%d', '%s', '%s' ]
        );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add api/ && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "feat: REST base controller with Abilities API integration"
```

---

### Task 5: Jobs REST Endpoint

**Files:**
- Create: `api/endpoints/class-jobs-endpoint.php`
- Create: `modules/jobs/class-jobs-meta.php`
- Create: `modules/jobs/class-jobs-expiry.php`

- [ ] **Step 1: Create `api/endpoints/class-jobs-endpoint.php`**

```php
<?php
namespace WCB\Api\Endpoints;

use WCB\Api\RestController;
use WCB\Modules\Boards\BoardsModule;

class JobsEndpoint extends RestController {

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/jobs', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_items' ],
                'permission_callback' => '__return_true', // Public
                'args'                => $this->get_collection_params(),
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_item' ],
                'permission_callback' => [ $this, 'create_item_permissions_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/jobs/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_item' ],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_item' ],
                'permission_callback' => [ $this, 'update_item_permissions_check' ],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_item' ],
                'permission_callback' => [ $this, 'delete_item_permissions_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/jobs/(?P<id>\d+)/bookmark', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'toggle_bookmark' ],
            'permission_callback' => fn() => is_user_logged_in(),
        ] );

        register_rest_route( $this->namespace, '/jobs/(?P<id>\d+)/applications', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_applications' ],
            'permission_callback' => [ $this, 'view_applications_permissions_check' ],
        ] );
    }

    public function get_items( \WP_REST_Request $request ): \WP_REST_Response {
        $args = [
            'post_type'      => 'wcb_job',
            'post_status'    => 'publish',
            'posts_per_page' => (int) $request->get_param( 'per_page' ) ?: 20,
            'paged'          => (int) $request->get_param( 'page' ) ?: 1,
            'tax_query'      => [],
            'meta_query'     => [],
        ];

        if ( $s = $request->get_param( 'search' ) ) {
            $args['s'] = sanitize_text_field( $s );
        }
        if ( $cat = $request->get_param( 'category' ) ) {
            $args['tax_query'][] = [ 'taxonomy' => 'wcb_category', 'terms' => explode( ',', $cat ), 'field' => 'slug' ];
        }
        if ( $type = $request->get_param( 'type' ) ) {
            $args['tax_query'][] = [ 'taxonomy' => 'wcb_job_type', 'terms' => explode( ',', $type ), 'field' => 'slug' ];
        }
        if ( $loc = $request->get_param( 'location' ) ) {
            $args['tax_query'][] = [ 'taxonomy' => 'wcb_location', 'terms' => explode( ',', $loc ), 'field' => 'slug' ];
        }
        if ( $exp = $request->get_param( 'experience' ) ) {
            $args['tax_query'][] = [ 'taxonomy' => 'wcb_experience', 'terms' => explode( ',', $exp ), 'field' => 'slug' ];
        }
        if ( $request->get_param( 'remote' ) ) {
            $args['meta_query'][] = [ 'key' => '_wcb_remote', 'value' => '1' ];
        }
        if ( $sal_min = $request->get_param( 'salary_min' ) ) {
            $args['meta_query'][] = [ 'key' => '_wcb_salary_max', 'value' => (int) $sal_min, 'compare' => '>=', 'type' => 'NUMERIC' ];
        }

        $query = new \WP_Query( $args );
        $jobs  = array_map( [ $this, 'prepare_item_for_response_array' ], $query->posts );

        $response = rest_ensure_response( $jobs );
        $response->header( 'X-WCB-Total', $query->found_posts );
        $response->header( 'X-WCB-TotalPages', $query->max_num_pages );
        return $response;
    }

    public function get_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $post = get_post( (int) $request['id'] );
        if ( ! $post || 'wcb_job' !== $post->post_type ) {
            return new \WP_Error( 'wcb_not_found', __( 'Job not found.', 'wp-career-board' ), [ 'status' => 404 ] );
        }
        $this->record_job_view( $post->ID );
        return rest_ensure_response( $this->prepare_item_for_response_array( $post ) );
    }

    public function create_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $title = sanitize_text_field( $request->get_param( 'title' ) );
        if ( empty( $title ) ) {
            return new \WP_Error( 'wcb_missing_title', __( 'Job title is required.', 'wp-career-board' ), [ 'status' => 400 ] );
        }

        // Moderation: auto-publish vs pending
        $settings    = get_option( 'wcb_settings', [] );
        $auto_publish = ! empty( $settings['auto_publish_jobs'] );
        $status       = $auto_publish ? 'publish' : 'pending';

        $job_id = wp_insert_post( [
            'post_type'    => 'wcb_job',
            'post_title'   => $title,
            'post_content' => wp_kses_post( $request->get_param( 'description' ) ?? '' ),
            'post_status'  => $status,
            'post_author'  => get_current_user_id(),
        ], true );

        if ( is_wp_error( $job_id ) ) {
            return $job_id;
        }

        // Save postmeta
        $meta_fields = [
            '_wcb_deadline'      => $request->get_param( 'deadline' ),
            '_wcb_salary_min'    => $request->get_param( 'salary_min' ),
            '_wcb_salary_max'    => $request->get_param( 'salary_max' ),
            '_wcb_salary_currency' => $request->get_param( 'salary_currency' ) ?? 'USD',
            '_wcb_remote'        => $request->get_param( 'remote' ) ? '1' : '0',
            '_wcb_board_id'      => $request->get_param( 'board_id' ) ?? BoardsModule::get_default_board_id(),
        ];
        foreach ( $meta_fields as $key => $value ) {
            if ( null !== $value ) {
                update_post_meta( $job_id, $key, $value );
            }
        }

        // Taxonomies
        if ( $cats = $request->get_param( 'categories' ) ) {
            wp_set_object_terms( $job_id, (array) $cats, 'wcb_category' );
        }
        if ( $types = $request->get_param( 'job_types' ) ) {
            wp_set_object_terms( $job_id, (array) $types, 'wcb_job_type' );
        }

        do_action( 'wcb_job_created', $job_id, $request );

        return rest_ensure_response( $this->prepare_item_for_response_array( get_post( $job_id ) ) );
    }

    public function update_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $post = get_post( (int) $request['id'] );
        if ( ! $post || 'wcb_job' !== $post->post_type ) {
            return new \WP_Error( 'wcb_not_found', __( 'Job not found.', 'wp-career-board' ), [ 'status' => 404 ] );
        }

        $data = [];
        if ( $title = $request->get_param( 'title' ) ) {
            $data['post_title'] = sanitize_text_field( $title );
        }
        if ( $desc = $request->get_param( 'description' ) ) {
            $data['post_content'] = wp_kses_post( $desc );
        }
        if ( ! empty( $data ) ) {
            $data['ID'] = $post->ID;
            wp_update_post( $data );
        }

        do_action( 'wcb_job_updated', $post->ID, $request );
        return rest_ensure_response( $this->prepare_item_for_response_array( get_post( $post->ID ) ) );
    }

    public function delete_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $post = get_post( (int) $request['id'] );
        if ( ! $post || 'wcb_job' !== $post->post_type ) {
            return new \WP_Error( 'wcb_not_found', __( 'Job not found.', 'wp-career-board' ), [ 'status' => 404 ] );
        }
        wp_trash_post( $post->ID );
        return rest_ensure_response( [ 'deleted' => true, 'id' => $post->ID ] );
    }

    public function toggle_bookmark( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id  = get_current_user_id();
        $job_id   = (int) $request['id'];
        $bookmarks = (array) get_user_meta( $user_id, '_wcb_bookmarks', true );

        if ( in_array( $job_id, $bookmarks, true ) ) {
            $bookmarks = array_values( array_diff( $bookmarks, [ $job_id ] ) );
            $saved     = false;
        } else {
            $bookmarks[] = $job_id;
            $saved        = true;
        }

        update_user_meta( $user_id, '_wcb_bookmarks', array_unique( $bookmarks ) );
        return rest_ensure_response( [ 'bookmarked' => $saved, 'job_id' => $job_id ] );
    }

    public function get_applications( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $job_id = (int) $request['id'];
        $posts  = get_posts( [
            'post_type'      => 'wcb_application',
            'posts_per_page' => -1,
            'meta_query'     => [ [ 'key' => '_wcb_job_id', 'value' => $job_id ] ],
        ] );

        $items = array_map( fn( $p ) => [
            'id'           => $p->ID,
            'candidate_id' => get_post_meta( $p->ID, '_wcb_candidate_id', true ),
            'status'       => get_post_meta( $p->ID, '_wcb_status', true ),
            'submitted_at' => $p->post_date,
        ], $posts );

        return rest_ensure_response( $items );
    }

    // Permissions
    public function create_item_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
        return $this->check_ability( 'wcb_post_jobs' ) ? true : $this->permission_error();
    }

    public function update_item_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
        $post = get_post( (int) $request['id'] );
        if ( ! $post ) return $this->permission_error();
        $is_owner = (int) $post->post_author === $this->current_user_id();
        $is_admin = current_user_can( 'manage_options' );
        return ( $is_owner || $is_admin ) ? true : $this->permission_error();
    }

    public function delete_item_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
        return $this->update_item_permissions_check( $request );
    }

    public function view_applications_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
        return $this->check_ability( 'wcb_view_applications' ) ? true : $this->permission_error();
    }

    private function prepare_item_for_response_array( \WP_Post $post ): array {
        return [
            'id'          => $post->ID,
            'title'       => $post->post_title,
            'description' => $post->post_content,
            'status'      => $post->post_status,
            'author'      => $post->post_author,
            'date'        => $post->post_date,
            'permalink'   => get_permalink( $post->ID ),
            'deadline'    => get_post_meta( $post->ID, '_wcb_deadline', true ),
            'salary_min'  => get_post_meta( $post->ID, '_wcb_salary_min', true ),
            'salary_max'  => get_post_meta( $post->ID, '_wcb_salary_max', true ),
            'salary_currency' => get_post_meta( $post->ID, '_wcb_salary_currency', true ) ?: 'USD',
            'remote'      => '1' === get_post_meta( $post->ID, '_wcb_remote', true ),
            'board_id'    => (int) get_post_meta( $post->ID, '_wcb_board_id', true ),
            'categories'  => wp_get_object_terms( $post->ID, 'wcb_category', [ 'fields' => 'slugs' ] ),
            'job_types'   => wp_get_object_terms( $post->ID, 'wcb_job_type', [ 'fields' => 'slugs' ] ),
            'locations'   => wp_get_object_terms( $post->ID, 'wcb_location', [ 'fields' => 'slugs' ] ),
            'experience'  => wp_get_object_terms( $post->ID, 'wcb_experience', [ 'fields' => 'slugs' ] ),
            'thumbnail'   => get_the_post_thumbnail_url( $post->ID, 'medium' ) ?: '',
        ];
    }

    private function get_collection_params(): array {
        return [
            'search'     => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            'category'   => [ 'type' => 'string' ],
            'type'       => [ 'type' => 'string' ],
            'location'   => [ 'type' => 'string' ],
            'experience' => [ 'type' => 'string' ],
            'remote'     => [ 'type' => 'boolean' ],
            'salary_min' => [ 'type' => 'integer' ],
            'salary_max' => [ 'type' => 'integer' ],
            'page'       => [ 'type' => 'integer', 'default' => 1 ],
            'per_page'   => [ 'type' => 'integer', 'default' => 20 ],
        ];
    }
}
```

- [ ] **Step 2: Create `modules/jobs/class-jobs-expiry.php`**

```php
<?php
namespace WCB\Modules\Jobs;

class JobsExpiry {
    public function boot(): void {
        add_action( 'wcb_check_job_expiry', [ $this, 'expire_jobs' ] );
        if ( ! wp_next_scheduled( 'wcb_check_job_expiry' ) ) {
            wp_schedule_event( time(), 'daily', 'wcb_check_job_expiry' );
        }
    }

    public function expire_jobs(): void {
        $jobs = get_posts( [
            'post_type'      => 'wcb_job',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_wcb_deadline',
                    'value'   => current_time( 'Y-m-d' ),
                    'compare' => '<',
                    'type'    => 'DATE',
                ],
            ],
        ] );

        foreach ( $jobs as $job ) {
            wp_update_post( [ 'ID' => $job->ID, 'post_status' => 'wcb_expired' ] );
            do_action( 'wcb_job_expired', $job->ID );
        }
    }
}
```

- [ ] **Step 3: Test job endpoints with WP-CLI**

```bash
# Create a test employer user
wp user create testemployer test@example.com --role=wcb_employer \
  --path="/Users/varundubey/Local Sites/job-portal/app/public"

# Post a job via REST API
curl -s -X POST "http://job-portal.local/wp-json/wcb/v1/jobs" \
  -u "testemployer:$(wp user application-password create testemployer wcb-test --porcelain --path='/Users/varundubey/Local Sites/job-portal/app/public')" \
  -H "Content-Type: application/json" \
  -d '{"title":"Senior PHP Developer","description":"Great job","remote":true}'
```
Expected: 200 response with job object including `id`, `title`, `status`.

- [ ] **Step 4: Test GET /jobs**

```bash
curl -s "http://job-portal.local/wp-json/wcb/v1/jobs" | python3 -m json.tool | head -30
```
Expected: JSON array with the job created above.

- [ ] **Step 5: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add api/ modules/jobs/ && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "feat: jobs REST endpoint (GET/POST/PATCH/DELETE/bookmark/applications)"
```

---

### Task 6: Applications REST Endpoint

**Files:**
- Create: `api/endpoints/class-applications-endpoint.php`
- Create: `modules/applications/class-applications-meta.php`

- [ ] **Step 1: Create `api/endpoints/class-applications-endpoint.php`**

```php
<?php
namespace WCB\Api\Endpoints;

use WCB\Api\RestController;

class ApplicationsEndpoint extends RestController {

    public function register_routes(): void {
        // Apply to a job
        register_rest_route( $this->namespace, '/jobs/(?P<id>\d+)/apply', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'submit_application' ],
            'permission_callback' => fn() => $this->check_ability( 'wcb_apply_jobs' ),
        ] );

        // Single application
        register_rest_route( $this->namespace, '/applications/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_item' ],
            'permission_callback' => [ $this, 'get_item_permissions_check' ],
        ] );

        // Update status
        register_rest_route( $this->namespace, '/applications/(?P<id>\d+)/status', [
            'methods'             => \WP_REST_Server::EDITABLE,
            'callback'            => [ $this, 'update_status' ],
            'permission_callback' => [ $this, 'update_permissions_check' ],
        ] );

        // My applications (candidate)
        register_rest_route( $this->namespace, '/candidates/(?P<id>\d+)/applications', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_candidate_applications' ],
            'permission_callback' => [ $this, 'candidate_permissions_check' ],
        ] );
    }

    public function submit_application( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $job_id      = (int) $request['id'];
        $candidate_id = get_current_user_id();

        $job = get_post( $job_id );
        if ( ! $job || 'wcb_job' !== $job->post_type || 'publish' !== $job->post_status ) {
            return new \WP_Error( 'wcb_job_unavailable', __( 'This job is not available.', 'wp-career-board' ), [ 'status' => 400 ] );
        }

        // Prevent duplicate applications
        $existing = get_posts( [
            'post_type'      => 'wcb_application',
            'posts_per_page' => 1,
            'meta_query'     => [
                [ 'key' => '_wcb_job_id', 'value' => $job_id ],
                [ 'key' => '_wcb_candidate_id', 'value' => $candidate_id ],
            ],
        ] );
        if ( $existing ) {
            return new \WP_Error( 'wcb_already_applied', __( 'You have already applied to this job.', 'wp-career-board' ), [ 'status' => 409 ] );
        }

        $app_id = wp_insert_post( [
            'post_type'   => 'wcb_application',
            'post_title'  => sprintf( 'Application: User %d → Job %d', $candidate_id, $job_id ),
            'post_status' => 'publish',
            'post_author' => $candidate_id,
        ], true );

        if ( is_wp_error( $app_id ) ) {
            return $app_id;
        }

        update_post_meta( $app_id, '_wcb_job_id', $job_id );
        update_post_meta( $app_id, '_wcb_candidate_id', $candidate_id );
        update_post_meta( $app_id, '_wcb_cover_letter', sanitize_textarea_field( $request->get_param( 'cover_letter' ) ?? '' ) );
        update_post_meta( $app_id, '_wcb_resume_id', (int) $request->get_param( 'resume_id' ) );
        update_post_meta( $app_id, '_wcb_status', 'submitted' );

        do_action( 'wcb_application_submitted', $app_id, $job_id, $candidate_id );

        return rest_ensure_response( [
            'id'     => $app_id,
            'job_id' => $job_id,
            'status' => 'submitted',
        ] );
    }

    public function get_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $post = get_post( (int) $request['id'] );
        if ( ! $post || 'wcb_application' !== $post->post_type ) {
            return new \WP_Error( 'wcb_not_found', __( 'Application not found.', 'wp-career-board' ), [ 'status' => 404 ] );
        }
        return rest_ensure_response( $this->prepare_application( $post ) );
    }

    public function update_status( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $post = get_post( (int) $request['id'] );
        if ( ! $post || 'wcb_application' !== $post->post_type ) {
            return new \WP_Error( 'wcb_not_found', __( 'Application not found.', 'wp-career-board' ), [ 'status' => 404 ] );
        }

        $allowed  = [ 'submitted', 'reviewed', 'closed' ];
        $new_status = sanitize_text_field( $request->get_param( 'status' ) );
        if ( ! in_array( $new_status, $allowed, true ) ) {
            return new \WP_Error( 'wcb_invalid_status', __( 'Invalid status.', 'wp-career-board' ), [ 'status' => 400 ] );
        }

        $old_status = get_post_meta( $post->ID, '_wcb_status', true );
        update_post_meta( $post->ID, '_wcb_status', $new_status );

        do_action( 'wcb_application_status_changed', $post->ID, $old_status, $new_status );

        return rest_ensure_response( [ 'id' => $post->ID, 'status' => $new_status ] );
    }

    public function get_candidate_applications( \WP_REST_Request $request ): \WP_REST_Response {
        $candidate_id = (int) $request['id'];
        $posts = get_posts( [
            'post_type'      => 'wcb_application',
            'posts_per_page' => -1,
            'meta_query'     => [ [ 'key' => '_wcb_candidate_id', 'value' => $candidate_id ] ],
        ] );
        return rest_ensure_response( array_map( [ $this, 'prepare_application' ], $posts ) );
    }

    public function get_item_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
        $post = get_post( (int) $request['id'] );
        if ( ! $post ) return $this->permission_error();
        $is_candidate = (int) get_post_meta( $post->ID, '_wcb_candidate_id', true ) === get_current_user_id();
        $job_id       = (int) get_post_meta( $post->ID, '_wcb_job_id', true );
        $job          = get_post( $job_id );
        $is_employer  = $job && (int) $job->post_author === get_current_user_id();
        return ( $is_candidate || $is_employer || current_user_can( 'manage_options' ) ) ? true : $this->permission_error();
    }

    public function update_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
        return $this->check_ability( 'wcb_view_applications' ) ? true : $this->permission_error();
    }

    public function candidate_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
        $same_user = get_current_user_id() === (int) $request['id'];
        return ( $same_user || current_user_can( 'manage_options' ) ) ? true : $this->permission_error();
    }

    private function prepare_application( \WP_Post $post ): array {
        return [
            'id'           => $post->ID,
            'job_id'       => (int) get_post_meta( $post->ID, '_wcb_job_id', true ),
            'candidate_id' => (int) get_post_meta( $post->ID, '_wcb_candidate_id', true ),
            'cover_letter' => get_post_meta( $post->ID, '_wcb_cover_letter', true ),
            'resume_id'    => (int) get_post_meta( $post->ID, '_wcb_resume_id', true ),
            'status'       => get_post_meta( $post->ID, '_wcb_status', true ) ?: 'submitted',
            'submitted_at' => $post->post_date,
        ];
    }
}
```

- [ ] **Step 2: Test application submission**

```bash
# Create candidate
wp user create testcandidate candidate@example.com --role=wcb_candidate \
  --path="/Users/varundubey/Local Sites/job-portal/app/public"

# Get job ID from previous task, then apply:
curl -s -X POST "http://job-portal.local/wp-json/wcb/v1/jobs/JOB_ID/apply" \
  -u "testcandidate:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{"cover_letter":"I am very interested in this role."}'
```
Expected: `{"id":N,"job_id":JOB_ID,"status":"submitted"}`

- [ ] **Step 3: Verify duplicate prevention**

Re-run the same curl command. Expected: 409 Conflict.

- [ ] **Step 4: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add api/endpoints/class-applications-endpoint.php modules/applications/ && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "feat: applications REST endpoint (apply, status, candidate applications)"
```

---

### Task 7: Employers + Candidates REST Endpoints

**Files:**
- Create: `api/endpoints/class-employers-endpoint.php`
- Create: `api/endpoints/class-candidates-endpoint.php`

- [ ] **Step 1: Create `api/endpoints/class-employers-endpoint.php`**

```php
<?php
namespace WCB\Api\Endpoints;

use WCB\Api\RestController;

class EmployersEndpoint extends RestController {

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/employers/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_item' ],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_item' ],
                'permission_callback' => [ $this, 'update_permissions_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/employers/(?P<id>\d+)/jobs', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_jobs' ],
            'permission_callback' => '__return_true',
        ] );

        // Create company (for new employer)
        register_rest_route( $this->namespace, '/employers', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'create_item' ],
            'permission_callback' => fn() => $this->check_ability( 'wcb_manage_company' ),
        ] );
    }

    public function get_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $post = get_post( (int) $request['id'] );
        if ( ! $post || 'wcb_company' !== $post->post_type ) {
            return new \WP_Error( 'wcb_not_found', __( 'Company not found.', 'wp-career-board' ), [ 'status' => 404 ] );
        }
        return rest_ensure_response( $this->prepare_company( $post ) );
    }

    public function create_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $name = sanitize_text_field( $request->get_param( 'name' ) );
        if ( empty( $name ) ) {
            return new \WP_Error( 'wcb_missing_name', __( 'Company name is required.', 'wp-career-board' ), [ 'status' => 400 ] );
        }

        $company_id = wp_insert_post( [
            'post_type'    => 'wcb_company',
            'post_title'   => $name,
            'post_content' => wp_kses_post( $request->get_param( 'description' ) ?? '' ),
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
        ], true );

        if ( is_wp_error( $company_id ) ) return $company_id;

        $meta_fields = [
            '_wcb_website'  => $request->get_param( 'website' ),
            '_wcb_industry' => $request->get_param( 'industry' ),
            '_wcb_size'     => $request->get_param( 'size' ),
            '_wcb_trust_level' => 'new',
        ];
        foreach ( $meta_fields as $key => $value ) {
            if ( null !== $value ) update_post_meta( $company_id, $key, $value );
        }

        // Link company to employer user
        update_user_meta( get_current_user_id(), '_wcb_company_id', $company_id );

        return rest_ensure_response( $this->prepare_company( get_post( $company_id ) ) );
    }

    public function update_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $post = get_post( (int) $request['id'] );
        if ( ! $post || 'wcb_company' !== $post->post_type ) {
            return new \WP_Error( 'wcb_not_found', __( 'Company not found.', 'wp-career-board' ), [ 'status' => 404 ] );
        }

        $data = [ 'ID' => $post->ID ];
        if ( $name = $request->get_param( 'name' ) )        $data['post_title']   = sanitize_text_field( $name );
        if ( $desc = $request->get_param( 'description' ) ) $data['post_content'] = wp_kses_post( $desc );
        wp_update_post( $data );

        foreach ( [ '_wcb_website', '_wcb_industry', '_wcb_size' ] as $meta ) {
            if ( null !== $request->get_param( ltrim( $meta, '_wcb_' ) ) ) {
                update_post_meta( $post->ID, $meta, sanitize_text_field( $request->get_param( ltrim( $meta, '_wcb_' ) ) ) );
            }
        }

        return rest_ensure_response( $this->prepare_company( get_post( $post->ID ) ) );
    }

    public function get_jobs( \WP_REST_Request $request ): \WP_REST_Response {
        $company_id = (int) $request['id'];
        $posts = get_posts( [
            'post_type'   => 'wcb_job',
            'post_author' => (int) get_post( $company_id )->post_author,
            'post_status' => [ 'publish', 'pending', 'draft' ],
            'numberposts' => -1,
        ] );
        return rest_ensure_response( array_map( fn( $p ) => [ 'id' => $p->ID, 'title' => $p->post_title, 'status' => $p->post_status ], $posts ) );
    }

    public function update_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
        $post = get_post( (int) $request['id'] );
        if ( ! $post ) return $this->permission_error();
        $is_owner = (int) $post->post_author === get_current_user_id();
        return ( $is_owner || current_user_can( 'manage_options' ) ) ? true : $this->permission_error();
    }

    private function prepare_company( \WP_Post $post ): array {
        return [
            'id'          => $post->ID,
            'name'        => $post->post_title,
            'description' => $post->post_content,
            'logo'        => get_the_post_thumbnail_url( $post->ID, 'medium' ) ?: '',
            'website'     => get_post_meta( $post->ID, '_wcb_website', true ),
            'industry'    => get_post_meta( $post->ID, '_wcb_industry', true ),
            'size'        => get_post_meta( $post->ID, '_wcb_size', true ),
            'trust_level' => get_post_meta( $post->ID, '_wcb_trust_level', true ) ?: 'new',
            'permalink'   => get_permalink( $post->ID ),
        ];
    }
}
```

- [ ] **Step 2: Create `api/endpoints/class-candidates-endpoint.php`**

```php
<?php
namespace WCB\Api\Endpoints;

use WCB\Api\RestController;

class CandidatesEndpoint extends RestController {

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/candidates/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_item' ],
                'permission_callback' => [ $this, 'get_item_permissions_check' ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_item' ],
                'permission_callback' => [ $this, 'update_permissions_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/candidates/(?P<id>\d+)/bookmarks', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_bookmarks' ],
            'permission_callback' => [ $this, 'self_permissions_check' ],
        ] );
    }

    public function get_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $user_id = (int) $request['id'];
        $user    = get_user_by( 'ID', $user_id );
        if ( ! $user ) {
            return new \WP_Error( 'wcb_not_found', __( 'Candidate not found.', 'wp-career-board' ), [ 'status' => 404 ] );
        }
        // Respect privacy setting
        $visibility = get_user_meta( $user_id, '_wcb_profile_visibility', true ) ?: 'public';
        if ( 'private' === $visibility && get_current_user_id() !== $user_id && ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error( 'wcb_private', __( 'This profile is private.', 'wp-career-board' ), [ 'status' => 403 ] );
        }
        return rest_ensure_response( $this->prepare_candidate( $user ) );
    }

    public function update_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $user_id = (int) $request['id'];
        $user    = get_user_by( 'ID', $user_id );
        if ( ! $user ) return new \WP_Error( 'wcb_not_found', __( 'Candidate not found.', 'wp-career-board' ), [ 'status' => 404 ] );

        if ( $bio = $request->get_param( 'bio' ) ) {
            wp_update_user( [ 'ID' => $user_id, 'description' => sanitize_textarea_field( $bio ) ] );
        }
        if ( null !== $request->get_param( 'profile_visibility' ) ) {
            $vis = in_array( $request->get_param( 'profile_visibility' ), [ 'public', 'private' ], true )
                ? $request->get_param( 'profile_visibility' ) : 'public';
            update_user_meta( $user_id, '_wcb_profile_visibility', $vis );
        }
        if ( $resume_data = $request->get_param( 'resume' ) ) {
            update_user_meta( $user_id, '_wcb_resume_data', $resume_data );
        }

        return rest_ensure_response( $this->prepare_candidate( get_user_by( 'ID', $user_id ) ) );
    }

    public function get_bookmarks( \WP_REST_Request $request ): \WP_REST_Response {
        $bookmarks = (array) get_user_meta( (int) $request['id'], '_wcb_bookmarks', true );
        $jobs = array_filter( array_map( fn( $id ) => get_post( $id ), $bookmarks ) );
        return rest_ensure_response( array_values( array_map( fn( $p ) => [
            'id'    => $p->ID,
            'title' => $p->post_title,
            'link'  => get_permalink( $p->ID ),
        ], $jobs ) ) );
    }

    public function get_item_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
        return true; // visibility handled inside get_item
    }

    public function update_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
        $same_user = get_current_user_id() === (int) $request['id'];
        return ( $same_user || current_user_can( 'manage_options' ) ) ? true : $this->permission_error();
    }

    public function self_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
        $same_user = get_current_user_id() === (int) $request['id'];
        return ( $same_user || current_user_can( 'manage_options' ) ) ? true : $this->permission_error();
    }

    private function prepare_candidate( \WP_User $user ): array {
        return [
            'id'                 => $user->ID,
            'display_name'       => $user->display_name,
            'bio'                => $user->description,
            'profile_visibility' => get_user_meta( $user->ID, '_wcb_profile_visibility', true ) ?: 'public',
            'avatar'             => get_avatar_url( $user->ID ),
            'resume_data'        => get_user_meta( $user->ID, '_wcb_resume_data', true ) ?: [],
        ];
    }
}
```

- [ ] **Step 3: Create `api/endpoints/class-search-endpoint.php`**

```php
<?php
namespace WCB\Api\Endpoints;

use WCB\Api\RestController;

class SearchEndpoint extends RestController {

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/search', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'search' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function search( \WP_REST_Request $request ): \WP_REST_Response {
        // Delegate to jobs endpoint with same params — unified search entry point
        $jobs_endpoint = new JobsEndpoint();
        return $jobs_endpoint->get_items( $request );
    }
}
```

- [ ] **Step 4: Test employer and candidate endpoints**

```bash
# Get company (after creating one in Task 5)
curl -s "http://job-portal.local/wp-json/wcb/v1/employers/COMPANY_ID" | python3 -m json.tool

# Get candidate profile
curl -s "http://job-portal.local/wp-json/wcb/v1/candidates/CANDIDATE_USER_ID" | python3 -m json.tool
```

- [ ] **Step 5: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add api/ && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "feat: employers, candidates, search REST endpoints"
```

---
### Chunk 2 — Corrections (from plan review)

Apply these before implementing any Chunk 2 task:

**Correction A — Abilities API in all permission methods.**
Every permission check method that currently calls `current_user_can('manage_options')` directly must instead call `$this->check_ability('wcb_manage_settings')`. Affects:
- `JobsEndpoint::update_item_permissions_check`
- `JobsEndpoint::delete_item_permissions_check`
- `ApplicationsEndpoint::get_item_permissions_check`
- `ApplicationsEndpoint::update_permissions_check`
- `EmployersEndpoint::update_permissions_check`
- `CandidatesEndpoint::update_permissions_check`
- `CandidatesEndpoint::self_permissions_check`

**Correction B — Duplicate application check must cover all statuses.**
In `submit_application()`, change `get_posts()` to include `'post_status' => 'any'` so trashed/draft applications are also caught.

**Correction C — Bookmark: unique key per job instead of array.**
Replace the serialized `_wcb_bookmarks` array with individual meta keys:
```php
// Add bookmark:
add_user_meta( $user_id, '_wcb_bookmark', $job_id ); // non-unique key, multiple values

// Remove bookmark:
delete_user_meta( $user_id, '_wcb_bookmark', $job_id );

// Get all bookmarks:
get_user_meta( $user_id, '_wcb_bookmark' ); // returns array of job IDs
```
This is atomic — no race condition.

**Correction D — Missing taxonomy terms in `create_item()`.**
After the existing `wcb_category` and `wcb_job_type` calls, add:
```php
if ( $locs = $request->get_param( 'locations' ) ) {
    wp_set_object_terms( $job_id, (array) $locs, 'wcb_location' );
}
if ( $exp = $request->get_param( 'experience' ) ) {
    wp_set_object_terms( $job_id, (array) $exp, 'wcb_experience' );
}
if ( $tags = $request->get_param( 'tags' ) ) {
    wp_set_object_terms( $job_id, (array) $tags, 'wcb_tag' );
}
```

**Correction E — Wire `salary_max` filter in `get_items()`.**
```php
if ( $sal_max = $request->get_param( 'salary_max' ) ) {
    $args['meta_query'][] = [ 'key' => '_wcb_salary_min', 'value' => (int) $sal_max, 'compare' => '<=', 'type' => 'NUMERIC' ];
}
```

**Correction F — Application tags are a Free feature.**
Add `POST /applications/{id}/tags` endpoint to `ApplicationsEndpoint`:
```php
register_rest_route( $this->namespace, '/applications/(?P<id>\d+)/tags', [
    'methods'             => \WP_REST_Server::CREATABLE,
    'callback'            => [ $this, 'update_tags' ],
    'permission_callback' => [ $this, 'update_permissions_check' ],
] );

public function update_tags( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
    $post = get_post( (int) $request['id'] );
    if ( ! $post || 'wcb_application' !== $post->post_type ) {
        return new \WP_Error( 'wcb_not_found', __( 'Application not found.', 'wp-career-board' ), [ 'status' => 404 ] );
    }
    $tags = array_map( 'sanitize_text_field', (array) $request->get_param( 'tags' ) );
    update_post_meta( $post->ID, '_wcb_tags', $tags );
    return rest_ensure_response( [ 'id' => $post->ID, 'tags' => $tags ] );
}
```

---
---

## Chunk 3: Services (Notifications, Moderation, SEO, GDPR)

### Task 8: Email Notifications (wp_mail)

**Files:**
- Create: `modules/notifications/class-notifications-email.php`

- [ ] **Step 1: Create the email notification driver**

```php
<?php
namespace WCB\Modules\Notifications;

class NotificationsEmail {
    public function boot(): void {
        add_action( 'wcb_job_created',               [ $this, 'on_job_created' ], 10, 2 );
        add_action( 'wcb_application_submitted',      [ $this, 'on_application_submitted' ], 10, 3 );
        add_action( 'wcb_application_status_changed', [ $this, 'on_status_changed' ], 10, 3 );
        add_action( 'wcb_job_approved',               [ $this, 'on_job_approved' ], 10, 1 );
        add_action( 'wcb_job_rejected',               [ $this, 'on_job_rejected' ], 10, 2 );
        add_action( 'wcb_job_expired',                [ $this, 'on_job_expired' ], 10, 1 );
    }

    /** Notify admin of new job pending review (approval-required mode). */
    public function on_job_created( int $job_id, \WP_REST_Request $request ): void {
        $job = get_post( $job_id );
        if ( 'pending' !== $job->post_status ) return; // auto-publish mode — no email needed

        $this->send(
            get_option( 'admin_email' ),
            sprintf( __( '[Action Required] New job pending approval: %s', 'wp-career-board' ), $job->post_title ),
            $this->render( 'job-pending-review', [
                'job_title'   => $job->post_title,
                'approve_url' => admin_url( 'post.php?post=' . $job_id . '&action=edit' ),
            ] )
        );
    }

    /** Notify employer: new application received. */
    public function on_application_submitted( int $app_id, int $job_id, int $candidate_id ): void {
        $job       = get_post( $job_id );
        $employer  = get_user_by( 'ID', $job->post_author );
        $candidate = get_user_by( 'ID', $candidate_id );

        $this->send(
            $employer->user_email,
            sprintf( __( 'New application for: %s', 'wp-career-board' ), $job->post_title ),
            $this->render( 'application-received', [
                'job_title'      => $job->post_title,
                'candidate_name' => $candidate->display_name,
                'dashboard_url'  => get_permalink( get_option( 'wcb_employer_dashboard_page' ) ),
            ] )
        );

        // Also confirm to candidate
        $this->send(
            $candidate->user_email,
            sprintf( __( 'Application submitted: %s', 'wp-career-board' ), $job->post_title ),
            $this->render( 'application-confirmation', [
                'job_title'   => $job->post_title,
                'company'     => $job->post_title, // resolved from company postmeta at implementation
                'dashboard_url' => get_permalink( get_option( 'wcb_candidate_dashboard_page' ) ),
            ] )
        );
    }

    /** Notify candidate: application status changed. */
    public function on_status_changed( int $app_id, string $old_status, string $new_status ): void {
        $candidate_id = (int) get_post_meta( $app_id, '_wcb_candidate_id', true );
        $job_id       = (int) get_post_meta( $app_id, '_wcb_job_id', true );
        $candidate    = get_user_by( 'ID', $candidate_id );
        $job          = get_post( $job_id );

        $this->send(
            $candidate->user_email,
            sprintf( __( 'Your application status updated: %s', 'wp-career-board' ), $job->post_title ),
            $this->render( 'application-status-changed', [
                'job_title'  => $job->post_title,
                'new_status' => ucfirst( $new_status ),
                'dashboard_url' => get_permalink( get_option( 'wcb_candidate_dashboard_page' ) ),
            ] )
        );
    }

    /** Notify employer: job approved by admin. */
    public function on_job_approved( int $job_id ): void {
        $job      = get_post( $job_id );
        $employer = get_user_by( 'ID', $job->post_author );
        $this->send(
            $employer->user_email,
            sprintf( __( 'Your job has been approved: %s', 'wp-career-board' ), $job->post_title ),
            $this->render( 'job-approved', [
                'job_title'  => $job->post_title,
                'job_url'    => get_permalink( $job_id ),
            ] )
        );
    }

    /** Notify employer: job rejected. */
    public function on_job_rejected( int $job_id, string $reason ): void {
        $job      = get_post( $job_id );
        $employer = get_user_by( 'ID', $job->post_author );
        $this->send(
            $employer->user_email,
            sprintf( __( 'Your job was not approved: %s', 'wp-career-board' ), $job->post_title ),
            $this->render( 'job-rejected', [
                'job_title' => $job->post_title,
                'reason'    => $reason,
            ] )
        );
    }

    /** Notify employer: job expired. */
    public function on_job_expired( int $job_id ): void {
        $job      = get_post( $job_id );
        $employer = get_user_by( 'ID', $job->post_author );
        $this->send(
            $employer->user_email,
            sprintf( __( 'Your job has expired: %s', 'wp-career-board' ), $job->post_title ),
            $this->render( 'job-expired', [
                'job_title'   => $job->post_title,
                'repost_url'  => get_permalink( get_option( 'wcb_employer_dashboard_page' ) ),
            ] )
        );
    }

    private function send( string $to, string $subject, string $body ): void {
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $sent    = wp_mail( $to, $subject, $body, $headers );

        // Log to wcb_notifications_log
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'wcb_notifications_log',
            [
                'user_id'    => 0, // resolved by caller context at implementation
                'event_type' => current_action(),
                'channel'    => 'email',
                'payload'    => wp_json_encode( compact( 'to', 'subject' ) ),
                'status'     => $sent ? 'sent' : 'failed',
                'sent_at'    => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    /**
     * Minimal template renderer. Templates live in modules/notifications/templates/.
     * Each template is a PHP file that returns a string.
     */
    private function render( string $template, array $vars ): string {
        $file = WCB_DIR . 'modules/notifications/templates/' . $template . '.php';
        if ( ! file_exists( $file ) ) {
            return implode( "\n", array_map(
                fn( $k, $v ) => ucfirst( $k ) . ': ' . $v,
                array_keys( $vars ), $vars
            ) );
        }
        extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
        ob_start();
        include $file;
        return ob_get_clean();
    }
}
```

- [ ] **Step 2: Create email templates directory and base templates**

Create `modules/notifications/templates/application-received.php`:
```php
<?php /* translators: email template */ ?>
<p><?php printf( esc_html__( 'A new application has been received for %s.', 'wp-career-board' ), esc_html( $job_title ) ); ?></p>
<p><?php printf( esc_html__( 'Applicant: %s', 'wp-career-board' ), esc_html( $candidate_name ) ); ?></p>
<p><a href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'View in Dashboard', 'wp-career-board' ); ?></a></p>
```

Repeat for: `job-pending-review.php`, `application-confirmation.php`, `application-status-changed.php`, `job-approved.php`, `job-rejected.php`, `job-expired.php` — each following the same minimal HTML pattern.

- [ ] **Step 3: Test notification fires on application**

1. Post a job as employer (auto-publish mode ON in settings).
2. Apply as candidate.
3. Check employer inbox (or use `wp_mail` debug plugin / WP Mail Log).
   Expected: "New application for: [Job Title]" email.
4. Check candidate inbox.
   Expected: "Application submitted: [Job Title]" email.

- [ ] **Step 4: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add modules/notifications/ && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "feat: email notifications for all job board events (wp_mail)"
```

---

### Task 9: Moderation Queue

**Files:**
- Create: `modules/moderation/class-moderation-module.php`

- [ ] **Step 1: Create moderation module**

```php
<?php
namespace WCB\Modules\Moderation;

class ModerationModule {
    public function boot(): void {
        // Register wcb_expired as a custom post status
        add_action( 'init', [ $this, 'register_post_statuses' ] );
        // Admin AJAX handlers for approve/reject
        add_action( 'wp_ajax_wcb_approve_job', [ $this, 'approve_job' ] );
        add_action( 'wp_ajax_wcb_reject_job',  [ $this, 'reject_job' ] );
    }

    public function register_post_statuses(): void {
        register_post_status( 'wcb_expired', [
            'label'                     => __( 'Expired', 'wp-career-board' ),
            'public'                    => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'wp-career-board' ),
        ] );
    }

    public function approve_job(): void {
        check_ajax_referer( 'wcb_moderate', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wcb_moderate_jobs' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-career-board' ), 403 );
        }

        $job_id = (int) $_POST['job_id'] ?? 0;
        $job    = get_post( $job_id );
        if ( ! $job || 'wcb_job' !== $job->post_type ) {
            wp_send_json_error( __( 'Job not found.', 'wp-career-board' ), 404 );
        }

        wp_update_post( [ 'ID' => $job_id, 'post_status' => 'publish' ] );
        do_action( 'wcb_job_approved', $job_id );

        wp_send_json_success( [ 'status' => 'published' ] );
    }

    public function reject_job(): void {
        check_ajax_referer( 'wcb_moderate', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wcb_moderate_jobs' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-career-board' ), 403 );
        }

        $job_id = (int) $_POST['job_id'] ?? 0;
        $reason = sanitize_textarea_field( $_POST['reason'] ?? '' );
        $job    = get_post( $job_id );
        if ( ! $job || 'wcb_job' !== $job->post_type ) {
            wp_send_json_error( __( 'Job not found.', 'wp-career-board' ), 404 );
        }

        wp_update_post( [ 'ID' => $job_id, 'post_status' => 'draft' ] );
        update_post_meta( $job_id, '_wcb_rejection_reason', $reason );
        do_action( 'wcb_job_rejected', $job_id, $reason );

        wp_send_json_success( [ 'status' => 'rejected' ] );
    }
}
```

- [ ] **Step 2: Test approve flow**

1. Set `wcb_settings['auto_publish_jobs'] = false` (approval-required mode).
2. Post a job as employer.
3. Verify job is in `pending` status in wp-admin > Posts > Jobs.
4. Call `wcb_approve_job` AJAX with admin credentials.
5. Verify job moves to `publish` and employer receives approval email.

- [ ] **Step 3: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add modules/moderation/ && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "feat: moderation queue (approve/reject), wcb_expired post status"
```

---

### Task 10: SEO Module

**Files:**
- Create: `modules/seo/class-seo-module.php`

- [ ] **Step 1: Create SEO module**

```php
<?php
namespace WCB\Modules\Seo;

class SeoModule {
    public function boot(): void {
        add_action( 'wp_head', [ $this, 'inject_schema' ] );
        add_action( 'wp_head', [ $this, 'inject_og_tags' ] );
        // Disable if Yoast/RankMath active — they handle schema
        add_action( 'init', function () {
            if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
                remove_action( 'wp_head', [ $this, 'inject_schema' ] );
            }
        } );
    }

    public function inject_schema(): void {
        if ( ! is_singular( 'wcb_job' ) ) return;

        $job       = get_post();
        $salary_min = get_post_meta( $job->ID, '_wcb_salary_min', true );
        $salary_max = get_post_meta( $job->ID, '_wcb_salary_max', true );
        $currency   = get_post_meta( $job->ID, '_wcb_salary_currency', true ) ?: 'USD';
        $deadline   = get_post_meta( $job->ID, '_wcb_deadline', true );
        $remote     = get_post_meta( $job->ID, '_wcb_remote', true );

        $schema = [
            '@context'         => 'https://schema.org',
            '@type'            => 'JobPosting',
            'title'            => get_the_title( $job ),
            'description'      => wp_strip_all_tags( $job->post_content ),
            'datePosted'       => get_the_date( 'c', $job ),
            'validThrough'     => $deadline ? date( 'c', strtotime( $deadline ) ) : null,
            'employmentType'   => $this->get_employment_types( $job->ID ),
            'jobLocationType'  => $remote ? 'TELECOMMUTE' : null,
            'hiringOrganization' => $this->get_hiring_org( $job->ID ),
        ];

        if ( $salary_min || $salary_max ) {
            $schema['baseSalary'] = [
                '@type'    => 'MonetaryAmount',
                'currency' => $currency,
                'value'    => [
                    '@type'    => 'QuantitativeValue',
                    'minValue' => $salary_min ? (float) $salary_min : null,
                    'maxValue' => $salary_max ? (float) $salary_max : null,
                    'unitText' => 'YEAR',
                ],
            ];
        }

        $schema = array_filter( $schema, fn( $v ) => null !== $v );
        echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>' . "\n"; // phpcs:ignore
    }

    public function inject_og_tags(): void {
        if ( ! is_singular( 'wcb_job' ) ) return;

        $job = get_post();
        echo '<meta property="og:type" content="website" />' . "\n"; // phpcs:ignore
        echo '<meta property="og:title" content="' . esc_attr( get_the_title( $job ) ) . '" />' . "\n"; // phpcs:ignore
        echo '<meta property="og:description" content="' . esc_attr( wp_trim_words( wp_strip_all_tags( $job->post_content ), 30 ) ) . '" />' . "\n"; // phpcs:ignore
        echo '<meta property="og:url" content="' . esc_url( get_permalink( $job ) ) . '" />' . "\n"; // phpcs:ignore
    }

    private function get_employment_types( int $job_id ): array {
        $map   = [ 'full-time' => 'FULL_TIME', 'part-time' => 'PART_TIME', 'contract' => 'CONTRACTOR', 'internship' => 'INTERN', 'freelance' => 'CONTRACTOR' ];
        $terms = wp_get_object_terms( $job_id, 'wcb_job_type', [ 'fields' => 'slugs' ] );
        return array_values( array_filter( array_map( fn( $s ) => $map[ $s ] ?? null, $terms ) ) );
    }

    private function get_hiring_org( int $job_id ): array {
        $job  = get_post( $job_id );
        $comp = get_user_meta( $job->post_author, '_wcb_company_id', true );
        $name = $comp ? get_the_title( (int) $comp ) : get_bloginfo( 'name' );
        return [ '@type' => 'Organization', 'name' => $name, 'sameAs' => get_bloginfo( 'url' ) ];
    }
}
```

- [ ] **Step 2: Verify schema on a job page**

1. Create and publish a test job.
2. View the job's single page source.
3. Search for `application/ld+json`.
   Expected: Valid `JobPosting` schema block.

- [ ] **Step 3: Test Yoast conflict prevention**

With Yoast active, verify `inject_schema` is removed (no duplicate schema).

- [ ] **Step 4: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add modules/seo/ && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "feat: SEO module — JobPosting schema, OG tags, Yoast/RankMath compat"
```

---

### Task 11: GDPR Module

**Files:**
- Create: `modules/gdpr/class-gdpr-module.php`

- [ ] **Step 1: Create GDPR module**

```php
<?php
namespace WCB\Modules\Gdpr;

class GdprModule {
    public function boot(): void {
        // Register with WordPress privacy tools
        add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );
        add_filter( 'wp_privacy_personal_data_erasers',   [ $this, 'register_eraser' ] );
    }

    public function register_exporter( array $exporters ): array {
        $exporters['wp-career-board'] = [
            'exporter_friendly_name' => __( 'WP Career Board', 'wp-career-board' ),
            'callback'               => [ $this, 'export_user_data' ],
        ];
        return $exporters;
    }

    public function register_eraser( array $erasers ): array {
        $erasers['wp-career-board'] = [
            'eraser_friendly_name' => __( 'WP Career Board', 'wp-career-board' ),
            'callback'             => [ $this, 'erase_user_data' ],
        ];
        return $erasers;
    }

    public function export_user_data( string $email_address, int $page = 1 ): array {
        $user = get_user_by( 'email', $email_address );
        if ( ! $user ) return [ 'data' => [], 'done' => true ];

        $data = [];

        // Applications
        $apps = get_posts( [
            'post_type'  => 'wcb_application',
            'meta_query' => [ [ 'key' => '_wcb_candidate_id', 'value' => $user->ID ] ],
            'numberposts' => -1,
        ] );
        foreach ( $apps as $app ) {
            $data[] = [
                'group_id'    => 'wcb-applications',
                'group_label' => __( 'Job Applications', 'wp-career-board' ),
                'item_id'     => 'application-' . $app->ID,
                'data'        => [
                    [ 'name' => __( 'Job', 'wp-career-board' ), 'value' => get_the_title( get_post_meta( $app->ID, '_wcb_job_id', true ) ) ],
                    [ 'name' => __( 'Status', 'wp-career-board' ), 'value' => get_post_meta( $app->ID, '_wcb_status', true ) ],
                    [ 'name' => __( 'Submitted', 'wp-career-board' ), 'value' => $app->post_date ],
                ],
            ];
        }

        // Log the export
        $this->log_action( $user->ID, 'export' );

        return [ 'data' => $data, 'done' => true ];
    }

    public function erase_user_data( string $email_address, int $page = 1 ): array {
        $user = get_user_by( 'email', $email_address );
        if ( ! $user ) return [ 'items_removed' => 0, 'items_retained' => 0, 'messages' => [], 'done' => true ];

        $removed = 0;

        // Erase applications
        $apps = get_posts( [
            'post_type'   => 'wcb_application',
            'meta_query'  => [ [ 'key' => '_wcb_candidate_id', 'value' => $user->ID ] ],
            'numberposts' => -1,
        ] );
        foreach ( $apps as $app ) {
            wp_delete_post( $app->ID, true );
            $removed++;
        }

        // Erase resume data
        delete_user_meta( $user->ID, '_wcb_resume_data' );
        delete_user_meta( $user->ID, '_wcb_bookmarks' );

        // Log the erasure
        $this->log_action( $user->ID, 'erase' );

        return [
            'items_removed'  => $removed,
            'items_retained' => 0,
            'messages'       => [],
            'done'           => true,
        ];
    }

    private function log_action( int $user_id, string $action ): void {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'wcb_gdpr_log',
            [
                'user_id'    => $user_id,
                'action'     => $action,
                'ip_hash'    => hash( 'sha256', $_SERVER['REMOTE_ADDR'] ?? '' ),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s' ]
        );
    }
}
```

- [ ] **Step 2: Verify in WordPress admin**

Go to **Tools > Export Personal Data**. Enter a candidate's email. Confirm WP Career Board appears as an exporter and exports application data.

- [ ] **Step 3: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add modules/gdpr/ && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "feat: GDPR module — WP privacy API exporter + eraser"
```

---
---

## Chunk 4: Admin Interface + Setup Wizard

### Task 12: Admin Menu + Settings

**Files:**
- Create: `admin/class-admin.php`
- Create: `admin/class-admin-settings.php`

- [ ] **Step 1: Create `admin/class-admin.php`**

```php
<?php
namespace WCB\Admin;

class Admin {
    public function boot(): void {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_menus(): void {
        // Top-level menu (WooCommerce-style)
        add_menu_page(
            __( 'WP Career Board', 'wp-career-board' ),
            __( 'Career Board', 'wp-career-board' ),
            'manage_options',
            'wp-career-board',
            [ $this, 'render_dashboard' ],
            'dashicons-portfolio',
            25
        );

        add_submenu_page( 'wp-career-board', __( 'Jobs', 'wp-career-board' ), __( 'Jobs', 'wp-career-board' ), 'manage_options', 'wcb-jobs', [ new AdminJobs(), 'render' ] );
        add_submenu_page( 'wp-career-board', __( 'Applications', 'wp-career-board' ), __( 'Applications', 'wp-career-board' ), 'manage_options', 'wcb-applications', [ new AdminApplications(), 'render' ] );
        add_submenu_page( 'wp-career-board', __( 'Employers', 'wp-career-board' ), __( 'Employers', 'wp-career-board' ), 'manage_options', 'wcb-employers', [ new AdminEmployers(), 'render' ] );
        add_submenu_page( 'wp-career-board', __( 'Settings', 'wp-career-board' ), __( 'Settings', 'wp-career-board' ), 'manage_options', 'wcb-settings', [ new AdminSettings(), 'render' ] );
    }

    public function render_dashboard(): void {
        $total_jobs     = wp_count_posts( 'wcb_job' )->publish ?? 0;
        $total_apps     = wp_count_posts( 'wcb_application' )->publish ?? 0;
        $pending_jobs   = wp_count_posts( 'wcb_job' )->pending ?? 0;
        ?>
        <div class="wrap wcb-admin-dashboard">
            <h1><?php esc_html_e( 'WP Career Board', 'wp-career-board' ); ?></h1>
            <div class="wcb-stats-grid">
                <div class="wcb-stat-box">
                    <span class="wcb-stat-number"><?php echo (int) $total_jobs; ?></span>
                    <span class="wcb-stat-label"><?php esc_html_e( 'Active Jobs', 'wp-career-board' ); ?></span>
                </div>
                <div class="wcb-stat-box">
                    <span class="wcb-stat-number"><?php echo (int) $total_apps; ?></span>
                    <span class="wcb-stat-label"><?php esc_html_e( 'Applications', 'wp-career-board' ); ?></span>
                </div>
                <div class="wcb-stat-box wcb-stat-alert">
                    <span class="wcb-stat-number"><?php echo (int) $pending_jobs; ?></span>
                    <span class="wcb-stat-label"><?php esc_html_e( 'Pending Review', 'wp-career-board' ); ?></span>
                </div>
            </div>
        </div>
        <?php
    }

    public function enqueue_assets( string $hook ): void {
        if ( false === strpos( $hook, 'wcb' ) && false === strpos( $hook, 'wp-career-board' ) ) return;
        wp_enqueue_style( 'wcb-admin', WCB_URL . 'assets/css/admin.css', [], WCB_VERSION );
        wp_enqueue_script( 'wcb-admin', WCB_URL . 'assets/js/admin.js', [ 'jquery' ], WCB_VERSION, true );
        wp_localize_script( 'wcb-admin', 'wcbAdmin', [
            'nonce'   => wp_create_nonce( 'wcb_moderate' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'i18n'    => [
                'confirmApprove' => __( 'Approve this job?', 'wp-career-board' ),
                'confirmReject'  => __( 'Reject this job? Enter reason:', 'wp-career-board' ),
            ],
        ] );
    }
}
```

- [ ] **Step 2: Create `admin/class-admin-settings.php`**

```php
<?php
namespace WCB\Admin;

class AdminSettings {
    const OPTION_KEY = 'wcb_settings';

    public function boot(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings(): void {
        register_setting( 'wcb_settings_group', self::OPTION_KEY, [
            'sanitize_callback' => [ $this, 'sanitize' ],
        ] );
    }

    public function sanitize( array $input ): array {
        return [
            'auto_publish_jobs'         => ! empty( $input['auto_publish_jobs'] ),
            'jobs_expire_days'          => max( 1, (int) ( $input['jobs_expire_days'] ?? 30 ) ),
            'employer_dashboard_page'   => (int) ( $input['employer_dashboard_page'] ?? 0 ),
            'candidate_dashboard_page'  => (int) ( $input['candidate_dashboard_page'] ?? 0 ),
            'jobs_archive_page'         => (int) ( $input['jobs_archive_page'] ?? 0 ),
        ];
    }

    public function render(): void {
        $settings = get_option( self::OPTION_KEY, [] );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'WP Career Board — Settings', 'wp-career-board' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'wcb_settings_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Auto-Publish Jobs', 'wp-career-board' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wcb_settings[auto_publish_jobs]" value="1" <?php checked( ! empty( $settings['auto_publish_jobs'] ) ); ?>>
                                <?php esc_html_e( 'Publish jobs immediately without admin review', 'wp-career-board' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Job Expiry (days)', 'wp-career-board' ); ?></th>
                        <td><input type="number" name="wcb_settings[jobs_expire_days]" value="<?php echo (int) ( $settings['jobs_expire_days'] ?? 30 ); ?>" min="1" max="365"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Employer Dashboard Page', 'wp-career-board' ); ?></th>
                        <td><?php wp_dropdown_pages( [ 'name' => 'wcb_settings[employer_dashboard_page]', 'selected' => $settings['employer_dashboard_page'] ?? 0, 'show_option_none' => __( '— Select —', 'wp-career-board' ) ] ); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Candidate Dashboard Page', 'wp-career-board' ); ?></th>
                        <td><?php wp_dropdown_pages( [ 'name' => 'wcb_settings[candidate_dashboard_page]', 'selected' => $settings['candidate_dashboard_page'] ?? 0, 'show_option_none' => __( '— Select —', 'wp-career-board' ) ] ); ?></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
```

- [ ] **Step 3: Create stub admin list pages**

Create `admin/class-admin-jobs.php`, `admin/class-admin-applications.php`, `admin/class-admin-employers.php` each with a `render()` method that calls `WP_List_Table` for the corresponding CPT. These can start as simple post-list redirects:

```php
<?php
namespace WCB\Admin;

class AdminJobs {
    public function render(): void {
        // Show pending jobs first for moderation
        $pending = get_posts( [ 'post_type' => 'wcb_job', 'post_status' => 'pending', 'numberposts' => -1 ] );
        $published = get_posts( [ 'post_type' => 'wcb_job', 'post_status' => 'publish', 'numberposts' => 50 ] );
        include WCB_DIR . 'admin/views/jobs-list.php';
    }
}
```

Create `admin/views/jobs-list.php` with a simple HTML table showing pending jobs (title, author, date) with Approve/Reject buttons wired to the AJAX handlers in ModerationModule.

- [ ] **Step 4: Verify admin menu appears**

Navigate to wp-admin. Confirm "Career Board" top-level menu with submenus: Jobs, Applications, Employers, Settings.

- [ ] **Step 5: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add admin/ assets/ && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "feat: admin menu, dashboard stats, settings page"
```

---

### Task 13: Setup Wizard + Sample Data

**Files:**
- Create: `admin/class-setup-wizard.php`

- [ ] **Step 1: Create setup wizard**

The wizard fires on first activation (when `wcb_setup_complete` option is not set). It creates required pages, sets defaults, and optionally inserts sample data.

```php
<?php
namespace WCB\Admin;

class SetupWizard {
    public function boot(): void {
        add_action( 'admin_init', [ $this, 'maybe_redirect' ] );
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'wp_ajax_wcb_wizard_step', [ $this, 'handle_step' ] );
    }

    public function maybe_redirect(): void {
        if ( get_transient( 'wcb_activation_redirect' ) && ! get_option( 'wcb_setup_complete' ) ) {
            delete_transient( 'wcb_activation_redirect' );
            wp_safe_redirect( admin_url( 'admin.php?page=wcb-setup' ) );
            exit;
        }
    }

    public function register_page(): void {
        add_submenu_page( null, __( 'Setup Wizard', 'wp-career-board' ), '', 'manage_options', 'wcb-setup', [ $this, 'render' ] );
    }

    public function render(): void {
        // Multi-step wizard: Welcome → Pages → Settings → Done
        include WCB_DIR . 'admin/views/setup-wizard.php';
    }

    public function handle_step(): void {
        check_ajax_referer( 'wcb_wizard', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

        $step = sanitize_text_field( $_POST['step'] ?? '' );

        if ( 'create_pages' === $step ) {
            $pages = $this->create_required_pages();
            wp_send_json_success( $pages );
        }

        if ( 'sample_data' === $step && ! empty( $_POST['install_sample'] ) ) {
            $this->install_sample_data();
        }

        if ( 'complete' === $step ) {
            update_option( 'wcb_setup_complete', true );
            wp_send_json_success( [ 'redirect' => admin_url( 'admin.php?page=wp-career-board' ) ] );
        }

        wp_send_json_success();
    }

    private function create_required_pages(): array {
        $settings = get_option( 'wcb_settings', [] );
        $created  = [];

        $pages = [
            'employer_dashboard_page' => [
                'title'   => __( 'Employer Dashboard', 'wp-career-board' ),
                'content' => '<!-- wp:wp-career-board/employer-dashboard /-->',
            ],
            'candidate_dashboard_page' => [
                'title'   => __( 'Candidate Dashboard', 'wp-career-board' ),
                'content' => '<!-- wp:wp-career-board/candidate-dashboard /-->',
            ],
            'jobs_archive_page' => [
                'title'   => __( 'Find Jobs', 'wp-career-board' ),
                'content' => '<!-- wp:wp-career-board/job-search /--><!-- wp:wp-career-board/job-filters /--><!-- wp:wp-career-board/job-listings /-->',
            ],
            'post_job_page' => [
                'title'   => __( 'Post a Job', 'wp-career-board' ),
                'content' => '<!-- wp:wp-career-board/job-form /-->',
            ],
        ];

        foreach ( $pages as $setting_key => $page_data ) {
            if ( ! empty( $settings[ $setting_key ] ) ) continue; // already set

            $page_id = wp_insert_post( [
                'post_title'   => $page_data['title'],
                'post_content' => $page_data['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ] );

            if ( $page_id && ! is_wp_error( $page_id ) ) {
                $settings[ $setting_key ] = $page_id;
                $created[ $setting_key ] = $page_id;
            }
        }

        update_option( 'wcb_settings', $settings );
        return $created;
    }

    private function install_sample_data(): void {
        // Create sample categories
        wp_insert_term( 'Technology', 'wcb_category' );
        wp_insert_term( 'Marketing', 'wcb_category' );
        wp_insert_term( 'Design', 'wcb_category' );

        // Create sample job types
        foreach ( [ 'Full-time', 'Part-time', 'Contract', 'Freelance', 'Internship' ] as $type ) {
            wp_insert_term( $type, 'wcb_job_type' );
        }

        // Create sample experience levels
        foreach ( [ 'Entry Level', 'Mid Level', 'Senior', 'Lead', 'Executive' ] as $exp ) {
            wp_insert_term( $exp, 'wcb_experience' );
        }

        // Create a sample company and job
        $company_id = wp_insert_post( [
            'post_type'   => 'wcb_company',
            'post_title'  => 'Acme Corp',
            'post_status' => 'publish',
        ] );

        $job_id = wp_insert_post( [
            'post_type'    => 'wcb_job',
            'post_title'   => 'Senior PHP Developer',
            'post_content' => '<p>We are looking for an experienced PHP developer to join our growing team. You will work on exciting projects using modern PHP 8.x, WordPress, and REST APIs.</p>',
            'post_status'  => 'publish',
        ] );

        if ( $job_id ) {
            update_post_meta( $job_id, '_wcb_salary_min', 80000 );
            update_post_meta( $job_id, '_wcb_salary_max', 120000 );
            update_post_meta( $job_id, '_wcb_salary_currency', 'USD' );
            update_post_meta( $job_id, '_wcb_remote', '1' );
            update_post_meta( $job_id, '_wcb_deadline', date( 'Y-m-d', strtotime( '+60 days' ) ) );
            wp_set_object_terms( $job_id, [ 'Technology' ], 'wcb_category' );
            wp_set_object_terms( $job_id, [ 'Full-time' ], 'wcb_job_type' );
            wp_set_object_terms( $job_id, [ 'Senior' ], 'wcb_experience' );
        }

        update_option( 'wcb_sample_data_installed', true );
    }
}
```

Also add to `Install::activate()`:
```php
set_transient( 'wcb_activation_redirect', true, 30 );
```

- [ ] **Step 2: Create `admin/views/setup-wizard.php`**

A clean multi-step HTML wizard (no external dependencies):

```php
<?php defined('ABSPATH') || exit; ?>
<div class="wcb-wizard wrap">
    <div class="wcb-wizard-header">
        <h1><?php esc_html_e( 'Welcome to WP Career Board', 'wp-career-board' ); ?></h1>
        <p><?php esc_html_e( 'Let\'s get your job board set up in 2 minutes.', 'wp-career-board' ); ?></p>
    </div>

    <div class="wcb-wizard-steps" id="wcb-wizard-steps">
        <!-- Step 1: Create Pages -->
        <div class="wcb-wizard-step active" data-step="1">
            <h2><?php esc_html_e( 'Create Pages', 'wp-career-board' ); ?></h2>
            <p><?php esc_html_e( 'We\'ll create the required pages automatically:', 'wp-career-board' ); ?></p>
            <ul>
                <li><?php esc_html_e( 'Find Jobs (with search + filters + listings)', 'wp-career-board' ); ?></li>
                <li><?php esc_html_e( 'Post a Job', 'wp-career-board' ); ?></li>
                <li><?php esc_html_e( 'Employer Dashboard', 'wp-career-board' ); ?></li>
                <li><?php esc_html_e( 'Candidate Dashboard', 'wp-career-board' ); ?></li>
            </ul>
            <button class="button button-primary" id="wcb-create-pages">
                <?php esc_html_e( 'Create Pages & Continue', 'wp-career-board' ); ?>
            </button>
        </div>

        <!-- Step 2: Sample Data -->
        <div class="wcb-wizard-step" data-step="2">
            <h2><?php esc_html_e( 'Sample Data', 'wp-career-board' ); ?></h2>
            <p><?php esc_html_e( 'Install sample categories, job types, and a demo job to see how everything looks?', 'wp-career-board' ); ?></p>
            <label><input type="checkbox" id="wcb-install-sample" checked> <?php esc_html_e( 'Yes, install sample data', 'wp-career-board' ); ?></label>
            <br><br>
            <button class="button button-primary" id="wcb-finish-wizard">
                <?php esc_html_e( 'Finish Setup', 'wp-career-board' ); ?>
            </button>
        </div>
    </div>

    <script>
    jQuery(function($) {
        const nonce = '<?php echo esc_js( wp_create_nonce( 'wcb_wizard' ) ); ?>';

        $('#wcb-create-pages').on('click', function() {
            $.post(ajaxurl, { action: 'wcb_wizard_step', step: 'create_pages', nonce }, function(res) {
                if (res.success) {
                    $('.wcb-wizard-step[data-step="1"]').removeClass('active');
                    $('.wcb-wizard-step[data-step="2"]').addClass('active');
                }
            });
        });

        $('#wcb-finish-wizard').on('click', function() {
            const installSample = $('#wcb-install-sample').is(':checked') ? 1 : 0;
            $.post(ajaxurl, { action: 'wcb_wizard_step', step: 'sample_data', nonce, install_sample: installSample }, function() {
                $.post(ajaxurl, { action: 'wcb_wizard_step', step: 'complete', nonce }, function(res) {
                    if (res.success) window.location = res.data.redirect;
                });
            });
        });
    });
    </script>
</div>
```

- [ ] **Step 3: Test setup wizard**

1. Deactivate and reactivate the plugin.
2. Confirm redirect to wizard page.
3. Click "Create Pages & Continue" — verify 4 pages created in admin.
4. Check "Yes, install sample data" and click "Finish Setup".
5. Navigate to `/find-jobs/` — confirm job listings page loads with sample job.

- [ ] **Step 4: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add admin/ && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "feat: setup wizard — auto-creates pages, installs sample data, redirects on activation"
```

---
---

## Chunk 5: Gutenberg Blocks (Interactivity API)

All blocks follow the same pattern:
- `block.json` — metadata, attributes, viewScript, style
- `render.php` — PHP server-side render, seeds `wp_interactivity_state()`
- `view.js` — Interactivity API `store()` with actions + callbacks
- `style.css` — Block-scoped CSS (no external dependencies)

Build tool: `@wordpress/scripts` via `package.json` at plugin root.

### Task 14: Build Configuration

**Files:**
- Create: `package.json`
- Create: `webpack.config.js` (optional — @wordpress/scripts default handles most cases)

- [ ] **Step 1: Create `package.json`**

```json
{
  "name": "wp-career-board",
  "version": "0.1.0",
  "scripts": {
    "build": "wp-scripts build",
    "start": "wp-scripts start",
    "lint:js": "wp-scripts lint-js"
  },
  "devDependencies": {
    "@wordpress/scripts": "^30.0.0"
  }
}
```

- [ ] **Step 2: Install dependencies**

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" && npm install
```

- [ ] **Step 3: Verify `@wordpress/interactivity` is available**

```bash
node -e "require('@wordpress/interactivity'); console.log('OK')" 2>/dev/null || echo "Bundled via @wordpress/scripts — OK"
```

- [ ] **Step 4: Commit `package.json` + `package-lock.json`, add `node_modules` to `.gitignore`**

```bash
echo "node_modules/" >> "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board/.gitignore"
echo "build/" >> "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board/.gitignore"
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add package.json package-lock.json .gitignore && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "chore: add @wordpress/scripts build tooling"
```

---

### Task 15: Job Listings Block (`wcb/job-listings`)

**Files:**
- Create: `blocks/job-listings/block.json`
- Create: `blocks/job-listings/render.php`
- Create: `blocks/job-listings/view.js`
- Create: `blocks/job-listings/style.css`

- [ ] **Step 1: Create `blocks/job-listings/block.json`**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "wp-career-board/job-listings",
  "version": "0.1.0",
  "title": "Job Listings",
  "category": "widgets",
  "description": "Reactive job listings grid with infinite scroll and bookmark toggle.",
  "textdomain": "wp-career-board",
  "attributes": {
    "perPage": { "type": "integer", "default": 20 },
    "layout": { "type": "string", "default": "grid", "enum": ["grid", "list"] }
  },
  "viewScriptModule": "file:./view.js",
  "style": "file:./style.css",
  "render": "file:./render.php",
  "supports": { "interactivity": true }
}
```

- [ ] **Step 2: Create `blocks/job-listings/render.php`**

```php
<?php
$per_page = (int) ( $attributes['perPage'] ?? 20 );
$layout   = in_array( $attributes['layout'] ?? 'grid', [ 'grid', 'list' ], true ) ? $attributes['layout'] : 'grid';

$jobs = get_posts( [
    'post_type'      => 'wcb_job',
    'post_status'    => 'publish',
    'posts_per_page' => $per_page,
] );

$state = [
    'jobs'      => array_map( fn( $j ) => [
        'id'         => $j->ID,
        'title'      => $j->post_title,
        'permalink'  => get_permalink( $j->ID ),
        'company'    => get_post_meta( $j->ID, '_wcb_company_name', true ),
        'location'   => implode( ', ', wp_get_object_terms( $j->ID, 'wcb_location', [ 'fields' => 'names' ] ) ),
        'type'       => implode( ', ', wp_get_object_terms( $j->ID, 'wcb_job_type', [ 'fields' => 'names' ] ) ),
        'remote'     => '1' === get_post_meta( $j->ID, '_wcb_remote', true ),
        'salary_min' => get_post_meta( $j->ID, '_wcb_salary_min', true ),
        'salary_max' => get_post_meta( $j->ID, '_wcb_salary_max', true ),
        'bookmarked' => in_array( $j->ID, (array) get_user_meta( get_current_user_id(), '_wcb_bookmark', false ), true ),
    ], $jobs ),
    'page'      => 1,
    'layout'    => $layout,
    'loading'   => false,
    'hasMore'   => count( $jobs ) >= $per_page,
    'apiBase'   => rest_url( 'wcb/v1/jobs' ),
    'nonce'     => wp_create_nonce( 'wp_rest' ),
];

wp_interactivity_state( 'wcb-job-listings', $state );
?>
<div
    <?php echo get_block_wrapper_attributes(); ?>
    data-wp-interactive="wcb-job-listings"
>
    <!-- Layout toggle -->
    <div class="wcb-listings-header">
        <button data-wp-on--click="actions.setGrid" data-wp-class--active="state.isGrid"><?php esc_html_e( 'Grid', 'wp-career-board' ); ?></button>
        <button data-wp-on--click="actions.setList" data-wp-class--active="state.isList"><?php esc_html_e( 'List', 'wp-career-board' ); ?></button>
    </div>

    <!-- Jobs container -->
    <div class="wcb-jobs-container" data-wp-class--wcb-grid="state.isGrid" data-wp-class--wcb-list="state.isList">
        <template data-wp-each--job="state.jobs" data-wp-each-key="context.job.id">
            <article class="wcb-job-card" data-wp-context='{}'>
                <h3><a data-wp-bind--href="context.job.permalink" data-wp-text="context.job.title"></a></h3>
                <span class="wcb-job-meta" data-wp-text="context.job.location"></span>
                <span class="wcb-job-type" data-wp-text="context.job.type"></span>
                <button
                    class="wcb-bookmark"
                    data-wp-on--click="actions.toggleBookmark"
                    data-wp-class--bookmarked="context.job.bookmarked"
                    aria-label="<?php esc_attr_e( 'Bookmark job', 'wp-career-board' ); ?>"
                >☆</button>
            </article>
        </template>
    </div>

    <!-- Load more -->
    <div data-wp-bind--hidden="!state.hasMore">
        <button data-wp-on--click="actions.loadMore" data-wp-bind--disabled="state.loading">
            <span data-wp-bind--hidden="state.loading"><?php esc_html_e( 'Load more', 'wp-career-board' ); ?></span>
            <span data-wp-bind--hidden="!state.loading"><?php esc_html_e( 'Loading…', 'wp-career-board' ); ?></span>
        </button>
    </div>
</div>
```

- [ ] **Step 3: Create `blocks/job-listings/view.js`**

```js
import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'wcb-job-listings', {
    state: {
        get isGrid() { return getState().layout === 'grid'; },
        get isList() { return getState().layout === 'list'; },
    },
    actions: {
        setGrid() { getState().layout = 'grid'; },
        setList() { getState().layout = 'list'; },

        async loadMore() {
            const state = getState();
            if ( state.loading ) return;
            state.loading = true;
            state.page++;

            const url = new URL( state.apiBase );
            url.searchParams.set( 'page', state.page );
            url.searchParams.set( 'per_page', 20 );

            // Carry through current search params from URL
            const searchParams = new URLSearchParams( window.location.search );
            for ( const [ key, val ] of searchParams ) {
                url.searchParams.set( key, val );
            }

            const res = await fetch( url.toString() );
            const jobs = await res.json();

            state.jobs.push( ...jobs );
            state.hasMore = jobs.length === 20;
            state.loading = false;
        },

        async toggleBookmark() {
            const ctx   = getContext();
            const state = getState();
            const job   = ctx.job;

            const res = await fetch( `${ state.apiBase }/${ job.id }/bookmark`, {
                method: 'POST',
                headers: { 'X-WP-Nonce': state.nonce },
            } );
            const data = await res.json();
            job.bookmarked = data.bookmarked;
        },
    },
} );

function getState() {
    return wp.interactivity.getServerState( 'wcb-job-listings' );
}
```

- [ ] **Step 4: Create `blocks/job-listings/style.css`**

```css
.wcb-jobs-container.wcb-grid {
    display: grid;
    grid-template-columns: repeat( auto-fill, minmax( 280px, 1fr ) );
    gap: 1.5rem;
}
.wcb-jobs-container.wcb-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.wcb-job-card {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1.25rem;
    background: #fff;
    position: relative;
}
.wcb-bookmark {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: none;
    border: none;
    font-size: 1.25rem;
    cursor: pointer;
    color: #94a3b8;
}
.wcb-bookmark.bookmarked { color: #f59e0b; }
@media (max-width: 600px) {
    .wcb-jobs-container.wcb-grid { grid-template-columns: 1fr; }
}
```

- [ ] **Step 5: Build and test**

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" && npm run build
```

1. Navigate to a page with the Job Listings block.
2. Verify jobs render server-side (view source — jobs visible without JS).
3. Enable JS — verify grid/list toggle works without page reload.
4. Click bookmark — verify heart fills without page reload.
5. Click "Load more" — verify additional jobs appear.

- [ ] **Step 6: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add blocks/job-listings/ && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "feat: wcb/job-listings block (Interactivity API, grid/list toggle, bookmark, load more)"
```

---

### Task 16: Job Search + Filters Blocks

**Files:** `blocks/job-search/`, `blocks/job-filters/`

These two blocks share state via the `wcb-search` Interactivity API namespace so changes in either update the job listings.

- [ ] **Step 1: Create `blocks/job-search/block.json`**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "wp-career-board/job-search",
  "title": "Job Search",
  "textdomain": "wp-career-board",
  "viewScriptModule": "file:./view.js",
  "style": "file:./style.css",
  "render": "file:./render.php",
  "supports": { "interactivity": true }
}
```

- [ ] **Step 2: Create `blocks/job-search/render.php`**

```php
<?php
wp_interactivity_state( 'wcb-search', [
    'query'   => sanitize_text_field( $_GET['search'] ?? '' ),
    'filters' => [
        'category'   => sanitize_text_field( $_GET['category'] ?? '' ),
        'type'       => sanitize_text_field( $_GET['type'] ?? '' ),
        'location'   => sanitize_text_field( $_GET['location'] ?? '' ),
        'experience' => sanitize_text_field( $_GET['experience'] ?? '' ),
        'remote'     => ! empty( $_GET['remote'] ),
    ],
] );
?>
<div <?php echo get_block_wrapper_attributes(); ?> data-wp-interactive="wcb-search">
    <form role="search" data-wp-on--submit="actions.search">
        <label for="wcb-search-input" class="screen-reader-text"><?php esc_html_e( 'Search jobs', 'wp-career-board' ); ?></label>
        <input
            id="wcb-search-input"
            type="search"
            placeholder="<?php esc_attr_e( 'Job title, skills, company…', 'wp-career-board' ); ?>"
            data-wp-bind--value="state.query"
            data-wp-on--input="actions.updateQuery"
            autocomplete="off"
        >
        <button type="submit"><?php esc_html_e( 'Search', 'wp-career-board' ); ?></button>
    </form>
</div>
```

- [ ] **Step 3: Create `blocks/job-search/view.js`**

```js
import { store } from '@wordpress/interactivity';

// Shared search store — job-listings block listens to this namespace
store( 'wcb-search', {
    actions: {
        updateQuery( event ) {
            const state = wp.interactivity.getServerState( 'wcb-search' );
            state.query = event.target.value;
        },
        search( event ) {
            event.preventDefault();
            const state = wp.interactivity.getServerState( 'wcb-search' );
            // Update URL params (shareable filtered URL)
            const url = new URL( window.location.href );
            if ( state.query ) {
                url.searchParams.set( 'search', state.query );
            } else {
                url.searchParams.delete( 'search' );
            }
            Object.entries( state.filters ).forEach( ( [ key, val ] ) => {
                if ( val ) url.searchParams.set( key, String( val ) );
                else url.searchParams.delete( key );
            } );
            window.history.pushState( {}, '', url.toString() );

            // Trigger listings refresh
            document.dispatchEvent( new CustomEvent( 'wcb:search', { detail: { query: state.query, filters: state.filters } } ) );
        },
    },
} );
```

- [ ] **Step 4: Create `blocks/job-filters/block.json` + `render.php` + `view.js`**

`render.php` outputs taxonomy filter dropdowns (categories, job types, locations, experience levels) each bound to `state.filters.*` in the `wcb-search` namespace. Changes dispatch `wcb:search` event.

`view.js` updates `state.filters` on dropdown change and dispatches `wcb:search`. The `wcb/job-listings` block's `view.js` listens for `wcb:search` and calls `actions.refresh()`.

- [ ] **Step 5: Wire `wcb-search` event in job-listings view.js**

Add to `blocks/job-listings/view.js`:
```js
document.addEventListener( 'wcb:search', async ( event ) => {
    const state = getState();
    const { query, filters } = event.detail;
    // Reset to page 1 with new params
    state.page = 1;
    state.jobs = [];
    state.loading = true;

    const url = new URL( state.apiBase );
    url.searchParams.set( 'per_page', 20 );
    if ( query ) url.searchParams.set( 'search', query );
    Object.entries( filters ).forEach( ( [ key, val ] ) => {
        if ( val ) url.searchParams.set( key, String( val ) );
    } );

    const res  = await fetch( url.toString() );
    const jobs = await res.json();
    state.jobs   = jobs;
    state.hasMore = jobs.length === 20;
    state.loading = false;
} );
```

- [ ] **Step 6: Test search + filter flow**

1. Place Job Search + Job Filters + Job Listings blocks on the Find Jobs page.
2. Type "PHP" in search box, hit Enter — confirm listings update without page reload.
3. Select "Full-time" from job type dropdown — confirm listings filter instantly.
4. Check URL updates with `?search=PHP&type=full-time` — confirm URL is shareable.

- [ ] **Step 7: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add blocks/job-search/ blocks/job-filters/ && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "feat: wcb/job-search + wcb/job-filters blocks (Interactivity API, URL sync, shared state)"
```

---

### Task 17: Remaining Blocks (Job Single, Job Form, Dashboards)

Each follows the same pattern. Implement in this order:

**`wcb/job-single`** — render.php outputs full job detail. view.js handles the slide-in application panel (cover letter textarea + resume selector + submit button calling `POST /jobs/{id}/apply`). On submit success, replaces panel with confirmation message.

**`wcb/job-form`** — Multi-step form. Step 1: title + description. Step 2: details (salary, location, remote, deadline). Step 3: taxonomies (category, type, experience). Step 4: preview + submit. Calls `POST /wcb/v1/jobs`. Shows live credit cost preview (always 0 in Free). On success redirects to employer dashboard.

**`wcb/employer-dashboard`** — Tabbed interface. Tab: My Jobs (list with status badges, edit/duplicate/trash actions). Tab: Applications (per-job expandable list with status change dropdown). Tab: Company Profile (inline-editable form calling PATCH /employers/{id}). Tab: Settings (link to candidate dashboard, account).

**`wcb/candidate-dashboard`** — Tabbed interface. Tab: My Applications (list with job title, status, date). Tab: Saved Jobs (bookmarked jobs with unbookmark). Tab: My Profile (profile visibility toggle, bio). Tab: Resume (basic text resume stored in usermeta).

**`wcb/company-profile`** — Public company page. Shows logo, description, active jobs list. Jobs fetched from `GET /employers/{id}/jobs`.

**`wcb/featured-jobs`** — Static server-rendered block. Shows N featured jobs (jobs with `_wcb_featured = 1` meta). No Interactivity API needed — content is static.

- [ ] **Step 1: Implement `wcb/job-single`**

Follow the block.json → render.php → view.js → style.css pattern. render.php outputs full job detail HTML. view.js powers the slide-in apply panel.

- [ ] **Step 2: Implement `wcb/job-form`**

4-step form with Interactivity API step navigation. No page reloads between steps.

- [ ] **Step 3: Implement `wcb/employer-dashboard`**

Tabbed interface. All data fetched from REST API via Interactivity API store.

- [ ] **Step 4: Implement `wcb/candidate-dashboard`**

Tabbed interface. Applications, bookmarks, profile, resume sections.

- [ ] **Step 5: Implement `wcb/company-profile`**

Server-rendered with inline edit capability for authenticated owner.

- [ ] **Step 6: Implement `wcb/featured-jobs`**

Pure PHP server-side render. No Interactivity API.

- [ ] **Step 7: Build all blocks**

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" && npm run build
```

- [ ] **Step 8: Manual QA — full user journey**

**Employer journey:**
1. Register as employer → fill company profile (Company Profile block)
2. Post a job (Job Form block) → verify appears in listings
3. View applications on employer dashboard
4. Change application status to "reviewed"

**Candidate journey:**
1. Register as candidate
2. Browse jobs (Job Listings + Search + Filters)
3. Bookmark a job → verify appears in saved jobs tab
4. Apply to a job (Job Single apply panel)
5. Check My Applications on candidate dashboard

- [ ] **Step 9: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add blocks/ && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "feat: all Gutenberg blocks (job-single, job-form, employer-dashboard, candidate-dashboard, company-profile, featured-jobs)"
```

---
---

## Chunk 6: Integrations + Release

### Task 18: Reign Theme Integration

**Files:**
- Create: `integrations/reign/class-reign-integration.php`
- Create: `integrations/reign/templates/single-wcb_job.php`
- Create: `integrations/reign/templates/archive-wcb_job.php`

- [ ] **Step 1: Create Reign integration class**

```php
<?php
namespace WCB\Integrations\Reign;

class ReignIntegration {
    public function boot(): void {
        // Override templates with Reign-compatible versions
        add_filter( 'single_template',  [ $this, 'single_template' ] );
        add_filter( 'archive_template', [ $this, 'archive_template' ] );

        // Add WP Career Board to Reign Customizer
        add_action( 'customize_register', [ $this, 'customizer_section' ] );

        // Add Career Board panels to Reign left nav
        add_filter( 'reign_nav_items', [ $this, 'add_nav_items' ] );

        // Enqueue Reign-compatible styles
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
    }

    public function single_template( string $template ): string {
        if ( is_singular( 'wcb_job' ) ) {
            $reign_tpl = WCB_DIR . 'integrations/reign/templates/single-wcb_job.php';
            return file_exists( $reign_tpl ) ? $reign_tpl : $template;
        }
        return $template;
    }

    public function archive_template( string $template ): string {
        if ( is_post_type_archive( 'wcb_job' ) ) {
            $reign_tpl = WCB_DIR . 'integrations/reign/templates/archive-wcb_job.php';
            return file_exists( $reign_tpl ) ? $reign_tpl : $template;
        }
        return $template;
    }

    public function customizer_section( \WP_Customize_Manager $wp_customize ): void {
        $wp_customize->add_section( 'wcb_reign', [
            'title'    => __( 'WP Career Board', 'wp-career-board' ),
            'priority' => 200,
        ] );

        $wp_customize->add_setting( 'wcb_reign_primary_color', [
            'default'           => '#4f46e5',
            'sanitize_callback' => 'sanitize_hex_color',
        ] );
        $wp_customize->add_control( new \WP_Customize_Color_Control( $wp_customize, 'wcb_reign_primary_color', [
            'label'   => __( 'Primary Color', 'wp-career-board' ),
            'section' => 'wcb_reign',
        ] ) );
    }

    public function add_nav_items( array $items ): array {
        $settings = get_option( 'wcb_settings', [] );
        $items[] = [
            'label' => __( 'Browse Jobs', 'wp-career-board' ),
            'url'   => ! empty( $settings['jobs_archive_page'] ) ? get_permalink( $settings['jobs_archive_page'] ) : home_url( '/jobs/' ),
            'icon'  => 'dashicons-portfolio',
        ];
        if ( current_user_can( 'wcb_post_jobs' ) ) {
            $items[] = [
                'label' => __( 'Employer Dashboard', 'wp-career-board' ),
                'url'   => ! empty( $settings['employer_dashboard_page'] ) ? get_permalink( $settings['employer_dashboard_page'] ) : '#',
                'icon'  => 'dashicons-building',
            ];
        }
        if ( current_user_can( 'wcb_apply_jobs' ) ) {
            $items[] = [
                'label' => __( 'My Applications', 'wp-career-board' ),
                'url'   => ! empty( $settings['candidate_dashboard_page'] ) ? get_permalink( $settings['candidate_dashboard_page'] ) : '#',
                'icon'  => 'dashicons-id-alt',
            ];
        }
        return $items;
    }

    public function enqueue_styles(): void {
        if ( ! is_singular( 'wcb_job' ) && ! is_post_type_archive( 'wcb_job' ) ) return;
        wp_enqueue_style( 'wcb-reign-compat', WCB_URL . 'integrations/reign/assets/reign-compat.css', [ 'reign-style' ], WCB_VERSION );
    }
}
```

- [ ] **Step 2: Create Reign-compatible single job template**

`integrations/reign/templates/single-wcb_job.php`:
```php
<?php
get_header();
// Render the wcb/job-single block inside Reign's content area
echo do_blocks( '<!-- wp:wp-career-board/job-single /-->' );
get_footer();
```

- [ ] **Step 3: Test with Reign active**

1. Activate Reign theme on the local site.
2. View a single job page — verify it uses Reign header/footer.
3. Check wp-admin > Customize — verify "WP Career Board" section exists.
4. Verify Reign left nav shows "Browse Jobs" and role-appropriate dashboard links.

- [ ] **Step 4: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add integrations/reign/ && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "feat: Reign theme integration — templates, Customizer section, nav items"
```

---

### Task 19: BuddyX Pro Integration

**Files:**
- Create: `integrations/buddyx-pro/class-buddyx-pro-integration.php`
- Create: `integrations/buddyx-pro/templates/single-wcb_job.php`

- [ ] **Step 1: Create BuddyX Pro integration** (mirrors Reign pattern)

```php
<?php
namespace WCB\Integrations\BuddyxPro;

class BuddyxProIntegration {
    public function boot(): void {
        add_filter( 'single_template',  [ $this, 'single_template' ] );
        add_filter( 'archive_template', [ $this, 'archive_template' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
        // Add job-seeking status badge to BuddyX Pro member profiles
        add_action( 'buddyx_pro_after_member_name', [ $this, 'show_job_seeking_badge' ] );
    }

    public function single_template( string $template ): string {
        if ( is_singular( 'wcb_job' ) ) {
            $tpl = WCB_DIR . 'integrations/buddyx-pro/templates/single-wcb_job.php';
            return file_exists( $tpl ) ? $tpl : $template;
        }
        return $template;
    }

    public function archive_template( string $template ): string {
        if ( is_post_type_archive( 'wcb_job' ) ) {
            $tpl = WCB_DIR . 'integrations/buddyx-pro/templates/archive-wcb_job.php';
            return file_exists( $tpl ) ? $tpl : $template;
        }
        return $template;
    }

    public function show_job_seeking_badge( int $user_id ): void {
        $seeking = get_user_meta( $user_id, '_wcb_open_to_work', true );
        if ( $seeking ) {
            echo '<span class="wcb-open-badge">' . esc_html__( '#OpenToWork', 'wp-career-board' ) . '</span>';
        }
    }

    public function enqueue_styles(): void {
        if ( ! is_singular( 'wcb_job' ) && ! is_post_type_archive( 'wcb_job' ) ) return;
        wp_enqueue_style( 'wcb-buddyx-compat', WCB_URL . 'integrations/buddyx-pro/assets/buddyx-compat.css', [], WCB_VERSION );
    }
}
```

- [ ] **Step 2: Test with BuddyX Pro active**

Activate BuddyX Pro. Verify job pages use BuddyX Pro layout. Verify #OpenToWork badge appears on candidate profiles that have opted in.

- [ ] **Step 3: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add integrations/buddyx-pro/ && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "feat: BuddyX Pro theme integration — templates, #OpenToWork badge"
```

---

### Task 20: BuddyPress Integration

**Files:**
- Create: `integrations/buddypress/class-bp-integration.php`

- [ ] **Step 1: Create BuddyPress integration**

```php
<?php
namespace WCB\Integrations\Buddypress;

class BpIntegration {
    public function boot(): void {
        add_action( 'bp_init', [ $this, 'register_member_types' ] );
        add_action( 'wcb_job_created', [ $this, 'activity_job_posted' ], 10, 2 );
        add_action( 'wcb_application_submitted', [ $this, 'activity_applied' ], 10, 3 );
    }

    public function register_member_types(): void {
        bp_register_member_type( 'employer', [
            'labels' => [
                'name'          => __( 'Employers', 'wp-career-board' ),
                'singular_name' => __( 'Employer', 'wp-career-board' ),
            ],
        ] );

        bp_register_member_type( 'candidate', [
            'labels' => [
                'name'          => __( 'Candidates', 'wp-career-board' ),
                'singular_name' => __( 'Candidate', 'wp-career-board' ),
            ],
        ] );

        // Sync WCB role → BP member type on user save
        add_action( 'set_user_role', function ( int $user_id, string $role ) {
            if ( 'wcb_employer' === $role ) bp_set_member_type( $user_id, 'employer' );
            if ( 'wcb_candidate' === $role ) bp_set_member_type( $user_id, 'candidate' );
        }, 10, 2 );
    }

    public function activity_job_posted( int $job_id ): void {
        if ( ! function_exists( 'bp_activity_add' ) ) return;
        $job = get_post( $job_id );
        if ( 'publish' !== $job->post_status ) return;

        bp_activity_add( [
            'user_id'           => $job->post_author,
            'action'            => sprintf(
                __( '%s posted a new job: %s', 'wp-career-board' ),
                bp_core_get_userlink( $job->post_author ),
                '<a href="' . get_permalink( $job_id ) . '">' . esc_html( $job->post_title ) . '</a>'
            ),
            'component'         => 'wp-career-board',
            'type'              => 'wcb_job_posted',
            'item_id'           => $job_id,
            'hide_sitewide'     => false,
        ] );
    }

    public function activity_applied( int $app_id, int $job_id, int $candidate_id ): void {
        if ( ! function_exists( 'bp_activity_add' ) ) return;
        $job = get_post( $job_id );

        bp_activity_add( [
            'user_id'       => $candidate_id,
            'action'        => sprintf(
                __( '%s applied for: %s', 'wp-career-board' ),
                bp_core_get_userlink( $candidate_id ),
                '<a href="' . get_permalink( $job_id ) . '">' . esc_html( $job->post_title ) . '</a>'
            ),
            'component'     => 'wp-career-board',
            'type'          => 'wcb_application_submitted',
            'item_id'       => $app_id,
            'hide_sitewide' => false,
        ] );
    }
}
```

- [ ] **Step 2: Test with BuddyPress active**

1. Activate BuddyPress.
2. Register a user with `wcb_employer` role.
3. Verify BP member type is set to "employer" on that user's profile.
4. Post a job — verify activity appears in BP activity stream.
5. Apply to a job as candidate — verify BP activity appears.

- [ ] **Step 3: Commit**

```bash
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add integrations/buddypress/ && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "feat: BuddyPress integration — member types, activity stream"
```

---

### Task 21: Pre-Release QA Checklist

- [ ] **Functional QA**

| Test | Expected | Pass? |
|------|----------|-------|
| Employer registers + creates company | Company post created, linked to user | |
| Employer posts job (auto-publish ON) | Job published immediately | |
| Employer posts job (auto-publish OFF) | Job pending, admin notified by email | |
| Admin approves job | Job published, employer notified | |
| Admin rejects job | Job drafted, employer notified with reason | |
| Job auto-expires past deadline | Status changes to `wcb_expired` | |
| Candidate registers | `wcb_candidate` role assigned | |
| Candidate browses jobs | Listings render, search works | |
| Candidate bookmarks job | Bookmark toggled, appears in saved jobs | |
| Candidate applies | Application created, employer + candidate notified | |
| Duplicate application prevented | 409 error on second apply | |
| Employer views applications | Application list visible | |
| Employer changes application status | Status updated, candidate notified | |
| Admin exports candidate data | GDPR export includes applications | |
| Admin erases candidate data | Applications deleted, resume wiped | |
| `JobPosting` schema on job page | Valid LD+JSON in page source | |
| Reign theme activated | Job pages use Reign layout | |
| BuddyX Pro activated | Job pages use BuddyX Pro layout | |
| BuddyPress activated | Member types set, activity streams populated | |

- [ ] **Accessibility QA**

All interactive elements (buttons, form fields, links) must be keyboard-navigable. Run [axe browser extension](https://www.deque.com/axe/) on job listings page, single job page, employer dashboard, candidate dashboard. Resolve all Critical and Serious issues.

- [ ] **Performance spot-check**

On local: open Query Monitor while browsing the job listings page. Confirm fewer than 50 queries. No N+1 patterns (all postmeta fetched in one call per block).

- [ ] **Security spot-check**

- All REST write endpoints: verify 401 response when called without auth
- CSRF: admin AJAX handlers use `check_ajax_referer()`
- No output without escaping (`esc_html`, `esc_attr`, `esc_url` on all user-facing output)

- [ ] **Final release commit**

```bash
# Update version in wp-career-board.php from 0.1.0 to match release tag
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  add -A && \
git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  commit -m "release: WP Career Board Free v0.1.0 — Core Alpha"

git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  tag v0.1.0

git -C "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board" \
  push origin main --tags
```

---

## Progress Tracker

Use this table to track task completion. Update the Status column as you go.

| Task | Description | Status |
|------|-------------|--------|
| T1 | Plugin main file + autoloader | ⬜ Not started |
| T2 | DB install + user roles + Abilities API | ⬜ Not started |
| T3 | CPTs + taxonomies (incl. wcb_board) | ⬜ Not started |
| T4 | REST base controller | ⬜ Not started |
| T5 | Jobs REST endpoint | ⬜ Not started |
| T6 | Applications REST endpoint | ⬜ Not started |
| T7 | Employers + candidates + search endpoints | ⬜ Not started |
| T8 | Email notifications (wp_mail) | ⬜ Not started |
| T9 | Moderation queue | ⬜ Not started |
| T10 | SEO module | ⬜ Not started |
| T11 | GDPR module | ⬜ Not started |
| T12 | Admin menu + settings | ⬜ Not started |
| T13 | Setup wizard + sample data | ⬜ Not started |
| T14 | Build configuration (@wordpress/scripts) | ⬜ Not started |
| T15 | wcb/job-listings block | ⬜ Not started |
| T16 | wcb/job-search + wcb/job-filters blocks | ⬜ Not started |
| T17 | wcb/job-single + wcb/job-form + dashboards + company | ⬜ Not started |
| T18 | Reign integration | ⬜ Not started |
| T19 | BuddyX Pro integration | ⬜ Not started |
| T20 | BuddyPress integration | ⬜ Not started |
| T21 | Pre-release QA | ⬜ Not started |
