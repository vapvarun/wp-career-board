# Analytics Dashboard (Pro)

The Analytics module gives you a snapshot of your job board's activity -- jobs, applications, users, views, and credit flow -- from a single dashboard screen.

> **Pro feature** - Requires the WP Career Board Pro plugin to be installed and active. Every Pro feature works as soon as the plugin is active; the license key only powers automatic updates, it never gates functionality.

## What Is Tracked

| Metric | Description |
|--------|-------------|
| **Total Jobs** | Count of published `wcb_job` posts |
| **Total Applications** | Count of published `wcb_application` posts |
| **Total Employers** | Number of users with the `wcb_employer` role |
| **Total Candidates** | Number of users with the `wcb_candidate` role |
| **Job Views (30 days)** | Page view events logged in the `wcb_job_views` table during the last 30 days |
| **Top 5 Jobs** | The five most-viewed jobs, with individual view counts |
| **Application Rate** | Average number of applications per published job |
| **Credits Issued** | Lifetime sum of all `topup` entries in the credit ledger |
| **Credits Spent** | Lifetime sum (absolute value) of all `deduction` entries in the credit ledger |

Job view tracking is provided by WP Career Board (free). All other metrics are computed directly from WordPress post counts, user roles, and the credit ledger table.

## Where to Find Analytics

Go to **Career Board -> Analytics** in your WordPress admin.

## CSV Export

You can export the full credit ledger as a CSV file for accounting or auditing purposes. The export is served by a REST endpoint (`GET /wp-json/wcb/v1/analytics/credits.csv`) gated on the credit-management ability.

1. Go to **Career Board -> Analytics**
2. Click the credit ledger export control
3. A file named `wcb-credits-YYYY-MM-DD.csv` downloads immediately

### CSV Columns

| Column | Description |
|--------|-------------|
| ID | Ledger row ID |
| Employer | WordPress user ID associated with the entry |
| Amount | Credit amount (positive for top-ups, negative for deductions) |
| Type | Entry type: `topup`, `hold`, `deduction`, or `refund` |
| Job | Associated item ID, normally the job post ID (0 if not applicable) |
| Note | Human-readable note attached to the entry |
| Date | Timestamp the entry was created |

Rows are exported newest-first (ordered by creation date, descending).

## Notes

- The credit ledger is append-only -- no entries are ever edited or deleted, so the export is a reliable audit trail
- Job view data requires the free plugin's `wcb_job_views` table; if the table is absent, view metrics return 0
- The stats are cached in a short-lived (5 minute) transient. The cache is cleared immediately whenever credits are topped up or consumed, so credit totals stay current
