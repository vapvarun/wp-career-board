# Progressive Web App (Pro)

The PWA module turns your job board into an installable Progressive Web App. Candidates can add it to their phone's home screen and browse job listings even with a poor connection.

> **Requires WP Career Board Pro** with a valid license key.

## What the PWA Provides

| Feature | Description |
|---------|-------------|
| **Install prompt** | Browsers prompt candidates to install the job board as a home screen app |
| **Offline browsing** | Previously visited job listings load from cache when the device is offline |
| **Network-first forms** | Application forms and dashboards always fetch fresh data -- never served stale from cache |
| **Branded splash screen** | The app name, theme color, and icon match your site's branding |
| **VAPID key pair** | Generated automatically on plugin activation for future push notification support |

## How It Works

The module serves two files from your site's root:

- `/wcb-manifest.json` -- Web App Manifest describing the app name, icon, and display mode
- `/wcb-service-worker.js` -- Service worker that intercepts fetch events on WCB pages

The service worker uses **stale-while-revalidate** for job listing pages (`/jobs/`, `/companies/`, `/candidates/`): the cached version loads instantly while a fresh copy is fetched in the background. Application forms and dashboard pages use **network-first**: they always try the network and fall back to cache only if the network is unavailable.

The manifest and service worker are only injected on WCB-related pages (job archives, single job pages, and the configured Employer/Candidate Dashboard pages).

## Setup

### Step 1: Configure the Theme Color

1. Go to **Career Board -> Settings -> Integrations**
2. Find the **PWA Settings** card
3. Pick a **Theme Color** -- this is the brand color shown in the browser toolbar and on the splash screen when the app launches
4. Click **Save**

The default theme color is `#4f46e5` (indigo).

### Step 2: Flush Permalinks

After first activating Pro, go to **Settings -> Permalinks** and click **Save Changes**. This registers the rewrite rules needed to serve `/wcb-manifest.json` and `/wcb-service-worker.js`.

## Browser Support

The PWA install prompt and service worker work in:

- Chrome and Edge (Android and desktop)
- Safari 16.4+ (iOS and macOS)
- Firefox (service worker only -- no install prompt on Firefox for Android)

Browsers that do not support service workers continue to work normally -- the module degrades gracefully.

## Verifying Installation

Open your job listings page in Chrome on Android. After a few seconds, Chrome displays an **Add to Home screen** banner at the bottom of the browser. Tap it to install. The app opens in standalone mode (no browser toolbar) with your chosen theme color.

On desktop Chrome, look for the install icon in the address bar.
