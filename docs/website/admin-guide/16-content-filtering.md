# Content filtering for blocked members

When a member blocks another member, neither of them sees the other's job listings. You do not need to set anything up.

## What is hidden

If either member has blocked the other, the signed-in member does not see the other's jobs in:

- The job listings on the Find Jobs page and in the Job Listings block.
- The single job page. It shows a 404 page instead.
- The jobs REST API (`GET /wcb/v1/jobs` and a single job), which the mobile app also uses.

It works both ways. It does not matter which member did the blocking.

Visitors who are not signed in are not affected, because they have no block list.

## Settings

There is no setting for this. To remove the effect, the member unblocks the other member from their account. See [Moderation](03-moderation.md#members-blocking-members) for how blocking works.

## Related

- [Moderation](03-moderation.md)
- [Reported jobs](03-moderation.md#reported-jobs-flagged)
