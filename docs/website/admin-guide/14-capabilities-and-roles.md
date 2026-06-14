# Capabilities & Roles

WP Career Board ships with **3 custom roles** and **13 custom
capabilities**. Site administrators have every Career Board cap by
default; the custom roles get a focused subset. Use this page to
decide what to grant team members.

## The custom roles

| Role | Slug | Purpose |
|---|---|---|
| **Employer** | `wcb_employer` | Posts jobs, manages a company profile, and reviews applications |
| **Candidate** | `wcb_candidate` | Anyone who applies to jobs and manages a resume |
| **Job Moderator** | `wcb_board_moderator` | Reviews pending jobs and approves/rejects |

**Note on the Job Moderator slug:** the role slug stays
`wcb_board_moderator` for back-compatibility with existing
assignments, but the display label is **Job Moderator** (renamed in
1.4.x because the role moderates jobs - boards are admin-only config
and carry nothing to moderate).

**Banning is not a role.** There is no `wcb_employer_banned` role.
Suspending an employer is a per-user flag (`_wcb_employer_banned`
user-meta) set from the admin Employers screen. See [Banning an
employer](#banning-an-employer) below.

## The 13 capabilities

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
| `wcb_access_admin_jobs` | Reach the admin Jobs queue (granted to moderators + admins) |
| `wcb_view_analytics` | See the analytics dashboard (Pro) |
| `wcb_manage_settings` | Configure Career Board settings, emails, integrations |
| `wcb_access_employer_dashboard` | See the Employer Dashboard page |
| `wcb_access_candidate_dashboard` | See the Candidate Dashboard page |

## Default capability map

| Role | Capabilities granted |
|---|---|
| **Administrator** | All 13 |
| **Employer** (`wcb_employer`) | `read`, `wcb_post_jobs`, `wcb_manage_company`, `wcb_view_applications`, `wcb_access_employer_dashboard` |
| **Candidate** (`wcb_candidate`) | `read`, `wcb_apply_jobs`, `wcb_manage_resume`, `wcb_bookmark_jobs`, `wcb_access_candidate_dashboard`, `wcb_withdraw_application` |
| **Job Moderator** (`wcb_board_moderator`) | `read`, `wcb_moderate_jobs`, `wcb_access_admin_jobs` |
| **Editor / Author** | None by default - grant `wcb_post_jobs` (and related caps) only if you want editorial staff to act as employers |

The roles and admin caps are kept in sync on every load, so cap and
label changes shipped in a plugin update reach existing installs
without re-activation.

## Granting capabilities to other roles

The easiest path is via a role manager plugin (User Role Editor,
Members, etc.):

1. Install your preferred role manager.
2. Edit the target role.
3. Tick the Career Board capabilities you want to grant.
4. Save.

**For "Editor as Employer" - a common editorial scenario:**

Grant the Editor role:
- `wcb_post_jobs`
- `wcb_view_applications`
- `wcb_access_employer_dashboard`
- `wcb_manage_company` (so they can edit their company profile)

That gives editorial staff the ability to post and review jobs without
the broader site-admin access.

## Built-in registration

The plugin's registration flow creates accounts with these roles:

- **Employer registration** - the Employer Registration block (placed
  on the "Employer Registration" page by the Setup Wizard) creates a
  user with the **Employer** (`wcb_employer`) role, which already
  carries `wcb_post_jobs`, `wcb_manage_company`,
  `wcb_view_applications`, and `wcb_access_employer_dashboard`.
- **Candidate registration** - the Candidate Dashboard register tab
  creates a user with the **Candidate** (`wcb_candidate`) role.

By default any logged-in member can apply to jobs and manage a resume
even without the Candidate role - jobs and resumes are commonly a
side-feature of a community site. Turn on **Settings → Job Listings →
Require Candidate Role** (or filter `wcb_candidate_requires_role`) to
reserve the candidate experience for users who hold the candidate cap.

## Banning an employer {#banning-an-employer}

Banning is done from the admin Employers list, not by changing the
user's role:

1. Go to **WP Career Board → Employers**.
2. Use the **Ban** row action on a single employer, or tick several
   rows and choose **Ban** from the bulk-action menu.

This sets the `_wcb_employer_banned` user-meta flag, which the
Abilities layer reads to strip **every** WCB ability from that user
regardless of which caps their role carries.

Effects:

- They lose every WCB ability - cannot post jobs, apply, manage a
  resume, or reach the dashboards.
- Existing published jobs stay live. If you want them down, change the
  job's status to pending or draft separately.
- They can still log in (they keep `read`).

To unban, use the **Unban** row or bulk action on the same screen,
which deletes the meta flag.

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
| `wcb/access-employer-dashboard` | `wcb_access_employer_dashboard` |
| `wcb/access-candidate-dashboard` | `wcb_access_candidate_dashboard` |
| `wcb/view-analytics` | `manage_options` (admin-only; reserved for Pro analytics) |

You only need to grant the underlying capability - the Abilities
layer reads it. The `wcb/manage-settings` and `wcb/view-analytics`
abilities are admin-gated and check `manage_options` directly rather
than a dedicated cap. Both the cap form and `wp_is_ability_granted()`
work in your own theme/plugin code, though `wp_is_ability_granted()`
is the canonical call.

Note: a banned employer (the `_wcb_employer_banned` flag) is denied
every WCB ability regardless of the caps their role holds.

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

The settings screen is gated on `manage_options` (the
`wcb/manage-settings` ability), which Administrator has by default.
An Editor temporarily acting as admin does not have it.

**Candidate registered but can't apply.**

Their role might have been overridden by another plugin's
registration flow. Check `wp user get <login> --field=roles` -
it should be `wcb_candidate`. If it's `subscriber` or `customer`,
either change the role manually or add `wcb_apply_jobs` to
whatever role they got.
