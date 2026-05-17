# WP-CLI Reference

WP Career Board ships **5 WP-CLI top-level commands** for
automation, migration, and scale testing.

```bash
wp wcb <command> <subcommand> [options]
```

## `wp wcb job`

Operate on `wcb_job` posts.

| Subcommand | Purpose |
|---|---|
| `wp wcb job list` | List published jobs with filters |
| `wp wcb job approve <id>` | Approve a pending job |
| `wp wcb job reject <id> --reason="..."` | Reject a job with a reason |
| `wp wcb job republish <id>` | Bring an expired job back live |
| `wp wcb job expire` | Run the expiry sweep manually (same as the daily cron) |
| `wp wcb job feature <id> --days=30` | Promote to featured for N days |

**Example - bulk reject:**

```bash
wp post list --post_type=wcb_job --post_status=pending --field=ID \
  | xargs -I{} wp wcb job reject {} --reason="Duplicate posting"
```

## `wp wcb application`

Operate on applications.

| Subcommand | Purpose |
|---|---|
| `wp wcb application list --candidate_id=<id>` | List a candidate's applications |
| `wp wcb application list --job_id=<id>` | List applications for a specific job |
| `wp wcb application status <id> --to=<status>` | Update an application's status |
| `wp wcb application withdraw <id>` | Withdraw an application (candidate or admin) |
| `wp wcb application export --job_id=<id>` | CSV-export applications for a job |

## `wp wcb migrate`

Move content in or out of Career Board.

| Subcommand | Purpose |
|---|---|
| `wp wcb migrate wpjm` | Import from WP Job Manager (Pro adds Job Manager Resume Manager import) |
| `wp wcb migrate csv --file=<path>` | Bulk-import jobs from CSV |
| `wp wcb migrate export --type=jobs` | Bulk-export to CSV or JSON |

## `wp wcb scale`

Production-readiness benchmarking. Per the team standard, every
plugin must define hot-path query budgets and time them against a
production-shape dataset.

| Subcommand | Purpose |
|---|---|
| `wp wcb scale seed` | Generate 10k users × 10 rows = 100k-row synthetic dataset |
| `wp wcb scale benchmark` | Time the hot-path queries; exit 1 if any query exceeds its budget |
| `wp wcb scale teardown` | Drop the synthetic rows |

Budgets are defined in `cli/class-scale-command.php` per query.
PK lookups ≤5ms, indexed scans ≤30ms, snapshot reads ≤20ms.

**Example - full benchmark cycle:**

```bash
wp wcb scale seed && wp wcb scale benchmark && wp wcb scale teardown
```

The scale gate runs as stage 5.1 of `composer ci`. The first time
you ship to production, run this against a clone of the production
DB sized to your actual customer load.

## `wp wcb` (top-level)

A few utility commands without a subcommand namespace:

| Command | Purpose |
|---|---|
| `wp wcb version` | Print the installed version (handy in CI scripts) |
| `wp wcb doctor` | Run a health check (page mappings, capabilities, cron schedule, debug-log diff) |

## Ability gating

WP-CLI runs as the system user (no `current_user_can` context).
By default, commands skip the ability checks; for production
sites you can map specific commands to abilities via the
`wcb_cli_abilities` filter:

```php
add_filter( 'wcb_cli_abilities', function ( $map ) {
    $map['wcb_job_reject'] = 'wcb/moderate-jobs';
    return $map;
});
```

When set, the CLI handler calls `wp_is_ability_granted()` against
the configured user (`--user=<login>` or the system root) before
proceeding.

## Adding your own command

Use the same base class the plugin uses:

```php
namespace MyAddon;

use WCB\Cli\Abstract_Cli_Command;

class My_Command extends Abstract_Cli_Command {
    protected $name = 'wcb my-thing';

    public function handle_run( $args, $assoc_args ) {
        \WP_CLI::log( "Hello from my command" );
    }
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    \WP_CLI::add_command( 'wcb my-thing', My_Command::class );
}
```

The base class gives you `--dry-run`, `--verbose`, structured
output (`--format=json|table|csv`), and the abilities-aware
permission helper.
