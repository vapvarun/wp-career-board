# Capabilities and roles

You can decide who posts jobs, applies, moderates and manages settings by choosing a role or granting capabilities. Administrators have every Career Board capability. The custom roles get a focused set.

## Roles

| Role | Slug | What it is for |
|---|---|---|
| Employer | `wcb_employer` | Posts jobs, manages a company profile, reviews applications |
| Candidate | `wcb_candidate` | Applies to jobs, manages a resume |
| Job Moderator | `wcb_board_moderator` | Reviews pending jobs and approves or rejects them |

Banning is not a role. It is a flag on the user. See [Ban an employer](#ban-an-employer).

## Capabilities

| Capability | What it lets the user do |
|---|---|
| `wcb_post_jobs` | Post a job and edit their own jobs |
| `wcb_apply_jobs` | Apply to jobs |
| `wcb_manage_company` | Edit their company profile |
| `wcb_view_applications` | See applications for their jobs |
| `wcb_manage_resume` | Create and edit a resume |
| `wcb_bookmark_jobs` | Save jobs |
| `wcb_withdraw_application` | Withdraw an application |
| `wcb_moderate_jobs` | Approve or reject pending jobs |
| `wcb_access_admin_jobs` | Reach the admin Jobs queue |
| `wcb_manage_settings` | Granted to administrators |
| `wcb_view_analytics` | Granted to administrators |
| `wcb_access_employer_dashboard` | Open the Employer Dashboard |
| `wcb_access_candidate_dashboard` | Open the Candidate Dashboard |

## What each role gets

| Role | Capabilities |
|---|---|
| Administrator | All of the above |
| Employer | `read`, `wcb_post_jobs`, `wcb_manage_company`, `wcb_view_applications`, `wcb_access_employer_dashboard` |
| Candidate | `read`, `wcb_apply_jobs`, `wcb_manage_resume`, `wcb_bookmark_jobs`, `wcb_access_candidate_dashboard`, `wcb_withdraw_application` |
| Job Moderator | `read`, `wcb_moderate_jobs`, `wcb_access_admin_jobs` |

Other roles get none of these. The plugin re-adds missing capabilities to its own roles on load, so a plugin update reaches existing sites without reactivation.

## Give a capability to another role

Use a role manager plugin such as User Role Editor or Members. Edit the role, tick the Career Board capabilities, and save.

To let Editors act as employers, grant `wcb_post_jobs`, `wcb_view_applications`, `wcb_manage_company` and `wcb_access_employer_dashboard`.

## Registration

- On the Employer Registration page, a visitor picks **Find a Job** or the hiring option. Find a Job creates a Candidate account. The hiring option creates an Employer account.
- If the person is already logged in with only the default role, the new role replaces it. If they hold another role, the new role is added.

## Who can apply

By default any logged-in member can apply to jobs and manage a resume, even without the Candidate role. A member who can post jobs but does not hold the apply capability cannot apply.

To reserve applying for members with the candidate capability, go to **Career Board > Settings > Sign-ups** and turn on **Require Candidate Role**. The `wcb_candidate_requires_role` filter does the same in code.

## Ban an employer

1. Go to **Career Board > Employers**.
2. Use the **Ban** row action, or tick several rows and choose **Ban** in the bulk actions.

A banned user:

- Loses every Career Board permission, including posting, applying, managing a resume and opening the dashboards.
- Has their jobs, company page and resume hidden from the site.
- Can still log in.

Use **Unban** on the same screen to reverse it. You cannot ban yourself. See [Moderation](03-moderation.md).

## Permission checks in code

Career Board checks permissions with the WordPress Abilities API. Each ability is backed by a capability:

| Ability | Backing capability |
|---|---|
| `wcb/post-jobs` | `wcb_post_jobs` |
| `wcb/apply-jobs` | `wcb_apply_jobs` |
| `wcb/manage-resume` | `wcb_manage_resume` |
| `wcb/bookmark-jobs` | `wcb_bookmark_jobs` |
| `wcb/withdraw-application` | `wcb_withdraw_application` |
| `wcb/manage-company` | `wcb_manage_company` |
| `wcb/view-applications` | `wcb_view_applications` |
| `wcb/moderate-jobs` | `wcb_moderate_jobs` |
| `wcb/access-employer-dashboard` | `wcb_access_employer_dashboard` |
| `wcb/access-candidate-dashboard` | `wcb_access_candidate_dashboard` |
| `wcb/manage-settings` | `manage_options` |
| `wcb/view-analytics` | `manage_options` |

Users with `manage_options` pass every check. A banned user fails every check.

## Add your own role

```php
add_action( 'init', function () {
    add_role( 'wcb_premium_employer', __( 'Premium Employer', 'my-addon' ), array(
        'read'                          => true,
        'wcb_post_jobs'                 => true,
        'wcb_view_applications'         => true,
        'wcb_manage_company'            => true,
        'wcb_access_employer_dashboard' => true,
    ) );
} );
```

## Troubleshooting permissions

**An employer cannot post a job.** Check that their role has `wcb_post_jobs`:

```bash
wp user get <login> --field=roles
wp user list-caps <login> | grep wcb_
```

**Someone cannot change settings.** Settings need `manage_options`, which Administrators have. Editors do not.

**A candidate cannot apply.** Another plugin may have given them a different role. Check their roles with the command above, then change the role or grant `wcb_apply_jobs`.
