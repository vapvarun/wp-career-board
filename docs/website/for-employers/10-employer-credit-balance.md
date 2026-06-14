# Your Credit Balance (Employer View)

If your site uses the **Credit System**, this is what you, the
employer, see and how the flow works from your side. The admin-side
setup is covered in [admin-guide/06-credit-system.md](../admin-guide/06-credit-system.md).

**This doc only applies when:** your site admin has enabled the
Credit System and you, as an employer, are required to spend credits
to post jobs.

## Where to See Your Balance

Open your **Employer Dashboard**. When the Credit System is enabled, a
**CREDITS** section appears in the dashboard sidebar showing:

- **Balance** - your current credit balance.
- **Buy Credits** - a link that appears only when the admin has set up
  a purchase page.

The balance also appears as a banner on the **Post a Job** form when
you're about to submit, so you don't have to leave the form to check.
A low-balance notice shows when your balance dips below the threshold
the admin set.

## How Credits Are Spent

The cost depends on which **Board** you're posting to. Different
boards can have different per-post prices (admin sets this up).

**The sequence:**

1. You start filling the Post a Job form.
2. The banner at the top reads, e.g., "Posting deducts 1 credit.
   Balance after: 99 (currently 100)."
3. You submit the job.
4. Credits are **held** - reserved but not yet consumed.
5. If your site requires admin approval, the credits stay held until
   the admin acts.
6. **If approved** - credits are deducted permanently.
7. **If rejected** - held credits are released back to your balance.
8. If you withdraw the job before approval, held credits are released.

## Insufficient Credits

If your balance is below the cost when you try to submit:

- The submit button is disabled.
- The banner shows: "This board requires N credits. Your balance: M.
  Please purchase more credits."
- A "Buy Credits" link appears (when the admin has configured a
  purchase page).

You can save the form as a **Draft** in this state - your job won't
post, but the form contents are preserved so you don't have to
re-type when you top up.

## Buying Credits

The **Buy Credits** link sends you to whatever purchase page your
admin configured (the admin sets this URL). It could be a checkout
page, a pricing page, or any page the admin points it at. The link
only appears when that purchase page has been set up.

Complete the purchase the way your admin's page asks. Once the
purchase clears, credits are added to your balance and you can go
back to posting your job.

If a payment is still pending (for example a bank transfer), credits
are not added until the payment clears - check with your admin if
you've paid but credits haven't appeared after a few minutes.

## Featured Job Upgrades

If the admin has enabled featured upgrades, you can pay extra credits
to promote your job to the top of search results for a configurable
duration. The "Featured" toggle appears on the Post a Job form with
the credit cost shown next to it.

Featured listings expire automatically after the duration the admin
configured (30 days by default). Once a featured job's window expires,
the listing stays - it just loses the boost. To feature it again, post
or edit the job and re-enable the Featured toggle.

## Transaction Types

Behind your balance, the Credit System tracks each movement:

| Type | What it means |
|---|---|
| **Top-up** | You bought credits (or the admin granted them) |
| **Hold** | Credits reserved for a pending job |
| **Deduct** | Credits consumed for an approved job |
| **Refund** | Credits returned (job rejected or you withdrew) |

If you ever dispute a charge, ask your site admin to check the ledger
for your account - they can see every top-up, hold, deduction, and
refund tied to your balance.

## Refunds

If you posted a job that turned out to be wrong or got rejected,
held credits return automatically. For an already-deducted job that
the admin agrees should be refunded, **contact your site admin** -
they can grant credits back to your account manually. Auto-refund of
deducted credits is not available because the job has been live and
received exposure.

## Frequently Asked

**Do credits expire?**

By default no - your balance never expires. Some sites configure
this differently (e.g., monthly membership credits that reset each
cycle). Check with your admin if you're on a recurring plan.

**Can I gift credits to a colleague at the same company?**

Not directly. Credits are per-account. If your colleague is an
employer at the same company, they need their own account and their
own credits. Contact the admin if you want to share a pool - they
can grant credits manually to either account.

**I bought credits but they're not showing.**

Wait 1–2 minutes (the dashboard caches balance briefly), then log
out and back in. If still missing, send your admin the order
number - they can verify the payment landed and grant the credits
manually if the automatic flow didn't fire.

## Related

- [02-post-a-job.md](02-post-a-job.md) - The full post-a-job flow
  including the credit banner.
- [admin-guide/06-credit-system.md](../admin-guide/06-credit-system.md)
  - Admin-side credit setup, for site owners.
