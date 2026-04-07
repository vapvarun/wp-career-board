# Job Feed / XML Syndication (Pro)

The Job Feed publishes all your live jobs as an XML feed at a fixed URL. Submit this URL to Indeed, LinkedIn, and other job aggregators to automatically syndicate your listings.

> **Requires WP Career Board Pro** with a valid license key.

## Feed URL

```
https://yoursite.com/wcb-jobs.xml
```

The feed is disabled by default. Enable it in **Career Board -> Settings -> Job Feed**.

## Feed Format

The feed uses the Indeed XML format, which is also accepted by Glassdoor, LinkedIn, and most other major job aggregators. Each `<job>` entry contains:

| Field | Source |
|-------|--------|
| `<title>` | Job post title |
| `<date>` | Publication date |
| `<referencenumber>` | WordPress post ID |
| `<url>` | Public permalink |
| `<company>` | Company name meta field |
| `<city>` | First term from `wcb_location` taxonomy |
| `<description>` | Job description (HTML stripped) |
| `<salary>` | Formatted min-max range, e.g. `$80,000 - $120,000 / yearly` |
| `<jobtype>` | First term from `wcb_job_type` taxonomy |
| `<email>` | Contact email from feed settings |
| `<expirationdate>` | Deadline meta field |

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

The feed returns up to **200 jobs per page** (the maximum Indeed recommends). If you have more than 200 published jobs, append a `start` parameter to retrieve additional pages:

```
https://yoursite.com/wcb-jobs.xml?start=0    <- jobs 1-200
https://yoursite.com/wcb-jobs.xml?start=200  <- jobs 201-400
https://yoursite.com/wcb-jobs.xml?start=400  <- jobs 401-600
```

## Caching

The feed is cached for one hour using WordPress transients. When any job is saved or updated, the cache is immediately invalidated -- the next request builds a fresh feed.

## Disabling the Feed

Toggle **Enable Feed** off. The URL returns a 404 response instead of XML. Aggregators that poll the URL will stop receiving new listings.
