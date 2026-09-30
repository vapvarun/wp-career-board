# GDPR & Privacy

You can answer personal-data export and erase requests with WordPress's built-in privacy tools. WP Career Board adds its own data to both.

## What data WP Career Board stores

- **Applications** - the job, status, submission date, cover letter and resume file. For guests, the name and email they applied with.
- **Candidate profile** - headline, location, open-to-work and profile visibility, profile resume data, and saved jobs, companies and resumes.
- **Uploaded files** - resumes and generated files, kept in a private folder.
- **Email history** - kept for the period set under **Settings → Advanced → Keep Email History** (180 days by default).
- **Employer content** - company pages and job listings.

## Data export

The WordPress export includes a person's applications, profile and email history from WP Career Board.

To export a user's data:
1. Go to **Tools → Export Personal Data** in wp-admin
2. Enter the user's email address
3. Click **Send Request**
4. The user receives an email with a link to download their data export

Guests who applied with an email address are found by that address.

## Data erasure

To erase a user's personal data:
1. Go to **Tools → Erase Personal Data** in wp-admin
2. Enter the user's email address
3. Click **Send Request**
4. The user confirms via email
5. After confirmation, WordPress erases the personal data, including the WP Career Board records described below

Erasing a person's data removes their Career Board profile data, uploaded files and saved items, and anonymises their applications: employers keep the job, status and dates, shown as "Deleted candidate", with the name, email, cover letter, answers and files removed. Guests are found by the email they applied with. Deleting a user under **Users** does the same. A ban on the account and the record that the privacy request was handled are kept. Job listings posted by an employer are not deleted automatically; remove those yourself if needed.

## Account deletion and the privacy tab

Members can delete their own account from their dashboard. It is locked at once and deleted after a 14 day grace period (the default). Under **Career Board → Settings → Privacy** you can see who is waiting, cancel a deletion on their behalf with **Keep account**, and read the request log of every export and erase the plugin processed. Visitor IP addresses in that log are stored only as a one-way hash.

Add-ons that store personal data register with the `wcb_personal_data_providers` filter once, and are covered by the WordPress export and erase tools and by account deletion. See the Developer Guide hooks reference.

## Privacy policy page

Add the following to your privacy policy to tell people what data WP Career Board collects:

- Account registration data (name, email)
- Job applications, including cover letters and resume files
- Email history for the period you keep it

## Cookies and browser storage

WP Career Board does not set its own cookies in the free version. It remembers the open dashboard tab in `sessionStorage`, which clears when the browser tab closes, and the job list layout (grid or list) in `localStorage`.
