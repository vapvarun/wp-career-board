# Error codes

Every failed request to the `wcb/v1` REST API returns the same body: a `code`, a translated `message` and `data.status` (the HTTP status).

```json
{
    "code": "wcb_already_applied",
    "message": "You have already applied to this job.",
    "data": { "status": 409 }
}
```

Branch on `code` and `data.status`, never on `message`: messages are translated and can change. Some errors add fields to `data`; those are listed in the table. The REST validation layer can also answer with WordPress's own `rest_invalid_param` and `rest_forbidden`.

## Sign-in and permissions

| Code | HTTP | When it is returned |
|---|---|---|
| `wcb_unauthorized` | 401 | The route needs a signed-in user and the request has none |
| `wcb_forbidden` | 403 | The user is signed in but lacks the ability the route checks. See [Capabilities and roles](../admin-guide/14-capabilities-and-roles.md) |
| `wcb_login_required` | 401 | A guest tried to apply while **Require login to apply** is on |
| `wcb_login_failed` | 401 | Wrong username or password on `POST /auth/app-password` |
| `wcb_too_many_attempts` | 429 | Too many failed app sign-ins from one address or for one account |
| `wcb_app_passwords_off` | 403 | App sign-in is off, the site is not on HTTPS, or the account cannot use it |
| `wcb_second_factor` | 409 | The account needs a second sign-in step; use the WordPress approval screen |
| `wcb_account_pending_deletion` | 403 | The account is waiting for deletion; cancel it on the website first |
| `wcb_email_unverified` | none | Returned to the WordPress login when **Email Verification** is on and the member has not confirmed their address. It is a login error, not a REST status |
| `wcb_rate_limited` | 429 | The hourly per-address limit for sign-ups, guest applications or confirmation re-sends. See [Abuse prevention](03-rest-api.md#abuse-prevention) |
| `wcb_captcha_failed` | 400 | The CAPTCHA check failed |
| `wcb_spam` | 400 | The honeypot caught the request |
| `wcb_registration_rejected` | 400 | A `wcb_pre_registration` or `registration_errors` listener refused the sign-up; the message is theirs |

## Jobs

| Code | HTTP | When it is returned |
|---|---|---|
| `wcb_not_found` | 404 | The job, company, candidate or application does not exist, or the caller may not see it. A private candidate profile answers this too |
| `wcb_job_not_found` | 404 | The moderation routes were given a job that does not exist |
| `wcb_missing_title` | 400 | `POST /jobs` without a title, or `PATCH /jobs/{id}` with an empty one. Also the block editor's answer when a job, company, resume or board is published untitled |
| `wcb_missing_name` | 400 | `PATCH /employers/{id}` with an empty company name |
| `wcb_salary_range` | 400 | The minimum salary is above the maximum, after the request. A `PATCH` of one side is checked against the saved other side |
| `wcb_location_required` | 400 | **Require a location** is on and the job has neither a location nor the remote flag |
| `wcb_active_job_limit` | 403 | The employer is at the cap set with `wcb_employer_active_job_limit`. `data.limit` and `data.count` carry the numbers |
| `wcb_insufficient_credits` | 402 | The board charges credits and the balance is too low (Pro). `data` carries `cost`, `balance` and `purchase_url` |
| `wcb_awaiting_payment` | 402 | A moderator approved a job whose employer cannot pay yet; the job stays pending |
| `wcb_job_not_live` | 409 | Only a published job can be closed |
| `wcb_pro_required` | 403 | Resume import was started without Pro |
| `wcb_source_inactive` | 400 | The WP Job Manager plugin the import reads from is not active |

## Applications

| Code | HTTP | When it is returned |
|---|---|---|
| `wcb_job_unavailable` | 400 | The job is not published |
| `wcb_job_deadline_passed` | 400 | The job's deadline has passed |
| `wcb_own_job` | 403 | A user tried to apply to a job they posted |
| `wcb_already_applied` | 409 | The signed-in user already applied to this job |
| `wcb_guest_has_account` | 409 | The guest's email belongs to an account; send them to sign in |
| `wcb_guest_name_required` | 400 | A guest application without a name |
| `wcb_guest_email_invalid` | 400 | A guest application without a valid email |
| `wcb_required_fields` | 400 | A required custom field was left empty on an application, a job create, or an update of a job, company, candidate or resume that sends `custom_fields`. Values already saved count. The message names the fields and `data.fields` lists their keys |
| `wcb_resume_required` | 400 | The site requires a resume and none came with the request |
| `wcb_resume_no_pdf` | 400 | The chosen built resume could not be turned into a PDF to attach (Pro) |
| `wcb_invalid_resume` | 400 | The resume or attachment id does not belong to the applicant |
| `wcb_invalid_file_type` | 400 | The upload is not a PDF, DOC or DOCX |
| `wcb_file_too_large` | 400 | The upload is over **Application Resume File Size (MB)** |
| `wcb_no_file` | 400 | An upload route got no file |
| `wcb_apply_busy` | 409 | The same application is still being saved; a double click lands here |
| `wcb_invalid_status` | 400 | A status outside the allowed list |
| `wcb_application_closed` | 409 | The application already has an outcome (withdrawn, position closed or job removed), so its status is final |
| `wcb_withdraw_disabled` | 403 | **Allow Withdraw** is off |
| `wcb_withdraw_closed` | 409 | The application already has an outcome and can no longer be withdrawn |
| `wcb_note_empty` | 400 | A hiring note with no text |
| `wcb_note_not_found` | 404 | The note was already removed, or belongs to someone else |

## Sign-up and profiles

| Code | HTTP | When it is returned |
|---|---|---|
| `wcb_registration_disabled` | 403 | **Anyone can register** is off in WordPress |
| `wcb_missing_params` | 400 | Required sign-up fields are missing; the message lists them |
| `wcb_weak_password` | 400 | The password is shorter than 8 characters |
| `wcb_email_exists` | 409 | An account with that email already exists |
| `wcb_registration_failed` | 500 | WordPress could not create the account; the message is WordPress's |
| `wcb_already_candidate` | 409 | The signed-in account is already a candidate |
| `wcb_already_employer` | 409 | The signed-in account is already an employer |
| `wcb_employer_account` | 409 | A candidate sign-up was attempted from an employer account |
| `wcb_candidate_account` | 409 | An employer sign-up was attempted from a candidate account |
| `wcb_missing_company` | 400 | Employer sign-up without a company name |
| `wcb_missing_name` | 400 | Company create or update without a name |
| `wcb_invalid_image` | 400 | The upload is not a JPEG, PNG, WebP or GIF image |
| `wcb_privacy_request_failed` | 400 | WordPress could not create the export or erase request |

## Account

| Code | HTTP | When it is returned |
|---|---|---|
| `wcb_invalid_email` | 400 | The new email address is not valid |
| `wcb_email_taken` | 409 | The new email address belongs to another account |
| `wcb_bad_current_password` | 403 | A change of email or password was sent without the right `current_password` |
| `wcb_nothing_to_update` | 400 | `PUT /account` was sent with nothing to change |
| `wcb_delete_confirm_required` | 400 | `DELETE /me` without `confirm: "DELETE"` |
| `wcb_delete_bad_password` | 403 | The password sent with `DELETE /me` is wrong |
| `wcb_delete_forbidden` | 403 | Administrator accounts cannot be deleted from the app |
| `wcb_user_not_found` | 404 | The member to report or block does not exist |
| `wcb_cannot_report_self` | 400 | A member tried to report themself |
| `wcb_cannot_block_self` | 400 | A member tried to block themself |

## Admin routes

| Code | HTTP | When it is returned |
|---|---|---|
| `wcb_email_not_found` | 404 | `POST /admin/emails/test` named an email that is not registered |
| `wcb_industries_empty` | 400 | Saving the industries list with no industries |
| `wcb_industry_in_use` | 400 | An industry that companies use was removed without choosing where they move |
| `wcb_industry_bad_target` | 400 | The industry chosen to receive those companies is not in the saved list |

## Integrations tab installer

The **Settings > Integrations** tab installs other Wbcom plugins. Its failures come back to that screen as a message, not as a REST status. The codes are `wcb_cap` (the user may not install plugins), `wcb_fs` (WordPress has no file access), `wcb_unknown_companion`, `wcb_no_item`, `wcb_no_license`, `wcb_license_invalid`, `wcb_license_activation_failed`, `wcb_store_unreachable`, `wcb_store_bad_response`, `wcb_no_package`, `wcb_download_rejected`, `wcb_install_failed` and `wcb_activate`.

## Use the codes in your own routes

Return a `WP_Error` with a `wcb_`-style prefix of your own and a `status` in `data`, so clients can handle your errors the same way:

```php
return new WP_Error(
    'my_addon_quota_reached',
    __( 'You have used all your slots.', 'my-addon' ),
    array( 'status' => 409 )
);
```
