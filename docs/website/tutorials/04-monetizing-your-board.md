# Monetizing Your Board

How to make money from your job board. Covers the four models WP
Career Board supports, when each one is the right call, and the
mechanics of setting each up.

> **Free vs Pro.** WP Career Board **Free** supports one monetization
> model: free postings. Every paid model below - pay-per-post, credit
> packages, and subscriptions - requires **WP Career Board Pro**. Pro
> ships the credit ledger (the `wcb_credit_ledger` table) and the Wbcom
> Credits SDK, which provides the WooCommerce, WooCommerce
> Subscriptions, WooCommerce Memberships, Paid Memberships Pro, and
> MemberPress adapters. The credit fields you see in Free's job form
> (cost, balance, Buy Credits link) are filter-driven stubs that
> default to free/zero until Pro fills them in. The admin screens this
> page mentions for credits, mappings, and the ledger are Pro screens.

## The four models

| Model | What employers pay for | When it fits | Setup complexity |
|---|---|---|---|
| **Free** | Nothing - postings are free | Niche communities, employer-branding boards, sites where the goal is engagement not revenue | Trivial |
| **Pay-per-post** | A flat fee per job listing | Small to medium boards with predictable per-job value | Low |
| **Credits / packages** | Bundles of postings purchased upfront | Recruiters or agencies who post multiple times per month - cheaper per-post when bought in bulk | Medium |
| **Subscriptions** | Recurring monthly / annual access for unlimited (or N) postings | High-volume employers, boards positioned as "Indeed for niche X" | Medium |

You can also combine: free for one type of role (e.g. internships),
paid for premium roles, with optional "featured" upgrades on top.

## Model 1 - Free postings

The simplest model. Employers register and post without paying. You
fund the board through:

- **Sponsorships** - a banner ad slot on the home page (your theme),
  sponsored by a company that wants exposure.
- **Affiliate / commission** - Career Board doesn't track placements;
  for affiliate, integrate manually or use a third-party tool.
- **Adjacent product / service** - a paid plugin, a course, a
  recruiting service, etc. The job board drives traffic; you sell
  something else.

### Setup

1. **WP Admin → Career Board → Settings → Job Listings.**
2. **Posting cost:** Free is the default - there is no per-post cost in
   the Free plugin, so there is nothing to set. (Per-post credit cost
   is a Pro feature.)
3. **Moderation:** the **Auto-Publish Jobs** toggle controls this. It
   is OFF by default, so new postings sit at Pending Review until an
   admin approves them. For free boards keep it off - spammers love
   free boards. Turn it on only if you trust your posters.

### When this is right

- You have an existing audience (community, newsletter, etc.) and
  the board is a service for them, not a product itself.
- Your goal is reach, not revenue.
- Spam is manageable through moderation.

### When this is wrong

- You need cash flow from the board.
- You don't have time to moderate a high-volume free queue.

## Model 2 - Pay-per-post (Pro, WooCommerce-backed)

Each new job posting requires payment. This requires Pro and a
checkout layer; WooCommerce is the most common choice.

### Setup

1. **Install WooCommerce.** Run its setup wizard. Configure payment
   gateways (Stripe / PayPal / etc.).
2. **Install Pro.** Pay-per-post needs Pro's credit ledger and the
   Wbcom Credits SDK. (The license drives updates only; the credit
   feature works once Pro is installed.)
3. **Create a "Job Posting" WooCommerce product.**
   - Type: Simple product.
   - Price: $29, $49, $99 - whatever your market bears.
   - Title: "Single Job Posting (30 days)."
4. **Map the product to a credit grant** in the Pro Credits admin tab
   (**WP Admin → Career Board → Settings → Credits → Credit
   Mappings**).
   - Adapter: WooCommerce.
   - Product: the one you just created.
   - Credits granted: 1.
5. **Set a Credits Purchase URL** in the same Credits tab, pointing at
   the WooCommerce product. The Buy Credits button only appears once
   both the mapping and the purchase URL are set.
6. **Set the per-board posting cost.** Credit cost is configured
   per board (opt-in since Pro 1.3.0). Give the board employers post to
   a cost of 1 credit. Employers now need 1 credit to post a job there.

### Flow from the employer's perspective

1. Employer clicks **Post a Job.**
2. Form shows: "You have 0 credits. Buy 1 credit to continue."
3. They click **Buy Credits**, get redirected to WooCommerce checkout.
4. After payment, credit lands on their account (via the ledger).
5. Back on the form, the credit auto-deducts and the job posts.

### When this is right

- You want predictable per-job pricing.
- Most employers post 1-3 jobs per year (one-off pricing makes sense).
- You're early days and want to gauge demand before bundling.

### Variations

- **Featured upgrade** - separate WooCommerce product priced higher
  ($99 instead of $29). Map it to "Featured upgrade" consumer in
  Career Board. Employers can buy it during the post flow.
- **Tiered pricing** - different products per category. Senior /
  executive postings cost more than entry-level. Map each to a
  different category at the credit-mapping level.

## Model 3 - Credit packages (Pro)

Bulk pricing: employers buy 5 or 10 postings upfront at a discount.

### Setup

1. WooCommerce + Pro (same as Model 2).
2. Create multiple WooCommerce products:
   - "1 Job Posting" - $49 - grants 1 credit.
   - "5 Job Postings" - $199 ($40/each) - grants 5 credits.
   - "10 Job Postings" - $349 ($35/each) - grants 10 credits.
3. Map each to its credit grant in **Settings → Credits → Credit
   Mappings**.

### Flow from the employer's perspective

1. The employer sees their credit balance on the dashboard overview
   (and the Pro Credit Balance block) and clicks **Buy Credits** on the
   job form when their balance is too low.
2. They see the packages you mapped.
3. Pick a package, check out via WooCommerce.
4. Credits land on their account. They post normally; each post on a
   paid board holds, then deducts, the board's credit cost.

### When this is right

- You have repeat employers (recruiters, agencies, staffing firms).
- Volume discount is a real selling point.
- You want a stable balance sheet - money upfront, postings spread
  over months.

## Model 4 - Subscriptions (Pro)

Employers pay monthly or annually for unlimited (or capped) postings.

### Setup options

The Wbcom Credits SDK bundled in Pro ships adapters for these
membership / subscription back-ends:

| Plugin | Best for | Setup |
|---|---|---|
| **WooCommerce** | One-off product purchases | Pro + WooCommerce adapter |
| **WooCommerce Subscriptions** | Mature ecosystem, lots of payment-gateway support | Pro + WooCommerce Subscriptions adapter |
| **WooCommerce Memberships** | Membership tiers on top of WooCommerce | Pro + WooCommerce Memberships adapter |
| **Paid Memberships Pro** | Community + content-gating + jobs in one plan | Pro + PMPro adapter |
| **MemberPress** | More polished membership UX, but per-feature pricing adds up | Pro + MemberPress adapter |

(There is no Restrict Content Pro adapter; pick one of the back-ends
above.)

### Setup (PMPro example)

1. Install PMPro. Configure your gateway.
2. Create membership levels:
   - **Starter** - $49/month - 3 postings.
   - **Pro** - $199/month - unlimited postings.
   - **Annual Pro** - $1,990/year - unlimited postings (10-month
     pricing).
3. In **Settings → Credits → Credit Mappings**, map each PMPro level to
   a credit grant:
   - Starter level → 3 credits per billing cycle.
   - Pro level → 999 credits per billing cycle (effectively unlimited).
4. Credits auto-grant on subscription payment (via the PMPro adapter).

### Flow from the employer's perspective

1. Employer registers, picks a plan, completes subscription checkout.
2. Credits land on their account immediately.
3. Each billing cycle, credits refresh (subject to your "carry over
   unused?" policy - set this in the adapter mapping).
4. Cancellation: subscription stops, no new credits, existing
   credits remain until expired or used.

### When this is right

- Your board has high-volume repeat employers.
- You want recurring revenue, not one-time.
- You're competing with general job boards on volume and need a
  business model that scales.

## Combining models

Most real boards run a hybrid. Examples:

- **Free for non-profits, paid for everyone else.** Filter the
  post-a-job form based on the user's role / membership level.
- **Free postings + paid Featured upgrade.** Volume comes free, you
  monetize the employers who want visibility.
- **Free first posting** as a trial, paid thereafter. Career Board
  doesn't ship this out of the box - you'd need a tiny custom plugin
  that grants 1 credit on first registration.

## Pricing - what to actually charge

There's no universal right number, but anchors:

| Board type | Typical per-post price |
|---|---|
| **Local / city-specific** | $10-$30 |
| **Niche tech (remote-friendly)** | $50-$200 |
| **Executive / specialty** | $200-$500 |
| **Internship / academic** | $0-$30 (often free) |
| **Healthcare / regulated industry** | $100-$400 |

**Featured upgrades** typically cost 2-3× the standard price. **Bundle
discounts** typically save 20-40% over individual purchase. **Annual
subscriptions** typically price at 10× monthly (give 2 months free).

Start with the low end of the range. You can always raise later;
lowering looks bad.

## How the credit ledger handles refunds and disputes (Pro)

Pro's credit ledger is **append-only**. Every credit movement writes a
row - top-up, hold, deduct, refund. Rows are never edited or deleted.

Refund flow:

1. **WooCommerce / PMPro / MemberPress** issues the refund through
   its own checkout flow.
2. The adapter detects the refund event and writes a **refund** ledger
   row.
3. The employer's credit balance reduces by the refunded amount.
4. If the employer already posted jobs against those refunded
   credits, the job posts stay live; the balance just reflects the
   refund.
5. For disputes that don't go through the checkout (e.g. a bank
   chargeback handled externally), use the **manual adjustment** card at
   the bottom of **Settings → Credits** to write an offsetting row.
   There is no per-employer "Adjust" button and no separate ledger
   report screen - adjustments all happen from that one card.

## Tracking revenue and renewals

- **WooCommerce → Reports** for order revenue.
- **PMPro → Reports** for subscription metrics (MRR, churn).
- **Career Board → Settings → Credits (Pro)** shows the manual
  adjustment card with a user's current balance; the ledger itself is
  the append-only record behind the balance.
- For deeper analytics, query the credit ledger table or your checkout
  plugin's reports - Career Board does not ship a built-in revenue
  dashboard.

## Common monetization mistakes

- **Charging too much too early.** A new board with no traffic gets
  zero postings if pricing matches Indeed. Start cheap, build a base,
  raise as you fill listings.
- **Hiding the price.** Employers should see what postings cost
  *before* they register. A clear pricing page beats a maze.
- **No free option for testers.** Many employers want to "try one
  posting" before committing to a package. Either offer a free first
  posting or a 7-day money-back guarantee.
- **Auto-cancelling paid jobs on subscription expiry.** Confusing
  for both employers and candidates. Keep posted jobs live until
  their natural deadline; just stop *new* postings on expired
  subscriptions.
- **Not testing the buy flow.** Always run a real $1 product end-to-end
  before going live. The ledger, the email, the credit grant, the
  post flow - they all need to land.

## Where to go next

- Pro's credit-system docs (`credit-system/01-overview.md` in the Pro
  docs) - full credit system, mappings, and refund reference.
- [02-employer-end-to-end.md](02-employer-end-to-end.md) - the employer
  flow you're enabling.
