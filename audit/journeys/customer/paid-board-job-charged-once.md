---
id: paid-board-job-charged-once
priority: critical
personas: employer.stripe, admin
requires: mu:autologin, pro
last_verified: 2026-09-27
needs: cli
bug_ref: 10344034392, 10340693866
---

# A job on a paid board is charged exactly once, whichever path moves it

**Why this journey exists:** credits were charged by side effects of three actions that several paths never fired. Auto-publish, approving a rejected job, resubmitting and moving a job to a paid board were all free. Every reject refunded again (minting credits), the CLI approve charged twice, the reconciler refunded any hold older than 24 hours (so slow moderation meant a free post), and ten parallel posts with credit for one produced two jobs and a negative balance.

## Steps

1. Board with credit cost 1, "Requires approval". Employer balance 5. Post a job → 201, pending, balance 4
2. Approve it (REST, bulk action or `wp wcb job approve <id> --user=1`) → published, balance still 4, one deduction row for the job
3. New job, reject it three times → balance back to 5, exactly one refund row
4. Employer resubmits the rejected job → pending, balance 4 (charged again)
5. Board set to "Auto-publish", post → published, balance 4, the job's record is `settled`
6. Post on the free board, then move it to the paid board → balance drops by 1; with balance 0 the move answers 402 and the board stays
7. Balance 0, post → 402 `wcb_insufficient_credits` with `data.cost`, `data.balance`, `data.purchase_url`
8. Balance 1, reject a job, set balance 0, approve it → 402 `wcb_awaiting_payment`, the job stays pending, "Awaiting payment" in the employer dashboard and the wp-admin Jobs view; top up 1 → it publishes on its own, balance 0
9. Trash or delete a pending job → its hold comes back
10. Balance 1, ten parallel posts → exactly one 201, balance 0
11. `wp wcb credits reconcile --dry-run` → 0 (holds on live jobs are left alone)
12. tail debug.log diff → expect ZERO new fatal/warning lines

Automated: `wp eval-file wp-content/plugins/wp-career-board-pro/tests/test-job-charge.php` (steps 1-9, 11); step 10 needs real concurrent requests.

## Teardown

```bash
wp post delete <test job ids> --force
```
