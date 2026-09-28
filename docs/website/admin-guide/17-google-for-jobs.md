# Google for Jobs and Social Sharing

WP Career Board adds the details Google needs to show your open jobs in
Google for Jobs, and the tags social networks use when a job or company
is shared. Everything is under **Career Board → Settings → Jobs →
Search engines and sharing**.

## What is added

- **Job pages** get JobPosting markup: title, description, date posted,
  closing date, employment type, company (name, website, logo), job
  location with country, salary with its unit (per hour, month or year),
  and whether candidates apply on your site. Remote jobs are marked as
  remote, with the country applicants may live in.
- **Company pages** get Organization markup: name, website, logo,
  tagline and headquarters.
- A job that has ended (past its deadline or closed) keeps its page but
  is no longer offered to Google as an open job.
- **Social sharing tags** (Open Graph and Twitter card) use the job's
  image, or the company logo when the job has none.

## Settings

| Setting | Default | What it does |
|---|---|---|
| Google for Jobs | On | Adds the job and company markup. It stays on with Yoast SEO or Rank Math, which do not add job markup. Turn off only if another plugin already adds JobPosting. |
| Require a location | On for new sites, off for sites created before 1.8.0 | The job form asks for a location unless the job is marked Remote. Google leaves out jobs with no location unless they are remote. Older jobs without a location can still be edited. |
| Default country | The country of your site language (for example US for English (United States)) | Used in job addresses and as the country remote applicants may live in. A country name or 2-letter code both work. |
| Social sharing tags | On | Adds sharing tags. Skipped automatically when Yoast SEO or Rank Math is active, since they add their own. |

## Boards with jobs in several countries

Open **Career Board → Job Locations**, edit a location, and fill in
**Country**. Jobs in that location use it; jobs in locations without a
country use the default country.

## Getting the most from Google for Jobs

- Give each company a logo and website on its company profile.
- Add a salary where you can; Google shows it on the listing.
- Check a job with Google's Rich Results Test
  (search.google.com/test/rich-results) after changing these settings.

## For developers

Filter `wcb_job_posting_schema( $schema, $job, $data )` changes the
JobPosting for a job, or returns an empty array to leave it out. The
Country on a location is term meta `_wcb_country`, readable and
writable over the REST API.
