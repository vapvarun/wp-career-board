# Job Map

> **Pro feature** — Requires WP Career Board Pro.

The Job Map block displays an interactive map of job locations alongside your listings. As candidates filter jobs, the map updates in real time — no page reload.

## How It Works

The Job Map shares the same search state as the Job Listings and Job Filters blocks. When a visitor searches by keyword, filters by category, or selects a job type, the map instantly updates to show only matching pins.

Clicking a pin on the map opens a small popup with the job title and a "View Job" link.

## Requirements

- Jobs must have a **Location** set (city, region, or full address)
- The location is geocoded automatically when the job is saved

No API key is required. The map uses **Leaflet** with OpenStreetMap tiles — it works out of the box.

## Adding the Job Map

1. Open your jobs page in the WordPress editor
2. Click **+** and search for "Job Map"
3. Insert the block

**Recommended layout** — two columns with Map on the left, Listings on the right:

```
[ Job Search ] [ Job Filters ]
[ Job Map     ] [ Job Listings ]
```

This gives candidates both a spatial view and a list view simultaneously.

## Block Settings

| Setting | Default | Description |
|---|---|---|
| **Map Height** | 480px | Height of the map canvas in pixels |

## Remote Jobs

Jobs posted as **Remote** appear in a special "Remote" cluster rather than a specific geographic pin, keeping the map accurate for fully remote roles.
