# Jobs RSS Feed

You can give RSS readers, Zapier, IFTTT, email digest tools and your own scripts a feed of your published jobs, with each job's company, salary, location and deadline included.

## Where to find it

```
https://your-site.com/jobs/feed/
```

The feed lists your most recent published jobs. WordPress decides how many, using **Settings → Reading → Syndication feeds show the most recent**.

## Item fields

The feed is WordPress's standard feed for the Jobs post type. WP Career Board adds fields in its own `wcb:` namespace to each `<item>`. The plain-text `<description>` starts with a one-line summary of company, location and salary, so readers that ignore custom namespaces still show useful context.

| Field | Content |
|---|---|
| `<wcb:reference>` | The job's ID |
| `<wcb:posted>` | Date the job was posted |
| `<wcb:company>` | Company name |
| `<wcb:salary currency="..." period="...">` | Salary, with `<wcb:min>` and `<wcb:max>` inside. Sent only when a minimum or maximum is set |
| `<wcb:location>` | Job location, one element per location |
| `<wcb:type>` | Job type, one element per type |
| `<wcb:category>` | Job category, one element per category |
| `<wcb:tag>` | Job tag, one element per tag |
| `<wcb:experience>` | Experience level, one element per level |
| `<wcb:deadline>` | Application deadline, if set |
| `<wcb:apply_url>` | Apply URL, if the employer set an external one |
| `<wcb:apply_email>` | Apply email, if set |
| `<wcb:remote>` | `true` or `false` |

The `wcb:` namespace is `https://wbcomdesigns.com/xmlns/wcb/1.0/`. It is declared on the `<rss>` root element.

## Filter the feed

You can narrow the feed to one term by adding its slug to the URL:

```
/jobs/feed/?wcb_category=engineering
/jobs/feed/?wcb_job_type=full-time
/jobs/feed/?wcb_location=san-francisco-ca
```

Use the term's slug, which you can see on its taxonomy screen. The feed has no filters for salary, remote or board.

## Get JSON instead

For richer filtering, or JSON, use the REST API:

```
GET /wp-json/wcb/v1/jobs?per_page=20
```

It returns the jobs with the total and the number of pages.
