# Developer Guide - Overview

You can extend WP Career Board without editing its source. Use actions and filters to change behavior, the `wcb/v1` REST API to read and write data, WP-CLI to automate tasks, and theme template overrides to change the markup of job and company pages.

**Use this guide when:**

- You are building a custom job-board theme or feature.
- You are writing a companion plugin that integrates with Career Board, such as a Slack notifier, a CRM sync or a custom apply flow.

If you run a job board and do not write code, use the for-employers, for-candidates and admin-guide sections instead.

## How the plugin is organized

| Layer | Where | Purpose |
|---|---|---|
| Blocks | `blocks/<name>/render.php` and `view.js` | Customer-facing UI, rendered on the server and hydrated by the Interactivity API |
| Shortcodes | `core/class-plugin.php` | Shortcode wrappers around the frontend blocks |
| REST API | `api/endpoints/class-*-endpoint.php` | Routes under `wcb/v1`, all extending `WCB\Api\RestController` |
| Modules | `modules/<area>/` | Feature areas: account, antispam, applications, boards, candidates, employers, gdpr, jobs, moderation, notifications, search, seo |
| Core services | `core/class-*.php` | Shared services such as settings, abilities, locations and the theme accent bridge |
| CLI | `cli/class-*.php` | The `wp wcb` commands |

Conventions:

- Functions, hooks, options and meta keys are prefixed `wcb_`.
- Abilities use the `wcb/<slug>` format, for example `wcb/post-jobs`.
- REST routes register through `WCB\Api\RestController`.

## Contents

| Doc | What's inside |
|---|---|
| [02-hooks-reference.md](02-hooks-reference.md) | The actions and filters the plugin fires, grouped by area |
| [03-rest-api.md](03-rest-api.md) | The REST routes with permissions, parameters and response fields |
| [04-wp-cli.md](04-wp-cli.md) | WP-CLI commands and options |
| [05-extension-cookbook.md](05-extension-cookbook.md) | Short recipes for common extension tasks |
| [06-template-overrides.md](06-template-overrides.md) | Copying a plugin template into a theme, and the Site Health check that keeps it current |

## Companion plugin development

If you are building a companion plugin like Pro, read the Pro developer guide page "Extending Free - The Canonical Pro Contract". It shows how an add-on extends Free's hooks, blocks and credit flow without forking it.
