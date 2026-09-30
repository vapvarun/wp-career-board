# WP-CLI Reference

WP Career Board ships **5 WP-CLI command groups** for automation,
migration, and scale testing.

```bash
wp wcb <command> <subcommand> [options]
```

## `wp wcb job`

Operate on `wcb_job` posts.

| Subcommand | Purpose |
|---|---|
| `wp wcb job list [--status=<status>] [--company=<slug>] [--format=<format>]` | List jobs. `--status` is `publish`, `pending`, `draft`, `wcb_expired` or `any` (default). `--format` is `table`, `csv`, `json` or `ids` |
| `wp wcb job approve <id>` | Approve a pending job |
| `wp wcb job reject <id> [--reason="..."]` | Reject a job with an optional reason |
| `wp wcb job expire <id>` | Expire one job now, whatever its deadline, and fire `wcb_job_expired` |
| `wp wcb job run-expiry` | Run the expiry sweep now, the same one the hourly cron runs |

`run-expiry` only does something on sites where jobs end at their deadline. On a site that kept the pre-1.8.0 "past-deadline jobs keep listing" behaviour, it prints a warning and expires nothing until you click **End jobs at their deadline** under **Settings → Jobs**.

**Example - bulk reject:**

```bash
wp wcb job list --status=pending --format=ids \
  | xargs -n1 -I{} wp wcb job reject {} --reason="Duplicate posting"
```

## `wp wcb application`

Operate on applications.

| Subcommand | Purpose |
|---|---|
| `wp wcb application list [--job=<id>] [--status=<status>] [--per-page=<n>] [--page=<n>] [--format=<format>]` | List applications, newest first. `--per-page` is 1-500 (default 100). `--format` is `table`, `csv`, `json`, `ids` or `count` (a count needs no paging) |
| `wp wcb application update <id> --status=<status>` | Set an application's status: `submitted`, `reviewing`, `shortlisted`, `hired` or `rejected`. Goes through the one status writer, so the change is logged and the candidate is emailed once. Setting the same status changes nothing |

## `wp wcb migrate`

Import legacy job-board content into Career Board, and move files.

| Subcommand | Purpose |
|---|---|
| `wp wcb migrate wpjm [--dry-run] [--limit=<n>] [--offset=<n>] [--status=<status>]` | Import jobs from WP Job Manager, with company pages. `--status` is `publish`, `pending`, `expired` or `any`. Safe to re-run |
| `wp wcb migrate wpjm-applications [--dry-run]` | Import WP Job Manager Applications onto the imported jobs. No emails are sent. Run the jobs import first |
| `wp wcb migrate wpjm-resumes [--dry-run] [--limit=<n>] [--offset=<n>] [--status=<status>]` | Import resumes from WP Job Manager Resume Manager (needs Pro) |
| `wp wcb migrate files` | Move existing candidate files (resumes, generated CVs) into private storage now. The 1.8.0 upgrade does this in the background, 50 files per cron pass |

## `wp wcb scale`

Production-readiness benchmarking. Per the team standard, every
plugin must define hot-path query budgets and time them against a
production-shape dataset.

| Subcommand | Purpose |
|---|---|
| `wp wcb scale seed` | Generate a production-shape synthetic dataset (defaults: 10,000 candidates, 1,000 employers, 500 companies, 5,000 jobs; override per type with `--candidates`, `--employers`, etc.) |
| `wp wcb scale benchmark` | Time the named hot-path queries; exit 1 if any exceeds its budget |
| `wp wcb scale teardown` | Drop the synthetic rows (idempotent - flagged via usermeta, never touches genuine content) |

Per-query budgets are defined in `cli/class-scale-command.php`
(`BUDGETS_MS`), unchanged through 1.7.0: single-job read 5ms,
applications-for-a-job 50ms, companies/candidates list-50 50ms,
jobs list-50 100ms, location filter 150ms, keyword search 200ms.

**Example - full benchmark cycle:**

```bash
wp wcb scale seed && wp wcb scale benchmark && wp wcb scale teardown
```

The scale gate runs as stage 5.1 of `composer ci`. The first time
you ship to production, run this against a clone of the production
DB sized to your actual customer load.

## `wp wcb` (top-level)

Utility subcommands on the root `wcb` command:

| Command | Purpose |
|---|---|
| `wp wcb status` | Print content counts by status for jobs, companies, applications and resumes, and user totals |
| `wp wcb abilities` | List the registered Career Board abilities and whether a user is granted each (`--user-id=<id>`) |

## Ability gating

WP-CLI runs as the system user (no current-user context). The
`wp wcb abilities` command resolves a list of Career Board
capabilities against a target user (`--user-id=<id>`) so you can
audit what a role can do. The `wcb_cli_abilities` filter extends
the capability-to-label map that command reports on - it does not
auto-gate other subcommands:

```php
add_filter( 'wcb_cli_abilities', function ( $map ) {
    // Add your add-on's custom capability to the audit table.
    $map['my_addon_manage_things'] = 'Manage My Addon Things';
    return $map;
});
```

If your own subcommand needs to enforce a capability, call the
base class helper inside the method:

```php
$this->require_ability( 'wcb/moderate-jobs' );
```

## Adding your own command

Use the same base class the plugin uses,
`WCB\Cli\AbstractCliCommand`. Each public method becomes a
subcommand (the standard WP-CLI convention):

```php
namespace MyAddon;

use WCB\Cli\AbstractCliCommand;

class My_Command extends AbstractCliCommand {

    /**
     * ## EXAMPLES
     *
     *   wp wcb my-thing greet
     *
     * @param array<int,string>    $args       Positional args.
     * @param array<string,string> $assoc_args Flags.
     */
    public function greet( array $args, array $assoc_args ): void {
        // Optional ability gate (no-op when no user context is set).
        $this->require_ability( 'wcb/post-jobs' );

        \WP_CLI::success( 'Hello from my command' );
    }
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    \WP_CLI::add_command( 'wcb my-thing', My_Command::class );
}
```

The base class extends `\WP_CLI_Command` and adds two
abilities-aware helpers: `check_ability( $ability )` (returns a
bool) and `require_ability( $ability )` (halts the command if the
ability is not granted). See the `wcb_cli_abilities` filter below
to map subcommands to abilities.
