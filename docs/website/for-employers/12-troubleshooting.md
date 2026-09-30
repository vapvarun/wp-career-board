# Troubleshooting (employers)

Answers to common employer questions. If you run the site, see the admin troubleshooting page in the admin guide.

## "I posted a job but it does not show up"

Check these in order:

1. **Is it pending approval?** Open **Employer Dashboard > My Jobs**. If the job shows **Pending**, the site holds new jobs for a moderator. Wait for approval or ask the site admin. If it shows **Awaiting payment**, your credit balance did not cover it. Top up and it goes live.
2. **Is it past its deadline?** A job that passes its deadline stops taking applications, and on most sites it leaves the listings. It shows as **Expired**. Click **Reopen** for a new listing period.
3. **Did the submit finish?** Your dashboard lists every job you created. If the job is not there, the form did not save. Submit it again.

## "Applicants are not coming in"

- **Find the job on the site.** Open the job listings page (`/find-jobs/` on a default install) in a private window. If you cannot find your job there, candidates cannot either.
- **Share the job link.** Post it on LinkedIn, your company channels and your own site.
- **Check the deadline.** A job past its deadline takes no applications.
- **Use common search terms.** "Senior Frontend Engineer" is easier to find than a job filed under a vague category.

## "I get 'Insufficient credits' when I post"

The site charges credits per posting. Your balance shows in the **Credits** item of the sidebar.

- **Buy more.** Click **Buy credits** and complete the checkout. Your balance updates when the purchase clears.
- **Wait for a pending payment.** If the payment is still pending (for example a bank transfer), credits are not added until it clears. If you paid and the credits do not appear, send the site admin your order or receipt number.
- **Try another board.** Boards can have different prices. Post to a cheaper board if it suits the role.

## "My company profile does not save"

- **Edit from the dashboard.** Use **Employer Dashboard > Profile**, then **Save Profile**. The public company page is read-only.
- **Enter a company name.** It is the only required field. If it is empty, the save stops with "Company name is required."
- **Save the profile before you upload a logo.** The logo upload works only after the profile has been saved once.

## "I do not get emails about new applications"

- **Check your spam folder.**
- **Check the email on your account.** Open **Employer Dashboard > Settings** and look at the Email field. Emails go to that address.
- **Check your email preferences.** In **Settings > Email Notifications** you can turn off optional emails such as **Job Ending Soon**. Emails about your account, applications and payments are always sent.
- **Ask the site admin to check email sending.** They can confirm that the site sends email and that the "Application Received (Employer)" email is enabled under **Career Board > Settings > Emails**.

## "The 'Apply on Company Site' button is missing"

- **Use a full address.** The Apply URL must start with `http://` or `https://`.
- **Look at the live job page.** The button appears on the public job page, not in the form preview.

## "Candidates say the apply form is broken"

- **They may need to log in.** If the site has **Require login to apply** on, the job page shows **Sign in to apply** in place of the form.
- **They may be an employer.** Employers do not see an Apply button, and nobody can apply to their own job.
- **The site may use anti-spam.** Ad blockers or strict browser settings can block a captcha script (Turnstile or reCAPTCHA). Ask the candidate to try another browser.
- **The resume may be too large.** The site sets a maximum resume size, 5 MB by default. Ask the candidate to shrink or re-export the file.

## "I cannot see the Pro features"

The site admin must install and activate WP Career Board Pro and activate its license under **WP Career Board > Settings > License**. You cannot turn Pro on from your dashboard.

## What to send your site admin

Send them:

1. The address of the page where the problem happens.
2. A screenshot of what you see and what you expected.
3. The date and time it happened.
4. The email address of your account.
5. If it applies, your order number, the job title or the candidate's email.

Do not send screenshots of payment details or sensitive HR data.

## Related

- [Post a Job](./02-post-a-job.md)
- [Review Applications](./04-review-applications.md)
- [Your Credit Balance](./10-employer-credit-balance.md)
