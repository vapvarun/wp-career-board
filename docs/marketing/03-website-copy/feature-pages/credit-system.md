# Feature Page: Credit System

**URL:** /features/credit-system/
**Goal:** Convert job board owners who want to monetize into Pro upgrades

---

## HERO

**Headline:** Charge employers to post jobs — keep 100% of the revenue

**Subheadline:**
WP Career Board Pro's Credit System uses Stripe to let you sell credit packages to employers. They buy credits, spend credits to post jobs. You set the prices. No platform percentage.

**CTA:** Upgrade to Pro

**Visual:** `credits-employer-balance.png` — employer credits balance view

---

## SECTION 1: HOW IT WORKS

Running a niche job board means you're providing employers with access to a specific talent pool. That's valuable — you should be able to charge for it.

The Credit System works like this:

1. **You create credit packages** — "1 job post for $29", "5 posts for $99", "10 posts for $179"
2. **Employers buy credits** via Stripe Checkout — card, Apple Pay, Google Pay
3. **Credits are added to their balance automatically** when payment confirms (Stripe webhook)
4. **When they post a job**, credits are deducted from their balance
5. **If their balance is zero**, they're prompted to buy more before posting

---

## SECTION 2: SETUP

**Heading:** Connected to Stripe in four steps

1. Install WP Career Board Pro and activate the Credit System module
2. Go to **WP Career Board → Settings → Credits** and enter your Stripe API keys
3. Paste the webhook URL shown in settings into your Stripe Dashboard
4. Create your credit packages — set the name, price, and credit count

**Visual:** `credits-stripe-keys.png` — settings screen with API key fields

**Visual:** `credits-stripe-webhook.png` — webhook configuration

---

## SECTION 3: PRICING FLEXIBILITY

**Heading:** Price however you want

- **Tiered packages** — bulk discounts encourage larger purchases
- **Featured listings** — charge more credits for featured job posts
- **Per-board pricing** — different boards can have different credit costs (e.g., the "Remote Only" premium board costs 2 credits; the general board costs 1)

---

## SECTION 4: EMPLOYER EXPERIENCE

**Heading:** A clean checkout, no friction

Employers click "Buy Credits" on their dashboard. They choose a package and go through Stripe Checkout — the same payment flow they use on major e-commerce sites.

When the payment completes, Stripe sends a webhook to your site and credits are added automatically. The employer sees their updated balance immediately.

**Visual:** `credits-package-add.png` — package selection screen

---

## SECTION 5: WHY NOT USE A JOB BOARD SaaS?

| | WP Career Board Pro | Job Board SaaS |
|---|---|---|
| Revenue | You keep 100% (minus Stripe fee) | Platform takes a cut or charges per posting |
| Data | Your WordPress database | Third-party platform |
| Customization | Block editor + field builder | Whatever the platform allows |
| Pricing model | One plugin license | Monthly/annual subscription |
| BuddyPress integration | Native | None |
| Reign Theme styling | Automatic | None |

---

## CTA SECTION

**Heading:** Turn your job board into a revenue source

**CTA:** Upgrade to WP Career Board Pro →
