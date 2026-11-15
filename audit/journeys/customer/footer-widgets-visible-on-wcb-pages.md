---
id: footer-widgets-visible-on-wcb-pages
priority: high
personas: admin
requires: mu:autologin, seed:jobs, pro:resume
last_verified: 2026-11-15
bug_ref: Basecamp 10511033319
---

# See the theme footer widgets on every Career Board page

**Why this journey exists:** the plugin's "hide the theme sidebar" rules matched `.widget-area`, which Reign, Astra and Kadence also put on their footer widget area, so a site owner's footer links vanished on every Career Board page.

## Steps

1. As `admin`, add any widget (a Paragraph block is enough) to the theme's footer widget area (Appearance > Widgets)
2. Open each of `/find-jobs/`, `/find-companies/`, a single job, a single company, a single resume (Pro) and the Home page → expect the footer widget text visible with a height above zero
3. On the same pages run `document.querySelectorAll('#secondary, aside.sidebar, .sidebar-area, #sidebar, .widget-area:not(.footer-widget-area)')` → expect none with a width above zero, so the sidebar is still suppressed
4. Resize to 390px → expect the footer widgets stack and there is no horizontal scroll
5. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp widget delete <the widget id added in step 1>
```

## Notes

Reign, Astra and Kadence use the class `footer-widget-area`; BuddyX uses other footer classes and was never affected. The sidebar rules live in `blocks/job-single/style.css`, `blocks/company-profile/style.css` and Pro `blocks/resume-single/style.css`.
