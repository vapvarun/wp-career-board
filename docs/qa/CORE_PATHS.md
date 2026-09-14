# WP Career Board — Core Paths (the 60–70%)

> **What nearly every owner uses, ranked.** QA walks these first, every cycle,
> as the named role, on a clean install. Bug priority follows this list (see
> `owner-questions.md` → Triage): a defect on a core path outranks one on an edge
> regardless of who reported it.
>
> **Seeded from evidence, confirmed by a human once.** Evidence: what activation
> creates, what the settings screen shows first, what the readme leads with, what
> the Basecamp ledger shows people report on, and the free/pro split (free is the
> core by definition). Re-confirm at each major release; the ledger will tell you
> when the ranking has drifted (`C-4`).

**Last confirmed: NOT YET CONFIRMED — seeded 2026-09-15 from the evidence below.**
The ranking is a proposal until the plugin owner signs it off; the skill is
explicit that this file is human-confirmed once, not generated.

## Evidence used

| Source | What it says |
|---|---|
| Setup wizard / Create Missing Pages | Provisions exactly six pages: Find Jobs, Post a Job, Employer Dashboard, Candidate Dashboard, Find Companies, Employer Registration. That set IS the product's own claim about what a site needs. |
| readme.txt description | Leads with "Employer dashboards, candidate applications, company profiles … and full applicant tracking — free." |
| Free/Pro split | Free is the core by definition. Resumes, alerts, credits, AI, kanban and maps are Pro and therefore edge for this file. |
| audit/journeys/customer/ | The member-facing journeys already written — apply-to-job, employer-post-job, candidate-register, employer-applicants-list — are the flows the team already treats as load-bearing. |
| Basecamp ledger | The defects that reached customers in 1.7.x clustered on the job archive, the apply drawer, the employer dashboard applications list and the employer login redirect. |

| # | Flow (owner's words) | Role | Surface | Why it is core (evidence) | Journey | Free/Pro |
|---|---|---|---|---|---|---|
| 1 | "Someone finds a job on my site" | anonymous | `/find-jobs/` → job single | Provisioned page; the archive is what the plugin exists to render. Every other flow starts here. | `customer/job-*` | Free |
| 2 | "Someone applies for it" | candidate + guest | Apply drawer on job single | The conversion path. Guest apply works without an account, so it is two walks, not one. | `customer/apply-to-job.md` | Free |
| 3 | "An employer posts a job" | employer | `/post-a-job/` | Provisioned page; without it the archive is empty. Multi-step form is the default; the simple form ships but is never provisioned. | `customer/employer-post-job.md` | Free |
| 4 | "An employer reads their applicants" | employer | `/employer-dashboard/` → Applications | Provisioned page and the readme's headline claim ("full applicant tracking"). Two 1.7.x customer defects landed here. | `customer/employer-applicants-list.md` | Free |
| 5 | "An employer moves someone through hiring" | employer | Employer dashboard → status control | The other half of applicant tracking. Status changes fire candidate email. | `customer/employer-application-status-change.md` | Free |
| 6 | "A candidate checks where their application got to" | candidate | `/candidate-dashboard/` → My Applications | Provisioned page; the member-side mirror of 4. | `customer/candidate-view-applications.md` | Free |
| 7 | "Someone signs up as an employer or a candidate" | anonymous | `/employer-registration/` | Provisioned page; the dual role-picker is the only way in without wp-admin. Gates everything above. | `customer/candidate-register.md` | Free |
| 8 | "The owner reviews and approves a job" | admin | Career Board → Jobs | Default is moderated publishing (`auto_publish_jobs` OFF), so this is on the critical path for a default install, not an option. | `admin/admin-jobs-*` | Free |
| 9 | "Someone browses companies" | anonymous | `/find-companies/` → company single | Provisioned page. Ranked below the job flows because a site works without anyone opening it. | `admin/admin-companies-*` | Free |
| 10 | "The owner sets the plugin up at all" | admin | Setup wizard → Settings | Runs once, but everything above depends on it creating the six pages correctly. | `admin/admin-*-page-renders` | Free |

## Edge (walk after core — welcome findings, never the starting point)

| Flow | Role | Surface | Why it is edge |
|---|---|---|---|
| Resume builder, resume directory, saved resumes | candidate / anonymous | `/find-candidates/`, resume blocks | Pro. A Free site never sees it. |
| Job alerts (instant / daily / weekly) | candidate | Candidate dashboard → Job Alerts | Pro. |
| Credits, checkout, credit balance | employer | Credit blocks, Settings → Credits | Pro, and only on sites that monetise posting. |
| Application kanban, pipeline stages | employer | Kanban block, Settings → Boards | Pro. |
| AI ranking, AI cover letter, AI search | employer / candidate | AI blocks + routes | Pro, and off until a provider key is set. |
| Job / resume maps, radius filter | anonymous | Map blocks | Pro, and needs a map provider key. |
| Mobile app sign-in and app-config | candidate / employer | `/auth/app-password`, `/settings/app-config` | Free, but only exercised by sites running the companion app. |
| CSV import, WPJM migration | admin | Settings → Import | Runs once on sites migrating in. |
| GDPR export / erase, account deletion | candidate | Candidate dashboard → Settings | Legally important, rarely exercised. Verify on release, not every cycle. |
| BuddyPress / Reign / BuddyX integrations | any | Theme-specific templates | Only on sites running those themes — but see the note below. |

## Zero-config check (C-2)

The first thing a new owner would try, with **no settings touched**:

**Activate → run the setup wizard → post a job as an employer → find it on
/find-jobs/ → apply to it as an anonymous visitor.**

That must complete on a fresh activate with nothing configured. It crosses paths
1, 2, 3 and 10, and it is the walk that catches provisioning defects — two of
which shipped in 1.7.x (a Companies page whose slug collided with the CPT
archive, and a Find Jobs page carrying two competing filter bars).

## Notes for whoever confirms this

1. **Rank 8 is the one to argue about.** Admin job approval is listed as core
   because `auto_publish_jobs` defaults OFF, so a default install queues every
   job for review. If most real sites turn it on, it drops to edge.
2. **Theme integrations look like edge and behave like core.** This plugin is
   bundled with Reign and BuddyX, so for a large share of installs the theme
   template IS the job page. A Reign-only defect reached customers in 1.7.1.
   Consider walking core paths 1 and 2 on a bundled theme as well as a generic one.
3. **Guest apply is a separate walk from member apply**, not a variant. It has
   its own claim-on-register path (`wcb_guest_applications_claimed`).
