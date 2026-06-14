# Progressive Web App (Pro)

The PWA module turns your job board into an installable Progressive Web App. Candidates can add it to their phone's home screen and browse job listings even with a poor connection.

> **Pro feature** - Requires the WP Career Board Pro plugin to be installed and active. Every Pro feature works as soon as the plugin is active; the license key only powers automatic updates, it never gates functionality.

## What the PWA Provides

| Feature | Description |
|---------|-------------|
| **Install prompt** | Browsers prompt candidates to install the job board as a home screen app |
| **Offline browsing** | Previously visited job listing pages load from cache when the device is offline |
| **Network-first forms** | Application forms and dashboards always try the network first - never served stale from cache |
| **Branded splash screen** | The app name (`{Site Name} Jobs`), your chosen theme color, and the WordPress Site Icon are used for the install and splash experience |
| **VAPID key pair** | A VAPID public/private key pair is generated once on plugin activation and stored in options, ready for future push-notification support |

## How It Works

The module serves two files from your site's root:

- `/wcb-manifest.json` -- Web App Manifest describing the app name, icon, theme color, and display mode
- `/wcb-service-worker.js` -- Service worker that intercepts fetch events on WCB pages

The service worker uses **stale-while-revalidate** for in-scope job pages (paths starting with `/jobs/`, `/companies/`, `/candidates/`): the cached version loads instantly while a fresh copy is fetched in the background. Apply requests and dashboard pages use **network-first**: they always try the network and fall back to cache only if the network is unavailable.

The manifest `<link>` tag and the service-worker registration script are only injected on WCB-related pages (the job archive, single job pages, and the configured Jobs archive / Employer Dashboard / Candidate Dashboard pages).

### App Icon

The manifest pulls its icon from the **WordPress Site Icon** (Settings -> General -> Site Icon). The plugin does not ship a default icon: if no Site Icon is set, the manifest is served without an icon entry and the browser falls back to the favicon. Set a Site Icon to get a branded install/splash icon.

## Setup

### Configure the Theme Color

1. Go to **Career Board -> Settings -> Integrations**
2. Find the **PWA Settings** card
3. Pick a **Theme Color** -- this is the brand color shown in the mobile browser chrome bar and on the splash screen when the app launches
4. Click **Save Integrations**

The default theme color is `#4f46e5` (indigo).

The manifest and service worker are served directly from `/wcb-manifest.json` and `/wcb-service-worker.js` (the module matches the request URI on `init` - it does not rely on permalink rewrite rules), so no permalink flush is required for the PWA.

## Browser Support

The PWA install prompt and service worker work in:

- Chrome and Edge (Android and desktop)
- Safari 16.4+ (iOS and macOS)
- Firefox (service worker only -- no install prompt on Firefox for Android)

Browsers that do not support service workers continue to work normally -- the module degrades gracefully.

## Verifying Installation

Open your job listings page in Chrome on Android. After a few seconds, Chrome displays an **Add to Home screen** banner at the bottom of the browser. Tap it to install. The app opens in standalone mode (no browser toolbar) with your chosen theme color.

On desktop Chrome, look for the install icon in the address bar.
