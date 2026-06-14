# Troubleshooting (Employers)

Common things employers hit, in plain language. If you're a site
owner / admin, see `admin-guide/07-troubleshooting.md` for the
admin-side debug path.

## "I posted a job but it isn't showing up"

Three things to check, in order:

1. **Is it pending approval?** Go to your **Employer Dashboard →
   My Jobs**. If the job is there with status "Pending" or "Under
   Review", the site is configured to require admin approval.
   Contact the site admin or wait for approval.
2. **Is it past the deadline?** If you set an "Apply by" date in
   the past (or your draft sat too long and the date came and
   went), the job auto-expires. Open it from your dashboard and
   either extend the deadline or republish.
3. **Did the submit actually complete?** Your dashboard shows
   every job you've created. If it's not there, the form
   didn't save - usually because of a network blip. Re-submit.

## "Applicants aren't coming in"

Posting alone doesn't bring traffic. Check:

- **The job is on `/find-jobs/`** - visit it from a private window.
  If you can't find it there, candidates can't either.
- **Your job's URL has been shared.** Share on LinkedIn, your
  company's social channels, internal Slack. New job boards
  build traffic over months.
- **The "Apply by" date isn't past.** Expired jobs disappear from
  the default listing.
- **Your category, location, and type are typical search terms.**
  A "Senior Frontend Engineer" gets more eyeballs than a job filed
  as "Other → Other".

## "I keep getting 'Insufficient credits' when I try to post"

The site charges credits per posting. Your balance is on the
Employer Dashboard → Credits panel.

- **You need to buy more.** Click "Buy Credits" and complete the
  checkout. Once the order completes, your balance updates and
  you can post.
- **You bought credits but they didn't add.** Wait 1-2 minutes
  (the dashboard caches the balance briefly). If still missing,
  contact the site admin with your order/receipt number.
- **Different boards cost different amounts.** Switch to a cheaper
  board in the post-a-job form if it suits your role.

## "My company profile changes aren't saving"

- **Edit from the dashboard.** The company profile is edited from
  **Employer Dashboard - Profile** (then **Save Profile**), not from
  the public company page.
- **Company name is required.** It's the only required field. If you
  clear it, the save is blocked with "Company name is required." All
  other fields are optional.
- **Save the profile before uploading a logo.** Logo upload only
  becomes available after the profile has been saved once.
- **Special characters.** If you pasted text with emoji or
  unusual characters and the save fails, copy the text into a
  plain editor first and re-paste.

## "I'm not getting email notifications about new applications"

- **Check your spam folder.** Most "missing email" complaints are
  spam-filter issues.
- **Confirm the email on your account is correct.** Open
  **Employer Dashboard - Settings** (Account Settings) and check the
  Email field. New-application emails go to that address.
- **The site's email might not be configured properly.** Contact
  the site admin and ask them to verify SMTP / email sending is
  working, and that the "new application" notification template is
  enabled in Career Board - Settings - Emails.

## "The 'Apply on Company Site' button is missing from my job"

You set an external Apply URL but it isn't showing? Two things:

- **The URL must start with `http://` or `https://`.** Just typing
  `company.com/careers` won't work - the plugin filters out URLs
  without a scheme.
- **It only appears on the public job page.** When you preview
  from your dashboard, the form's preview pane might not show it
  - check the live job URL instead.

## "Candidates report the apply form is broken"

- **They might not be logged in.** If your site requires login to
  apply, the Apply button shows but the form short-circuits. Tell
  candidates to register or log in first.
- **The site might have anti-spam (Turnstile/reCAPTCHA) enabled.**
  Adblockers or strict browser settings can block the captcha
  script. If a candidate reports this, ask them to try in a
  different browser.
- **The resume file is too large.** Default limit is 5 MB. Larger
  files get rejected at upload. Ask the candidate to resize/
  re-export their PDF.

## "I can't see the Pro features I'm paying for"

- **Pro plugin needs to be installed AND active.** Site admin
  needs to do this - you can't enable it from your dashboard.
- **License needs to be activated.** Site admin enters the license
  key under Career Board → Settings → License.
- **Some features depend on configuration.** Job Map needs Google
  Maps API key, AI needs an OpenAI/Anthropic key, alerts need
  cron working. Site admin handles all this.

## I'm stuck - what should I send my site admin?

Send them:

1. The URL of the page where the problem happens.
2. A screenshot of what you see vs. what you expect.
3. The time and date the problem occurred (helps them check logs).
4. Your user account email (so they can find your record).
5. If applicable: your order/transaction number, the job title,
   the candidate's email.

Don't send screenshots of payment info or sensitive HR data -
just the descriptive details.

## Related

- [02-post-a-job.md](02-post-a-job.md) - Full post-a-job flow
- [04-review-applications.md](04-review-applications.md) - Reviewing applicants
- [10-employer-credit-balance.md](10-employer-credit-balance.md) - Credits FAQ
