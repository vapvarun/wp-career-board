# Your Credit Balance (Employer View)

If your site uses the **Credit System**, this is what you, the
employer, see and how the flow works from your side. The admin-side
setup is covered in [admin-guide/06-credit-system.md](../admin-guide/06-credit-system.md).

**This doc only applies when:** your site admin has enabled the
Credit System and you, as an employer, are required to spend credits
to post jobs.

## Where to See Your Balance

**Career Board → Employer Dashboard.** The Credits panel in the
sidebar shows:

- Your current balance.
- A "Buy Credits" button (only appears when the admin has set up a
  purchase page).
- A "View transaction history" link to see every top-up and deduction.

The balance also appears as a banner on the **Post a Job** page when
you're about to submit — so you don't have to leave the form to
check.

## How Credits Are Spent

The cost depends on which **Board** you're posting to. Different
boards can have different per-post prices (admin sets this up).

**The sequence:**

1. You start filling the Post a Job form.
2. The banner at the top reads, e.g., "Posting deducts 1 credit.
   Balance after: 99 (currently 100)."
3. You submit the job.
4. Credits are **held** — reserved but not yet consumed.
5. If your site requires admin approval, the credits stay held until
   the admin acts.
6. **If approved** — credits are deducted permanently.
7. **If rejected** — held credits are released back to your balance.
8. If you withdraw the job before approval, held credits are released.

## Insufficient Credits

If your balance is below the cost when you try to submit:

- The submit button is disabled.
- The banner shows: "This board requires N credits. Your balance: M.
  Please purchase more credits."
- A "Buy Credits" link appears (when the admin has configured a
  purchase page).

You can save the form as a **Draft** in this state — your job won't
post, but the form contents are preserved so you don't have to
re-type when you top up.

## Buying Credits

What you see when you click **Buy Credits** depends on how your
admin set up the purchase flow:

| If your admin set up... | You'll see... |
|---|---|
| WooCommerce store | A standard WooCommerce product page or cart |
| Paid Memberships Pro | A PMPro membership level checkout |
| MemberPress | A MemberPress signup page |
| Custom URL | Whatever page the admin pointed it at |

Complete the checkout the same way you would buy any product. Once
the order completes, credits are added to your balance automatically
and you can immediately go back to posting your job.

If the order goes into **Pending** (e.g. bank transfer payments),
credits are NOT added until the order moves to **Completed** — check
with your admin if you've paid but credits haven't appeared after a
few minutes.

## Featured Job Upgrades

If the admin has enabled featured upgrades, you can pay extra credits
to promote your job to the top of search results for a configurable
duration. The "Featured" toggle appears on the Post a Job form with
the credit cost shown next to it.

Once a featured job's window expires, the listing stays — it just
loses the boost. You can renew the upgrade from your Employer
Dashboard → Jobs → Featured tab.

## Transaction History

Your Employer Dashboard → Credits → History shows every:

| Type | What it means |
|---|---|
| **Top-up** | You bought credits (or admin granted them) |
| **Hold** | Credits reserved for a pending job |
| **Deduct** | Credits consumed for an approved job |
| **Refund** | Credits returned (job rejected or you withdrew) |

The history is your audit trail. If you ever dispute a charge, this
is what to screenshot.

## Refunds

If you posted a job that turned out to be wrong or got rejected,
held credits return automatically. For an already-deducted job that
the admin agrees should be refunded, **contact your site admin** —
they can issue a manual refund from their Career Board → Reports
panel. Auto-refund of deducted credits is not available because
the job has been live and received exposure.

## Frequently Asked

**Do credits expire?**

By default no — your balance never expires. Some sites configure
this differently (e.g., monthly membership credits that reset each
cycle). Check with your admin if you're on a recurring plan.

**Can I gift credits to a colleague at the same company?**

Not directly. Credits are per-account. If your colleague is an
employer at the same company, they need their own account and their
own credits. Contact the admin if you want to share a pool — they
can grant credits manually to either account.

**I bought credits but they're not showing.**

Wait 1–2 minutes (the dashboard caches balance briefly), then log
out and back in. If still missing, send your admin the order
number — they can verify the payment landed and grant the credits
manually if the automatic flow didn't fire.

## Related

- [02-post-a-job.md](02-post-a-job.md) — The full post-a-job flow
  including the credit banner.
- [admin-guide/06-credit-system.md](../admin-guide/06-credit-system.md)
  — Admin-side credit setup, for site owners.
