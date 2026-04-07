# Analytics Dashboard (Pro)

The Analytics module gives you a real-time snapshot of your job board's activity -- jobs, applications, users, views, and credit flow -- from a single dashboard screen.

> **Requires WP Career Board Pro** with a valid license key.

## What Is Tracked

| Metric | Description |
|--------|-------------|
| **Total Jobs** | Count of all published `wcb_job` posts |
| **Total Applications** | Count of all submitted applications |
| **Total Employers** | Number of users with the `wcb_employer` role |
| **Total Candidates** | Number of users with the `wcb_candidate` role |
| **Job Views (30 days)** | Page view events logged in the `wcb_job_views` table during the last 30 days |
| **Top 5 Jobs** | The five most-viewed jobs, with individual view counts |
| **Application Rate** | Average number of applications per published job |
| **Credits Issued** | Lifetime sum of all `topup` entries in the credit ledger |
| **Credits Spent** | Lifetime sum of all `deduction` entries in the credit ledger |

Job view tracking is provided by WP Career Board (free). All other metrics are computed directly from WordPress post counts, user roles, and the credit ledger table.

## Where to Find Analytics

Go to **Career Board -> Analytics** in your WordPress admin. The dashboard refreshes on each page load -- no manual refresh is required.

## CSV Export

You can export the full credit ledger as a CSV file for accounting or auditing purposes.

1. Go to **Career Board -> Analytics**
2. Click **Export Credit Ledger CSV**
3. A file named `wcb-credits-YYYY-MM-DD.csv` downloads immediately

### CSV Columns

| Column | Description |
|--------|-------------|
| ID | Ledger row ID |
| Employer | WordPress user ID of the employer |
| Amount | Credit amount (positive for top-ups, negative for deductions) |
| Type | Entry type: `topup`, `hold`, `deduct`, or `refund` |
| Job | Associated job post ID (if applicable) |
| Note | Human-readable note attached to the entry |
| Date | Timestamp the entry was created |

## Notes

- The credit ledger is append-only -- no entries are ever edited or deleted, so the export is a reliable audit trail
- Job view data requires the Free plugin's `wcb_job_views` table; if the table is absent, view metrics show 0
- Analytics data is not cached -- it queries the database on every page load
