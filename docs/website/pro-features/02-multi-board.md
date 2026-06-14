# Multi-Board Engine

> **Pro feature** - Requires the WP Career Board Pro plugin to be installed and active. Every Pro feature works as soon as the plugin is active; the license key only powers automatic updates, it never gates functionality.

The Multi-Board Engine lets you segment one WordPress install into multiple independent job boards (for example "Tech Jobs", "Marketing Jobs", "Remote Only"). Each board carries its own per-board configuration and scopes the jobs assigned to it.

Boards are administrator-only configuration. They are created and managed from wp-admin; they are not a per-employer or front-end self-service feature.

## What You Get

- **Multiple boards** - create as many boards as you need
- **Board scoping** - a job is linked to a board via its `_wcb_board_id` meta, so listings can be filtered to a single board
- **Per-board settings** - each board has its own credit cost, moderation mode, expiry, currency, map provider, and AI toggle
- **Board-scoped listings** - the Job Listings block accepts a `boardId` attribute (or `[wcb_job_listings boardId="42"]` shortcode) to render only one board's jobs anywhere on the site

## Where Boards Live

Boards are managed at **Career Board -> Settings -> Boards**. The free plugin creates one board automatically on activation, named **Main Board**, which becomes the default.

## Creating a Board

1. Go to **Career Board -> Settings -> Boards**
2. Click **Add Board** (this opens the standard WordPress editor for the board)
3. Enter the board **Title** - this is the board name
4. Configure the **Board Settings** meta box (see below)
5. Click **Publish**

The Boards list shows each board's job count, number of pipeline stages, and credit cost, with Edit and Delete actions. Deleting a board removes its pipeline stages and unlinks (but does not delete) any jobs assigned to it - those jobs stay visible but are no longer board-restricted.

## Board Settings

Open a board and use the **Board Settings** meta box on the board edit screen:

| Setting | Description |
|---|---|
| **Credit Cost Per Job** | Credits deducted when an employer posts to this board. 0 means free. |
| **Moderation** | "Use global default", "Auto-publish", or "Requires approval" for jobs posted to this board. |
| **Job Expiry (days)** | Days until jobs on this board expire. 0 follows the site-wide default. |
| **Currency** | Salary currency for this board (from the plugin currency catalog). |
| **Map Provider** | Leaflet / OpenStreetMap, Google Maps, or Mapbox for this board's Job Map. |
| **Enable AI Features** | Turns the AI features on for jobs and applicants on this board. |

## Assigning Jobs to a Board

A job's board is stored in its `_wcb_board_id` meta. Jobs can be assigned a board through the posting flow, through the CSV importer (the `board_id` column), or by an integration that sets the meta. A job posted without a board falls back to the default board (`wcb_default_board_id`).

## Default Board

The first board created on activation ("Main Board") is stored in the `wcb_default_board_id` option and is used for any job that is posted without an explicit board. There is no separate "set as default" control in the Boards list - the default is the board recorded in that option.

## Per-Board Pipeline Stages

Each board can carry its own application pipeline stages, stored in the `wcb_application_stages` table keyed by `board_id`. The Boards list shows how many stages each board has. The stages drive the status columns shown on the employer dashboard's Applications board (the List / Board Kanban toggle).

## Rendering a Single Board's Jobs

To show one board's jobs on a page, set the **Board** attribute on the Job Listings block, or use the shortcode form:

```
[wcb_job_listings boardId="42" perPage="6"]
```

Replace `42` with the board's post ID. Without a `boardId`, the Job Listings block shows jobs across all boards.
