# WP Career Board 1.7.0 — Pre-release Gate Findings

Record for the two 1.7.0 release-gate cards. Both resolved.

## Gate A — Big-site by-job perf (`applications.list_for_job`)

**Card:** 10109888056. Benchmark budget was blown (109ms vs 50ms at 50k applications) because by-job application queries compared `_wcb_job_id` with `type => 'NUMERIC'`, which emits `CAST(meta_value AS UNSIGNED)` and makes the `wp_postmeta.wcb_meta_key_value (meta_key(191), meta_value(20))` composite index ineligible — MySQL falls back to the `meta_key`-only prefix and scans every `_wcb_job_id` row.

`_wcb_job_id` is written as a string via `update_post_meta`, so a plain string equality is exact and index-served.

**Fixed (string compare, `NUMERIC` removed):**
- `api/endpoints/class-jobs-endpoint.php` — appCount `IN` query (production).
- `cli/class-application-commands.php` — `wp wcb application list --job=`.
- `cli/class-scale-command.php` — the `applications.list_for_job` benchmark op (was measuring a query pattern production never runs; now mirrors the real endpoint).

Production `api/endpoints/class-applications-endpoint.php` and `class-jobs-endpoint.php:1211` already used string compare — no change needed. The employer raw-SQL joins (`class-employers-endpoint.php:867,909`) resolve the job by its PK (`job.ID = CAST(...)`) via the postmeta `(post_id, meta_key)` path, not the `(meta_key, meta_value)` index, and are within budget — left as-is.

**Proof (EXPLAIN, any data volume):**
| Form | `key` | `ref` | Meaning |
|---|---|---|---|
| `CAST(meta_value AS UNSIGNED) = 16` | `meta_key` | `const` | only meta_key prefix, filters rest with `Using where` |
| `meta_value = '16'` | `wcb_meta_key_value` | `const,const` | full composite seek (both columns) |

`wp wcb scale benchmark --user=1` → all 7 ops within budget (`applications.list_for_job` OK).

## Gate B — WPCS/PHPStan zero-error + PHP 8.4 `fputcsv()`

**Card:** 10109887756. `composer phpcs` + `composer phpstan` must be clean before tag; plus a PHP 8.4+ deprecation on every CSV export.

**PHP 8.4 `fputcsv()` — explicit `$escape` added at all 6 sites** (`,`, `'"'`, `''` — empty escape = RFC-4180-clean, Excel-safe):
- FREE `admin/class-admin-applications.php` (header + row).
- PRO `api/endpoints/class-analytics-endpoint.php` (header + row).
- PRO `modules/migration/class-csv-importer.php` (header + row).

**PHPCS errors fixed:**
- FREE `modules/applications/widgets/class-cover-letter.php` — `phpcs:ignore` for the intentional `the_content` core-filter invocation.
- FREE `api/endpoints/class-members-endpoint.php` — array spacing (phpcbf).
- PRO `admin/class-pro-admin.php` — array spacing (phpcbf) + relocated an `EscapeOutput` ignore the reflow had detached from its `echo`.
- PRO `api/endpoints/class-analytics-endpoint.php` — hoisted `count()` out of the `do/while` condition.
- PRO `api/endpoints/class-pipeline-endpoint.php` — added `wcb_` to PRO `phpcs.xml` allowed prefixes (Pro extends Free's shared `wcb/` namespace and invokes Free-owned hooks like `wcb_job_board_id`).

**PHPStan error fixed:**
- FREE `api/endpoints/class-employers-endpoint.php` — `get_my_applications()` return type narrowed to `WP_REST_Response` (never returns `WP_Error`).

**Result:** FREE + PRO both report 0 phpcs errors, 0 phpstan errors.
