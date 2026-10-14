---
id: job-description-shortcodes-trusted-authors-only
priority: high
personas: employer.figma, varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-10-13
needs: cli
bug_ref: 10355318941
---

# Job descriptions run shortcodes only for authors trusted with unfiltered HTML, and never recursively

**Why this journey exists:** the single-job block ran `do_shortcode()` on every description. An employer could put `[wcb_job_single jobId="<own id>"]` in it, which rendered the job page inside the job page until PHP ran out of memory, and any shortcode on the site was available to untrusted authors. Since 1.8.1 shortcodes run only when the AUTHOR can `unfiltered_html`, once per request (`$GLOBALS['wcb_rendering_job_desc']` guard); otherwise they are stripped.

## Steps

1. Add an mu-plugin `zz-probe.php`: `add_shortcode( 'wcb_probe', fn() => 'PROBE-RENDERED' );`. Set `WCB_SITE="$(wp option get home)"` first.
2. As `employer.figma` (employer role, no `unfiltered_html`), create a job whose description is `Intro [wcb_probe] outro` (`POST /wp-json/wcb/v1/jobs`), publish it, then `curl -s "$WCB_SITE/jobs/<slug>/"` → HTTP 200, the page contains `Intro` and `outro` but NOT `PROBE-RENDERED` and NOT the literal `[wcb_probe]` (`strip_shortcodes` removed it).
3. Same job but authored by `varundubey` (administrator, `unfiltered_html`) → the page contains `PROBE-RENDERED` exactly once.
4. Recursion guard, employer author: description `[wcb_job_single jobId="<its own id>"]` → the job page returns 200 quickly (no memory error in debug.log, `curl --max-time 20` completes), shortcode text absent.
5. Recursion guard, admin author: the same description on an admin-authored job → 200; the nested block renders at most once (the inner render sees `$GLOBALS['wcb_rendering_job_desc']` set and strips the shortcode), the response is finite and the request memory peak stays low (`WP_DEBUG` log has no `Allowed memory size` line).
6. Negative: an empty description still shows the fallback "No description has been provided for this role yet." (unchanged); description HTML that `wp_kses_post` allows (paragraphs, lists) still renders for both authors.
7. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

```bash
rm -f wp-content/mu-plugins/zz-probe.php
wp post delete <job ids> --force
```

## Notes

- `user_can( $job->post_author, 'unfiltered_html' )` is false for everyone on multisite except super admins and when `DISALLOW_UNFILTERED_HTML` is set - on such installs even admin-authored descriptions are stripped.
