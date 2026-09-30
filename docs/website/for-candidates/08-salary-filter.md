# Salary Range Filter

The Find Jobs page (`/find-jobs/`) has a salary filter so candidates can narrow listings to roles that pay within a range.

## Where it lives

On the Find Jobs page, in the filter panel, under **Salary**. It has a **Minimum** and a **Maximum** slider and a **Reset** link.

## How it works

- Move **Minimum** to set the lowest pay you want.
- Move **Maximum** to set the highest. The right end means "Any", so no upper limit.
- The active range shows as a pill above the listings. Click the pill's ✕ to clear it.
- The listings update without a page reload.

A job matches when its pay range overlaps yours: its top figure reaches your minimum and its bottom figure is under your maximum. Jobs with no salary are left out while a salary filter is active. Clear the filter to see them again.

## Periods and currency

The filter compares pay per year, the same way the **Highest salary** sort does: hourly pay counts as 2,080 hours a year and monthly pay as 12 months. A job paying 40 per hour (about 83,000 a year) matches a minimum of 80,000; a job paying 3,000 a month (36,000 a year) does not.

The slider labels use the site's default currency, set under **Career Board → Settings → Jobs → Default Salary Currency**. While a salary filter is on, only jobs paid in that currency match, because the plugin has no exchange rates to convert with. Each job card still shows the job's own currency.

Job alerts that include a salary range use the same rules, so an alert sends the same jobs the saved search showed.
