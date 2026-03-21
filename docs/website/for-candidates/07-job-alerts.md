# Job Alerts

> **Pro feature** — Requires WP Career Board Pro.

Job Alerts let candidates subscribe to saved searches and receive email notifications when new matching jobs are posted. Candidates set a frequency — instant, daily, or weekly — and WP Career Board sends digests automatically.

## How It Works

1. A candidate searches or filters jobs on your board
2. They click **Save Alert** in the Job Alerts block
3. New jobs that match their saved filters trigger an email at their chosen frequency
4. Every alert email includes an unsubscribe link

## Adding the Job Alerts Block

Add the **Job Alerts** block to your jobs page. The recommended placement is below the Job Filters block:

```
[ Job Search ]
[ Job Filters ]
[ Job Alerts  ]  ← subscribe to current search
[ Job Listings ]
```

The block automatically reads the current search state — whatever filters the visitor has applied become the alert criteria when they subscribe.

## Alert Frequency Options

| Frequency | When the email sends |
|---|---|
| **Instant** | As soon as a matching job is posted |
| **Daily** | Once per day (morning digest) |
| **Weekly** | Once per week |

Candidates choose their preferred frequency at the time of subscription and can change it from their dashboard.

## Candidate Experience

### Logged-In Candidates

Logged-in candidates see a **Save as Alert** button in the Job Alerts block. After clicking:
1. A frequency selector appears (Instant / Daily / Weekly)
2. They click **Save Alert**
3. The alert is active immediately

### Guest Visitors

Guests are asked for their email address. After submitting, they receive a confirmation email with a double opt-in link. The alert only activates after they confirm.

## Managing Alerts

Candidates manage their active alerts from **Candidate Dashboard → My Alerts**:
- View all active alerts with their saved filters
- Change frequency
- Delete an alert

## Admin: Viewing Alerts

Admins can view all active alerts per user from **WP Career Board → Candidates → [candidate name] → Alerts**.

## Email Delivery

Alert emails are sent via the Notifications settings configured in **WP Career Board → Settings → Notifications** (From Name, From Email). Configure an SMTP plugin for reliable delivery.
