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

WP Career Board enriches WordPress core's standard job feed: the core
template provides `<title>`, `<link>`, `<description>`, `<pubDate>`,
and `<guid>`, and the plugin injects the `wcb:`-namespaced fields
below into each `<item>`. The plain-text `<description>` is also
prefixed with a one-line "company - location - salary" summary so
readers that ignore custom namespaces still get useful context.

| Field | Source |
|---|---|
| `<wcb:company>` | Company name |
| `<wcb:salary currency="..." period="...">` | Salary wrapper; carries `currency` and `period` attributes and `<wcb:min>` / `<wcb:max>` children |
| `<wcb:min>` | Minimum salary (numeric, no formatting), inside `<wcb:salary>` |
| `<wcb:max>` | Maximum salary (numeric, no formatting), inside `<wcb:salary>` |
| `<wcb:location>` | Job location term(s) |
| `<wcb:type>` | Job type term(s) - Full-time, Part-time, Contract, etc. |
| `<wcb:category>` | Job category term(s) |
| `<wcb:tag>` | Job tag term(s) |
| `<wcb:experience>` | Experience-level term(s) |
| `<wcb:deadline>` | Application deadline - if set |
| `<wcb:apply_url>` | Direct apply URL, if the employer set an external one |
| `<wcb:apply_email>` | Direct apply email, if set |
| `<wcb:remote>` | `true` or `false` |

Multi-value fields (location, type, category, tag, experience) emit
one element per term rather than a comma-joined string.

The `wcb:` namespace (`https://wbcomdesigns.com/xmlns/wcb/1.0/`) is
declared on the `<rss>` root so RSS readers that support custom
namespaces (most modern ones) can surface these fields.

## Filtering the feed

The `/jobs/feed/` route is the standard WordPress CPT feed enriched
with the `wcb:` fields above; it does not add custom filter
parameters of its own. For a filtered feed, use a taxonomy archive
feed, which WordPress generates automatically from the registered
WCB taxonomy query vars:

```
/jobs/feed/?wcb_category=engineering    # one category
/jobs/feed/?wcb_job_type=full-time      # one job type
/jobs/feed/?wcb_location=remote         # one location term
```

For richer filtering (salary range, remote flag, board, combined
filters), use the REST endpoint described under
[Cache + performance](#cache--performance) below, which accepts the
full job-query surface.

## Use cases

- **Cross-post to a Slack channel** via Zapier or RSS-to-Slack
  integration when new jobs are published.
- **Job aggregator listings** - many aggregators accept RSS as their
  ingestion format (Indeed, ZipRecruiter, Google Jobs feeds).
- **Email digest** - feed an RSS-to-email tool (Mailchimp,
  ConvertKit) to send weekly digests.
- **Static site builder** - feed Astro / Next.js / Hugo with the
  feed during build to render a careers page on a marketing site.
- **IFTTT / make.com** - trigger downstream automations on new jobs.

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
