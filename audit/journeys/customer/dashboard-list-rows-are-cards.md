---
id: dashboard-list-rows-are-cards
priority: medium
personas: daniel.okafor, kenji.watanabe
requires: mu:autologin, seed:jobs, seed:companies, seed:resumes, pro:optional
last_verified: 2026-11-20
---

# Every dashboard list row is a card, like My Jobs

**Why this journey exists:** My Jobs showed each job as a bordered card while My Applications, Saved jobs, Saved companies, Saved resumes, My Resumes and Job alerts were flat rows split by hairlines, Settings sections had no frame, and an opened status history ran straight into the next application. One shared rule in `assets/css/wcb-ui.css` now paints every row the same.

## Steps

At 1440 and 390, light and dark (`html[data-bx-mode="dark"]`), and once with `dir="rtl"`:

1. As `daniel.okafor`, open `/candidate-dashboard/#applications`. Each `.wcb-cd-app-row` has a 1px `--wcb-border` border, `--wcb-radius-md` corners and an 8px gap to the next row.
2. Click "Status history" on the first row. The `.wcb-cd-app-timeline` opens inside that card with 8px/12px padding and a tinted background, spans the card's full width at 390, and the next card starts below the first card's border.
3. Open `#bookmarks` and `#saved-companies`: every `.wcb-cd-bookmark-row` is the same card. Open `#resumes`: every `.wcb-resume-card` is a card. Open `#alerts`: the New alert form is a card with a 16px gap above the first `.wcb-alert-row` card.
4. Open `#settings`: Account, Change Password, Email Notifications and Export my data are each a bordered card; Delete my account keeps its danger frame.
5. As `kenji.watanabe`, open `/employer-dashboard/#jobs`, `#saved-resumes` and `#settings`: the same card look on rows and settings sections.
6. As a member with nothing saved or applied, each tab shows its empty state with no stray card frame.
7. No view scrolls sideways (`document.documentElement.scrollWidth <= innerWidth`).

## Pass criteria

- Every list row on both dashboards reports the same `border`, `border-radius` and row gap as `.wcb-job-row`.
- An open status history never touches the next application.
