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
| `wp wcb job list` | List jobs, with filters such as `--status=pending` |
| `wp wcb job approve <id>` | Approve a pending job |
| `wp wcb job reject <id> --reason="..."` | Reject a job with a reason |
| `wp wcb job expire [<id>]` | Run the expiry sweep manually (same as the daily cron); pass an ID to expire one job |
| `wp wcb job run-expiry` | Run the scheduled expiry cron callback directly |

**Example - bulk reject:**

```bash
wp post list --post_type=wcb_job --post_status=pending --field=ID \
  | xargs -I{} wp wcb job reject {} --reason="Duplicate posting"
```

## `wp wcb application`

Operate on applications.

| Subcommand | Purpose |
|---|---|
| `wp wcb application list` | List applications (filter with `--candidate_id=<id>` or `--job_id=<id>`) |
| `wp wcb application update <id> --to=<status>` | Update an application's status (fires `wcb_application_status_changed`) |

## `wp wcb migrate`

Import legacy job-board content into Career Board.

| Subcommand | Purpose |
|---|---|
| `wp wcb migrate wpjm` | Import jobs from WP Job Manager |
| `wp wcb migrate wpjm-resumes` | Import resumes from WP Job Manager Resume Manager (Pro features consume the imported resumes) |

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
| `wp wcb status` | Print a health summary (page mappings, capabilities, cron schedule, version) |
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
