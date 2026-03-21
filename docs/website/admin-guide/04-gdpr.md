# GDPR & Privacy

WP Career Board integrates with WordPress's built-in privacy tools to help you comply with GDPR and similar data protection regulations.

## What Data WP Career Board Stores

**Per employer:**
- Company name, logo, description, website, size, industry
- Posted job listings

**Per candidate:**
- Name and email address
- Cover letters submitted
- Application history and status

**System logs:**
- Application timestamps

## Data Export

WordPress has a built-in personal data export tool. WP Career Board integrates with it so all job board data for a user is included.

To export a user's data:
1. Go to **Tools → Export Personal Data** in wp-admin
2. Enter the user's email address
3. Click **Send Request**
4. The user receives an email with a link to download their data export

The export includes all applications, cover letters, and profile data associated with that email address.

## Data Erasure

To erase a user's personal data:
1. Go to **Tools → Erase Personal Data** in wp-admin
2. Enter the user's email address
3. Click **Send Request**
4. The user confirms via email
5. After confirmation, WordPress erases all personal data including WP Career Board records

> **Note:** Erasing a user's data removes their applications and profile. Job listings posted by an employer are not automatically deleted — you may need to manually remove those.

## Privacy Policy Page

Add the following to your privacy policy to inform users what data WP Career Board collects:

- Account registration data (name, email)
- Job applications including cover letters
- Activity logs for job board interactions
- No payment data is stored (payments processed by Stripe in Pro version)

## Cookie Usage

WP Career Board does not set any cookies in the free version. Session state (e.g., active dashboard tab) is stored in `sessionStorage` (browser memory only, not a cookie, cleared when the browser tab closes).
