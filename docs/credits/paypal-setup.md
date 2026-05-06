# PayPal — Setup Guide

This guide walks you through enabling **PayPal Orders v2** as a direct payment gateway for credit purchases on WP Career Board Pro. No WooCommerce required.

**What you'll end up with:**

- Employers click "Buy Credits" → land on the PayPal payment page → pay with PayPal balance, card, or local payment methods PayPal supports → return to your dashboard with credits already topped up.
- Refunds (full or partial) issued from your PayPal dashboard automatically debit the employer's credit balance.

**Time required:** ~15 minutes (10 in PayPal, 5 in WordPress). PayPal's developer console is denser than Stripe's — most of the time goes into navigating it.

---

## Before you start

You'll need:

1. A **PayPal Business account** (Personal accounts cannot accept programmatic payments). Sign up at https://www.paypal.com/business if you don't have one.
2. Admin access to your WordPress site.
3. WP Career Board Pro v1.2.0 or newer with credits enabled.

PayPal takes a fee on every charge (~3.49% + fixed fee in the US, varies by country). Plugin doesn't add anything on top.

---

## Step 1 — Create a REST app in the PayPal developer console

PayPal's API uses OAuth 2.0 client_credentials, which requires a paired **client ID** + **client secret**. Each environment (sandbox, live) gets its own pair.

1. Sign in at **https://developer.paypal.com/**.
2. Open **Apps & Credentials** in the top menu.
3. **Top right corner**, toggle between **Sandbox** and **Live**:
   - **Sandbox** — fake money, fake buyers. Use this until you're confident the flow works.
   - **Live** — real money. Switch only after testing.
4. Under **REST API apps**, click **"Create App"**.
5. Fill in:
   - **App Name** — `WP Career Board Credits` (or anything you'll recognize).
   - **Type** — Merchant.
   - **Sandbox Business Account** — pick one of the auto-created test merchant accounts (sandbox only). For Live mode this defaults to your business account.
6. Click **Create App**.
7. On the app detail page you'll see:
   - **Client ID** — long string, public-ish.
   - **Secret** — long string, click "Show" to reveal. **Treat this like a password — never paste it anywhere public.**

Keep both somewhere safe; you'll paste them into WordPress in Step 3.

---

## Step 2 — Configure the app's features and webhook

1. Still on the app detail page, scroll to **App settings → Features**.
2. **Tick exactly these boxes** (untick everything else; PayPal grants only the scopes you tick):
   - ✅ **Accept payments**
   - ✅ **Refund**

   Optional (only if you'll need them later):
   - **Vault** — only if you plan to store payment methods for repeat purchases.
   - **Subscriptions** — not needed for one-off credit top-ups (subscriptions land in v1.3 of the SDK).

3. Scroll further to **Webhooks** and click **"Add webhook"**.

4. **Copy your webhook URL from the WP admin.** Go to:

   `wp-admin/admin.php?page=wcb-settings&tab=credits` → **Direct Payment Gateways** → **PayPal**.

   Below the credentials fields you'll see a **"Webhook URL"** row. It looks like:

   ```
   https://yoursite.com/wp-json/wbcom-credits/v1/wp-career-board/webhook/paypal
   ```

   Paste it into PayPal's **Webhook URL** field.

5. **Event types — tick exactly these two:**
   - ✅ `Payment capture completed` (`PAYMENT.CAPTURE.COMPLETED`)
   - ✅ `Payment capture refunded` (`PAYMENT.CAPTURE.REFUNDED`)

6. Click **Save**.

7. Back on the webhook list you just created, **copy the Webhook ID**. It's a string like `8XL12345…`. You need it in Step 3 to verify webhook signatures.

---

## Step 3 — Configure WordPress

1. In WP admin go to **Career Board → Settings → Credits**.
2. Scroll to **"Direct Payment Gateways"** and expand the **PayPal** section.
3. Fill in:
   - **Enable PayPal** — toggle on.
   - **Mode** — match what you used in Step 1 (Sandbox or Live).
   - **Client ID** — paste the value from Step 1.7.
   - **Client Secret** — paste the value from Step 1.7.
   - **Webhook ID** — paste the value from Step 2.7.
4. Click **"Save Gateway Settings"**.

---

## Step 4 — Verify it works (sandbox mode)

While in Sandbox mode:

1. Sign in as an employer (not as the site admin).
2. Click **"Buy Credits"** on the dashboard or in the post-job form.
3. On the PayPal page, sign in with one of the **sandbox personal test accounts** PayPal pre-created for you. Find them at https://developer.paypal.com/dashboard/accounts. They have a fake email + password and pre-loaded fake balance.
4. Complete the payment.
5. After the sandbox payment you should:
   - Land back on the dashboard.
   - See a green banner: **"✓ N credits added to your balance."**
   - The sidebar Balance counter should show the new total.
6. **Verify the refund flow.** Open the order at https://www.sandbox.paypal.com/ → Activity → click the transaction → "Issue a refund". The employer's balance should drop within ~5 seconds (PayPal fires `PAYMENT.CAPTURE.REFUNDED`, the SDK debits credits).

If Step 4.5 fails, see **Troubleshooting** below.

---

## Step 5 — Switch to live mode

When sandbox looks good:

1. PayPal developer dashboard → toggle **Live** in the top right.
2. Re-do **Steps 1 + 2** in Live mode — you'll get a brand-new client ID, secret, and webhook ID.
3. In WP admin → Credits, change **Mode** to **Live** and paste the live values.
4. Save.

You're now accepting real PayPal payments.

---

## Permissions / scopes

PayPal uses OAuth 2.0 client_credentials. The features you ticked in **Step 2.2** map to specific scopes:

| Tickbox in PayPal | OAuth scope | Why we need it |
|---|---|---|
| Accept payments | `https://uri.paypal.com/services/payments/payment` | Create / capture / read orders. |
| Refund | `https://uri.paypal.com/services/payments/refund` | Issue refunds when admin uses the SDK refund API. Provider-side refunds (admin clicks Refund in PayPal) don't need this scope. |

If you don't tick Refund, the integration still works — buyers can pay, employers get credits. But you won't be able to refund from WP-CLI / your code; you'll have to refund manually in the PayPal dashboard (which still triggers the credit-debit webhook correctly).

The SDK never asks for `Vault`, `Subscriptions`, `Payouts`, or `Disputes` scopes. You can leave those un-ticked.

**PayPal API operations the SDK performs:**

| What | Endpoint | Mode |
|---|---|---|
| Get an OAuth token | `POST /v1/oauth2/token` | Both |
| Create an order | `POST /v2/checkout/orders` | Both |
| Get the order's capture id (for refunds) | `GET /v2/checkout/orders/{id}` | Both |
| Issue a refund | `POST /v2/payments/captures/{capture_id}/refund` | Both |

API host: `https://api-m.paypal.com` (live) / `https://api-m.sandbox.paypal.com` (sandbox). Webhook signature verification calls `POST /v1/notifications/verify-webhook-signature` to confirm a notification really came from PayPal.

---

## Troubleshooting

### "I paid in sandbox but my balance still shows 0."

Check, in order:

1. **Webhook URL is reachable.** Open the URL you pasted in Step 2.4 directly in a browser. You should see a JSON 404 like `{"code":"rest_no_route",...}` (correct — the route only accepts POST). A WordPress 404 or host challenge page means something is blocking the request before WordPress sees it (Cloudflare, security plugin, host firewall).
2. **Webhook ID matches.** A typo or pasting the wrong webhook's ID makes every signature check fail. Re-copy it from PayPal → developer dashboard → My Apps → your app → Webhooks.
3. **PayPal shows the webhook delivery.** PayPal developer dashboard → Webhook events. Failed deliveries show the response body. A `400 amount_or_currency_mismatch` means the price PayPal captured doesn't match what the plugin expected — almost always a currency mismatch on the credit-mappings option (e.g. you priced credits in EUR but PayPal sent USD).
4. **Site can talk back out.** Run `wp eval 'var_dump(wp_remote_post("https://api-m.sandbox.paypal.com/v1/oauth2/token"));'` — if you get a `cURL error 6/7`, your host blocks outbound HTTPS. Whitelist `api-m.paypal.com` and `api-m.sandbox.paypal.com`.

### "PayPal says 'INVALID_CLIENT' when an employer tries to pay."

The Client ID + Secret don't match. Common causes:

- You pasted the **Live** client ID with the **Sandbox** secret (or vice versa).
- You copied a trailing space.
- Your app was deleted / regenerated in the PayPal dashboard.

Re-copy both values, paste, save.

### "Webhook signature verification fails."

PayPal's signature algorithm changed mid-2024 to use the API endpoint `/v1/notifications/verify-webhook-signature`. The SDK calls that endpoint on every webhook delivery, which means:

- Your app must keep the **Refund** + **Accept payments** scopes ticked. If you accidentally untick "Accept payments" the verify call returns 401 and we ignore the webhook.
- Your **Webhook ID** in WP must exactly match the one PayPal generated for the URL you pasted. If you delete and recreate the webhook, the ID changes.

### "Sandbox worked. Live fails on first payment."

Live mode requires a separate webhook entry — you can't reuse the sandbox webhook ID. Re-do Step 2 in live mode, copy the new webhook ID, paste into WP, save.

### "I see two refund debits in the ledger for one PayPal refund."

This is what idempotency guards against — and the SDK should never let it happen. If you see it, please check the `Transaction_Log` table for the offending `event_id` and report it. The SDK clamps total refunded credits to never exceed credits originally granted, so even a misbehaving provider can't over-refund.

---

## Removing PayPal

To stop accepting PayPal payments:

1. Career Board → Settings → Credits → PayPal → toggle **Enable PayPal** off, save.
2. (Optional) Open your PayPal app → Webhooks → delete the webhook entry. PayPal will retry failed deliveries for ~3 days otherwise.

Existing credits stay where they are; only future top-ups are blocked.

---

## Need help?

- PayPal developer support: https://www.paypal.com/us/smarthelp/contact-us
- Plugin support: https://wbcomdesigns.com/contact-us/
