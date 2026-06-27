---
feature: block wp-career-board/job-form (multi-step) + job-form-simple
roles: employer, admin
surface: frontend block + REST (POST /jobs, PATCH /jobs/{id})
last_walked: 2026-06-26
---

# Post a job — full browser walkthrough

**What it is:** The employer job-posting form — a 4-step wizard (`job-form`) and a single-page variant (`job-form-simple`), both submitting to the same `POST /wcb/v1/jobs` endpoint.
**Where it lives:** `/post-a-job/` (the `job-form` block), and the employer dashboard **Post a Job** tab at `/employer-dashboard/` (which `do_blocks()` the same wizard).

## As anonymous / logged-in non-employer
1. Navigate to `/post-a-job/` logged out → expect the gate: "Please sign in as an employer to post a job." + a **Sign In** button (no form rendered).
2. Log in as a plain subscriber → the gate shows "Posting a job is for employers…" with a **Register as an employer** link (only when an employer-registration page is configured). The form is gated by `wp_is_ability_granted( 'wcb/post-jobs' )`.

## As employer
1. `?autologin=wcb_demo_employer` → `/post-a-job/`. The 4-step indicator renders: **Basics → Details → Categories → Preview**, step 1 active with `aria-current="step"`.
2. **Step 1 (Basics):** Board picker shows only on multi-board (Pro) sites. Leave Job Title blank → click **Next: Details** → expect the validation banner (`role="alert"`): "Job title is required before you can continue." Fill title, leave the rich-text description empty → Next → "Job description is required before you can continue."
3. Fill title + description (TinyMCE-style inline editor) → Next advances; the step pip flips to `wcb-step--done`.
4. **Step 2 (Details):** currency / min / max / per-period salary row; Remote checkbox; **Application Deadline is read-only** (auto-filled from the `wcb_job_default_expiry_days` policy — `tabindex="-1"`, with the "Contact your site admin to extend" hint); Apply URL; Apply Email.
5. **Step 3 (Categories):** Category, Job Type, **Location**, Experience, Skills/Tags. The Location dropdown is scoped by `Locations::get_dropdown_terms()` → the employer sees only **their company HQ term + Remote + Other**, plus "Other (enter manually)…" which reveals a free-text input (sent as `location_custom`). (Admins get the full taxonomy.)
6. **Step 4 (Preview & Submit):** the preview card mirrors the entered values. Click **Post Job** → spinner ("Posting…"), then the success block with a check icon: "Job posted successfully!" + **View your job listing →** and **Post another job** (success auto-resets after 8s). If the board moderates posts, it reads "Job submitted for review. You'll be notified once it's approved." instead, and no public link is shown.
7. **Edit / republish:** open `/post-a-job/?edit=<jobId>` (or the dashboard **Edit** link). The form pre-populates from the post; the submit button reads **Update Job** and PATCHes `/jobs/{id}`. Editing a job you do not own (not author, not same company, not admin) → "You are not authorized to edit this job." A rejected listing re-submitted from My Jobs goes back to **pending**, not straight live.
8. **Simple variant** (`job-form-simple`, e.g. a sidebar embed): all fields on one page across 4 sections (About the role / Classification / Compensation & schedule / How candidates apply). No edit mode by design; same POST, same honeypot; success shows "Job posted" + **View your job**.

## As admin
1. `?autologin=1` → wp-admin → **Career Board → Jobs**. A just-posted job appears with its status (publish, or **pending** when the board moderates). Cross-check moderation in `admin-jobs.md` / `moderation.md`.

## Themes & states
- Reign, BuddyX light, **BuddyX dark** at 1440px + 390px. Step indicator, validation banner, and success block stay readable in dark mode.
- Edge: insufficient credits (Pro) blocks submit with a credit message; honeypot (`wcb_hp`) filled → fake success, no post created.

## Contracts guarded
- REST↔JS: submit body keys (`title`, `description`, `salary_*`, `location_custom`, `board_id`, `custom_fields`, `hp`) match the `JobsEndpoint` create/update schema; `rest_cookie_invalid_nonce` surfaces the session-expired string.
- Security: form gated on `wcb/post-jobs`; edit gated on author/company/admin; deadline is server-controlled (read-only input).
- a11y: required fields marked, `role="alert"` validation banner, `aria-current` step, 40px tap targets.
- Location scoping: non-admin employers never see the full location taxonomy — only HQ + Remote + Other.
