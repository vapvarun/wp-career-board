# Job Map

> **Pro feature** - Requires the WP Career Board Pro plugin to be installed and active. Every Pro feature works as soon as the plugin is active; the license key only powers automatic updates, it never gates functionality.

The Job Map block displays an interactive map of job locations alongside your listings. As candidates filter jobs, the map updates in real time - no page reload.

## How It Works

The Job Map shares the same search state as the Job Listings and Job Filters blocks. When a visitor searches by keyword, filters by category, or selects a job type, the map instantly updates to show only matching pins.

Clicking a pin on the map opens a small popup with the job title and a "View Job" link.

## Requirements

- Jobs must have a **Location** set (the `wcb_location` taxonomy term, e.g. city, region, or country)
- Locations are geocoded to latitude/longitude automatically when the job is created

A job's coordinates are stored in the `_wcb_lat` and `_wcb_lng` post meta. You can also set these directly when importing jobs from CSV (the `lat` and `lng` columns).

## Map Providers

The Job Map supports three providers. The default is **Leaflet** with OpenStreetMap tiles, which works out of the box with no API key.

| Provider | API key needed | Notes |
|---|---|---|
| **Leaflet / OpenStreetMap** | No | Default. Zero configuration. Uses OpenStreetMap tiles and Nominatim geocoding. |
| **Google Maps** | Yes (API key) | Set the key under Map Settings. Get one at console.cloud.google.com. |
| **Mapbox** | Yes (access token) | Set the token under Map Settings. Get one at account.mapbox.com. |

### Choosing a Provider

1. Go to **Career Board -> Settings -> Integrations**
2. Find the **Map Settings** card
3. Pick a **Map Provider** from the dropdown
4. If you chose Google Maps or Mapbox, paste the API key / access token in the matching field
5. Click **Save Integrations**

The provider can also be overridden per board. Open a board (Career Board -> Settings -> Boards -> Edit) and set the **Map Provider** field in the **Board Settings** meta box. A board's setting takes priority over the global default; if a board leaves it on the default, the global Map Provider is used.

## Adding the Job Map

1. Open your jobs page in the WordPress editor
2. Click **+** and search for "Job Map" (block name `wcb/job-map`)
3. Insert the block

**Recommended layout** - two columns with Map on the left, Listings on the right:

```
[ Job Search ] [ Job Filters ]
[ Job Map     ] [ Job Listings ]
```

This gives candidates both a spatial view and a list view simultaneously.

## Block Settings

| Setting | Default | Description |
|---|---|---|
| **Map Height** | 480 | Height of the map canvas in pixels |
