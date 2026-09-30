---
id: candidate-files-are-private
priority: critical
personas: marcus.williams, employer.figma, employer.stripe
requires: mu:autologin
last_verified: 2026-09-27
bug_ref: 10340676643, 10340678629, 10344034444
---

# Candidate files are private and download only for the right people

**Why this journey exists:** every uploaded CV was a public attachment. `/wp/v2/media` listed them to anonymous visitors, each file downloaded from a guessable URL (private resumes' generated `resume-{id}.pdf` included), a guest applicant could attach anyone's CV by id, and the generated PDF was reused after the candidate edited the resume.

## Steps

1. Logged out, GET `/wp-json/wp/v2/media?media_type=application` → expect 0 items
2. As `marcus.williams`, apply to a job with an uploaded PDF → the attachment is `private` and its path contains `/wcb-private/`
3. As the job's employer, open the application → `resume_url` is `…/?wcb_file=<id>`; opening it downloads the PDF
4. As `employer.stripe` (no application from this candidate), GET `/wp-json/wcb/v1/files/<id>` → 403; logged out, `/?wcb_file=<id>` → 404
5. Logged out, POST `/wp-json/wcb/v1/jobs/<job>/apply` as a guest with `resume_attachment_id=<id>` → 400 `wcb_invalid_resume`
6. (Pro) Apply with a builder resume, edit the resume headline, apply to a second job → the second application gets a new PDF with the new headline; the first keeps its original
7. (Pro) Logged out on a public resume page → "Sign in to download PDF", never a raw 401
8. Tools > Site Health → "Candidate files are private" is good (Apache) or recommends the nginx `deny all` rule
9. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post delete <application ids> --force
```

## Notes

- Existing files move in the background after upgrading (cron `wcb_private_files_migrate`, 50 per pass) or at once with `wp wcb migrate files`.
