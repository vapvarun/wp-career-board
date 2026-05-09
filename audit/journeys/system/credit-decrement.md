---
id: credit-decrement
priority: high
personas: employer.figma
requires: mu:autologin, pro:credits, seed:credit_ledger
last_verified: 2026-05-09
needs: cli
---

# Posting a job decrements the employer's credit balance (Pro)

**Why this journey exists:** the Pro `credits` module is a paid-feature gate. If posting a job doesn't decrement, the plugin gives away free posts. If it over-decrements, customers complain. Verify both directions: the wallet decrements by exactly 1 (or the package amount), and at zero balance the post is blocked with a clear message.

## Steps

1. Skip cleanly if Pro is not active: `wp plugin is-active wp-career-board-pro` → if false, mark journey `skipped: pro_inactive` and exit success
2. As `varundubey` (admin), seed credits for `employer.figma` to exactly 2: insert into `wp_wcb_credit_ledger` with `employer_id=<figma-id>`, `entry_type=grant`, `amount=2`, `note='Smoke journey credit-decrement seed'`
3. Read current balance: `wp eval 'echo \WCB\Pro\Credits\Wallet::balance(<figma-id>);'` (or whatever the public method is — read `modules/credits/`) → expect `2`
4. As `employer.figma` (autologin), POST a new job via REST as in `customer/employer-post-job.md` step 3 → expect HTTP 201
5. Re-read balance → expect `1` (decremented by 1)
6. Verify a debit row landed in the ledger: `wp db query "SELECT entry_type, amount FROM wp_wcb_credit_ledger WHERE employer_id=<figma-id> ORDER BY id DESC LIMIT 1"` → expect `entry_type=debit, amount=1` (or matching package definition)
7. POST another job → expect 201, balance now `0`
8. POST a third job → expect HTTP 402 (or 403 with code `wcb_credits_exhausted` / `wcb_out_of_credits` — read code), response NOT 500
9. As employer, navigate to the employer dashboard → expect a clear "out of credits" notice (NOT a silent failure or a generic error)
10. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
# Delete the smoke jobs and reset the ledger
wp post delete $(wp post list --post_type=wcb_job --author=49 --post_title__like='Smoke Journey%' --field=ID) --force 2>/dev/null
wp db query "DELETE FROM wp_wcb_credit_ledger WHERE note LIKE 'Smoke journey%'"
```

## Notes

- Credits decrement is hookable — if a customer disables it via filter `wcbp_credits_charge_enabled`, the journey should detect this and skip with reason `credits_disabled_by_filter`.
- Stripe driver may add an extra `wcb_credit_gateway_log` row on top-up — not relevant here since we're testing decrement only.
