# The Integration Standard (portfolio-wide)

**Applies to every plugin that shows another of our plugins' data**: BuddyNext over Jetonomy,
WPMediaVerse, WB Gamification, Career Board, Learnomy, Eventonomy, Listora, Member Blog, and any
future pair. The companion to `free-pro-seam-standard.md`: that one governs a base and its
extension (vertical); this one governs two products that meet (horizontal).

Each plugin keeps a synced copy at `docs/standards/integration.md`. Do not re-explain per plugin.
Change this file first, then re-sync the copies.

---

## The one-line thesis

**Every rule, sentence and permission lives in exactly one plugin: the one that owns the data.
The other plugin renders it and calls it. Anything written twice will drift, and the drift is
the bug.**

---

## 1. Ownership: who writes what

| | The data plugin (Jetonomy, MediaVerse, ...) | The application layer (BuddyNext) |
|---|---|---|
| Rules (who may see, act, message) | Owns them, exposes them as a function or filter | Calls them. Never re-implements |
| Words (notification text, labels) | Writes and translates them, plain text | Displays them |
| Links | Builds them | Uses them |
| Email for its features | Sends it | Never sends it |
| Screens, bell, push, feed cards | Stays out when the application layer is active | Owns them |

When BuddyNext is the screen for a feature (MediaVerse follows, comments, favorites, DMs), the
data plugin is told to stand down for that feature (for example `mvs_should_send_notification`).
One feature, one owner, decided and written down. Never both.

## 2. No duplicate code: the rules

1. **Manifest first.** Before adding a function, hook, route or helper, read `audit/manifest.json`
   and the plugin's CLAUDE.md in BOTH plugins. If it exists, call it.
2. **Missing door = add it to the owner.** If the application layer needs a rule the data plugin
   has no public door for, add the door to the data plugin (PR on its dev branch). Never copy the
   logic across, not even "for now".
3. **One helper per plugin family.** Shared logic lives in the free plugin; Pro calls it. A second
   copy in Pro is a defect (see `free-pro-seam-standard.md`).
4. **No parallel paths.** When a new path replaces an old one, the old one is deleted in the same
   release. A switch that keeps both alive ("stand down when adopted") is allowed only while the
   two sides ship in different releases, and it carries a comment naming the release that removes
   it. We have no legacy install base (BuddyNext is new), so the default is lockstep: both sides
   in one release, no switch.
5. **Protocol, not code, crosses the boundary.** Plugins agree on hook names, payload shapes and
   filter signatures (below). They never include each other's classes for logic, never write each
   other's tables, and guard every cross-plugin call with `function_exists` / `class_exists`.
6. **No dead code.** A guard that makes a branch unreachable removes the branch.

## 3. Bridge rules (application layer)

1. **State-driven sync.** One sync per partner object that reads the partner row's CURRENT state
   on every transition (create, approve, restore, trash, edit, purge).
2. **Withdraw, don't delete.** Reversible removal hides the card; only a permanent purge deletes;
   restore brings back the same card.
3. **One notification per action**, from the source event, never as a side effect of a mirror.
   Mirror writes never notify.
4. **Namespaced object types** for partner rows in shared tables (`jetonomy_post`, `mvs_media`),
   never a bare `post` holding a partner id.
5. **Partner visibility.** The partner answers who may see its rows and cards (see 4.3).
6. **Plain text** from partners into plain-text surfaces. No partner HTML.
7. **Background work in the job.** Deferred work (card creation, carry-over, email) runs inside
   the Action Scheduler job, not the member's request.

## 4. Notification contract

One inbox, like Facebook. The data plugin decides when to notify and writes the words; BuddyNext
shows it (bell, grouping, push, a settings switch per type); the data plugin emails.

### 4.1 Payload, as the LAST argument of the plugin's existing hook

| Plugin | Hook (unchanged) | Prefix | Source slug |
|---|---|---|---|
| Jetonomy | `jetonomy_notification_created` | `jetonomy` | `jetonomy` |
| WP Career Board | `wcb_notification_created` | `wcb` | `career_board` |
| Learnomy | `learnomy_send_notification` | `learnomy` | `learnomy` |
| Eventonomy | `evnm_notification_dispatch` | `evnm` | `eventonomy` |
| WPMediaVerse | `mvs_notification_created` | `mvs` | `mediaverse` |
| WB Gamification | `wb_gam_notification_created` (payload only) | `wb_gam` | `wb_gamification` |

A new plugin joins through BuddyNext's `buddynext_notification_sources` filter.

```php
array(
	'recipient_id'    => 42,                   // required
	'type'            => 'reply_to_post',      // required, the plugin's own slug
	'actor_id'        => 7,                    // 0 for system notices
	'object_type'     => 'post',               // the plugin's own object kind
	'object_id'       => 1153,
	'message'         => 'Aisha replied to "Welcome thread".',     // required, translated plain text
	'message_grouped' => '{actor} and {others} replied to "Welcome thread".', // grouped rows
	'message_single'  => '{actor} replied to "Welcome thread".',              // grouped rows
	'url'             => 'https://example.com/...',               // required
	'group_key'       => 'reply_to_post_1153', // per object + subtype, never per actor
	'item_type'       => 'reply',              // grouped rows: the thing THIS event is about
	'item_id'         => 88,
	'context'         => array( 'type' => 'space', 'id' => 12, 'label' => 'Design Critique' ),
	'notification_id' => 991,                  // the plugin's own row id
	'renotify'        => true,                 // false = a running notice (see below)
)
```

`{actor}` = latest actor's name; `{others}` = BuddyNext's translated "1 other" / "3 others".
Never fire for the actor themself or during an import. Build the payload in ONE helper.

**Grouped rows** ("Aisha and 2 others replied"). One row per object, so the `object_*` is the
container (the topic) and `group_key` names it. Each event also says what it was: `item_type` +
`item_id` (the reply). BuddyNext keeps the events as (person, item), newest first, and counts
PEOPLE, not events, so one person replying twice is one person however the others interleave.
Send all three sentences: `message` (this event, used when the row is one event),
`message_single` (`{actor}` placeholder; used when the row is left with one person), and
`message_grouped` (`{actor}` and `{others}`). A row is grouped only for an event with a named
person: send `actor_id` 0 and a `group_key` unique to the event for anonymous content, and it
never merges. The container and the items must describe what the link opens: a row keeps its
first event's link, so link to the container.

**Running notices** (a personal record that keeps growing through the week, a progress count):
set `renotify => false` with a `group_key` per period. The first fire creates the row; every
repeat with the same key refreshes that row's text quietly (keeps read state and date, no push).
Without it, repeats merge: the row jumps to the top and alerts again, which is right for
"Aisha and 3 others replied" and wrong for a number that only grows.

### 4.2 Declare types: the one switch

`{prefix}_community_notification_types` (slug => label, description, default_on). Declaring is
what makes BuddyNext read the payload. Ship the payload and the declaration together.

### 4.3 Visibility

`{prefix}_community_notification_visible( array $visible, int $viewer_id, array $targets )`,
return key => bool. Batched per bell page. Reuse the plugin's existing permission checks.
A target with `'item' => true` is one event inside a grouped row (`object_type`/`object_id` = the
item, `actor_id` = the person who did it): answer false when the item is gone, trashed, or by
someone the viewer should not see (banned, blocked). A missing item is NOT visible, unlike a
missing container. The row shrinks to the events that remain and disappears when none does.

### 4.4 Removal

`{prefix}_community_notification_removed( $object_type, $object_id )` on PERMANENT delete only.
Trash and unpublish are visibility. A permanently deleted grouped-row item needs no call:
the `item` target answers false.

## 5. Checklist for any integration PR

- [ ] Manifest read in both plugins; nothing re-implemented that exists.
- [ ] Every rule lives in the data owner; the other side calls it.
- [ ] One helper per concern; Pro calls Free's.
- [ ] Old path deleted in the same release (or a dated removal comment if releases differ).
- [ ] Notification payload on every member-facing notification site; types declared; visibility
      reuses existing checks; removal on permanent delete; own email unchanged.
- [ ] Existing listeners of a changed hook still receive the arguments they registered for.
- [ ] Hooks documented in the plugin's hooks reference; `docs/standards/integration.md` synced.
- [ ] Verified live with both plugins active: one bell row, one email, correct link, per role.

---

## 6. Third-party plugins: support, never dependency

Applies to every plugin and theme that works with a plugin we do not own (BuddyPress, bbPress,
WooCommerce, page builders, membership plugins, themes). We never know what a customer runs, so
**supporting** a plugin and **depending** on it are different things, and only the first is allowed
(owner, 2026-11-08).

| Support (allowed) | Dependency (not allowed) |
|---|---|
| Plugin works fully with only WordPress active | A feature breaks or vanishes without the partner |
| Partner code lives in `integrations/<slug>/`, booted behind one `function_exists` / `class_exists` guard | Partner functions called from core, modules, API or templates |
| Our data stays in our store (roles, meta, tables); the partner gets a mirror synced from ours | The partner's table or meta is the only copy of our data |
| Partner deactivated: mirror stops, nothing fatals, nothing is lost | Deactivating the partner fatals, or orphans our data |
| A partner's UI is cited as a precedent for a pattern | We assume the partner is installed, or copy its code |

### Rules

1. **Core first.** Every flow has a WordPress-core path (core hooks, core screens, core APIs).
   Partner support only adds to it.
2. **One door per partner.** All of a partner's calls live in `integrations/<slug>/`, loaded by one
   guard at boot. Zero partner calls anywhere else in the plugin.
3. **One-way mirror.** We write our data, then sync it to the partner through our own hooks. The
   sync can be re-run (idempotent) and never reads the partner back as the truth.
4. **No forced installs.** No `Requires Plugins` header and no install nag for an optional partner.
5. **Precedent is not dependency.** Citing bbPress, WooCommerce or any plugin for a UX pattern is
   fine. The code still uses core hooks only.

### Checklist for any change that touches a partner, or a flow partners mirror

- [ ] The flow works end to end with **no other plugin active** (browser-verified).
- [ ] Partner function prefixes (`bp_`, `bbp_`, `wc_` ...) appear only under `integrations/` (grep, excluding stubs and tests).
- [ ] The integration boots behind one guard; boot smoke passes with the partner **on and off**.
- [ ] Our data stays correct after the partner is deactivated, then reactivated (the mirror catches up).
- [ ] No `Requires Plugins` entry and no notice for optional partners.
- [ ] Partner support listed in `audit/manifest.json` with its guard, plus the hooks it listens to.
