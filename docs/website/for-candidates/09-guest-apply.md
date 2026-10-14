# Apply as a guest

You can apply to a job with just your name, email and a resume, without creating an account. This works unless the site turned on **Require login to apply**. In that case the job page shows **Sign in to apply**.

## Submit an application

1. Open a job page.
2. Click **Apply Now**.
3. Enter **Your Name** and **Your Email**.
4. Click **Click to upload resume** and choose a PDF, DOC or DOCX file. The default size limit is 5 MB, and the site owner can raise it up to 20 MB.
5. Optional: write a cover letter.
6. Click **Submit Application**.

The employer sees your name, email, cover letter and resume file in their dashboard.

## Is a resume required?

It depends on the site. By default a resume is required, and you cannot submit without a file. If the site owner turned the requirement off, the field is marked **(optional)** and you can submit without one.

## Emails you get

- A confirmation email when you apply.
- An email when the employer changes your status, including a "not selected" email if you are not chosen.

## Limits

- Applying to the same job again with the same email within 24 hours does not create a second application; you see the same confirmation as before.
- If your email already belongs to an account, the form asks you to sign in instead (**Sign in to apply**), so the application lands on your account.
- One connection can send 10 guest applications an hour.
- You cannot withdraw a guest application, and you have no dashboard to track it.

## Link guest applications to an account

Your application is stored against your email address. If you later create an account with the same email, your earlier guest applications move to it and appear in **My Applications**. There is nothing to request, but the move waits until the site knows the email is yours. It happens when:

- You open the link in the confirmation email. This is how it works on sites with **Email Verification** on, which is the default on new sites.
- You set your password from an emailed link, for example after a password reset.
- An administrator or WP-CLI creates the account for you. This moves them at once.

On a site with Email Verification off, signing up through the website form does not move them by itself. Use **Lost your password?** once with the same email, or ask the site owner to confirm your address.

Only applications sent from the exact email of the account move. Applications from a guest who registers with a different email stay under the guest email, and the employer still sees them. Since 1.8.1 a guest cannot apply with the email of an existing account (the form asks them to sign in), so sign in before you apply to keep everything in one place.

To get a copy of your data or have it deleted, ask the site owner to run a personal-data request for your email address. Guest applications are found by email. The site owner can follow [GDPR and privacy](../admin-guide/04-gdpr.md).

## Accepted files

| Format | Accepted |
|---|---|
| PDF | Yes |
| DOC | Yes |
| DOCX | Yes |
| ODT, RTF, TXT | No. Save as PDF first |
| Images (JPG, PNG) | No. Convert to PDF |
