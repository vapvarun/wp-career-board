# CSV Import (Pro)

The CSV importer lets you bulk-import jobs from a CSV file. Imported jobs default to **Pending** status for editorial review, though you can set a different status per row.

> **Pro feature** - Requires the WP Career Board Pro plugin to be installed and active. Every Pro feature works as soon as the plugin is active; the license key only powers automatic updates, it never gates functionality.

## CSV Import

### Finding the Import Screen

Go to **Career Board -> Import** and look for the **CSV → Jobs** card (marked Pro). The card is added to the free plugin's Import page by Pro.

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
| `status` | `pending` (default), `publish`, or `draft`. Any other value falls back to `pending`. |
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

Add any field key from the **Field Builder** as a column header. The importer matches each non-standard column against the custom field keys defined in the Field Builder (the `wcb_field_definitions` table); recognized keys are stored on the imported job. Columns that do not match a known field key are ignored.

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

## Migrating from WP Job Manager

The recommended path for moving listings from WP Job Manager into WP Career Board is the **CSV import** described above: export your WPJM listings to CSV, map the columns to the WCB column names in this reference, and import.

> Note: a code-level WP Job Manager importer class exists in the plugin but is not currently exposed through any admin screen, REST route, or WP-CLI command, so it is not a self-service feature in this release. Use the CSV import for WPJM data.
