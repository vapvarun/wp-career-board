# WP-CLI Reference

Use `wp wcb` to list and moderate jobs, change application statuses, import from WP Job Manager and benchmark a large site from the command line.

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

`run-expiry` only expires jobs on sites where jobs end at their deadline. If that is off, it prints a warning and expires nothing. Turn it on with the **End jobs at their deadline** button under **Settings > Jobs**, which appears only while the setting is off.

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
| `wp wcb application update <id> --status=<status>` | Set an application's status: `submitted`, `reviewing`, `shortlisted`, `hired` or `rejected`. The change is logged and `wcb_application_status_changed` fires once. Setting the same status, or changing an application that already has an outcome, prints a warning and changes nothing |

## `wp wcb migrate`

Import legacy job-board content into Career Board, and move files.

| Subcommand | Purpose |
|---|---|
| `wp wcb migrate wpjm [--dry-run] [--limit=<n>] [--offset=<n>] [--status=<status>]` | Import jobs from WP Job Manager, with company pages. `--status` is `publish`, `pending`, `expired` or `any`. Safe to re-run |
| `wp wcb migrate wpjm-applications [--dry-run]` | Import WP Job Manager Applications onto the imported jobs. No emails are sent. Run the jobs import first |
| `wp wcb migrate wpjm-resumes [--dry-run] [--limit=<n>] [--offset=<n>] [--status=<status>]` | Import resumes from WP Job Manager Resume Manager. That plugin must be active. `--status` is `publish`, `pending` or `any` (default `publish`) |
| `wp wcb migrate files` | Move existing candidate files (resumes, generated CVs) into private storage now. The plugin also does this in the background, 50 files per cron pass |

## `wp wcb scale`

Create a large synthetic dataset and time the main queries against a budget, to check how the plugin performs on a big site.

| Subcommand | Purpose |
|---|---|
| `wp wcb scale seed [--candidates=<n>] [--employers=<n>] [--companies=<n>] [--jobs=<n>] [--applications=<n>]` | Generate a synthetic dataset. Defaults: 10,000 candidates, 1,000 employers, 500 companies, 5,000 jobs, 50,000 applications. Running it again only creates what is missing |
| `wp wcb scale benchmark [--per-page=<n>] [--format=<format>]` | Time the main queries with a cold cache; exit 1 if any exceeds its budget. `--per-page` defaults to 50; `--format` is `table` (default), `json` or `csv` |
| `wp wcb scale teardown` | Delete the synthetic rows. Safe to run again |

Default budgets: single-job read 5 ms, applications for a job 50 ms, companies list and candidates list (50 rows) 50 ms each, jobs list (50 rows) 100 ms, location filter 150 ms, keyword search 200 ms. Change them with the `wcb_scale_budgets` filter.

**Example - full benchmark cycle:**

```bash
wp wcb scale seed && wp wcb scale benchmark && wp wcb scale teardown
```

Run it on a staging copy, not on a live site.

## `wp wcb` (top-level)

Utility subcommands on the root `wcb` command:

| Command | Purpose |
|---|---|
| `wp wcb status` | Print content counts by status for jobs, companies and applications (and resumes when Pro is active), plus employer and candidate totals |
| `wp wcb abilities [--user-id=<id>] [--format=<format>]` | List the Career Board abilities and whether a user is granted each. Without a user, the grant column shows n/a |

## Ability gating

WP-CLI runs without a current user unless you pass the global `--user=<id>`. Several subcommands check an ability and stop with "Permission denied" if the current user lacks it, so run them with `--user=<admin>`. The `wp wcb abilities` command reports what a user can do. The `wcb_cli_abilities` filter extends the list it reports on; it does not gate other subcommands:

```php
add_filter( 'wcb_cli_abilities', function ( $map ) {
    // Add your add-on's custom capability to the abilities list.
    $map['my_addon_manage_things'] = 'Manage My Addon Things';
    return $map;
});
```

If your own subcommand needs to enforce an ability, call the base class helper inside the method:

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
        // Stops the command with an error if the current user lacks the ability.
        $this->require_ability( 'wcb/post-jobs' );

        \WP_CLI::success( 'Hello from my command' );
    }
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    \WP_CLI::add_command( 'wcb my-thing', My_Command::class );
}
```

The base class extends `\WP_CLI_Command` and adds two abilities-aware helpers: `check_ability( $ability )` (returns a bool) and `require_ability( $ability )` (halts the command if the ability is not granted).
