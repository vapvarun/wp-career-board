# Jobs RSS Feed

The plugin exposes a rich RSS feed at `/jobs/feed/` containing every
published job with full metadata. Wire it up to RSS readers, IFTTT,
Zapier, partner aggregators, or your own automation.

## Where to find it

```
https://your-site.com/jobs/feed/
```

The URL respects whatever post-type slug you've configured (defaults
to `jobs`). If you've changed the slug, the feed URL changes with it.

## Item schema

Each `<item>` in the feed contains:

| Field | Source |
|---|---|
| `<title>` | Job title |
| `<link>` | Job permalink |
| `<description>` | Full job description (HTML stripped to plain text + 500-char excerpt) |
| `<pubDate>` | Job publish date |
| `<guid>` | Job permalink (stable, isPermaLink="true") |
| `<wcb:company>` | Company name |
| `<wcb:salary_min>` | Minimum salary (numeric, no formatting) |
| `<wcb:salary_max>` | Maximum salary |
| `<wcb:salary_currency>` | ISO 4217 code (USD, EUR, GBP, etc.) |
| `<wcb:salary_period>` | `yearly` / `monthly` / `hourly` |
| `<wcb:location>` | Job location string |
| `<wcb:job_type>` | Full-time / Part-time / Contract / etc. |
| `<wcb:experience>` | Entry / Mid / Senior / Lead / Executive |
| `<wcb:category>` | Category slug + display name |
| `<wcb:tags>` | Comma-separated tag slugs |
| `<wcb:deadline>` | Application deadline (ISO 8601) — if set |
| `<wcb:apply_url>` | Direct apply URL (uses external URL if employer set one, otherwise the job permalink) |
| `<wcb:remote>` | `1` or `0` |

The `wcb:` namespace is declared on the `<rss>` root so RSS readers
that support custom namespaces (most modern ones) can surface these
fields.

## Filtering the feed

Feed URL accepts query string parameters that mirror the REST API:

```
/jobs/feed/?board_id=42                 # only board 42's jobs
/jobs/feed/?category=engineering        # category slug
/jobs/feed/?type=full-time              # job type slug
/jobs/feed/?remote=1                    # remote-only
/jobs/feed/?salary_min=80000            # $80k+ minimum
```

Combine multiple filters with `&`.

## Use cases

- **Cross-post to a Slack channel** via Zapier or RSS-to-Slack
  integration when new jobs are published.
- **Job aggregator listings** — many aggregators accept RSS as their
  ingestion format (Indeed, ZipRecruiter, Google Jobs feeds).
- **Email digest** — feed an RSS-to-email tool (Mailchimp,
  ConvertKit) to send weekly digests.
- **Static site builder** — feed Astro / Next.js / Hugo with the
  feed during build to render a careers page on a marketing site.
- **IFTTT / make.com** — trigger downstream automations on new jobs.

## Cache + performance

The feed respects WordPress's standard feed caching. WP_DEBUG-disabled
production sites cache the feed output for ~5 minutes by default.
Tools like WP Super Cache and W3 Total Cache also cache feeds.

If you need a real-time feed for a specific integration, hit the
REST endpoint directly:

```
GET /wp-json/wcb/v1/jobs?per_page=20&orderby=date&order=DESC
```

The REST API returns JSON with the same data but without RSS-format
overhead.
