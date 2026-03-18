# WP Career Board — WP-CLI Reference

All commands run from the WordPress root directory (where `wp-config.php` lives).

---

## Demo Data (eval-file)

Demo data is managed via `wp eval-file` — not through the `wp wcb` command group.

### Insert demo data (Free + Pro)

```bash
wp eval-file wp-content/plugins/wp-career-board/tests/fixtures/seed-data.php
```

Creates: 3 employer users, 5 candidate users, 5 companies, 17 jobs (15 published + 2 pending),
13 applications, taxonomy terms. Pro: also creates 5 resume posts if `wp-career-board-pro` is active.

Safe to run multiple times — skips records that already exist.

### Remove all demo data

```bash
wp eval-file wp-content/plugins/wp-career-board/tests/fixtures/cleanup-seed-data.php
```

Permanently deletes all content created by the seed script.
Does **not** touch admin users or any content outside the seed list.

---

## Operational Commands

### `wp wcb status`

Show content counts and user totals.

```bash
wp wcb status
```

Output:

```
+-------------------+-----------+---------+-------+---------+-------+
| Type              | Published | Pending | Draft | Expired | Trash |
+-------------------+-----------+---------+-------+---------+-------+
| Jobs              | 15        | 2       | 0     | 0       | 0     |
| Companies         | 5         | 0       | 0     | 0       | 0     |
| Applications      | 13        | 0       | 0     | 0       | 0     |
| Resumes (Pro)     | 5         | 0       | 0     | 0       | 0     |
+-------------------+-----------+---------+-------+---------+-------+

Users:
  Employers  (wcb_employer):  3
  Candidates (wcb_candidate): 5
```

---

### `wp wcb job list`

List job listings with optional filters.

```bash
# All jobs (any status)
wp wcb job list

# Filter by status
wp wcb job list --status=pending
wp wcb job list --status=publish
wp wcb job list --status=wcb_expired

# Filter by company slug
wp wcb job list --company=stripe

# Export as JSON
wp wcb job list --format=json

# Get IDs only
wp wcb job list --status=pending --format=ids
```

**Options:**

| Flag | Description | Default |
|------|-------------|---------|
| `--status` | `publish`, `pending`, `draft`, `wcb_expired`, or `any` | `any` |
| `--company` | Company post slug | — |
| `--format` | `table`, `csv`, `json`, `ids` | `table` |

---

### `wp wcb job approve <id>`

Approve a pending job — publishes it and fires `wcb_job_approved` (sends employer notification email).

```bash
wp wcb job approve 42
```

---

### `wp wcb job reject <id>`

Reject a pending job — sets it to draft, stores the rejection reason, fires `wcb_job_rejected` (sends employer notification email).

```bash
wp wcb job reject 42
wp wcb job reject 42 --reason="Duplicate listing"
```

**Options:**

| Flag | Description |
|------|-------------|
| `--reason` | Rejection reason text sent to the employer |

---

### `wp wcb job expire <id>`

Force-expire a single job regardless of its deadline value. Fires `wcb_job_expired`.

```bash
wp wcb job expire 42
```

---

### `wp wcb job run-expiry`

Trigger the daily expiry cron manually. Expires all published jobs whose `_wcb_deadline` has passed.
Respects the **Deadline auto-close** setting in WP Career Board → Settings.

```bash
wp wcb job run-expiry
```

> **Tip:** If `deadline_auto_close` is disabled in settings this command is a no-op.
> Enable it under **WP Career Board → Settings → Job Listings**.

---

### `wp wcb application list`

List job applications with optional filters.

```bash
# All applications
wp wcb application list

# By job ID
wp wcb application list --job=42

# By status
wp wcb application list --status=shortlisted

# Combined + JSON output
wp wcb application list --job=42 --status=reviewing --format=json
```

**Options:**

| Flag | Description | Default |
|------|-------------|---------|
| `--job` | Job post ID | — |
| `--status` | `submitted`, `reviewing`, `shortlisted`, `hired`, `rejected` | — |
| `--format` | `table`, `csv`, `json`, `ids` | `table` |

---

### `wp wcb application update <id> --status=<status>`

Update an application's status. Fires `wcb_application_status_changed` (sends applicant notification email).

```bash
wp wcb application update 7 --status=reviewing
wp wcb application update 7 --status=shortlisted
wp wcb application update 7 --status=hired
wp wcb application update 7 --status=rejected
```

**Valid statuses:** `submitted` → `reviewing` → `shortlisted` → `hired` / `rejected`
