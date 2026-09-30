---
id: employer-buys-credits-and-features
priority: critical
personas: employer.stripe, admin
requires: mu:autologin, pro
last_verified: 2026-09-27
needs: cli, mailpit
bug_ref: 10344034912, 10344034812, 10344035035
---

# An employer buys credits from the dashboard, gets a receipt, and features a job

**Why this journey exists:** there was no purchase path: "Buy Credits" pointed at a URL the owner had to invent, 402s had no link, the credit-balance block was placed nowhere. Checkout charged 100x on zero-decimal currencies, a paid Stripe checkout was never credited on a site without a webhook, and there were no receipts, billing details, coupons, tax or paid Featured.

## Steps

1. Settings > Credits: Stripe enabled (test keys), a pack (e.g. 10 credits, JPY 500 or USD 29), tax 10%, a coupon SAVE20; Checkout & receipts shows billing mode, tax, business details, receipt prefix
2. As the employer, post on a paid board with 0 credits → 402 with a "Buy credits" link next to the error; it opens `/employer-dashboard/#credits`
3. Credits tab: balance, Stripe pill, packs priced in the pack currency's own decimals (JPY 500 shows ¥500, not ¥5), billing fields prefilled from the account, coupon field, tax note
4. Empty a required billing field, Continue → the field is highlighted, no redirect; unknown coupon → "That coupon code is not valid."
5. Complete a Stripe test checkout with SAVE20 → returns to the Credits tab, balance goes up without a webhook configured (claim on return), "Receipts" lists the purchase
6. Mailpit: "Your receipt INV-…" (no second top-up email); "View receipt" opens the printable receipt: pack price, discount, tax, total, billing snapshot; another employer gets 404 on the same URL
7. My Jobs: "Feature" on a live job → confirm → "Featured" badge, balance down by the featured cost; the job lists first on /jobs/ and stays first on Load More
8. With no gateway, pack or mapped product: the Credits tab says credits can't be bought, and Career Board admin screens show "Employers can't buy credits, but a board charges them"
9. Settings > Analytics: Credits Spent is the net of charged jobs, Revenue shows the currency total after refunds
10. tail debug.log diff → expect ZERO new fatal/warning lines

Automated: `wp eval-file wp-content/plugins/wp-career-board-pro/tests/test-credit-checkout.php` (fake gateway, steps 3-8 logic).

## Teardown

```bash
wp option delete wbcom_credits_gateway_settings_wp-career-board   # if set only for the test
```
