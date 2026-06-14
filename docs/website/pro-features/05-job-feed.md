# Job Feed / XML Syndication (Pro)

The Job Feed publishes all your live jobs as an XML feed at a fixed URL. Submit this URL to Indeed, Glassdoor, LinkedIn, and other job aggregators to automatically syndicate your listings.

> **Pro feature** - Requires the WP Career Board Pro plugin to be installed and active. Every Pro feature works as soon as the plugin is active; the license key only powers automatic updates, it never gates functionality.

## Feed URL

```
https://yoursite.com/wcb-jobs.xml
```

The feed is disabled by default. Enable it in **Career Board -> Settings -> Job Feed**.

## Feed Format

The feed uses the Indeed XML format, which is also accepted by Glassdoor, LinkedIn, and most other major job aggregators. The document opens with a `<source>` element carrying `<publisher>` (your site name) and `<publisherurl>` (your site URL), followed by one `<job>` entry per listing.

Each `<job>` entry contains:

| Field | Source |
|-------|--------|
| `<title>` | Job post title |
| `<date>` | Publication date (RFC-822 GMT) |
| `<referencenumber>` | WordPress post ID |
| `<url>` | Public permalink |
| `<company>` | `_wcb_company_name` meta |
| `<city>` | First term from the `wcb_location` taxonomy |
| `<country>` | Currently emitted empty |
| `<description>` | Job description (HTML stripped, wrapped in CDATA) |
| `<salary>` | Formatted min-max range, e.g. `$80,000 - $120,000 / yearly` |
| `<jobtype>` | First term from the `wcb_job_type` taxonomy |
| `<email>` | Contact email from feed settings |
| `<expirationdate>` | `_wcb_deadline` meta |

The salary uses the job's own currency symbol from the plugin currency catalog (USD, EUR, GBP, CAD, AUD, INR, SGD), so a EUR or INR job is not exported with a hardcoded dollar sign.

## Setup

### Step 1: Enable the Feed

1. Go to **Career Board -> Settings -> Job Feed**
2. Toggle **Enable Feed** on
3. The feed URL appears immediately below the toggle

### Step 2: Set the Contact Email

Enter the email address to include in the `<email>` field of every job entry. This is the address aggregators and candidates use to contact you about listings. It defaults to the WordPress admin email.

### Step 3: Submit to Indeed

1. Log in to the [Indeed Employer Portal](https://employers.indeed.com)
2. Go to **Integrations -> Job Feed**
3. Enter your feed URL: `https://yoursite.com/wcb-jobs.xml`
4. Indeed re-fetches the feed every 24 hours

## Pagination

The feed returns up to **200 jobs per page**. If you have more than 200 published jobs, append a `start` parameter to retrieve additional pages:

```
https://yoursite.com/wcb-jobs.xml?start=0    <- jobs 1-200
https://yoursite.com/wcb-jobs.xml?start=200  <- jobs 201-400
https://yoursite.com/wcb-jobs.xml?start=400  <- jobs 401-600
```

## Caching

Each feed page is cached for one hour using WordPress transients. The cache key includes a version number stored in the `wcbp_feed_version` option, and that version is bumped every time a job is saved. The next request after a save therefore reads a fresh feed (its key no longer matches the old cached copy), and stale per-page caches expire naturally within the hour. The feed also sends a `Cache-Control: public, max-age=3600` header for CDN edge caching.

## Disabling the Feed

Toggle **Enable Feed** off. The URL returns a 404 response instead of XML. Aggregators that poll the URL will stop receiving new listings.
