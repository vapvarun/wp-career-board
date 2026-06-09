---
id: credits-opt-in-free-posting
priority: critical
personas: employer.figma
requires: mu:autologin, pro:credits
last_verified: 2026-06-09
bug_ref: 9976885975
---

# Credits are opt-in — posting is free by default

**Why this journey exists:** credits are an OPTIONAL Pro feature. On the default Main Board an employer must be able to post a job for free (cost 0), and the front-door gate and the SDK hold must resolve the cost the same way. Guards Basecamp 9976885975 (default `credit_cost` was 1, so the gate blocked a 0-credit employer while the SDK hold read raw absent meta as 0 — inconsistent, and free posting was blocked out of the box).

## Steps

1. As `employer.figma` with a 0-credit balance, navigate to the employer dashboard Post-a-Job tab → expect HTTP 200, NO "purchase more credits" / "requires N credits" nag, the Next/Post Job buttons are enabled
2. Resolve the default-board cost: `BoardSettings::get(<main_board_id>)['credit_cost']` → expect `0` (credits off by default)
3. Front-door gate: `apply_filters('wcb_board_credit_cost', 0, <main_board_id>)` → expect `0` (no gate)
4. Post a job to the Main Board with 0 credits → expect success (job created, status `pending`/`publish`), and the employer credit balance is UNCHANGED (no hold, no deduction)
5. Enable monetization: set the board's `_wcb_board_settings` `credit_cost` to 3 → `BoardSettings::get` returns 3, the gate returns 3, AND the SDK `job_post` consumer cost callable returns 3 (gate and hold agree)
6. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post delete <test-job-id> --force
# restore the Main Board to free (delete the per-board settings written in step 5)
wp post meta delete <main_board_id> _wcb_board_settings
```

## Notes

- Both the gate (`wcb_board_credit_cost` filter) and the SDK consumer cost callable resolve via `BoardSettings::get()` (merges defaults), so a board with no saved settings reads cost 0 from both — free, consistent.
- Find the Main Board id: `wp post list --post_type=wcb_board --fields=ID --posts_per_page=1`.
