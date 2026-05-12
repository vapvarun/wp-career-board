# Capabilities & Roles

WP Career Board ships with **3 custom roles** and **12 custom
capabilities**. Site administrators have every Career Board cap by
default; the custom roles get a focused subset. Use this page to
decide what to grant team members.

## The custom roles

| Role | Slug | Purpose |
|---|---|---|
| **Candidate** | `wcb_candidate` | Anyone who applies to jobs and manages a resume |
| **Board Moderator** | `wcb_board_moderator` | Reviews pending jobs and approves/rejects |
| **Banned Employer** | `wcb_employer_banned` | Suspended account — can still log in but cannot post |

**Note:** "Employer" is not a separate role in Career Board. Anyone
who has the `wcb_post_jobs` capability is an employer for our
purposes. Many sites assign that cap to the **Editor** role, or
create their own custom role and add it.

## The 12 capabilities

| Capability | What it lets the user do |
|---|---|
| `wcb_post_jobs` | Post a job, edit / republish own jobs |
| `wcb_apply_jobs` | Apply to jobs, submit a resume on the apply form |
| `wcb_manage_company` | Edit the company profile they're attached to |
| `wcb_view_applications` | See incoming applications for their job posts |
| `wcb_manage_resume` | Create / edit / publish a resume (Candidate flow) |
| `wcb_bookmark_jobs` | Save jobs to "My Saved Jobs" |
| `wcb_withdraw_application` | Withdraw an application after submitting |
| `wcb_moderate_jobs` | Approve or reject pending jobs |
| `wcb_view_analytics` | See the analytics dashboard (Pro) |
| `wcb_manage_settings` | Configure Career Board settings, emails, integrations |
| `wcb_access_employer_dashboard` | See the Employer Dashboard page |
| `wcb_access_candidate_dashboard` | See the Candidate Dashboard page |

## Default capability map

| Role | Capabilities granted |
|---|---|
| **Administrator** | All 12 |
| **Editor** | None by default — usually granted `wcb_post_jobs` + `wcb_view_applications` + `wcb_access_employer_dashboard` for editorial sites |
| **Author** | None by default |
| **Candidate** (`wcb_candidate`) | `read`, `wcb_apply_jobs`, `wcb_manage_resume`, `wcb_bookmark_jobs`, `wcb_access_candidate_dashboard`, `wcb_withdraw_application` |
| **Board Moderator** (`wcb_board_moderator`) | `read`, `wcb_moderate_jobs` |
| **Banned Employer** (`wcb_employer_banned`) | None of the Career Board caps. Still has `read` so they can log in and see the suspension message. |

## Granting capabilities to other roles

The easiest path is via a role manager plugin (User Role Editor,
Members, etc.):

1. Install your preferred role manager.
2. Edit the target role.
3. Tick the Career Board capabilities you want to grant.
4. Save.

**For "Editor as Employer" — the most common scenario:**

Grant the Editor role:
- `wcb_post_jobs`
- `wcb_view_applications`
- `wcb_access_employer_dashboard`
- `wcb_manage_company` (so they can edit their company profile)

That gives editorial staff the ability to post and review jobs without
the broader site-admin access.

## Built-in registration

The plugin's registration flow creates accounts with these roles:

- **Employer registration** (`/employer-registration/`) creates a
  user with the `wcb_post_jobs` cap added to whatever role you set
  in **Settings → Registration → Default employer role**. Default
  is "Editor."
- **Candidate registration** (Candidate Dashboard → Register tab)
  creates a user with the `wcb_candidate` role.

You can override the default roles via the
`wcb_employer_default_role` and `wcb_candidate_default_role`
settings (Settings → Registration).

## Banning an employer

Set the user's role to **Banned Employer**
(`wcb_employer_banned`):

```bash
wp user set-role <login> wcb_employer_banned
```

Or via WP Admin → Users → edit → Role dropdown.

Effects:

- They lose `wcb_post_jobs` — cannot create new jobs.
- Existing published jobs stay live (use this carefully — if you
  want them down, change the job's status to `pending` or `draft`
  separately).
- They can still log in, but the dashboard shows a suspension
  message instead of the post-job form.

To unban, set the role back to whatever they had before
(typically Editor).

## How the plugin actually checks permissions

Internally Career Board uses the **WordPress Abilities API**
(`wp_register_ability` + `wp_is_ability_granted`) rather than raw
`current_user_can` calls. Each capability above maps to a
namespaced ability slug:

| Ability slug | Backing capability |
|---|---|
| `wcb/post-jobs` | `wcb_post_jobs` |
| `wcb/apply-jobs` | `wcb_apply_jobs` |
| `wcb/manage-settings` | `wcb_manage_settings` |
| `wcb/moderate-jobs` | `wcb_moderate_jobs` |
| `wcb/manage-resume` | `wcb_manage_resume` |
| `wcb/bookmark-jobs` | `wcb_bookmark_jobs` |
| `wcb/withdraw-application` | `wcb_withdraw_application` |
| `wcb/manage-company` | `wcb_manage_company` |
| `wcb/view-applications` | `wcb_view_applications` |
| `wcb/view-analytics` | `wcb_view_analytics` |

You only need to grant the underlying capability — the Abilities
layer reads it. Both forms work for `current_user_can()` checks
in your own theme/plugin code, though `wp_is_ability_granted()`
is the canonical call.

## Adding your own role

If you want a custom role (e.g. "Premium Employer") with a different
mix:

```php
add_action( 'init', function () {
    add_role( 'wcb_premium_employer', __( 'Premium Employer', 'my-addon' ), array(
        'read'                          => true,
        'wcb_post_jobs'                 => true,
        'wcb_view_applications'         => true,
        'wcb_manage_company'            => true,
        'wcb_access_employer_dashboard' => true,
        'wcb_view_analytics'            => true,  // Pro-only, no-op without Pro
    ));
});
```

To remove the role on uninstall, call `remove_role()` in the same
file (or in your plugin's uninstall hook).

## Troubleshooting permissions

**"You don't have permission to do this" when an employer tries to
post a job.**

Check the employer's user role has `wcb_post_jobs`. The fastest
check:

```bash
wp user get <login> --field=roles
wp user list-caps <login> | grep wcb_
```

**"You don't have permission" when admin tries to change settings.**

The setting check is `wcb_manage_settings`, which Administrator
has by default. If you're an Editor temporarily acting as admin,
you don't have it.

**Candidate registered but can't apply.**

Their role might have been overridden by another plugin's
registration flow. Check `wp user get <login> --field=roles` —
it should be `wcb_candidate`. If it's `subscriber` or `customer`,
either change the role manually or add `wcb_apply_jobs` to
whatever role they got.
