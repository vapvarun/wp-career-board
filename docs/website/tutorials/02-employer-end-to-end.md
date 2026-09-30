# Employer end to end

You can run a full hiring round from the employer dashboard: register, set up your company, post a job, review applicants, hire, and close the role. This walkthrough covers each step.

## Step 1 - Register as an employer

Open the employer registration page, usually at `/employer-registration/`. Your site owner may also link it from the menu or from a **Post a Job** button. If you cannot find a link, ask the site owner for the address.

1. Choose **Hire Talent**.
2. Enter your name, email and a password of at least 8 characters.
3. Enter your company name. You can also add its website, industry, size and headquarters.
4. Submit.

If the site asks new members to confirm their email, open the link in the message you receive, then sign in. Otherwise you are signed in straight away and receive a Welcome email.

Registration creates your company profile from the company name.

## Step 2 - Complete your company profile

Applicants see your company profile on every job. Open the **Employer Dashboard** and go to **Company > Profile**. You can set:

- **Company Logo** - a JPEG, PNG, WebP or GIF image. Save the profile first, then upload the logo.
- **Company Name** and **Tagline** - the tagline is a one-line description shown on listings.
- **About the Company**
- **Industry**, **Company Size** and **Company Type**
- **HQ Location**
- **Website**, **LinkedIn** and **X (Twitter)**
- **Founded Year**

The **Live Preview** beside the form shows how the profile looks. Click **Save Profile**. Your public company page lives under `/companies/` on the site. Use **Company > Public Page** to open it.

## Step 3 - Get posting credits, if your board charges for jobs

Free boards post jobs without charge. Charging for job posts and featured listings with credits is a Pro feature. If your board uses credits, your balance shows on the dashboard overview, and the **Credits** section of the dashboard is where you manage it. When your balance is too low to post, the job form shows a **Buy Credits** link if the site owner has set up a purchase page.

For how credits are set up and bought, see the Pro docs page **Credit system**.

## Step 4 - Post your first job

Open **Employer Dashboard > Post a Job**. The form has four steps.

### Job Basics

- **Job Title** - write the title a candidate would search for. "Senior Frontend Engineer" works better than a clever title.
- **Job Description** - open with what the role is, then list responsibilities and requirements. Use the inline toolbar and the block menu for headings, lists and links.

### Job Details

- **Salary Range** - choose the currency, a minimum and maximum, and the period (year, month or hour). Leave it blank to hide salary from candidates.
- **Remote-friendly position** - tick this for remote roles.
- **Application Deadline** - shown for information. It is filled in from the board's listing length and you cannot edit it. Ask the site owner if you need a longer listing.
- **Apply URL** and **Apply Email** - both are optional. Leave them empty to receive applications inside the dashboard. Fill in **Apply URL** to send candidates to your own application system. The job page then shows **Apply on Company Site**. Fill in **Apply Email** to show an address candidates can apply to.

### Classify Your Job

- **Category**, **Job Type** and **Location**. Location is required unless the job is remote. Choose **Other (enter manually)** if your city is not in the list.
- **Experience Level**
- **Skills / Tags** - comma-separated. These help candidates find your job by keyword.

### Preview & Submit

Review the job, go back to fix anything, then submit.

If you have Pro with AI features enabled, the description field has a **Generate with AI** button. Always edit the result. See the Pro docs page **AI features**.

## Step 5 - Approval

Whether a new job publishes at once depends on the site's **Auto-Publish Jobs** setting.

- If it is on, the job is published as soon as you submit.
- If it is off, the job waits as Pending until the site owner or a moderator approves it. You get a **Your job has been approved** email, or **Your job was not approved** if it is rejected. A rejected job shows a **Resubmit** button in **My Jobs**.

## Step 6 - Promote the listing

- Share the job address on your social accounts and in your company channels.
- The site publishes a job feed at `/jobs/feed/`. Each item carries the company, salary, location, job type, category, tags, experience level, deadline, remote flag and apply link or email. Aggregators that read RSS can use it.

## Step 7 - Review applications

Open **Employer Dashboard > Applications**. Pick a job from the list, then pick an applicant. You see:

- The applicant's name and submitted date.
- The current status, which starts as Submitted.
- The cover letter, and answers to any screening questions.
- **View Resume** and **Download Resume**.
- Your private notes and rating. Only your hiring team sees them.

You receive a **New application for your job** email for each application.

Use the **List** and **Board** buttons to switch layout. The Board groups applicants into status columns. Drag a card to change its status, or use the **Move to** menu on the card. Click **Export CSV** to download the applications for the selected job. The file has the application ID, job ID, job title, applicant name and email, status, submitted date, cover letter, resume URL and screening answers.

## Step 8 - Triage

1. Use the filter buttons at the top (**All**, Submitted, **Reviewing**, **Shortlisted**, **Rejected**, **Hired**) to focus on one group. Submitted hides everything you have already moved.
2. Read each application.
3. Set the status with the status menu: **Reviewing** if you might talk to them, **Shortlisted** for a clear yes, **Rejected** for a clear no.

The candidate gets an email for **Reviewing**, **Shortlisted** and **Hired**. **Rejected** sends a separate, gentler email titled with the job name. Candidates see Rejected as **Not selected**. The site owner can edit these emails under **Career Board > Settings > Emails**.

If Pro's AI ranking is on, you can sort by the fit score. Treat it as a guide and read the applications yourself. See the Pro docs page **AI features**.

## Step 9 - Interview

Interviews happen outside the board. Use the five statuses (Submitted, Reviewing, Shortlisted, Rejected, Hired) to track where each candidate is. With Pro, the **Application Pipeline** adds custom stages. See the Pro docs page **Application pipeline**.

## Step 10 - Decide

1. Set the chosen candidate to **Hired**. They get the status email.
2. Send your offer yourself. The status email tells the candidate the status only, not the terms.
3. Set the remaining candidates to **Rejected**.

## Step 11 - Close the role

1. Go to **Employer Dashboard > My Jobs** and click **Close** on the job. The job leaves the listings and stops taking applications. Applicants you have not hired or rejected are told the position is closed.
2. To reopen it later, click **Reopen**.
3. Export the applications with **Export CSV** before you close the role if you want a spreadsheet.

## Step 12 - Find candidates with Pro

With Pro, a public **Find Candidates** page lets employers search candidate resumes. See the Pro docs page **Resume builder**, and its Find candidates section, for how it works. Free does not include a candidate directory.

## Common employer mistakes

- **Posting and not checking in.** Review applications as they arrive.
- **Leaving out salary.** A stated range helps candidates decide to apply.
- **Slow status updates.** Move applications to Reviewing soon after you start reading, so candidates know you have seen them.
- **Rejecting without a message.** The default Rejected email is better than silence.
- **Posting the same job twice.** Edit the existing job instead. Use **Edit** in **My Jobs**.

## Where to go next

- [Candidate end to end](03-candidate-end-to-end.md) - the other side of every step.
- [Monetizing your board](04-monetizing-your-board.md) - for site owners deciding how postings are paid for.
- [Employer troubleshooting](../for-employers/12-troubleshooting.md) - when something does not behave.
