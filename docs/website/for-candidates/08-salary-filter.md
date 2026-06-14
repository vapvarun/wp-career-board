# Salary Range Filter

The Find Jobs page (`/jobs/`) now ships a salary range slider so
candidates can narrow listings to roles paying within a specific
range - instead of scanning every salary line manually.

## Where it lives

On the Find Jobs page, in the filter panel below the search bar.
The slider appears alongside the existing filters (location, job
type, experience, category).

## How it works

- Drag the **lower handle** to set a minimum salary.
- Drag the **upper handle** to set a maximum salary.
- The active range shows as a chip pill above the listings -
  e.g. `$60k-$120k/yr ✕`. Click the ✕ to clear that filter.
- Active range updates the listings live (no page reload).

The slider's range adapts to the salary distribution of currently
listed jobs, so on a small board the slider tracks `$0-$100k` while
on a senior-only board it might track `$80k-$300k`.

## Periods

The slider respects whichever salary period each job is using -
yearly, monthly, or hourly. Jobs without salary data are filtered
out when the salary slider is active (set the slider to its widest
range to include them again).

## Currency

The slider currency follows the site's default currency
(set under **Career Board → Settings → Listings**, the "Default
currency" field). Multi-currency boards still display each job in
its own currency, but the filter applies the comparison after a
normalized conversion.

## REST equivalent

If you're driving listings programmatically:

```
GET /wp-json/wcb/v1/jobs?salary_min=60000&salary_max=120000
```

Both bounds are optional. Omit `salary_max` for "$60k+" listings;
omit `salary_min` for "up to $120k" listings.

## On mobile

The slider stacks below the search bar and uses a touch-friendly
grip. The chip pill remains tap-to-clear.
