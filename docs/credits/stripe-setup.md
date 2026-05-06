# Stripe Checkout — Setup Guide

This guide walks you through enabling **Stripe Checkout** as a direct payment gateway for credit purchases on WP Career Board Pro. No WooCommerce required.

**What you'll end up with:**

- Employers click "Buy Credits" → land on a hosted Stripe Checkout page → pay → return to your dashboard with the credits already topped up.
- Refunds (full or partial) issued from your Stripe dashboard automatically debit the employer's credit balance.

**Time required:** ~10 minutes (5 in Stripe, 5 in WordPress).

---

## Before you start

You'll need:

1. A Stripe account — sign up free at https://dashboard.stripe.com/register if you don't have one.
2. Admin access to your WordPress site.
3. WP Career Board Pro v1.2.0 or newer with credits enabled (see Settings → Career Board → Credits).

Stripe takes a fee on every charge (~2.9% + 30¢ in the US, varies by country). The plugin doesn't charge anything extra on top.

---

## Step 1 — Get your Stripe API keys

1. Sign in at **https://dashboard.stripe.com/**.
2. **Top right corner**, toggle the **"Test mode"** switch:
   - **Test mode** lets you run end-to-end purchases with fake card numbers. Use this until you're confident everything works.
   - **Live mode** processes real payments. Switch to this only after you've tested.
3. Open **Developers → API keys** in the left sidebar (or visit https://dashboard.stripe.com/apikeys directly).
4. You'll see two keys:
   - **Publishable key** — starts with `pk_test_…` (test) or `pk_live_…` (live).
   - **Secret key** — starts with `sk_test_…` or `sk_live_…`. Click "Reveal test key" / "Reveal live key" to copy.
5. **Keep both keys somewhere safe.** Never paste the secret key into a public message, screenshot, or commit.

---

## Step 2 — Create the webhook endpoint

Stripe needs to call back to your site when a payment completes or a refund is issued. Without this step, employers will pay but never get credits — so don't skip it.

1. **Copy your webhook URL from the WP admin.** Go to:

   `wp-admin/admin.php?page=wcb-settings&tab=credits` → **Direct Payment Gateways** → **Stripe**.

   Below the credentials fields you'll see a **"Webhook URL"** row. It looks like:

   ```
   https://yoursite.com/wp-json/wbcom-credits/v1/wp-career-board/webhook/stripe
   ```

   Copy it.

2. **Create the webhook in Stripe.**

   - Open **Developers → Webhooks** in the Stripe dashboard (or https://dashboard.stripe.com/webhooks).
   - Click **"+ Add endpoint"**.
   - **Endpoint URL** — paste the URL you copied above.
   - **Description** (optional) — `WP Career Board credit top-ups`.
   - **Events to send** — click "+ Select events" and tick exactly these two:
     - `checkout.session.completed`
     - `charge.refunded`
   - Click **"Add endpoint"** to save.

3. **Reveal and copy the signing secret.**

   On the endpoint detail page you just created, click **"Reveal"** under **Signing secret**. It looks like:

   ```
   whsec_a1b2c3...
   ```

   This proves to your site that webhook calls really come from Stripe — without it, attackers could fake top-ups.

   **Copy this value.** You'll paste it into WordPress in Step 3.

---

## Step 3 — Configure WordPress

1. In WP admin go to **Career Board → Settings → Credits**.
2. Scroll to **"Direct Payment Gateways"** and expand the **Stripe** section.
3. Fill in:
   - **Enable Stripe** — toggle on.
   - **Mode** — match what you used for the keys (Test or Live).
   - **Publishable key** — paste the `pk_…` value.
   - **Secret key** — paste the `sk_…` value.
   - **Webhook signing secret** — paste the `whsec_…` value from Step 2.3.
   - **Post-purchase redirect** — the page employers land on after a successful payment. Most sites point this at the **Employer Dashboard** page (the URL is shown in Settings → Pages). Leave blank to fall back to the site home with `?wbcom_credits=success`.
   - **Cancel-redirect URL** — where to send employers who clicked "Cancel" on the Stripe page. Usually the credits-purchase page. Leave blank to fall back to home with `?wbcom_credits=cancel`.
4. Click **"Save Gateway Settings"**.

---

## Step 4 — Verify it works (test mode)

While in Test mode:

1. Sign in as an employer (not as the site admin).
2. Click **"Buy Credits"** on the dashboard or in the post-job form.
3. On the Stripe Checkout page use one of these test cards:
   - **Successful payment:** `4242 4242 4242 4242`, any future expiry, any 3-digit CVC, any ZIP.
   - **Successful + 3D Secure:** `4000 0027 6000 3184` (Stripe will prompt for "Complete authentication").
   - **Declined:** `4000 0000 0000 0002`.
4. After the test payment you should:
   - Land back on the dashboard.
   - See a green banner: **"✓ N credits added to your balance."**
   - The sidebar Balance counter should show the new total.
5. **Verify the refund flow.** Open the corresponding payment in Stripe → Payments → click "Refund". The employer's balance should drop within ~5 seconds (the Stripe webhook fires `charge.refunded`, the SDK looks up the original session, and credits the equivalent number of credits back out of the ledger).

If Step 4.4 fails, see **Troubleshooting** below.

---

## Step 5 — Switch to live mode

When test mode looks good:

1. In the Stripe dashboard, toggle **"Test mode" off**.
2. Re-do **Step 1** (copy your `pk_live_…` and `sk_live_…` keys) and **Step 2** (create a new webhook endpoint with the same events; it gets a fresh `whsec_…` secret).
3. In WP admin → Credits, change **Mode** to **Live** and paste the live keys + signing secret.
4. Save.

You're now accepting real payments.

---

## Permissions / scopes

Stripe doesn't use OAuth scopes — your secret key has full access to your Stripe account. That's why:

- The secret key field is `password`-typed (never echoed to logs or screen).
- We never store the key in transient/option caches.
- The HTTPS calls SDK → Stripe always go to `https://api.stripe.com/` with `Authorization: Bearer sk_…` (TLS-pinned by Stripe).

The webhook endpoint only accepts payloads with a valid `Stripe-Signature` header that matches your signing secret, so a bad actor cannot top up balances by hand-rolling a request.

**Stripe API operations the SDK performs:**

| What | Endpoint | Why |
|---|---|---|
| Create a Checkout Session | `POST /v1/checkout/sessions` | Builds the hosted payment page URL when an employer clicks "Buy Credits". |
| Look up a Session | `GET /v1/checkout/sessions/{id}` | Resolves `payment_intent` so refunds know what to refund. |
| Issue a refund (admin-side) | `POST /v1/refunds` | Only used when admin clicks "Refund" via the SDK refund API. Manual refunds in the Stripe dashboard fire a webhook in the other direction. |

---

## Troubleshooting

### "I clicked Pay, paid the test card, but the dashboard says my balance is still 0."

This is almost always a webhook issue. Check:

1. **Webhook URL is reachable.** Open the URL you pasted in Step 2 directly in a browser. You should see a JSON 404 like `{"code":"rest_no_route",...}` (correct — the route only accepts POST). If you see a WordPress 404 page or your host's challenge page (Cloudflare, etc.), the URL is being blocked before WordPress sees it.
2. **Webhook signing secret matches.** A typo here makes every webhook call fail signature verification. Re-paste it from Stripe → Developers → Webhooks → your endpoint → "Signing secret".
3. **Stripe shows the webhook delivery.** Stripe → Developers → Webhooks → your endpoint → "Webhook attempts" tab. Failed deliveries show the response body. A `400 amount_or_currency_mismatch` means the price the employer paid didn't match what your plugin asked for — usually a currency mismatch on the credit-mappings option.
4. **Site can talk back out.** Some hosts block outbound HTTPS. Run `wp eval 'var_dump(wp_remote_get("https://api.stripe.com/v1/balance"));'` — if you get `WP_Error: cURL error 6` or `7`, your host's firewall is the problem.

### "Webhook calls work, but I see 'unknown_session' in the Stripe dashboard."

The SDK tracks pending checkouts in a transient. If your object cache (Redis/Memcached) is dropping data after a few minutes, transients won't survive long enough for the webhook to find them. Increase your transient/object-cache TTL or switch to database-backed transients.

### "Test mode worked. Live mode fails."

Live mode keys + webhook are different from test mode. You need a brand-new live webhook endpoint with its own `whsec_…`. Don't reuse the test signing secret.

---

## Removing Stripe

To stop accepting Stripe payments:

1. Career Board → Settings → Credits → Stripe → toggle **Enable Stripe** off, save.
2. (Optional) In the Stripe dashboard, disable or delete the webhook endpoint so Stripe stops trying to call your site.

Existing credits stay where they are — only future top-ups are blocked.

---

## Need help?

- Stripe support: https://support.stripe.com/
- Plugin support: https://wbcomdesigns.com/contact-us/
