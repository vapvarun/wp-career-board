# Multi-Board Engine

> **Pro feature** — Requires WP Career Board Pro.

The Multi-Board Engine lets you run multiple independent job boards from a single WordPress install. Each board has its own set of jobs, employers, and settings — all managed from one admin.

## What You Get

- **Multiple boards** — create as many boards as you need (e.g. "Tech Jobs", "Marketing Jobs", "Remote Only")
- **Board isolation** — jobs posted to one board don't appear on others
- **Board Switcher block** — a tab bar that lets visitors switch between boards on a single page
- **Per-board settings** — each board can have its own pipeline stages, credit pricing, and custom fields

## Creating a Board

1. Go to **WP Career Board → Boards** in wp-admin
2. Click **Add New Board**
3. Fill in:

| Field | Description |
|---|---|
| **Board Name** | Visible to employers when posting (e.g. "Tech Jobs") |
| **Slug** | URL-friendly identifier, auto-generated from the name |
| **Description** | Optional internal note |

4. Click **Save**

## Assigning Jobs to a Board

When an employer posts a job, they see a **Board** dropdown in the job form (if more than one board exists). The job is assigned to their selected board.

Admins can also reassign jobs from **WP Career Board → Jobs → Edit Job**.

## Board Switcher Block

Add the **Board Switcher** block to your jobs page so visitors can tab between boards:

1. Open your jobs page in the WordPress editor
2. Insert the **Board Switcher** block above the Job Listings block
3. Save

**Page layout with Board Switcher:**

```
[ Board Switcher: All Jobs | Tech | Marketing | Remote ]
[ Job Search ] [ Job Filters ]
[ Job Listings ]
```

Clicking a tab updates the Job Listings block to show only that board's jobs — no page reload.

## Default Board

The first board you create becomes the default. Jobs posted without a board selection go to the default board.

To change the default: go to **WP Career Board → Boards** and click **Set as Default**.

## Per-Board Pipeline Stages

Each board can have its own application pipeline stages. Go to **WP Career Board → Boards**, open the board, and configure its stages independently.

## Employer Access

Admins assign employers to one or more boards:

1. Go to **WP Career Board → Employers**
2. Click the employer's name
3. In the **Boards** section, check the boards they can post to

Employers only see jobs and applications for their assigned boards.
