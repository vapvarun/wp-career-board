# Migration and CSV Import (Pro)

The Migration module lets you bulk-import jobs from a CSV file and migrate existing listings from WP Job Manager. All imports land as **Pending** posts for editorial review before going live.

> **Requires WP Career Board Pro** with a valid license key.

## CSV Import

### Finding the Import Screen

Go to **Career Board -> Import** and look for the **CSV -> Jobs** card (marked Pro).

### Download the Sample File

Click **Download Sample CSV** to get a correctly structured template with two example rows. Use it as a starting point for your data.

### CSV Column Reference

#### Required

| Column | Description |
|--------|-------------|
| `title` | Job title -- the only required column |

#### Content

| Column | Description |
|--------|-------------|
| `description` | Full job description (HTML allowed) |
| `status` | `pending` (default), `publish`, or `draft` |
| `deadline` | Application deadline -- any parseable date format, stored as `YYYY-MM-DD` |

#### Salary

| Column | Accepted Values |
|--------|----------------|
| `salary_min` | Integer (e.g. `80000`) |
| `salary_max` | Integer (e.g. `120000`) |
| `salary_currency` | `USD`, `EUR`, `GBP`, `CAD`, `AUD`, `INR`, `SGD` |
| `salary_type` | `yearly`, `monthly`, or `hourly` |

If `salary_min` is greater than `salary_max`, the importer swaps them automatically and records a warning.

#### Flags

| Column | Accepted Values |
|--------|----------------|
| `remote` | `yes`, `no`, `1`, or `0` |
| `featured` | `yes`, `no`, `1`, or `0` |

#### Company

| Column | Description |
|--------|-------------|
| `company` | Company name text |
| `company_id` | WordPress post ID of an existing company post |

#### Application

| Column | Description |
|--------|-------------|
| `apply_url` | External application URL |
| `apply_email` | Application contact email |

#### Taxonomies

Separate multiple values with commas or pipes. Terms are created automatically if they do not exist.

| Column | Taxonomy |
|--------|----------|
| `categories` | Job category |
| `job_types` | Job type (e.g. `Full-time|Part-time`) |
| `locations` | Location |
| `experience` | Experience level |
| `tags` | Job tags |

#### Geo and Board

| Column | Description |
|--------|-------------|
| `lat` | Latitude (decimal) |
| `lng` | Longitude (decimal) |
| `board_id` | WordPress post ID of the target job board (Multi-Board) |

#### Custom Fields

Add any field key from the **Field Builder** as a column header. Values are mapped to the corresponding custom field on each imported job.

### Running the Import

1. Select your CSV file using the file picker
2. Click **Import**
3. A results summary shows how many jobs were imported, skipped, and whether any warnings occurred
4. Go to **Career Board -> Jobs** and review the Pending listings before publishing

### Error Handling

| Condition | Result |
|-----------|--------|
| File not found or unreadable | Fatal error -- no rows processed |
| Missing `title` column | Fatal error -- no rows processed |
| Row has wrong column count | Row skipped, error logged in summary |
| Empty title on a row | Row skipped, error logged in summary |
| Invalid currency or salary type | Field skipped for that row |
| Invalid date | Field skipped for that row |

## WP Job Manager Migration

If you are switching from WP Job Manager, the WPJM importer copies all `job_listing` posts to `wcb_job` posts, preserving title, content, author, and publish date.

### What Gets Migrated

| WPJM Field | WCB Field |
|-----------|-----------|
| `_job_location` | Location text |
| `_job_salary` | Salary text |
| `_company_name` | Company name |
| `_job_expires` | Deadline |
| `_remote_position` | Remote flag |
| `job_listing_type` terms | `wcb_job_type` taxonomy |

### How to Run the WPJM Import

The WPJM importer is triggered via the REST API. Go to **Career Board -> Import** and use the **WP Job Manager** card, or call the endpoint directly:

```
POST /wp-json/wcb/v1/import/wpjm
```

The importer is safe to run multiple times. Jobs already migrated are marked with `_wcb_imported_from_wpjm` meta and are skipped on subsequent runs.
