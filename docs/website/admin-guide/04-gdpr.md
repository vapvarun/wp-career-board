# GDPR & Privacy

WP Career Board integrates with WordPress's built-in privacy tools to help you comply with GDPR and similar data protection regulations.

## What data WP Career Board stores

**Per employer:**
- Company name, logo, description, website, size, industry
- Posted job listings

**Per candidate:**
- Name and email address
- Cover letters submitted
- Application history and status
- Uploaded resume files, kept in a private folder and served only to the candidate, staff and the employer they applied to

**System logs:**
- Application timestamps
- Email history (kept for the period set under **Settings → Advanced → Keep Email History**, 180 days by default)

## Data export

WordPress has a built-in personal data export tool. WP Career Board integrates with it so all job board data for a user is included.

To export a user's data:
1. Go to **Tools → Export Personal Data** in wp-admin
2. Enter the user's email address
3. Click **Send Request**
4. The user receives an email with a link to download their data export

The export includes all applications, cover letters, and profile data associated with that email address.

## Data erasure

To erase a user's personal data:
1. Go to **Tools → Erase Personal Data** in wp-admin
2. Enter the user's email address
3. Click **Send Request**
4. The user confirms via email
5. After confirmation, WordPress erases all personal data including WP Career Board records

Erasing a person's data removes their profile, files, resumes and saved items, and anonymises their applications: employers keep the job, status and dates, shown as "Deleted candidate", with the name, email, cover letter, answers and files removed. Guests are found by the email they applied with. Deleting a user under **Users** does the same. Job listings posted by an employer are not deleted automatically; remove those yourself if needed.

## Account deletion and the privacy tab

Members can delete their own account from their dashboard. It is locked at once and deleted after a 14 day grace period. Under **Career Board → Settings → Privacy** you can see who is waiting, cancel a deletion on their behalf with **Keep account**, and read the request log of every export and erase the plugin processed. Visitor IP addresses in that log are stored only as a one-way hash.

Add-ons that store personal data register with the `wcb_personal_data_providers` filter once, and are covered by the WordPress export and erase tools and by account deletion. See the Developer Guide hooks reference.

## Privacy policy page

Add the following to your privacy policy to inform users what data WP Career Board collects:

- Account registration data (name, email)
- Job applications including cover letters
- Activity logs for job board interactions
- No payment data is stored (payments are processed by your e-commerce plugin - WooCommerce, PMPro, or MemberPress - not by WP Career Board directly)

## Cookie usage

WP Career Board does not set any cookies in the free version. Session state (e.g., active dashboard tab) is stored in `sessionStorage` (browser memory only, not a cookie, cleared when the browser tab closes).
