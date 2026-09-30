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

The filter compares the numbers employers entered, without converting between yearly, monthly and hourly pay or between currencies. A job listed at 25 per hour is not compared as a yearly figure. The **Highest salary** sort does convert pay to a yearly amount, so use it to compare jobs with different pay periods.

The slider labels use the site's default currency, set under **Career Board → Settings → Jobs → Default Salary Currency**. Each job card still shows the job's own currency.
