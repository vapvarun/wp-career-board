---
id: employer-applicant-list-after-ai-rank
priority: medium
personas: employer
requires: mu:autologin, pro:active, seed:applications
last_verified: 2026-11-20
bug_ref: 10522249007
needs: cli
---

# Applicant names stay readable after "Rank by AI fit"

**Why this journey exists:** Basecamp 10522249007. In the 280px applicant list the AI score badge, status badge and unread dot did not shrink, so after ranking added the score the text column fell to about 24px and names and dates wrapped one letter per line. Rows now wrap the badges under the text (`.wcb-applicant-row` flex-wrap, text column floor 9rem in `blocks/employer-dashboard/styles/views.css`).

## Steps

1. Seed one job with 6+ applicants with long names. Without an AI provider: set `wcbp_ai_completion_provider` to `ollama` (restore afterwards) and give each application `_wcbp_ai_fit_score`, `_wcbp_ai_fit_reason`, `_wcbp_ai_summary` and `_wcbp_ai_scored_at` (cached scores, no outbound call).
2. As the job's employer open `/employer-dashboard/?job_apps=<job>` in List layout and click **Rank by AI fit**.
3. Measure each visible `.wcb-applicant-row .wcb-app-info` → width ≥ 140px at 1440 and 390 (was ~24px at 1440); names are one line with an ellipsis, dates on one line; the score and status badges sit on their own line under the text.
4. Switch to Board layout → cards still show name, score and Move-to select.
5. Repeat at 1024, light and dark (`html[data-bx-mode="dark"]`), and once in an RTL locale (badges wrap to the right edge).

## Teardown

Delete the seeded job and applications by their captured IDs; restore `wcbp_ai_completion_provider`.
