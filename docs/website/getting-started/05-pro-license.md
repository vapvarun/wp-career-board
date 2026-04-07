# Installing & Activating WP Career Board Pro

> **Pro feature** — Requires a WP Career Board Pro license from [wbcomdesigns.com](https://wbcomdesigns.com).

WP Career Board Pro is an add-on plugin. It requires the free **WP Career Board** plugin to be installed and active first.

## Step 1: Install the Pro Plugin

1. Log in to your account at wbcomdesigns.com
2. Go to **My Account → Downloads**
3. Download `wp-career-board-pro.zip`
4. In your WordPress admin, go to **Plugins → Add New → Upload Plugin**
5. Select the downloaded zip and click **Install Now**
6. Click **Activate Plugin**

If the free plugin is not active, activation will be blocked with an error message. Install and activate WP Career Board (free) first, then retry.

## Step 2: Activate Your License

1. Go to **WP Career Board → Settings → License**
2. Paste your license key in the **License Key** field
3. Click **Activate License**

A confirmation shows your license status, expiry date, and how many sites are using this license.

## License Statuses

| Status | Meaning |
|---|---|
| **Active** | Valid license, updates available |
| **Expired** | License period ended — plugin still works but no updates |
| **Inactive** | Key entered but not yet activated on this site |
| **Invalid** | Key does not match any license |
| **No activations left** | All license slots used — deactivate from another site first |

## License Tiers

| Tier | Sites |
|---|---|
| Single Site | 1 site |
| Business | 5 sites |
| Agency | Unlimited sites |

## Deactivating

To move your license to a different site:

1. Go to **WP Career Board → Settings → License**
2. Click **Deactivate License**
3. Activate on the new site

You can also manage all site activations from your account at wbcomdesigns.com.

## Renewing

The plugin continues to work after expiry — you just stop receiving updates. To renew, log in to wbcomdesigns.com → **My Account → Licenses → Renew**.

## What Activates with Pro

On activation, WP Career Board Pro:

- Creates additional Pro database tables
- Adds Pro settings tabs to **WP Career Board → Settings**
- Registers 15 additional blocks in the block inserter
- Enables the Resume Builder, Field Builder, Pipeline, Credit System, Multi-Board, Job Alerts, Job Map, and AI Search modules

## Pro Setup Wizard

After activating the Pro plugin, a **Pro Setup Wizard** runs automatically to configure Pro-specific settings (pipeline stages, credits, resume page, etc.). This wizard appends its own steps to the standard wizard using the `wcb_wizard_steps` filter.

If the Free wizard already ran, the Pro wizard renders as a focused mini-wizard that handles only the Pro steps. You can re-run it any time from **WP Career Board → Settings → Run Setup Wizard**.
