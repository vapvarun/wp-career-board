# Troubleshooting & FAQ

Common issues and how to resolve them.

---

## Setup Wizard

### The wizard says "Failed to create pages" and won't advance

The wizard calls the WordPress REST API to create pages. This can fail when:

- **Pretty permalinks are off** — go to **Settings → Permalinks**, select any option other than Plain, and save.
- **REST API is blocked** — a security plugin, firewall, or hosting rule is blocking `/wp-json/`. Temporarily deactivate security plugins and try again.
- **Auth cookie not sent** — if your site uses basic HTTP auth (common on staging), the REST request won't carry your session. Disable basic auth temporarily or add an exception for `/wp-json/`.

After fixing the underlying issue, go to **WP Career Board → Settings → Run Setup Wizard** to run the wizard again.

---

### Pages were created but they're blank or show a 404

The pages were created but may not have the correct block assigned. Edit each page in the block editor and insert the matching block:

| Page | Block to insert |
|---|---|
| Find Jobs | **Job Search** + **Job Filters** + **Job Listings** |
| Employer Registration | **Employer Registration** |
| Employer Dashboard | **Employer Dashboard** |
| Candidate Dashboard | **Candidate Dashboard** |
| Companies | **Company Archive** |

Then go to **Settings → Permalinks** and click **Save Changes** to flush rewrite rules.

---

## Jobs Not Appearing

### The Job Listings block shows "No jobs found"

1. Confirm you have published jobs — go to **WP Career Board → Jobs** and check the status column.
2. If jobs are pending review, go to **WP Career Board → Settings → Job Listings** and check whether **Auto-Publish Jobs** is enabled. If off, you need to approve each job manually from the Jobs list.
3. Check your active filters in the block — the **Job Type**, **Category**, or **Location** filters may be set to a value that returns no results.
4. Go to **Settings → Permalinks** and click **Save Changes** to flush rewrite rules.

### Jobs appear in wp-admin but not on the frontend

This is almost always a permalink flush issue. Go to **Settings → Permalinks** and click **Save Changes**.

---

## Application Form

### The "Apply" button does nothing / the application form doesn't open

- Guest applications are supported by default — no setting needs to be enabled. If the form still doesn't open, check that the user's browser is not blocking JavaScript.
- If using a page caching plugin (WP Rocket, W3 Total Cache), purge the cache after activating WP Career Board.
- Check the browser console for JavaScript errors — a JavaScript conflict with another plugin can prevent the form from loading.

### Candidates can't submit the application form

- The job may have a **deadline** that has already passed. Check the job listing's deadline field.
- If the job requires a resume upload and the candidate has no resume, the form will block submission. Check if **Require Resume** is enabled for that job type.
- Make sure file upload limits in your hosting's `php.ini` (`upload_max_filesize`, `post_max_size`) are large enough for resume files (recommend at least 5 MB).

---

## Email Notifications

### Emails are not being sent

WP Career Board uses `wp_mail()` to send emails. If emails aren't arriving:

1. **Check spam** — the notification emails from a local WordPress install often land in spam.
2. **Install an SMTP plugin** — the default `wp_mail()` uses PHP's `mail()` function, which most shared hosts reject. Install an SMTP plugin (e.g. WP Mail SMTP, FluentSMTP) and connect it to a transactional email service (Mailgun, SendGrid, Postmark).
3. **Verify the sender address** — go to **WP Career Board → Settings → Notifications** and confirm the From email matches your domain. Some hosts reject mail from mismatched domains.
4. **Check notification toggles** — each notification type can be toggled off in **Settings → Notifications**. Confirm the relevant notification is enabled.

### The wrong email address is receiving notifications

Admin notification emails go to the address set in **Settings → Notifications → Admin Email**. This defaults to the WordPress admin email but can be overridden.

---

## Employer & Candidate Accounts

### A user registered but isn't showing up as an Employer or Candidate

The role is assigned at registration based on which registration form the user used:

- Employers register via the **Employer Registration** page (which contains the Employer Registration block).
- Candidates register via the **Candidate Dashboard** page.

If a user registered via the standard WordPress login page, they won't have a job board role. Go to **WP Career Board → Employers** or **Candidates** and manually assign the user.

### An employer can't post jobs

1. Check the employer's account in **WP Career Board → Employers** — confirm they have the Employer role.
2. If the Credit System is active (Pro), confirm the employer has available credits. A zero balance blocks job posting.
3. Confirm the Job Form page has the **Job Form** block inserted.

---

## Credit System (Pro)

### The Stripe payment isn't completing

1. Confirm you are using the correct API keys for your environment — test keys for staging, live keys for production.
2. Go to **WP Career Board → Settings → Credits** and verify the Stripe Secret Key and Publishable Key are entered correctly.
3. **Webhooks** — Stripe requires a webhook endpoint to confirm payment. The webhook URL is shown in **Settings → Credits**. Paste it into your Stripe Dashboard under **Developers → Webhooks** and enable the `checkout.session.completed` event.
4. Test with Stripe's built-in test card numbers (`4242 4242 4242 4242`) before going live.

### Credits were paid but not added to the employer's balance

This is almost always a webhook delivery failure. In the Stripe Dashboard, go to **Developers → Webhooks**, find your endpoint, and check the event log. Re-deliver failed events manually if needed.

---

## Block Issues

### The block editor shows "Your block contains unexpected or invalid content"

This usually means the block's HTML was hand-edited or copied incorrectly. Click **Attempt Block Recovery** when prompted — this will restore the block from its saved attributes.

### The block renders but looks completely unstyled

WP Career Board enqueues its CSS only on pages that contain its blocks. If you are embedding a shortcode or pasting raw HTML outside a block, styles won't load. Use the block editor and insert the correct block instead.

---

## Performance

### The jobs page is slow

- Enable **object caching** on your server (Redis or Memcached) — WP Career Board caches job queries.
- If using a page caching plugin, configure it to **exclude** the Candidate Dashboard and Employer Dashboard pages (they are user-specific and must not be served from cache).
- The job search uses a live REST API call on every keystroke (with debounce). If the REST API is slow, check for slow database queries using **Query Monitor**.

---

## Still Stuck?

If none of the above resolves your issue:

1. Enable **WP_DEBUG** and **WP_DEBUG_LOG** in `wp-config.php` and check `wp-content/debug.log` for PHP errors.
2. Deactivate all plugins except WP Career Board to rule out conflicts, then reactivate one by one.
3. Open a support ticket at [wbcomdesigns.com/support](https://wbcomdesigns.com/support) with your WordPress version, PHP version, active theme, and a description of what you tried.
