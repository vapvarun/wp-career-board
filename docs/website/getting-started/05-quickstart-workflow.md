# Day-1 quickstart

You can go from a fresh install to your first job and your first application in five tasks.

## 1. Finish the setup wizard

If you skipped the wizard, go to **Career Board > Settings** and click **Re-run Setup Wizard** at the bottom of the page. The wizard:

- Creates the pages your board needs: Find Jobs, Post a Job, Employer Registration, Employer Dashboard, Candidate Dashboard and Find Companies. A page you already have is kept.
- Can install sample categories, job types, companies and jobs, so you can see the board with content.

If you already ran it, check **Career Board > Settings > Pages** to confirm each page is assigned.

## 2. Add menu items

Open **Appearance > Menus** and add links to the pages you want visitors to reach:

- Jobs: `/find-jobs/`
- Employers: `/post-a-job/` or `/employer-dashboard/`
- Candidates: `/candidate-dashboard/`
- Companies: `/find-companies/`

The paths above are the default page slugs. Use the real page links if you changed them.

## 3. Post your first job

1. Go to `/post-a-job/`.
2. Fill in the form: title, company, description, salary, location and category.
3. Submit. If **Auto-Publish Jobs** is off, the job waits under **Career Board > Jobs** as Pending Review. Approve it.
4. Open `/find-jobs/`. Your job appears in the listing.
5. Click into it to see the page candidates see.

## 4. Apply to it as a test candidate

1. Log out, or open a private window.
2. Register a test account at `/employer-registration/` and choose **Find a Job**. You can also apply as a guest unless **Require login to apply** is on.
3. Apply to the job you posted.
4. Log back in as admin and open **Career Board > Applications**. Your test application is listed.
5. Open it to see what employers see when they review applicants.

## 5. Check moderation, credits and emails

- **Moderation:** in **Career Board > Settings > Jobs**, the **Auto-Publish Jobs** toggle. Leave it off to approve each job yourself, or turn it on to publish jobs straight away.
- **Credits (Pro):** **Career Board > Settings > Credits**. Use it to charge employers for job posts.
- **Emails:** **Career Board > Settings > Emails**. Candidates get an application confirmation email and employers get a new application email.

## Next steps

| If you are running... | Read next |
|---|---|
| A public job board | [Post a job](../for-employers/02-post-a-job.md) |
| A paid job board | [Credit system](../admin-guide/06-credit-system.md) |
| An internal hiring board | [Settings](../admin-guide/01-settings.md) |
| Classic-editor pages | [Page builder embeds](../for-employers/11-page-builder-embeds.md) |

## Troubleshooting

- **Visitors cannot apply without an account:** check **Require login to apply** under **Career Board > Settings > Sign-ups**. When it is on, only signed-in members can apply. If **Require Candidate Role** is also on, only users with the Candidate role can apply.
- **An employer has no Post a Job link:** the account needs the `wcb_post_jobs` capability. Administrators and the Employer role have it.
- **Emails do not arrive:** Career Board sends email through `wp_mail`, like other plugins. Set up an SMTP plugin with a real sending domain.
